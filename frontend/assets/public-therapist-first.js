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
            this.categoryId = ''; // Store selected category ID
            this.selectedDate = '';
            this.selectedTime = '';
            this.services = [];
            this.locations = []; // Store locations data
            this.therapist = null;
            this.allTherapists = []; // Store all therapists for selection step
            
            // Track pending AJAX requests to prevent race conditions
            this.pendingCategoriesRequest = null;
            this.loadingCategoriesForTherapistId = null;
            this.isLoadingCategories = false;
            
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
            this.loadCategories();
        }
        
        /**
         * Initialize events
         */
        initEvents() {
            const self = this;
            
            // Category selection (step 1) - categories use kab-category-item class
            this.$form.on('click', '.kab-category-item[data-category-id]', function() {
                const categoryId = $(this).data('category-id');
                self.selectCategory(categoryId);
            });
            
            // Location selection (step 2) - locations use kab-category-item class
            this.$form.on('click', '.kab-category-item[data-location-id]', function() {
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
         * Load categories for the therapist (step 1) - grouped by parent categories
         */
        loadCategories() {
            const self = this;
            const $categoriesList = this.$form.find('.kab-categories-list');
            
            // Cancel any pending request
            if (this.pendingCategoriesRequest) {
                this.pendingCategoriesRequest.abort();
                this.pendingCategoriesRequest = null;
            }
            
            // Store which therapist we're loading categories for
            const loadingForTherapistId = this.therapistId;
            this.loadingCategoriesForTherapistId = loadingForTherapistId;
            this.isLoadingCategories = true;
            
            // Clear old categories immediately and show loading
            $categoriesList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            // Clear current category selection
            this.categoryId = '';
            this.serviceId = '';
            
            this.pendingCategoriesRequest = $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_categories_for_therapist',
                    therapist_id: loadingForTherapistId,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    // Only process if this response is for the current therapist
                    if (self.loadingCategoriesForTherapistId !== loadingForTherapistId) {
                        return; // Ignore stale response
                    }
                    
                    // Clear the pending request reference
                    self.pendingCategoriesRequest = null;
                    self.isLoadingCategories = false;
                    self.loadingCategoriesForTherapistId = null;
                    
                    if (response.success && response.data.categories) {
                        const categories = response.data.categories;
                        const parentCategories = response.data.parent_categories || [];
                        
                        // Group categories by parent_category_id
                        const categoriesByParent = {};
                        const uncategorizedCategories = [];
                        
                        // Initialize parent categories
                        parentCategories.forEach(parentCat => {
                            categoriesByParent[parentCat.id] = {
                                parent: parentCat,
                                categories: []
                            };
                        });
                        
                        // Group categories
                        categories.forEach(category => {
                            if (category.parent_category_id && categoriesByParent[category.parent_category_id]) {
                                categoriesByParent[category.parent_category_id].categories.push(category);
                            } else {
                                uncategorizedCategories.push(category);
                            }
                        });
                        
                        // Build HTML with parent category groups
                        let html = '';
                        
                        // Check if we have any categorized categories
                        const hasCategorizedCategories = Object.values(categoriesByParent).some(parentData => parentData.categories.length > 0);
                        
                        if (hasCategorizedCategories) {
                            // Render categorized categories in collapsible groups
                            Object.values(categoriesByParent).forEach(parentData => {
                                if (parentData.categories.length > 0) {
                                    html += `<div class="kab-category-group">
                                        <div class="kab-category-header" data-parent-category-id="${parentData.parent.id}">
                                            <span class="kab-category-name">${parentData.parent.parent_category_name}</span>
                                            <span class="kab-category-toggle">+</span>
                                        </div>
                                        <div class="kab-category-services" style="display: none;">
                                            ${parentData.categories.map(category => 
                                                `<div class="kab-category-item" data-category-id="${category.id}">
                                                    <p class="kab-category-name">${category.category_name}</p>
                                                </div>`
                                            ).join('')}
                                        </div>
                                    </div>`;
                                }
                            });
                            
                            // Add uncategorized categories directly (without parent grouping)
                            if (uncategorizedCategories.length > 0) {
                                html += uncategorizedCategories.map(category => 
                                    `<div class="kab-category-item" data-category-id="${category.id}">
                                        <p class="kab-category-name">${category.category_name}</p>
                                    </div>`
                                ).join('');
                            }
                        } else {
                            // No parent categories, render all categories directly
                            html = categories.map(category => 
                                `<div class="kab-category-item" data-category-id="${category.id}">
                                    <p class="kab-category-name">${category.category_name}</p>
                                </div>`
                            ).join('');
                        }
                        
                        $categoriesList.html(html);
                        
                        // Initialize parent category toggle functionality (only if parent categories exist)
                        if (hasCategorizedCategories) {
                            $categoriesList.find('.kab-category-header').on('click', function(e) {
                                // Don't trigger category selection when clicking parent header
                                e.stopPropagation();
                                
                                const $header = $(this);
                                const $categories = $header.next('.kab-category-services');
                                const $toggle = $header.find('.kab-category-toggle');
                                
                                // Close all other parent categories
                                $categoriesList.find('.kab-category-services').not($categories).slideUp(200);
                                $categoriesList.find('.kab-category-toggle').not($toggle).text('+');
                                
                                // Toggle current parent category
                                $categories.slideToggle(200);
                                $toggle.text($categories.is(':visible') ? '−' : '+');
                            });
                        }
                    } else {
                        $categoriesList.html('<div class="kab-no-data">No categories available for this therapist.</div>');
                    }
                },
                error: function(xhr, status, error) {
                    // Only process error if this response is for the current therapist
                    if (self.loadingCategoriesForTherapistId !== loadingForTherapistId) {
                        return; // Ignore stale response
                    }
                    
                    // Clear the pending request reference
                    self.pendingCategoriesRequest = null;
                    self.isLoadingCategories = false;
                    self.loadingCategoriesForTherapistId = null;
                    
                    // Don't show error if request was aborted (user changed therapist)
                    if (status !== 'abort') {
                        $categoriesList.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
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
                        // Location assignment removed - show all therapists
                        self.allTherapists = response.data.therapists;
                        
                        if (self.allTherapists.length === 0) {
                            $therapistsList.html('<div class="kab-no-data">No therapists available.</div>');
                            return;
                        }
                        
                        let html = '<div class="kab-therapists-grid">';
                        
                        $.each(self.allTherapists, function(index, therapist) {
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
            
            // Reset selections immediately
            this.serviceId = '';
            this.locationId = '';
            this.categoryId = '';
            this.selectedDate = '';
            this.selectedTime = '';
            this.services = [];
            
            // Clear any selected UI
            this.$form.find('.kab-category-item[data-category-id]').removeClass('selected');
            
            // Reload therapist info
            this.loadTherapist();
            
            // Reload categories for the new therapist (will cancel any pending request)
            this.loadCategories();
            
            // Go back to step 1
            this.goToStep(1);
        }
        
        /**
         * Select a category
         */
        selectCategory(categoryId, autoAdvance = true) {
            this.categoryId = categoryId;
            
            // Update UI
            this.$form.find('.kab-category-item[data-category-id]').removeClass('selected');
            this.$form.find(`.kab-category-item[data-category-id="${categoryId}"]`).addClass('selected');
            
            // Automatically proceed to next step if autoAdvance is true
            if (autoAdvance) {
                setTimeout(() => {
                    this.nextStep();
                }, 300); // Small delay for visual feedback
            }
        }
        
        /**
         * Load locations by category (step 2) - grouped by location categories
         */
        loadLocationsByCategory() {
            const self = this;
            const $locationsList = this.$form.find('.kab-locations-list');
            
            if (!this.categoryId) {
                $locationsList.html('<div class="kab-no-data">Please select a category first.</div>');
                return;
            }
            
            $locationsList.html('<div class="kab-loading">' + kab_vars.loading + '</div>');
            
            $.ajax({
                url: kab_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'kab_get_locations_by_category',
                    category_id: this.categoryId,
                    nonce: kab_vars.nonce
                },
                success: function(response) {
                    if (response.success && response.data.locations) {
                        // Store locations data
                        self.locations = response.data.locations;
                        const locationCategories = response.data.categories || [];
                        
                        // Location assignment removed - use all locations for the service
                        const availableLocations = self.locations;
                        
                        if (availableLocations.length === 0) {
                            $locationsList.html('<div class="kab-no-data">No locations available for this category combination.</div>');
                            return;
                        }
                        
                        // Group locations by location category
                                const locationsByCategory = {};
                                const uncategorizedLocations = [];
                                
                                // Initialize location categories
                                locationCategories.forEach(cat => {
                                    locationsByCategory[cat.id] = {
                                        category: cat,
                                        locations: []
                                    };
                                });
                                
                                // Group locations
                                availableLocations.forEach(location => {
                                    if (location.category_id && locationsByCategory[location.category_id]) {
                                        locationsByCategory[location.category_id].locations.push(location);
                                    } else {
                                        uncategorizedLocations.push(location);
                                    }
                                });
                                
                                // Build HTML with location category groups
                                let html = '';
                                
                                // Check if we have any categorized locations
                                const hasCategorizedLocations = Object.values(locationsByCategory).some(catData => catData.locations.length > 0);
                                
                                if (hasCategorizedLocations) {
                                    // Render categorized locations in collapsible groups
                                    Object.values(locationsByCategory).forEach(catData => {
                                        if (catData.locations.length > 0) {
                                            html += `<div class="kab-category-group">
                                                <div class="kab-category-header" data-location-category-id="${catData.category.id}">
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
                                    // No location categories, render all locations directly
                                    html = availableLocations.map(location => 
                                        `<div class="kab-category-item" data-location-id="${location.id}">
                                            <p class="kab-category-name">${location.location_name}</p>
                                        </div>`
                                    ).join('');
                                }
                                
                                $locationsList.html(html);
                                
                                // Initialize location category toggle functionality (only if location categories exist)
                                if (hasCategorizedLocations) {
                                    $locationsList.find('.kab-category-header').on('click', function(e) {
                                        // Don't trigger location selection when clicking category header
                                        e.stopPropagation();
                                        
                                        const $header = $(this);
                                        const $locations = $header.next('.kab-category-services');
                                        const $toggle = $header.find('.kab-category-toggle');
                                        
                                        // Close all other location categories
                                        $locationsList.find('.kab-category-services').not($locations).slideUp(200);
                                        $locationsList.find('.kab-category-toggle').not($toggle).text('+');
                                        
                                        // Toggle current location category
                                        $locations.slideToggle(200);
                                        $toggle.text($locations.is(':visible') ? '−' : '+');
                                    });
                                }
                    } else {
                        $locationsList.html('<div class="kab-no-data">No locations available for this category.</div>');
                    }
                },
                error: function() {
                    $locationsList.html('<div class="kab-no-data">' + kab_vars.error + '</div>');
                }
            });
        }
        
        /**
         * Render locations HTML (helper function)
         */
        renderLocations(locations, locationCategories, $container) {
            const self = this;
            
            // Group locations by location category
            const locationsByCategory = {};
            const uncategorizedLocations = [];
            
            // Initialize location categories
            locationCategories.forEach(cat => {
                locationsByCategory[cat.id] = {
                    category: cat,
                    locations: []
                };
            });
            
            // Group locations
            locations.forEach(location => {
                if (location.category_id && locationsByCategory[location.category_id]) {
                    locationsByCategory[location.category_id].locations.push(location);
                } else {
                    uncategorizedLocations.push(location);
                }
            });
            
            // Build HTML
            let html = '';
            const hasCategorizedLocations = Object.values(locationsByCategory).some(catData => catData.locations.length > 0);
            
            if (hasCategorizedLocations) {
                Object.values(locationsByCategory).forEach(catData => {
                    if (catData.locations.length > 0) {
                        html += `<div class="kab-category-group">
                            <div class="kab-category-header" data-location-category-id="${catData.category.id}">
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
                
                if (uncategorizedLocations.length > 0) {
                    html += uncategorizedLocations.map(location => 
                        `<div class="kab-category-item" data-location-id="${location.id}">
                            <p class="kab-category-name">${location.location_name}</p>
                        </div>`
                    ).join('');
                }
            } else {
                html = locations.map(location => 
                    `<div class="kab-category-item" data-location-id="${location.id}">
                        <p class="kab-category-name">${location.location_name}</p>
                    </div>`
                ).join('');
            }
            
            $container.html(html);
            
            if (hasCategorizedLocations) {
                $container.find('.kab-category-header').on('click', function(e) {
                    e.stopPropagation();
                    const $header = $(this);
                    const $locations = $header.next('.kab-category-services');
                    const $toggle = $header.find('.kab-category-toggle');
                    $container.find('.kab-category-services').not($locations).slideUp(200);
                    $container.find('.kab-category-toggle').not($toggle).text('+');
                    $locations.slideToggle(200);
                    $toggle.text($locations.is(':visible') ? '−' : '+');
                });
            }
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
                    this.loadLocationsByCategory();
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
        selectLocation(locationId, autoAdvance = true) {
            const self = this;
            // Ensure locationId is stored as string for consistency
            this.locationId = String(locationId);
            
            // Update UI - locations use kab-category-item class
            this.$form.find('.kab-category-item[data-location-id]').removeClass('selected');
            this.$form.find(`.kab-category-item[data-location-id="${locationId}"]`).addClass('selected');
            
            // Auto-select service based on category + location
            if (this.categoryId) {
                $.ajax({
                    url: kab_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kab_get_service_by_category_location',
                        category_id: this.categoryId,
                        location_id: locationId,
                        nonce: kab_vars.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.service) {
                            // Store service data
                            const service = response.data.service;
                            self.serviceId = service.id;
                            
                            // Store service in services array if not already there
                            const existingService = self.services.find(s => s.id === service.id);
                            if (!existingService) {
                                self.services.push(service);
                            }
                            
                            // Automatically proceed to next step if autoAdvance is true
                            if (autoAdvance) {
                                setTimeout(() => {
                                    self.nextStep();
                                }, 300); // Small delay for visual feedback
                            }
                        } else {
                            // Service not found - show error but still allow to proceed
                            console.error('Service not found for category and location combination');
                            if (autoAdvance) {
                                setTimeout(() => {
                                    self.nextStep();
                                }, 300);
                            }
                        }
                    },
                    error: function() {
                        // Error getting service - still allow to proceed
                        console.error('Error getting service by category and location');
                        if (autoAdvance) {
                            setTimeout(() => {
                                self.nextStep();
                            }, 300);
                        }
                    }
                });
            } else {
                // No category selected - just proceed
                if (autoAdvance) {
                    setTimeout(() => {
                        this.nextStep();
                    }, 300);
                }
            }
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
            // Get service name - service is auto-selected, so get from stored services data
            let serviceName = '';
            if (this.serviceId && this.services.length > 0) {
                const selectedService = this.services.find(service => service.id === this.serviceId);
                if (selectedService && selectedService.name) {
                    serviceName = selectedService.name;
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
            let price = '0';
            if (this.serviceId && this.services.length > 0) {
                const selectedService = this.services.find(service => service.id === this.serviceId);
                if (selectedService && selectedService.price) {
                    price = selectedService.price || '0';
                    if (price && price !== '0' && !price.includes(',')) {
                        price = price + ',-';
                    }
                } else if (selectedService) {
                    price = selectedService.price || '0';
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
            this.categoryId = '';
            this.selectedDate = '';
            this.selectedTime = '';
            
            // Reset UI selections
            this.$form.find('.kab-category-item[data-category-id]').removeClass('selected');
            this.$form.find('.kab-category-item[data-location-id]').removeClass('selected');
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
            
            // Reload categories for the therapist (will use therapistId from data attribute)
            if (this.therapistId) {
                this.loadCategories();
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

