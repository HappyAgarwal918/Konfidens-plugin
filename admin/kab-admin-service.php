<?php
/**
 * Admin service functionality for Konfidens Appointment Booking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display services page
 */
function kab_display_services_page() {
    // Get services from API
    $services_response = kab_api_request('services', array('clinic_id' => get_option('kab_clinic_id', '')));
    $services = array();
    
    if ($services_response['success'] && !empty($services_response['data'])) {
        $services = $services_response['data'];
        
        
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
    
    // Get locations (categories)
    $locations = kab_get_locations();
    
    ?>
    <div class="wrap kab-admin kab-services-page">
        <h1><?php _e('Konfidens Services', 'konfidens-appointment-booking'); ?></h1>
        
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
                            <th><?php _e('Priority', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Service Price', 'konfidens-appointment-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $n = 1;
                        // Get booking counts
                        global $wpdb;
                        $table_name_count = $wpdb->prefix . 'kab_booking_form_data';
                        $booking_counts = $wpdb->get_results("SELECT service_ids, COUNT(*) as total FROM $table_name_count GROUP BY service_ids", OBJECT_K);
                        
                        foreach ($services as $service): 
                            // Get service priority and locations
                            $priority = kab_get_service_priority($service['id']);
                            $service_locations = kab_get_service_locations($service['id']);
                            $price = kab_get_service_price($service['id']);
                            
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
                                    <div class="location-checkboxes" data-id="<?php echo esc_attr($service['id']); ?>">
                                        <?php foreach ($locations as $location): ?>
                                            <div class="location-checkbox-item">
                                                <label>
                                                    <input 
                                                        type="checkbox" 
                                                        name="service_location_<?php echo esc_attr($service['id']); ?>_<?php echo esc_attr($location->id); ?>" 
                                                        value="<?php echo esc_attr($location->id); ?>" 
                                                        class="service-location-checkbox"
                                                        data-service-id="<?php echo esc_attr($service['id']); ?>"
                                                        <?php echo in_array($location->id, $service_locations) ? 'checked' : ''; ?>
                                                    >
                                                    <?php echo esc_html($location->location_name); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="save-status" id="service-save-status-<?php echo esc_attr($service['id']); ?>" style="display: none; margin-top: 5px; color: green; font-style: italic;">
                                        <?php _e('Saved!', 'konfidens-appointment-booking'); ?>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" min="0" name="priority" class="priority-select" data-id="<?php echo esc_attr($service['id']); ?>" value="<?php echo esc_attr($priority !== null ? $priority : ''); ?>">
                                </td>
                                <td>
                                    <input type="number" min="0" name="price" class="price-select" data-id="<?php echo esc_attr($service['id']); ?>" value="<?php echo esc_attr($price); ?>">
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
                }).catch(function(err) {
                    console.error("Failed to copy text: ", err);
                });
            }
            
            jQuery(document).ready(function($) {
                // Location checkbox selection
                $('.service-location-checkbox').on('change', function() {
                    var serviceId = $(this).data('service-id');
                    var saveStatus = $('#service-save-status-' + serviceId);
                    
                    // Get all currently selected locations for this service
                    var selectedLocations = [];
                    $('.service-location-checkbox[data-service-id="' + serviceId + '"]:checked').each(function() {
                        selectedLocations.push($(this).val());
                    });
                    
                    // Show saving indicator
                    saveStatus.text('<?php _e("Saving...", "konfidens-appointment-booking"); ?>').css('color', '#666').show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kab_update_service_locations',
                            service_id: serviceId,
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
                
                // Priority update
                $('.priority-select').on('change', function() {
                    var serviceId = $(this).data('id');
                    var priority = $(this).val();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kab_update_service_priority',
                            service_id: serviceId,
                            priority: priority,
                            location_ids: '', // Not used but required by the function
                            nonce: '<?php echo wp_create_nonce("kab-admin-nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Priority updated successfully!');
                            } else {
                                alert('Error updating priority: ' + response.data.message);
                            }
                        }
                    });
                });
                
                // Price update
                $('.price-select').on('change', function() {
                    var serviceId = $(this).data('id');
                    var price = $(this).val();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'kab_update_service_price',
                            service_id: serviceId,
                            price: price,
                            nonce: '<?php echo wp_create_nonce("kab-admin-nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Price updated successfully!');
                            } else {
                                alert('Error updating price: ' + response.data.message);
                            }
                        }
                    });
                });
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
    $services_response = kab_api_request('services', array('clinic_id' => get_option('kab_clinic_id', '')));
    
    if ($services_response['success'] && !empty($services_response['data'])) {
        foreach ($services_response['data'] as $service) {
            if ($service['id'] === $service_id) {
                return $service;
            }
        }
    }
    
    return array();
}

/**
 * Get services with priority
 */
function kab_get_services_with_priority() {
    $services_response = kab_api_request('services', array('clinic_id' => get_option('kab_clinic_id', '')));
    $services = array();
    
    if ($services_response['success'] && !empty($services_response['data'])) {
        // Only include services with priority set
        $prioritized_services = array();
        
        foreach ($services_response['data'] as $service) {
            $priority = kab_get_service_priority($service['id']);
            
            // Only include services that have a priority set (not null or empty)
            if ($priority !== null && $priority !== '') {
                $service['priority'] = $priority;
                // Add price to service data
                $service['price'] = kab_get_service_price($service['id']);
                $prioritized_services[] = $service;
            }
        }
        
        // Sort prioritized services by priority (highest first)
        usort($prioritized_services, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        $services = $prioritized_services;
    }
    
    return $services;
}

/**
 * AJAX handler for updating service priority
 */
function kab_update_service_priority() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-admin-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'konfidens-appointment-booking')));
    }
    
    // Check required fields
    if (!isset($_POST['service_id']) || !isset($_POST['priority'])) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    $priority = intval($_POST['priority']);
    $location_ids = isset($_POST['location_ids']) ? sanitize_text_field($_POST['location_ids']) : '';
    
    // Update service priority
    $result = kab_add_update_service_location($service_id, $location_ids, $priority);
    
    if ($result !== false) {
        wp_send_json_success(array('message' => __('Service priority updated successfully.', 'konfidens-appointment-booking')));
    } else {
        wp_send_json_error(array('message' => __('Failed to update service priority.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_update_service_priority', 'kab_update_service_priority');

/**
 * AJAX handler for updating service price
 */
function kab_update_service_price_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-admin-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'konfidens-appointment-booking')));
    }
    
    // Check required fields
    if (!isset($_POST['service_id']) || !isset($_POST['price'])) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    $price = sanitize_text_field($_POST['price']);
    
    // Update service price
    $result = kab_update_service_price($service_id, $price);
    
    if ($result !== false) {
        wp_send_json_success(array('message' => __('Service price updated successfully.', 'konfidens-appointment-booking')));
    } else {
        wp_send_json_error(array('message' => __('Failed to update service price.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_update_service_price', 'kab_update_service_price_ajax');

/**
 * AJAX handler for updating service locations
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
    if (!isset($_POST['service_id']) || !isset($_POST['location_ids'])) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    $location_ids = sanitize_text_field($_POST['location_ids']);
    
    // Get current priority
    $priority = kab_get_service_priority($service_id);
    
    // Update service locations
    $result = kab_add_update_service_location($service_id, $location_ids, $priority);
    
    if ($result !== false) {
        wp_send_json_success(array('message' => __('Service locations updated successfully.', 'konfidens-appointment-booking')));
    } else {
        wp_send_json_error(array('message' => __('Failed to update service locations.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_update_service_locations', 'kab_update_service_locations');
