# Konfidens Appointment Booking Plugin

**Version:** 1.1.1

A comprehensive WordPress plugin for appointment booking using the Konfidens API. This plugin allows users to book appointments with therapists based on various criteria such as location, service type, and availability.

## Overview

The Konfidens Appointment Booking plugin integrates with the Konfidens API to provide a seamless booking experience for therapy services. It offers flexible configuration options and enables contextual booking options throughout the website. The plugin supports multiple booking flows including standard category-based booking and therapist-first booking.

## Features

- Integration with Konfidens API for real-time appointment booking
- Service management with category assignment
- Service category management with parent category support
- Location management with category assignment
- Therapist/specialist management
- Real-time availability checking
- Interactive calendar for date selection
- Time slot selection
- Email notifications for bookings
- Booking confirmation system
- Thank you page for completed bookings
- Google reCAPTCHA integration for spam protection
- Responsive design for mobile and desktop
- Popup booking form option
- Shortcode integration
- Service sets for predefined service groups

## Backend Architecture

### Database Tables

The plugin creates several custom database tables upon activation:

1. **`{prefix}_kab_location`**: Stores location information
   - `id`: Auto-increment ID
   - `location_name`: Location Name
   - `category_id`: Location category ID
   - `created_at`/`updated_at`: Timestamps

2. **`{prefix}_kab_location_category`**: Stores location categories
   - `id`: Auto-increment ID
   - `category_name`: Category name
   - `created_at`/`updated_at`: Timestamps

3. **`{prefix}_kab_location_service`**: Maps services to locations and categories
   - `id`: Auto-increment ID
   - `service_id`: Konfidens service ID
   - `location_ids`: Single location ID (stored as text, despite plural name - each service has one location)
   - `category_id`: Service category ID
   - `created_at`/`updated_at`: Timestamps

4. **`{prefix}_kab_service_category`**: Stores service categories
   - `id`: Auto-increment ID
   - `category_name`: Category name
   - `parent_category_id`: Parent category ID (for hierarchical categories)
   - `created_at`/`updated_at`: Timestamps

5. **`{prefix}_kab_service_parent_category`**: Stores parent categories for service categories
   - `id`: Auto-increment ID
   - `parent_category_name`: Parent category name
   - `created_at`/`updated_at`: Timestamps

6. **`{prefix}_kab_setting`**: Stores API configuration and settings
   - `id`: Auto-increment ID
   - `base_url`: Konfidens API base URL
   - `api_key`: API authentication key
   - `clinic_id`: Clinic identifier
   - `primary_color`: UI color setting
   - `booking_msg`: Customizable booking message
   - `created_at`/`updated_at`: Timestamps

7. **`{prefix}_kab_location_specialist`**: Stores specialist metadata
   - `id`: Auto-increment ID
   - `location_ids`: **Deprecated** - Location assignment functionality has been removed. This field is no longer used for filtering specialists. Kept for backward compatibility only.
   - `specialist_id`: Specialist ID
   - `tags`: Specialist tags (comma-separated) - **This is the only actively used field**
   - `created_at`/`updated_at`: Timestamps
   
   **Note:** Specialists are no longer filtered by location. All specialists available for a service are shown regardless of location assignments. The table is primarily used for storing specialist tags.

8. **`{prefix}_kab_booking_form_data`**: Stores booking information
   - `id`: Auto-increment ID
   - `service_ids`: Service ID
   - `service_name`: Service name
   - `specialist_ids`: Therapist ID
   - `specialist_name`: Therapist name
   - `timeslot_date`: Appointment date
   - `timeslot_time`: Appointment time
   - `first_name`: Client first name
   - `last_name`: Client last name
   - `email`: Client email
   - `phone`: Client phone
   - `booking_id`: Unique booking identifier
   - `booked_from`: Booking source (1=form, 2=direct)
   - `created_at`/`updated_at`: Timestamps

### API Integration

The plugin communicates with the Konfidens API using the following endpoints:

1. **Services API**: `/services?clinic_id={CLINIC_ID}`
   - Retrieves all available services for the clinic
   - Filters out disabled services (where `code !== null` or `enabled_by_specialists` is empty)

2. **Specialists API**: `/specialists?clinic_id={CLINIC_ID}&service_id={SERVICE_ID}`
   - Retrieves specialists/therapists available for a specific service
   - Optional filtering by specialist ID

