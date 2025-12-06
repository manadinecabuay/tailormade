<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customerID'])) {
  echo json_encode(['error' => 'Not logged in']);
  exit();
}

$customer_id = $_SESSION['customerID'];

// Get customer name
$customerStmt = $conn->prepare("SELECT full_name FROM customers WHERE customerID = ?");
$customerStmt->bind_param("i", $customer_id);
$customerStmt->execute();
$customerResult = $customerStmt->get_result();
$customerData = $customerResult->fetch_assoc();
$customer_name = $customerData['full_name'] ?? '';

// Get the most recent confirmed order status
$stmt = $conn->prepare("SELECT orderID, order_status as status 
                        FROM confirmed_order 
                        WHERE customer = ? AND order_status NOT IN ('Complete', 'Cancelled')
                        ORDER BY created_at DESC 
                        LIMIT 1");
$stmt->bind_param("s", $customer_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  $order = $result->fetch_assoc();
  echo json_encode([
    'orderID' => $order['orderID'],
    'status' => $order['status']
  ]);
} else {
  echo json_encode(['status' => null]);
}

$conn->close();
?>
