<?php
/**
 * Plugin Name: Konfidens Appointment Booking
 * Plugin URI: https://jobcvpro.com/konfidens-appointment-booking
 * Description: A WordPress plugin for appointment booking using the Konfidens API.
 * Version: 1.2.3
 * Author: Happy
 * Author URI: https://jobcvpro.com
 * Text Domain: konfidens-appointment-booking
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('KAB_VERSION', '1.2.3');
define('KAB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KAB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once KAB_PLUGIN_DIR . 'kab-database.php';
require_once KAB_PLUGIN_DIR . 'kab-activator.php';
require_once KAB_PLUGIN_DIR . 'admin/kab-admin-settings.php';
require_once KAB_PLUGIN_DIR . 'admin/kab-admin-service-category.php';
require_once KAB_PLUGIN_DIR . 'admin/kab-admin-service.php';
require_once KAB_PLUGIN_DIR . 'admin/kab-admin-specialist.php';
require_once KAB_PLUGIN_DIR . 'admin/kab-admin-location.php';
require_once KAB_PLUGIN_DIR . 'frontend/kab-frontend-form.php';
require_once KAB_PLUGIN_DIR . 'frontend/kab-email.php';

// Define API constants from WordPress options for use throughout the plugin
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
add_action('plugins_loaded', 'kab_define_api_constants', 5);
register_activation_hook(__FILE__, 'kab_activate');

register_deactivation_hook(__FILE__, 'kab_deactivate');

function kab_deactivate() {
    flush_rewrite_rules();
}


// Get the correct "From" email address for SMTP compatibility
function kab_get_from_email() {
    // Try to get from WP Mail SMTP plugin
    if (function_exists('wp_mail_smtp')) {
        $options = get_option('wp_mail_smtp', array());
        if (!empty($options['mail']['from_email'])) {
            return $options['mail']['from_email'];
        }
    }
    
    // Try to get from Easy WP SMTP
    $easy_smtp = get_option('swpsmtp_options', array());
    if (!empty($easy_smtp['from_email_field'])) {
        return $easy_smtp['from_email_field'];
    }
    
    // Try to get from Post SMTP
    $post_smtp = get_option('postman_options', array());
    if (!empty($post_smtp['sender_email'])) {
        return $post_smtp['sender_email'];
    }
    
    // Try to get from PHPMailer global (if available)
    global $phpmailer;
    if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->From)) {
        return $phpmailer->From;
    }
    
    // Fallback to admin email
    return get_option('admin_email');
}

// Get the correct "From" name for SMTP compatibility
function kab_get_from_name() {
    // Try to get from WP Mail SMTP plugin
    if (function_exists('wp_mail_smtp')) {
        $options = get_option('wp_mail_smtp', array());
        if (!empty($options['mail']['from_name'])) {
            return $options['mail']['from_name'];
        }
    }
    
    // Try to get from Easy WP SMTP
    $easy_smtp = get_option('swpsmtp_options', array());
    if (!empty($easy_smtp['from_name_field'])) {
        return $easy_smtp['from_name_field'];
    }
    
    // Try to get from Post SMTP
    $post_smtp = get_option('postman_options', array());
    if (!empty($post_smtp['sender_name'])) {
        return $post_smtp['sender_name'];
    }
    
    // Fallback to site name
    return get_bloginfo('name');
}

function kab_admin_enqueue_scripts() {
    $screen = get_current_screen();
    // Check for both 'kab' and 'konfidens' in screen ID to cover all admin pages
    if (strpos($screen->id, 'kab') !== false || strpos($screen->id, 'konfidens') !== false) {
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

// Register and conditionally enqueue frontend scripts (only when shortcode is used)
function kab_register_scripts() {
    wp_register_style('kab-css', KAB_PLUGIN_URL . 'frontend/assets/public.css', array(), KAB_VERSION);
    wp_register_script('kab-js', KAB_PLUGIN_URL . 'frontend/assets/public.js', array('jquery'), KAB_VERSION, true);
    wp_register_script('kab-js-therapist-first', KAB_PLUGIN_URL . 'frontend/assets/public-therapist-first.js', array('jquery', 'kab-js'), KAB_VERSION, true);
}
add_action('wp_enqueue_scripts', 'kab_register_scripts', 5);

/**
 * Enqueue booking assets (called from shortcode so scripts load only on pages with booking form)
 */
