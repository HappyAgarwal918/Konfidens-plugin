<?php
/**
 * Admin settings functionality for Konfidens Appointment Booking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu
 */
function kab_add_admin_menu() {
    // Main menu
    add_menu_page(
        __('Konfidens', 'konfidens-appointment-booking'),
        __('Konfidens', 'konfidens-appointment-booking'),
        'manage_options',
        'konfidens',
        'kab_display_dashboard_page',
        'dashicons-calendar-alt',
        30
    );
    
    // Service Categories submenu
    add_submenu_page(
        'konfidens',
        __('Service Categories', 'konfidens-appointment-booking'),
        __('Service Categories', 'konfidens-appointment-booking'),
        'manage_options',
        'konfidens-service-categories',
        'kab_display_service_categories_page'
    );
    
    // Services submenu
    add_submenu_page(
        'konfidens',
        __('Services', 'konfidens-appointment-booking'),
        __('Services', 'konfidens-appointment-booking'),
        'manage_options',
        'konfidens-services',
        'kab_display_services_page'
    );
    
    // Therapists submenu
    add_submenu_page(
        'konfidens',
        __('Therapists', 'konfidens-appointment-booking'),
        __('Therapists', 'konfidens-appointment-booking'),
        'manage_options',
        'konfidens-therapists',
        'kab_display_therapists_page'
    );
    
    // Locations submenu
    add_submenu_page(
        'konfidens',
        __('Locations', 'konfidens-appointment-booking'),
        __('Locations', 'konfidens-appointment-booking'),
        'manage_options',
        'konfidens-locations',
        'kab_display_locations_page'
    );
    
    // Settings submenu
    add_submenu_page(
        'konfidens',
        __('Settings', 'konfidens-appointment-booking'),
        __('Settings', 'konfidens-appointment-booking'),
        'manage_options',
        'konfidens-settings',
        'kab_display_settings_page'
    );
    
    // Service Sets submenu (for alternative form)
    add_submenu_page(
        'konfidens',
        __('Service Sets', 'konfidens-appointment-booking'),
        __('Service Sets', 'konfidens-appointment-booking'),
        'manage_options',
        'konfidens-service-sets',
        'kab_display_service_sets_page'
    );
    
    // Bookings submenu
    add_submenu_page(
        'konfidens',
        __('Bookings', 'konfidens-appointment-booking'),
        __('Bookings', 'konfidens-appointment-booking'),
        'manage_options',
        'konfidens-bookings',
        'kab_display_bookings_page'
    );
}
add_action('admin_menu', 'kab_add_admin_menu');

/**
 * Display dashboard page
 */
