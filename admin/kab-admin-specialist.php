<?php
/**
 * Admin specialist functionality for Konfidens Appointment Booking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display therapists page
 */
function kab_display_therapists_page() {
    // Get therapists
    $therapists = kab_get_all_therapists();
    
    // Get locations
    $locations = kab_get_locations();
    
    // Add default locations if none exist
    if (empty($locations)) {
        kab_add_default_categories();
        $locations = kab_get_locations();
    }
    
    ?>
    <div class="wrap kab-admin">
        <h1><?php _e('Konfidens Therapists', 'konfidens-appointment-booking'); ?></h1>
        
        <style>
            .location-select {
                width: 100%;
                min-height: 80px;
            }
            .wp-list-table td {
                vertical-align: top;
            }
            select[multiple] {
                height: auto;
                min-height: 80px;
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
                            <th><?php _e('Locations', 'konfidens-appointment-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($therapists as $therapist): ?>
                            <?php 
                            // Get therapist categories
                            $therapist_categories = kab_get_specialist_locations($therapist['id']);
                            
                            // Get category names
                            $category_names = array();
                            if (!empty($therapist_categories)) {
                                foreach ($locations as $location) {
                                    if (in_array($location->id, $therapist_categories)) {
                                        $category_names[] = $location->location_name;
                                    }
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html($therapist['id']); ?></td>
                                <td><?php echo esc_html($therapist['name']); ?></td>
                                <td>
                                    <div class="location-checkboxes" data-id="<?php echo esc_attr($therapist['id']); ?>">
                                        <?php foreach ($locations as $location): ?>
                                            <div class="location-checkbox-item">
                                                <label>
                                                    <input 
                                                        type="checkbox" 
                                                        name="therapist_location_<?php echo esc_attr($therapist['id']); ?>_<?php echo esc_attr($location->id); ?>" 
                                                        value="<?php echo esc_attr($location->id); ?>" 
                                                        class="location-checkbox"
                                                        data-therapist-id="<?php echo esc_attr($therapist['id']); ?>"
                                                        <?php echo in_array($location->id, $therapist_categories) ? 'checked' : ''; ?>
                                                    >
                                                    <?php echo esc_html($location->location_name); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="save-status" id="save-status-<?php echo esc_attr($therapist['id']); ?>" style="display: none; margin-top: 5px; color: green; font-style: italic;">
                                        <?php _e('Saved!', 'konfidens-appointment-booking'); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .location-checkboxes {
            margin-bottom: 10px;
        }
        .location-checkbox-item {
            margin-bottom: 5px;
        }
        .location-checkbox-item label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        .location-checkbox-item input[type="checkbox"] {
            margin-right: 8px;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Handle checkbox clicks
        $('.location-checkbox').on('change', function() {
            var therapistId = $(this).data('therapist-id');
            var locationId = $(this).val();
            var isChecked = $(this).is(':checked');
            var saveStatus = $('#save-status-' + therapistId);
            
            // Get all currently selected locations for this therapist
            var selectedLocations = [];
            $('.location-checkbox[data-therapist-id="' + therapistId + '"]:checked').each(function() {
                selectedLocations.push($(this).val());
            });
            
            // Show saving indicator
            saveStatus.text('<?php _e("Saving...", "konfidens-appointment-booking"); ?>').css('color', '#666').show();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'kab_update_specialist_categories',
                    specialist_id: therapistId,
                    location_ids: selectedLocations.join(','),
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
    });
    </script>
    <?php
}

/**
 * Get all therapists
 */
function kab_get_all_therapists() {
    // Try to get all specialists directly first
    $specialists_response = kab_api_request('specialists', array('clinic_id' => get_option('kab_clinic_id', '')));
    $therapists = array();
    
    if ($specialists_response['success'] && !empty($specialists_response['data'])) {
        // Direct API call worked
        foreach ($specialists_response['data'] as $specialist) {
            $therapists[$specialist['id']] = $specialist;
        }
        
        return $therapists;
    }
    
    // Fallback: Get services first, then get specialists for each service
    $services_response = kab_api_request('services', array('clinic_id' => get_option('kab_clinic_id', '')));
    
    if ($services_response['success'] && !empty($services_response['data'])) {
        $services = $services_response['data'];
        
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
 * Get therapists for a service
 */
function kab_get_therapists_for_service($service_id) {
    $specialists_response = kab_api_request('specialists', array(
        'clinic_id' => get_option('kab_clinic_id', ''),
        'service_id' => $service_id
    ));
    $therapists = array();
    
    if ($specialists_response['success'] && !empty($specialists_response['data'])) {
        $therapists = $specialists_response['data'];
    }
    
    return $therapists;
}

/**
 * Get therapists for a service and location
 */
function kab_get_therapists_for_service_location($service_id, $location_id) {
    $therapists = kab_get_therapists_for_service($service_id);
    $filtered_therapists = array();
    
    if (!empty($therapists)) {
        foreach ($therapists as $therapist) {
            $therapist_categories = kab_get_specialist_locations($therapist['id']);
            
            if (in_array($location_id, $therapist_categories)) {
                $filtered_therapists[] = $therapist;
            }
        }
    }
    
    return $filtered_therapists;
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
 * AJAX handler for updating specialist locations
 */
function kab_update_specialist_categories() {
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
    $location_ids = isset($_POST['location_ids']) ? sanitize_text_field($_POST['location_ids']) : '';
    
    // Update specialist locations
    $result = kab_add_update_specialist_location($specialist_id, $location_ids);
    
    if ($result !== false) {
        wp_send_json_success(array('message' => __('Specialist locations updated successfully.', 'konfidens-appointment-booking')));
    } else {
        wp_send_json_error(array('message' => __('Failed to update specialist locations.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_update_specialist_categories', 'kab_update_specialist_categories');
