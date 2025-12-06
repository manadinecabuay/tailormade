<?php
require_once 'connection.php';

// Try to detect which session to logout from
// First check if SUPERADMIN_SESSION exists
session_name('SUPERADMIN_SESSION');
session_start();

if (isset($_SESSION['owner_id'])) {
    $owner_id = $_SESSION['owner_id'];
    // Invalidate current session token
    $conn->query("UPDATE owner SET session_token = NULL WHERE id = $owner_id");
    session_unset();
    session_destroy();
    header("Location: unified_login.php?message=logged_out");
    exit();
}

// If not superadmin, check ADMIN_SESSION
session_write_close(); // Close superadmin session check
session_name('ADMIN_SESSION');
session_start();

if (isset($_SESSION['supervisor_id'])) {
    $supervisor_id = $_SESSION['supervisor_id'];
    $conn->query("UPDATE supervisors SET session_token = NULL WHERE id = $supervisor_id");
    session_unset();
    session_destroy();
    header("Location: unified_login.php?message=logged_out");
    exit();
}

// If no session found, just redirect
session_unset();
session_destroy();
header("Location: unified_login.php?message=logged_out");
exit();
?>
