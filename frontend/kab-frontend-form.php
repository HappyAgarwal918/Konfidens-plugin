<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get service set configuration by ID
function kab_get_service_set($set_id) {
    $service_sets = get_option('kab_service_sets', array());
    if (isset($service_sets[$set_id])) {
        return $service_sets[$set_id];
    }
    return null;
}

// Get categories that contain services from the specified service set
function kab_get_categories_by_service_set($set_id) {
    $service_set = kab_get_service_set($set_id);
    if (!$service_set || empty($service_set['service_ids'])) {
        return array();
    }
    
    global $wpdb;
    $location_service_table = $wpdb->prefix . 'kab_location_service';
    $category_table = $wpdb->prefix . 'kab_service_category';
    
    // Get unique category IDs for services in the set
    $service_ids = array_map('sanitize_text_field', $service_set['service_ids']);
    $service_ids_str = "'" . implode("','", $service_ids) . "'";
    
    $category_ids = $wpdb->get_col(
        "SELECT DISTINCT category_id FROM $location_service_table 
         WHERE service_id IN ($service_ids_str) AND category_id IS NOT NULL"
    );
    
    if (empty($category_ids)) {
        return array();
    }
    
    $category_ids_str = implode(',', array_map('intval', $category_ids));
    $categories = $wpdb->get_results(
        "SELECT * FROM $category_table WHERE id IN ($category_ids_str) ORDER BY id ASC"
    );
    
    return $categories;
}

// Get locations associated with services in the specified service set
function kab_get_locations_by_service_set($set_id) {
    $service_set = kab_get_service_set($set_id);
    if (!$service_set || empty($service_set['service_ids'])) {
        return array();
    }
    
    global $wpdb;
    $location_service_table = $wpdb->prefix . 'kab_location_service';
    $location_table = $wpdb->prefix . 'kab_location';
    
    // Get unique location IDs for services in the set
    $service_ids = array_map('sanitize_text_field', $service_set['service_ids']);
    $service_ids_str = "'" . implode("','", $service_ids) . "'";
    
    $location_ids_rows = $wpdb->get_col(
        "SELECT DISTINCT location_ids FROM $location_service_table 
         WHERE service_id IN ($service_ids_str) AND location_ids != '' AND location_ids IS NOT NULL"
    );
    
    if (empty($location_ids_rows)) {
        return array();
    }
    
    // Filter out empty values and get unique IDs (handle comma-separated location_ids)
    $unique_location_ids = array();
    foreach ($location_ids_rows as $loc_ids_str) {
        if (!empty($loc_ids_str)) {
            // Split comma-separated location IDs
            $loc_ids = array_map('trim', explode(',', $loc_ids_str));
            foreach ($loc_ids as $loc_id) {
                if (!empty($loc_id) && !in_array($loc_id, $unique_location_ids)) {
                    $unique_location_ids[] = $loc_id;
                }
            }
        }
    }
    
    if (empty($unique_location_ids)) {
        return array();
    }
    
    $location_ids_str = implode(',', array_map('intval', $unique_location_ids));
    $locations = $wpdb->get_results(
        "SELECT * FROM $location_table WHERE id IN ($location_ids_str) ORDER BY location_name ASC"
    );
    
    return $locations;
}

/**
 * AJAX handler for getting service categories
 */
function kab_get_categories_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check if service set is provided
    $service_set_id = isset($_POST['service_set_id']) ? sanitize_text_field($_POST['service_set_id']) : '';
    
    if (!empty($service_set_id)) {
        // Get filtered categories for service set
        $categories = kab_get_categories_by_service_set($service_set_id);
        $parent_categories = kab_get_service_parent_categories();
        
        if (!empty($categories)) {
            wp_send_json_success(array(
                'categories' => $categories,
                'parent_categories' => $parent_categories
            ));
        } else {
            wp_send_json_error(array('message' => __('No categories available for this service set.', 'konfidens-appointment-booking')));
        }
    } else {
        // Get all service categories
        $categories = kab_get_service_categories();
        
        // Get all parent categories
        $parent_categories = kab_get_service_parent_categories();
        
        if (!empty($categories)) {
            wp_send_json_success(array(
                'categories' => $categories,
                'parent_categories' => $parent_categories
            ));
        } else {
            wp_send_json_error(array('message' => __('No categories available.', 'konfidens-appointment-booking')));
        }
    }
}
add_action('wp_ajax_kab_get_categories', 'kab_get_categories_ajax');
add_action('wp_ajax_nopriv_kab_get_categories', 'kab_get_categories_ajax');

/**
 * AJAX handler for getting services
 */
