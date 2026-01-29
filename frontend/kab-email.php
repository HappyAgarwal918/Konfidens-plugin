<?php
if (!defined('ABSPATH')) {
    exit;
}

// Send email notification to admin and confirmation email to user after booking
function kab_send_booking_notification($booking_data) {
    $recipient = get_option('kab_email_recipient', get_option('admin_email'));
    $subject = get_option('kab_email_subject', __('New Appointment Booking', 'konfidens-appointment-booking'));
    $date = date_i18n(get_option('date_format'), strtotime($booking_data['timeslot_date']));
    $time = date_i18n(get_option('time_format'), strtotime($booking_data['timeslot_time']));
    $content = kab_get_email_template($booking_data, $date, $time);
    
    // Use SMTP-compatible From address
    $from_email = function_exists('kab_get_from_email') ? kab_get_from_email() : get_option('admin_email');
    $from_name = function_exists('kab_get_from_name') ? kab_get_from_name() : get_bloginfo('name');
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>'
    );
    
    $admin_email_sent = wp_mail($recipient, $subject, $content, $headers);
    kab_send_user_confirmation_email($booking_data);
    return $admin_email_sent;
}

// Send booking confirmation email to the user
function kab_send_user_confirmation_email($booking_data) {
    if (empty($booking_data['email'])) {
        return false;
    }
    
    $user_email = sanitize_email($booking_data['email']);
    $subject = __('Booking Confirmation', 'konfidens-appointment-booking');
    $date = date_i18n(get_option('date_format'), strtotime($booking_data['timeslot_date']));
    $time = date_i18n(get_option('time_format'), strtotime($booking_data['timeslot_time']));
    $content = kab_get_user_confirmation_email_template($booking_data, $date, $time);
    
    // Use SMTP-compatible From address
    $from_email = function_exists('kab_get_from_email') ? kab_get_from_email() : get_option('admin_email');
    $from_name = function_exists('kab_get_from_name') ? kab_get_from_name() : get_bloginfo('name');
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from_name . ' <' . $from_email . '>'
    );
    
    return wp_mail($user_email, $subject, $content, $headers);
}

// Generate HTML email template for admin notification
function kab_get_email_template($booking_data, $date, $time) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title><?php _e('New Appointment Booking', 'konfidens-appointment-booking'); ?></title>
        <style type="text/css">
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background-color: #007bff;
                color: #fff;
                padding: 15px;
                text-align: center;
            }
            .content {
                padding: 20px;
                background-color: #f9f9f9;
            }
            .booking-details {
                background-color: #fff;
                padding: 15px;
                margin-bottom: 20px;
                border-left: 4px solid #007bff;
            }
            .patient-details {
                background-color: #fff;
                padding: 15px;
                border-left: 4px solid #28a745;
            }
            .footer {
                text-align: center;
                padding: 10px;
                font-size: 12px;
                color: #777;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            table td {
                padding: 8px;
            }
            .label {
                font-weight: bold;
                width: 40%;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><?php _e('New Appointment Booking', 'konfidens-appointment-booking'); ?></h1>
            </div>
            
            <div class="content">
                <p><?php _e('A new appointment has been booked through the website.', 'konfidens-appointment-booking'); ?></p>
                
                <div class="booking-details">
                    <h2><?php _e('Booking Details', 'konfidens-appointment-booking'); ?></h2>
                    <table>
                        <tr>
                            <td class="label"><?php _e('Booking ID', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($booking_data['booking_id']); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Service', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($booking_data['service_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Therapist', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($booking_data['specialist_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Date', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($date); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Time', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($time); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Booked From', 'konfidens-appointment-booking'); ?>:</td>
                            <td>
                                <?php 
                                if ($booking_data['booked_from'] == 1) {
                                    _e('Booking Form', 'konfidens-appointment-booking');
                                } else {
                                    _e('Direct Link', 'konfidens-appointment-booking');
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Page URL', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_url(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : site_url()); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="patient-details">
                    <h2><?php _e('Patient Information', 'konfidens-appointment-booking'); ?></h2>
                    <table>
                        <tr>
                            <td class="label"><?php _e('Name', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html(trim($booking_data['first_name'] . ' ' . (isset($booking_data['last_name']) ? $booking_data['last_name'] : ''))); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Email', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($booking_data['email']); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Phone', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($booking_data['phone']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="footer">
                <p><?php _e('This email was sent from', 'konfidens-appointment-booking'); ?> <?php echo get_bloginfo('name'); ?> (<?php echo site_url(); ?>)</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    
    return ob_get_clean();
}

// Generate HTML email template for user confirmation
function kab_get_user_confirmation_email_template($booking_data, $date, $time) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title><?php _e('Booking Confirmation', 'konfidens-appointment-booking'); ?></title>
        <style type="text/css">
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                background-color: #007bff;
                color: #fff;
                padding: 15px;
                text-align: center;
            }
            .content {
                padding: 20px;
                background-color: #f9f9f9;
            }
            .booking-details {
                background-color: #fff;
                padding: 15px;
                margin-bottom: 20px;
                border-left: 4px solid #007bff;
            }
            .footer {
                text-align: center;
                padding: 10px;
                font-size: 12px;
                color: #777;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            table td {
                padding: 8px;
            }
            .label {
                font-weight: bold;
                width: 40%;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><?php _e('Booking Confirmation', 'konfidens-appointment-booking'); ?></h1>
            </div>
            
            <div class="content">
                <p><?php printf(__('Dear %s,', 'konfidens-appointment-booking'), esc_html($booking_data['first_name'])); ?></p>
                
                <p><?php _e('Thank you for booking an appointment with us. Your booking has been confirmed.', 'konfidens-appointment-booking'); ?></p>
                
                <div class="booking-details">
                    <h2><?php _e('Your Booking Details', 'konfidens-appointment-booking'); ?></h2>
                    <table>
                        <tr>
                            <td class="label"><?php _e('Booking ID', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($booking_data['booking_id']); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Service', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($booking_data['service_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Therapist', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($booking_data['specialist_name']); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Date', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($date); ?></td>
                        </tr>
                        <tr>
                            <td class="label"><?php _e('Time', 'konfidens-appointment-booking'); ?>:</td>
                            <td><?php echo esc_html($time); ?></td>
                        </tr>
                    </table>
                </div>
                
                <p><?php _e('Please check your inbox for any additional information or updates regarding your appointment.', 'konfidens-appointment-booking'); ?></p>
                
                <p><?php _e('If you have any questions or need to make changes to your booking, please contact us.', 'konfidens-appointment-booking'); ?></p>
            </div>
            
            <div class="footer">
                <p><?php _e('This email was sent from', 'konfidens-appointment-booking'); ?> <?php echo get_bloginfo('name'); ?> (<?php echo site_url(); ?>)</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    
    return ob_get_clean();
}
