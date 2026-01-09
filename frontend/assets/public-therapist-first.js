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
            // Store original pre-selected therapist ID to distinguish from user-selected
            this.preselectedTherapistId = $form.data('therapist-id') || '';
            this.serviceId = '';
            this.locationId = '';
            this.selectedDate = '';
            this.selectedTime = '';
            this.services = [];
            this.locations = []; // Store locations data
            this.therapist = null;
            this.allTherapists = []; // Store all therapists for selection step
            
            // Track pending AJAX requests to prevent race conditions
            this.pendingServicesRequest = null;
            this.loadingServicesForTherapistId = null;
            this.isLoadingServices = false;
            
            this.init();
        }
        
        /**
         * Initialize the form
         */
        init() {
            if (!this.therapistId) {
                return;
            }
            
            // Hide all steps initially
            this.$form.find('.kab-form-step').hide();
            
            // Show step 1 immediately (don't wait for data to load)
            this.goToStep(1);
            
            this.initEvents();
            
            // Load data in background
            this.loadTherapist();
            this.loadServices();
        }
        
        /**
         * Initialize events
         */
        initEvents() {
            const self = this;
            
            // Service selection - only allow if services are loaded
            this.$form.on('click', '.kab-service-item', function() {
                // Prevent selection while loading
                if (self.isLoadingServices) {
                    return;
                }
                
                const serviceId = $(this).data('service-id');
                // Verify service belongs to current therapist
                const service = self.services.find(s => s.id === serviceId);
                if (service) {
                    self.selectService(serviceId);
                }
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
            
            // Form submission - handle button click since there's no form element
            this.$form.on('click', '.kab-submit-btn', function(e) {
                e.preventDefault();
                
                // Validate form fields first
                const firstName = self.$form.find('#kab-first-name-tf').val();
                const email = self.$form.find('#kab-email-tf').val();
                const phone = self.$form.find('#kab-phone-tf').val();
                const terms = self.$form.find('#kab-terms-tf').is(':checked');
                
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
            
            // Form submission (fallback for actual form elements)
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
            
            // Change therapist link
            this.$form.on('click', '.kab-change-therapist-link', function(e) {
                e.preventDefault();
                self.goToStep(0);
                self.loadAllTherapists();
            });
            
            // Therapist selection in step 0 - make entire card clickable
            this.$form.on('click', '.kab-therapist-select-card', function() {
                const therapistId = $(this).data('therapist-id');
                self.selectNewTherapist(therapistId);
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
                            // Show card with error message
                            const $card = self.$form.find('.kab-selected-therapist-card');
                            if ($card.length) {
                                $card.find('.kab-therapist-name').text('Therapist not found');
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
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
            
            // Cancel any pending request
            if (this.pendingServicesRequest) {
                this.pendingServicesRequest.abort();
                this.pendingServicesRequest = null;
            }
            
            // Store which therapist we're loading services for
            const loadingForTherapistId = this.therapistId;
            this.loadingServicesForTherapistId = loadingForTherapistId;
            this.isLoadingServices = true;
            
            // Clear old services immediately and show loading
            $servicesList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            // Disable service selection while loading (add visual indicator)
            $servicesList.addClass('kab-loading-services');
            
            // Clear current service selection
            this.serviceId = '';
            this.services = [];
            
            this.pendingServicesRequest = $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_services_for_therapist',
                    therapist_id: loadingForTherapistId,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    // Only process if this response is for the current therapist
                    if (self.loadingServicesForTherapistId !== loadingForTherapistId) {
                        return; // Ignore stale response
                    }
                    
                    // Clear the pending request reference
                    self.pendingServicesRequest = null;
                    self.isLoadingServices = false;
                    self.loadingServicesForTherapistId = null;
                    
                    if (response.success && response.data.services) {
                        // Remove loading indicator
                        self.$form.find('.kab-services-list').removeClass('kab-loading-services');
                        
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
                    } else {
                        $servicesList.html('<div class="kab-no-data">' + (response.data?.message || kab_vars.no_services) + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    // Only process error if this response is for the current therapist
                    if (self.loadingServicesForTherapistId !== loadingForTherapistId) {
                        return; // Ignore stale response
                    }
                    
                    // Clear the pending request reference
                    self.pendingServicesRequest = null;
                    self.isLoadingServices = false;
                    self.loadingServicesForTherapistId = null;
                    
                    // Remove loading indicator
                    self.$form.find('.kab-services-list').removeClass('kab-loading-services');
                    
                    // Don't show error if request was aborted (user changed therapist)
                    if (status !== 'abort') {
                        $servicesList.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                    }
                }
            });
        }
        
        /**
         * Load all therapists for selection
         */
        loadAllTherapists() {
            const self = this;
            const $therapistsList = this.$form.find('.kab-specialists-list');
            
            $therapistsList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_all_therapists',
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.therapists) {
                        // Filter therapists to only show those with locations assigned
                        const therapistsWithLocations = response.data.therapists.filter(function(therapist) {
                            return therapist.has_locations === true && 
                                   therapist.location_ids && 
                                   therapist.location_ids.length > 0;
                        });
                        
                        // Store filtered therapists
                        self.allTherapists = therapistsWithLocations;
                        
                        if (therapistsWithLocations.length === 0) {
                            $therapistsList.html('<div class="kab-no-data">No therapists with assigned locations available.</div>');
                            return;
                        }
                        
                        let html = '<div class="kab-therapists-grid">';
                        
                        $.each(therapistsWithLocations, function(index, therapist) {
                            const imageUrl = therapist.image_url || therapist.profile_image || therapist.image || 'https://via.placeholder.com/100';
                            const therapistName = therapist.name || '';
                            const therapistTitle = therapist.title || '';
                            
                            html += `
                                <div class="kab-therapist-select-card" data-therapist-id="${therapist.id}">
                                    <div class="kab-therapist-card-content">
                                        <div class="kab-therapist-image">
                                            <img src="${imageUrl}" alt="${therapistName}" class="kab-therapist-img">
                                        </div>
                                        <div class="kab-therapist-info">
                                            <div class="kab-therapist-name">${therapistName}</div>
                                            ${therapistTitle ? `<div class="kab-therapist-title">${therapistTitle}</div>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        html += '</div>';
                        $therapistsList.html(html);
                    } else {
                        $therapistsList.html('<div class="kab-no-data">' + (response.data?.message || 'No therapists available.') + '</div>');
                    }
                },
                error: function() {
                    $therapistsList.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                }
            });
        }
        
        /**
         * Select a new therapist and reload services
         */
        selectNewTherapist(therapistId) {
            const self = this;
            
            // Don't do anything if selecting the same therapist
            if (this.therapistId === therapistId) {
                return;
            }
            
            // Update therapist ID
            this.therapistId = therapistId;
            this.$form.attr('data-therapist-id', therapistId);
            
            // Reset service selection immediately
            this.serviceId = '';
            this.locationId = '';
            this.selectedDate = '';
            this.selectedTime = '';
            this.services = [];
            
            // Clear any selected service UI
            this.$form.find('.kab-service-item').removeClass('selected');
            
            // Reload therapist info
            this.loadTherapist();
            
            // Reload services for the new therapist (will cancel any pending request)
            this.loadServices();
            
            // Go back to step 1
            this.goToStep(1);
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
                        // Store locations data
                        self.locations = response.data.locations;
                        
                        // Group locations by category
                        const categories = response.data.categories || [];
                        const locationsByCategory = {};
                        const uncategorizedLocations = [];
                        
                        // Initialize categories
                        categories.forEach(cat => {
                            locationsByCategory[cat.id] = {
                                category: cat,
                                locations: []
                            };
                        });
                        
                        // Group locations
                        self.locations.forEach(location => {
                            if (location.category_id && locationsByCategory[location.category_id]) {
                                locationsByCategory[location.category_id].locations.push(location);
                            } else {
                                uncategorizedLocations.push(location);
                            }
                        });
                        
                        // Build HTML with category dropdowns
                        let html = '';
                        
                        // Check if we have any categorized locations
                        const hasCategorizedLocations = Object.values(locationsByCategory).some(catData => catData.locations.length > 0);
                        
                        if (hasCategorizedLocations) {
                            // Render categorized locations in dropdown groups
                            Object.values(locationsByCategory).forEach(catData => {
                                if (catData.locations.length > 0) {
                                    html += `<div class="kab-category-group">
                                        <div class="kab-category-header" data-category-id="${catData.category.id}">
                                            <span class="kab-category-name">${catData.category.category_name}</span>
                                            <span class="kab-category-toggle">+</span>
                                        </div>
                                        <div class="kab-category-services" style="display: none;">
                                            ${catData.locations.map(location => 
                                                `<div class="kab-category-item" data-location-id="${location.id}">
                                                    <p class="kab-category-name">${location.location_name}</p>
                                                </div>`
                                            ).join('')}
                                        </div>
                                    </div>`;
                                }
                            });
                            
                            // Add uncategorized locations directly (without category grouping)
                            if (uncategorizedLocations.length > 0) {
                                html += uncategorizedLocations.map(location => 
                                    `<div class="kab-category-item" data-location-id="${location.id}">
                                        <p class="kab-category-name">${location.location_name}</p>
                                    </div>`
                                ).join('');
                            }
                        } else {
                            // No categories, render all locations directly like before
                            html = self.locations.map(location => 
                                `<div class="kab-category-item" data-location-id="${location.id}">
                                    <p class="kab-category-name">${location.location_name}</p>
                                </div>`
                            ).join('');
                        }
                        
                        $categoriesList.html(html);
                        
                        // Initialize category toggle functionality (only if categories exist)
                        if (hasCategorizedLocations) {
                            $categoriesList.find('.kab-category-header').on('click', function() {
                                const $header = $(this);
                                const $locations = $header.next('.kab-category-services');
                                const $toggle = $header.find('.kab-category-toggle');
                                
                                // Close all other categories
                                $categoriesList.find('.kab-category-services').not($locations).slideUp(200);
                                $categoriesList.find('.kab-category-toggle').not($toggle).text('+');
                                
                                // Toggle current category
                                $locations.slideToggle(200);
                                $toggle.text($locations.is(':visible') ? '−' : '+');
                            });
                        }
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
            if (this.currentStep > 0) {
                // If on step 0, go back to step 1
                if (this.currentStep === 0) {
                    this.goToStep(1);
                } else {
                    this.goToStep(this.currentStep - 1);
                }
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
            
            // Hide progress bar on confirmation step (step 5)
            if (step === 5) {
                this.$form.find('.kab-form-progress').hide();
            } else {
                this.$form.find('.kab-form-progress').show();
                // Update progress - step 0 is a special step, so don't count it in progress
                if (step === 0) {
                    // When on therapist selection step, show step 1 progress
                    this.$form.find('.kab-progress-text').text(`1/${this.maxStep}`);
                    this.$form.find('.kab-progress-fill').css('width', '25%');
                } else {
                    const progressPercentage = (step / this.maxStep) * 100;
                    this.$form.find('.kab-progress-text').text(`${step}/${this.maxStep}`);
                    this.$form.find('.kab-progress-fill').css('width', progressPercentage + '%');
                }
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
                case 0:
                    // Therapist selection step - always reload all therapists when going to step 0
                    // This ensures we show all therapists, not just the previously selected one
                    this.loadAllTherapists();
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
                    // Confirmation step - show loading state
                    // submitBooking will be called separately
                    break;
            }
        }
        
        /**
         * Select a service
         */
        selectService(serviceId, autoAdvance = true) {
            // Prevent selection while loading services
            if (this.isLoadingServices) {
                return;
            }
            
            // Verify service exists in current services array
            const service = this.services.find(s => s.id === serviceId);
            if (!service) {
                return; // Service doesn't belong to current therapist
            }
            
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
            if (this.therapist) {
                therapistName = this.therapist.name;
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
                    const selectedLocation = this.locations.find(location => location.id == this.locationId);
                    if (selectedLocation && selectedLocation.location_name) {
                        locationName = selectedLocation.location_name;
                    }
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
         * Reset form to initial state
         */
        resetForm() {
            // Reset all selections
            // Read therapistId from data attribute to ensure we use the correct one
            // This ensures we use the original pre-selected therapist when reopening popup
            const therapistIdData = this.$form.data('therapist-id') || '';
            if (therapistIdData) {
                this.therapistId = therapistIdData;
            }
            
            this.serviceId = '';
            this.locationId = '';
            this.selectedDate = '';
            this.selectedTime = '';
            
            // Reset UI selections
            this.$form.find('.kab-category-item').removeClass('selected');
            this.$form.find('.kab-date-item').removeClass('selected');
            this.$form.find('.kab-time-slot').removeClass('selected');
            
            // Clear form fields
            this.$form.find('#kab-first-name-tf').val('');
            this.$form.find('#kab-email-tf').val('');
            this.$form.find('#kab-phone-tf').val('');
            this.$form.find('#kab-terms-tf').prop('checked', false);
            
            // Reset navigation buttons
            this.$form.find('.kab-next-btn').prop('disabled', true);
            this.$form.find('.kab-prev-btn').prop('disabled', false);
            
            // Show progress bar again
            this.$form.find('.kab-form-progress').show();
            
            // Go back to step 1
            this.goToStep(1);
            
            // Reload services for the therapist (will use therapistId from data attribute)
            if (this.therapistId) {
                this.loadServices();
                // Also reload therapist info to show correct therapist card
                this.loadTherapist();
            }
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
            
            // Validate that we have all booking data
            if (!this.serviceId || !this.therapistId || !this.selectedTime) {
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
                    specialist_id: this.therapistId,
                    location_id: this.locationId, // Include location_id
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
                        
                        // No redirect - stay on confirmation step
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
        // Initialize therapist-first booking forms (skip forms in hidden popups - they'll be initialized when popup opens)
        $('.kab-booking-form.kab-therapist-first').not('.kab-popup .kab-booking-form').each(function() {
            const $form = $(this);
            const instance = new KABTherapistFirstBookingForm($form);
            $form.data('kab-form-instance', instance);
        });
        
        // Initialize popup functionality for therapist-first forms
        $('.kab-button[data-popup-target]').click(function() {
            const $button = $(this);
            const popupId = $button.data('popup-target');
            const $popup = $('#' + popupId);
            
            // Get therapist_id from button
            const therapistId = $button.data('specialist-id') || $button.data('id') || '';
            
            // Show popup
            $popup.show();
            
            // Handle therapist-first booking form
            const $form = $popup.find('.kab-booking-form.kab-therapist-first');
            if ($form.length) {
                // Apply data attributes from button if present
                if (therapistId) {
                    $form.attr('data-therapist-id', therapistId);
                }
                
                // Get or create form instance
                let formInstance = $form.data('kab-form-instance');
                if (!formInstance) {
                    formInstance = new KABTherapistFirstBookingForm($form);
                    $form.data('kab-form-instance', formInstance);
                } else {
                    // Reset form state when reopening popup
                    // Always reset to original pre-selected therapist from button
                    formInstance.therapistId = therapistId || '';
                    formInstance.preselectedTherapistId = therapistId || '';
                    formInstance.serviceId = '';
                    formInstance.locationId = '';
                    formInstance.selectedDate = '';
                    formInstance.selectedTime = '';
                    
                    // Update form's data attribute to match button
                    if (therapistId) {
                        $form.attr('data-therapist-id', therapistId);
                    }
                    
                    // Reset form (will reload services for original therapist)
                    formInstance.resetForm();
                }
            }
        });
    });

})(jQuery);

