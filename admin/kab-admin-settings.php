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
    
    // Settings submenu
    add_submenu_page(
        'konfidens',
        __('Settings', 'konfidens-appointment-booking'),
        __('Settings', 'konfidens-appointment-booking'),
        'manage_options',
        'konfidens-settings',
        'kab_display_settings_page'
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
