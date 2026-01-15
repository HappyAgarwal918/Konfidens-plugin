<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function kab_uninstall_plugin() {
    delete_transient('kab_services_cache');
    delete_transient('kab_specialists_cache');
    delete_transient('kab_locations_cache');
    wp_clear_scheduled_hook('kab_daily_sync');
}

kab_uninstall_plugin();
