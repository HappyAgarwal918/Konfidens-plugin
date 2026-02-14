<?php
if (!defined('ABSPATH')) {
    exit;
}

// Display therapists page: Show all therapists and manage tags + profession + locations
function kab_display_therapists_page() {
    $therapists = kab_get_all_therapists();
    
    // Get locations and location categories for the Locations column (like services page)
    $locations = kab_get_locations();
    $location_categories = kab_get_location_categories();
    $location_categories_map = array();
    foreach ($location_categories as $loc_cat) {
        $location_categories_map[$loc_cat->id] = $loc_cat->category_name;
    }
    
    // Batch load specialist location_ids from database
    $specialist_locations_map = array();
    if (!empty($therapists)) {
        global $wpdb;
        $location_specialist_table = $wpdb->prefix . 'kab_location_specialist';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $location_specialist_table LIKE 'location_ids'");
        if (!empty($column_exists)) {
            $rows = $wpdb->get_results("SELECT specialist_id, location_ids FROM $location_specialist_table");
            foreach ($rows as $row) {
                $specialist_locations_map[$row->specialist_id] = !empty($row->location_ids) ? $row->location_ids : '';
            }
        }
    }
    
    ?>
    <div class="wrap kab-admin">
        <h1><?php _e('Konfidens Therapists', 'konfidens-appointment-booking'); ?></h1>
        
        <style>
            .wp-list-table td {
                vertical-align: top;
            }
            .striped>tbody>:nth-child(odd){
                background-color: #e9ffff;
            }
        </style>
        
        <?php if (empty($therapists)): ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('No therapists found. Please check your API connection in the settings.', 'konfidens-appointment-booking'); ?>
                    <a href="<?php echo admin_url('admin.php?page=konfidens-settings'); ?>" class="button button-secondary">
                        <?php _e('Go to Settings', 'konfidens-appointment-booking'); ?>
                    </a>
                </p>
            </div>
        <?php else: ?>
            <div class="kab-therapists-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Name', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Profession', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Tags', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Locations', 'konfidens-appointment-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($therapists as $therapist): ?>
                            <?php 
                            $therapist_tags = kab_get_specialist_tags($therapist['id']);
                            $tags_string = !empty($therapist_tags) ? implode(', ', $therapist_tags) : '';
                            $therapist_profession = kab_get_specialist_profession($therapist['id']);
                            $therapist_location_ids = isset($specialist_locations_map[$therapist['id']]) ? $specialist_locations_map[$therapist['id']] : '';
                            $therapist_location_ids_array = !empty($therapist_location_ids) ? array_map('trim', explode(',', $therapist_location_ids)) : array();
                            ?>
                            <tr>
                                <td><?php echo esc_html($therapist['id']); ?></td>
                                <td><?php echo esc_html($therapist['name']); ?></td>
                                <td>
                                    <input type="text" 
                                        class="therapist-profession-input regular-text" 
                                        data-therapist-id="<?php echo esc_attr($therapist['id']); ?>"
                                        value="<?php echo esc_attr($therapist_profession); ?>"
                                        placeholder="<?php _e('e.g. Psychologist', 'konfidens-appointment-booking'); ?>"
                                        style="max-width: 200px;"
                                    />
                                    <div class="profession-save-status" id="profession-save-status-<?php echo esc_attr($therapist['id']); ?>" style="display: none; margin-top: 5px; color: green; font-style: italic; font-size: 12px;">
                                        <?php _e('Profession saved!', 'konfidens-appointment-booking'); ?>
                                    </div>
                                </td>
                                <td>
                                    <textarea 
                                        class="therapist-tags-textarea" 
                                        data-therapist-id="<?php echo esc_attr($therapist['id']); ?>"
                                        rows="3"
                                        style="width: 100%; min-width: 200px;"
                                        placeholder="<?php _e('Enter tags separated by commas (e.g., Angst, Utbrenthet, Selvfølelse)', 'konfidens-appointment-booking'); ?>"
                                    ><?php echo esc_textarea($tags_string); ?></textarea>
                                    <div class="tags-save-status" id="tags-save-status-<?php echo esc_attr($therapist['id']); ?>" style="display: none; margin-top: 5px; color: green; font-style: italic; font-size: 12px;">
                                        <?php _e('Tags saved!', 'konfidens-appointment-booking'); ?>
                                    </div>
                                    <p class="description" style="margin-top: 5px; font-size: 11px; color: #666;">
                                        <?php _e('Separate tags with commas', 'konfidens-appointment-booking'); ?>
                                    </p>
                                </td>
                                <td>
                                    <div class="kab-therapist-location-checkboxes" data-therapist-id="<?php echo esc_attr($therapist['id']); ?>" style="max-width: 100%;">
                                        <?php if (empty($locations)): ?>
                                            <span style="color: #999;"><?php _e('No locations available', 'konfidens-appointment-booking'); ?></span>
                                        <?php else: ?>
                                            <?php foreach ($locations as $location): 
                                                $display_name = esc_html($location->location_name);
                                                if (!empty($location->category_id) && isset($location_categories_map[$location->category_id])) {
                                                    $display_name .= ' (' . esc_html($location_categories_map[$location->category_id]) . ')';
                                                }
                                            ?>
                                                <label style="display: block; margin: 5px 0;">
                                                    <input type="checkbox" 
                                                           class="kab-therapist-location-checkbox" 
                                                           value="<?php echo esc_attr($location->id); ?>"
                                                           <?php checked(in_array((string)$location->id, $therapist_location_ids_array)); ?>>
                                                    <?php echo $display_name; ?>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <span class="kab-therapist-location-save-status" id="therapist-location-save-status-<?php echo esc_attr($therapist['id']); ?>" style="display: none; margin-left: 10px; color: green; font-style: italic;">
                                        <?php _e('Saved!', 'konfidens-appointment-booking'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var tagsSaveTimeout = {};
        $('.therapist-tags-textarea').on('input', function() {
            var therapistId = $(this).data('therapist-id');
            var $textarea = $(this);
            var saveStatus = $('#tags-save-status-' + therapistId);
            if (tagsSaveTimeout[therapistId]) clearTimeout(tagsSaveTimeout[therapistId]);
            tagsSaveTimeout[therapistId] = setTimeout(function() {
                saveStatus.text('<?php _e("Saving...", "konfidens-appointment-booking"); ?>').css('color', '#666').show();
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kab_update_specialist_tags',
                        specialist_id: therapistId,
                        tags: $textarea.val(),
                        nonce: '<?php echo wp_create_nonce("kab-admin-nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            saveStatus.text('<?php _e("Tags saved!", "konfidens-appointment-booking"); ?>').css('color', 'green');
                            setTimeout(function() { saveStatus.fadeOut(500); }, 2000);
                        } else {
                            saveStatus.text(response.data && response.data.message ? response.data.message : '<?php _e("Error saving tags", "konfidens-appointment-booking"); ?>').css('color', 'red');
                        }
                    },
                    error: function(xhr) {
                        saveStatus.text(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : '<?php _e("Error saving tags", "konfidens-appointment-booking"); ?>').css('color', 'red');
                    }
                });
            }, 1000);
        });
        var professionSaveTimeout = {};
        $('.therapist-profession-input').on('input', function() {
            var therapistId = $(this).data('therapist-id');
            var $input = $(this);
            var saveStatus = $('#profession-save-status-' + therapistId);
            if (professionSaveTimeout[therapistId]) clearTimeout(professionSaveTimeout[therapistId]);
            professionSaveTimeout[therapistId] = setTimeout(function() {
                saveStatus.text('<?php _e("Saving...", "konfidens-appointment-booking"); ?>').css('color', '#666').show();
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kab_update_specialist_profession',
                        specialist_id: therapistId,
                        profession: $input.val(),
                        nonce: '<?php echo wp_create_nonce("kab-admin-nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            saveStatus.text('<?php _e("Profession saved!", "konfidens-appointment-booking"); ?>').css('color', 'green');
                            setTimeout(function() { saveStatus.fadeOut(500); }, 2000);
                        } else {
                            saveStatus.text(response.data && response.data.message ? response.data.message : '<?php _e("Error saving profession", "konfidens-appointment-booking"); ?>').css('color', 'red');
                        }
                    },
                    error: function(xhr) {
                        saveStatus.text(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : '<?php _e("Error saving profession", "konfidens-appointment-booking"); ?>').css('color', 'red');
                    }
                });
            }, 1000);
        });
        // Therapist location checkboxes (same pattern as service locations)
        $('.kab-therapist-location-checkbox').on('change', function() {
            var $container = $(this).closest('.kab-therapist-location-checkboxes');
            var therapistId = $container.data('therapist-id');
            var locationCheckboxes = $container.find('.kab-therapist-location-checkbox');
            var selectedLocations = [];
            locationCheckboxes.each(function() {
                if ($(this).is(':checked')) {
                    selectedLocations.push($(this).val());
                }
            });
            var locationIds = selectedLocations.join(',');
            var saveStatus = $('#therapist-location-save-status-' + therapistId);
            saveStatus.text('<?php _e("Saving...", "konfidens-appointment-booking"); ?>').css('color', '#666').show();
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'kab_update_specialist_locations',
                    specialist_id: therapistId,
                    location_ids: locationIds,
                    nonce: '<?php echo wp_create_nonce("kab-admin-nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        saveStatus.text('<?php _e("Saved!", "konfidens-appointment-booking"); ?>').css('color', 'green');
                        setTimeout(function() { saveStatus.fadeOut(500); }, 2000);
                    } else {
                        saveStatus.text(response.data && response.data.message ? response.data.message : '<?php _e("Error saving locations", "konfidens-appointment-booking"); ?>').css('color', 'red');
                    }
                },
                error: function(xhr) {
                    saveStatus.text(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : '<?php _e("Error saving locations", "konfidens-appointment-booking"); ?>').css('color', 'red');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Get all therapists (cached 5 minutes to reduce API calls)
 */
function kab_get_all_therapists() {
    $cache_key = 'kab_all_therapists';
    $cached = get_transient($cache_key);
    if ($cached !== false && is_array($cached)) {
        return $cached;
    }

    // Try to get all specialists directly first
    $specialists_response = kab_api_request('specialists', array('clinic_id' => get_option('kab_clinic_id', '')));
    $therapists = array();
    
    if ($specialists_response['success'] && !empty($specialists_response['data'])) {
        // Direct API call worked
        foreach ($specialists_response['data'] as $specialist) {
            $therapists[$specialist['id']] = $specialist;
        }
        set_transient($cache_key, $therapists, 300); // 5 minutes
        return $therapists;
    }
    
    // Fallback: Get services first, then get specialists for each service
    $services_response = kab_api_request('services', array('clinic_id' => get_option('kab_clinic_id', '')));
    
    if ($services_response['success'] && !empty($services_response['data'])) {
        // Filter only enabled services (same as old plugin)
        $services = array_filter($services_response['data'], function ($service) {
            return (isset($service['code']) ? $service['code'] === null : true) && !empty($service['enabled_by_specialists']);
        });
        // Re-index array after filtering
        $services = array_values($services);
        
        // For each service, get specialists
        foreach ($services as $service) {
            $specialists_response = kab_api_request('specialists', array(
                'clinic_id' => get_option('kab_clinic_id', ''),
                'service_id' => $service['id']
            ));
            
            if ($specialists_response['success'] && !empty($specialists_response['data'])) {
                foreach ($specialists_response['data'] as $specialist) {
                    // Add to therapists array if not already added
                    if (!isset($therapists[$specialist['id']])) {
                        $therapists[$specialist['id']] = $specialist;
                    }
                }
            }
        }
    }

    set_transient($cache_key, $therapists, 300); // 5 minutes
    return $therapists;
}

/**
 * Get therapist by ID
 */
function kab_get_therapist_by_id($therapist_id) {
    $therapists = kab_get_all_therapists();
    
    if (isset($therapists[$therapist_id])) {
        return $therapists[$therapist_id];
    }
    
    return array();
}

/**
 * Get therapists for a service (cached 5 minutes per service to reduce API calls)
 */
function kab_get_therapists_for_service($service_id) {
    $cache_key = 'kab_therapists_service_' . md5($service_id);
    $cached = get_transient($cache_key);
    if ($cached !== false && is_array($cached)) {
        return $cached;
    }

    $specialists_response = kab_api_request('specialists', array(
        'clinic_id' => get_option('kab_clinic_id', ''),
        'service_id' => $service_id
    ));
    $therapists = array();
    
    if ($specialists_response['success'] && !empty($specialists_response['data'])) {
        $therapists = $specialists_response['data'];
    }

    set_transient($cache_key, $therapists, 300); // 5 minutes
    return $therapists;
}

/**
 * Get therapists for a service and location
 * Note: Location filtering has been removed - this function now returns all therapists for the service
 */
function kab_get_therapists_for_service_location($service_id, $location_id) {
    // Location filtering removed - return all therapists for the service
    return kab_get_therapists_for_service($service_id);
}

/**
 * Display mapping page
 * 
 * This function is kept for backward compatibility but redirects to the therapists page
 */
function kab_display_mapping_page() {
    // Redirect to therapists page
    wp_redirect(admin_url('admin.php?page=konfidens-therapists'));
    exit;
}


/**
 * AJAX handler for updating specialist tags
 */
function kab_update_specialist_tags_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-admin-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'konfidens-appointment-booking')));
    }
    
    // Check required fields
    if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
        wp_send_json_error(array('message' => __('Specialist ID is required.', 'konfidens-appointment-booking')));
    }
    
    $specialist_id = sanitize_text_field($_POST['specialist_id']);
    $tags = isset($_POST['tags']) ? sanitize_text_field($_POST['tags']) : '';
    
    // Update specialist tags
    $result = kab_update_specialist_tags($specialist_id, $tags);
    
    if ($result) {
        wp_send_json_success(array('message' => __('Specialist tags updated successfully.', 'konfidens-appointment-booking')));
    } else {
        global $wpdb;
        $error_message = __('Failed to update specialist tags.', 'konfidens-appointment-booking');
        if ($wpdb->last_error) {
            $error_message .= ' ' . $wpdb->last_error;
        }
        wp_send_json_error(array('message' => $error_message));
    }
}
add_action('wp_ajax_kab_update_specialist_tags', 'kab_update_specialist_tags_ajax');

