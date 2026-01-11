# Konfidens Appointment Booking Plugin

A comprehensive WordPress plugin for appointment booking using the Konfidens API. This plugin allows users to book appointments with therapists based on various criteria such as location, service type, and availability.

## Overview

The Konfidens Appointment Booking plugin integrates with the Konfidens API to provide a seamless booking experience for therapy services. It offers flexible configuration options and enabling contextual booking options throughout the website.

## Features

- Integration with Konfidens API for real-time appointment booking
- Mapping of therapists to service categories
- Service categorization by location type
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

## Backend Architecture

### Database Tables

The plugin creates several custom database tables upon activation:

1. **`{prefix}_kab_location`**: Add Location
   - `id`: Auto-increment ID
   - `location_name`: Location Name
   - `created_at`/`updated_at`: Timestamps

2. **`{prefix}_kab_location_service`**: Add mapping of Location Service
   - `id`: Auto-increment ID
   - `service_id`: Konfidens service ID
   - `location_ids`: Location IDs
   - `category_id`: Service category ID
   - `created_at`/`updated_at`: Timestamps

3. **`{prefix}_kab_setting`**: Stores API configuration and settings
   - `id`: Auto-increment ID
   - `base_url`: Konfidens API base URL
   - `api_key`: API authentication key
   - `clinic_id`: Clinic identifier
   - `primary_color`: UI color setting
   - `booking_msg`: Customizable booking message
   - `created_at`/`updated_at`: Timestamps

4. **`{prefix}_kab_location_specialist`: Add mapping of location and specialist
   - `id`: Auto-increment ID
   - `location_ids`: Location IDs
   - `specialist_id`: Specialist ID
   - `created_at`/`updated_at`: Timestamps

5. **`{prefix}_kab_booking_form_data`**: Stores booking information
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
   - Services are displayed in the backend services menu inside Konfidens
   - Services are displayed in the booking form

2. **Location Selection**: Show all location based on selected service

2. **Specialist Selection**:
   - When a service is selected, the plugin fetches specialists who can provide that service
   - Specialists are displayed with their profile image, name, and description
   - The plugin checks for available time slots for each specialist

3. **Date & Time Selection**:
   - Available dates are fetched for the selected specialist and service
   - The calendar shows only dates with available slots
   - When a date is selected, available time slots are displayed

4. **Booking Process**:
   - User fills in personal information
   - Form validates all required fields
   - Google reCAPTCHA verifies the user is not a bot
   - Booking request is sent to the API
   - Booking details are stored in the local database
   - Confirmation email is sent
   - User receives a confirmation message

4. **Services → Specialists**:
   - Each service can be provided by multiple specialists
   - This relationship comes from the Konfidens API

5. **Specialists → Categories**:
   - Specialists can be associated with specific categories
   - This allows filtering specialists by location type

## Frontend Components

### Shortcodes

1. **`[su_button]Book Now[/su_button]`**: Creates a button that opens the booking form in a popup
   - Flow: Service → Location → Therapist → Date & Time → Personal Details

2. **`[su_button id="SPECIALIST_ID"]Book With This Therapist[/su_button]`**: Creates a button with pre-selected therapist
   - Flow: Therapist (pre-selected) → Services (according to therapist) → Location → Date & Time → Personal Details
   - Note: Therapist can be changed during the booking process

### multistep form
step 1. **Service Selection**: Shows all available services
step 2. **Category Selection**: Show all category based on selected service
step 3. **Specialist Selection**: filter specialist based on selected service and category and Uses a slider to browse and select specialists
step 4. **Date Picker**: Custom calendar showing only available dates and **Time Slot Selection**: Displays available time slots for selected date
step 5. **Form Submission**: Handles validation and API submission
step 6. **Booking Confirmation**: Shows success message and booking details

