<?php
session_start();
include('connection.php');

header('Content-Type: application/json');

// Check if customer is logged in
if (!isset($_SESSION['customerID'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$customer_id = $_SESSION['customerID'];
$username = trim($_POST['username'] ?? '');
$newPassword = $_POST['newPassword'] ?? '';

// Validate username
if (empty($username)) {
    echo json_encode(['success' => false, 'field' => 'username', 'message' => 'Username is required']);
    exit;
}

if (strlen($username) < 3) {
    echo json_encode(['success' => false, 'field' => 'username', 'message' => 'Username must be at least 3 characters']);
    exit;
}

// Check if username is already taken by another customer
$stmt = $conn->prepare("SELECT customerID FROM customers WHERE username = ? AND customerID != ?");
$stmt->bind_param("si", $username, $customer_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'field' => 'username', 'message' => 'Username is already taken']);
    $stmt->close();
    exit;
}
$stmt->close();

// Validate password if provided
if (!empty($newPassword)) {
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'field' => 'password', 'message' => 'Password must be at least 8 characters long']);
        exit;
    }

    if (!preg_match('/[A-Z]/', $newPassword)) {
        echo json_encode(['success' => false, 'field' => 'password', 'message' => 'Password must contain at least 1 capital letter']);
        exit;
    }

    if (!preg_match('/[0-9]/', $newPassword)) {
        echo json_encode(['success' => false, 'field' => 'password', 'message' => 'Password must contain at least 1 number']);
        exit;
    }

    // Update username and password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE customers SET username = ?, password = ? WHERE customerID = ?");
    $stmt->bind_param("ssi", $username, $hashedPassword, $customer_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Username and password updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'field' => 'password', 'message' => 'Failed to update credentials']);
    }
    $stmt->close();
} else {
    // Update only username
    $stmt = $conn->prepare("UPDATE customers SET username = ? WHERE customerID = ?");
    $stmt->bind_param("si", $username, $customer_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Username updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'field' => 'username', 'message' => 'Failed to update username']);
    }
    $stmt->close();
}

$conn->close();
?>
