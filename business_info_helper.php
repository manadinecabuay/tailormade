<?php
/**
 * Business Information Helper
 * Provides easy access to business information across all pages
 * Include after theme_loader.php to access business info
 */

// Fetch business information if not already loaded
if (!isset($global_business_info) && isset($conn)) {
    $business_query = "SELECT * FROM business_info WHERE id = 1 LIMIT 1";
    $business_result = $conn->query($business_query);
    
    if ($business_result && $business_result->num_rows > 0) {
        $global_business_info = $business_result->fetch_assoc();
    } else {
        // Default values if no business info found
        $global_business_info = [
            'business_name' => 'Your Business',
            'about_us' => 'Welcome to our business',
            'contact' => '+1234567890',
            'email' => 'contact@business.com',
            'logo_path' => 'tailor.jpg',
            'machine_availability_message' => ''
        ];
    }
}

/**
 * Helper Functions to Display Business Information
 */

// Get business name
function get_business_name() {
    global $global_business_info;
    return htmlspecialchars($global_business_info['business_name'] ?? 'Your Business');
}

// Get business about us
function get_business_about() {
    global $global_business_info;
    return htmlspecialchars($global_business_info['about_us'] ?? '');
}

// Get business contact
function get_business_contact() {
    global $global_business_info;
    return htmlspecialchars($global_business_info['contact'] ?? '');
}

// Get business email
function get_business_email() {
    global $global_business_info;
    return htmlspecialchars($global_business_info['email'] ?? '');
}

// Get business logo path
function get_business_logo() {
    global $global_business_info;
    $logo = $global_business_info['logo_path'] ?? 'tailor.jpg';
    // Check if file exists, otherwise return default
    if (!empty($logo) && file_exists($logo)) {
        return htmlspecialchars($logo);
    }
    return 'tailor.jpg';
}

// Get machine availability message
function get_machine_message() {
    global $global_business_info;
    return htmlspecialchars($global_business_info['machine_availability_message'] ?? '');
}

// Display business name (echo)
function display_business_name() {
    echo get_business_name();
}

// Display business logo (echo img tag)
function display_business_logo($class = '', $alt = 'Business Logo') {
    $logo = get_business_logo();
    $class_attr = !empty($class) ? ' class="' . htmlspecialchars($class) . '"' : '';
    echo '<img src="' . $logo . '" alt="' . htmlspecialchars($alt) . '"' . $class_attr . '>';
}

// Display business contact info
function display_business_contact() {
    echo get_business_contact();
}

// Display business email
function display_business_email() {
    echo get_business_email();
}
?>
