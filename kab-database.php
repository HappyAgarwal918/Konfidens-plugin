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
        category_id int(11) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
    
    // Add category_id column if it doesn't exist (for existing installations)
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $location_table LIKE 'category_id'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $location_table ADD COLUMN category_id int(11) DEFAULT NULL AFTER location_name");
    }
    
    // Create location category table
    $location_category_table = $wpdb->prefix . 'kab_location_category';
    $wpdb->query("CREATE TABLE IF NOT EXISTS $location_category_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        category_name varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
    
    // Create service category table
    $category_table = $wpdb->prefix . 'kab_service_category';
    $wpdb->query("CREATE TABLE IF NOT EXISTS $category_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        category_name varchar(255) NOT NULL,
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
        category_id int(11) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
    
    // Add category_id column if it doesn't exist (for existing installations)
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $location_service_table LIKE 'category_id'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $location_service_table ADD COLUMN category_id int(11) DEFAULT NULL AFTER location_ids");
    }
    
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
        tags text DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
    
    // Add tags column if it doesn't exist (for existing installations)
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $location_specialist_table LIKE 'tags'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $location_specialist_table ADD COLUMN tags text DEFAULT '' AFTER specialist_id");
    }
    
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
function kab_add_location($location_name, $category_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    $data = array('location_name' => sanitize_text_field($location_name));
    if ($category_id !== null && $category_id !== '') {
        $data['category_id'] = intval($category_id);
    }
    
    $result = $wpdb->insert($table_name, $data);
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Update a location
 */
function kab_update_location($id, $location_name, $category_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    $data = array('location_name' => sanitize_text_field($location_name));
    if ($category_id !== null && $category_id !== '') {
        $data['category_id'] = intval($category_id);
    } else {
        $data['category_id'] = null;
    }
    
    return $wpdb->update(
        $table_name,
        $data,
        array('id' => $id)
    );
}

/**
 * Get location category ID
 */
function kab_get_location_category_id($location_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    $location = $wpdb->get_row($wpdb->prepare("SELECT category_id FROM $table_name WHERE id = %d", $location_id));
    
    if ($location && $location->category_id !== null && $location->category_id !== '') {
        return intval($location->category_id);
    }
    
    return null;
}

/**
 * Get all location categories
 */
function kab_get_location_categories() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_category';
    
    return $wpdb->get_results("SELECT * FROM $table_name ORDER BY category_name ASC");
}

/**
 * Get location category by ID
 */
function kab_get_location_category_by_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_category';
    
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
}

/**
 * Add a new location category
 */
function kab_add_location_category($category_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_category';
    
    $result = $wpdb->insert(
        $table_name,
        array('category_name' => sanitize_text_field($category_name))
    );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Update a location category
 */
function kab_update_location_category($id, $category_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_category';
    
    return $wpdb->update(
        $table_name,
        array('category_name' => sanitize_text_field($category_name)),
        array('id' => $id)
    );
}

/**
 * Delete a location category
 */
function kab_delete_location_category($id) {
    global $wpdb;
    $location_category_table = $wpdb->prefix . 'kab_location_category';
    $location_table = $wpdb->prefix . 'kab_location';
    
    // First, remove category from all locations
    $wpdb->update(
        $location_table,
        array('category_id' => null),
        array('category_id' => $id)
    );
    
    // Then delete the category
    return $wpdb->delete($location_category_table, array('id' => $id));
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
 * Get service category ID
 */
function kab_get_service_category_id($service_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_service';
    
    $service_location = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE service_id = %s", $service_id));
    
    if ($service_location && $service_location->category_id !== null && $service_location->category_id !== '') {
        return intval($service_location->category_id);
    }
    
    return null;
}

/**
 * Get all service categories
 */
function kab_get_service_categories() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_service_category';
    
    return $wpdb->get_results("SELECT * FROM $table_name ORDER BY category_name ASC");
}

/**
 * Get service category by ID
 */
function kab_get_service_category_by_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_service_category';
    
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
}

/**
 * Add a new service category
 */