3. **Timeslots API**: `/timeslots?clinic_id={CLINIC_ID}&from_date={FROM_DATE}&to_date={TO_DATE}&service_id={SERVICE_ID}&specialist_id={SPECIALIST_ID}`
   - Retrieves available time slots for a specific service and specialist within a date range

4. **Bookings API**: `/bookings`
   - Creates a new booking with patient data and booking token

### Data Flow

1. **Service Selection**:
   - The plugin fetches all available services from the Konfidens API
   - Services are filtered to show only enabled services
   - Services can be assigned to categories in the admin panel
   - Services are displayed in the booking form

2. **Location Selection**: 
   - Shows all locations based on selected service
   - Locations can be assigned to categories
   - Locations are filtered based on service assignments

3. **Specialist Selection**:
   - When a service is selected, the plugin fetches specialists who can provide that service
   - Specialists are displayed with their profile image, name, and description
   - The plugin checks for available time slots for each specialist

4. **Date & Time Selection**:
   - Available dates are fetched for the selected specialist and service
   - The calendar shows only dates with available slots
   - When a date is selected, available time slots are displayed

5. **Booking Process**:
   - User fills in personal information
   - Form validates all required fields
   - Google reCAPTCHA verifies the user is not a bot
   - Booking request is sent to the API
   - Booking details are stored in the local database
   - Confirmation email is sent
   - User receives a confirmation message

## Frontend Components

### Shortcodes

The plugin provides a `[su_button]` shortcode to create booking buttons that open the booking form in a popup.

**Available Parameters:**
- `id` (optional): Pre-select a therapist/specialist by their ID
- `set` (optional): Use a service set ID to filter categories and locations (only shows categories/locations for services in the set)
- `background` (optional): Custom background color for the button
- `class` (optional): Additional CSS classes for the button

**Usage Examples:**

1. **`[su_button]Book Now[/su_button]`**: Creates a button that opens the booking form in a popup
   - Flow: Category → Location → Therapist → Date & Time → Personal Details

2. **`[su_button id="SPECIALIST_ID"]Book With This Therapist[/su_button]`**: Creates a button with pre-selected therapist
   - Flow: Therapist (pre-selected) → Services (according to therapist) → Location → Date & Time → Personal Details
   - Note: Therapist can be changed during the booking process

3. **`[su_button set="SET_ID"]Book Now[/su_button]`**: Creates a button filtered by service set
   - Flow: Category (filtered) → Location (filtered) → Therapist → Date & Time → Personal Details
   - Only shows categories and locations that contain services from the specified service set
   - Service set ID can be found in the Service Sets admin page

4. **`[su_button background="#007bff" class="custom-class"]Book Now[/su_button]`**: Custom styled button
   - Same flow as standard button with custom styling

### Multistep Form

The plugin uses a multi-step form with progress tracking. The form adapts based on the booking flow selected.

**Standard Flow (Category-based):**
- Step 1: **Category Selection**: Shows all available service categories (grouped by parent categories if configured)
- Step 2: **Location Selection**: Shows locations based on selected category
- Step 3: **Specialist Selection**: Shows all specialists available for the selected service (not filtered by location). Uses a slider to browse and select specialists
- Step 4: **Date Picker**: Custom calendar showing only available dates and **Time Slot Selection**: Displays available time slots for selected date
- Step 5: **Form Submission**: Handles validation, reCAPTCHA verification, and API submission
- Step 6: **Booking Confirmation**: Shows success message and booking details

**Therapist-First Flow (when specialist is pre-selected):**
- Step 1: **Service Selection**: Shows all available services that the pre-selected therapist can provide
- Step 2: **Location Selection**: Shows locations based on selected service, with option to change therapist
- Step 3: **Specialist Selection**: Skipped (therapist already selected)
- Step 4: **Date Picker**: Custom calendar showing only available dates and **Time Slot Selection**: Displays available time slots for selected date
- Step 5: **Form Submission**: Handles validation, reCAPTCHA verification, and API submission
- Step 6: **Booking Confirmation**: Shows success message and booking details

## Admin Interface

The plugin provides several admin pages for configuration accessible under the "Konfidens" menu:

1. **Dashboard**: Overview page (main menu item)

2. **Service Categories**: Manage service categories and parent categories
   - Create, edit, and delete service categories
   - Assign parent categories for hierarchical organization
   - Manage parent categories separately

