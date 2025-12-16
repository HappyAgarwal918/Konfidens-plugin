<?php
/**
 * Thank you page template
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get booking details from URL parameters
$service_id = isset($_GET['serviceId']) ? sanitize_text_field($_GET['serviceId']) : '';
$service_title = isset($_GET['serviceTitle']) ? sanitize_text_field($_GET['serviceTitle']) : '';
$practitioner_id = isset($_GET['practitionerId']) ? sanitize_text_field($_GET['practitionerId']) : '';
$starts_at = isset($_GET['startsAt']) ? sanitize_text_field($_GET['startsAt']) : '';
$booking_id = isset($_GET['bookingId']) ? sanitize_text_field($_GET['bookingId']) : '';

// Format date and time
$date = '';
$time = '';

if (!empty($starts_at)) {
    $date_time = explode('T', $starts_at);
    
    if (isset($date_time[0])) {
        $date = date_i18n(get_option('date_format'), strtotime($date_time[0]));
    }
    
    if (isset($date_time[1])) {
        $time = date_i18n(get_option('time_format'), strtotime($date_time[1]));
    }
}

// Get therapist information if available
$therapist_name = '';
if (!empty($practitioner_id)) {
    $therapist = kab_get_therapist_by_id($practitioner_id);
    
    if (!empty($therapist) && isset($therapist['name'])) {
        $therapist_name = $therapist['name'];
    }
}

// Get booking message from settings
$booking_msg = get_option('kab_booking_msg', 'Thank you for booking with us. Your appointment has been confirmed.');

get_header();
?>

<div class="kab-thank-you-page">
    <div class="kab-thank-you-container">
        <div class="kab-thank-you-header">
            <div class="kab-success-icon">✓</div>
            <h1><?php _e('Booking Confirmed!', 'konfidens-appointment-booking'); ?></h1>
        </div>
        
        <div class="kab-thank-you-message">
            <?php echo wp_kses_post($booking_msg); ?>
        </div>
        
        <div class="kab-thank-you-details">
            <h2><?php _e('Booking Details', 'konfidens-appointment-booking'); ?></h2>
            
            <div class="kab-booking-info">
                <?php if (!empty($booking_id)): ?>
                    <div class="kab-booking-info-item">
                        <span class="kab-booking-info-label"><?php _e('Booking ID', 'konfidens-appointment-booking'); ?>:</span>
                        <span class="kab-booking-info-value"><?php echo esc_html($booking_id); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($service_title)): ?>
                    <div class="kab-booking-info-item">
                        <span class="kab-booking-info-label"><?php _e('Service', 'konfidens-appointment-booking'); ?>:</span>
                        <span class="kab-booking-info-value"><?php echo esc_html($service_title); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($therapist_name)): ?>
                    <div class="kab-booking-info-item">
                        <span class="kab-booking-info-label"><?php _e('Therapist', 'konfidens-appointment-booking'); ?>:</span>
                        <span class="kab-booking-info-value"><?php echo esc_html($therapist_name); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($date)): ?>
                    <div class="kab-booking-info-item">
                        <span class="kab-booking-info-label"><?php _e('Date', 'konfidens-appointment-booking'); ?>:</span>
                        <span class="kab-booking-info-value"><?php echo esc_html($date); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($time)): ?>
                    <div class="kab-booking-info-item">
                        <span class="kab-booking-info-label"><?php _e('Time', 'konfidens-appointment-booking'); ?>:</span>
                        <span class="kab-booking-info-value"><?php echo esc_html($time); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="kab-thank-you-actions">
            <a href="<?php echo esc_url(home_url()); ?>" class="kab-button">
                <?php _e('Return to Home', 'konfidens-appointment-booking'); ?>
            </a>
        </div>
    </div>
</div>

<?php get_footer(); ?>