<?php
/**
 * Database operations for Konfidens Appointment Booking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create database tables
 */
function kab_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create location table
    $location_table = $wpdb->prefix . 'kab_location';
    $wpdb->query("CREATE TABLE IF NOT EXISTS $location_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        location_name varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
    
    // Create location service table
    $location_service_table = $wpdb->prefix . 'kab_location_service';
    $wpdb->query("CREATE TABLE IF NOT EXISTS $location_service_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        service_id varchar(50) NOT NULL,
        location_ids text NOT NULL,
        priority int(11) DEFAULT 0,
        price varchar(50) DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
    
    // Create setting table
    $setting_table = $wpdb->prefix . 'kab_setting';
    $wpdb->query("CREATE TABLE IF NOT EXISTS $setting_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        base_url varchar(255) NOT NULL,
        api_key varchar(255) NOT NULL,
        clinic_id varchar(50) NOT NULL,
        primary_color varchar(20) DEFAULT '#007bff',
        booking_msg text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
    
    // Create location specialist table
    $location_specialist_table = $wpdb->prefix . 'kab_location_specialist';
    $wpdb->query("CREATE TABLE IF NOT EXISTS $location_specialist_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        location_ids text NOT NULL,
        specialist_id varchar(50) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
    
    // Create booking form data table
    $booking_form_data_table = $wpdb->prefix . 'kab_booking_form_data';
    $wpdb->query("CREATE TABLE IF NOT EXISTS $booking_form_data_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        service_ids varchar(50) NOT NULL,
        service_name varchar(255) NOT NULL,
        specialist_ids varchar(50) NOT NULL,
        specialist_name varchar(255) NOT NULL,
        timeslot_date date NOT NULL,
        timeslot_time time NOT NULL,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(50) NOT NULL,
        booking_id varchar(100) NOT NULL,
        booked_from tinyint(1) DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
}

/**
 * Get locations from the database
 */
function kab_get_locations() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    return $wpdb->get_results("SELECT * FROM $table_name ORDER BY location_name ASC");
}

/**
 * Get location by ID
 */
function kab_get_location_by_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
}

/**
 * Add a new location
 */
function kab_add_location($location_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    $result = $wpdb->insert(
        $table_name,
        array('location_name' => sanitize_text_field($location_name))
    );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Update a location
 */
function kab_update_location($id, $location_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    return $wpdb->update(
        $table_name,
        array('location_name' => sanitize_text_field($location_name)),
        array('id' => $id)
    );
}

/**
 * Delete a location
 */
function kab_delete_location($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    return $wpdb->delete($table_name, array('id' => $id));
}

/**
 * Get service locations
 */
function kab_get_service_locations($service_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_service';
    
    $service_location = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE service_id = %s", $service_id));
    
    if ($service_location) {
        return explode(',', $service_location->location_ids);
    }
    
    return array();
}

/**
 * Get service priority
 */
function kab_get_service_priority($service_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_service';
    
    $service_location = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE service_id = %s", $service_id));
    
    if ($service_location && $service_location->priority !== null) {
        // Return the actual priority value, even if it's 0
        return $service_location->priority;
    }
    
    // No priority set, return null
    return null;
}

/**
 * Get service price
 */
function kab_get_service_price($service_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_service';
    
    $service_location = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE service_id = %s", $service_id));
    
    if ($service_location && isset($service_location->price)) {
        return $service_location->price;
    }
    
    return '';
}

/**
 * Add or update service location
 */
function kab_add_update_service_location($service_id, $location_ids, $priority) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_service';
    
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE service_id = %s", $service_id));
    
    if ($existing) {
        return $wpdb->update(
            $table_name,
            array(
                'location_ids' => sanitize_text_field($location_ids),
                'priority' => intval($priority)
            ),
            array('service_id' => $service_id)
        );
    } else {
        $result = $wpdb->insert(
            $table_name,
            array(
                'service_id' => sanitize_text_field($service_id),
                'location_ids' => sanitize_text_field($location_ids),
                'priority' => intval($priority)
            )
        );
        
        return $result ? $wpdb->insert_id : false;
    }
}

/**
 * Update service price
 */
function kab_update_service_price($service_id, $price) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_service';
    
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE service_id = %s", $service_id));
    
    if ($existing) {
        return $wpdb->update(
            $table_name,
            array('price' => sanitize_text_field($price)),
            array('service_id' => $service_id)
        );
    } else {
        $result = $wpdb->insert(
            $table_name,
            array(
                'service_id' => sanitize_text_field($service_id),
                'location_ids' => '',
                'priority' => 0,
                'price' => sanitize_text_field($price)
            )
        );
        
        return $result ? $wpdb->insert_id : false;
    }
}

/**
 * Get specialist locations
 */
function kab_get_specialist_locations($specialist_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_specialist';
    
    $specialist_location = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE specialist_id = %s", $specialist_id));
    
    if ($specialist_location) {
        return explode(',', $specialist_location->location_ids);
    }
    
    return array();
}

/**
 * Add or update specialist location
 */
function kab_add_update_specialist_location($specialist_id, $location_ids) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_specialist';
    
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE specialist_id = %s", $specialist_id));
    
    if ($existing) {
        return $wpdb->update(
            $table_name,
            array('location_ids' => sanitize_text_field($location_ids)),
            array('specialist_id' => $specialist_id)
        );
    } else {
        $result = $wpdb->insert(
            $table_name,
            array(
                'specialist_id' => sanitize_text_field($specialist_id),
                'location_ids' => sanitize_text_field($location_ids)
            )
        );
        
        return $result ? $wpdb->insert_id : false;
    }
}

