<?php
// Test file to manually add a notification to verify the system works
include 'connection.php';

// Add a test new order notification
$test_message = "Test: New order #999 from Test Customer for 5 shirts";
$test_link = "superadmin_order.php?order_id=999";
$test_type = "new_order";
$test_order_id = 999;

$stmt = $conn->prepare("INSERT INTO owner_notifications (notification_type, order_id, message, link_url) VALUES (?, ?, ?, ?)");
$stmt->bind_param("siss", $test_type, $test_order_id, $test_message, $test_link);

if ($stmt->execute()) {
    echo "✅ Test notification added successfully!<br>";
    echo "Notification ID: " . $conn->insert_id . "<br>";
    echo "Go to superadmin_dashboard.php to see it.";
} else {
    echo "❌ Error: " . $stmt->error;
}

$stmt->close();

// Add a test cancelled order notification
$test_message2 = "Test: Order #888 has been cancelled by customer: John Doe";
$test_link2 = "superadmin_order.php?order_id=888";
$test_type2 = "cancelled_order";
$test_order_id2 = 888;

$stmt2 = $conn->prepare("INSERT INTO owner_notifications (notification_type, order_id, message, link_url) VALUES (?, ?, ?, ?)");
$stmt2->bind_param("siss", $test_type2, $test_order_id2, $test_message2, $test_link2);

if ($stmt2->execute()) {
    echo "<br><br>✅ Test cancelled notification added successfully!<br>";
    echo "Notification ID: " . $conn->insert_id . "<br>";
} else {
    echo "<br><br>❌ Error: " . $stmt2->error;
}

$stmt2->close();
$conn->close();

echo "<br><br><a href='superadmin_dashboard.php'>Go to Dashboard</a>";
?>