3. **Services**: View and manage services from the Konfidens API
   - Display service information (service_id, service_name, service_duration, service_price, total_booking)
   - Assign categories to services
   - Assign locations to services (each service can have one location)
   - Services are automatically imported from the API

4. **Therapists**: View and manage therapists/specialists
   - Display all therapists with their specialist ID
   - Assign tags to therapists for filtering and organization
   - **Note:** Location assignment for therapists has been removed. All therapists available for a service are shown regardless of location.

5. **Locations**: Manage locations and location categories
   - Create, edit, and delete locations
   - Create and manage location categories
   - Assign categories to locations
   - Default locations are created on plugin activation: "Online Video Samtale", "Hjemmebesøk", "På vårt kontor"

6. **Service Sets**: Create and manage predefined groups of services
   - Create named service sets containing multiple services
   - Edit and delete existing service sets
   - Service sets are automatically cleaned to remove disabled services
   - Use service sets in shortcodes with `set="SET_ID"` parameter
   - When using a service set, the booking form only shows categories and locations that contain services from that set
   - Service set ID is displayed in the Service Sets admin page for easy copy-paste

7. **Settings**: Configure API credentials and general settings
   - Base URL: Konfidens API base URL
   - API Key: Authentication key for API requests
   - Clinic ID: Your clinic identifier
   - Primary Color: Customize the plugin's primary color theme
   - Booking Message: Customizable confirmation message
   - Email Settings: Configure email recipient and subject
   - Google reCAPTCHA: Enable and configure reCAPTCHA v3 for spam protection

8. **Bookings**: View all booking records
   - Display all bookings with details
   - Shows booking source (form or direct URL)
   - Includes pagination for large booking lists

## Installation

1. Upload the plugin files to the `/wp-content/plugins/konfidens-appointment-booking` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically:
   - Create all necessary database tables
   - Set default options
   - Create default locations (if none exist)
   - Register custom endpoints
4. Go to 'Konfidens' > 'Settings' to configure API credentials:
   - Enter your Konfidens API Base URL
   - Enter your API Key
   - Enter your Clinic ID
5. Configure additional settings:
   - Set primary color for the booking form
   - Customize booking confirmation message
   - Configure email notifications
   - Enable and configure Google reCAPTCHA (optional)
6. Set up your data:
   - Import services from the API (automatic on first load)
   - Create service categories and assign services
   - Assign locations to services
   - Assign locations and tags to therapists

## Plugin Structure

The plugin is organized into the following structure:

### Core Files
- `konfidens-appointment-booking.php`: Main plugin file with shortcodes
- `kab-database.php`: Database functionality
- `kab-activator.php`: Plugin activation functionality
- `uninstall.php`: Standard WordPress uninstallation file

### Admin Files
- `admin/kab-admin-settings.php`: Admin settings functionality
- `admin/kab-admin-service-category.php`: Service category management
- `admin/kab-admin-service.php`: Service management functionality
- `admin/kab-admin-specialist.php`: Specialist management functionality
- `admin/kab-admin-location.php`: Location management functionality
- `admin/assets/admin.css`: Admin CSS styles
- `admin/assets/admin.js`: Admin JavaScript functionality

### Frontend Files
- `frontend/kab-frontend-form.php`: Frontend form functionality and AJAX handlers
- `frontend/kab-email.php`: Email notification functionality (admin and user notifications)
- `frontend/assets/public.css`: Frontend CSS styles
- `frontend/assets/public.js`: Frontend JavaScript functionality for standard form flow
- `frontend/assets/public-therapist-first.js`: Frontend JavaScript for therapist-first flow
- `frontend/templates/form-template.php`: Standard form template (category-based flow)
- `frontend/templates/form-template-therapist-first.php`: Therapist-first form template
- `frontend/templates/thank-you-template.php`: Thank you page template
- `frontend/templates/email-template.php`: Email template for notifications

## Usage

### Popup Form Buttons

Add a button that opens the booking form in a popup with full flow:

```
[su_button]Book Now[/su_button]
```

To pre-select a specific therapist:

```
[su_button id="SPECIALIST_ID"]Book With This Therapist[/su_button]
```

To customize button appearance:

```
[su_button background="#007bff" class="my-custom-class"]Book Now[/su_button]
```

### Service Sets

Service sets can be created in the admin panel to group related services together. When you use a service set in a shortcode with the `set` parameter, the booking form will automatically filter:

