<?php
// Use the correct session name for superadmin
session_name('SUPERADMIN_SESSION');
session_start();

header('Content-Type: application/json');
require_once 'connection.php';

if (!isset($_SESSION['owner_id'])) {
    echo json_encode(['active' => false]);
    exit();
}

$owner_id = $_SESSION['owner_id'];

// Just check if owner exists (no session token validation, no timeout)
$stmt = $conn->prepare("SELECT id FROM owner WHERE id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$stmt->bind_result($exists);
$stmt->fetch();
$stmt->close();

// Session is active if owner exists
echo json_encode(['active' => ($exists !== null)]);
?>