/**
 * Save booking data
 */
function kab_save_booking($booking_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_booking_form_data';
    
    $data = array(
        'service_ids' => sanitize_text_field($booking_data['service_ids']),
        'service_name' => sanitize_text_field($booking_data['service_name']),
        'specialist_ids' => sanitize_text_field($booking_data['specialist_ids']),
        'specialist_name' => sanitize_text_field($booking_data['specialist_name']),
        'timeslot_date' => sanitize_text_field($booking_data['timeslot_date']),
        'timeslot_time' => sanitize_text_field($booking_data['timeslot_time']),
        'first_name' => sanitize_text_field($booking_data['first_name']),
        'last_name' => sanitize_text_field($booking_data['last_name']),
        'email' => sanitize_email($booking_data['email']),
        'phone' => sanitize_text_field($booking_data['phone']),
        'booking_id' => sanitize_text_field($booking_data['booking_id']),
        'booked_from' => intval($booking_data['booked_from'])
    );
    
    $result = $wpdb->insert($table_name, $data);
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Get booking by ID
 */
function kab_get_booking_by_id($booking_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_booking_form_data';
    
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE booking_id = %s", $booking_id));
}

/**
 * Import services from API to database
 */
function kab_import_services($services) {
    if (empty($services)) {
        return 0;
    }
    
    $imported = 0;
    
    // Create default location if none exists
    $locations = kab_get_locations();
    $default_location_id = 0;
    
    if (empty($locations)) {
        $default_location_id = kab_add_location([
            'location_name' => 'Default Location',
            'location_address' => '',
            'location_phone' => '',
            'location_email' => ''
        ]);
    } else {
        $default_location_id = $locations[0]->id;
    }
    
    // Import each service
    foreach ($services as $service) {
        if (empty($service['id']) || empty($service['name'])) {
            continue;
        }
        
        // Set default priority
        $priority = 10;
        
        // Add or update service-location mapping
        $result = kab_add_update_service_location($service['id'], $default_location_id, $priority);
        
        if ($result !== false) {
            $imported++;
        }
    }
    
    return $imported;
}

/**
 * Get all bookings
 */
function kab_get_all_bookings($limit = 20, $offset = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_booking_form_data';
    
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        )
    );
}

/**
 * Get settings
 */
function kab_get_settings() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_setting';
    
    $settings = $wpdb->get_row("SELECT * FROM $table_name LIMIT 1");
    
    if (!$settings) {
        return (object) array(
            'base_url' => '',
            'api_key' => '',
            'clinic_id' => '',
            'primary_color' => '#007bff',
            'booking_msg' => 'Thank you for booking with us. Your appointment has been confirmed.'
        );
    }
    
    return $settings;
}

/**
 * Save settings
 */
function kab_save_settings($settings) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_setting';
    
    $data = array(
        'base_url' => esc_url_raw($settings['base_url']),
        'api_key' => sanitize_text_field($settings['api_key']),
        'clinic_id' => sanitize_text_field($settings['clinic_id']),
        'primary_color' => sanitize_hex_color($settings['primary_color']),
        'booking_msg' => wp_kses_post($settings['booking_msg'])
    );
    
    $existing = $wpdb->get_row("SELECT * FROM $table_name LIMIT 1");
    
    if ($existing) {
        return $wpdb->update($table_name, $data, array('id' => $existing->id));
    } else {
        $result = $wpdb->insert($table_name, $data);
        return $result ? $wpdb->insert_id : false;
    }
}