If service is preselected in shortcode then -
step 1. **Service Selection**: skipped
step 2. **Category Selection**: Show all category based on selected service
step 3. **Specialist Selection**: filter specialist based on selected service and category and Uses a slider to browse and select specialists
step 4. **Date Picker**: Custom calendar showing only available dates and **Time Slot Selection**: Displays available time slots for selected date
step 5. **Form Submission**: Handles validation and API submission
step 6. **Booking Confirmation**: Shows success message and booking details

If specialist is preselected in shortcode then -
step 1. **Service Selection**: Shows all available services based on selected specialist
step 2. **Category Selection**: Show all category based on selected service and specialist also in this step there is an option to change specialist
step 3. **Specialist Selection**: skipped
step 4. **Date Picker**: Custom calendar showing only available dates and **Time Slot Selection**: Displays available time slots for selected date
step 5. **Form Submission**: Handles validation and API submission
step 6. **Booking Confirmation**: Shows success message and booking details


## Admin Interface

The plugin provides several admin pages for configuration:

1. **Konfidens**: Main menu item
Sub menu -
2. **Services**: Show all service information (service_id, service_name, service_duration, service_price, total_booking)
3. **Categories**: Manages category information (add category, remove category)
4. **Mapping Categories with Therapist**: Maps categories to therapists (add, remove, update). Multiple categories can be mapped to a single therapist.
5. **Therapist**: Show all therapists with their specialist id
6. **Settings**: Configures API credentials and general settings (Base URL, API Key, Clinic ID, Primary Color, Booking Message, Mail settings)

## Installation

1. Upload the plugin files to the `/wp-content/plugins/konfidens-appointment-booking` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Konfidens' > 'Settings' to configure API credentials
4. Set up other settings as needed

## Plugin Structure

The plugin is organized into the following structure:

### Core Files
- `konfidens-appointment-booking.php`: Main plugin file with shortcodes
- `kab-database.php`: Database functionality
- `kab-activator.php`: Plugin activation functionality
- `uninstall.php`: Standard WordPress uninstallation file

### Admin Files
- `admin/kab-admin-settings.php`: Admin settings functionality
- `admin/kab-admin-service.php`: Service management functionality
- `admin/kab-admin-specialist.php`: Specialist management functionality
- `admin/kab-admin-location.php`: Location management functionality
- `admin/assets/admin.css`: Admin CSS styles
- `admin/assets/admin.js`: Admin JavaScript functionality

### Frontend Files
- `frontend/kab-frontend-form.php`: Frontend form functionality
- `frontend/kab-email.php`: Email notification functionality
- `frontend/assets/public.css`: Frontend CSS styles
- `frontend/assets/public.js`: Frontend JavaScript functionality
- `frontend/templates/form-template.php`: Form template
- `frontend/templates/thank-you-template.php`: Thank you page template
- `frontend/templates/email-template.php`: Email template

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

## Email Notifications

When a booking is made, the plugin sends an email notification to the configured email address. The email includes:

- Booking ID
- Service name
- Specialist name
- Date and time
- Patient information (name, email, phone)
- Page URL where the booking was made

For direct bookings (via URL), a separate notification is sent with available information.

## Thank You Page

The plugin creates a custom endpoint `/booking/thanks` that displays a thank you message after successful booking. This page can be accessed directly via URL with parameters:

```
/booking/thanks?serviceId=XXX&serviceTitle=XXX&practitionerId=XXX&startsAt=XXX&bookingId=XXX
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- cURL extension enabled
- Valid Konfidens API credentials

## Security Features

- Input sanitization for all user inputs
- Google reCAPTCHA integration
- API key protection
- Nonce verification for AJAX requests

## Customization

The plugin's appearance can be customized through:

1. CSS files in the `admin/assets` and `frontend/assets` directories
2. Color settings in the admin interface
3. Custom booking confirmation message in settings

## Troubleshooting

If bookings are not working properly, check:

1. API credentials in the settings
2. Service and specialist mappings
3. Browser console for JavaScript errors
4. WordPress error log for PHP errors
5. Network tab in browser developer tools for API response errors

## Support

For support inquiries, please contact the plugin author.

## License

This plugin is licensed under the GPL v2 or later.

---