/**
 * AJAX handler for updating specialist profession
 */
function kab_update_specialist_profession_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-admin-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'konfidens-appointment-booking')));
    }
    if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
        wp_send_json_error(array('message' => __('Specialist ID is required.', 'konfidens-appointment-booking')));
    }
    $specialist_id = sanitize_text_field($_POST['specialist_id']);
    $profession = isset($_POST['profession']) ? sanitize_text_field($_POST['profession']) : '';
    $result = kab_update_specialist_profession($specialist_id, $profession);
    if ($result) {
        wp_send_json_success(array('message' => __('Profession saved successfully.', 'konfidens-appointment-booking')));
    } else {
        global $wpdb;
        $error_message = __('Failed to update profession.', 'konfidens-appointment-booking');
        if ($wpdb->last_error) {
            $error_message .= ' ' . $wpdb->last_error;
        }
        wp_send_json_error(array('message' => $error_message));
    }
}
add_action('wp_ajax_kab_update_specialist_profession', 'kab_update_specialist_profession_ajax');

/**
 * AJAX handler for updating specialist locations
 */
function kab_update_specialist_locations_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-admin-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'konfidens-appointment-booking')));
    }
    if (!isset($_POST['specialist_id']) || empty($_POST['specialist_id'])) {
        wp_send_json_error(array('message' => __('Specialist ID is required.', 'konfidens-appointment-booking')));
    }
    $specialist_id = sanitize_text_field($_POST['specialist_id']);
    $location_ids = isset($_POST['location_ids']) ? sanitize_text_field($_POST['location_ids']) : '';
    $result = kab_add_update_specialist_locations($specialist_id, $location_ids);
    if ($result) {
        wp_send_json_success(array('message' => __('Therapist locations updated successfully.', 'konfidens-appointment-booking')));
    } else {
        global $wpdb;
        $error_message = __('Failed to update therapist locations.', 'konfidens-appointment-booking');
        if ($wpdb->last_error) {
            $error_message .= ' ' . $wpdb->last_error;
        }
        wp_send_json_error(array('message' => $error_message));
    }
}
add_action('wp_ajax_kab_update_specialist_locations', 'kab_update_specialist_locations_ajax');
