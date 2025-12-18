<?php
/**
 * Activator functionality for Konfidens Appointment Booking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activation
 */
function kab_activate() {
    // Create database tables
    kab_create_tables();
    
    // Set default options
    kab_set_default_options();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Check and update database tables if needed
 */
function kab_check_db_updates() {
    global $wpdb;
    
    // Check if price column exists in location_service table
    $table_name = $wpdb->prefix . 'kab_location_service';
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'price'");
    
    if (empty($column_exists)) {
        // Add price column
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN price varchar(50) DEFAULT ''");
    }
}
add_action('plugins_loaded', 'kab_check_db_updates');

/**
 * Set default options
 */
function kab_set_default_options() {
    $default_options = array(
        'kab_base_url' => '',
        'kab_api_key' => '',
        'kab_clinic_id' => '',
        'kab_primary_color' => '#007bff',
        'kab_booking_msg' => 'Thank you for booking with us. Your appointment has been confirmed.',
        'kab_email_recipient' => get_option('admin_email'),
        'kab_email_subject' => 'New Appointment Booking',
        'kab_enable_recaptcha' => 0,
        'kab_recaptcha_site_key' => '',
        'kab_recaptcha_secret_key' => ''
    );
    
    foreach ($default_options as $option_name => $option_value) {
        if (get_option($option_name) === false) {
            add_option($option_name, $option_value);
        }
    }
    
    // Add default categories/locations if none exist
    kab_add_default_categories();
}

/**
 * Add default categories/locations
 */
function kab_add_default_categories() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    // Check if any categories exist
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    if ($count == 0) {
        // Add default categories
        $default_categories = array(
            'Online Video Samtale',
            'Hjemmebesøk',
            'På vårt kontor'
        );
        
        foreach ($default_categories as $category) {
            $wpdb->insert(
                $table_name,
                array('location_name' => $category)
            );
        }
        
        return true;
    }
    
    return false;
}