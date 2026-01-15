<?php
if (!defined('ABSPATH')) {
    exit;
}

// Plugin activation: Create tables, set defaults, flush rewrite rules
function kab_activate() {
    kab_create_tables();
    kab_set_default_options();
    flush_rewrite_rules();
}

// Set default WordPress options if they don't exist
function kab_set_default_options() {
    $default_options = array(
        'kab_base_url' => '',
        'kab_api_key' => '',
        'kab_clinic_id' => '',
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

// Add default location categories if database is empty
function kab_add_default_categories() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    if ($count == 0) {
        $default_categories = array(
            'Online Video Samtale',
            'Hjemmebesøk',
            'På vårt kontor'
        );
        
        foreach ($default_categories as $category) {
            $wpdb->insert($table_name, array('location_name' => $category));
        }
        return true;
    }
    return false;
}