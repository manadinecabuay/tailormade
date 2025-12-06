<?php
// Use the correct session name for admin
session_name('ADMIN_SESSION');
session_start();

header('Content-Type: application/json');
require_once 'connection.php';

if (!isset($_SESSION['supervisor_id'])) {
    echo json_encode(['active' => false]);
    exit();
}

$supervisor_id = $_SESSION['supervisor_id'];

// Just check if supervisor exists and is active
$stmt = $conn->prepare("SELECT status FROM supervisors WHERE id = ?");
$stmt->bind_param("i", $supervisor_id);
$stmt->execute();
$stmt->bind_result($status);
$stmt->fetch();
$stmt->close();

// Session is active if supervisor exists and status is active
echo json_encode(['active' => ($status === 'active')]);
?>
