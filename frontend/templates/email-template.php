<?php
/**
 * Email template for booking notifications
 *
 * @package Konfidens_Appointment_Booking
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
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
                        <td><?php echo esc_html($booking_data['first_name'] . ' ' . $booking_data['last_name']); ?></td>
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
