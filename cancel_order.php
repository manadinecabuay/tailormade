<?php
session_start();
include 'connection.php';
header('Content-Type: application/json');

if (!isset($_SESSION['customerID'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    $customer_id = $_SESSION['customerID'];

    // Verify order belongs to customer
    $checkStmt = $conn->prepare("SELECT status FROM orders WHERE orderID = ? AND customer_id = ?");
    $checkStmt->bind_param("ii", $order_id, $customer_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    $order = $result->fetch_assoc();
    
    // Check if order has been confirmed by superadmin (exists in confirmed_order table)
    $confirmedCheck = $conn->prepare("SELECT orderID FROM confirmed_order WHERE orderID = ?");
    $confirmedCheck->bind_param("i", $order_id);
    $confirmedCheck->execute();
    $confirmedResult = $confirmedCheck->get_result();
    
    if ($confirmedResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot cancel order. Your order has been confirmed and is being processed by our team.']);
        $confirmedCheck->close();
        exit;
    }
    $confirmedCheck->close();
    
    // Allow cancellation for any order that hasn't been confirmed yet
    // (Orders that are not in confirmed_order table can be cancelled)

    // Get customer name and order details for notification
    $customerStmt = $conn->prepare("SELECT full_name FROM customers WHERE customerID = ?");
    $customerStmt->bind_param("i", $customer_id);
    $customerStmt->execute();
    $customerResult = $customerStmt->get_result();
    $customerData = $customerResult->fetch_assoc();
    $customer_name = $customerData['full_name'] ?? 'Customer';
    $customerStmt->close();

    // Update order status to Cancelled
    $updateStmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE orderID = ?");
    $updateStmt->bind_param("i", $order_id);

    if ($updateStmt->execute()) {
        // Add notification for owner/superadmin
        $notification_message = "Order #$order_id cancelled by $customer_name.";
        $notification_type = "order_cancelled";
        $link_url = "superadmin_order.php?order_id=$order_id";
        
        $notifStmt = $conn->prepare("INSERT INTO owner_notifications (notification_type, order_id, message, link_url, created_at) VALUES (?, ?, ?, ?, NOW())");
        $notifStmt->bind_param("siss", $notification_type, $order_id, $notification_message, $link_url);
        $notifStmt->execute();
        $notifStmt->close();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
    }

    $updateStmt->close();
    $checkStmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>
