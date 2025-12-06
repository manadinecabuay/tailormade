<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$customer_id = $_SESSION['customer_id'];

// Mark all confirmed/cancelled orders as seen for this customer
$update_query = $conn->prepare("
    UPDATE orders 
    SET notification_seen = 1 
    WHERE customer_id = ? 
    AND status IN ('confirmed', 'cancelled')
    AND notification_seen = 0
");

$update_query->bind_param("i", $customer_id);

if ($update_query->execute()) {
    echo json_encode(['success' => true, 'message' => 'Notifications marked as seen']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update']);
}

$update_query->close();
$conn->close();
?>
