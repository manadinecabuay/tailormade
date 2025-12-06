<?php
// Script to add 'order_cancelled' to owner_notifications notification_type ENUM
include('connection.php');

// Check if the table exists first
$check_table = "SHOW TABLES LIKE 'owner_notifications'";
$result = $conn->query($check_table);

if ($result->num_rows == 0) {
    echo "❌ Error: The 'owner_notifications' table does not exist.<br>";
    echo "Please run 'create_owner_notifications_table.sql' first.";
    $conn->close();
    exit();
}

// Check current ENUM values
$check_enum = "SHOW COLUMNS FROM owner_notifications LIKE 'notification_type'";
$result = $conn->query($check_enum);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $current_type = $row['Type'];
    
    // Check if 'order_cancelled' already exists
    if (strpos($current_type, 'order_cancelled') !== false) {
        echo "ℹ️ Info: The 'order_cancelled' notification type already exists in the owner_notifications table.<br>";
        echo "No changes needed.";
        $conn->close();
        exit();
    }
}

// Add 'order_cancelled' to the ENUM
$sql = "ALTER TABLE owner_notifications 
        MODIFY COLUMN notification_type ENUM('quota_met', 'new_order', 'order_cancelled') NOT NULL";

if ($conn->query($sql) === TRUE) {
    echo "✅ Success! The 'order_cancelled' notification type has been added to owner_notifications table.<br>";
    echo "Customer order cancellation notifications will now appear in the superadmin dashboard bell.";
} else {
    echo "❌ Error: " . $conn->error;
}

$conn->close();
?>
