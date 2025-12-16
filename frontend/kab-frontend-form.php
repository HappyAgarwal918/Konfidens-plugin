<?php
/**
 * Frontend form functionality for Konfidens Appointment Booking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handler for getting services
 */
function kab_get_services() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    
    // Get services with priority
    $services = kab_get_services_with_priority();
    
    if (!empty($services)) {
        wp_send_json_success(array('services' => $services));
    } else {
        wp_send_json_error(array('message' => __('No services available.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_get_services', 'kab_get_services');
add_action('wp_ajax_nopriv_kab_get_services', 'kab_get_services');

/**
 * AJAX handler for getting locations
 */
function kab_get_locations_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    
    // Check required fields
    if (!isset($_POST['service_id']) || empty($_POST['service_id'])) {
        wp_send_json_error(array('message' => __('Service ID is required.', 'konfidens-appointment-booking')));
    }
    
    $service_id = sanitize_text_field($_POST['service_id']);
    
    // Get service locations
    $service_location_ids = kab_get_service_locations($service_id);
    
    if (!empty($service_location_ids)) {
        $locations = array();
        
        foreach ($service_location_ids as $location_id) {
            $location = kab_get_location_by_id($location_id);
            
            if ($location) {
                $locations[] = $location;
            }
        }
        
        if (!empty($locations)) {
            wp_send_json_success(array('locations' => $locations));
        } else {
            wp_send_json_error(array('message' => __('No locations available for this service.', 'konfidens-appointment-booking')));
        }
    } else {
        wp_send_json_error(array('message' => __('No locations available for this service.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_get_locations', 'kab_get_locations_ajax');
add_action('wp_ajax_nopriv_kab_get_locations', 'kab_get_locations_ajax');

/**
 * AJAX handler for getting specialists
 */
function kab_get_specialists_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
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
        foreach ($specialists as $key => $specialist) {
            // First check if profile_image is available (old plugin format)
            if (isset($specialist['profile_image'])) {
                $specialists[$key]['image_url'] = $specialist['profile_image'];
            }
            // Then check for profilepicture property
            else if (isset($specialist['profilepicture'])) {
                $specialists[$key]['image_url'] = 'https://images-files.konfidens.com/practitioners/profilepicture/' . $specialist['profilepicture'];
            }
            // Finally, check for image property that might contain a partial path
            else if (isset($specialist['image']) && strpos($specialist['image'], 'http') !== 0) {
                $specialists[$key]['image_url'] = 'https://images-files.konfidens.com/practitioners/profilepicture/' . $specialist['image'];
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

/**
 * AJAX handler for getting available dates
 */
function kab_get_available_dates() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
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
    
    // Debug information
    error_log('API Response for dates: ' . print_r($timeslots_response, true));
    
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
            
            // Get time slots for the selected date
            $from_date_selected = date('Y-m-d\T00:00:00\Z', strtotime($selected_date));
            $to_date_selected = date('Y-m-d\T23:59:59\Z', strtotime($selected_date));
            
            $slots_response = kab_api_request('timeslots', array(
                'clinic_id' => get_option('kab_clinic_id', ''),
                'from_date' => $from_date_selected,
                'to_date' => $to_date_selected,
                'service_id' => $service_id,
                'specialist_id' => $specialist_id
            ));
            
            if ($slots_response['success'] && !empty($slots_response['data'])) {
                $html_booking_slot = '';
                
                foreach ($slots_response['data'] as $slot) {
                    if (is_array($slot)) {
                        // Old format
                        if (isset($slot['time_from']) && isset($slot['booking_token'])) {
                            $time = date('H:i', strtotime($slot['time_from']));
                            $slot_value = $slot['booking_token'];
                            $html_booking_slot .= "<div class='kab-time-slot' data-timeslot='" . esc_attr($slot_value) . "'>" . esc_html($time) . "</div>";
                        }
                    } else {
                        // New format
                        $time = explode('T', $slot)[1];
                        $time = substr($time, 0, 5);
                        $html_booking_slot .= "<div class='kab-time-slot' data-timeslot='" . esc_attr($slot) . "'>" . esc_html($time) . "</div>";
                    }
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

/**
 * AJAX handler for getting timeslots
 */
function kab_get_timeslots_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
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
    
    // Debug information
    error_log('API Response for timeslots: ' . print_r($timeslots_response, true));
    
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

/**
 * AJAX handler for creating a booking
 */
function kab_create_booking() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-public-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    
    // Check required fields
    $required_fields = array(
        'service_id' => __('Service is required.', 'konfidens-appointment-booking'),
        'specialist_id' => __('Specialist is required.', 'konfidens-appointment-booking'),
        'timeslot' => __('Time slot is required.', 'konfidens-appointment-booking'),
        'first_name' => __('First name is required.', 'konfidens-appointment-booking'),
        'last_name' => __('Last name is required.', 'konfidens-appointment-booking'),
        'email' => __('Email is required.', 'konfidens-appointment-booking'),
        'phone' => __('Phone number is required.', 'konfidens-appointment-booking')
    );
    
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
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
    
    // Get service and specialist names
    $service = kab_get_service_by_id($service_id);
    $specialist = kab_get_therapist_by_id($specialist_id);
    
    $service_name = isset($service['name']) ? $service['name'] : '';
    $specialist_name = isset($specialist['name']) ? $specialist['name'] : '';
    
    // Parse timeslot
    $timeslot_parts = explode('T', $timeslot);
    $timeslot_date = isset($timeslot_parts[0]) ? $timeslot_parts[0] : '';
    $timeslot_time = isset($timeslot_parts[1]) ? substr($timeslot_parts[1], 0, 5) : '';
    
    // Create booking in API
    $booking_data = array(
        'service_id' => $service_id,
        'specialist_id' => $specialist_id,
        'starts_at' => $timeslot,
        'patient' => array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'notes' => $notes
        )
    );
    
    $booking_response = kab_api_request('bookings', array_merge(
        array('clinic_id' => get_option('kab_clinic_id', '')),
        $booking_data
    ), 'POST');
    
    if ($booking_response['success'] && !empty($booking_response['data'])) {
        $booking_id = $booking_response['data']['id'];
        
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
        
        // Return success with booking details
        wp_send_json_success(array(
            'message' => __('Booking created successfully.', 'konfidens-appointment-booking'),
            'booking_id' => $booking_id,
            'redirect_url' => home_url('/booking/thanks') . '?' . http_build_query(array(
                'serviceId' => $service_id,
                'serviceTitle' => $service_name,
                'practitionerId' => $specialist_id,
                'startsAt' => $timeslot,
                'bookingId' => $booking_id
            ))
        ));
    } else {
        $error_message = isset($booking_response['message']) ? $booking_response['message'] : __('Failed to create booking. Please try again.', 'konfidens-appointment-booking');
        wp_send_json_error(array('message' => $error_message));
    }
}
add_action('wp_ajax_kab_create_booking', 'kab_create_booking');
add_action('wp_ajax_nopriv_kab_create_booking', 'kab_create_booking');