function kab_get_services() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Try cache first (5 minute cache)
    $cache_key = 'kab_all_services_with_categories';
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        wp_send_json_success($cached);
        return;
    }
    
    // Get all services
    $services = kab_get_services_with_priority();
    
    $result = array(
        'services' => $services
    );
    
    // Cache for 30 minutes
    set_transient($cache_key, $result, 1800);

    if (!empty($services)) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error(array('message' => __('No services available.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_get_services', 'kab_get_services');
add_action('wp_ajax_nopriv_kab_get_services', 'kab_get_services');

// AJAX handler: Get locations for selected category (filtered by service set and therapist if provided)
function kab_get_locations_by_category_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['category_id']) || empty($_POST['category_id'])) {
        wp_send_json_error(array('message' => __('Category ID is required.', 'konfidens-appointment-booking')));
    }
    
    $category_id = intval($_POST['category_id']);
    $service_set_id = isset($_POST['service_set_id']) ? sanitize_text_field($_POST['service_set_id']) : '';
    $therapist_id = isset($_POST['therapist_id']) ? sanitize_text_field($_POST['therapist_id']) : '';
    
    // Get locations by category
    $locations = kab_get_locations_by_category($category_id);
    
    // If service set is provided, filter locations to only those in the set
    if (!empty($service_set_id)) {
        $set_locations = kab_get_locations_by_service_set($service_set_id);
        $set_location_ids = array();
        foreach ($set_locations as $loc) {
            $set_location_ids[] = $loc->id;
        }
        
        // Filter locations to only include those in the service set
        $filtered_locations = array();
        foreach ($locations as $location) {
            if (in_array($location->id, $set_location_ids)) {
                $filtered_locations[] = $location;
            }
        }
        $locations = $filtered_locations;
    }
    
    // If therapist_id is provided, filter locations to only those where the therapist provides a service
    if (!empty($therapist_id)) {
        $filtered_locations = array();
        foreach ($locations as $location) {
            // Get service for this category + location combination
            $service_id = kab_get_service_by_category_location($category_id, $location->id);
            
            if (!empty($service_id)) {
                // Check if therapist provides this service
                $therapists_for_service = kab_get_therapists_for_service($service_id);
                $therapist_provides_service = false;
                
                foreach ($therapists_for_service as $therapist) {
                    if ($therapist['id'] === $therapist_id) {
                        $therapist_provides_service = true;
                        break;
                    }
                }
                
                // Only include location if therapist provides the service
                if ($therapist_provides_service) {
                    $filtered_locations[] = $location;
                }
            }
        }
        $locations = $filtered_locations;
        
        // Also filter by therapist's assigned locations: only show locations the therapist is assigned to
        $therapist_location_ids = kab_get_specialist_locations($therapist_id);
        if ($therapist_location_ids !== '') {
            $ids = array_map('trim', explode(',', $therapist_location_ids));
            $therapist_location_ids_set = array_flip($ids);
            $filtered_locations = array();
            foreach ($locations as $location) {
                if (isset($therapist_location_ids_set[(string) $location->id])) {
                    $filtered_locations[] = $location;
                }
            }
            $locations = $filtered_locations;
        }
    }
    
    // Get all location categories for grouping
    $location_categories = kab_get_location_categories();

    // category_id is already included in the SELECT * result — no extra DB query needed per location

    if (!empty($locations)) {
        wp_send_json_success(array(
            'locations' => $locations,
            'categories' => $location_categories
        ));
    } else {
        wp_send_json_error(array('message' => __('No locations available for this category.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_get_locations_by_category', 'kab_get_locations_by_category_ajax');
add_action('wp_ajax_nopriv_kab_get_locations_by_category', 'kab_get_locations_by_category_ajax');

/**
 * Fetch service by ID using cached data (no direct API call).
 * Checks the filtered (enabled) list first, then falls back to the full unfiltered cache
 * so price lookups work even for services filtered out of the main list.
 */
function kab_fetch_service_from_api($service_id) {
    // Common case: service is in the filtered/enabled list
    foreach (kab_get_services_with_priority() as $s) {
        if ((string) $s['id'] === (string) $service_id) {
            return $s;
        }
    }
    // Fallback: unfiltered cache (covers disabled / non-standard services)
    foreach (kab_get_all_services_raw_cached() as $s) {
        if ((string) $s['id'] === (string) $service_id) {
            return $s;
        }
    }
    return null;
}

/**
 * Return all services from the API (no enabled-only filter), cached for 30 minutes.
 */
function kab_get_all_services_raw_cached() {
    $cache_key = 'kab_all_services_raw';
    $cached = get_transient($cache_key);
    if ($cached !== false && is_array($cached)) {
        return $cached;
    }
    $response = kab_api_request('services', array('clinic_id' => get_option('kab_clinic_id', '')));
    if ($response['success'] && !empty($response['data'])) {
        $services = $response['data'];
        set_transient($cache_key, $services, 1800);
        return $services;
    }
    return array();
}

/**
 * AJAX handler for getting service price from API
 */
function kab_get_service_price_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['service_id']) || empty($_POST['service_id'])) {
        wp_send_json_error(array('message' => __('Service ID is required.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);

    // Use raw (unprocessed) services cache so all original API price fields are intact.
    // kab_get_services_with_priority() preprocesses price to a string which can interfere
    // with the extraction logic below; raw data guarantees the correct format.
    $service = null;
    foreach (kab_get_all_services_raw_cached() as $s) {
        if ((string) $s['id'] === (string) $service_id) {
            $service = $s;
            break;
        }
    }
    if (!$service) {
        wp_send_json_error(array('message' => __('Service not found.', 'konfidens-appointment-booking')));
    }
    
    $extract_price = function($value) {
        $raw_price = '';
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_numeric($item)) {
                    $raw_price = (string) $item;
                    break;
                }
            }
            if (empty($raw_price)) {
                $raw_price = is_array($value) && !empty($value) ? (string) reset($value) : '';
            }
        } elseif (is_object($value)) {
            if (isset($value->value)) {
                $raw_price = (string) $value->value;
            } elseif (isset($value->amount)) {
                $raw_price = (string) $value->amount;
            } elseif (isset($value->price)) {
                $raw_price = (string) $value->price;
            }
        } else {
            $raw_price = (string) $value;
        }
        if (!empty($raw_price) && is_numeric($raw_price)) {
            $numeric_price = floatval($raw_price);
            if ($numeric_price >= 1000 && $numeric_price == floor($numeric_price)) {
                $numeric_price = $numeric_price / 100;
                $numeric_price = rtrim(rtrim(number_format($numeric_price, 2, '.', ''), '0'), '.');
                return (string) $numeric_price;
            }
        }
        return $raw_price;
    };
    
    $price_fields = array('price', 'Price', 'amount', 'Amount');
    $price = '';
    foreach ($price_fields as $field) {
        if (isset($service[$field])) {
            $price = $extract_price($service[$field]);
            if (!empty($price)) {
                break;
            }
        }
    }
    if (empty($price)) {
        foreach ($service as $key => $value) {
            if (stripos($key, 'price') !== false || stripos($key, 'amount') !== false || stripos($key, 'cost') !== false) {
                $extracted = $extract_price($value);
                if (!empty($extracted)) {
                    $price = $extracted;
                    break;
                }
            }
        }
    }
    if (empty($price)) {
        $price = '0';
    }
    wp_send_json_success(array('price' => (string) $price));
}
add_action('wp_ajax_kab_get_service_price', 'kab_get_service_price_ajax');
add_action('wp_ajax_nopriv_kab_get_service_price', 'kab_get_service_price_ajax');

/**
 * AJAX handler for getting service by category and location
 */
function kab_get_service_by_category_location_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['category_id']) || empty($_POST['category_id']) ||
        !isset($_POST['location_id']) || empty($_POST['location_id'])) {
        wp_send_json_error(array('message' => __('Category ID and Location ID are required.', 'konfidens-appointment-booking')));
    }
    
    $category_id = intval($_POST['category_id']);
    $location_id = intval($_POST['location_id']);
    $therapist_id = isset($_POST['therapist_id']) ? sanitize_text_field($_POST['therapist_id']) : '';
    
    // Get service by category and location
    $service_id = kab_get_service_by_category_location($category_id, $location_id);
    
    if (!empty($service_id)) {
        $service = kab_fetch_service_from_api($service_id);

        if ($service) {
            // If therapist_id is provided, validate that the therapist provides this service
            if (!empty($therapist_id)) {
                $therapists_for_service = kab_get_therapists_for_service($service_id);
                $therapist_provides_service = false;

                foreach ($therapists_for_service as $therapist) {
                    if ($therapist['id'] === $therapist_id) {
                        $therapist_provides_service = true;
                        break;
                    }
                }

                if (!$therapist_provides_service) {
                    wp_send_json_error(array(
                        'message' => __('The selected therapist does not provide this service for the selected category and location combination.', 'konfidens-appointment-booking'),
                        'therapist_invalid' => true
                    ));
                    return;
                }
            }

            wp_send_json_success(array('service' => $service));
        } else {
            wp_send_json_error(array('message' => __('Service not found.', 'konfidens-appointment-booking')));
        }
    } else {
        wp_send_json_error(array('message' => __('No service available for this category and location combination.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_get_service_by_category_location', 'kab_get_service_by_category_location_ajax');
add_action('wp_ajax_nopriv_kab_get_service_by_category_location', 'kab_get_service_by_category_location_ajax');

/**
 * AJAX handler for getting locations (kept for backward compatibility)
 */
function kab_get_locations_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['service_id']) || empty($_POST['service_id'])) {
        wp_send_json_error(array('message' => __('Service ID is required.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    
    // Get service locations (comma-separated location IDs)
    $service_location_ids = kab_get_service_locations($service_id);
    
    if (!empty($service_location_ids)) {
        // Batch-load all locations in one query instead of N individual queries
        $location_ids_array = array_filter(array_map('intval', array_map('trim', explode(',', $service_location_ids))));

        if (!empty($location_ids_array)) {
            global $wpdb;
            $loc_table = $wpdb->prefix . 'kab_location';
            $ids_str = implode(',', $location_ids_array);
            $locations = $wpdb->get_results("SELECT * FROM $loc_table WHERE id IN ($ids_str) ORDER BY location_name ASC");
        } else {
            $locations = array();
        }

        if (!empty($locations)) {
            $categories = kab_get_location_categories();
            // category_id is already included in SELECT * — no extra DB query per location
            wp_send_json_success(array(
                'locations' => $locations,
                'categories' => $categories
            ));
        } else {
            wp_send_json_error(array('message' => __('No locations available for this service.', 'konfidens-appointment-booking')));
        }
    } else {
        wp_send_json_error(array('message' => __('No locations available for this service.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_get_locations', 'kab_get_locations_ajax');
add_action('wp_ajax_nopriv_kab_get_locations', 'kab_get_locations_ajax');

// AJAX handler: Get specialists/therapists for selected service
function kab_get_specialists_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['service_id']) || empty($_POST['service_id'])) {
        wp_send_json_error(array('message' => __('Service ID is required.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
    $specialist_id = isset($_POST['specialist_id']) ? sanitize_text_field($_POST['specialist_id']) : '';
    
    // Get specialists
    if ($location_id > 0) {
        $specialists = kab_get_therapists_for_service_location($service_id, $location_id);
    } else {
        $specialists = kab_get_therapists_for_service($service_id);
    }
    
    // Process specialist data to ensure image URLs are properly formatted
    if (!empty($specialists)) {
        // Batch-load tags and profession in one DB query instead of N queries
        $specialist_ids = array_column($specialists, 'id');
        $db_data = kab_get_specialists_db_data_batch($specialist_ids);

        foreach ($specialists as $key => $specialist) {
            if (isset($specialist['profile_image'])) {
                $specialists[$key]['image_url'] = $specialist['profile_image'];
            } elseif (isset($specialist['profilepicture'])) {
                $specialists[$key]['image_url'] = 'https://images-files.konfidens.com/practitioners/profilepicture/' . $specialist['profilepicture'];
            } elseif (isset($specialist['image']) && strpos($specialist['image'], 'http') !== 0) {
                $specialists[$key]['image_url'] = 'https://images-files.konfidens.com/practitioners/profilepicture/' . $specialist['image'];
            }

            $sid = $specialist['id'];
            if (isset($db_data[$sid])) {
                if (!empty($db_data[$sid]['tags'])) {
                    $specialists[$key]['stored_tags'] = $db_data[$sid]['tags'];
                }
                if ($db_data[$sid]['profession'] !== '') {
                    $specialists[$key]['profession'] = $db_data[$sid]['profession'];
                }
            }
        }
    }

    // If specialist_id is provided, filter to only that specialist
    if (!empty($specialist_id) && !empty($specialists)) {
        $filtered_specialists = array();
        
        foreach ($specialists as $specialist) {
            if ($specialist['id'] === $specialist_id) {
                $filtered_specialists[] = $specialist;
                break;
            }
        }
        
        $specialists = $filtered_specialists;
    }
    
    if (!empty($specialists)) {
        wp_send_json_success(array('specialists' => $specialists));
    } else {
        wp_send_json_error(array('message' => __('No specialists available for this service.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_get_specialists', 'kab_get_specialists_ajax');
add_action('wp_ajax_nopriv_kab_get_specialists', 'kab_get_specialists_ajax');

// AJAX handler: Get all therapists (filtered by service set if provided) - for therapist-first form Step 0
function kab_get_all_therapists_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check if service set is provided
    $service_set_id = isset($_POST['service_set_id']) ? sanitize_text_field($_POST['service_set_id']) : '';
    $service_set_service_ids = array();
    
    if (!empty($service_set_id)) {
        $service_set = kab_get_service_set($service_set_id);
        if ($service_set && !empty($service_set['service_ids'])) {
            $service_set_service_ids = $service_set['service_ids'];
        }
    }
    
    // Get all therapists (returns associative array with therapist IDs as keys)
    $all_therapists = kab_get_all_therapists();
    
    // If service set is provided, filter therapists to only those who provide at least one service in the set
    $therapists = $all_therapists;
    if (!empty($service_set_id) && !empty($service_set_service_ids)) {
        // Get all services
        $all_services = kab_get_services_with_priority();
        
        // Build a map of therapist_id => array of service_ids they provide
        $therapist_service_map = array();
        
        foreach ($all_services as $service) {
            $service_id = $service['id'];
            
            // Only check services that are in the service set
            if (!in_array($service_id, $service_set_service_ids)) {
                continue;
            }
            
            // Get therapists for this service
            $service_therapists = kab_get_therapists_for_service($service_id);
            
            foreach ($service_therapists as $therapist) {
                $tid = $therapist['id'];
                if (!isset($therapist_service_map[$tid])) {
                    $therapist_service_map[$tid] = array();
                }
                if (!in_array($service_id, $therapist_service_map[$tid])) {
                    $therapist_service_map[$tid][] = $service_id;
                }
            }
        }
        
        // Filter therapists to only those in the map (they provide at least one service from the set)
        $filtered_therapists = array();
        foreach ($all_therapists as $therapist_id => $therapist) {
            if (isset($therapist_service_map[$therapist_id])) {
                $filtered_therapists[$therapist_id] = $therapist;
            }
        }
        $therapists = $filtered_therapists;
    }
    
    // Process therapist data to ensure image URLs are properly formatted
    $therapists_array = array();
    if (!empty($therapists)) {
        // Resolve IDs first, then batch-load DB data in one query
        $resolved = array();
        foreach ($therapists as $tid => $t) {
            if (!isset($t['id'])) {
                $t['id'] = $tid;
            }
            $resolved[] = $t;
        }
        $db_data = kab_get_specialists_db_data_batch(array_column($resolved, 'id'));

        foreach ($resolved as $therapist) {
            if (isset($therapist['profile_image'])) {
                $therapist['image_url'] = $therapist['profile_image'];
            } elseif (isset($therapist['profilepicture'])) {
                $therapist['image_url'] = 'https://images-files.konfidens.com/practitioners/profilepicture/' . $therapist['profilepicture'];
            } elseif (isset($therapist['image']) && strpos($therapist['image'], 'http') !== 0) {
                $therapist['image_url'] = 'https://images-files.konfidens.com/practitioners/profilepicture/' . $therapist['image'];
            }

            $sid = $therapist['id'];
            if (isset($db_data[$sid])) {
                if (!empty($db_data[$sid]['tags'])) {
                    $therapist['stored_tags'] = $db_data[$sid]['tags'];
                }
                if ($db_data[$sid]['profession'] !== '') {
                    $therapist['profession'] = $db_data[$sid]['profession'];
                }
            }

            $therapist['has_locations'] = true;
            $therapist['location_ids'] = array();

            $therapists_array[] = $therapist;
        }
    }
    
    if (!empty($therapists_array)) {
        wp_send_json_success(array('therapists' => $therapists_array));
    } else {
        $error_msg = !empty($service_set_id)
            ? __('No therapists available for the selected service set.', 'konfidens-appointment-booking')
            : __('No therapists available.', 'konfidens-appointment-booking');
        wp_send_json_error(array('message' => $error_msg));
    }
}
add_action('wp_ajax_kab_get_all_therapists', 'kab_get_all_therapists_ajax');
add_action('wp_ajax_nopriv_kab_get_all_therapists', 'kab_get_all_therapists_ajax');

/**
 * AJAX handler for getting services for a specific therapist
 * Optimized to reduce API calls using caching
 */
function kab_get_services_for_therapist_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['therapist_id']) || empty($_POST['therapist_id'])) {
        wp_send_json_error(array('message' => __('Therapist ID is required.', 'konfidens-appointment-booking')));
    }
    
    $therapist_id = sanitize_text_field($_POST['therapist_id']);
    
    // Try to use cache first (5 minute cache)
    $cache_key = 'kab_therapist_services_' . md5($therapist_id);
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        wp_send_json_success($cached);
        return;
    }
    
    // Get all services (single API call)
    $all_services = kab_get_services_with_priority();
    
    if (empty($all_services)) {
        wp_send_json_error(array('message' => __('No services available.', 'konfidens-appointment-booking')));
        return;
    }
    
    // Build a reverse map: therapist_id => array of service_ids
    // This is more efficient than checking each service
    $therapist_service_map_key = 'kab_therapist_service_map';
    $therapist_service_map = get_transient($therapist_service_map_key);
    
    if ($therapist_service_map === false) {
        // Build the map from scratch (cache miss)
        $therapist_service_map = array();
        
        // Get therapists for each service, but cache individual service-therapist mappings
        foreach ($all_services as $service) {
            $service_id = $service['id'];
            
            // Check cache for this service's therapists
            $therapist_cache_key = 'kab_service_therapists_' . md5($service_id);
            $cached_therapists = get_transient($therapist_cache_key);
            
            if ($cached_therapists === false) {
                // Get therapists for this service
                $service_therapists = kab_get_therapists_for_service($service_id);
                
                // Cache for 30 minutes
                set_transient($therapist_cache_key, $service_therapists, 1800);
            } else {
                $service_therapists = $cached_therapists;
            }
            
            // Build reverse map: therapist_id => service_ids[]
            foreach ($service_therapists as $therapist) {
                $tid = $therapist['id'];
                if (!isset($therapist_service_map[$tid])) {
                    $therapist_service_map[$tid] = array();
                }
                $therapist_service_map[$tid][] = $service_id;
            }
        }
        
        // Cache the reverse map for 30 minutes
        set_transient($therapist_service_map_key, $therapist_service_map, 1800);
    }
    
    // Get service IDs for this therapist from the reverse map
    $therapist_service_ids = isset($therapist_service_map[$therapist_id]) ? $therapist_service_map[$therapist_id] : array();
    
    // Check if service set is provided
    $service_set_id = isset($_POST['service_set_id']) ? sanitize_text_field($_POST['service_set_id']) : '';
    $service_set_service_ids = array();
    
    if (!empty($service_set_id)) {
        $service_set = kab_get_service_set($service_set_id);
        if ($service_set && !empty($service_set['service_ids'])) {
            $service_set_service_ids = $service_set['service_ids'];
        }
    }
    
    // Filter services: must be in therapist's services AND (if service set provided) in service set
    $therapist_services = array();
    
    if (!empty($therapist_service_ids)) {
        // Create a lookup array for faster filtering
        $therapist_service_ids_lookup = array_flip($therapist_service_ids);
        
        // If service set is provided, also create lookup for service set
        $service_set_lookup = array();
        if (!empty($service_set_service_ids)) {
            $service_set_lookup = array_flip($service_set_service_ids);
        }
        
        foreach ($all_services as $service) {
            // Service must be in therapist's services
            if (isset($therapist_service_ids_lookup[$service['id']])) {
                // If service set is provided, service must also be in the set
                if (empty($service_set_id) || isset($service_set_lookup[$service['id']])) {
                    $therapist_services[] = $service;
                }
            }
        }
    }
    
    $result = array(
        'services' => $therapist_services
    );
    
    // Cache the result for 30 minutes (but don't cache if service set is used, as it's dynamic)
    if (empty($service_set_id)) {
        set_transient($cache_key, $result, 1800);
    }
    
    if (!empty($therapist_services)) {
        wp_send_json_success($result);
    } else {
        $error_msg = !empty($service_set_id) 
            ? __('No services available for this therapist in the selected service set.', 'konfidens-appointment-booking')
            : __('No services available for this therapist.', 'konfidens-appointment-booking');
        wp_send_json_error(array('message' => $error_msg));
    }
}
add_action('wp_ajax_kab_get_services_for_therapist', 'kab_get_services_for_therapist_ajax');
add_action('wp_ajax_nopriv_kab_get_services_for_therapist', 'kab_get_services_for_therapist_ajax');

/**
 * AJAX handler for getting categories for a specific therapist
 * Only returns categories that are assigned to services available for the therapist
 */
function kab_get_categories_for_therapist_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['therapist_id']) || empty($_POST['therapist_id'])) {
        wp_send_json_error(array('message' => __('Therapist ID is required.', 'konfidens-appointment-booking')));
    }
    
    $therapist_id = sanitize_text_field($_POST['therapist_id']);
    $service_set_id = isset($_POST['service_set_id']) ? sanitize_text_field($_POST['service_set_id']) : '';
    
    // Get all services
    $all_services = kab_get_services_with_priority();
    
    if (empty($all_services)) {
        wp_send_json_error(array('message' => __('No services available.', 'konfidens-appointment-booking')));
        return;
    }
    
    // Get service set service IDs if provided
    $service_set_service_ids = array();
    if (!empty($service_set_id)) {
        $service_set = kab_get_service_set($service_set_id);
        if ($service_set && !empty($service_set['service_ids'])) {
            $service_set_service_ids = $service_set['service_ids'];
        }
    }

    // Use the shared therapist-service map (cached) to avoid N API calls per service
    $therapist_service_map_key = 'kab_therapist_service_map';
    $therapist_service_map = get_transient($therapist_service_map_key);

    if ($therapist_service_map === false) {
        // Build map from individual per-service transients (warm cache = fast; cold = N API calls once)
        $therapist_service_map = array();
        foreach ($all_services as $service) {
            $sid = $service['id'];
            $service_therapists = kab_get_therapists_for_service($sid);
            foreach ($service_therapists as $t) {
                $tid = $t['id'];
                if (!isset($therapist_service_map[$tid])) {
                    $therapist_service_map[$tid] = array();
                }
                $therapist_service_map[$tid][] = $sid;
            }
        }
        set_transient($therapist_service_map_key, $therapist_service_map, 1800);
    }

    $all_therapist_service_ids = isset($therapist_service_map[$therapist_id]) ? $therapist_service_map[$therapist_id] : array();

    // Apply service-set filter
    if (!empty($service_set_service_ids)) {
        $therapist_service_ids = array_values(array_intersect($all_therapist_service_ids, $service_set_service_ids));
    } else {
        $therapist_service_ids = $all_therapist_service_ids;
    }

    if (empty($therapist_service_ids)) {
        wp_send_json_success(array(
            'categories' => array(),
            'parent_categories' => array()
        ));
        return;
    }

    // Batch-load category IDs for all therapist services in one query
    global $wpdb;
    $location_service_table = $wpdb->prefix . 'kab_location_service';
    $sids_str = implode(',', array_map(function($s) use ($wpdb) { return $wpdb->prepare('%s', $s); }, $therapist_service_ids));
    $category_rows = $wpdb->get_results(
        "SELECT DISTINCT category_id FROM $location_service_table WHERE service_id IN ($sids_str) AND category_id IS NOT NULL"
    );
    $category_ids = array();
    foreach ($category_rows as $row) {
        $category_ids[] = intval($row->category_id);
    }
    
    if (empty($category_ids)) {
        wp_send_json_success(array(
            'categories' => array(),
            'parent_categories' => array()
        ));
        return;
    }
    
    // Get categories
    $category_table = $wpdb->prefix . 'kab_service_category';
    $category_ids_str = implode(',', array_map('intval', $category_ids));
    $categories = $wpdb->get_results(
        "SELECT * FROM $category_table WHERE id IN ($category_ids_str) ORDER BY id ASC"
    );
    
    // Get parent categories for these categories
    $parent_category_ids = array();
    foreach ($categories as $category) {
        if ($category->parent_category_id && !in_array($category->parent_category_id, $parent_category_ids)) {
            $parent_category_ids[] = $category->parent_category_id;
        }
    }
    
    $parent_categories = array();
    if (!empty($parent_category_ids)) {
        $parent_category_table = $wpdb->prefix . 'kab_service_parent_category';
        $parent_ids_str = implode(',', array_map('intval', $parent_category_ids));
        $parent_categories = $wpdb->get_results(
            "SELECT * FROM $parent_category_table WHERE id IN ($parent_ids_str) ORDER BY id ASC"
        );
    }
    
    wp_send_json_success(array(
        'categories' => $categories,
        'parent_categories' => $parent_categories
    ));
}
add_action('wp_ajax_kab_get_categories_for_therapist', 'kab_get_categories_for_therapist_ajax');
add_action('wp_ajax_nopriv_kab_get_categories_for_therapist', 'kab_get_categories_for_therapist_ajax');

/**
 * AJAX handler for getting specialist locations
 * Note: Location assignment removed - returns empty array for backward compatibility
 */
function kab_get_specialist_locations_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
        wp_send_json_error(array('message' => __('Specialist ID is required.', 'konfidens-appointment-booking')));
    }
    
    $specialist_id = sanitize_text_field($_POST['specialist_id']);
    
    // Location assignment removed - return empty array (no location restrictions)
    wp_send_json_success(array('location_ids' => array()));
}
add_action('wp_ajax_kab_get_specialist_locations', 'kab_get_specialist_locations_ajax');
add_action('wp_ajax_nopriv_kab_get_specialist_locations', 'kab_get_specialist_locations_ajax');

/**
 * AJAX handler for getting locations for a specific therapist and service
 */
function kab_get_locations_for_therapist_service_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['service_id']) || empty($_POST['service_id']) ||
        !isset($_POST['therapist_id']) || empty($_POST['therapist_id'])) {
        wp_send_json_error(array('message' => __('Service ID and Therapist ID are required.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    $therapist_id = sanitize_text_field($_POST['therapist_id']);
    
    // Get service locations (comma-separated location IDs, therapist location filtering removed)
    $service_location_ids = kab_get_service_locations($service_id);

    if (!empty($service_location_ids)) {
        // Batch-load all locations in one query
        $location_ids_array = array_filter(array_map('intval', array_map('trim', explode(',', $service_location_ids))));

        if (!empty($location_ids_array)) {
            global $wpdb;
            $loc_table = $wpdb->prefix . 'kab_location';
            $ids_str = implode(',', $location_ids_array);
            $locations = $wpdb->get_results("SELECT * FROM $loc_table WHERE id IN ($ids_str) ORDER BY location_name ASC");
        } else {
            $locations = array();
        }

        if (!empty($locations)) {
            $categories = kab_get_location_categories();
            // category_id is already included in SELECT * — no extra DB query per location
            wp_send_json_success(array(
                'locations' => $locations,
                'categories' => $categories
            ));
        } else {
            wp_send_json_error(array('message' => __('No locations available for this service.', 'konfidens-appointment-booking')));
        }
    } else {
        wp_send_json_error(array('message' => __('No locations available for this service.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_get_locations_for_therapist_service', 'kab_get_locations_for_therapist_service_ajax');
add_action('wp_ajax_nopriv_kab_get_locations_for_therapist_service', 'kab_get_locations_for_therapist_service_ajax');

/**
 * AJAX handler for getting available dates
 */
function kab_get_available_dates() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['service_id']) || empty($_POST['service_id']) ||
        !isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    $specialist_id = sanitize_text_field($_POST['specialist_id']);
    
    // Get dates for next 30 days - use the same format as the old plugin
    $from_date = date('Y-m-d\TH:i:s\Z', strtotime('now'));
    $to_date = date('Y-m-d\TH:i:s\Z', strtotime('+30 days'));
    
    // Get timeslots from API
    $timeslots_response = kab_api_request('timeslots', array(
        'clinic_id' => get_option('kab_clinic_id', ''),
        'from_date' => $from_date,
        'to_date' => $to_date,
        'service_id' => $service_id,
        'specialist_id' => $specialist_id
    ));
    
    if ($timeslots_response['success'] && !empty($timeslots_response['data'])) {
        $timeslots = $timeslots_response['data'];
        $dates = array();
        $selected_date = '';
        $html_booking_slot = '';
        
        // Process dates from timeslots
        foreach ($timeslots as $timeslot) {
            // Handle both array and string formats
            if (is_array($timeslot) && isset($timeslot['date'])) {
                $date = $timeslot['date'];
            } else {
                $date = explode('T', $timeslot)[0];
            }
            
            if (!in_array($date, $dates)) {
                $dates[] = $date;
            }
        }
        
        // Set the first available date as selected
        if (!empty($dates)) {
            $selected_date = $dates[0];

            // Filter slots for the first date from the already-fetched 30-day response (no second API call)
            $html_booking_slot = '';
            foreach ($timeslots as $slot) {
                if (is_array($slot)) {
                    if (!isset($slot['time_from']) || !isset($slot['booking_token'])) continue;
                    $slot_date = isset($slot['date']) ? $slot['date'] : explode('T', $slot['time_from'])[0];
                    if ($slot_date !== $selected_date) continue;
                    $time = date('H:i', strtotime($slot['time_from']));
                    $html_booking_slot .= "<div class='kab-time-slot' data-timeslot='" . esc_attr($slot['booking_token']) . "'>" . esc_html($time) . "</div>";
                } else {
                    if (strpos($slot, $selected_date) !== 0) continue;
                    $time = substr(explode('T', $slot)[1], 0, 5);
                    $html_booking_slot .= "<div class='kab-time-slot' data-timeslot='" . esc_attr($slot) . "'>" . esc_html($time) . "</div>";
                }
            }
        }
        
        // Return both dates array and the selected date's time slots
        wp_send_json_success(array(
            'dates' => $dates,
            'selected_date' => $selected_date,
            'html_booking_slot' => $html_booking_slot
        ));
    } else {
        wp_send_json_error(array('message' => __('No available dates found.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_get_available_dates', 'kab_get_available_dates');
add_action('wp_ajax_nopriv_kab_get_available_dates', 'kab_get_available_dates');

// AJAX handler: Get available time slots for selected date, service, and specialist
function kab_get_timeslots_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    if (!isset($_POST['service_id']) || empty($_POST['service_id']) ||
        !isset($_POST['specialist_id']) || empty($_POST['specialist_id']) ||
        !isset($_POST['from_date']) || empty($_POST['from_date'])) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    $specialist_id = sanitize_text_field($_POST['specialist_id']);
    $selected_date = sanitize_text_field($_POST['from_date']);
    
    // Format dates correctly for the API - same as old plugin
    $from_date = date('Y-m-d\T00:00:00\Z', strtotime($selected_date));
    $to_date = date('Y-m-d\T23:59:59\Z', strtotime($selected_date));
    
    // Get timeslots from API
    $timeslots_response = kab_api_request('timeslots', array(
        'clinic_id' => get_option('kab_clinic_id', ''),
        'from_date' => $from_date,
        'to_date' => $to_date,
        'service_id' => $service_id,
        'specialist_id' => $specialist_id
    ));
    
    $html_response = '';
    
    if ($timeslots_response['success'] && !empty($timeslots_response['data'])) {
        $decoded_response = $timeslots_response['data'];
        
        if (!empty($decoded_response)) {
            // Don't wrap in kab-time-slots div, just return the slots directly
            
            foreach ($decoded_response as $slot) {
                if (is_array($slot)) {
                    // Old format
                    if (isset($slot['time_from']) && isset($slot['booking_token'])) {
                        $time = date('H:i', strtotime($slot['time_from']));
                        $slot_value = $slot['booking_token'];
                        $html_response .= "<div class='kab-time-slot' data-timeslot='" . esc_attr($slot_value) . "'>" . esc_html($time) . "</div>";
                    }
                } else {
                    // New format
                    $time = explode('T', $slot)[1];
                    $time = substr($time, 0, 5);
                    $html_response .= "<div class='kab-time-slot' data-timeslot='" . esc_attr($slot) . "'>" . esc_html($time) . "</div>";
                }
            }
            
            // Return HTML directly like the old plugin
            echo $html_response;
            die();
        }
    }
    
    // If we reach here, there was an error or no slots available
    echo "<div class='kab-no-time-slots'>No available times for this date. Please select another date.</div>";
    die();
}
add_action('wp_ajax_kab_get_timeslots', 'kab_get_timeslots_ajax');
add_action('wp_ajax_nopriv_kab_get_timeslots', 'kab_get_timeslots_ajax');

// AJAX handler: Create booking via Konfidens API and save to local database
function kab_create_booking() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking'), 'code' => 'security_check_failed'));
    }
    
    // Check required fields
    $required_fields = array(
        'service_id' => __('Service is required.', 'konfidens-appointment-booking'),
        'specialist_id' => __('Specialist is required.', 'konfidens-appointment-booking'),
        'timeslot' => __('Time slot is required.', 'konfidens-appointment-booking'),
        'first_name' => __('First name is required.', 'konfidens-appointment-booking'),
        'email' => __('Email is required.', 'konfidens-appointment-booking'),
        'phone' => __('Phone number is required.', 'konfidens-appointment-booking')
    );
    
    // Last name is optional (for therapist-first form)
    
    foreach ($required_fields as $field => $message) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            wp_send_json_error(array('message' => $message));
        }
    }
    
    // Validate email
    if (!is_email($_POST['email'])) {
        wp_send_json_error(array('message' => __('Please enter a valid email address.', 'konfidens-appointment-booking')));
    }
    
    // Verify reCAPTCHA if enabled
    if (get_option('kab_enable_recaptcha', false)) {
        $recaptcha_secret = get_option('kab_recaptcha_secret_key', '');
        $recaptcha_token = isset($_POST['recaptcha_token']) ? $_POST['recaptcha_token'] : '';
        
        if (!empty($recaptcha_secret) && !empty($recaptcha_token)) {
            $verify_response = wp_remote_post(
                'https://www.google.com/recaptcha/api/siteverify',
                array(
                    'body' => array(
                        'secret' => $recaptcha_secret,
                        'response' => $recaptcha_token
                    )
                )
            );
            
            if (is_wp_error($verify_response)) {
                wp_send_json_error(array('message' => __('reCAPTCHA verification failed. Please try again.', 'konfidens-appointment-booking')));
            }
            
            $verify_data = json_decode(wp_remote_retrieve_body($verify_response), true);
            
            if (!isset($verify_data['success']) || $verify_data['success'] !== true) {
                wp_send_json_error(array('message' => __('reCAPTCHA verification failed. Please try again.', 'konfidens-appointment-booking')));
            }
        }
    }
    
    // Prepare booking data
    $service_id = sanitize_text_field($_POST['service_id']);
    $specialist_id = sanitize_text_field($_POST['specialist_id']);
    $timeslot = sanitize_text_field($_POST['timeslot']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
    
    // Get service and specialist names
    $service = kab_get_service_by_id($service_id);
    $specialist = kab_get_therapist_by_id($specialist_id);
    
    $service_name = isset($service['name']) ? $service['name'] : '';
    $specialist_name = isset($specialist['name']) ? $specialist['name'] : '';
    
    // The timeslot is actually a booking_token (JWT token from API)
    // We need to extract the actual datetime from it for local storage
    $booking_token = $timeslot;
    
    // Try to decode the booking_token to get the actual datetime
    // The token is base64 encoded JSON with startsAt field
    $timeslot_date = '';
    $timeslot_time = '';
    if (!empty($booking_token)) {
        // Try to decode the JWT token (it's base64 encoded JSON)
        $decoded = base64_decode($booking_token, true);
        if ($decoded !== false) {
            $token_data = json_decode($decoded, true);
            if (isset($token_data['startsAt'])) {
                $starts_at = $token_data['startsAt'];
                $timeslot_parts = explode('T', $starts_at);
                $timeslot_date = isset($timeslot_parts[0]) ? $timeslot_parts[0] : '';
                $timeslot_time = isset($timeslot_parts[1]) ? substr($timeslot_parts[1], 0, 5) : '';
            }
        }
        
        // Fallback: if decoding fails, try to parse as ISO datetime string
        if (empty($timeslot_date) && strpos($booking_token, 'T') !== false) {
            $timeslot_parts = explode('T', $booking_token);
            $timeslot_date = isset($timeslot_parts[0]) ? $timeslot_parts[0] : '';
            $timeslot_time = isset($timeslot_parts[1]) ? substr($timeslot_parts[1], 0, 5) : '';
        }
    }
    
    // Create booking in API - match the old plugin format exactly
    // The old plugin only sends booking_token and patient_data (no service_id, specialist_id, location_id, or clinic_id)
    // The booking_token already contains all necessary information encoded in it
    $booking_data = array(
        'booking_token' => $booking_token, // Only booking_token, no other IDs needed
        'patient_data' => array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'mobile_number' => $phone // Old plugin uses 'mobile_number' instead of 'phone'
            // Notes are not included in the old plugin format
        )
    );
    
    $booking_response = kab_api_request('bookings', $booking_data, 'POST');
    
    if (isset($booking_response['data']) && is_array($booking_response['data'])) {
        // Check for validation errors
        if (isset($booking_response['data']['errors'])) {
        }
    }
    
    if ($booking_response['success'] && !empty($booking_response['data'])) {
        // Get booking ID from response - old plugin expects 'booking_id' field
        $booking_id = '';
        $patient_data = null;
        
        if (is_array($booking_response['data'])) {
            // Check for booking_id (old plugin format)
            $booking_id = isset($booking_response['data']['booking_id']) ? $booking_response['data']['booking_id'] : '';
            // Also check for 'id' as fallback
            if (empty($booking_id) && isset($booking_response['data']['id'])) {
                $booking_id = $booking_response['data']['id'];
            }
            // Get patient_data if available
            $patient_data = isset($booking_response['data']['patient_data']) ? $booking_response['data']['patient_data'] : null;
        } elseif (is_string($booking_response['data'])) {
            // If data is a string, try to decode it
            $decoded = json_decode($booking_response['data'], true);
            if ($decoded) {
                $booking_id = isset($decoded['booking_id']) ? $decoded['booking_id'] : (isset($decoded['id']) ? $decoded['id'] : '');
                $patient_data = isset($decoded['patient_data']) ? $decoded['patient_data'] : null;
            }
        }
        
        if (empty($booking_id)) {
            wp_send_json_error(array('message' => __('Booking created but could not retrieve booking ID. Please contact support.', 'konfidens-appointment-booking')));
            return;
        }
        
        // Save booking in local database
        $local_booking_data = array(
            'service_ids' => $service_id,
            'service_name' => $service_name,
            'specialist_ids' => $specialist_id,
            'specialist_name' => $specialist_name,
            'timeslot_date' => $timeslot_date,
            'timeslot_time' => $timeslot_time,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'booking_id' => $booking_id,
            'booked_from' => 1 // 1 = form
        );
        
        kab_save_booking($local_booking_data);
        
        // Send email notification
        kab_send_booking_notification($local_booking_data);
        
        // Return success with booking details (no redirect - stay on confirmation step)
        wp_send_json_success(array(
            'message' => __('Booking created successfully.', 'konfidens-appointment-booking'),
            'booking_id' => $booking_id
        ));
    } else {
        // Get detailed error message from API response
        $error_message = __('Failed to create booking. Please try again.', 'konfidens-appointment-booking');
        
        // Try to extract error message from various possible locations in the response
        if (isset($booking_response['message'])) {
            $error_message = $booking_response['message'];
        }
        
        // Check response data for error details
        if (isset($booking_response['data'])) {
            if (is_array($booking_response['data'])) {
                // Check for validation errors (common in Laravel/API responses)
                if (isset($booking_response['data']['errors']) && is_array($booking_response['data']['errors'])) {
                    $validation_errors = array();
                    foreach ($booking_response['data']['errors'] as $field => $messages) {
                        if (is_array($messages)) {
                            // Handle nested arrays (like _errors array)
                            if (isset($messages['_errors']) && is_array($messages['_errors'])) {
                                foreach ($messages['_errors'] as $error_msg) {
                                    $validation_errors[] = $field . ': ' . $error_msg;
                                }
                            } else {
                                // Regular array of messages
                                $validation_errors[] = $field . ': ' . implode(', ', $messages);
                            }
                        } else {
                            $validation_errors[] = $field . ': ' . $messages;
                        }
                    }
                    if (!empty($validation_errors)) {
                        $error_message = __('Validation error: ', 'konfidens-appointment-booking') . implode('; ', $validation_errors);
                    }
                }
                // Check for message field
                if (isset($booking_response['data']['message'])) {
                    $error_message = $booking_response['data']['message'];
                }
                // Check for error field
                if (isset($booking_response['data']['error'])) {
                    $error_message = $booking_response['data']['error'];
                }
                // Check for error_message field
                if (isset($booking_response['data']['error_message'])) {
                    $error_message = $booking_response['data']['error_message'];
                }
            } elseif (is_string($booking_response['data'])) {
                $error_message = $booking_response['data'];
            }
        }
        
        // Include HTTP code if available
        if (isset($booking_response['code'])) {
            $error_message .= ' (HTTP ' . $booking_response['code'] . ')';
        }
        
        wp_send_json_error(array('message' => $error_message));
    }
}
add_action('wp_ajax_kab_create_booking', 'kab_create_booking');
add_action('wp_ajax_nopriv_kab_create_booking', 'kab_create_booking');
