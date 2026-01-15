<?php
if (!defined('ABSPATH')) {
    exit;
}

// Display service categories page: Manage service categories and parent categories
function kab_display_service_categories_page() {
    if (isset($_POST['kab_save_category']) && check_admin_referer('kab_service_category_nonce', 'kab_service_category_nonce')) {
        $category_id = isset($_POST['kab_category_id']) ? intval($_POST['kab_category_id']) : 0;
        $category_name = isset($_POST['kab_category_name']) ? sanitize_text_field($_POST['kab_category_name']) : '';
        
        if (empty($category_name)) {
            add_settings_error(
                'kab_service_categories',
                'kab_category_name_required',
                __('Category name is required.', 'konfidens-appointment-booking'),
                'error'
            );
        } else {
            if ($category_id > 0) {
                // Update existing category
                $result = kab_update_service_category($category_id, $category_name);
                if ($result !== false) {
                    add_settings_error(
                        'kab_service_categories',
                        'kab_category_updated',
                        __('Category updated successfully.', 'konfidens-appointment-booking'),
                        'success'
                    );
                    // Redirect to avoid resubmission
                    wp_redirect(admin_url('admin.php?page=konfidens-service-categories&updated=1'));
                    exit;
                } else {
                    add_settings_error(
                        'kab_service_categories',
                        'kab_category_update_failed',
                        __('Failed to update category.', 'konfidens-appointment-booking'),
                        'error'
                    );
                }
            } else {
                // Add new category
                $result = kab_add_service_category($category_name);
                if ($result !== false) {
                    add_settings_error(
                        'kab_service_categories',
                        'kab_category_added',
                        __('Category added successfully.', 'konfidens-appointment-booking'),
                        'success'
                    );
                    // Redirect to avoid resubmission
                    wp_redirect(admin_url('admin.php?page=konfidens-service-categories&added=1'));
                    exit;
                } else {
                    add_settings_error(
                        'kab_service_categories',
                        'kab_category_add_failed',
                        __('Failed to add category.', 'konfidens-appointment-booking'),
                        'error'
                    );
                }
            }
        }
    }
    
    // Handle delete
    if (isset($_GET['delete']) && check_admin_referer('kab_delete_category_' . $_GET['delete'])) {
        $category_id = intval($_GET['delete']);
        $result = kab_delete_service_category($category_id);
        
        if ($result !== false) {
            add_settings_error(
                'kab_service_categories',
                'kab_category_deleted',
                __('Category deleted successfully.', 'konfidens-appointment-booking'),
                'success'
            );
            // Redirect to avoid resubmission
            wp_redirect(admin_url('admin.php?page=konfidens-service-categories&deleted=1'));
            exit;
        } else {
            add_settings_error(
                'kab_service_categories',
                'kab_category_delete_failed',
                __('Failed to delete category.', 'konfidens-appointment-booking'),
                'error'
            );
        }
    }
    
    // Get category to edit
    $editing_category = null;
    $editing_category_id = '';
    if (isset($_GET['edit'])) {
        $editing_category_id = intval($_GET['edit']);
        $editing_category = kab_get_service_category_by_id($editing_category_id);
    }
    
    // Get all categories
    $categories = kab_get_service_categories();
    
    // Get all parent categories
    $parent_categories = kab_get_service_parent_categories();
    
    // Show success messages after redirect
    if (isset($_GET['added']) && $_GET['added'] == '1') {
        add_settings_error(
            'kab_service_categories',
            'kab_category_added',
            __('Category added successfully.', 'konfidens-appointment-booking'),
            'success'
        );
    }
    if (isset($_GET['updated']) && $_GET['updated'] == '1') {
        add_settings_error(
            'kab_service_categories',
            'kab_category_updated',
            __('Category updated successfully.', 'konfidens-appointment-booking'),
            'success'
        );
    }
    if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
        add_settings_error(
            'kab_service_categories',
            'kab_category_deleted',
            __('Category deleted successfully.', 'konfidens-appointment-booking'),
            'success'
        );
    }
    
    settings_errors('kab_service_categories');
    ?>
    <div class="wrap kab-admin">
        <h1><?php _e('Service Categories', 'konfidens-appointment-booking'); ?></h1>
        <p><?php _e('Manage service categories. Categories can be used to organize services in the booking form.', 'konfidens-appointment-booking'); ?></p>
        
        <!-- Parent Category Management Section -->
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
            <h2><?php _e('Parent Categories', 'konfidens-appointment-booking'); ?></h2>
            <p><?php _e('Manage parent categories. Each service category can be assigned to one parent category.', 'konfidens-appointment-booking'); ?></p>
            
            <?php
            // Handle parent category form submissions
            if (isset($_POST['kab_save_parent_category']) && check_admin_referer('kab_parent_category_nonce', 'kab_parent_category_nonce')) {
                $parent_category_id = isset($_POST['kab_parent_category_id']) ? intval($_POST['kab_parent_category_id']) : 0;
                $parent_category_name = isset($_POST['kab_parent_category_name']) ? sanitize_text_field($_POST['kab_parent_category_name']) : '';
                
                if (empty($parent_category_name)) {
                    echo '<div class="notice notice-error"><p>' . __('Parent category name is required.', 'konfidens-appointment-booking') . '</p></div>';
                } else {
                    if ($parent_category_id > 0) {
                        $result = kab_update_service_parent_category($parent_category_id, $parent_category_name);
                        if ($result !== false) {
                            echo '<div class="notice notice-success"><p>' . __('Parent category updated successfully.', 'konfidens-appointment-booking') . '</p></div>';
                            wp_redirect(admin_url('admin.php?page=konfidens-service-categories&parent_updated=1'));
                            exit;
                        } else {
                            echo '<div class="notice notice-error"><p>' . __('Failed to update parent category.', 'konfidens-appointment-booking') . '</p></div>';
                        }
                    } else {
                        $result = kab_add_service_parent_category($parent_category_name);
                        if ($result !== false) {
                            echo '<div class="notice notice-success"><p>' . __('Parent category added successfully.', 'konfidens-appointment-booking') . '</p></div>';
                            wp_redirect(admin_url('admin.php?page=konfidens-service-categories&parent_added=1'));
                            exit;
                        } else {
                            echo '<div class="notice notice-error"><p>' . __('Failed to add parent category.', 'konfidens-appointment-booking') . '</p></div>';
                        }
                    }
                }
            }
            
            // Handle parent category delete
            if (isset($_GET['delete_parent']) && check_admin_referer('kab_delete_parent_category_' . $_GET['delete_parent'])) {
                $parent_category_id = intval($_GET['delete_parent']);
                $result = kab_delete_service_parent_category($parent_category_id);
                
                if ($result !== false) {
                    echo '<div class="notice notice-success"><p>' . __('Parent category deleted successfully.', 'konfidens-appointment-booking') . '</p></div>';
                    wp_redirect(admin_url('admin.php?page=konfidens-service-categories&parent_deleted=1'));
                    exit;
                } else {
                    echo '<div class="notice notice-error"><p>' . __('Failed to delete parent category.', 'konfidens-appointment-booking') . '</p></div>';
                }
            }
            
            // Get parent category to edit
            $editing_parent_category = null;
            $editing_parent_category_id = '';
            if (isset($_GET['edit_parent'])) {
                $editing_parent_category_id = intval($_GET['edit_parent']);
                $editing_parent_category = kab_get_service_parent_category_by_id($editing_parent_category_id);
            }
            
            // Show success messages after redirect
            if (isset($_GET['parent_added']) && $_GET['parent_added'] == '1') {
                echo '<div class="notice notice-success"><p>' . __('Parent category added successfully.', 'konfidens-appointment-booking') . '</p></div>';
            }
            if (isset($_GET['parent_updated']) && $_GET['parent_updated'] == '1') {
                echo '<div class="notice notice-success"><p>' . __('Parent category updated successfully.', 'konfidens-appointment-booking') . '</p></div>';
            }
            if (isset($_GET['parent_deleted']) && $_GET['parent_deleted'] == '1') {
                echo '<div class="notice notice-success"><p>' . __('Parent category deleted successfully.', 'konfidens-appointment-booking') . '</p></div>';
            }
            ?>
            
            <div style="margin-bottom: 20px;">
                <h3><?php echo $editing_parent_category ? __('Edit Parent Category', 'konfidens-appointment-booking') : __('Add New Parent Category', 'konfidens-appointment-booking'); ?></h3>
                <form method="post" action="" style="display: inline-block;">
                    <?php wp_nonce_field('kab_parent_category_nonce', 'kab_parent_category_nonce'); ?>
                    <?php if ($editing_parent_category_id): ?>
                        <input type="hidden" name="kab_parent_category_id" value="<?php echo esc_attr($editing_parent_category_id); ?>" />
                    <?php endif; ?>
                    
                    <input type="text" name="kab_parent_category_name" class="regular-text" 
                        value="<?php echo $editing_parent_category ? esc_attr($editing_parent_category->parent_category_name) : ''; ?>" 
                        placeholder="<?php _e('e.g., Main Category, Sub Category', 'konfidens-appointment-booking'); ?>" required />
                    
                    <input type="submit" name="kab_save_parent_category" class="button button-primary" 
                        value="<?php echo $editing_parent_category ? __('Update Parent Category', 'konfidens-appointment-booking') : __('Add Parent Category', 'konfidens-appointment-booking'); ?>" />
                    
                    <?php if ($editing_parent_category): ?>
                        <a href="<?php echo admin_url('admin.php?page=konfidens-service-categories'); ?>" class="button">
                            <?php _e('Cancel', 'konfidens-appointment-booking'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if (!empty($parent_categories)): ?>
                <div style="margin-top: 15px;">
                    <h3><?php _e('Existing Parent Categories', 'konfidens-appointment-booking'); ?></h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <?php foreach ($parent_categories as $parent_cat): ?>
                            <li style="margin: 5px 0;">
                                <strong><?php echo esc_html($parent_cat->parent_category_name); ?></strong>
                                <a href="<?php echo admin_url('admin.php?page=konfidens-service-categories&edit_parent=' . esc_attr($parent_cat->id)); ?>" class="button button-small" style="margin-left: 10px;">
                                    <?php _e('Edit', 'konfidens-appointment-booking'); ?>
                                </a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=konfidens-service-categories&delete_parent=' . esc_attr($parent_cat->id)), 'kab_delete_parent_category_' . esc_attr($parent_cat->id)); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('<?php _e('Are you sure you want to delete this parent category? Service categories assigned to this parent category will have their parent category removed.', 'konfidens-appointment-booking'); ?>');">
                                    <?php _e('Delete', 'konfidens-appointment-booking'); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
            <h2><?php echo $editing_category ? __('Edit Category', 'konfidens-appointment-booking') : __('Add New Category', 'konfidens-appointment-booking'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('kab_service_category_nonce', 'kab_service_category_nonce'); ?>
                <?php if ($editing_category_id): ?>
                    <input type="hidden" name="kab_category_id" value="<?php echo esc_attr($editing_category_id); ?>" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kab_category_name"><?php _e('Category Name', 'konfidens-appointment-booking'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="kab_category_name" id="kab_category_name" class="regular-text" 
                                value="<?php echo $editing_category ? esc_attr($editing_category->category_name) : ''; ?>" 
                                placeholder="<?php _e('e.g., Massage, Therapy, Consultation', 'konfidens-appointment-booking'); ?>" required />
                            <p class="description"><?php _e('Enter a name for this category.', 'konfidens-appointment-booking'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="kab_save_category" class="button button-primary" 
                        value="<?php echo $editing_category ? __('Update Category', 'konfidens-appointment-booking') : __('Add Category', 'konfidens-appointment-booking'); ?>" />
                    <?php if ($editing_category): ?>
                        <a href="<?php echo admin_url('admin.php?page=konfidens-service-categories'); ?>" class="button">
                            <?php _e('Cancel', 'konfidens-appointment-booking'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </form>
        </div>
        
        <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
            <h2><?php _e('Existing Categories', 'konfidens-appointment-booking'); ?></h2>
            <?php if (empty($categories)): ?>
                <p><?php _e('No categories created yet.', 'konfidens-appointment-booking'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Category Name', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Parent Category', 'konfidens-appointment-booking'); ?></th>
                            <th><?php _e('Actions', 'konfidens-appointment-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): 
                            // Get parent category name if assigned
                            $parent_category_name = '';
                            if (!empty($category->parent_category_id)) {
                                $parent_cat = kab_get_service_parent_category_by_id($category->parent_category_id);
                                if ($parent_cat) {
                                    $parent_category_name = $parent_cat->parent_category_name;
                                }
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html($category->id); ?></td>
                                <td><strong><?php echo esc_html($category->category_name); ?></strong></td>
                                <td>
                                    <select class="kab-parent-category-select" data-category-id="<?php echo esc_attr($category->id); ?>" style="min-width: 200px;">
                                        <option value=""><?php _e('-- No Parent Category --', 'konfidens-appointment-booking'); ?></option>
                                        <?php foreach ($parent_categories as $parent_cat): ?>
                                            <option value="<?php echo esc_attr($parent_cat->id); ?>" <?php selected($category->parent_category_id, $parent_cat->id); ?>>
                                                <?php echo esc_html($parent_cat->parent_category_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="kab-parent-category-save-status" id="parent-save-status-<?php echo esc_attr($category->id); ?>" style="display: none; margin-left: 10px; color: green; font-style: italic;">
                                        <?php _e('Saved!', 'konfidens-appointment-booking'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=konfidens-service-categories&edit=' . esc_attr($category->id)); ?>" class="button button-small">
                                        <?php _e('Edit', 'konfidens-appointment-booking'); ?>
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=konfidens-service-categories&delete=' . esc_attr($category->id)), 'kab_delete_category_' . esc_attr($category->id)); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php _e('Are you sure you want to delete this category? Services assigned to this category will have their category removed.', 'konfidens-appointment-booking'); ?>');">
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
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Parent category assignment via AJAX
        $('.kab-parent-category-select').on('change', function() {
            var categoryId = $(this).data('category-id');
            var parentCategoryId = $(this).val();
            var saveStatus = $('#parent-save-status-' + categoryId);
            
            saveStatus.text('<?php _e("Saving...", "konfidens-appointment-booking"); ?>').css('color', '#666').show();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'kab_update_service_category_parent',
                    category_id: categoryId,
                    parent_category_id: parentCategoryId,
                    nonce: '<?php echo wp_create_nonce("kab-admin-nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        saveStatus.text('<?php _e("Saved!", "konfidens-appointment-booking"); ?>').css('color', 'green');
                        setTimeout(function() {
                            saveStatus.fadeOut(500);
                        }, 1500);
                    } else {
                        saveStatus.text('<?php _e("Error saving", "konfidens-appointment-booking"); ?>').css('color', 'red');
                    }
                },
                error: function() {
                    saveStatus.text('<?php _e("Error saving", "konfidens-appointment-booking"); ?>').css('color', 'red');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * AJAX handler for updating service category parent category
 */
function kab_update_service_category_parent_ajax() {
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'kab-admin-nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'konfidens-appointment-booking')));
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'konfidens-appointment-booking')));
    }
    
    // Check required fields
    if (!isset($_POST['category_id'])) {
        wp_send_json_error(array('message' => __('Missing required fields.', 'konfidens-appointment-booking')));
    }
    
    $category_id = intval($_POST['category_id']);
    $parent_category_id = isset($_POST['parent_category_id']) ? $_POST['parent_category_id'] : null;
    
    // Update parent category assignment
    $result = kab_update_service_category_parent($category_id, $parent_category_id);
    
    if ($result !== false) {
        wp_send_json_success(array('message' => __('Parent category updated successfully.', 'konfidens-appointment-booking')));
    } else {
        wp_send_json_error(array('message' => __('Failed to update parent category.', 'konfidens-appointment-booking')));
    }
}
add_action('wp_ajax_kab_update_service_category_parent', 'kab_update_service_category_parent_ajax');
