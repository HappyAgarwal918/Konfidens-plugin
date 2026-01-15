<?php
if (!defined('ABSPATH')) {
    exit;
}

// Display therapists page: Show all therapists and manage tags
function kab_display_therapists_page() {
    $therapists = kab_get_all_therapists();
    
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
                            <th><?php _e('Tags', 'konfidens-appointment-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($therapists as $therapist): ?>
                            <?php 
                            // Get therapist tags
                            $therapist_tags = kab_get_specialist_tags($therapist['id']);
                            $tags_string = !empty($therapist_tags) ? implode(', ', $therapist_tags) : '';
                            ?>
                            <tr>
                                <td><?php echo esc_html($therapist['id']); ?></td>
                                <td><?php echo esc_html($therapist['name']); ?></td>
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
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Handle tags textarea - save on blur (when user leaves the field)
        var tagsSaveTimeout = {};
        $('.therapist-tags-textarea').on('input', function() {
            var therapistId = $(this).data('therapist-id');
            var $textarea = $(this);
            var saveStatus = $('#tags-save-status-' + therapistId);
            
            // Clear existing timeout
            if (tagsSaveTimeout[therapistId]) {
                clearTimeout(tagsSaveTimeout[therapistId]);
            }
            
            // Set new timeout to save after 1 second of no typing
            tagsSaveTimeout[therapistId] = setTimeout(function() {
                var tags = $textarea.val();
                
                // Show saving indicator
                saveStatus.text('<?php _e("Saving...", "konfidens-appointment-booking"); ?>').css('color', '#666').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kab_update_specialist_tags',
                        specialist_id: therapistId,
                        tags: tags,
                        nonce: '<?php echo wp_create_nonce("kab-admin-nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            saveStatus.text('<?php _e("Tags saved!", "konfidens-appointment-booking"); ?>').css('color', 'green');
                            setTimeout(function() {
                                saveStatus.fadeOut(500);
                            }, 2000);
                        } else {
                            var errorMsg = response.data && response.data.message ? response.data.message : '<?php _e("Error saving tags", "konfidens-appointment-booking"); ?>';
                            saveStatus.text(errorMsg).css('color', 'red');
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMsg = '<?php _e("Error saving tags", "konfidens-appointment-booking"); ?>';
                        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            errorMsg = xhr.responseJSON.data.message;
                        }
                        saveStatus.text(errorMsg).css('color', 'red');
                    }
                });
            }, 1000); // Wait 1 second after user stops typing
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
