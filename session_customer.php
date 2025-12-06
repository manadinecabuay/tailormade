<?php
session_start();

// Check if customer is logged in
if (!isset($_SESSION['customerID'])) {
    header("Location: customer_login.php?status=error&msg=" . urlencode("Please login first"));
    exit();
}

// Optional: Check session timeout (30 minutes)
$timeout_duration = 1800; // 30 minutes in seconds

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: customer_login.php?status=error&msg=" . urlencode("Session expired. Please login again."));
    exit();
}

// Update last activity time
$_SESSION['LAST_ACTIVITY'] = time();
?>
