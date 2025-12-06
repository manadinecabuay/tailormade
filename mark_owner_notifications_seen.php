<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['owner_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Mark all unseen notifications as seen
$update_query = $conn->query("
    UPDATE owner_notifications 
    SET is_seen = 1 
    WHERE is_seen = 0
");

if ($update_query) {
    echo json_encode(['success' => true, 'message' => 'Notifications marked as seen']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update']);
}

$conn->close();
?>
