<?php
if (!defined('ABSPATH')) {
    exit;
}

// Display services page: Import services from API and assign to categories/locations
function kab_display_services_page() {
    $services_response = kab_api_request('services', array('clinic_id' => get_option('kab_clinic_id', '')));
    $services = array();
    
    if ($services_response['success'] && !empty($services_response['data'])) {
        // Filter only enabled services (same as old plugin)
        $services = array_filter($services_response['data'], function ($service) {
            return (isset($service['code']) ? $service['code'] === null : true) && !empty($service['enabled_by_specialists']);
        });
        
        // Re-index array after filtering
        $services = array_values($services);
        
        // Auto-import services if requested
        if (isset($_GET['import']) && $_GET['import'] == '1') {
            $imported = kab_import_services($services);
            echo '<div class="notice notice-success"><p>' . $imported . ' services imported/updated successfully.</p></div>';
        }
    } else {
        // Display API error
        if (isset($services_response['message'])) {
            echo '<div class="notice notice-error"><p>API Error: ' . esc_html($services_response['message']) . '</p></div>';
        }
    }
    
    // Get locations
    $locations = kab_get_locations();
    
    // Get all service categories for dropdown
    $service_categories = kab_get_service_categories();
    
    // Get all parent categories for lookup
    $parent_categories = kab_get_service_parent_categories();
    $parent_categories_map = array();
    foreach ($parent_categories as $parent_cat) {
        $parent_categories_map[$parent_cat->id] = $parent_cat->parent_category_name;
    }
    
    ?>
    <div class="wrap kab-admin kab-services-page">
        <h1><?php _e('Konfidens Services', 'konfidens-appointment-booking'); ?></h1>
        
        <style>
            .striped>tbody>:nth-child(odd){
                background-color: #e9ffff;
            }
        </style>
        
        <?php if (empty($services)): ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('No services found. Please check your API connection in the settings.', 'konfidens-appointment-booking'); ?>
                    <a href="<?php echo admin_url('admin.php?page=konfidens-settings'); ?>" class="button button-secondary">
                        <?php _e('Go to Settings', 'konfidens-appointment-booking'); ?>
                    </a>
                </p>
            </div>
        <?php else: ?>
            <div class="kab-admin-actions">
                <a href="<?php echo admin_url('admin.php?page=konfidens-services&import=1'); ?>" class="button button-primary">
                    <?php _e('Import/Update All Services', 'konfidens-appointment-booking'); ?>
                </a>
            </div>
            
            <div class="kab-services-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('No.', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Service ID', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Service Name', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Total Bookings', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Locations', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Service Category', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Service Price (from API)', 'konfidens-appointment-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $n = 1;
                        // Get booking counts
                        global $wpdb;
                        $table_name_count = $wpdb->prefix . 'kab_booking_form_data';
                        $booking_counts = $wpdb->get_results("SELECT service_ids, COUNT(*) as total FROM $table_name_count GROUP BY service_ids", OBJECT_K);
                        
                        // Batch load all service locations and categories in one query (performance optimization)
                        $location_service_table = $wpdb->prefix . 'kab_location_service';
                        $all_service_data = $wpdb->get_results("SELECT service_id, location_ids, category_id FROM $location_service_table", OBJECT_K);
                        
                        // Create lookup arrays for fast access
                        $service_locations_map = array();
                        $service_categories_map = array();
                        foreach ($all_service_data as $service_id => $data) {
                            // Services now only have a single location_id
                            $service_locations_map[$service_id] = !empty($data->location_ids) ? $data->location_ids : '';
                            $service_categories_map[$service_id] = !empty($data->category_id) ? intval($data->category_id) : null;
                        }
                        
                        foreach ($services as $service): 
                            // Get service location and category from lookup arrays (fast, no database query)
                            $service_location_ids = isset($service_locations_map[$service['id']]) ? $service_locations_map[$service['id']] : '';
                            // Parse comma-separated location IDs into array
                            $service_location_ids_array = !empty($service_location_ids) ? array_map('trim', explode(',', $service_location_ids)) : array();
                            $service_category_id = isset($service_categories_map[$service['id']]) ? $service_categories_map[$service['id']] : null;
                            // Get price from API only
                            // Helper function to extract price (handles arrays and objects)
                            // Converts from cents to main currency unit if needed
                            $extract_price = function($value) {
                                $raw_price = '';
                                
                                if (is_array($value)) {
                                    // If it's an array, try to get the first numeric value
                                    foreach ($value as $item) {
                                        if (is_numeric($item)) {
                                            $raw_price = (string) $item;
                                            break;
                                        }
                                    }
                                    // If no numeric value found, return first item as string
                                    if (empty($raw_price)) {
                                        $raw_price = is_array($value) && !empty($value) ? (string) reset($value) : '';
                                    }
                                } elseif (is_object($value)) {
                                    // If it's an object, try to get a value property
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
                                
                                // Convert from cents to main currency if the price seems to be in cents
                                // If price is a number and >= 1000, assume it might be in cents
                                if (!empty($raw_price) && is_numeric($raw_price)) {
                                    $numeric_price = floatval($raw_price);
                                    // If price is >= 1000 and is a whole number, likely in cents
                                    // Convert by dividing by 100 (e.g., 119000 cents = 1190.00)
                                    if ($numeric_price >= 1000 && $numeric_price == floor($numeric_price)) {
                                        $numeric_price = $numeric_price / 100;
                                        // Format to 2 decimal places, remove trailing zeros
                                        $numeric_price = rtrim(rtrim(number_format($numeric_price, 2, '.', ''), '0'), '.');
                                        return (string) $numeric_price;
                                    }
                                }
                                
                                return $raw_price;
                            };
                            
                            // Get price from API - check common field names
                            $price = '';
                            $price_fields = array('price', 'Price', 'amount', 'Amount');
                            
                            foreach ($price_fields as $field) {
                                if (isset($service[$field])) {
                                    $price = $extract_price($service[$field]);
                                    if (!empty($price)) break;
                                }
                            }
                            
                            // If not found, default to 0 (don't make additional API calls)
                            if (empty($price)) {
                                $price = '0';
                            }
                            
                            // Ensure price is always a string
                            $price = (string) $price;
                            
                            // Get booking count
                            $service_id = $service['id'];
                            $count = isset($booking_counts[$service_id]) ? $booking_counts[$service_id]->total : 0;
                        ?>
                            <tr>
                                <td><?php echo esc_html($n++); ?></td>
                                <td onclick="copyToClipboard(this)" style="cursor: pointer; position: relative;">
                                    <span class="hidden-id" style="display: none;"><?php echo esc_html($service['id']); ?></span>
                                    <button class="copy-btn" type="button">Copy Service ID</button>
                                    <span class="copied-text" style="display: none;">
                                        Copied: <?php echo esc_html($service['id']); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($service['name']); ?></td>
                                <td><?php echo esc_html($count); ?></td>
                                <td>
                                    <div class="kab-service-location-checkboxes" data-service-id="<?php echo esc_attr($service['id']); ?>" style="max-width: 100%;">
                                        <?php if (empty($locations)): ?>
                                            <span style="color: #999;"><?php _e('No locations available', 'konfidens-appointment-booking'); ?></span>
                                        <?php else: ?>
                                            <?php foreach ($locations as $location): ?>
                                                <label style="display: block; margin: 5px 0;">
                                                    <input type="checkbox" 
                                                           class="kab-service-location-checkbox" 
                                                           value="<?php echo esc_attr($location->id); ?>"
                                                           <?php checked(in_array((string)$location->id, $service_location_ids_array)); ?>>
                                                    <?php echo esc_html($location->location_name); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <span class="kab-service-location-save-status" id="service-save-status-<?php echo esc_attr($service['id']); ?>" style="display: none; margin-left: 10px; color: green; font-style: italic;">
                                        <?php _e('Saved!', 'konfidens-appointment-booking'); ?>
                                    </span>
                                </td>
                                <td>
                                    <select class="kab-service-category-select" data-service-id="<?php echo esc_attr($service['id']); ?>" style="max-width: 100%;">
                                        <option value=""><?php _e('-- No Category --', 'konfidens-appointment-booking'); ?></option>
                                        <?php foreach ($service_categories as $category): 
                                            // Get parent category name if exists
                                            $display_name = esc_html($category->category_name);
                                            if (!empty($category->parent_category_id) && isset($parent_categories_map[$category->parent_category_id])) {
                                                $display_name .= ' (' . esc_html($parent_categories_map[$category->parent_category_id]) . ')';
                                            }
                                        ?>
                                            <option value="<?php echo esc_attr($category->id); ?>" <?php selected($service_category_id, $category->id); ?>>
                                                <?php echo $display_name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="kab-service-category-save-status" id="category-save-status-<?php echo esc_attr($service['id']); ?>" style="display: none; margin-left: 10px; color: green; font-style: italic;">
                                        <?php _e('Saved!', 'konfidens-appointment-booking'); ?>
                                    </span>
                                </td>
                                <td>
                                    <input type="text" name="price" class="price-select" data-id="<?php echo esc_attr($service['id']); ?>" value="<?php echo esc_attr($price); ?>" readonly style="background-color: #f0f0f0; cursor: not-allowed;" title="<?php _e('Price is fetched from Konfidens API', 'konfidens-appointment-booking'); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
            function copyToClipboard(element) {
                var text = element.querySelector('.hidden-id').innerText.trim(); // Get the hidden ID
                navigator.clipboard.writeText(text).then(function() {
                    var copiedText = element.querySelector('.copied-text');
                    copiedText.style.display = 'inline-block'; // Show "Copied" message
                    
                    setTimeout(function() {
                        copiedText.style.display = 'none'; // Hide after 1.5 sec
                    }, 1500);
                });
            }
            
            jQuery(document).ready(function($) {
                // Location checkbox selection
                $('.kab-service-location-checkbox').on('change', function() {
                    var $container = $(this).closest('.kab-service-location-checkboxes');
                    var serviceId = $container.data('service-id');
                    var locationCheckboxes = $container.find('.kab-service-location-checkbox');
                    var selectedLocations = [];
                    
                    locationCheckboxes.each(function() {
                        if ($(this).is(':checked')) {
                            selectedLocations.push($(this).val());
                        }
                    });
                    
                    var locationIds = selectedLocations.join(',');
                    var saveStatus = $('#service-save-status-' + serviceId);
                    
                    // Show saving indicator
                    saveStatus.text('<?php _e("Saving...", "konfidens-appointment-booking"); ?>').css('color', '#666').show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kab_update_service_locations',
                            service_id: serviceId,
                            location_ids: locationIds,
                            nonce: '<?php echo wp_create_nonce("kab-admin-nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                saveStatus.text('<?php _e("Saved!", "konfidens-appointment-booking"); ?>').css('color', 'green');
                                setTimeout(function() {
                                    saveStatus.fadeOut(500);
                                }, 1500);
                            } else {
                                saveStatus.text('<?php _e("Error saving", "konfidens-appointment-booking"); ?>').css('color', 'red');
                            }
                        },
                        error: function() {
                            saveStatus.text('<?php _e("Error saving", "konfidens-appointment-booking"); ?>').css('color', 'red');
                        }
                    });
                });
                
                // Service category assignment via AJAX
                $('.kab-service-category-select').on('change', function() {
                    var serviceId = $(this).data('service-id');
                    var categoryId = $(this).val();
                    var saveStatus = $('#category-save-status-' + serviceId);
                    
                    saveStatus.text('<?php _e("Saving...", "konfidens-appointment-booking"); ?>').css('color', '#666').show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kab_update_service_category_assignment',
                            service_id: serviceId,
                            category_id: categoryId,
                            nonce: '<?php echo wp_create_nonce("kab-admin-nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                saveStatus.text('<?php _e("Saved!", "konfidens-appointment-booking"); ?>').css('color', 'green');
                                setTimeout(function() {
                                    saveStatus.fadeOut(500);
                                }, 1500);
                            } else {
                                saveStatus.text('<?php _e("Error saving", "konfidens-appointment-booking"); ?>').css('color', 'red');
                            }
                        },
                        error: function() {
                            saveStatus.text('<?php _e("Error saving", "konfidens-appointment-booking"); ?>').css('color', 'red');
                        }
                    });
                });
                
                // Price field is read-only (fetched from API)
                // No update handler needed
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Get service by ID
 */
