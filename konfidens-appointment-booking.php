<?php
/**
 * Plugin Name: Konfidens Appointment Booking
 * Plugin URI: https://jobcvpro.com/konfidens-appointment-booking
 * Description: A WordPress plugin for appointment booking using the Konfidens API.
 * Version: 1.0.8
 * Author: Happy
 * Author URI: https://jobcvpro.com
 * Text Domain: konfidens-appointment-booking
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KAB_VERSION', '1.0.8');
define('KAB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KAB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once KAB_PLUGIN_DIR . 'kab-database.php';
require_once KAB_PLUGIN_DIR . 'kab-activator.php';
require_once KAB_PLUGIN_DIR . 'admin/kab-admin-settings.php';
require_once KAB_PLUGIN_DIR . 'admin/kab-admin-service.php';
require_once KAB_PLUGIN_DIR . 'admin/kab-admin-specialist.php';
require_once KAB_PLUGIN_DIR . 'admin/kab-admin-location.php';
require_once KAB_PLUGIN_DIR . 'frontend/kab-frontend-form.php';
require_once KAB_PLUGIN_DIR . 'frontend/kab-email.php';

// Define API constants from saved options
function kab_define_api_constants() {
    $base_url = get_option('kab_base_url', '');
    $api_key = get_option('kab_api_key', '');
    $clinic_id = get_option('kab_clinic_id', '');
    
    if (!empty($base_url) && !defined('BASE_URL')) {
        define('BASE_URL', $base_url);
    }
    
    if (!empty($api_key) && !defined('API_KEY')) {
        define('API_KEY', $api_key);
    }
    
    if (!empty($clinic_id) && !defined('CLINIC_ID')) {
        define('CLINIC_ID', $clinic_id);
    }
}
add_action('plugins_loaded', 'kab_define_api_constants', 5); // Run early

// Register activation hook
register_activation_hook(__FILE__, 'kab_activate');

// Register deactivation hook
register_deactivation_hook(__FILE__, 'kab_deactivate');

// Note: Uninstall functionality is handled by uninstall.php

/**
 * Plugin deactivation
 */
function kab_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Clear any scheduled events
    wp_clear_scheduled_hook('kab_daily_cleanup');
}

/**
 * Enqueue admin scripts and styles
 */
function kab_admin_enqueue_scripts() {
    $screen = get_current_screen();
    
    // Only load on plugin admin pages
    if (strpos($screen->id, 'kab') !== false) {
        wp_enqueue_style('kab-admin-css', KAB_PLUGIN_URL . 'admin/assets/admin.css', array(), KAB_VERSION);
        wp_enqueue_script('kab-admin-js', KAB_PLUGIN_URL . 'admin/assets/admin.js', array('jquery', 'wp-color-picker'), KAB_VERSION, true);
        wp_enqueue_style('wp-color-picker');
        
        wp_localize_script('kab-admin-js', 'kab_admin_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kab-admin-nonce'),
            'loading' => __('Loading...', 'konfidens-appointment-booking'),
            'error' => __('Error occurred. Please try again.', 'konfidens-appointment-booking'),
            'success' => __('Successfully saved.', 'konfidens-appointment-booking'),
            'confirm_delete' => __('Are you sure you want to delete this item?', 'konfidens-appointment-booking')
        ));
    }
}
add_action('admin_enqueue_scripts', 'kab_admin_enqueue_scripts');

/**
 * Enqueue frontend scripts and styles
 */
