/**
 * Admin JavaScript for Konfidens Appointment Booking
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Modal functionality
        $('.kab-modal-close, .kab-modal-cancel').click(function() {
            $(this).closest('.kab-modal').hide();
        });

        // Close modal when clicking outside
        $(window).click(function(e) {
            if ($(e.target).hasClass('kab-modal')) {
                $('.kab-modal').hide();
            }
        });

        // Initialize color picker if available
        if ($.fn.wpColorPicker) {
            $('.kab-color-picker').wpColorPicker();
        }

        // Services page functionality
        if ($('.kab-admin').hasClass('kab-services-page')) {
            // Edit service button click
            $('.kab-edit-service').click(function() {
                var serviceId = $(this).data('service-id');
                var serviceName = $(this).data('service-name');
                var locations = $(this).data('locations').toString();
                
                $('#service_id').val(serviceId);
                $('#service_name').val(serviceName);
                
                // Set selected locations
                if (locations) {
                    var locationIds = locations.split(',');
                    $('#location_ids').val(locationIds);
                } else {
                    $('#location_ids').val([]);
                }
                
                $('#kab-edit-service-modal').show();
            });
            
            // Submit edit service form
            $('#kab-edit-service-form').submit(function(e) {
                e.preventDefault();
                
                var serviceId = $('#service_id').val();
                var locationIds = $('#location_ids').val();
                
                var data = {
                    'action': 'kab_update_service_locations',
                    'service_id': serviceId,
                    'location_ids': locationIds ? locationIds.join(',') : '',
                    'nonce': kab_admin_vars.nonce
                };
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                });
            });
        }

        // Categories page functionality
        if ($('.kab-admin').hasClass('kab-categories-page')) {
            // Add category button click
            $('#kab-add-category-btn').click(function() {
                $('#kab-add-category-modal').show();
            });
            
            // Submit add category form
            $('#kab-add-category-form').submit(function(e) {
                e.preventDefault();
                
                var categoryName = $('#category_name').val();
                
                if (!categoryName) {
                    alert(kab_admin_vars.error);
                    return;
                }
                
                var data = {
                    'action': 'kab_add_category',
                    'category_name': categoryName,
                    'nonce': kab_admin_vars.nonce
                };
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                });
            });
            
            // Edit category button click
            $('.kab-edit-category').click(function() {
                var categoryId = $(this).data('category-id');
                var categoryName = $(this).data('category-name');
                
                $('#edit_category_id').val(categoryId);
                $('#edit_category_name').val(categoryName);
                
                $('#kab-edit-category-modal').show();
            });
            
            // Submit edit category form
            $('#kab-edit-category-form').submit(function(e) {
                e.preventDefault();
                
                var categoryId = $('#edit_category_id').val();
                var categoryName = $('#edit_category_name').val();
                
                if (!categoryName) {
                    alert(kab_admin_vars.error);
                    return;
                }
                
                var data = {
                    'action': 'kab_update_category',
                    'category_id': categoryId,
                    'category_name': categoryName,
                    'nonce': kab_admin_vars.nonce
                };
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                });
            });
            
            // Delete category button click
            $('.kab-delete-category').click(function() {
                var categoryId = $(this).data('category-id');
                
                if (confirm(kab_admin_vars.confirm_delete)) {
                    var data = {
                        'action': 'kab_delete_category',
                        'category_id': categoryId,
                        'nonce': kab_admin_vars.nonce
                    };
                    
                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message);
                        }
                    });
                }
            });
        }

        // Mapping page functionality
        if ($('.kab-admin').hasClass('kab-mapping-page')) {
            // Edit mapping button click
            $('.kab-edit-mapping').click(function() {
                var specialistId = $(this).data('specialist-id');
                var specialistName = $(this).data('specialist-name');
                var locations = $(this).data('locations').toString();
                
                $('#specialist_id').val(specialistId);
                $('#specialist_name').val(specialistName);
                
                // Set selected locations
                if (locations) {
                    var locationIds = locations.split(',');
                    $('#location_ids').val(locationIds);
                } else {
                    $('#location_ids').val([]);
                }
                
                $('#kab-edit-mapping-modal').show();
            });
            
            // Submit edit mapping form
            $('#kab-edit-mapping-form').submit(function(e) {
                e.preventDefault();
                
                var specialistId = $('#specialist_id').val();
                var locationIds = $('#location_ids').val();
                
                var data = {
                    'action': 'kab_update_specialist_categories',
                    'specialist_id': specialistId,
                    'location_ids': locationIds ? locationIds.join(',') : '',
                    'nonce': kab_admin_vars.nonce
                };
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                });
            });
        }

        // Settings page functionality
        if ($('.kab-admin').hasClass('kab-settings-page')) {
            // Toggle reCAPTCHA fields
            $('#kab_enable_recaptcha').change(function() {
                if ($(this).is(':checked')) {
                    $('.kab-recaptcha-field').show();
                } else {
                    $('.kab-recaptcha-field').hide();
                }
            });
            
        }
    });

})(jQuery);