- **Categories**: Only shows service categories that contain services from the specified set
- **Locations**: Only shows locations that are associated with services from the specified set

This allows you to create focused booking experiences for specific service groups. For example, you could create a "Homepage Services" set and use it on your homepage to show only relevant categories and locations.

**Example:**
```
[su_button set="set_1234567890_5678"]Book Homepage Services[/su_button]
```

The service set ID can be found in the Service Sets admin page, where it's displayed next to each set for easy copy-paste.

## Email Notifications

When a booking is made, the plugin sends email notifications to both the administrator and the user:

**Admin Notification:**
- Sent to the configured email recipient (defaults to admin email)
- Includes booking ID, service name, specialist name, date and time
- Includes patient information (name, email, phone)
- Includes page URL where the booking was made
- Customizable subject line in settings

**User Confirmation:**
- Sent to the user's email address provided during booking
- Confirms the booking details
- Includes appointment information

For direct bookings (via URL), a separate notification is sent with available information.

## Thank You Page

The plugin creates a custom endpoint `/booking/thanks` that displays a thank you message after successful booking. This page can be accessed directly via URL with parameters:

```
/booking/thanks?serviceId=XXX&serviceTitle=XXX&practitionerId=XXX&startsAt=XXX&bookingId=XXX
```

This endpoint is useful for direct bookings from external sources or redirects after successful API bookings.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- cURL extension enabled (for API requests)
- Valid Konfidens API credentials (Base URL, API Key, Clinic ID)
- MySQL/MariaDB database

## Security Features

- Input sanitization for all user inputs
- Output escaping for all displayed data
- Google reCAPTCHA v3 integration (optional, configurable in settings)
- API key stored securely in database
- Nonce verification for all AJAX requests
- Permission checks for all admin operations
- SQL injection prevention using prepared statements
- XSS protection through WordPress sanitization functions

## Customization

The plugin's appearance can be customized through:

1. **Primary Color**: Set in Settings to customize the plugin's color scheme
2. **CSS Files**: Override styles using the CSS files in `admin/assets` and `frontend/assets` directories
3. **Booking Message**: Customize the confirmation message in Settings
4. **Button Styling**: Use `background` and `class` parameters in shortcodes
5. **Email Templates**: Customize email templates in `frontend/templates/email-template.php`

The plugin uses CSS custom properties (variables) for the primary color, making it easy to theme.

## Troubleshooting

If bookings are not working properly, check:

1. **API Credentials**: Verify Base URL, API Key, and Clinic ID in Settings
2. **Service Mappings**: Ensure services are assigned to categories and locations
3. **Specialist Mappings**: Verify therapists are available for the selected services (location filtering has been removed)
4. **Browser Console**: Check for JavaScript errors in browser developer tools
5. **WordPress Error Log**: Review PHP errors in WordPress debug log
6. **Network Tab**: Check API response errors in browser developer tools
7. **Cache**: Clear WordPress transients if services/specialists are not updating
8. **reCAPTCHA**: If enabled, verify site key and secret key are correct
9. **Email Settings**: Check email recipient and SMTP configuration if emails are not sending
10. **Rewrite Rules**: Flush rewrite rules if thank you page is not accessible (Settings > Permalinks > Save)

## Performance

The plugin includes several performance optimizations:

- **Caching**: Service data is cached for 5 minutes to reduce API calls
- **Lazy Loading**: Form steps are loaded on demand
- **Optimized Queries**: Database queries use indexes and prepared statements
- **Minimal Assets**: CSS and JavaScript are only loaded where needed

## Uninstallation

When the plugin is uninstalled:

- **Database tables are preserved** - All booking data, settings, and configurations remain intact
- Transients are cleared
- Scheduled events are removed
- This allows for safe plugin updates and re-activation without data loss

To completely remove all data, manually drop the database tables or use a database cleanup tool.

## Changelog

### Version 1.1.1
- Current stable version
- Supports category-based and therapist-first booking flows
- Service sets management
- Email notifications for admin and users
- Google reCAPTCHA v3 integration
- Custom thank you page endpoint

## Support

For support inquiries, please contact the plugin author.

**Author:** Happy  
**Author URI:** https://jobcvpro.com  
**Plugin URI:** https://jobcvpro.com/konfidens-appointment-booking

## License

This plugin is licensed under the GPL v2 or later.