function kab_enqueue_booking_assets() {
    wp_enqueue_style('kab-css');
    wp_enqueue_script('kab-js');
    wp_enqueue_script('kab-js-therapist-first');
    wp_localize_script('kab-js', 'kab_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('kab-public-nonce'),
        'logged_in' => is_user_logged_in(),
        'rest_nonce_url' => rest_url('kab/v1/public-nonce'),
        'ajax_nonce_action' => 'kab_get_public_nonce',
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

    if (get_option('kab_enable_recaptcha', false)) {
        $recaptcha_site_key = get_option('kab_recaptcha_site_key', '');
        if (!empty($recaptcha_site_key) && !wp_script_is('kab-recaptcha', 'enqueued')) {
            wp_enqueue_script('kab-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . $recaptcha_site_key, array(), null, true);
        }
    }
}

/**
 * Warm booking cache when shortcode is shown (so first form open is fast)
 * Only runs if cache is empty; uses transients so later requests are instant.
 */
function kab_warm_booking_cache() {
    if (get_transient('kab_services_with_priority') === false) {
        kab_get_services_with_priority();
    }
    if (get_transient('kab_all_therapists') === false) {
        kab_get_all_therapists();
    }
}

/**
 * Clear API caches (call after saving settings or when data may have changed)
 */
function kab_clear_api_cache() {
    delete_transient('kab_services_with_priority');
    delete_transient('kab_all_therapists');
    delete_transient('kab_all_services_with_categories');
    delete_transient('kab_therapist_service_map');
}

// Make API requests to Konfidens API with authentication
function kab_api_request($endpoint, $args = array(), $method = 'GET') {
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
        $body_args = $args;
        $query_params = array();
        
        if (isset($args['clinic_id'])) {
            $query_params['clinic_id'] = $args['clinic_id'];
            unset($body_args['clinic_id']);
        }
        
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
 * REST API: return a fresh public nonce for booking forms.
 * Used when the popup opens so cached pages (e.g. for logged-out users) get a valid nonce.
 */
function kab_rest_register_public_nonce() {
    register_rest_route('kab/v1', '/public-nonce', array(
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function () {
            return array('nonce' => wp_create_nonce('kab-public-nonce'));
        },
    ));
}
add_action('rest_api_init', 'kab_rest_register_public_nonce');

/**
 * AJAX fallback: return a fresh public nonce (no nonce required).
 * Used when REST API is disabled or blocked (e.g. by host/security plugin).
 */
function kab_ajax_get_public_nonce() {
    nocache_headers();
    wp_send_json_success(array('nonce' => wp_create_nonce('kab-public-nonce')));
}
add_action('wp_ajax_kab_get_public_nonce', 'kab_ajax_get_public_nonce');
add_action('wp_ajax_nopriv_kab_get_public_nonce', 'kab_ajax_get_public_nonce');

// Register shortcode for booking button
function kab_register_shortcodes() {
    add_shortcode('su_button', 'kab_button_shortcode');
}
add_action('init', 'kab_register_shortcodes');

// Shortcode handler: [su_button] - Creates booking form popup button
// Parameters: id (therapist ID), set (service set ID), background, class
function kab_button_shortcode($atts, $content = null) {
    kab_enqueue_booking_assets();
    // Warm API cache on first load so the form responds quickly when user opens it
    kab_warm_booking_cache();
    $atts = shortcode_atts(
        array(
            'background' => '', // Button background color
            'class' => '', // Additional CSS classes
            'id' => '', // Pre-select therapist ID (therapist-first flow)
            'set' => '', // Service set ID to filter categories/locations
        ),
        $atts,
        'su_button'
    );
    
    // Default button text
    if (empty($content)) {
        $content = __('Book Now', 'konfidens-appointment-booking');
    }
    
    // Generate unique popup ID
    $popup_id = 'kab-popup-' . uniqid();
    $style = '';
    if (!empty($atts['background'])) {
        $style = 'style="background-color: ' . esc_attr($atts['background']) . ';"';
    }
    
    $class = 'kab-button';
    if (!empty($atts['class'])) {
        $class .= ' ' . esc_attr($atts['class']);
    }
    
    $data_attrs = '';
    if (!empty($atts['id'])) {
        $data_attrs .= ' data-specialist-id="' . esc_attr($atts['id']) . '"';
    }
    if (!empty($atts['set'])) {
        $data_attrs .= ' data-service-set="' . esc_attr($atts['set']) . '"';
    }
    
    ob_start();
    ?>
    <!-- Booking button -->
    <button class="<?php echo esc_attr($class); ?>" <?php echo $style; ?> data-popup-target="<?php echo esc_attr($popup_id); ?>"<?php echo $data_attrs; ?>>
        <?php echo esc_html($content); ?>
    </button>
    
    <!-- Booking form popup -->
    <div id="<?php echo esc_attr($popup_id); ?>" class="kab-popup">
        <div class="kab-popup-overlay"></div>
        <div class="kab-popup-content">
            <button class="kab-popup-close">&times;</button>
            <div class="kab-popup-inner">
                <?php 
                // Use therapist-first form if therapist ID is provided, otherwise use standard form
                if (!empty($atts['id'])) {
                    $therapist_atts = array(
                        'therapist_id' => $atts['id'], 
                        'context' => 'popup',
                        'service_set_id' => !empty($atts['set']) ? $atts['set'] : ''
                    );
                    $atts = $therapist_atts;
                    include KAB_PLUGIN_DIR . 'frontend/templates/form-template-therapist-first.php';
                } else {
                    $form_atts = array(
                        'context' => 'popup',
                        'popup_id' => $popup_id,
                        'service_set_id' => !empty($atts['set']) ? $atts['set'] : ''
                    );
                    $atts = $form_atts;
                    include KAB_PLUGIN_DIR . 'frontend/templates/form-template.php';
                }
                ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
