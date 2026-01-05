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
            this.currentLogicalStep = 1;
            this.isNavigating = false; // Flag to prevent rapid navigation
            this.lastNavigationTime = 0; // Track last navigation time
            
            this.serviceId = $form.data('service-id') || '';
            this.specialistId = $form.data('specialist-id') || '';
            // Ensure locationId is stored as string for consistency
            const locationIdData = $form.data('location-id') || '';
            this.locationId = locationIdData ? String(locationIdData) : '';
            // Store original pre-selected location ID to distinguish from user-selected
            this.preselectedLocationId = locationIdData ? String(locationIdData) : '';
            this.selectedDate = '';
            this.selectedTime = '';
            this.services = []; // Store services data with prices
            this.locations = []; // Store locations data
            this.specialists = []; // Store specialists data for random selection
            this.isInitialLoad = true; // Flag to track if this is the initial load
            this.isLoadingSpecialists = false; // Flag to prevent multiple simultaneous specialist loads
            
            // Determine which steps are visible and create step mapping
            this.setupStepMapping();
            
            this.init();
        }
        
        /**
         * Setup step mapping based on pre-selected values
         * Maps logical step numbers (1, 2, 3...) to actual step numbers (1, 2, 3, 4, 5)
         */
        setupStepMapping() {
            // Define which actual steps should be visible
            // Step 1: Service Selection
            // Step 2: Location Selection
            // Step 3: Specialist Selection
            // Step 4: Date & Time Selection
            // Step 5: Personal Details
            // Step 6: Confirmation (not counted in progress)
            
            this.visibleSteps = [];
            this.stepMapping = {}; // Maps logical step number to actual step number
            this.reverseMapping = {}; // Maps actual step number to logical step number
            
            // Step 1: Service Selection (only if service is not pre-selected)
            if (!this.serviceId) {
                this.visibleSteps.push(1);
                this.stepMapping[this.visibleSteps.length] = 1;
                this.reverseMapping[1] = this.visibleSteps.length;
            }
            
            // Step 2: Location Selection (only if location is not pre-selected)
            if (!this.locationId) {
                this.visibleSteps.push(2);
                this.stepMapping[this.visibleSteps.length] = 2;
                this.reverseMapping[2] = this.visibleSteps.length;
            }
            
            // Step 3: Specialist Selection (always visible)
            this.visibleSteps.push(3);
            this.stepMapping[this.visibleSteps.length] = 3;
            this.reverseMapping[3] = this.visibleSteps.length;
            
            // Step 4: Date & Time Selection (always visible)
            this.visibleSteps.push(4);
            this.stepMapping[this.visibleSteps.length] = 4;
            this.reverseMapping[4] = this.visibleSteps.length;
            
            // Step 5: Personal Details (always visible)
            this.visibleSteps.push(5);
            this.stepMapping[this.visibleSteps.length] = 5;
            this.reverseMapping[5] = this.visibleSteps.length;
            
            // Set maxStep based on visible steps
            this.maxStep = this.visibleSteps.length;
            
            // Hide steps that are not visible
            this.hideSkippedSteps();
        }
        
        /**
         * Hide steps that are skipped due to pre-selection
         */
        hideSkippedSteps() {
            // Hide service step if service is pre-selected
            if (this.serviceId) {
                this.$form.find('.kab-form-step[data-step="1"]').hide();
            }
            
            // Hide location step if location is pre-selected
            if (this.locationId) {
                this.$form.find('.kab-form-step[data-step="2"]').hide();
            }
        }
        
        /**
         * Get logical step number from actual step number
         */
        getLogicalStep(actualStep) {
            return this.reverseMapping[actualStep] || actualStep;
        }
        
        /**
         * Get actual step number from logical step number
         */
        getActualStep(logicalStep) {
            return this.stepMapping[logicalStep] || logicalStep;
        }
        
        /**
         * Initialize the form
         */
        init() {
            // Hide all steps initially
            this.$form.find('.kab-form-step').hide();
            
            // Determine which logical step to show first
            let firstLogicalStep = 1;
            
            if (this.serviceId && this.locationId) {
                // Both service and location are pre-selected, start at therapist selection (logical step 1 = actual step 3)
                firstLogicalStep = this.getLogicalStep(3);
            } else if (this.serviceId) {
                // Only service is pre-selected, start at location selection (logical step 1 = actual step 2)
                firstLogicalStep = this.getLogicalStep(2);
            } else {
                // Nothing pre-selected, start at service selection (logical step 1 = actual step 1)
                firstLogicalStep = this.getLogicalStep(1);
            }
            
            // Go to the first logical step (which will show the correct actual step)
            this.goToLogicalStep(firstLogicalStep);
            
            this.initEvents();
            this.loadServices();
            
            // Locations will be loaded after services are loaded if location is pre-selected
            
            // If specialist ID is preselected
            if (this.specialistId) {
                // Will be handled after services are loaded
                this.preselectedSpecialist = true;
            }
        }
        
        /**
         * Load locations data for summary (when location step is skipped)
         */
        loadCategoriesForSummary() {
            const self = this;
            
            // Prepare location_id - convert to integer if it's a string, or use 0 if empty
            let locationId = this.locationId;
            if (locationId && typeof locationId === 'string') {
                locationId = parseInt(locationId, 10) || 0;
            } else if (!locationId) {
                locationId = 0;
            }
            
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
                        // Store locations data for summary
                        self.locations = response.data.locations;
                    }
                },
                error: function() {
                    // Silently fail - locations just won't show in summary
                }
            });
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
                // User manually selected location, so auto-advance
                self.selectLocation(locationId, true);
            });
            
            // Specialist selection - handled later in loadSpecialists to avoid conflicts
            
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
            
            // Form submission - handle button click since there's no form element
            this.$form.on('click', '.kab-submit-btn', function(e) {
                e.preventDefault();
                
                // Validate form fields first
                const firstName = self.$form.find('#kab-first-name').val();
                const lastName = self.$form.find('#kab-last-name').val() || ''; // Last name is optional
                const email = self.$form.find('#kab-email').val();
                const phone = self.$form.find('#kab-phone').val();
                const terms = self.$form.find('#kab-terms').is(':checked');
                
                // Check if all required fields are filled (last name is optional)
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
                self.goToStep(6);
                self.submitBooking();
                return false;
            });
            
            // Form submission (fallback for actual form elements)
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
            
            // Book new appointment button
            this.$form.on('click', '.kab-book-new-btn', function() {
                // Reset form and go back to step 1
                self.resetForm();
            });
            
            // Close window button
            this.$form.on('click', '.kab-close-window-btn', function() {
                // If in a popup, close it; otherwise, just hide the form or redirect
                const $popup = self.$form.closest('.kab-popup');
                if ($popup.length) {
                    $popup.fadeOut(300);
                } else {
                    // If not in popup, could redirect to home or hide form
                    window.location.href = '/';
                }
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
                        
                        // Group services by category
                        const categories = response.data.categories || [];
                        const servicesByCategory = {};
                        const uncategorizedServices = [];
                        
                        // Initialize categories
                        categories.forEach(cat => {
                            servicesByCategory[cat.id] = {
                                category: cat,
                                services: []
                            };
                        });
                        
                        // Group services
                        self.services.forEach(service => {
                            if (service.category_id && servicesByCategory[service.category_id]) {
                                servicesByCategory[service.category_id].services.push(service);
                            } else {
                                uncategorizedServices.push(service);
                            }
                        });
                        
                        // Build HTML with category dropdowns
                        let html = '';
                        
                        // Check if we have any categorized services
                        const hasCategorizedServices = Object.values(servicesByCategory).some(catData => catData.services.length > 0);
                        
                        if (hasCategorizedServices) {
                            // Render categorized services directly in the grid (no wrapper div)
                            Object.values(servicesByCategory).forEach(catData => {
                                if (catData.services.length > 0) {
                                    html += `<div class="kab-category-group">
                                        <div class="kab-category-header" data-category-id="${catData.category.id}">
                                            <span class="kab-category-name">${catData.category.category_name}</span>
                                            <span class="kab-category-toggle">+</span>
                                        </div>
                                        <div class="kab-category-services" style="display: none;">
                                            ${catData.services.map(service => 
                                                `<div class="kab-service-item" data-service-id="${service.id}">
                                                    <div class="kab-service-info">
                                                        <p>${service.name}</p>
                                                    </div>
                                                </div>`
                                            ).join('')}
                                        </div>
                                    </div>`;
                                }
                            });
                            
                            // Add uncategorized services directly (without category grouping)
                            if (uncategorizedServices.length > 0) {
                                html += uncategorizedServices.map(service => 
                                    `<div class="kab-service-item" data-service-id="${service.id}">
                                        <div class="kab-service-info">
                                            <p>${service.name}</p>
                                        </div>
                                    </div>`
                                ).join('');
                            }
                        } else {
                            // No categories, render all services directly like before
                            html = self.services.map(service => 
                                `<div class="kab-service-item" data-service-id="${service.id}">
                                    <div class="kab-service-info">
                                        <p>${service.name}</p>
                                    </div>
                                </div>`
                            ).join('');
                        }
                        
                        // Render services immediately without delay
                        $servicesList.html(html);
                        
                        // Initialize category toggle functionality (only if categories exist)
                        if (hasCategorizedServices) {
                            $servicesList.find('.kab-category-header').on('click', function() {
                                const $header = $(this);
                                const $services = $header.next('.kab-category-services');
                                const $toggle = $header.find('.kab-category-toggle');
                                
                                // Close all other categories
                                $servicesList.find('.kab-category-services').not($services).slideUp(200);
                                $servicesList.find('.kab-category-toggle').not($toggle).text('+');
                                
                                // Toggle current category
                                $services.slideToggle(200);
                                $toggle.text($services.is(':visible') ? '−' : '+');
                            });
                        }
                        
                        // If service is preselected
                        if (self.serviceId) {
                            self.$form.find(`.kab-service-item[data-service-id="${self.serviceId}"]`).addClass('selected');
                            self.$form.find('.kab-form-step[data-step="1"] .kab-next-btn').prop('disabled', false);
                            
                            // If location is also preselected, load locations data for summary
                            if (self.locationId) {
                                // Load locations in background so they're available for summary
                                self.loadCategoriesForSummary();
                                // Both service and location are preselected, go to therapist step immediately
                                self.goToLogicalStep(self.getLogicalStep(3));
                            } else {
                                // Only service is preselected, go to location step immediately
                                self.goToLogicalStep(self.getLogicalStep(2));
                            }
                        }
                        
                        // If specialist is preselected
                        if (self.preselectedSpecialist && self.specialistId) {
                            // Get the first service
                            if (!self.serviceId && response.data.services.length > 0) {
                                self.serviceId = response.data.services[0].id;
                                self.$form.find(`.kab-service-item[data-service-id="${self.serviceId}"]`).addClass('selected');
                                self.$form.find('.kab-form-step[data-step="1"] .kab-next-btn').prop('disabled', false);
                            }
                            // Go to location step using logical step immediately
                            self.goToLogicalStep(self.getLogicalStep(2));
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
                        // Store locations data
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
                        
                        // If we have a preselected location, auto-select it and advance
                        // Only auto-advance if location was pre-selected from button, not if user selected it
                        if (self.locationId) {
                            // Check if the preselected location exists in the loaded locations
                            const preselectedLocation = response.data.locations.find(loc => {
                                return String(loc.id) === String(self.locationId) || loc.id == self.locationId;
                            });
                            if (preselectedLocation) {
                                // Only auto-advance if this location was pre-selected from button
                                // If user selected it and navigated back, just show it selected without advancing
                                const wasPreselected = self.preselectedLocationId && 
                                    String(self.locationId) === String(self.preselectedLocationId);
                                self.selectLocation(self.locationId, wasPreselected);
                            }
                        } else if (self.preselectedSpecialist && self.specialistId && response.data.locations.length > 0) {
                            // If we have a preselected specialist, auto-select the first category
                            self.selectLocation(response.data.locations[0].id, true);
                        }
                        
                        // Mark that initial load is complete
                        self.isInitialLoad = false;
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
            
            // Prevent multiple simultaneous loads
            if (this.isLoadingSpecialists) {
                return;
            }
            this.isLoadingSpecialists = true;
            
            // Ensure we have required data
            if (!this.serviceId) {
                $specialistsList.html('<div class="kab-no-data">' + kab_vars.select_service + '</div>');
                this.isLoadingSpecialists = false;
                return;
            }
            
            $specialistsList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            // Prepare location_id - convert to integer if it's a string, or use 0 if empty
            let locationId = this.locationId;
            if (locationId && typeof locationId === 'string') {
                locationId = parseInt(locationId, 10) || 0;
            } else if (!locationId) {
                locationId = 0;
            }
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_specialists',
                    service_id: this.serviceId,
                    location_id: locationId,
                    // Don't send specialist_id when loading for display - we want all specialists
                    // specialist_id is only used when pre-selecting from button
                    specialist_id: '',
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
                                    <p class="kab-specialist-description">${shortDesc}</p>
                                    ${specialist.description.length > maxLength ? '<a href="#" class="kab-read-more">Les mer</a>' : ''}
                                `;
                            }
                            
                            // Hide all cards except the first one initially to prevent blinking
                            const displayStyle = index === 0 ? '' : 'style="display: none;"';
                            sliderHtml += `
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
                            
                            // No dots needed
                        });
                        
                        sliderHtml += '</div>';
                        
                        // Render HTML
                        $specialistsList.html(sliderHtml);
                        
                        // Initialize slider immediately using requestAnimationFrame for smooth rendering
                        // This ensures DOM is ready but doesn't cause visible delay
                        requestAnimationFrame(() => {
                            self.initSpecialistSlider();
                            self.isLoadingSpecialists = false;
                        });
                        
                        // If we have a preselected specialist (from button), select it and auto-advance
                        if (self.specialistId && self.preselectedSpecialist && self.currentStep === 3) {
                            // Check if the preselected specialist exists in the loaded list
                            const preselectedSpecialist = response.data.specialists.find(spec => spec.id === self.specialistId);
                            if (preselectedSpecialist) {
                                self.selectSpecialist(self.specialistId);
                                self.preselectedSpecialist = false;
                                // Auto-advance only if it was pre-selected from button
                                setTimeout(() => {
                                    if (self.currentStep === 3) {
                                        self.nextStep();
                                    }
                                }, 300);
                            } else {
                                // Specialist not found, clear the selection
                                self.specialistId = '';
                                self.$form.find('.kab-form-step[data-step="3"] .kab-next-btn').prop('disabled', false);
                            }
                        } else if (self.specialistId) {
                            // User had selected a specialist before going back - just mark it as selected visually
                            // Check if the selected specialist exists in the loaded list
                            const selectedSpecialist = response.data.specialists.find(spec => spec.id === self.specialistId);
                            if (selectedSpecialist) {
                                self.selectSpecialist(self.specialistId);
                            } else {
                                // Selected specialist no longer available, clear selection
                                self.specialistId = '';
                                self.$form.find('.kab-specialist-card').removeClass('selected');
                            }
                            // Enable next button since we have a selection
                            self.$form.find('.kab-form-step[data-step="3"] .kab-next-btn').prop('disabled', false);
                        } else {
                            // No specialist selected, enable next button (will auto-select if needed)
                            self.$form.find('.kab-form-step[data-step="3"] .kab-next-btn').prop('disabled', false);
                        }
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
            
            // If there are no cards, return early
            if ($cards.length === 0) {
                return;
            }
            
            let currentIndex = 0;
            const totalSlides = $cards.length;
            
            // Cards are already hidden except the first one (set in HTML during rendering)
            // Just verify the first one is visible - don't manipulate all cards to avoid flickering
            // Only ensure first card is visible if somehow it got hidden
            const $firstCard = $cards.eq(currentIndex);
            if ($firstCard.is(':hidden')) {
                $firstCard.show();
            }
            
            // Update slider function with animation
            const updateSlider = () => {
                $cards.fadeOut(200).promise().done(function() {
                    $cards.eq(currentIndex).fadeIn(200);
                });
            };
            
            // Remove existing handlers to prevent duplicates
            $nextBtn.off('click');
            $prevBtn.off('click');
            
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
            
            // Remove existing handlers to prevent duplicates
            this.$form.off('click', '.kab-select-specialist-btn');
            this.$form.off('click', '.kab-specialist-card');
            
            // Handle select specialist button clicks - auto advance to next step
            this.$form.on('click', '.kab-select-specialist-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const specialistId = $(this).data('specialist-id');
                if (specialistId) {
                    self.selectSpecialist(specialistId);
                    
                    // Auto advance to next step after selecting therapist
                    setTimeout(() => {
                        self.nextStep();
                    }, 300);
                }
            });
            
            // Handle specialist card clicks - select but don't auto-advance
            this.$form.on('click', '.kab-specialist-card', function(e) {
                // Don't trigger if clicking the button inside
                if ($(e.target).closest('.kab-select-specialist-btn').length) {
                    return;
                }
                const specialistId = $(this).data('specialist-id');
                if (specialistId) {
                    self.selectSpecialist(specialistId);
                }
            });
            
            // Handle "Les mer" link clicks
            this.$form.off('click', '.kab-read-more').on('click', '.kab-read-more', function(e) {
                e.preventDefault();
                const $link = $(this);
                const $description = $link.prev('.kab-specialist-description');
                const $card = $link.closest('.kab-specialist-card');
                const specialistId = $card.data('specialist-id');
                
                // Get specialist from stored specialists array
                const specialist = self.specialists.find(s => s.id === specialistId);
                
                if (specialist && specialist.description) {
                    // Toggle between short and full description
                    if ($link.hasClass('expanded')) {
                        // Show short description
                        const maxLength = 150;
                        const shortDesc = specialist.description.length > maxLength 
                            ? specialist.description.substring(0, maxLength) + '...' 
                            : specialist.description;
                        $description.text(shortDesc);
                        $link.text('Les mer').removeClass('expanded');
                    } else {
                        // Show full description
                        $description.text(specialist.description);
                        $link.text('Vis mindre').addClass('expanded');
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
            // Prevent multiple rapid step changes (only block if trying to navigate while already navigating to a different step)
            if (this.isNavigating) {
                // Allow navigation if it's been more than 200ms since last navigation
                const now = Date.now();
                if (this.lastNavigationTime && (now - this.lastNavigationTime) < 200) {
                    return;
                }
            }
            this.isNavigating = true;
            this.lastNavigationTime = Date.now();
            
            // Auto-select a therapist if we're on the therapist step and none is selected
            if (this.currentStep === 3 && !this.specialistId) {
                const $firstSpecialist = this.$form.find('.kab-specialist-card:first');
                if ($firstSpecialist.length) {
                    const specialistId = $firstSpecialist.data('specialist-id');
                    this.selectSpecialist(specialistId);
                }
            }
            
            // Get next logical step
            const nextLogicalStep = this.currentLogicalStep + 1;
            if (nextLogicalStep <= this.maxStep) {
                this.goToLogicalStep(nextLogicalStep);
            }
            
            // Reset navigation flag after a delay
            setTimeout(() => {
                this.isNavigating = false;
            }, 300);
        }
        
        /**
         * Go to previous step
         */
        prevStep() {
            // Prevent multiple rapid step changes
            if (this.isNavigating) {
                const now = Date.now();
                if (this.lastNavigationTime && (now - this.lastNavigationTime) < 200) {
                    return;
                }
            }
            this.isNavigating = true;
            this.lastNavigationTime = Date.now();
            
            const prevLogicalStep = this.currentLogicalStep - 1;
            if (prevLogicalStep >= 1) {
                this.goToLogicalStep(prevLogicalStep);
            }
            
            // Reset navigation flag after a delay
            setTimeout(() => {
                this.isNavigating = false;
            }, 300);
        }
        
        /**
         * Go to specific logical step (user-facing step number)
         * 
         * @param {number} logicalStep Logical step number (1, 2, 3...)
         */
        goToLogicalStep(logicalStep) {
            // Validate logical step
            if (logicalStep < 1 || logicalStep > this.maxStep) {
                return;
            }
            
            // Get the actual step number
            const actualStep = this.getActualStep(logicalStep);
            if (!actualStep) {
                console.warn('Invalid logical step:', logicalStep);
                return;
            }
            
            this.goToStep(actualStep, logicalStep);
        }
        
        /**
         * Go to specific step
         * 
         * @param {number} actualStep Actual step number (1, 2, 3, 4, 5)
         * @param {number} logicalStep Optional logical step number (for display)
         */
        goToStep(actualStep, logicalStep = null) {
            // Hide all steps
            this.$form.find('.kab-form-step').hide();
            
            // Show the target step
            this.$form.find(`.kab-form-step[data-step="${actualStep}"]`).show();
            
            // Hide progress bar on confirmation step (step 6) - not counted in progress
            if (actualStep === 6) {
                this.$form.find('.kab-form-progress').hide();
            } else {
                this.$form.find('.kab-form-progress').show();
                // Use logical step for progress display
                if (logicalStep === null) {
                    logicalStep = this.getLogicalStep(actualStep);
                }
                const progressPercentage = (logicalStep / this.maxStep) * 100;
                this.$form.find('.kab-progress-text').text(`${logicalStep}/${this.maxStep}`);
                this.$form.find('.kab-progress-fill').css('width', progressPercentage + '%');
            }
            
            // Update current step (store both actual and logical)
            this.currentStep = actualStep;
            this.currentLogicalStep = logicalStep || this.getLogicalStep(actualStep);
            
            // Handle step-specific actions
            this.handleStepChange(actualStep);
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
         * Reset form to initial state
         */
        resetForm() {
            // Reset all selections except serviceId, specialistId, and locationId (they come from button/data)
            // Read locationId from data attribute to restore pre-selected value
            const locationIdData = this.$form.data('location-id') || '';
            this.locationId = locationIdData ? String(locationIdData) : '';
            this.preselectedLocationId = locationIdData ? String(locationIdData) : '';
            
            this.selectedDate = '';
            this.selectedTime = '';
            
            // Reset UI selections
            this.$form.find('.kab-service-item').removeClass('selected');
            this.$form.find('.kab-category-item').removeClass('selected');
            this.$form.find('.kab-date-item').removeClass('selected');
            this.$form.find('.kab-time-slot').removeClass('selected');
            
            // Reset navigation buttons
            this.$form.find('.kab-next-btn').prop('disabled', true);
            this.$form.find('.kab-prev-btn').prop('disabled', false);
            
            // Recalculate step mapping based on current pre-selected values
            this.setupStepMapping();
            
            // Determine correct initial step based on pre-selected values
            let firstLogicalStep = 1;
            if (this.serviceId && this.locationId) {
                // Both service and location are pre-selected, start at therapist selection
                firstLogicalStep = this.getLogicalStep(3);
            } else if (this.serviceId) {
                // Only service is pre-selected, start at location selection
                firstLogicalStep = this.getLogicalStep(2);
            } else {
                // Nothing pre-selected, start at service selection
                firstLogicalStep = this.getLogicalStep(1);
            }
            
            // Go to the correct initial step
            this.goToLogicalStep(firstLogicalStep);
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
         * @param {boolean} autoAdvance Whether to automatically advance to next step
         */
        selectLocation(locationId, autoAdvance = true) {
            // Ensure locationId is stored as string for consistency
            this.locationId = String(locationId);
            
            // Update UI
            this.$form.find('.kab-category-item').removeClass('selected');
            this.$form.find(`.kab-category-item[data-location-id="${locationId}"]`).addClass('selected');
            
            // Automatically proceed to next step if autoAdvance is true
            if (autoAdvance) {
                setTimeout(() => {
                    this.nextStep();
                }, 300); // Small delay for visual feedback
            }
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
                    // Try p tag first (updated structure), fallback to h4 for backward compatibility
                    serviceName = $selectedService.find('.kab-service-info p').text();
                }
                // If still empty, try to get from stored services data
                if (!serviceName && this.services.length > 0) {
                    const selectedService = this.services.find(service => service.id === this.serviceId);
                    if (selectedService && selectedService.name) {
                        serviceName = selectedService.name;
                    }
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
            if (this.locationId) {
                // First try to get from DOM
                const $selectedLocation = this.$form.find(`.kab-category-item[data-location-id="${this.locationId}"]`);
                if ($selectedLocation.length) {
                    locationName = $selectedLocation.find('.kab-category-name').text().trim();
                }
                // If still empty, try to get from stored locations data
                if (!locationName && this.locations.length > 0) {
                    // Convert locationId to string for comparison
                    const locationIdStr = String(this.locationId);
                    const selectedLocation = this.locations.find(location => {
                        // Try both string and number comparison
                        return String(location.id) === locationIdStr || location.id == this.locationId;
                    });
                    if (selectedLocation && selectedLocation.location_name) {
                        locationName = selectedLocation.location_name;
                    }
                }
                
                // If still empty and we have locationId, try to load locations if not already loaded
                if (!locationName && this.locations.length === 0 && this.serviceId) {
                    // Load locations synchronously for summary
                    const self = this;
                    $.ajax({
                        url: kab_vars.ajax_url,
                        type: 'POST',
                        async: false, // Synchronous to get the data immediately
                        data: {
                            action: 'kab_get_locations',
                            service_id: this.serviceId,
                            nonce: kab_vars.nonce
                        },
                        success: function(response) {
                            if (response.success && response.data.locations) {
                                self.locations = response.data.locations;
                                // Try to find location again
                                const locationIdStr = String(self.locationId);
                                const selectedLocation = self.locations.find(location => {
                                    return String(location.id) === locationIdStr || location.id == self.locationId;
                                });
                                if (selectedLocation && selectedLocation.location_name) {
                                    locationName = selectedLocation.location_name;
                                }
                            }
                        }
                    });
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
            const firstName = this.$form.find('#kab-first-name').val();
            const lastName = this.$form.find('#kab-last-name').val() || ''; // Last name is optional
            const email = this.$form.find('#kab-email').val();
            const phone = this.$form.find('#kab-phone').val();
            const notes = this.$form.find('#kab-notes').val() || ''; // Notes are optional
            
            // Validate form data (last name is optional)
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
                    location_id: this.locationId, // Include location_id
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
                        
                        // No redirect - stay on confirmation step
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
        // Initialize booking forms (skip forms in hidden popups, therapist-first forms, and service-therapist-first forms - they'll be initialized when popup opens or by their own script)
        $('.kab-booking-form').not('.kab-popup .kab-booking-form').not('.kab-therapist-first').not('.kab-service-therapist-first').each(function() {
            const $form = $(this);
            const instance = new KABBookingForm($form);
            $form.data('kab-form-instance', instance);
        });
        
        // Initialize popup functionality
        $('.kab-button[data-popup-target]').click(function() {
            const $button = $(this);
            const popupId = $button.data('popup-target');
            const $popup = $('#' + popupId);
            
            // Get service_id, location_id, and specialist_id from button
            const serviceId = $button.data('service-id') || '';
            const locationId = $button.data('location-id') || '';
            const specialistId = $button.data('specialist-id') || '';
            
            // Show popup
            $popup.show();
            
            // Skip if this is a therapist-first form (handled by public-therapist-first.js)
            const $therapistForm = $popup.find('.kab-booking-form.kab-therapist-first');
            if ($therapistForm.length) {
                return; // Don't process therapist-first forms here
            }
            
            // Skip if this is a service-therapist-first form (handled by public-service-therapist-first.js)
            const $serviceTherapistForm = $popup.find('.kab-booking-form.kab-service-therapist-first');
            if ($serviceTherapistForm.length) {
                return; // Don't process service-therapist-first forms here
            }
            
            // Handle regular booking form
            const $form = $popup.find('.kab-booking-form');
            if ($form.length) {
                // Apply data attributes from button if present
                if (serviceId) {
                    $form.attr('data-service-id', serviceId);
                }
                if (locationId) {
                    $form.attr('data-location-id', locationId);
                }
                if (specialistId) {
                    $form.attr('data-specialist-id', specialistId);
                }
                
                // Get or create form instance
                let formInstance = $form.data('kab-form-instance');
                if (!formInstance) {
                    formInstance = new KABBookingForm($form);
                    $form.data('kab-form-instance', formInstance);
                } else {
                    // Update form instance with fresh values from data attributes
                    formInstance.serviceId = $form.data('service-id') || '';
                    const locationIdData = $form.data('location-id') || '';
                    formInstance.locationId = locationIdData ? String(locationIdData) : '';
                    formInstance.preselectedLocationId = locationIdData ? String(locationIdData) : '';
                    formInstance.specialistId = $form.data('specialist-id') || '';
                    formInstance.selectedDate = '';
                    formInstance.selectedTime = '';
                    
                    // Reset form (will recalculate step mapping and go to correct step)
                    formInstance.resetForm();
                }
                
                // If serviceId provided, ensure it's selected
                if (serviceId) {
                    formInstance.serviceId = serviceId;
                    // Update the data attribute
                    $form.attr('data-service-id', serviceId);
                    
                    // If locationId also provided, set it
                    if (locationId) {
                        formInstance.locationId = String(locationId);
                        formInstance.preselectedLocationId = String(locationId);
                        $form.attr('data-location-id', locationId);
                    }
                    
                    // Recalculate step mapping with updated values
                    formInstance.setupStepMapping();
                    
                    // Determine correct initial step
                    let firstLogicalStep = 1;
                    if (formInstance.serviceId && formInstance.locationId) {
                        firstLogicalStep = formInstance.getLogicalStep(3);
                    } else if (formInstance.serviceId) {
                        firstLogicalStep = formInstance.getLogicalStep(2);
                    } else {
                        firstLogicalStep = formInstance.getLogicalStep(1);
                    }
                    
                    // If services are already loaded, select directly
                    if (formInstance.services && formInstance.services.length > 0) {
                        const serviceExists = formInstance.services.some(s => s.id === serviceId);
                        if (serviceExists) {
                            // Select service visually (without auto-advance)
                            formInstance.selectService(serviceId, false);
                            // Go to correct step based on pre-selected values
                            formInstance.goToLogicalStep(firstLogicalStep);
                            // Load locations if needed for summary
                            if (formInstance.locationId && formInstance.serviceId) {
                                formInstance.loadCategoriesForSummary();
                            }
                        } else {
                            formInstance.loadServices();
                        }
                    } else {
                        // Services not loaded yet; load and auto-select
                        formInstance.loadServices();
                    }
                } else {
                    // No preselected service; reset to step 1
                    formInstance.goToLogicalStep(1);
                    // If not initialized before, ensure services load
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