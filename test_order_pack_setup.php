<?php
// Test script to verify Order Pack functionality setup
include('connection.php');

echo "<h2>Order Pack Setup Verification</h2>";

// Check if uploads/order_pack directory exists
$upload_dir = 'uploads/order_pack/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    echo "<p style='color: green;'>✓ Created uploads/order_pack/ directory</p>";
} else {
    echo "<p style='color: green;'>✓ uploads/order_pack/ directory exists</p>";
}

// Check if directory is writable
if (is_writable($upload_dir)) {
    echo "<p style='color: green;'>✓ uploads/order_pack/ directory is writable</p>";
} else {
    echo "<p style='color: red;'>✗ uploads/order_pack/ directory is NOT writable - please set permissions to 0777</p>";
}

// Check if required columns exist in confirmed_order table
$columns_check = $conn->query("SHOW COLUMNS FROM confirmed_order LIKE 'goods_count'");
if ($columns_check && $columns_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ Database column 'goods_count' exists</p>";
} else {
    echo "<p style='color: red;'>✗ Database column 'goods_count' missing - run update_confirmed_order_table.sql</p>";
}

$columns_check = $conn->query("SHOW COLUMNS FROM confirmed_order LIKE 'defects_count'");
if ($columns_check && $columns_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ Database column 'defects_count' exists</p>";
} else {
    echo "<p style='color: red;'>✗ Database column 'defects_count' missing - run update_confirmed_order_table.sql</p>";
}

$columns_check = $conn->query("SHOW COLUMNS FROM confirmed_order LIKE 'goods_photo'");
if ($columns_check && $columns_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ Database column 'goods_photo' exists</p>";
} else {
    echo "<p style='color: red;'>✗ Database column 'goods_photo' missing - run update_confirmed_order_table.sql</p>";
}

$columns_check = $conn->query("SHOW COLUMNS FROM confirmed_order LIKE 'defects_photo'");
if ($columns_check && $columns_check->num_rows > 0) {
    echo "<p style='color: green;'>✓ Database column 'defects_photo' exists</p>";
} else {
    echo "<p style='color: red;'>✗ Database column 'defects_photo' missing - run update_confirmed_order_table.sql</p>";
}

echo "<hr>";
echo "<h3>Setup Instructions:</h3>";
echo "<ol>";
echo "<li>If any database columns are missing, run the SQL script: <code>update_confirmed_order_table.sql</code></li>";
echo "<li>Ensure the uploads/order_pack/ directory has write permissions (0777)</li>";
echo "<li>Test the Order Pack upload on superadmin_orderStatus.php</li>";
echo "</ol>";

$conn->close();
?>
