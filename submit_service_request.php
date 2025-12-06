<?php
session_start();
include 'connection.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['customerID'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in.']);
    exit;
}

$customer_id = $_SESSION['customerID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $garment_type = trim($_POST['garment_type'] ?? '');
    $fabric_type = trim($_POST['fabric_type'] ?? '');
    $quantity = intval($_POST['total_garments'] ?? 0);
    $price_per_piece = floatval($_POST['garment_price'] ?? 0);
    $due_date = $_POST['due_date'] ?? '';
    $message = trim($_POST['message'] ?? '');

    // Validation
    if (
        empty($full_name) || empty($address) || empty($contact) ||
        empty($garment_type) || empty($fabric_type) ||
        empty($due_date) || $quantity < 500 || $quantity > 3000 || $price_per_piece <= 0
    ) {
        echo json_encode(['status' => 'error', 'message' => 'Please complete all required fields correctly.']);
        exit;
    }

    // Calculate total price: total_price = garment_price * total_garments
    $total_price = $quantity * $price_per_piece;

    // Insert into orders table
    $sqlOrder = "INSERT INTO orders 
        (customer_id, fullname, address, contact, garment_type, fabric_type, total_garments, garment_price, total_price, due_date, message, status, order_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Order Confirmation', NOW())";

    $stmtOrder = $conn->prepare($sqlOrder);
    $stmtOrder->bind_param(
        "isssssiidss",
        $customer_id,
        $full_name,
        $address,
        $contact,
        $garment_type,
        $fabric_type,
        $quantity,
        $price_per_piece,
        $total_price,
        $due_date,
        $message
    );

    if (!$stmtOrder->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save order: ' . $stmtOrder->error]);
        exit;
    }

    $order_id = $stmtOrder->insert_id;
    $stmtOrder->close();
    $conn->close();

    echo json_encode(['status' => 'success', 'message' => 'Your order has been submitted successfully!', 'order_id' => $order_id]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
?>