function kab_get_service_by_id($service_id) {
    // Use kab_get_services_with_priority to get service with price extracted
    $all_services = kab_get_services_with_priority();
    
    foreach ($all_services as $service) {
        if ($service['id'] === $service_id) {
            return $service;
        }
    }
    
    return array();
}

/**
 * Get all services
 */
function kab_get_services_with_priority() {
    $services_response = kab_api_request('services', array('clinic_id' => get_option('kab_clinic_id', '')));
    $services = array();
    
    if ($services_response['success'] && !empty($services_response['data'])) {
        // Filter only enabled services (same as old plugin)
        $enabled_services = array_filter($services_response['data'], function ($service) {
            return (isset($service['code']) ? $service['code'] === null : true) && !empty($service['enabled_by_specialists']);
        });
        
        foreach ($enabled_services as $service) {
            // Get price from API only (prices are fetched from Konfidens API, not stored in database)
            // Helper function to safely extract price (handles arrays and objects)
            // Also converts from cents to main currency unit if needed
            $extract_price = function($value) {
                $raw_price = '';
                
                if (is_array($value)) {
                    // If it's an array, try to get the first numeric value
                    foreach ($value as $item) {
                        if (is_numeric($item)) {
                            $raw_price = (string) $item;
                            break;
                        }
                    }
                    // If no numeric value found, return first item as string
                    if (empty($raw_price)) {
                        $raw_price = is_array($value) && !empty($value) ? (string) reset($value) : '';
                    }
                } elseif (is_object($value)) {
                    // If it's an object, try to get a value property
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
                
                // Convert from cents to main currency if the price seems to be in cents
                // If price is a number and >= 1000, assume it might be in cents
                if (!empty($raw_price) && is_numeric($raw_price)) {
                    $numeric_price = floatval($raw_price);
                    // If price is >= 1000 and is a whole number, likely in cents
                    // Convert by dividing by 100 (e.g., 119000 cents = 1190.00)
                    if ($numeric_price >= 1000 && $numeric_price == floor($numeric_price)) {
                        $numeric_price = $numeric_price / 100;
                        // Format to 2 decimal places, remove trailing zeros
                        $numeric_price = rtrim(rtrim(number_format($numeric_price, 2, '.', ''), '0'), '.');
                        return (string) $numeric_price;
                    }
                }
                
                return $raw_price;
            };
            
            // Get price from API - check common field names
            $price_fields = array('price', 'Price', 'amount', 'Amount');
            $service['price'] = '';
            
            foreach ($price_fields as $field) {
                if (isset($service[$field])) {
                    $service['price'] = $extract_price($service[$field]);
                    if (!empty($service['price'])) break;
                }
            }
            
            // If not found, default to 0 (don't make additional API calls)
            if (empty($service['price'])) {
                $service['price'] = '0';
            }
            
            // Ensure price is always a string
            $service['price'] = (string) $service['price'];
            
            $services[] = $service;
        }
    }
    
    return $services;
}


/**
 * AJAX handler for updating service locations (multiple locations supported)
 */
function kab_update_service_locations() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-admin-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'konfidens-appointment-booking')));
    }
    
    // Check required fields
    if (!isset($_POST['service_id'])) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    // Accept location_ids (comma-separated) or location_id (single, for backward compatibility)
    $location_ids = isset($_POST['location_ids']) ? sanitize_text_field($_POST['location_ids']) : (isset($_POST['location_id']) ? sanitize_text_field($_POST['location_id']) : '');
    
    // Update service locations (comma-separated IDs)
    $result = kab_add_update_service_location($service_id, $location_ids);
    
    if ($result !== false) {
        wp_send_json_success(array('message' => __('Service locations updated successfully.', 'konfidens-appointment-booking')));
    } else {
        wp_send_json_error(array('message' => __('Failed to update service locations.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_update_service_locations', 'kab_update_service_locations');

/**
 * AJAX handler for updating service category assignment
 */
function kab_update_service_category_assignment_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-admin-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'konfidens-appointment-booking')));
    }
    
    // Check required fields
    if (!isset($_POST['service_id'])) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    $category_id_input = isset($_POST['category_id']) ? $_POST['category_id'] : '';
    // Convert to integer if set, otherwise null (allow clearing category)
    $category_id = ($category_id_input !== '' && $category_id_input !== null && $category_id_input !== '0') ? intval($category_id_input) : null;
    
    // Get existing location IDs to preserve them (comma-separated)
    $location_ids = kab_get_service_locations($service_id);
    
    // Ensure service entry exists (create if it doesn't)
    $existing_location_check = kab_get_service_locations($service_id);
    if (empty($existing_location_check) && $location_ids === '') {
        // Service entry doesn't exist, create it first with empty location_ids
        kab_add_update_service_location($service_id, '', $category_id);
        // Re-fetch location after creation
        $location_ids = kab_get_service_locations($service_id);
    }
    
    // Update service category assignment (preserve location IDs)
    $result = kab_add_update_service_location($service_id, $location_ids, $category_id);
    
    if ($result !== false) {
        wp_send_json_success(array('message' => __('Service category updated successfully.', 'konfidens-appointment-booking')));
    } else {
        wp_send_json_error(array('message' => __('Failed to update service category.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_update_service_category_assignment', 'kab_update_service_category_assignment_ajax');