function kab_add_service_category($category_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_service_category';
    
    $result = $wpdb->insert(
        $table_name,
        array('category_name' => sanitize_text_field($category_name))
    );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Update a service category
 */
function kab_update_service_category($id, $category_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_service_category';
    
    return $wpdb->update(
        $table_name,
        array('category_name' => sanitize_text_field($category_name)),
        array('id' => $id)
    );
}

/**
 * Delete a service category
 */
function kab_delete_service_category($id) {
    global $wpdb;
    $category_table = $wpdb->prefix . 'kab_service_category';
    $location_service_table = $wpdb->prefix . 'kab_location_service';
    
    // First, remove category assignment from all services
    $wpdb->query($wpdb->prepare(
        "UPDATE $location_service_table SET category_id = NULL WHERE category_id = %d",
        $id
    ));
    
    // Then delete the category
    return $wpdb->delete($category_table, array('id' => $id));
}

/**
 * Get service price from API only
 * Price is always fetched from Konfidens API, not stored in database
 */
function kab_get_service_price($service_id) {
    // Get price from API
    $service = kab_get_service_by_id($service_id);
    
    // Helper function to extract price value (handles arrays and objects)
    // Also converts from cents to main currency unit if needed
    $extract_price = function($value) {
        $raw_price = '';
        
        if (is_array($value)) {
            // If it's an array, try to get the first numeric value or join them
            foreach ($value as $item) {
                if (is_numeric($item)) {
                    $raw_price = (string) $item;
                    break;
                }
            }
            // If no numeric value found, return first item as string
            if (empty($raw_price)) {
                $raw_price = is_array($value) && !empty($value) ? (string) reset($value) : '';
            }
        } elseif (is_object($value)) {
            // If it's an object, try to get a value property
            if (isset($value->value)) {
                $raw_price = (string) $value->value;
            } elseif (isset($value->amount)) {
                $raw_price = (string) $value->amount;
            } elseif (isset($value->price)) {
                $raw_price = (string) $value->price;
            }
        } else {
            $raw_price = (string) $value;
        }
        
        // Convert from cents to main currency if the price seems to be in cents
        // If price is a number and >= 1000, assume it might be in cents
        if (!empty($raw_price) && is_numeric($raw_price)) {
            $numeric_price = floatval($raw_price);
            // If price is >= 1000 and is a whole number, likely in cents
            // Convert by dividing by 100 (e.g., 119000 cents = 1190.00)
            if ($numeric_price >= 1000 && $numeric_price == floor($numeric_price)) {
                $numeric_price = $numeric_price / 100;
                // Format to 2 decimal places, remove trailing zeros
                $numeric_price = rtrim(rtrim(number_format($numeric_price, 2, '.', ''), '0'), '.');
                return (string) $numeric_price;
            }
        }
        
        return $raw_price;
    };
    
    // Check if API response has price field
    if (!empty($service)) {
        // Check common price field names (in order of likelihood)
        $price_fields = array('price', 'Price', 'amount', 'Amount');
        
        foreach ($price_fields as $field) {
            if (isset($service[$field])) {
                $price = $extract_price($service[$field]);
                if (!empty($price)) {
                    return $price;
                }
            }
        }
    }
    
    // No price found in API response
    return '0';
}

/**
 * Add or update service location
 */
function kab_add_update_service_location($service_id, $location_ids) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_service';
    
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE service_id = %s", $service_id));
    
    if ($existing) {
        // Update existing record
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET location_ids = %s WHERE service_id = %s",
            sanitize_text_field($location_ids),
            $service_id
        ));
        return $result !== false;
    } else {
        // Insert new record
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO $table_name (service_id, location_ids) VALUES (%s, %s)",
            sanitize_text_field($service_id),
            sanitize_text_field($location_ids)
        ));
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
 * Get specialist tags
 */
function kab_get_specialist_tags($specialist_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_specialist';
    
    $specialist_data = $wpdb->get_row($wpdb->prepare("SELECT tags FROM $table_name WHERE specialist_id = %s", $specialist_id));
    
    if ($specialist_data && !empty($specialist_data->tags)) {
        // Split by comma and trim each tag
        $tags = array_map('trim', explode(',', $specialist_data->tags));
        // Remove empty tags
        return array_filter($tags);
    }
    
    return array();
}

/**
 * Update specialist tags
 */
function kab_update_specialist_tags($specialist_id, $tags) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_specialist';
    
    // Sanitize tags - remove extra spaces and clean
    $tags_string = sanitize_text_field($tags);
    
    // Check if tags column exists, if not add it
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'tags'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN tags text DEFAULT '' AFTER specialist_id");
    }
    
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE specialist_id = %s", $specialist_id));
    
    if ($existing) {
        $result = $wpdb->update(
            $table_name,
            array('tags' => $tags_string),
            array('specialist_id' => $specialist_id),
            array('%s'),
            array('%s')
        );
        
        // wpdb->update returns number of rows affected (0 or more), false on error
        return $result !== false;
    } else {
        // If no record exists, create one with empty location_ids
        $result = $wpdb->insert(
            $table_name,
            array(
                'specialist_id' => sanitize_text_field($specialist_id),
                'location_ids' => '',
                'tags' => $tags_string
            ),
            array('%s', '%s', '%s')
        );
        
        return $result !== false;
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
    
    // Import each service
    foreach ($services as $service) {
        if (empty($service['id']) || empty($service['name'])) {
            continue;
        }
        
        // Check if service already exists in database
        $existing_locations = kab_get_service_locations($service['id']);
        
        // Only update if service doesn't exist yet
        if (empty($existing_locations)) {
            // Service doesn't exist - create entry with blank location
            $result = kab_add_update_service_location($service['id'], '');
            
            if ($result !== false) {
                $imported++;
            }
        } else {
            // Service already exists - preserve existing locations
            // Just count it as imported/updated
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