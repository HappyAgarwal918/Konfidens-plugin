/**
 * Public JavaScript for Konfidens Appointment Booking - Service & Therapist First Flow
 * Step order: Service -> Therapist -> Location -> Date&Time -> Personal Details -> Confirmation
 */

(function($) {
    'use strict';

    /**
     * Booking Form Class for Service & Therapist First Flow
     */
    class KABServiceTherapistFirstBookingForm {
        /**
         * Initialize the service & therapist first booking form
         * 
         * @param {jQuery} $form The booking form element
         */
        constructor($form) {
            this.$form = $form;
            this.currentStep = 0; // Start at 0, will be set to 1 when services load
            this.maxStep = 4; // Steps 1-4 are counted (Therapist, Location, Date&Time, Personal Details), step 5 (confirmation) is not
            
            // Service is always required and pre-selected
            this.serviceId = $form.data('service-id') || '';
            if (!this.serviceId) {
                return;
            }
            
            this.specialistId = $form.data('specialist-id') || '';
            this.locationId = '';
            this.selectedDate = '';
            this.selectedTime = '';
            this.services = []; // Store services data with prices
            this.locations = []; // Store locations data
            this.specialists = []; // Store specialists data for random selection
            this.isLoadingSpecialists = false; // Flag to prevent multiple simultaneous specialist loads
            
            this.init();
        }
        
        /**
         * Initialize the form
         */
        init() {
            // Hide all steps initially
            this.$form.find('.kab-form-step').hide();
            
            // Show step 1 immediately (don't wait for data to load)
            this.goToStep(1);
            
            // Initialize events first
            this.initEvents();
            
            // Show service card container immediately (even before data loads)
            const $serviceCard = this.$form.find('.kab-selected-service-card');
            if ($serviceCard.length) {
                $serviceCard.show();
            }
            
            // Load services in background (service is always pre-selected from shortcode)
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
            
            // Specialist selection - clicking card just selects, doesn't auto-advance
            this.$form.on('click', '.kab-specialist-card', function(e) {
                // Don't trigger if clicking the button inside the card
                if ($(e.target).closest('.kab-select-specialist-btn').length) {
                    return;
                }
                const specialistId = $(this).data('specialist-id');
                self.selectSpecialist(specialistId);
                // Don't auto-advance - user must click button or next button
            });
            
            // Random specialist selection button
            this.$form.on('click', '.kab-random-specialist-btn', function() {
                self.selectRandomSpecialist();
            });
            
            // Category selection
            this.$form.on('click', '.kab-category-item', function() {
                const locationId = $(this).data('location-id');
                self.selectLocation(locationId);
            });
            
            // Day selection - use event delegation to handle dynamically rendered calendar
            this.$form.on('click', '.kab-day:not(.empty):not(.disabled)', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // Try both data() and attr() to ensure we get the date
                const date = $(this).data('date') || $(this).attr('data-date');
                if (date) {
                    self.selectDate(date);
                }
            });
            
            // Time slot selection
            this.$form.on('click', '.kab-time-slot:not(.disabled)', function() {
                const timeSlot = $(this).data('timeslot');
                self.selectTimeSlot(timeSlot);
            });
            
            // Navigation buttons
            this.$form.on('click', '.kab-prev-btn', function() {
                self.prevStep();
            });
            
            // Form submission
            this.$form.on('click', '.kab-submit-btn', function(e) {
                e.preventDefault();
                
                // Validate form fields first
                const firstName = self.$form.find('#kab-first-name-stf').val();
                const email = self.$form.find('#kab-email-stf').val();
                const phone = self.$form.find('#kab-phone-stf').val();
                const terms = self.$form.find('#kab-terms-stf').is(':checked');
                
                // Check if all required fields are filled
                if (!firstName || !email || !phone) {
                    alert('Please fill in all required fields.');
                    return false;
                }
                
                // Check if terms are accepted
                if (!terms) {
                    alert('Please accept the terms and conditions to continue.');
                    return false;
                }
                
                // Validate email format
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    alert('Please enter a valid email address.');
                    return false;
                }
                
                // If validation passes, go to confirmation step and submit
                self.goToStep(5);
                self.submitBooking();
                return false;
            });
            
            // Form submission (fallback)
            this.$form.on('submit', function(e) {
                e.preventDefault();
                self.goToStep(5);
                self.submitBooking();
                return false;
            });
            
            // Try again button
            this.$form.on('click', '.kab-try-again-btn', function() {
                self.goToStep(4);
            });
            
            // Book new appointment button
            this.$form.on('click', '.kab-book-new-btn', function() {
                self.resetForm();
            });
            
            // Close window button
            this.$form.on('click', '.kab-close-window-btn', function() {
                const $popup = self.$form.closest('.kab-popup');
                if ($popup.length) {
                    $popup.fadeOut(300);
                } else {
                    window.location.href = '/';
                }
            });
        }
        
        /**
         * Load services
         */
        loadServices() {
            const self = this;
            const $servicesList = this.$form.find('.kab-services-list');
            
            // Only show loading if list is empty
            if ($servicesList.is(':empty') || $servicesList.find('.kab-service-item').length === 0) {
                $servicesList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            }
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_services',
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.services) {
                        self.services = response.data.services;
                        
                        // Build HTML more efficiently using array map and join
                        const html = response.data.services.map(service => {
                            const isSelected = self.serviceId == service.id ? 'selected' : '';
                            return `<div class="kab-service-item ${isSelected}" data-service-id="${service.id}">
                                <div class="kab-service-info">
                                    <p>${service.name}</p>
                                </div>
                            </div>`;
                        }).join('');
                        
                        $servicesList.html(html);
                        
                        // Service is always pre-selected, mark it and display service card
                        if (self.serviceId) {
                            self.$form.find(`.kab-service-item[data-service-id="${self.serviceId}"]`).addClass('selected');
                            
                            // Display service card
                            const selectedService = response.data.services.find(s => s.id === self.serviceId);
                            if (selectedService) {
                                self.displayServiceCard(selectedService);
                            }
                        }
                        
                        // Step 1 is already shown from init(), no need to navigate again
                        // Just ensure specialists are loaded if we're on step 1
                        if (self.currentStep === 1) {
                            self.loadSpecialists();
                        }
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
         * Display service card
         */
        displayServiceCard(service) {
            const $card = this.$form.find('.kab-selected-service-card');
            
            if (!$card.length) {
                return;
            }
            
            // Show the card
            $card.show();
            
            const serviceName = service.name || '';
            
            // Set service name
            $card.find('.kab-service-name').text(serviceName);
        }
        
        /**
         * Load specialists
         */
        loadSpecialists() {
            const self = this;
            const $specialistsList = this.$form.find('.kab-specialists-list');
            
            // Prevent multiple simultaneous loads
            if (this.isLoadingSpecialists) {
                return;
            }
            this.isLoadingSpecialists = true;
            
            $specialistsList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_specialists',
                    service_id: this.serviceId,
                    // Don't send specialist_id when loading for display - we want all specialists
                    // specialist_id is only used when pre-selecting from button
                    specialist_id: '',
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.specialists && response.data.specialists.length > 0) {
                        // Store specialists for random selection
                        self.specialists = response.data.specialists;
                        
                        // Build HTML more efficiently using array map
                        const cardsHtml = response.data.specialists.map((specialist, index) => {
                            // Extract tags from specialist data
                            let tagsHtml = '';
                            const tags = self.getSpecialistTags(specialist);
                            
                            if (tags && tags.length > 0) {
                                tagsHtml = '<div class="kab-therapist-tags">' +
                                    tags.map(tag => `<span class="kab-therapist-tag">${tag}</span>`).join('') +
                                    '</div>';
                            }
                            
                            // Create truncated description with "Les mer" link if description is long
                            let descriptionHtml = '';
                            if (specialist.description) {
                                const maxLength = 150;
                                const shortDesc = specialist.description.length > maxLength 
                                    ? specialist.description.substring(0, maxLength) + '...' 
                                    : specialist.description;
                                
                                descriptionHtml = `<p class="kab-specialist-description">${shortDesc}</p>` +
                                    (specialist.description.length > maxLength ? '<a href="#" class="kab-read-more">Les mer</a>' : '');
                            }
                            
                            // Hide all cards except the first one initially to prevent blinking
                            const displayStyle = index === 0 ? '' : 'style="display: none;"';
                            return `
                                <div class="kab-specialist-card" data-specialist-id="${specialist.id}" data-index="${index}" ${displayStyle}>
                                    <div class="kab-specialist-card-inner">
                                        <div class="kab-specialist-image">
                                            <img src="${specialist.image_url || specialist.profile_image || specialist.image || 'https://via.placeholder.com/100'}" alt="${specialist.name}">
                                        </div>
                                        <div class="kab-specialist-info">
                                            <h4 class="kab-specialist-name">${specialist.name}</h4>
                                            ${specialist.title ? `<div class="kab-specialist-title">${specialist.title}</div>` : ''}
                                            ${descriptionHtml}
                                        </div>
                                    </div>
                                    <div class="kab-specialist-other-info">
                                        ${tagsHtml}
                                        <button class="kab-select-specialist-btn" data-specialist-id="${specialist.id}">Velg denne terapeuten</button>
                                    </div>
                                </div>
                            `;
                        }).join('');
                        
                        // Build complete slider HTML
                        let sliderHtml = '<div class="kab-specialists-slider">' +
                            '<div class="kab-slider-arrow kab-slider-prev"><img src="' + kab_vars.plugin_url + 'frontend/assets/images/arrow-left.png" alt="Flere terapeuter"></div>' +
                            '<div class="kab-slider-arrow kab-slider-next"><img src="' + kab_vars.plugin_url + 'frontend/assets/images/arrow-right.png" alt="Flere terapeuter"></div>' +
                            cardsHtml +
                            '</div>';
                        
                        // Render HTML
                        $specialistsList.html(sliderHtml);
                        
                        // Initialize slider immediately using requestAnimationFrame for smooth rendering
                        // This ensures DOM is ready but doesn't cause visible delay
                        requestAnimationFrame(() => {
                            self.initSpecialistSlider();
                            
                            // If we have a selected specialist (from user selection or pre-selection), visually select it
                            // This handles both pre-selected specialists (from button) and user-selected ones (when navigating back)
                            if (self.specialistId) {
                                // Check if the selected specialist exists in the loaded list
                                const selectedSpecialist = response.data.specialists.find(spec => spec.id === self.specialistId);
                                if (selectedSpecialist) {
                                    // Visually select it but don't auto-advance
                                    self.$form.find('.kab-specialist-card').removeClass('selected');
                                    self.$form.find(`.kab-specialist-card[data-specialist-id="${self.specialistId}"]`).addClass('selected');
                                    
                                    // If it's not the first card, show it in the slider
                                    const $selectedCard = self.$form.find(`.kab-specialist-card[data-specialist-id="${self.specialistId}"]`);
                                    const cardIndex = $selectedCard.data('index') || 0;
                                    if (cardIndex > 0) {
                                        // Update slider to show selected card
                                        const $slider = self.$form.find('.kab-specialists-slider');
                                        const $cards = $slider.find('.kab-specialist-card');
                                        $cards.hide();
                                        $cards.eq(cardIndex).show();
                                    }
                                }
                            }
                            
                            self.isLoadingSpecialists = false;
                        });
                    } else {
                        $specialistsList.html('<div class="kab-no-data">' + kab_vars.no_specialists + '</div>');
                        self.isLoadingSpecialists = false;
                    }
                },
                error: function() {
                    $specialistsList.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                    self.isLoadingSpecialists = false;
                }
            });
        }
        
        /**
         * Extract tags from specialist data
         */
        /**
         * Extract tags from specialist data
         * Only returns tags stored in the database (admin-managed)
         * 
         * @param {Object} specialist The specialist data object
         * @returns {Array} Array of tag strings
         */
        getSpecialistTags(specialist) {
            // Only return tags from database (admin-managed)
            if (specialist.stored_tags && Array.isArray(specialist.stored_tags) && specialist.stored_tags.length > 0) {
                return specialist.stored_tags;
            }
            
            // Return empty array if no tags in database
            return [];
        }
        
        /**
         * Initialize specialist slider functionality
         */
        initSpecialistSlider() {
            const self = this;
            const $slider = this.$form.find('.kab-specialists-slider');
            const $cards = $slider.find('.kab-specialist-card');
            const $prevBtn = this.$form.find('.kab-slider-prev');
            const $nextBtn = this.$form.find('.kab-slider-next');
            
            if ($cards.length === 0) return;
            
            let currentIndex = 0;
            const totalSlides = $cards.length;
            
            // Cards are already hidden except the first one (set in HTML during rendering)
            // Just verify the first one is visible - don't manipulate all cards to avoid flickering
            // Only ensure first card is visible if somehow it got hidden
            const $firstCard = $cards.eq(currentIndex);
            if ($firstCard.is(':hidden')) {
                $firstCard.show();
            }
            
            const updateSlider = () => {
                $cards.fadeOut(200).promise().done(function() {
                    $cards.eq(currentIndex).fadeIn(200);
                });
            };
            
            $nextBtn.on('click', function() {
                currentIndex = (currentIndex + 1) % totalSlides;
                updateSlider();
            });
            
            $prevBtn.on('click', function() {
                currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
                updateSlider();
            });
            
            // Handle select specialist button clicks - only advance when button is explicitly clicked by user
            this.$form.off('click', '.kab-select-specialist-btn').on('click', '.kab-select-specialist-btn', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Prevent event bubbling to card click
                const specialistId = $(this).data('specialist-id');
                if (!specialistId) return;
                // Pass autoAdvance=true to advance to next step ONLY when user clicks button
                self.selectSpecialist(specialistId, true);
            });
            
            // Handle "Les mer" link clicks
            this.$form.off('click', '.kab-read-more').on('click', '.kab-read-more', function(e) {
                e.preventDefault();
                const $link = $(this);
                const $description = $link.prev('.kab-specialist-description');
                const $card = $link.closest('.kab-specialist-card');
                const specialistId = $card.data('specialist-id');
                
                const specialist = self.specialists.find(s => s.id === specialistId);
                
                if (specialist && specialist.description) {
                    if ($link.hasClass('expanded')) {
                        const maxLength = 150;
                        const shortDesc = specialist.description.length > maxLength 
                            ? specialist.description.substring(0, maxLength) + '...' 
                            : specialist.description;
                        $description.text(shortDesc);
                        $link.text('Les mer').removeClass('expanded');
                    } else {
                        $description.text(specialist.description);
                        $link.text('Vis mindre').addClass('expanded');
                    }
                }
            });
        }
        
        /**
         * Select a service
         * @param {string} serviceId The service ID to select
         * @param {boolean} autoAdvance Whether to automatically advance to next step (default: true)
         */
        selectService(serviceId, autoAdvance = true) {
            this.serviceId = serviceId;
            
            // Update UI
            this.$form.find('.kab-service-item').removeClass('selected');
            this.$form.find(`.kab-service-item[data-service-id="${serviceId}"]`).addClass('selected');
            
            // Automatically proceed to next step (step 1 - specialist) if autoAdvance is true
            if (autoAdvance) {
                setTimeout(() => {
                    this.goToStep(1);
                }, 300);
            }
        }
        
        /**
         * Select a specialist
         * @param {string} specialistId Specialist ID
         * @param {boolean} autoAdvance Whether to automatically advance to next step (default: false)
         */
        selectSpecialist(specialistId, autoAdvance = false) {
            if (!specialistId) return;
            
            this.specialistId = specialistId;
            
            // Update UI
            this.$form.find('.kab-specialist-card').removeClass('selected');
            this.$form.find(`.kab-specialist-card[data-specialist-id="${specialistId}"]`).addClass('selected');
            
            // Enable the random specialist button (which acts as next button)
            // Note: There's no .kab-next-btn in step 1, only .kab-random-specialist-btn
            
            // ONLY auto-advance if explicitly requested (user clicked the "Velg denne terapeuten" button)
            // Never auto-advance when specialist is pre-selected or card is clicked
            if (autoAdvance && this.currentStep === 1) {
                // Double-check we're on step 1 before advancing
                setTimeout(() => {
                    if (this.currentStep === 1) {
                        this.goToStep(2);
                    }
                }, 300);
            }
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
                    therapist_id: this.specialistId,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.locations) {
                        self.locations = response.data.locations;
                        
                        let html = '';
                        $.each(response.data.locations, function(index, location) {
                            html += `
                                <div class="kab-category-item" data-location-id="${location.id}">
                                    <p class="kab-category-name">${location.location_name}</p>
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
                    specialist_id: this.specialistId,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.dates && response.data.dates.length > 0) {
                        const today = new Date();
                        const currentMonth = today.getMonth();
                        const currentYear = today.getFullYear();
                        
                        $datePicker.html(self.createCalendar(currentMonth, currentYear, response.data.dates));
                        self.initMonthNavigation(response.data.dates);
                        
                        if (response.data.selected_date) {
                            self.selectedDate = response.data.selected_date;
                            const $selectedDateElement = $datePicker.find(`.kab-day[data-date="${response.data.selected_date}"]`);
                            if ($selectedDateElement.length) {
                                $selectedDateElement.addClass('selected');
                            }
                            
                            if (response.data.html_booking_slot) {
                                $timeSlots.html(response.data.html_booking_slot);
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
            
            // Remove existing handlers to prevent duplicates
            $datePicker.off('click', '.kab-month-prev, .kab-month-next');
            
            $datePicker.on('click', '.kab-month-prev, .kab-month-next', function() {
                const month = parseInt($(this).data('month'));
                const year = parseInt($(this).data('year'));
                
                $datePicker.html(self.createCalendar(month, year, availableDates));
                self.initMonthNavigation(availableDates);
                
                // If we have a selected date, maintain the selection and reload time slots
                if (self.selectedDate) {
                    const $selectedDateElement = $datePicker.find(`.kab-day[data-date="${self.selectedDate}"]`);
                    if ($selectedDateElement.length) {
                        $selectedDateElement.addClass('selected');
                        // Reload time slots for the selected date
                        self.loadTimeSlots(self.selectedDate);
                    }
                }
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
            
            const firstDayOfWeek = new Date(year, month, 1).getDay();
            const firstDay = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1;
            const lastDay = new Date(year, month + 1, 0).getDate();
            
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
            
            // Hide progress bar on confirmation step
            if (step === 5) {
                this.$form.find('.kab-form-progress').hide();
            } else {
                this.$form.find('.kab-form-progress').show();
                const progressPercentage = (step / this.maxStep) * 100;
                this.$form.find('.kab-progress-text').text(`${step}/${this.maxStep}`);
                this.$form.find('.kab-progress-fill').css('width', progressPercentage + '%');
            }
            
            // Hide/show back button based on step
            if (step === 1) {
                // Hide back button on first step (therapist selection)
                this.$form.find('.kab-form-step[data-step="1"] .kab-prev-btn').hide();
            } else {
                // Show back button on all other steps
                this.$form.find('.kab-form-step .kab-prev-btn').show();
            }
            
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
                case 1:
                    this.loadSpecialists();
                    break;
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
                    // Confirmation step - submitBooking will be called separately
                    break;
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
            
            // Automatically proceed to next step (step 3 - date)
            setTimeout(() => {
                this.goToStep(3);
            }, 300);
        }
        
        /**
         * Select a date
         */
        selectDate(date) {
            if (!date) {
                return;
            }
            
            this.selectedDate = date;
            
            // Update UI
            this.$form.find('.kab-day').removeClass('selected');
            const $selectedDay = this.$form.find(`.kab-day[data-date="${date}"]`);
            if ($selectedDay.length) {
                $selectedDay.addClass('selected');
            }
            
            // Load time slots
            this.loadTimeSlots(date);
        }
        
        /**
         * Load time slots
         */
        loadTimeSlots(date) {
            const self = this;
            const $timeSlots = this.$form.find('.kab-time-slots');
            
            // Validate required data
            if (!this.serviceId) {
                $timeSlots.html('<div class="kab-no-data">Service is required.</div>');
                return;
            }
            
            if (!this.specialistId) {
                $timeSlots.html('<div class="kab-no-data">Please select a therapist first.</div>');
                return;
            }
            
            if (!date) {
                $timeSlots.html('<div class="kab-no-data">Please select a date.</div>');
                return;
            }
            
            $timeSlots.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            this.selectedDate = date;
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_timeslots',
                    service_id: this.serviceId,
                    specialist_id: this.specialistId,
                    from_date: date,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response) {
                        $timeSlots.html(response);
                    } else {
                        $timeSlots.html('<div class="kab-no-data">No time slots available for this date.</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $timeSlots.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
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
            
            // Auto-advance to next step
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
            if (this.serviceId && this.services.length > 0) {
                const selectedService = this.services.find(service => service.id == this.serviceId);
                if (selectedService && selectedService.name) {
                    serviceName = selectedService.name;
                }
            }
            
            // Get therapist name
            let therapistName = '';
            if (this.specialistId) {
                const $selectedTherapist = this.$form.find(`.kab-specialist-card[data-specialist-id="${this.specialistId}"]`);
                if ($selectedTherapist.length) {
                    therapistName = $selectedTherapist.find('.kab-specialist-name').text();
                }
            }
            
            // Get location name
            let locationName = '';
            if (this.locationId && this.locations.length > 0) {
                const selectedLocation = this.locations.find(location => location.id == this.locationId);
                if (selectedLocation && selectedLocation.location_name) {
                    locationName = selectedLocation.location_name;
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
                const selectedService = this.services.find(service => service.id == this.serviceId);
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
         * Reset form to initial state
         */
        resetForm() {
            // Reset all selections except serviceId and specialistId (they come from button)
            this.locationId = '';
            this.selectedDate = '';
            this.selectedTime = '';
            
            // Reset UI selections
            this.$form.find('.kab-service-item').removeClass('selected');
            this.$form.find('.kab-category-item').removeClass('selected');
            this.$form.find('.kab-specialist-card').removeClass('selected');
            this.$form.find('.kab-day').removeClass('selected');
            this.$form.find('.kab-time-slot').removeClass('selected');
            
            // Clear form fields
            this.$form.find('#kab-first-name-stf').val('');
            this.$form.find('#kab-email-stf').val('');
            this.$form.find('#kab-phone-stf').val('');
            this.$form.find('#kab-terms-stf').prop('checked', false);
            
            // Service is always pre-selected, so always start at step 1 (therapist)
            if (this.serviceId) {
                this.$form.find(`.kab-service-item[data-service-id="${this.serviceId}"]`).addClass('selected');
            }
            this.goToStep(1);
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
            const firstName = this.$form.find('#kab-first-name-stf').val();
            const email = this.$form.find('#kab-email-stf').val();
            const phone = this.$form.find('#kab-phone-stf').val();
            
            // Validate form data
            if (!firstName || !email || !phone) {
                $loading.hide();
                $error.show();
                $error.find('.kab-error-message').text('Please fill in all required fields.');
                return;
            }
            
            // Validate that we have all booking data
            if (!this.serviceId || !this.specialistId || !this.selectedTime) {
                $loading.hide();
                $error.show();
                $error.find('.kab-error-message').text('Missing booking information. Please go back and complete all steps.');
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
                    specialist_id: this.specialistId,
                    location_id: this.locationId,
                    timeslot: this.selectedTime,
                    first_name: firstName,
                    last_name: '',
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
                        
                        // Build booking details for display
                        let bookingDetails = '';
                        if (response.data.booking_id) {
                            bookingDetails += `
                                <div class="kab-booking-info-item">
                                    <span class="kab-booking-info-label">Booking ID:</span>
                                    <span class="kab-booking-info-value">${response.data.booking_id}</span>
                                </div>
                            `;
                        }
                        $success.find('.kab-booking-details').html(bookingDetails);
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
        
        /**
         * Select a random specialist from the list
         */
        selectRandomSpecialist() {
            // Check if we have specialists loaded
            if (!this.specialists || this.specialists.length === 0) {
                return;
            }
            
            // Select a random specialist
            const randomIndex = Math.floor(Math.random() * this.specialists.length);
            const randomSpecialist = this.specialists[randomIndex];
            
            // Select the random specialist and auto-advance
            this.selectSpecialist(randomSpecialist.id, true);
        }
    }
    
    /**
     * Initialize service & therapist first booking forms when DOM is ready
     */
    $(document).ready(function() {
        // Initialize service & therapist first booking forms (skip forms in hidden popups - they'll be initialized when popup opens)
        $('.kab-booking-form.kab-service-therapist-first').not('.kab-popup .kab-booking-form').each(function() {
            const $form = $(this);
            const instance = new KABServiceTherapistFirstBookingForm($form);
            $form.data('kab-form-instance', instance);
        });
        
        // Initialize popup functionality
        $('.kab-button[data-popup-target]').click(function() {
            const $button = $(this);
            const popupId = $button.data('popup-target');
            const $popup = $('#' + popupId);
            
            // Get service_id and specialist_id from button
            const serviceId = $button.data('service-id') || '';
            const specialistId = $button.data('specialist-id') || '';
            
            // Show popup
            $popup.show();
            
            // Skip if this is a therapist-first form (handled by public-therapist-first.js)
            const $therapistForm = $popup.find('.kab-booking-form.kab-therapist-first');
            if ($therapistForm.length) {
                return; // Don't process therapist-first forms here
            }
            
            // Handle service & therapist first booking form
            const $form = $popup.find('.kab-booking-form.kab-service-therapist-first');
            if ($form.length) {
                // Apply data attributes from button if present
                if (serviceId) {
                    $form.attr('data-service-id', serviceId);
                }
                if (specialistId) {
                    $form.attr('data-specialist-id', specialistId);
                }
                
                // Get or create form instance
                let formInstance = $form.data('kab-form-instance');
                if (!formInstance) {
                    formInstance = new KABServiceTherapistFirstBookingForm($form);
                    $form.data('kab-form-instance', formInstance);
                } else {
                    // Reset form state when reopening popup
                    // But preserve serviceId and specialistId if they're being set
                    const currentServiceId = formInstance.serviceId;
                    const currentSpecialistId = formInstance.specialistId;
                    formInstance.resetForm();
                    // Restore serviceId and specialistId after reset
                    if (currentServiceId) formInstance.serviceId = currentServiceId;
                    if (currentSpecialistId) formInstance.specialistId = currentSpecialistId;
                }
                
                // Service is always required and pre-selected
                if (serviceId) {
                    formInstance.serviceId = serviceId;
                    // Update the data attribute
                    $form.attr('data-service-id', serviceId);
                    
                    // If services are already loaded, select directly and go to step 1
                    if (formInstance.services && formInstance.services.length > 0) {
                        const serviceExists = formInstance.services.some(s => s.id === serviceId);
                        if (serviceExists) {
                            // Select service visually (without auto-advance)
                            formInstance.selectService(serviceId, false);
                            // Go directly to step 1 (therapist) without showing step 0 (service)
                            setTimeout(() => {
                                formInstance.goToStep(1);
                            }, 50);
                        } else {
                            formInstance.loadServices();
                        }
                    } else {
                        // Services not loaded yet; load and auto-select
                        formInstance.loadServices();
                    }
                } else {
                    formInstance.goToStep(1);
                    if (!formInstance.services || formInstance.services.length === 0) {
                        formInstance.loadServices();
                    }
                }
            }
        });
        
        $('.kab-popup-close').click(function() {
            $(this).closest('.kab-popup').hide();
        });
        
        $(window).click(function(e) {
            if ($(e.target).hasClass('kab-popup-overlay')) {
                $('.kab-popup').hide();
            }
        });
    });

})(jQuery);

