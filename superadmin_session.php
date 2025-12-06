<?php
// Use a unique session name for superadmin/owner sessions
session_name('SUPERADMIN_SESSION');
session_start();

require_once 'connection.php';

// 🔒 Check if logged in
if (!isset($_SESSION['owner_id'])) {
    header("Location: superadmin_dashboard.php");
    exit();
}

// ✅ No session timeout - sessions persist until manual logout

// 🔄 Verify owner exists in database
$owner_id = $_SESSION['owner_id'];

$stmt = $conn->prepare("SELECT first_name, last_name FROM owner WHERE id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();

// compatibility for non-mysqlnd setups
if (method_exists($stmt, 'get_result')) {
    $result = $stmt->get_result();
    $owner = $result->fetch_assoc();
} else {
    $stmt->bind_result($first_name, $last_name);
    $stmt->fetch();
    $owner = [
        'first_name' => $first_name,
        'last_name' => $last_name
    ];
}
$stmt->close();

// ❌ If no matching owner found, destroy session
if (!$owner) {
    session_unset();
    session_destroy();
    header("Location: customer_login.php?error=not_found");
    exit();
}

// ✅ Multiple sessions allowed - No session token validation
// Users can now login from multiple devices/browsers simultaneously

// ✅ Store owner's name for page use
$owner_name = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
?>
