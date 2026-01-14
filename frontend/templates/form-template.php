<?php
/**
 * Frontend booking form template
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get context
$context = isset($atts['context']) ? $atts['context'] : 'sidebar';

// Get popup ID for tracking
$popup_id = isset($atts['popup_id']) ? $atts['popup_id'] : '';

// Generate unique form ID for tracking
$form_unique_id = 'kab-form-' . uniqid();

// Form classes
$form_classes = array('kab-booking-form');
$form_classes[] = 'kab-context-' . $context;
?>

<div class="<?php echo esc_attr(implode(' ', $form_classes)); ?>"
     id="<?php echo esc_attr($form_unique_id); ?>"
     data-form-id="<?php echo esc_attr($form_unique_id); ?>"
     <?php echo !empty($popup_id) ? ' data-popup-id="' . esc_attr($popup_id) . '"' : ''; ?>>
    
    <div class="kab-form-progress">
        <div class="kab-progress-text">1/5</div>
        <div class="kab-progress-bar">
            <div class="kab-progress-fill" style="width: 20%"></div>
        </div>
    </div>
    
    <div class="kab-form-steps">
        <!-- Step 1: Category Selection -->
        <div class="kab-form-step" data-step="1">
            <div>
                <div class="kab-step-header">
                    <img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'clover.png'; ?>" alt="Velg kategori">
                    <h3><?php _e('Velg kategori', 'konfidens-appointment-booking'); ?></h3>
                    <p><?php _e('Hva kan vi hjelpe deg med?', 'konfidens-appointment-booking'); ?></p>
                </div>            
                <div class="kab-categories-list">
                    <div class="kab-loading"><?php _e('Loading categories...', 'konfidens-appointment-booking'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Step 2: Location Selection -->
        <div class="kab-form-step" data-step="2" style="display: none;">
            <div>
                <div class="kab-step-header">
                    <img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'map-pin.png'; ?>" alt="Sted for møtet">
                    <h3><?php _e('Sted for møtet', 'konfidens-appointment-booking'); ?></h3>
                    <p><?php _e('Hvordan ønsker du å gjennomføre samtalen?', 'konfidens-appointment-booking'); ?></p>
                </div>
                <div class="kab-locations-list">
                    <div class="kab-loading"><?php _e('Loading locations...', 'konfidens-appointment-booking'); ?></div>
                </div>
            </div>
            <div class="kab-form-navigation">
                <button class="kab-prev-btn"><img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'arrowleft.png'; ?>" alt="<?php _e('Back', 'konfidens-appointment-booking'); ?>"></button>
            </div>
        </div>
        
        <!-- Step 3: Specialist Selection -->
        <div class="kab-form-step" data-step="3" style="display: none;">
            <div>
                <div class="kab-step-header">
                    <img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'face.png'; ?>" alt="Din behandler">
                    <h3><?php _e('Din behandler', 'konfidens-appointment-booking'); ?></h3>
                    <p><?php _e('Hvilken terapeut ønsker du å bestille en samtale med?', 'konfidens-appointment-booking'); ?></p>
                </div>        
                <div class="kab-specialists-list">
                    <div class="kab-loading"><?php _e('Loading therapists...', 'konfidens-appointment-booking'); ?></div>
                </div>
            </div>
            <div class="kab-form-navigation">
                <button class="kab-prev-btn"><img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'arrowleft.png'; ?>" alt="<?php _e('Back', 'konfidens-appointment-booking'); ?>"></button>
                <button class="kab-random-specialist-btn"><img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'arrowright.png'; ?>" alt="<?php _e('Next', 'konfidens-appointment-booking'); ?>"></button>
            </div>
        </div>
        
        <!-- Step 4: Date & Time Selection -->
        <div class="kab-form-step" data-step="4" style="display: none;">
            <div>
                <div class="kab-step-header">
                    <img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'date.png'; ?>" alt="Dato & klokkeslett">
                    <h3><?php _e('Dato & klokkeslett', 'konfidens-appointment-booking'); ?></h3>
                    <p><?php _e('Vennligst velg en passende dato og klokkeslett.', 'konfidens-appointment-booking'); ?></p>
                </div>
                <div class="kab-date-time-selection">
                    <div class="kab-date-picker-container">
                        <div class="kab-date-picker"></div>
                    </div>
                    
                    <div class="kab-time-slots-container">
                        <div class="kab-time-slots">
                            <div class="kab-no-date-selected">
                                <?php _e('Please select a date to view available time slots.', 'konfidens-appointment-booking'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>    
            <div class="kab-form-navigation">
                <button class="kab-prev-btn"><img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'arrowleft.png'; ?>" alt="<?php _e('Back', 'konfidens-appointment-booking'); ?>"></button>
            </div>
        </div>
        
        <!-- Step 5: Personal Details -->
        <div class="kab-form-step" data-step="5" style="display: none;">
            <div>
                <div class="kab-step-header">
                    <img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'info.png'; ?>" alt="Dine detaljer">
                    <h3><?php _e('Dine detaljer', 'konfidens-appointment-booking'); ?></h3>
                    <p><?php _e('Siste steg! Alle dine personlige opplysninger forblir helt anonyme.', 'konfidens-appointment-booking'); ?></p>
                </div>    
                <div class="kab-details-container">
                    <!-- Left column: Booking summary -->
                    <div class="kab-booking-summary">
                        <div class="kab-summary-content">
                            <div class="kab-summary-item">
                                <div class="kab-summary-label"><?php _e('Din terapeut:', 'konfidens-appointment-booking'); ?></div>
                                <div class="kab-summary-value kab-summary-therapist"></div>
                            </div>
                            <div class="kab-summary-item">
                                <div class="kab-summary-label"><?php _e('Type:', 'konfidens-appointment-booking'); ?></div>
                                <div class="kab-summary-value kab-summary-service"></div>
                            </div>
                            <div class="kab-summary-item">
                                <div class="kab-summary-label"><?php _e('Sted:', 'konfidens-appointment-booking'); ?></div>
                                <div class="kab-summary-value kab-summary-location"></div>
                            </div>
                            <div class="kab-summary-item">
                                <div class="kab-summary-label"><?php _e('Dato:', 'konfidens-appointment-booking'); ?></div>
                                <div class="kab-summary-value kab-summary-date"></div>
                            </div>
                            <div class="kab-summary-item">
                                <div class="kab-summary-label"><?php _e('Klokkeslett:', 'konfidens-appointment-booking'); ?></div>
                                <div class="kab-summary-value kab-summary-time"></div>
                            </div>
                            <div class="kab-summary-item kab-summary-price-item">
                                <div class="kab-summary-label"><?php _e('Pris:', 'konfidens-appointment-booking'); ?></div>
                                <div class="kab-summary-value kab-summary-price"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right column: Personal details form -->
                    <div class="kab-personal-details">
                        <div class="kab-form-group">
                            <label for="kab-first-name"><?php _e('Fornavnet ditt', 'konfidens-appointment-booking'); ?></label>
                            <input type="text" id="kab-first-name" name="first_name" required placeholder="<?php _e('Fornavnet ditt', 'konfidens-appointment-booking'); ?>">
                        </div>
                        
                        <div class="kab-form-group">
                            <label for="kab-email"><?php _e('Din e-post', 'konfidens-appointment-booking'); ?></label>
                            <input type="email" id="kab-email" name="email" required placeholder="<?php _e('Din e-post', 'konfidens-appointment-booking'); ?>">
                        </div>
                        
                        <div class="kab-form-group">
                            <label for="kab-phone"><?php _e('Telefonnummeret ditt', 'konfidens-appointment-booking'); ?></label>
                            <input type="tel" id="kab-phone" name="phone" required placeholder="<?php _e('Telefonnummeret ditt', 'konfidens-appointment-booking'); ?>">
                        </div>
                        
                        <div class="kab-form-group kab-checkbox-group">
                            <input type="checkbox" id="kab-terms" name="terms" required>
                            <label for="kab-terms"><?php _e('Klikk & samtykk til våre', 'konfidens-appointment-booking'); ?> <a href="#" class="kab-terms-link"><?php _e('handelsbetingelser', 'konfidens-appointment-booking'); ?></a> <?php _e('for å fullføre bestilling.', 'konfidens-appointment-booking'); ?></label>
                        </div>
                        
                        <?php if (get_option('kab_enable_recaptcha', false) && !empty(get_option('kab_recaptcha_site_key', ''))): ?>
                            <div class="kab-recaptcha-notice">
                                <?php _e('This site is protected by reCAPTCHA.', 'konfidens-appointment-booking'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="kab-form-navigation">
                            <button type="button" class="kab-submit-btn"><?php _e('Bekreft reservasjon', 'konfidens-appointment-booking'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="kab-details-navigation">
                <button type="button" class="kab-prev-btn"><img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'arrowleft.png'; ?>" alt="<?php _e('Back', 'konfidens-appointment-booking'); ?>"></button>
            </div>
        </div>
        
        <!-- Step 6: Confirmation -->
        <div class="kab-form-step" data-step="6" style="display: none;">
            <div>
                <div class="kab-confirmation-message">
                    <div class="kab-loading"><?php _e('Processing your booking...', 'konfidens-appointment-booking'); ?></div>
                    <div class="kab-success" style="display: none;">
                        <div class="kab-success-icon">
                            <img src="<?php echo plugins_url('frontend/assets/images/', dirname(dirname(__FILE__))) . 'illustration.png'; ?>" alt="<?php _e('Success', 'konfidens-appointment-booking'); ?>">
                        </div>
                        <h3><?php _e('Du har bestilt din samtale!', 'konfidens-appointment-booking'); ?></h3>
                        <p class="kab-booking-message"><?php _e('Vi har sendt deg en bekreftelse på e-post. Vennligst sjekk innboksen din for å bekrefte at alle opplysningene stemmer.', 'konfidens-appointment-booking'); ?></p>
                        <div class="kab-booking-details"></div>
                        <div class="kab-confirmation-buttons">
                            <button class="kab-book-new-btn"><?php _e('Bestill en ny time', 'konfidens-appointment-booking'); ?></button>
                            <button class="kab-close-window-btn"><?php _e('Lukk vindu', 'konfidens-appointment-booking'); ?></button>
                        </div>
                    </div>
                    <div class="kab-error" style="display: none;">
                        <div class="kab-error-icon">✗</div>
                        <h3><?php _e('Booking Failed', 'konfidens-appointment-booking'); ?></h3>
                        <p class="kab-error-message"></p>
                        <button class="kab-try-again-btn"><?php _e('Try Again', 'konfidens-appointment-booking'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>