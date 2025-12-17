/**
 * Public JavaScript for Konfidens Appointment Booking - Therapist First Flow
 */

(function($) {
    'use strict';

    /**
     * Booking Form Class for Therapist-First Flow
     */
    class KABTherapistFirstBookingForm {
        /**
         * Initialize the therapist-first booking form
         * 
         * @param {jQuery} $form The booking form element
         */
        constructor($form) {
            this.$form = $form;
            this.currentStep = 1;
            this.maxStep = 4;
            
            this.therapistId = $form.data('therapist-id') || '';
            this.serviceId = '';
            this.locationId = '';
            this.selectedDate = '';
            this.selectedTime = '';
            this.services = [];
            this.therapist = null;
            
            this.init();
        }
        
        /**
         * Initialize the form
         */
        init() {
            if (!this.therapistId) {
                console.error('Therapist ID is required for therapist-first booking form');
                return;
            }
            
            this.initEvents();
            this.loadTherapist();
            this.loadServices();
        }
        
        /**
         * Initialize events
         */
        initEvents() {
            const self = this;
            
            // Service selection
            this.$form.on('click', '.kab-service-item', function() {
                const serviceId = $(this).data('service-id');
                self.selectService(serviceId);
            });
            
            // Category selection
            this.$form.on('click', '.kab-category-item', function() {
                const locationId = $(this).data('location-id');
                self.selectLocation(locationId);
            });
            
            // Day selection
            this.$form.on('click', '.kab-day:not(.empty):not(.disabled)', function() {
                const date = $(this).data('date');
                self.selectDate(date);
            });
            
            // Time slot selection
            this.$form.on('click', '.kab-time-slot:not(.disabled)', function() {
                const timeSlot = $(this).data('timeslot');
                self.selectTimeSlot(timeSlot);
            });
            
            // Navigation buttons
            this.$form.on('click', '.kab-next-btn', function() {
                if ($(this).prop('disabled')) return;
                self.nextStep();
            });
            
            this.$form.on('click', '.kab-prev-btn', function() {
                self.prevStep();
            });
            
            // Form submission
            this.$form.on('submit', function(e) {
                e.preventDefault();
                self.submitBooking();
                self.goToStep(5);
                return false;
            });
            
            // Try again button
            this.$form.on('click', '.kab-try-again-btn', function() {
                self.goToStep(4);
            });
            
            // Change therapist link
            this.$form.on('click', '.kab-change-therapist-link', function(e) {
                e.preventDefault();
                // This could trigger opening the therapist selection modal
                // For now, we'll just log it
                console.log('Change therapist clicked');
            });
        }
        
        /**
         * Load therapist information
         */
        loadTherapist() {
            const self = this;
            
            // Show the therapist card container even before data loads
            const $card = this.$form.find('.kab-selected-therapist-card');
            if ($card.length) {
                $card.show();
            }
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_all_therapists',
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.therapists) {
                        // Find the selected therapist
                        const therapist = response.data.therapists.find(t => t.id === self.therapistId);
                        
                        if (therapist) {
                            self.therapist = therapist;
                            self.displayTherapistCard(therapist);
                        } else {
                            console.warn('Therapist not found with ID:', self.therapistId);
                            // Show card with error message
                            const $card = self.$form.find('.kab-selected-therapist-card');
                            $card.find('.kab-therapist-name').text('Therapist not found');
                        }
                    } else {
                        console.error('No therapists in response');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading therapist:', error);
                    // Show card with error message
                    const $card = self.$form.find('.kab-selected-therapist-card');
                    if ($card.length) {
                        $card.show();
                        $card.find('.kab-therapist-name').text('Error loading therapist');
                    }
                }
            });
        }
        
        /**
         * Display therapist card
         */
        displayTherapistCard(therapist) {
            const self = this;
            const $card = this.$form.find('.kab-selected-therapist-card');
            
            if (!$card.length) {
                console.error('Therapist card element not found');
                return;
            }
            
            // Show the card
            $card.show();
            
            const imageUrl = therapist.image_url || therapist.profile_image || therapist.image || '';
            const therapistName = therapist.name || '';
            const therapistTitle = therapist.title || '';
            
            // Set image
            const $img = $card.find('.kab-therapist-img');
            if (imageUrl) {
                $img.attr('src', imageUrl).attr('alt', therapistName);
            } else {
                // Use a placeholder if no image
                $img.attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gSW1hZ2U8L3RleHQ+PC9zdmc+').attr('alt', therapistName);
            }
            
            // Set name and title
            $card.find('.kab-therapist-name').text(therapistName);
            $card.find('.kab-therapist-title').text(therapistTitle);
        }
        
        /**
         * Load services for the therapist
         */
        loadServices() {
            const self = this;
            const $servicesList = this.$form.find('.kab-services-list');
            
            $servicesList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_services_for_therapist',
                    therapist_id: this.therapistId,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.services) {
                        // Store services data for later use (including prices)
                        self.services = response.data.services;
                        
                        let html = '';
                        
                        $.each(response.data.services, function(index, service) {
                            html += `
                                <div class="kab-service-item" data-service-id="${service.id}">
                                    <div class="kab-service-info">
                                        <h4>${service.name}</h4>
                                    </div>
                                </div>
                            `;
                        });
                        
                        $servicesList.html(html);
                    } else {
                        $servicesList.html('<div class="kab-no-data">' + (response.data?.message || kab_vars.no_services) + '</div>');
                    }
                },
                error: function() {
                    $servicesList.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                }
            });
        }
        
        /**
         * Load locations for therapist and service
         */
        loadCategories() {
            const self = this;
            const $categoriesList = this.$form.find('.kab-categories-list');
            
            $categoriesList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_locations_for_therapist_service',
                    service_id: this.serviceId,
                    therapist_id: this.therapistId,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.locations) {
                        let html = '';
                        
                        $.each(response.data.locations, function(index, location) {
                            html += `
                                <div class="kab-category-item" data-location-id="${location.id}">
                                    <div class="kab-category-name">${location.location_name}</div>
                                </div>
                            `;
                        });
                        
                        $categoriesList.html(html);
                    } else {
                        $categoriesList.html('<div class="kab-no-data">' + (response.data?.message || 'No locations available for this therapist and service combination.') + '</div>');
                    }
                },
                error: function() {
                    $categoriesList.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                }
            });
        }
        
        /**
         * Load date picker
         */
        loadDatePicker() {
            const self = this;
            const $datePicker = this.$form.find('.kab-date-picker');
            const $timeSlots = this.$form.find('.kab-time-slots');
            
            $datePicker.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            $timeSlots.html('');
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_available_dates',
                    service_id: this.serviceId,
                    specialist_id: this.therapistId,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.dates && response.data.dates.length > 0) {
                        // Create date picker
                        const today = new Date();
                        const currentMonth = today.getMonth();
                        const currentYear = today.getFullYear();
                        
                        $datePicker.html(self.createCalendar(currentMonth, currentYear, response.data.dates));
                        
                        // Add event handlers for month navigation
                        self.initMonthNavigation(response.data.dates);
                        
                        // If we have a selected date and time slots from the response
                        if (response.data.selected_date) {
                            self.selectedDate = response.data.selected_date;
                            const $selectedDateElement = $datePicker.find(`.kab-day[data-date="${response.data.selected_date}"]`);
                            if ($selectedDateElement.length) {
                                $selectedDateElement.addClass('selected');
                            }
                            
                            if (response.data.html_booking_slot) {
                                $timeSlots.html(response.data.html_booking_slot);
                                
                                if ($timeSlots.find('.kab-time-slot').length > 0) {
                                    self.$form.find('.kab-form-step[data-step="3"] .kab-next-btn').prop('disabled', false);
                                }
                            }
                        } else {
                            if (response.data.dates.length > 0) {
                                const sortedDates = [...response.data.dates].sort();
                                const todayStr = new Date().toISOString().split('T')[0];
                                
                                let nearestDate = sortedDates[0];
                                for (const date of sortedDates) {
                                    if (date >= todayStr) {
                                        nearestDate = date;
                                        break;
                                    }
                                }
                                
                                self.selectDate(nearestDate);
                            }
                        }
                    } else {
                        $datePicker.html('<div class="kab-no-data">No available dates found for this therapist. Please select a different therapist.</div>');
                        $timeSlots.html('');
                    }
                },
                error: function(xhr, status, error) {
                    $datePicker.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                    $timeSlots.html('');
                }
            });
        }
        
        /**
         * Initialize month navigation
         */
        initMonthNavigation(availableDates) {
            const self = this;
            const $datePicker = this.$form.find('.kab-date-picker');
            
            $datePicker.on('click', '.kab-month-prev, .kab-month-next', function() {
                const month = parseInt($(this).data('month'));
                const year = parseInt($(this).data('year'));
                
                $datePicker.html(self.createCalendar(month, year, availableDates));
                self.initMonthNavigation(availableDates);
            });
        }
        
        /**
         * Create calendar (Monday-first week)
         */
        createCalendar(month, year, availableDates) {
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            
            const prevMonth = month === 0 ? 11 : month - 1;
            const prevYear = month === 0 ? year - 1 : year;
            const nextMonth = month === 11 ? 0 : month + 1;
            const nextYear = month === 11 ? year + 1 : year;
            
            let html = `
                <div class="kab-calendar">
                    <div class="kab-month-header">
                        <button class="kab-month-prev" data-month="${prevMonth}" data-year="${prevYear}">&lt;</button>
                        <div class="kab-month-title">${monthNames[month]} ${year}</div>
                        <button class="kab-month-next" data-month="${nextMonth}" data-year="${nextYear}">&gt;</button>
                    </div>
                    <div class="kab-weekdays">
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                        <div>Sun</div>
                    </div>
                    <div class="kab-days">
            `;
            
            // Get first day of month (0 = Sunday, 1 = Monday, etc.)
            // Adjust for Monday as first day: 0 (Sun) -> 6, 1 (Mon) -> 0, 2 (Tue) -> 1, etc.
            const firstDayOfWeek = new Date(year, month, 1).getDay();
            const firstDay = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1; // Convert to Monday-first (0-6)
            
            // Get last day of month
            const lastDay = new Date(year, month + 1, 0).getDate();
            
            // Add empty cells for days before first day of month
            for (let i = 0; i < firstDay; i++) {
                html += '<div class="kab-day empty"></div>';
            }
            
            const today = new Date();
            const currentDate = today.getDate();
            const currentMonth = today.getMonth();
            const currentYear = today.getFullYear();
            
            for (let day = 1; day <= lastDay; day++) {
                const date = `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
                const isAvailable = availableDates.includes(date);
                const isToday = day === currentDate && month === currentMonth && year === currentYear;
                
                html += `
                    <div class="kab-day ${isAvailable ? '' : 'disabled'} ${isToday ? 'today' : ''}" 
                        data-date="${date}">
                        ${day}
                    </div>
                `;
            }
            
            html += '</div></div>';
            
            return html;
        }
        
        /**
         * Go to next step
         */
        nextStep() {
            if (this.currentStep < this.maxStep) {
                this.goToStep(this.currentStep + 1);
            }
        }
        
        /**
         * Go to previous step
         */
        prevStep() {
            if (this.currentStep > 1) {
                this.goToStep(this.currentStep - 1);
            }
        }
        
        /**
         * Go to specific step
         */
        goToStep(step) {
            // Hide all steps
            this.$form.find('.kab-form-step').hide();
            
            // Show the target step
            this.$form.find(`.kab-form-step[data-step="${step}"]`).show();
            
            // Update progress
            const progressPercentage = (step / this.maxStep) * 100;
            this.$form.find('.kab-progress-text').text(`${step}/${this.maxStep}`);
            this.$form.find('.kab-progress-fill').css('width', progressPercentage + '%');
            
            // Update current step
            this.currentStep = step;
            
            // Handle step-specific actions
            this.handleStepChange(step);
        }
        
        /**
         * Handle step change actions
         */
        handleStepChange(step) {
            switch (step) {
                case 2:
                    this.loadCategories();
                    break;
                case 3:
                    this.loadDatePicker();
                    break;
                case 4:
                    this.updateBookingSummary();
                    break;
                case 5:
                    // Confirmation step - handled by submitBooking
                    break;
            }
        }
        
        /**
         * Select a service
         */
        selectService(serviceId, autoAdvance = true) {
            this.serviceId = serviceId;
            
            // Update UI
            this.$form.find('.kab-service-item').removeClass('selected');
            this.$form.find(`.kab-service-item[data-service-id="${serviceId}"]`).addClass('selected');
            
            // Enable next button
            this.$form.find('.kab-form-step[data-step="1"] .kab-next-btn').prop('disabled', false);
            
            // Automatically proceed to next step if autoAdvance is true
            if (autoAdvance) {
                setTimeout(() => {
                    this.nextStep();
                }, 300);
            }
        }
        
        /**
         * Select a location
         */
        selectLocation(locationId) {
            this.locationId = locationId;
            
            // Update UI
            this.$form.find('.kab-category-item').removeClass('selected');
            this.$form.find(`.kab-category-item[data-location-id="${locationId}"]`).addClass('selected');
            
            // Enable next button
            this.$form.find('.kab-form-step[data-step="2"] .kab-next-btn').prop('disabled', false);
            
            // Automatically proceed to next step
            setTimeout(() => {
                this.nextStep();
            }, 300);
        }
        
        /**
         * Select a date
         */
        selectDate(date) {
            this.selectedDate = date;
            
            // Update UI
            this.$form.find('.kab-day').removeClass('selected');
            this.$form.find(`.kab-day[data-date="${date}"]`).addClass('selected');
            
            // Load time slots
            this.loadTimeSlots(date);
        }
        
        /**
         * Load time slots
         */
        loadTimeSlots(date) {
            const self = this;
            const $timeSlots = this.$form.find('.kab-time-slots');
            
            $timeSlots.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            this.selectedDate = date;
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_timeslots',
                    service_id: this.serviceId,
                    specialist_id: this.therapistId,
                    from_date: date,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    $timeSlots.html(response);
                    
                    if ($timeSlots.find('.kab-time-slot').length > 0) {
                        self.$form.find('.kab-form-step[data-step="3"] .kab-next-btn').prop('disabled', false);
                    } else {
                        self.$form.find('.kab-form-step[data-step="3"] .kab-next-btn').prop('disabled', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching time slots:', error);
                    $timeSlots.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                    self.$form.find('.kab-form-step[data-step="3"] .kab-next-btn').prop('disabled', true);
                }
            });
        }
        
        /**
         * Select a time slot
         */
        selectTimeSlot(timeSlot) {
            this.selectedTime = timeSlot;
            
            // Update UI
            this.$form.find('.kab-time-slot').removeClass('selected');
            this.$form.find(`.kab-time-slot[data-timeslot="${timeSlot}"]`).addClass('selected');
            
            // Enable next button
            this.$form.find('.kab-form-step[data-step="3"] .kab-next-btn').prop('disabled', false);
            
            // Auto-advance to next step after selecting time slot
            setTimeout(() => {
                this.nextStep();
            }, 300);
        }
        
        /**
         * Update booking summary
         */
        updateBookingSummary() {
            // Get service name
            let serviceName = '';
            if (this.serviceId) {
                const $selectedService = this.$form.find(`.kab-service-item[data-service-id="${this.serviceId}"]`);
                if ($selectedService.length) {
                    serviceName = $selectedService.find('.kab-service-info h4').text();
                }
            }
            
            // Get therapist name
            let therapistName = '';
            if (this.therapist) {
                therapistName = this.therapist.name;
            }
            
            // Get location name
            let locationName = '';
            if (this.locationId) {
                const $selectedLocation = this.$form.find(`.kab-category-item[data-location-id="${this.locationId}"]`);
                if ($selectedLocation.length) {
                    locationName = $selectedLocation.find('.kab-category-name').text();
                }
            }
            
            // Format date
            let formattedDate = '';
            if (this.selectedDate) {
                const dateObj = new Date(this.selectedDate);
                formattedDate = dateObj.toLocaleDateString('nb-NO', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            }
            
            // Format time
            let formattedTime = '';
            if (this.selectedTime) {
                const $selectedTimeSlot = this.$form.find(`.kab-time-slot[data-timeslot="${this.selectedTime}"]`);
                if ($selectedTimeSlot.length) {
                    formattedTime = $selectedTimeSlot.text().trim();
                }
                
                if (!formattedTime || formattedTime.length === 0 || !/^\d{1,2}:\d{2}$/.test(formattedTime)) {
                    if (this.selectedTime.includes('T')) {
                        const timePart = this.selectedTime.split('T')[1];
                        if (timePart) {
                            formattedTime = timePart.substring(0, 5);
                        }
                    } else if (this.selectedTime.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/)) {
                        formattedTime = this.selectedTime.split('T')[1].substring(0, 5);
                    } else if (this.selectedTime.match(/^\d{2}:\d{2}/)) {
                        formattedTime = this.selectedTime.substring(0, 5);
                    }
                }
            }
            
            // Get price from stored service data
            let price = '';
            if (this.serviceId && this.services.length > 0) {
                const selectedService = this.services.find(service => service.id === this.serviceId);
                if (selectedService && selectedService.price) {
                    price = selectedService.price;
                    if (price && !price.includes(',')) {
                        price = price + ',-';
                    }
                }
            }
            
            // Update summary fields
            this.$form.find('.kab-summary-therapist').text(therapistName);
            this.$form.find('.kab-summary-service').text(serviceName);
            this.$form.find('.kab-summary-location').text(locationName);
            this.$form.find('.kab-summary-date').text(formattedDate);
            this.$form.find('.kab-summary-time').text(formattedTime);
            this.$form.find('.kab-summary-price').text(price);
        }
        
        /**
         * Submit booking
         */
        submitBooking() {
            const self = this;
            const $confirmationMessage = this.$form.find('.kab-confirmation-message');
            const $loading = $confirmationMessage.find('.kab-loading');
            const $success = $confirmationMessage.find('.kab-success');
            const $error = $confirmationMessage.find('.kab-error');
            
            // Get form data
            const firstName = this.$form.find('#kab-first-name-tf').val();
            const email = this.$form.find('#kab-email-tf').val();
            const phone = this.$form.find('#kab-phone-tf').val();
            
            // Validate form data
            if (!firstName || !email || !phone) {
                $loading.hide();
                $error.show();
                $error.find('.kab-error-message').text('Please fill in all required fields.');
                return;
            }
            
            // Show loading
            $loading.show();
            $success.hide();
            $error.hide();
            
            // Get reCAPTCHA token if enabled
            let recaptchaToken = '';
            
            if (typeof grecaptcha !== 'undefined' && kab_vars.recaptcha_site_key) {
                grecaptcha.ready(function() {
                    grecaptcha.execute(kab_vars.recaptcha_site_key, {action: 'submit'}).then(function(token) {
                        recaptchaToken = token;
                        self.processBooking(firstName, email, phone, recaptchaToken);
                    });
                });
            } else {
                this.processBooking(firstName, email, phone, recaptchaToken);
            }
        }
        
        /**
         * Process booking
         */
        processBooking(firstName, email, phone, recaptchaToken) {
            const self = this;
            const $confirmationMessage = this.$form.find('.kab-confirmation-message');
            const $loading = $confirmationMessage.find('.kab-loading');
            const $success = $confirmationMessage.find('.kab-success');
            const $error = $confirmationMessage.find('.kab-error');
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_create_booking',
                    service_id: this.serviceId,
                    specialist_id: this.therapistId,
                    timeslot: this.selectedTime,
                    first_name: firstName,
                    last_name: '', // Not required in this form
                    email: email,
                    phone: phone,
                    notes: '',
                    recaptcha_token: recaptchaToken,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    $loading.hide();
                    
                    if (response.success) {
                        $success.show();
                        $success.find('.kab-booking-message').html(response.data.message || 'Booking created successfully.');
                        
                        if (response.data.redirect_url) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url;
                            }, 2000);
                        }
                    } else {
                        $error.show();
                        $error.find('.kab-error-message').text(response.data.message || kab_vars.error);
                    }
                },
                error: function() {
                    $loading.hide();
                    $error.show();
                    $error.find('.kab-error-message').text(kab_vars.error);
                }
            });
        }
    }
    
    /**
     * Initialize therapist-first booking forms when DOM is ready
     */
    $(document).ready(function() {
        // Initialize therapist-first booking forms
        $('.kab-booking-form.kab-therapist-first').each(function() {
            new KABTherapistFirstBookingForm($(this));
        });
    });

})(jQuery);

