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
    
    // Create parent category table
    $parent_category_table = $wpdb->prefix . 'kab_service_parent_category';
    $wpdb->query("CREATE TABLE IF NOT EXISTS $parent_category_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        parent_category_name varchar(255) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
    
    // Create service category table
    $category_table = $wpdb->prefix . 'kab_service_category';
    $wpdb->query("CREATE TABLE IF NOT EXISTS $category_table (
        id int(11) NOT NULL AUTO_INCREMENT,
        category_name varchar(255) NOT NULL,
        parent_category_id int(11) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;");
    
    // Add parent_category_id column if it doesn't exist (for existing installations)
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $category_table LIKE 'parent_category_id'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $category_table ADD COLUMN parent_category_id int(11) DEFAULT NULL AFTER category_name");
    }
    
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

// Get all locations from database
function kab_get_locations() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    return $wpdb->get_results("SELECT * FROM $table_name ORDER BY location_name ASC");
}

// Get single location by ID
function kab_get_location_by_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location';
    
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
}

// Add new location to database
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
    $location_table = $wpdb->prefix . 'kab_location';
    $location_service_table = $wpdb->prefix . 'kab_location_service';
    
    // First, remove the deleted location ID from all service assignments
    // Get all service records that have this location ID (services now only have one location)
    $all_services = $wpdb->get_results($wpdb->prepare(
        "SELECT service_id, location_ids FROM $location_service_table WHERE location_ids = %s",
        $id
    ));
    
    foreach ($all_services as $service) {
        // Clear the location_id since it matches the deleted location
        $wpdb->query($wpdb->prepare(
            "UPDATE $location_service_table SET location_ids = '' WHERE service_id = %s",
            $service->service_id
        ));
    }
    
    // Then delete the location
    return $wpdb->delete($location_table, array('id' => $id));
}

/**
 * Get service locations
 */
function kab_get_service_locations($service_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_service';
    
    $service_location = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE service_id = %s", $service_id));
    
    if ($service_location && !empty($service_location->location_ids)) {
        // Return single location_id (services now only have one location)
        return $service_location->location_ids;
    }
    
    return '';
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
 * Get locations by category (locations that have services in the given category)
 */
function kab_get_locations_by_category($category_id) {
    global $wpdb;
    $location_service_table = $wpdb->prefix . 'kab_location_service';
    $location_table = $wpdb->prefix . 'kab_location';
    
    // Get all services in this category
    $services = $wpdb->get_results($wpdb->prepare(
        "SELECT service_id, location_ids FROM $location_service_table WHERE category_id = %d",
        intval($category_id)
    ));
    
    if (empty($services)) {
        return array();
    }
    
    // Collect all unique location IDs (services now only have one location)
    $location_ids = array();
    foreach ($services as $service) {
        if (!empty($service->location_ids)) {
            $loc_id = trim($service->location_ids);
            if (!empty($loc_id) && !in_array($loc_id, $location_ids)) {
                $location_ids[] = $loc_id;
            }
        }
    }
    
    if (empty($location_ids)) {
        return array();
    }
    
    // Get location details
    $location_ids_str = implode(',', array_map('intval', $location_ids));
    $locations = $wpdb->get_results(
        "SELECT * FROM $location_table WHERE id IN ($location_ids_str) ORDER BY location_name ASC"
    );
    
    return $locations;
}

/**
 * Get service by category and location
 */
function kab_get_service_by_category_location($category_id, $location_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_service';
    
    // Get service in this category with the requested location (services now only have one location)
    $service = $wpdb->get_row($wpdb->prepare(
        "SELECT service_id FROM $table_name WHERE category_id = %d AND location_ids = %s",
        intval($category_id),
        $location_id
    ));
    
    if ($service && !empty($service->service_id)) {
        return $service->service_id;
    }
    
    return null;
}

// Get all service categories
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
    
    // Remove parent category assignment from service categories
    $wpdb->query($wpdb->prepare(
        "UPDATE $category_table SET parent_category_id = NULL WHERE parent_category_id = %d",
        $id
    ));
    
    // Then delete the category
    return $wpdb->delete($category_table, array('id' => $id));
}

/**
 * Get all service parent categories
 */
function kab_get_service_parent_categories() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_service_parent_category';
    
    return $wpdb->get_results("SELECT * FROM $table_name ORDER BY parent_category_name ASC");
}

/**
 * Get service parent category by ID
 */
function kab_get_service_parent_category_by_id($id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_service_parent_category';
    
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
}

