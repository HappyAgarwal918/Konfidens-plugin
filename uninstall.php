<?php
/**
 * Konfidens Appointment Booking Uninstaller
 *
 * This file is used to uninstall the plugin when it is deleted from the WordPress admin.
 * It runs automatically when the plugin is deleted.
 *
 * @since      1.0.0
 * @package    Konfidens_Appointment_Booking
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Uninstall the plugin
 * 
 * This function only cleans up transients and scheduled events.
 * Database tables and data are preserved for future use.
 */
function kab_uninstall_plugin() {
    // Delete transients only
    delete_transient('kab_services_cache');
    delete_transient('kab_specialists_cache');
    delete_transient('kab_locations_cache');
    
    // Clear any scheduled cron events
    wp_clear_scheduled_hook('kab_daily_sync');
    
    // Note: We intentionally preserve all database tables and options
    // to ensure data is not lost when the plugin is uninstalled
}

// Run the uninstall function
kab_uninstall_plugin();