function kab_display_dashboard_page() {
    // Get recent bookings
    $recent_bookings = kab_get_all_bookings(5);
    
    // Get API status
    $api_status = kab_api_request('services', array('clinic_id' => get_option('kab_clinic_id', '')));
    $api_connected = $api_status['success'];
    
    ?>
    <div class="wrap kab-admin">
        <h1><?php _e('Konfidens Appointment Booking Dashboard', 'konfidens-appointment-booking'); ?></h1>
        
        <div class="kab-dashboard-widgets">
            <div class="kab-dashboard-widget">
                <div class="kab-dashboard-widget-header">
                    <h2><?php _e('System Status', 'konfidens-appointment-booking'); ?></h2>
                </div>
                <div class="kab-dashboard-widget-content">
                    <table class="widefat">
                        <tr>
                            <td><?php _e('Plugin Version', 'konfidens-appointment-booking'); ?></td>
                            <td><strong><?php echo KAB_VERSION; ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php _e('WordPress Version', 'konfidens-appointment-booking'); ?></td>
                            <td><strong><?php echo get_bloginfo('version'); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php _e('PHP Version', 'konfidens-appointment-booking'); ?></td>
                            <td><strong><?php echo phpversion(); ?></strong></td>
                        </tr>
                        <tr>
                            <td><?php _e('API Connection', 'konfidens-appointment-booking'); ?></td>
                            <td>
                                <?php if ($api_connected): ?>
                                    <span class="kab-status kab-status-success"><?php _e('Connected', 'konfidens-appointment-booking'); ?></span>
                                <?php else: ?>
                                    <span class="kab-status kab-status-error"><?php _e('Not Connected', 'konfidens-appointment-booking'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if (!$api_connected): ?>
                        <p class="kab-notice kab-notice-error">
                            <?php _e('API connection failed. Please check your API credentials in the settings.', 'konfidens-appointment-booking'); ?>
                            <a href="<?php echo admin_url('admin.php?page=konfidens-settings'); ?>" class="button button-secondary">
                                <?php _e('Go to Settings', 'konfidens-appointment-booking'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="kab-dashboard-widget">
                <div class="kab-dashboard-widget-header">
                    <h2><?php _e('Recent Bookings', 'konfidens-appointment-booking'); ?></h2>
                </div>
                <div class="kab-dashboard-widget-content">
                    <?php if (!empty($recent_bookings)): ?>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('Booking ID', 'konfidens-appointment-booking'); ?></th>
                                    <th><?php _e('Patient', 'konfidens-appointment-booking'); ?></th>
                                    <th><?php _e('Service', 'konfidens-appointment-booking'); ?></th>
                                    <th><?php _e('Therapist', 'konfidens-appointment-booking'); ?></th>
                                    <th><?php _e('Date & Time', 'konfidens-appointment-booking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <tr>
                                        <td><?php echo esc_html($booking->booking_id); ?></td>
                                        <td><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></td>
                                        <td><?php echo esc_html($booking->service_name); ?></td>
                                        <td><?php echo esc_html($booking->specialist_name); ?></td>
                                        <td>
                                            <?php 
                                            $date = date_i18n(get_option('date_format'), strtotime($booking->timeslot_date));
                                            $time = date_i18n(get_option('time_format'), strtotime($booking->timeslot_time));
                                            echo esc_html($date . ' ' . $time);
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="kab-view-all">
                            <a href="<?php echo admin_url('admin.php?page=konfidens-bookings'); ?>" class="button button-secondary">
                                <?php _e('View All Bookings', 'konfidens-appointment-booking'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <p><?php _e('No bookings yet.', 'konfidens-appointment-booking'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="kab-dashboard-widget">
                <div class="kab-dashboard-widget-header">
                    <h2><?php _e('Shortcodes', 'konfidens-appointment-booking'); ?></h2>
                </div>
                <div class="kab-dashboard-widget-content">
                    <p><?php _e('Use these shortcodes to add booking functionality to your pages:', 'konfidens-appointment-booking'); ?></p>
                    
                    <div class="kab-shortcode-example">
                        <h4><?php _e('1. Popup Booking Button - Full Flow', 'konfidens-appointment-booking'); ?></h4>
                        <code>[su_button]Book Now[/su_button]</code>
                        <p><?php _e('Creates a button that opens the booking form in a popup. Flow: Service → Location → Therapist → Date & Time → Personal Details.', 'konfidens-appointment-booking'); ?></p>
                    </div>
                    
                    <div class="kab-shortcode-example">
                        <h4><?php _e('2. Popup Button with Pre-selected Therapist', 'konfidens-appointment-booking'); ?></h4>
                        <code>[su_button id="SPECIALIST_ID"]Book With This Therapist[/su_button]</code>
                        <p><?php _e('Creates a button that opens the booking form in a popup with a specific therapist pre-selected. Flow: Therapist (pre-selected) → Services (according to therapist) → Location → Date & Time → Personal Details. You can change the therapist. Replace SPECIALIST_ID with the actual therapist ID.', 'konfidens-appointment-booking'); ?></p>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Display bookings page
 */
function kab_display_bookings_page() {
    // Get bookings
    $bookings = kab_get_all_bookings();
    
    ?>
    <div class="wrap kab-admin">
        <h1><?php _e('Bookings', 'konfidens-appointment-booking'); ?></h1>
        
        <?php if (!empty($bookings)): ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Booking ID', 'konfidens-appointment-booking'); ?></th>
                        <th><?php _e('Patient', 'konfidens-appointment-booking'); ?></th>
                        <th><?php _e('Contact', 'konfidens-appointment-booking'); ?></th>
                        <th><?php _e('Service', 'konfidens-appointment-booking'); ?></th>
                        <th><?php _e('Therapist', 'konfidens-appointment-booking'); ?></th>
                        <th><?php _e('Date & Time', 'konfidens-appointment-booking'); ?></th>
                        <th><?php _e('Created', 'konfidens-appointment-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo esc_html($booking->booking_id); ?></td>
                            <td><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></td>
                            <td>
                                <?php echo esc_html($booking->email); ?><br>
                                <?php echo esc_html($booking->phone); ?>
                            </td>
                            <td><?php echo esc_html($booking->service_name); ?></td>
                            <td><?php echo esc_html($booking->specialist_name); ?></td>
                            <td>
                                <?php 
                                $date = date_i18n(get_option('date_format'), strtotime($booking->timeslot_date));
                                $time = date_i18n(get_option('time_format'), strtotime($booking->timeslot_time));
                                echo esc_html($date . ' ' . $time);
                                ?>
                            </td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->created_at))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No bookings found.', 'konfidens-appointment-booking'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Display settings page
 */
function kab_display_settings_page() {
    // Check if form is submitted
    if (isset($_POST['kab_save_settings']) && check_admin_referer('kab_settings_nonce', 'kab_settings_nonce')) {
        // Save settings
        $settings = array(
            'base_url' => sanitize_url($_POST['kab_base_url']),
            'api_key' => sanitize_text_field($_POST['kab_api_key']),
            'clinic_id' => sanitize_text_field($_POST['kab_clinic_id']),
            'primary_color' => sanitize_hex_color($_POST['kab_primary_color']),
            'booking_msg' => wp_kses_post($_POST['kab_booking_msg'])
        );
        
        // Save settings in database table
        $result = kab_save_settings($settings);
        
        // Save WordPress options
        update_option('kab_base_url', $settings['base_url']);
        update_option('kab_api_key', $settings['api_key']);
        update_option('kab_clinic_id', $settings['clinic_id']);
        update_option('kab_primary_color', $settings['primary_color']);
        update_option('kab_booking_msg', $settings['booking_msg']);
        
        // Email settings
        if (isset($_POST['kab_email_recipient'])) {
            update_option('kab_email_recipient', sanitize_email($_POST['kab_email_recipient']));
        }
        
        if (isset($_POST['kab_email_subject'])) {
            update_option('kab_email_subject', sanitize_text_field($_POST['kab_email_subject']));
        }
        
        // reCAPTCHA settings
        update_option('kab_enable_recaptcha', isset($_POST['kab_enable_recaptcha']) ? 1 : 0);
        update_option('kab_recaptcha_site_key', sanitize_text_field($_POST['kab_recaptcha_site_key']));
        update_option('kab_recaptcha_secret_key', sanitize_text_field($_POST['kab_recaptcha_secret_key']));
        
        // Set admin notice
        if ($result !== false) {
            add_settings_error(
                'kab_settings',
                'kab_settings_saved',
                __('Settings saved successfully.', 'konfidens-appointment-booking'),
                'success'
            );
            
            // Force reload of constants
            if (!defined('BASE_URL')) {
                define('BASE_URL', $settings['base_url']);
            }
            if (!defined('API_KEY')) {
                define('API_KEY', $settings['api_key']);
            }
            if (!defined('CLINIC_ID')) {
                define('CLINIC_ID', $settings['clinic_id']);
            }
        } else {
            add_settings_error(
                'kab_settings',
                'kab_settings_error',
                __('Error saving settings.', 'konfidens-appointment-booking'),
                'error'
            );
        }
    }
    
    // Get settings
    $settings = kab_get_settings();
    $email_recipient = get_option('kab_email_recipient', get_option('admin_email'));
    $email_subject = get_option('kab_email_subject', __('New Appointment Booking', 'konfidens-appointment-booking'));
    $enable_recaptcha = get_option('kab_enable_recaptcha', false);
    $recaptcha_site_key = get_option('kab_recaptcha_site_key', '');
    $recaptcha_secret_key = get_option('kab_recaptcha_secret_key', '');
    
    ?>
    <div class="wrap kab-admin">
        <h1><?php _e('Konfidens Settings', 'konfidens-appointment-booking'); ?></h1>
        
        <?php settings_errors('kab_settings'); ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('kab_settings_nonce', 'kab_settings_nonce'); ?>
            
            <div class="kab-settings-section">
                <h2><?php _e('API Settings', 'konfidens-appointment-booking'); ?></h2>
                <p><?php _e('Configure your Konfidens API credentials.', 'konfidens-appointment-booking'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kab_base_url"><?php _e('API Base URL', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="kab_base_url" id="kab_base_url" class="regular-text" 
                                value="<?php echo esc_attr($settings->base_url); ?>" required />
                            <p class="description"><?php _e('The base URL for the Konfidens API (e.g., https://api.konfidens.com/v1).', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="kab_api_key"><?php _e('API Key', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="kab_api_key" id="kab_api_key" class="regular-text" 
                                value="<?php echo esc_attr($settings->api_key); ?>" required />
                            <p class="description"><?php _e('Your Konfidens API authentication key.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="kab_clinic_id"><?php _e('Clinic ID', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="kab_clinic_id" id="kab_clinic_id" class="regular-text" 
                                value="<?php echo esc_attr($settings->clinic_id); ?>" required />
                            <p class="description"><?php _e('Your Konfidens clinic identifier.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="kab-settings-section">
                <h2><?php _e('Appearance Settings', 'konfidens-appointment-booking'); ?></h2>
                <p><?php _e('Customize the appearance of the booking form.', 'konfidens-appointment-booking'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kab_primary_color"><?php _e('Primary Color', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="kab_primary_color" id="kab_primary_color" class="kab-color-picker" 
                                value="<?php echo esc_attr($settings->primary_color); ?>" />
                            <p class="description"><?php _e('Choose the primary color for buttons and highlights.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="kab_booking_msg"><?php _e('Booking Confirmation Message', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_editor(
                                $settings->booking_msg,
                                'kab_booking_msg',
                                array(
                                    'textarea_name' => 'kab_booking_msg',
                                    'textarea_rows' => 5,
                                    'media_buttons' => false,
                                    'teeny' => true
                                )
                            );
                            ?>
                            <p class="description"><?php _e('The message displayed to users after a successful booking.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="kab-settings-section">
                <h2><?php _e('Email Settings', 'konfidens-appointment-booking'); ?></h2>
                <p><?php _e('Configure email notifications for new bookings.', 'konfidens-appointment-booking'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kab_email_recipient"><?php _e('Notification Recipient', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="email" name="kab_email_recipient" id="kab_email_recipient" class="regular-text" 
                                value="<?php echo esc_attr($email_recipient); ?>" />
                            <p class="description"><?php _e('Email address to receive booking notifications.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="kab_email_subject"><?php _e('Email Subject', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="kab_email_subject" id="kab_email_subject" class="regular-text" 
                                value="<?php echo esc_attr($email_subject); ?>" />
                            <p class="description"><?php _e('Subject line for booking notification emails.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="kab-settings-section">
                <h2><?php _e('Security Settings', 'konfidens-appointment-booking'); ?></h2>
                <p><?php _e('Configure security features for the booking form.', 'konfidens-appointment-booking'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kab_enable_recaptcha"><?php _e('Enable reCAPTCHA', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="kab_enable_recaptcha" id="kab_enable_recaptcha" 
                                <?php checked($enable_recaptcha, true); ?> value="1" />
                            <p class="description"><?php _e('Enable Google reCAPTCHA v3 to protect against spam submissions.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                    <tr class="kab-recaptcha-field" <?php echo !$enable_recaptcha ? 'style="display:none;"' : ''; ?>>
                        <th scope="row">
                            <label for="kab_recaptcha_site_key"><?php _e('reCAPTCHA Site Key', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="kab_recaptcha_site_key" id="kab_recaptcha_site_key" class="regular-text" 
                                value="<?php echo esc_attr($recaptcha_site_key); ?>" />
                            <p class="description"><?php _e('Your Google reCAPTCHA v3 site key.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                    <tr class="kab-recaptcha-field" <?php echo !$enable_recaptcha ? 'style="display:none;"' : ''; ?>>
                        <th scope="row">
                            <label for="kab_recaptcha_secret_key"><?php _e('reCAPTCHA Secret Key', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="kab_recaptcha_secret_key" id="kab_recaptcha_secret_key" class="regular-text" 
                                value="<?php echo esc_attr($recaptcha_secret_key); ?>" />
                            <p class="description"><?php _e('Your Google reCAPTCHA v3 secret key.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" name="kab_save_settings" class="button button-primary" value="<?php _e('Save Settings', 'konfidens-appointment-booking'); ?>" />
            </p>
        </form>
    </div>
    <?php
}

/**
 * Display Service Sets page
 */
function kab_display_service_sets_page() {
    // Handle form submissions
    if (isset($_POST['kab_save_service_set']) && check_admin_referer('kab_service_set_nonce', 'kab_service_set_nonce')) {
        $set_name = sanitize_text_field($_POST['kab_set_name']);
        $set_id = isset($_POST['kab_set_id']) ? sanitize_text_field($_POST['kab_set_id']) : '';
        $service_ids = isset($_POST['kab_service_ids']) && is_array($_POST['kab_service_ids']) 
            ? array_map('sanitize_text_field', $_POST['kab_service_ids']) 
            : array();
        
        if (!empty($set_name)) {
            $sets = get_option('kab_service_sets', array());
            
            if (empty($set_id)) {
                // Create new set
                $set_id = 'set_' . time() . '_' . rand(1000, 9999);
            }
            
            $sets[$set_id] = array(
                'name' => $set_name,
                'service_ids' => $service_ids,
                'created' => isset($sets[$set_id]['created']) ? $sets[$set_id]['created'] : current_time('mysql')
            );
            
            update_option('kab_service_sets', $sets);
            add_settings_error(
                'kab_service_sets',
                'kab_service_set_saved',
                __('Service set saved successfully.', 'konfidens-appointment-booking'),
                'success'
            );
        }
    }
    
    // Handle delete
    if (isset($_GET['delete_set']) && check_admin_referer('kab_delete_set_' . $_GET['delete_set'])) {
        $sets = get_option('kab_service_sets', array());
        $set_id = sanitize_text_field($_GET['delete_set']);
        if (isset($sets[$set_id])) {
            unset($sets[$set_id]);
            update_option('kab_service_sets', $sets);
            add_settings_error(
                'kab_service_sets',
                'kab_service_set_deleted',
                __('Service set deleted successfully.', 'konfidens-appointment-booking'),
                'success'
            );
        }
    }
    
    // Get all service sets
    $service_sets = get_option('kab_service_sets', array());
    
    // Get services from API
    $services_response = kab_api_request('services', array('clinic_id' => get_option('kab_clinic_id', '')));
    $all_services = array();
    $enabled_service_ids = array(); // Track enabled service IDs for validation
    if ($services_response['success'] && !empty($services_response['data'])) {
        // Filter only enabled services (same as old plugin)
        $all_services = array_filter($services_response['data'], function ($service) {
            return (isset($service['code']) ? $service['code'] === null : true) && !empty($service['enabled_by_specialists']);
        });
        // Re-index array after filtering
        $all_services = array_values($all_services);
        
        // Create array of enabled service IDs for quick lookup
        foreach ($all_services as $service) {
            if (isset($service['id'])) {
                $enabled_service_ids[] = $service['id'];
            }
        }
    }
    
    // Clean up service sets: remove disabled services and update if needed
    $sets_updated = false;
    foreach ($service_sets as $set_id => &$set) {
        if (!empty($set['service_ids']) && is_array($set['service_ids'])) {
            $original_count = count($set['service_ids']);
            // Filter out disabled services
            $set['service_ids'] = array_filter($set['service_ids'], function($service_id) use ($enabled_service_ids) {
                return in_array($service_id, $enabled_service_ids);
            });
            $set['service_ids'] = array_values($set['service_ids']); // Re-index
            
            if (count($set['service_ids']) < $original_count) {
                $sets_updated = true;
            }
        }
    }
    unset($set); // Unset reference
    
    // Save cleaned up sets if any were modified
    if ($sets_updated) {
        update_option('kab_service_sets', $service_sets);
        add_settings_error(
            'kab_service_sets',
            'kab_service_set_cleaned',
            __('Service sets have been automatically cleaned: disabled services have been removed.', 'konfidens-appointment-booking'),
            'info'
        );
    }
    
    // Get set to edit
    $editing_set = null;
    $editing_set_id = '';
    if (isset($_GET['edit_set'])) {
        $editing_set_id = sanitize_text_field($_GET['edit_set']);
        if (isset($service_sets[$editing_set_id])) {
            $editing_set = $service_sets[$editing_set_id];
        }
    }
    
    settings_errors('kab_service_sets');
    ?>
    <div class="wrap kab-admin">
        <h1><?php _e('Service Sets', 'konfidens-appointment-booking'); ?></h1>
        <p><?php _e('Create service sets to use in your booking buttons. Instead of passing long UUID lists, simply reference a set name in your shortcode.', 'konfidens-appointment-booking'); ?></p>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
            <h2><?php echo $editing_set ? __('Edit Service Set', 'konfidens-appointment-booking') : __('Create New Service Set', 'konfidens-appointment-booking'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('kab_service_set_nonce', 'kab_service_set_nonce'); ?>
                <?php if ($editing_set_id): ?>
                    <input type="hidden" name="kab_set_id" value="<?php echo esc_attr($editing_set_id); ?>" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kab_set_name"><?php _e('Set Name', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="kab_set_name" id="kab_set_name" class="regular-text" 
                                value="<?php echo $editing_set ? esc_attr($editing_set['name']) : ''; ?>" 
                                placeholder="<?php _e('e.g., Set 1, Homepage Services, etc.', 'konfidens-appointment-booking'); ?>" required />
                            <p class="description"><?php _e('A friendly name for this service set (e.g., "Set 1", "Homepage Services").', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label><?php _e('Select Services', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <?php if (empty($all_services)): ?>
                                <p class="description" style="color: #d63638;">
                                    <?php _e('No services available. Please check your API connection in Settings.', 'konfidens-appointment-booking'); ?>
                                </p>
                            <?php else: ?>
                                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                    <?php 
                                    $selected_ids = $editing_set ? $editing_set['service_ids'] : array();
                                    foreach ($all_services as $service): 
                                        $service_id = isset($service['id']) ? $service['id'] : '';
                                        $service_name = isset($service['name']) ? $service['name'] : __('Unnamed Service', 'konfidens-appointment-booking');
                                        $is_selected = in_array($service_id, $selected_ids);
                                    ?>
                                        <label style="display: block; padding: 5px; margin: 2px 0; cursor: pointer; <?php echo $is_selected ? 'background: #e7f5e7;' : ''; ?>">
                                            <input type="checkbox" name="kab_service_ids[]" value="<?php echo esc_attr($service_id); ?>" 
                                                <?php checked($is_selected); ?> />
                                            <strong><?php echo esc_html($service_name); ?></strong>
                                            <span style="color: #666; font-size: 11px; margin-left: 10px;">(<?php echo esc_html($service_id); ?>)</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description"><?php _e('Select the services that should be available in this set.', 'konfidens-appointment-booking'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="kab_save_service_set" class="button button-primary" 
                        value="<?php echo $editing_set ? __('Update Set', 'konfidens-appointment-booking') : __('Create Set', 'konfidens-appointment-booking'); ?>" />
                    <?php if ($editing_set_id): ?>
                        <a href="<?php echo admin_url('admin.php?page=konfidens-service-sets'); ?>" class="button">
                            <?php _e('Cancel', 'konfidens-appointment-booking'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
            <h2><?php _e('Existing Service Sets', 'konfidens-appointment-booking'); ?></h2>
            <?php if (empty($service_sets)): ?>
                <p><?php _e('No service sets created yet.', 'konfidens-appointment-booking'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Set Name', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Services Count', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Shortcode', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Actions', 'konfidens-appointment-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($service_sets as $set_id => $set): 
                            $set_service_ids = isset($set['service_ids']) && is_array($set['service_ids']) ? $set['service_ids'] : array();
                            $enabled_count = 0;
                            $disabled_count = 0;
                            
                            // Count enabled vs disabled services in this set
                            foreach ($set_service_ids as $service_id) {
                                if (in_array($service_id, $enabled_service_ids)) {
                                    $enabled_count++;
                                } else {
                                    $disabled_count++;
                                }
                            }
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($set['name']); ?></strong></td>
                                <td>
                                    <?php echo $enabled_count; ?> <?php _e('enabled', 'konfidens-appointment-booking'); ?>
                                    <?php if ($disabled_count > 0): ?>
                                        <span style="color: #d63638; margin-left: 5px;">
                                            (<?php echo $disabled_count; ?> <?php _e('disabled', 'konfidens-appointment-booking'); ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="background: #f0f0f0; padding: 5px 10px; border-radius: 3px; display: block; margin-bottom: 5px;">
                                        set="<?php echo esc_attr($set_id); ?>"
                                    </code>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=konfidens-service-sets&edit_set=' . esc_attr($set_id)); ?>" class="button button-small">
                                        <?php _e('Edit', 'konfidens-appointment-booking'); ?>
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=konfidens-service-sets&delete_set=' . esc_attr($set_id)), 'kab_delete_set_' . esc_attr($set_id)); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php _e('Are you sure you want to delete this service set?', 'konfidens-appointment-booking'); ?>');">
                                        <?php _e('Delete', 'konfidens-appointment-booking'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
