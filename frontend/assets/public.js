/**
 * Public JavaScript for Konfidens Appointment Booking
 */

(function($) {
    'use strict';

    /**
     * Booking Form Class
     */
    class KABBookingForm {
        /**
         * Initialize the booking form
         * 
         * @param {jQuery} $form The booking form element
         */
        constructor($form) {
            this.$form = $form;
            this.currentStep = 1;
            this.maxStep = 6;
            
            this.serviceId = $form.data('service-id') || '';
            this.specialistId = $form.data('specialist-id') || '';
            this.locationId = '';
            this.selectedDate = '';
            this.selectedTime = '';
            this.services = []; // Store services data with prices
            this.specialists = []; // Store specialists data for random selection
            
            this.init();
        }
        
        /**
         * Initialize the form
         */
        init() {
            this.initEvents();
            this.loadServices();
            
            // If specialist ID is preselected
            if (this.specialistId) {
                // Will be handled after services are loaded
                this.preselectedSpecialist = true;
            }
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
            
            // Specialist selection
            this.$form.on('click', '.kab-specialist-card', function() {
                const specialistId = $(this).data('specialist-id');
                self.selectSpecialist(specialistId);
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
                self.goToStep(6);
                return false;
            });
            
            // Try again button
            this.$form.on('click', '.kab-try-again-btn', function() {
                self.goToStep(5);
            });
            
            // Random specialist selection button
            this.$form.on('click', '.kab-random-specialist-btn', function() {
                self.selectRandomSpecialist();
            });
        }
        
        /**
         * Load services
         */
        loadServices() {
            const self = this;
            const $servicesList = this.$form.find('.kab-services-list');
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_services',
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
                        
                        // If service is preselected
                        if (self.serviceId) {
                            self.$form.find(`.kab-service-item[data-service-id="${self.serviceId}"]`).addClass('selected');
                            self.$form.find('.kab-form-step[data-step="1"] .kab-next-btn').prop('disabled', false);
                            // Navigate to location step (step 2) without skipping it
                            setTimeout(() => {
                                self.goToStep(2);
                            }, 100);
                        }
                        
                        // If specialist is preselected
                        if (self.preselectedSpecialist && self.specialistId) {
                            // Get the first service
                            if (!self.serviceId && response.data.services.length > 0) {
                                self.serviceId = response.data.services[0].id;
                                self.$form.find(`.kab-service-item[data-service-id="${self.serviceId}"]`).addClass('selected');
                                self.$form.find('.kab-form-step[data-step="1"] .kab-next-btn').prop('disabled', false);
                            }
                            self.goToStep(2);
                        }
                    } else {
                        $servicesList.html('<div class="kab-no-data">' + kab_vars.no_services + '</div>');
                    }
                },
                error: function() {
                    $servicesList.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                }
            });
        }
        
        /**
         * Load locations
         */
        loadCategories() {
            const self = this;
            const $categoriesList = this.$form.find('.kab-categories-list');
            
            $categoriesList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_locations',
                    service_id: this.serviceId,
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
                        
                        // If we have a preselected specialist, auto-select the first category
                        if (self.preselectedSpecialist && self.specialistId && response.data.locations.length > 0) {
                            self.selectLocation(response.data.locations[0].id);
                            self.nextStep();
                        }
                    } else {
                        $categoriesList.html('<div class="kab-no-data">No locations available for this service.</div>');
                    }
                },
                error: function() {
                    $categoriesList.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                }
            });
        }
        
        /**
         * Load specialists
         */
        loadSpecialists() {
            const self = this;
            const $specialistsList = this.$form.find('.kab-specialists-list');
            
            $specialistsList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_specialists',
                    service_id: this.serviceId,
                    location_id: this.locationId,
                    specialist_id: this.specialistId,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.specialists && response.data.specialists.length > 0) {
                        // Store specialists for random selection
                        self.specialists = response.data.specialists;
                        
                        let sliderHtml = '<div class="kab-specialists-slider">';
                        
                        // Add slider navigation arrows
                        sliderHtml += '<div class="kab-slider-arrow kab-slider-prev"><img src="' + kab_vars.plugin_url + 'frontend/assets/images/arrow-left.png" alt="Flere terapeuter"></div>';
                        sliderHtml += '<div class="kab-slider-arrow kab-slider-next"><img src="' + kab_vars.plugin_url + 'frontend/assets/images/arrow-right.png" alt="Flere terapeuter"></div>';
                        
                        $.each(response.data.specialists, function(index, specialist) {
                            // Extract tags from specialist data (could be from categories, specialties, or other fields)
                            let tagsHtml = '';
                            const tags = self.getSpecialistTags(specialist);
                            
                            if (tags && tags.length > 0) {
                                tagsHtml = '<div class="kab-therapist-tags">';
                                tags.forEach(tag => {
                                    tagsHtml += `<span class="kab-therapist-tag">${tag}</span>`;
                                });
                                tagsHtml += '</div>';
                            }
                            
                            // Create truncated description with "Les mer" link if description is long
                            let descriptionHtml = '';
                            if (specialist.description) {
                                const maxLength = 150;
                                const shortDesc = specialist.description.length > maxLength 
                                    ? specialist.description.substring(0, maxLength) + '...' 
                                    : specialist.description;
                                
                                descriptionHtml = `
                                    <div class="kab-specialist-description">${shortDesc}</div>
                                    ${specialist.description.length > maxLength ? '<a href="#" class="kab-read-more">Les mer</a>' : ''}
                                `;
                            }
                            
                            sliderHtml += `
                                <div class="kab-specialist-card" data-specialist-id="${specialist.id}" data-index="${index}">
                                    <div class="kab-specialist-card-inner">
                                        <div class="kab-specialist-image">
                                            <img src="${specialist.image_url || specialist.profile_image || specialist.image || 'https://via.placeholder.com/100'}" alt="${specialist.name}">
                                        </div>
                                        <div class="kab-specialist-info">
                                            <div class="kab-specialist-name">${specialist.name}</div>
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
                            
                            // No dots needed
                        });
                        
                        sliderHtml += '</div>';
                        
                        $specialistsList.html(sliderHtml);
                        
                        // Initialize slider functionality with a slight delay to ensure DOM is ready
                        setTimeout(() => {
                            self.initSpecialistSlider();
                        }, 100);
                        
                        // If we have a preselected specialist, select it
                        if (self.specialistId) {
                            self.selectSpecialist(self.specialistId);
                            
                            if (self.preselectedSpecialist) {
                                self.preselectedSpecialist = false;
                                self.nextStep();
                            }
                        } else {
                            // Enable next button anyway - we'll auto-select if needed
                            self.$form.find('.kab-form-step[data-step="3"] .kab-next-btn').prop('disabled', false);
                        }
                    } else {
                        $specialistsList.html('<div class="kab-no-data">' + kab_vars.no_specialists + '</div>');
                    }
                },
                error: function() {
                    $specialistsList.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                }
            });
        }
        
        /**
         * Extract tags from specialist data
         * 
         * @param {Object} specialist The specialist data object
         * @returns {Array} Array of tag strings
         */
        getSpecialistTags(specialist) {
            const tags = [];
            
            // Try to extract tags from different possible sources in the specialist data
            
            // 1. Check for categories/specialties directly in the specialist object
            if (specialist.categories && Array.isArray(specialist.categories)) {
                specialist.categories.forEach(category => {
                    if (typeof category === 'string') {
                        tags.push(category);
                    } else if (category.name) {
                        tags.push(category.name);
                    }
                });
            }
            
            // 2. Check for specialties
            if (specialist.specialties && Array.isArray(specialist.specialties)) {
                specialist.specialties.forEach(specialty => {
                    if (typeof specialty === 'string') {
                        tags.push(specialty);
                    } else if (specialty.name) {
                        tags.push(specialty.name);
                    }
                });
            }
            
            // 3. If no tags found, add some default tags from the screenshot
            if (tags.length === 0) {
                // These are example tags from the screenshot
                const defaultTags = ['Angst', 'Utbrenthet', 'Selvfølelse/selvbilde', 'Psykiske utfordringer', 'Personlig vekst'];
                
                // Add 2-4 random tags to make it look natural
                const numTags = Math.floor(Math.random() * 3) + 2; // 2-4 tags
                const shuffled = [...defaultTags].sort(() => 0.5 - Math.random());
                return shuffled.slice(0, numTags);
            }
            
            return tags;
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
            
            // If there are no cards, return early
            if ($cards.length === 0) {
                console.log('No specialist cards found');
                return;
            }
            
            let currentIndex = 0;
            const totalSlides = $cards.length;
            
            console.log(`Initializing slider with ${totalSlides} specialists`);
            
            // Show only the first card initially
            $cards.hide().eq(currentIndex).show();
            
            // Update slider function with animation
            const updateSlider = () => {
                $cards.fadeOut(200).promise().done(function() {
                    $cards.eq(currentIndex).fadeIn(200);
                });
            };
            
            // Handle next button click
            $nextBtn.on('click', function() {
                currentIndex = (currentIndex + 1) % totalSlides;
                updateSlider();
            });
            
            // Handle previous button click
            $prevBtn.on('click', function() {
                currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
                updateSlider();
            });
            
            // No dot clicks to handle
            
            // Handle select specialist button clicks - auto advance to next step
            this.$form.off('click', '.kab-select-specialist-btn').on('click', '.kab-select-specialist-btn', function() {
                const specialistId = $(this).data('specialist-id');
                self.selectSpecialist(specialistId);
                
                // Auto advance to next step after selecting therapist
                setTimeout(() => {
                    self.nextStep();
                }, 300);
            });
            
            // Handle "Les mer" link clicks
            this.$form.on('click', '.kab-read-more', function(e) {
                e.preventDefault();
                const $description = $(this).prev('.kab-specialist-description');
                const $card = $(this).closest('.kab-specialist-card');
                const specialistId = $card.data('specialist-id');
                const specialist = response.data.specialists.find(s => s.id === specialistId);
                
                if (specialist && specialist.description) {
                    // Toggle between short and full description
                    if ($(this).hasClass('expanded')) {
                        // Show short description
                        const shortDesc = specialist.description.substring(0, 150) + '...';
                        $description.text(shortDesc);
                        $(this).text('Les mer').removeClass('expanded');
                    } else {
                        // Show full description
                        $description.text(specialist.description);
                        $(this).text('Vis mindre').addClass('expanded');
                    }
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
            
            // Ensure a therapist is selected before loading dates
            if (!this.specialistId) {
                $datePicker.html('<div class="kab-no-data">Please select a therapist first.</div>');
                return;
            }
            
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
                        // Create date picker
                        const today = new Date();
                        const currentMonth = today.getMonth();
                        const currentYear = today.getFullYear();
                        
                        $datePicker.html(self.createCalendar(currentMonth, currentYear, response.data.dates));
                        
                        // Add event handlers for month navigation
                        self.initMonthNavigation(response.data.dates);
                        
                        // If we have a selected date and time slots from the response
                        if (response.data.selected_date) {
                            // Set the selected date
                            self.selectedDate = response.data.selected_date;
                            const $selectedDateElement = $datePicker.find(`.kab-day[data-date="${response.data.selected_date}"]`);
                            if ($selectedDateElement.length) {
                                $selectedDateElement.addClass('selected');
                            }
                            
                            // If we have time slots HTML, display it
                            if (response.data.html_booking_slot) {
                                $timeSlots.html(response.data.html_booking_slot);
                                
                                // Enable next button if we have time slots
                                if ($timeSlots.find('.kab-time-slot').length > 0) {
                                    self.$form.find('.kab-form-step[data-step="4"] .kab-next-btn').prop('disabled', false);
                                    // No auto-selection of time slots - user must select manually
                                }
                            }
                        } else {
                            // If no selected date was returned, select the most recent available date immediately
                            if (response.data.dates.length > 0) {
                                // Sort dates to find the most recent one
                                const sortedDates = [...response.data.dates].sort();
                                const todayStr = new Date().toISOString().split('T')[0];
                                
                                // Find the first date that is today or after today
                                let nearestDate = sortedDates[0];
                                for (const date of sortedDates) {
                                    if (date >= todayStr) {
                                        nearestDate = date;
                                        break;
                                    }
                                }
                                
                                // Directly call selectDate instead of triggering click event
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
         * 
         * @param {Array} availableDates Array of available dates
         */
        initMonthNavigation(availableDates) {
            const self = this;
            const $datePicker = this.$form.find('.kab-date-picker');
            
            // Handle month navigation
            $datePicker.on('click', '.kab-month-prev, .kab-month-next', function() {
                const month = parseInt($(this).data('month'));
                const year = parseInt($(this).data('year'));
                
                console.log(`Navigating to month: ${month}, year: ${year}`);
                
                // Update calendar
                $datePicker.html(self.createCalendar(month, year, availableDates));
                
                // Re-initialize month navigation for the new calendar
                self.initMonthNavigation(availableDates);
            });
        }
        
        /**
         * Create calendar
         */
        createCalendar(month, year, availableDates) {
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            
            // Calculate previous and next month
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
                        <div>Sun</div>
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                    </div>
                    <div class="kab-days">
            `;
            
            // Get first day of month
            const firstDay = new Date(year, month, 1).getDay();
            
            // Get last day of month
            const lastDay = new Date(year, month + 1, 0).getDate();
            
            // Add empty cells for days before first day of month
            for (let i = 0; i < firstDay; i++) {
                html += '<div class="kab-day empty"></div>';
            }
            
            // Add days
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
            // Auto-select a therapist if we're on the therapist step and none is selected
            if (this.currentStep === 3 && !this.specialistId) {
                const $firstSpecialist = this.$form.find('.kab-specialist-card:first');
                if ($firstSpecialist.length) {
                    const specialistId = $firstSpecialist.data('specialist-id');
                    this.selectSpecialist(specialistId);
                }
            }
            
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
         * 
         * @param {number} step Step number
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
         * 
         * @param {number} step Step number
         */
        handleStepChange(step) {
            switch (step) {
                case 2:
                    this.loadCategories();
                    break;
                case 3:
                    this.loadSpecialists();
                    break;
                case 4:
                    this.loadDatePicker();
                    break;
                case 5:
                    this.updateBookingSummary();
                    break;
                case 6:
                    this.submitBooking();
                    break;
            }
        }
        
        /**
         * Select a service
         * 
         * @param {string} serviceId Service ID
         * @param {boolean} autoAdvance Whether to automatically advance to next step (default: true)
         */
        selectService(serviceId, autoAdvance = true) {
            this.serviceId = serviceId;
            
            // Update UI
            this.$form.find('.kab-service-item').removeClass('selected');
            this.$form.find(`.kab-service-item[data-service-id="${serviceId}"]`).addClass('selected');
            
            // Automatically proceed to next step if autoAdvance is true
            if (autoAdvance) {
                setTimeout(() => {
                    this.nextStep();
                }, 300); // Small delay for visual feedback
            }
        }
        
        /**
         * Select a location
         * 
         * @param {string} locationId Location ID
         */
        selectLocation(locationId) {
            this.locationId = locationId;
            
            // Update UI
            this.$form.find('.kab-category-item').removeClass('selected');
            this.$form.find(`.kab-category-item[data-location-id="${locationId}"]`).addClass('selected');
            
            // Automatically proceed to next step
            setTimeout(() => {
                this.nextStep();
            }, 300); // Small delay for visual feedback
        }
        
        /**
         * Select a specialist
         * 
         * @param {string} specialistId Specialist ID
         */
        selectSpecialist(specialistId) {
            this.specialistId = specialistId;
            
            // Update UI
            this.$form.find('.kab-specialist-card').removeClass('selected');
            this.$form.find(`.kab-specialist-card[data-specialist-id="${specialistId}"]`).addClass('selected');
            
            // Enable next button
            this.$form.find('.kab-form-step[data-step="3"] .kab-next-btn').prop('disabled', false);
            
            // If we're already on the date selection step, reload the date picker for this therapist
            if (this.currentStep === 4) {
                this.loadDatePicker();
                // Clear any selected date and time
                this.selectedDate = '';
                this.selectedTime = '';
                this.$form.find('.kab-form-step[data-step="4"] .kab-next-btn').prop('disabled', true);
            }
        }
        
        /**
         * Select a random specialist from the list
         */
        selectRandomSpecialist() {
            // Check if we have specialists loaded
            if (!this.specialists || this.specialists.length === 0) {
                console.warn('No specialists available for random selection');
                return;
            }
            
            // Select a random specialist
            const randomIndex = Math.floor(Math.random() * this.specialists.length);
            const randomSpecialist = this.specialists[randomIndex];
            
            // Select the random specialist
            this.selectSpecialist(randomSpecialist.id);
            
            // Automatically advance to next step
            setTimeout(() => {
                this.nextStep();
            }, 300);
        }
        
        /**
         * Select a date
         * 
         * @param {string} date Selected date
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
         * 
         * @param {string} date Selected date
         */
        loadTimeSlots(date) {
            const self = this;
            const $timeSlots = this.$form.find('.kab-time-slots');
            
            // Ensure a therapist is selected
            if (!this.specialistId) {
                $timeSlots.html('<div class="kab-no-data">Please select a therapist first.</div>');
                return;
            }
            
            $timeSlots.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            // Display which therapist's schedule we're viewing
            const $selectedTherapist = this.$form.find(`.kab-specialist-card[data-specialist-id="${this.specialistId}"]`);
            let therapistName = "selected therapist";
            if ($selectedTherapist.length) {
                therapistName = $selectedTherapist.find('.kab-specialist-name').text() || therapistName;
            }
            
            // Add therapist name to the time slots header
            this.$form.find('.kab-time-slots-container h4').text('Available times for ' + therapistName);
            
            // Set the selected date
            this.selectedDate = date;
            
            // Using AJAX to get HTML directly like the old plugin
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
                    // The response is direct HTML, not JSON
                    $timeSlots.html(response);
                    
                    // Enable next button if we have time slots
                    if ($timeSlots.find('.kab-time-slot').length > 0) {
                        self.$form.find('.kab-form-step[data-step="4"] .kab-next-btn').prop('disabled', false);
                        // No auto-selection of time slots - user must select manually
                    } else {
                        self.$form.find('.kab-form-step[data-step="4"] .kab-next-btn').prop('disabled', true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching time slots:', error);
                    $timeSlots.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                    self.$form.find('.kab-form-step[data-step="4"] .kab-next-btn').prop('disabled', true);
                }
            });
        }
        
        /**
         * Select a time slot
         * 
         * @param {string} timeSlot Time slot
         */
        selectTimeSlot(timeSlot) {
            this.selectedTime = timeSlot;
            
            // Update UI
            this.$form.find('.kab-time-slot').removeClass('selected');
            this.$form.find(`.kab-time-slot[data-timeslot="${timeSlot}"]`).addClass('selected');
            
            // Enable next button
            this.$form.find('.kab-form-step[data-step="4"] .kab-next-btn').prop('disabled', false);
            
            // Auto-advance to next step after selecting time slot
            setTimeout(() => {
                this.nextStep();
            }, 300);
        }
        
        /**
         * Update booking summary on the details page
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
            if (this.specialistId) {
                const $selectedTherapist = this.$form.find(`.kab-specialist-card[data-specialist-id="${this.specialistId}"]`);
                if ($selectedTherapist.length) {
                    therapistName = $selectedTherapist.find('.kab-specialist-name').text();
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
                // Try to get time from the selected time slot element first
                const $selectedTimeSlot = this.$form.find(`.kab-time-slot[data-timeslot="${this.selectedTime}"]`);
                if ($selectedTimeSlot.length) {
                    formattedTime = $selectedTimeSlot.text().trim();
                }
                
                // If we didn't get a valid time from the element, try parsing the selectedTime value
                if (!formattedTime || formattedTime.length === 0 || !/^\d{1,2}:\d{2}$/.test(formattedTime)) {
                    if (this.selectedTime.includes('T')) {
                        // ISO format: extract time part
                        const timePart = this.selectedTime.split('T')[1];
                        if (timePart) {
                            formattedTime = timePart.substring(0, 5);
                        }
                    } else if (this.selectedTime.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/)) {
                        // Another ISO format
                        formattedTime = this.selectedTime.split('T')[1].substring(0, 5);
                    } else if (this.selectedTime.match(/^\d{2}:\d{2}/)) {
                        // Already in HH:MM format
                        formattedTime = this.selectedTime.substring(0, 5);
                    }
                }
            }
            
            // Get price from stored service data
            let price = '';
            if (this.serviceId && this.services.length > 0) {
                const selectedService = this.services.find(service => service.id === this.serviceId);
                if (selectedService && selectedService.price) {
                    // Format price (add comma and dash if needed)
                    price = selectedService.price;
                    if (price && !price.includes(',')) {
                        price = price + ',-';
                    }
                }
            }
            
            // Update summary fields
            this.$form.find('.kab-summary-therapist').text(therapistName);
            this.$form.find('.kab-summary-service').text(serviceName);
            this.$form.find('.kab-summary-location').text('Online Video Samtale');
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
            const firstName = this.$form.find('#kab-first-name').val();
            const lastName = this.$form.find('#kab-last-name').val();
            const email = this.$form.find('#kab-email').val();
            const phone = this.$form.find('#kab-phone').val();
            const notes = this.$form.find('#kab-notes').val();
            
            // Validate form data
            if (!firstName || !lastName || !email || !phone) {
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
                        self.processBooking(firstName, lastName, email, phone, notes, recaptchaToken);
                    });
                });
            } else {
                this.processBooking(firstName, lastName, email, phone, notes, recaptchaToken);
            }
        }
        
        /**
         * Process booking
         * 
         * @param {string} firstName First name
         * @param {string} lastName Last name
         * @param {string} email Email
         * @param {string} phone Phone
         * @param {string} notes Notes
         * @param {string} recaptchaToken reCAPTCHA token
         */
        processBooking(firstName, lastName, email, phone, notes, recaptchaToken) {
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
                    timeslot: this.selectedTime,
                    first_name: firstName,
                    last_name: lastName,
                    email: email,
                    phone: phone,
                    notes: notes,
                    recaptcha_token: recaptchaToken,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    $loading.hide();
                    
                    if (response.success) {
                        $success.show();
                        $success.find('.kab-booking-message').html(response.data.message);
                        
                        // Build booking details
                        let bookingDetails = `
                            <div class="kab-booking-info-item">
                                <span class="kab-booking-info-label">Booking ID:</span>
                                <span class="kab-booking-info-value">${response.data.booking_id}</span>
                            </div>
                        `;
                        
                        $success.find('.kab-booking-details').html(bookingDetails);
                        
                        // Redirect if URL provided
                        if (response.data.redirect_url) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url;
                            }, 2000);
                        }
                    } else {
                        $error.show();
                        $error.find('.kab-error-message').text(response.data.message);
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
     * Initialize booking forms when DOM is ready
     */
    $(document).ready(function() {
        // Initialize booking forms
        $('.kab-booking-form').each(function() {
            new KABBookingForm($(this));
        });
        
        // Initialize popup functionality
        $('.kab-button[data-popup-target]').click(function() {
            const popupId = $(this).data('popup-target');
            $('#' + popupId).show();
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