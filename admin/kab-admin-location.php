<?php
if (!defined('ABSPATH')) {
    exit;
}

// Display locations page: Manage location categories and locations
function kab_display_locations_page() {
    if (isset($_POST['kab_save_location_category']) && check_admin_referer('kab_location_category_nonce', 'kab_location_category_nonce')) {
        $category_name = sanitize_text_field($_POST['kab_category_name']);
        $category_id = isset($_POST['kab_category_id']) ? intval($_POST['kab_category_id']) : 0;
        
        if (!empty($category_name)) {
            if ($category_id > 0) {
                $result = kab_update_location_category($category_id, $category_name);
                if ($result !== false) {
                    add_settings_error('kab_locations', 'kab_category_updated', __('Category updated successfully.', 'konfidens-appointment-booking'), 'success');
                }
            } else {
                $result = kab_add_location_category($category_name);
                if ($result !== false) {
                    add_settings_error('kab_locations', 'kab_category_added', __('Category added successfully.', 'konfidens-appointment-booking'), 'success');
                }
            }
        }
    }
    
    // Handle category delete
    if (isset($_GET['delete_category']) && check_admin_referer('kab_delete_category_' . $_GET['delete_category'])) {
        $category_id = intval($_GET['delete_category']);
        $result = kab_delete_location_category($category_id);
        if ($result !== false) {
            add_settings_error('kab_locations', 'kab_category_deleted', __('Category deleted successfully.', 'konfidens-appointment-booking'), 'success');
        }
    }
    
    // Handle form submissions
    if (isset($_POST['kab_save_location']) && check_admin_referer('kab_location_nonce', 'kab_location_nonce')) {
        $location_name = sanitize_text_field($_POST['kab_location_name']);
        $location_id = isset($_POST['kab_location_id']) ? intval($_POST['kab_location_id']) : 0;
        $category_id = isset($_POST['kab_location_category']) && $_POST['kab_location_category'] !== '' ? intval($_POST['kab_location_category']) : null;
        
        if (!empty($location_name)) {
            if ($location_id > 0) {
                // Update existing location
                $result = kab_update_location($location_id, $location_name, $category_id);
                if ($result !== false) {
                    add_settings_error(
                        'kab_locations',
                        'kab_location_updated',
                        __('Location updated successfully.', 'konfidens-appointment-booking'),
                        'success'
                    );
                    // Redirect to avoid resubmission and reset form to "Add New" mode
                    wp_redirect(admin_url('admin.php?page=konfidens-locations&updated=1'));
                    exit;
                } else {
                    add_settings_error(
                        'kab_locations',
                        'kab_location_error',
                        __('Error updating location.', 'konfidens-appointment-booking'),
                        'error'
                    );
                }
            } else {
                // Add new location
                $result = kab_add_location($location_name, $category_id);
                if ($result !== false) {
                    add_settings_error(
                        'kab_locations',
                        'kab_location_added',
                        __('Location added successfully.', 'konfidens-appointment-booking'),
                        'success'
                    );
                    // Redirect to avoid resubmission
                    wp_redirect(admin_url('admin.php?page=konfidens-locations&added=1'));
                    exit;
                } else {
                    add_settings_error(
                        'kab_locations',
                        'kab_location_error',
                        __('Error adding location.', 'konfidens-appointment-booking'),
                        'error'
                    );
                }
            }
        }
    }
    
    // Handle delete
    if (isset($_GET['delete_location']) && check_admin_referer('kab_delete_location_' . $_GET['delete_location'])) {
        $location_id = intval($_GET['delete_location']);
        $result = kab_delete_location($location_id);
        
        if ($result !== false) {
            add_settings_error(
                'kab_locations',
                'kab_location_deleted',
                __('Location deleted successfully.', 'konfidens-appointment-booking'),
                'success'
            );
            // Redirect to avoid resubmission
            wp_redirect(admin_url('admin.php?page=konfidens-locations&deleted=1'));
            exit;
        } else {
            add_settings_error(
                'kab_locations',
                'kab_location_error',
                __('Error deleting location.', 'konfidens-appointment-booking'),
                'error'
            );
        }
    }
    
    // Get all locations
    $locations = kab_get_locations();
    
    // Get all location categories
    $location_categories = kab_get_location_categories();
    
    // Get location to edit
    $editing_location = null;
    $editing_location_id = 0;
    $editing_category_id = null;
    if (isset($_GET['edit_location'])) {
        $editing_location_id = intval($_GET['edit_location']);
        $editing_location = kab_get_location_by_id($editing_location_id);
        if ($editing_location) {
            $editing_category_id = kab_get_location_category_id($editing_location_id);
        }
    }
    
    // Get category to edit
    $editing_category = null;
    $editing_category_id_for_category = 0;
    if (isset($_GET['edit_category'])) {
        $editing_category_id_for_category = intval($_GET['edit_category']);
        $editing_category = kab_get_location_category_by_id($editing_category_id_for_category);
    }
    
    // Show success messages after redirect
    if (isset($_GET['updated']) && $_GET['updated'] == '1') {
        add_settings_error(
            'kab_locations',
            'kab_location_updated',
            __('Location updated successfully.', 'konfidens-appointment-booking'),
            'success'
        );
    }
    if (isset($_GET['added']) && $_GET['added'] == '1') {
        add_settings_error(
            'kab_locations',
            'kab_location_added',
            __('Location added successfully.', 'konfidens-appointment-booking'),
            'success'
        );
    }
    if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
        add_settings_error(
            'kab_locations',
            'kab_location_deleted',
            __('Location deleted successfully.', 'konfidens-appointment-booking'),
            'success'
        );
    }
    
    settings_errors('kab_locations');
    ?>
    <div class="wrap kab-admin">
        <h1><?php _e('Locations', 'konfidens-appointment-booking'); ?></h1>
        <p><?php _e('Manage locations for your booking system. Locations are used to filter services and specialists.', 'konfidens-appointment-booking'); ?></p>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
            <h2><?php echo $editing_location ? __('Edit Location', 'konfidens-appointment-booking') : __('Add New Location', 'konfidens-appointment-booking'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('kab_location_nonce', 'kab_location_nonce'); ?>
                <?php if ($editing_location_id > 0): ?>
                    <input type="hidden" name="kab_location_id" value="<?php echo esc_attr($editing_location_id); ?>" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kab_location_name"><?php _e('Location Name', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="kab_location_name" id="kab_location_name" class="regular-text" 
                                value="<?php echo $editing_location ? esc_attr($editing_location->location_name) : ''; ?>" 
                                placeholder="<?php _e('e.g., Main Office, Branch Office, etc.', 'konfidens-appointment-booking'); ?>" required />
                            <p class="description"><?php _e('Enter a name for this location.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="kab_location_category"><?php _e('Category', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <select name="kab_location_category" id="kab_location_category" class="regular-text">
                                <option value=""><?php _e('-- No Category --', 'konfidens-appointment-booking'); ?></option>
                                <?php foreach ($location_categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->id); ?>" <?php selected($editing_category_id, $category->id); ?>>
                                        <?php echo esc_html($category->category_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Assign this location to a category. Locations with categories will be shown in dropdowns in the booking form.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="kab_save_location" class="button button-primary" 
                        value="<?php echo $editing_location ? __('Update Location', 'konfidens-appointment-booking') : __('Add Location', 'konfidens-appointment-booking'); ?>" />
                    <?php if ($editing_location_id > 0): ?>
                        <a href="<?php echo admin_url('admin.php?page=konfidens-locations'); ?>" class="button">
                            <?php _e('Cancel', 'konfidens-appointment-booking'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
            <h2><?php _e('Location Categories', 'konfidens-appointment-booking'); ?></h2>
            <p><?php _e('Manage categories for locations. Locations assigned to categories will be displayed in dropdown groups in the booking form.', 'konfidens-appointment-booking'); ?></p>
            
            <div style="margin-top: 20px;">
                <h3><?php echo $editing_category ? __('Edit Category', 'konfidens-appointment-booking') : __('Add New Category', 'konfidens-appointment-booking'); ?></h3>
                <form method="post" action="" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('kab_location_category_nonce', 'kab_location_category_nonce'); ?>
                    <?php if ($editing_category_id_for_category > 0): ?>
                        <input type="hidden" name="kab_category_id" value="<?php echo esc_attr($editing_category_id_for_category); ?>" />
                    <?php endif; ?>
                    <input type="text" name="kab_category_name" value="<?php echo $editing_category ? esc_attr($editing_category->category_name) : ''; ?>" 
                           placeholder="<?php _e('Category Name', 'konfidens-appointment-booking'); ?>" required style="width: 300px; margin-right: 10px;" />
                    <input type="submit" name="kab_save_location_category" class="button button-primary" 
                           value="<?php echo $editing_category ? __('Update Category', 'konfidens-appointment-booking') : __('Add Category', 'konfidens-appointment-booking'); ?>" />
                    <?php if ($editing_category_id_for_category > 0): ?>
                        <a href="<?php echo admin_url('admin.php?page=konfidens-locations'); ?>" class="button"><?php _e('Cancel', 'konfidens-appointment-booking'); ?></a>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if (!empty($location_categories)): ?>
                <div style="margin-top: 20px;">
                    <h3><?php _e('Existing Categories', 'konfidens-appointment-booking'); ?></h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <?php foreach ($location_categories as $category): ?>
                            <li style="margin-bottom: 5px;">
                                <strong><?php echo esc_html($category->category_name); ?></strong>
                                <a href="<?php echo admin_url('admin.php?page=konfidens-locations&edit_category=' . esc_attr($category->id)); ?>" class="button button-small" style="margin-left: 10px;"><?php _e('Edit', 'konfidens-appointment-booking'); ?></a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=konfidens-locations&delete_category=' . esc_attr($category->id)), 'kab_delete_category_' . esc_attr($category->id)); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('<?php _e('Are you sure? This will remove the category from all locations.', 'konfidens-appointment-booking'); ?>');"
                                   style="color: #a00;"><?php _e('Delete', 'konfidens-appointment-booking'); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
            <h2><?php _e('Existing Locations', 'konfidens-appointment-booking'); ?></h2>
            <?php if (empty($locations)): ?>
                <p><?php _e('No locations found. Add your first location above.', 'konfidens-appointment-booking'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><?php _e('ID', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Location Name', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Category', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Created', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Updated', 'konfidens-appointment-booking'); ?></th>
                            <th style="width: 150px;"><?php _e('Actions', 'konfidens-appointment-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location): 
                            $location_category_id = kab_get_location_category_id($location->id);
                            $location_category = $location_category_id ? kab_get_location_category_by_id($location_category_id) : null;
                        ?>
                            <tr>
                                <td><?php echo esc_html($location->id); ?></td>
                                <td><strong><?php echo esc_html($location->location_name); ?></strong></td>
                                <td><?php echo $location_category ? esc_html($location_category->category_name) : '<em>' . __('No Category', 'konfidens-appointment-booking') . '</em>'; ?></td>
                                <td><?php echo esc_html($location->created_at); ?></td>
                                <td><?php echo esc_html($location->updated_at); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=konfidens-locations&edit_location=' . esc_attr($location->id)); ?>" class="button button-small">
                                        <?php _e('Edit', 'konfidens-appointment-booking'); ?>
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=konfidens-locations&delete_location=' . esc_attr($location->id)), 'kab_delete_location_' . esc_attr($location->id)); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php _e('Are you sure you want to delete this location? This action cannot be undone.', 'konfidens-appointment-booking'); ?>');"
                                       style="color: #a00;">
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