function kab_enqueue_scripts() {
    wp_enqueue_style('kab-css', KAB_PLUGIN_URL . 'frontend/assets/public.css', array(), KAB_VERSION);
    wp_enqueue_script('kab-js', KAB_PLUGIN_URL . 'frontend/assets/public.js', array('jquery'), KAB_VERSION, true);
    
    // Enqueue therapist-first form script
    wp_enqueue_script('kab-js-therapist-first', KAB_PLUGIN_URL . 'frontend/assets/public-therapist-first.js', array('jquery', 'kab-js'), KAB_VERSION, true);
    
    // Add reCAPTCHA if enabled
    if (get_option('kab_enable_recaptcha', false)) {
        $recaptcha_site_key = get_option('kab_recaptcha_site_key', '');
        
        if (!empty($recaptcha_site_key)) {
            wp_enqueue_script('kab-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . $recaptcha_site_key, array(), null, true);
        }
    }
    
    // Localize script (shared by both forms)
    wp_localize_script('kab-js', 'kab_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('kab-public-nonce'),
        'plugin_url' => plugin_dir_url(__FILE__),
        'loading' => __('Loading...', 'konfidens-appointment-booking'),
        'error' => __('Error occurred. Please try again.', 'konfidens-appointment-booking'),
        'no_services' => __('No services available.', 'konfidens-appointment-booking'),
        'no_specialists' => __('No specialists available for this service.', 'konfidens-appointment-booking'),
        'no_timeslots' => __('No available time slots for the selected date.', 'konfidens-appointment-booking'),
        'select_service' => __('Please select a service.', 'konfidens-appointment-booking'),
        'select_specialist' => __('Please select a specialist.', 'konfidens-appointment-booking'),
        'select_date' => __('Please select a date.', 'konfidens-appointment-booking'),
        'select_time' => __('Please select a time slot.', 'konfidens-appointment-booking'),
        'recaptcha_site_key' => get_option('kab_recaptcha_site_key', '')
    ));
    
    // Also localize for therapist-first script
    wp_localize_script('kab-js-therapist-first', 'kab_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('kab-public-nonce'),
        'plugin_url' => plugin_dir_url(__FILE__),
        'loading' => __('Loading...', 'konfidens-appointment-booking'),
        'error' => __('Error occurred. Please try again.', 'konfidens-appointment-booking'),
        'no_services' => __('No services available.', 'konfidens-appointment-booking'),
        'no_specialists' => __('No specialists available for this service.', 'konfidens-appointment-booking'),
        'no_timeslots' => __('No available time slots for the selected date.', 'konfidens-appointment-booking'),
        'select_service' => __('Please select a service.', 'konfidens-appointment-booking'),
        'select_specialist' => __('Please select a specialist.', 'konfidens-appointment-booking'),
        'select_date' => __('Please select a date.', 'konfidens-appointment-booking'),
        'select_time' => __('Please select a time slot.', 'konfidens-appointment-booking'),
        'recaptcha_site_key' => get_option('kab_recaptcha_site_key', '')
    ));
    
    // Get primary color from settings
    $primary_color = get_option('kab_primary_color', '#007bff');
    
    // Add inline CSS for primary color
    $custom_css = "
        :root {
            --kab-primary-color: {$primary_color};
            --kab-primary-color-light: " . kab_adjust_brightness($primary_color, 30) . ";
            --kab-primary-color-dark: " . kab_adjust_brightness($primary_color, -30) . ";
        }
    ";
    wp_add_inline_style('kab-css', $custom_css);
}
add_action('wp_enqueue_scripts', 'kab_enqueue_scripts');

/**
 * Adjust color brightness
 */
function kab_adjust_brightness($hex, $steps) {
    // Remove # if present
    $hex = ltrim($hex, '#');
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Adjust brightness
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));
    
    // Convert back to hex
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * API Request function
 */
function kab_api_request($endpoint, $args = array(), $method = 'GET') {
    // First try to use constants (preferred method)
    $base_url = defined('BASE_URL') ? BASE_URL : get_option('kab_base_url', '');
    $api_key = defined('API_KEY') ? API_KEY : get_option('kab_api_key', '');
    $clinic_id = defined('CLINIC_ID') ? CLINIC_ID : get_option('kab_clinic_id', '');
    
    if (empty($base_url) || empty($api_key)) {
        return array(
            'success' => false,
            'message' => 'API credentials not configured',
            'data' => null
        );
    }

    $url = trailingslashit($base_url) . ltrim($endpoint, '/');
    
    $headers = array(
        'x-api-key' => $api_key,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    );
    
    $request_args = array(
        'method' => $method,
        'headers' => $headers,
        'timeout' => 30,
        'sslverify' => true
    );
    
    if ($method === 'GET') {
        $url = add_query_arg($args, $url);
    } else {
        // For POST/PUT/DELETE, check if there's a 'clinic_id' in args
        // Some APIs expect clinic_id as query parameter even for POST requests
        $body_args = $args;
        $query_params = array();
        
        // Extract clinic_id if present (common pattern for multi-tenant APIs)
        if (isset($args['clinic_id'])) {
            $query_params['clinic_id'] = $args['clinic_id'];
            unset($body_args['clinic_id']);
        }
        
        // Add query parameters to URL if any
        if (!empty($query_params)) {
            $url = add_query_arg($query_params, $url);
        }
        
        $request_args['body'] = json_encode($body_args);
    }
    
    $response = wp_remote_request($url, $request_args);
    
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => $response->get_error_message(),
            'data' => null
        );
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);
    
    if ($response_code >= 200 && $response_code < 300) {
        return array(
            'success' => true,
            'message' => 'Success',
            'data' => $response_data
        );
    } else {
        $error_message = isset($response_data['message']) ? $response_data['message'] : 'API error';
        
        // For Forbidden errors, provide more context
        if ($response_code == 403) {
            $error_message = 'API Access Forbidden (403). Please check your API key and permissions.';
        }
        
        return array(
            'success' => false,
            'message' => $error_message,
            'data' => $response_data,
            'code' => $response_code
        );
    }
}

