<?php
// Use a unique session name for admin/supervisor sessions
session_name('ADMIN_SESSION');
session_start();

// --- 1️⃣ Check if supervisor is logged in ---
if (!isset($_SESSION['supervisor_id'])) {
    header("Location: customer_login.php");
    exit();
}

// --- 2️⃣ No session timeout - sessions persist until manual logout ---

// --- 4️⃣ Connect to database ---
require_once 'connection.php';
$supervisor_id = $_SESSION['supervisor_id'];

// --- 5️⃣ Verify the supervisor and active session token ---
$stmt = $conn->prepare("SELECT first_name, last_name, status, session_token FROM supervisors WHERE id = ?");
$stmt->bind_param("i", $supervisor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    session_destroy();
    header("Location: customer_login.php?error=not_found");
    exit();
}

$supervisor = $result->fetch_assoc();
$stmt->close();

// --- 6️⃣ If the supervisor’s status is inactive ---
if ($supervisor['status'] !== 'active') {
    session_destroy();
    header("Location: customer_login.php?error=inactive");
    exit();
}

// --- 7️⃣ Multiple sessions allowed - No session token validation ---
// Users can now login from multiple devices/browsers simultaneously
?>