/**
 * Add a new service parent category
 */
function kab_add_service_parent_category($parent_category_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_service_parent_category';
    
    $result = $wpdb->insert(
        $table_name,
        array('parent_category_name' => sanitize_text_field($parent_category_name))
    );
    
    return $result ? $wpdb->insert_id : false;
}

/**
 * Update a service parent category
 */
function kab_update_service_parent_category($id, $parent_category_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_service_parent_category';
    
    return $wpdb->update(
        $table_name,
        array('parent_category_name' => sanitize_text_field($parent_category_name)),
        array('id' => $id)
    );
}

/**
 * Delete a service parent category
 */
function kab_delete_service_parent_category($id) {
    global $wpdb;
    $parent_category_table = $wpdb->prefix . 'kab_service_parent_category';
    $category_table = $wpdb->prefix . 'kab_service_category';
    
    // First, remove parent category assignment from all service categories
    $wpdb->query($wpdb->prepare(
        "UPDATE $category_table SET parent_category_id = NULL WHERE parent_category_id = %d",
        $id
    ));
    
    // Then delete the parent category
    return $wpdb->delete($parent_category_table, array('id' => $id));
}

/**
 * Update service category parent category assignment
 */
function kab_update_service_category_parent($category_id, $parent_category_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_service_category';
    
    // Convert to integer if set, otherwise null
    $parent_id_value = ($parent_category_id !== null && $parent_category_id !== '' && $parent_category_id !== '0') ? intval($parent_category_id) : null;
    
    if ($parent_id_value === null) {
        // Use raw SQL to set NULL
        return $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET parent_category_id = NULL WHERE id = %d",
            $category_id
        )) !== false;
    } else {
        return $wpdb->update(
            $table_name,
            array('parent_category_id' => $parent_id_value),
            array('id' => $category_id)
        ) !== false;
    }
}

/**
 * Add or update service location
 */
function kab_add_update_service_location($service_id, $location_ids, $category_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_location_service';
    
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE service_id = %s", $service_id));
    
    // Handle category_id - convert to integer if set, otherwise null
    $category_id_value = ($category_id !== null && $category_id !== '' && $category_id !== '0') ? intval($category_id) : null;
    
    if ($existing) {
        // Update existing record
        if ($category_id_value === null) {
            // Use raw SQL to set NULL
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET location_ids = %s, category_id = NULL WHERE service_id = %s",
                sanitize_text_field($location_ids),
                $service_id
            ));
            return $result !== false;
        } else {
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE $table_name SET location_ids = %s, category_id = %d WHERE service_id = %s",
                sanitize_text_field($location_ids),
                $category_id_value,
                $service_id
            ));
            return $result !== false;
        }
    } else {
        // Insert new record
        if ($category_id_value === null) {
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO $table_name (service_id, location_ids, category_id) VALUES (%s, %s, NULL)",
                sanitize_text_field($service_id),
                sanitize_text_field($location_ids)
            ));
            return $result ? $wpdb->insert_id : false;
        } else {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'service_id' => sanitize_text_field($service_id),
                    'location_ids' => sanitize_text_field($location_ids),
                    'category_id' => $category_id_value
                ),
                array('%s', '%s', '%d')
            );
            return $result ? $wpdb->insert_id : false;
        }
    }
}

// Get tags stored for a specialist
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

// Save booking to local database after successful API booking
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
        $existing_location = kab_get_service_locations($service['id']);
        
        // Only update if service doesn't exist yet
        if (empty($existing_location)) {
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

// Get all bookings from database with pagination
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

// Get plugin settings from database
function kab_get_settings() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_setting';
    
    $settings = $wpdb->get_row("SELECT * FROM $table_name LIMIT 1");
    
    if (!$settings) {
        return (object) array(
            'base_url' => '',
            'api_key' => '',
            'clinic_id' => ''
        );
    }
    
    return $settings;
}

// Save plugin settings to database
function kab_save_settings($settings) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kab_setting';
    
    $data = array(
        'base_url' => esc_url_raw($settings['base_url']),
        'api_key' => sanitize_text_field($settings['api_key']),
        'clinic_id' => sanitize_text_field($settings['clinic_id'])
    );
    
    $existing = $wpdb->get_row("SELECT * FROM $table_name LIMIT 1");
    
    if ($existing) {
        return $wpdb->update($table_name, $data, array('id' => $existing->id));
    } else {
        $result = $wpdb->insert($table_name, $data);
        return $result ? $wpdb->insert_id : false;
    }
}