/**
 * Register shortcodes
 */
function kab_register_shortcodes() {
    add_shortcode('su_button', 'kab_button_shortcode');
}
add_action('init', 'kab_register_shortcodes');

/**
 * Button shortcode
 * 
 * [su_button]Book Now[/su_button] - Full flow: Service → Location → Therapist → Date & Time → Personal Details
 * [su_button id="SPECIALIST_ID"]Book With This Therapist[/su_button] - Therapist pre-selected: Therapist → Services → Location → Date & Time → Personal Details
 */
function kab_button_shortcode($atts, $content = null) {
    $atts = shortcode_atts(
        array(
            'background' => '',
            'class' => '',
            'id' => '', // specialist_id
            'set' => '', // Service set name (new preferred method)
        ),
        $atts,
        'su_button'
    );
    
    // Default content
    if (empty($content)) {
        $content = __('Book Now', 'konfidens-appointment-booking');
    }
    
    // Generate unique ID for popup
    $popup_id = 'kab-popup-' . uniqid();
    
    // Prepare button style
    $style = '';
    if (!empty($atts['background'])) {
        $style = 'style="background-color: ' . esc_attr($atts['background']) . ';"';
    }
    
    // Prepare button class
    $class = 'kab-button';
    if (!empty($atts['class'])) {
        $class .= ' ' . esc_attr($atts['class']);
    }
    
    // Prepare data attributes
    $data_attrs = '';
    if (!empty($atts['id'])) {
        $data_attrs .= ' data-specialist-id="' . esc_attr($atts['id']) . '"';
    }
    
    // Process service set if provided
    $service_ids = array();
    if (!empty($atts['set'])) {
        // Get service set from options
        $service_sets = get_option('kab_service_sets', array());
        $set_id = sanitize_text_field($atts['set']);
        
        if (isset($service_sets[$set_id]) && !empty($service_sets[$set_id]['service_ids'])) {
            $service_ids = $service_sets[$set_id]['service_ids'];
        }
    }
    
    // Start output buffering
    ob_start();
    
    // Button HTML
    ?>
    <button class="<?php echo esc_attr($class); ?>" <?php echo $style; ?> data-popup-target="<?php echo esc_attr($popup_id); ?>"<?php echo $data_attrs; ?>>
        <?php echo esc_html($content); ?>
    </button>
    
    <!-- Popup HTML -->
    <div id="<?php echo esc_attr($popup_id); ?>" class="kab-popup">
        <div class="kab-popup-overlay"></div>
        <div class="kab-popup-content">
            <button class="kab-popup-close">&times;</button>
            <div class="kab-popup-inner">
                <?php 
                // Determine which template to use based on parameters
                if (!empty($atts['id'])) {
                    // Therapist First flow: Therapist is pre-selected → show services according to therapist → Location → Date & Time → Personal Details
                    $therapist_atts = array('therapist_id' => $atts['id'], 'context' => 'popup');
                    $atts = $therapist_atts;
                    include KAB_PLUGIN_DIR . 'frontend/templates/form-template-therapist-first.php';
                } else {
                    // Regular form template: Service → Location → Therapist → Date & Time → Personal Details
                    $form_atts = array(
                        'context' => 'popup',
                        'service_ids' => $service_ids, // Pass filtered service IDs
                        'popup_id' => $popup_id
                    );
                    $atts = $form_atts;
                    include KAB_PLUGIN_DIR . 'frontend/templates/form-template.php';
                }
                ?>
            </div>
        </div>
    </div>
    <?php
    
    // Return buffered content
    return ob_get_clean();
}

/**
 * Register custom endpoint for thank you page
 */
function kab_register_endpoints() {
    add_rewrite_rule(
        'booking/thanks/?$',
        'index.php?kab_booking_thanks=true',
        'top'
    );
    
    add_rewrite_tag('%kab_booking_thanks%', '([^&]+)');
}
add_action('init', 'kab_register_endpoints');

/**
 * Handle custom endpoints
 */
function kab_handle_custom_endpoints() {
    global $wp_query;
    
    if (isset($wp_query->query_vars['kab_booking_thanks'])) {
        include KAB_PLUGIN_DIR . 'frontend/templates/thank-you-template.php';
        exit;
    }
}
add_action('template_redirect', 'kab_handle_custom_endpoints');