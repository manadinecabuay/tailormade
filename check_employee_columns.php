<?php
// Simple script to check if employee name columns exist
include('connection.php');

echo "<h2>Checking Employee Table Structure</h2>";

// Check current columns
$result = $conn->query("DESCRIBE employees");

echo "<h3>Current Columns:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

$has_first_name = false;
$has_middle_name = false;
$has_last_name = false;
$has_full_name = false;

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
    
    if ($row['Field'] === 'first_name') $has_first_name = true;
    if ($row['Field'] === 'middle_name') $has_middle_name = true;
    if ($row['Field'] === 'last_name') $has_last_name = true;
    if ($row['Field'] === 'full_name') $has_full_name = true;
}
echo "</table>";

echo "<h3>Status:</h3>";
echo "<ul>";
echo "<li>first_name: " . ($has_first_name ? "✅ EXISTS" : "❌ MISSING") . "</li>";
echo "<li>middle_name: " . ($has_middle_name ? "✅ EXISTS" : "❌ MISSING") . "</li>";
echo "<li>last_name: " . ($has_last_name ? "✅ EXISTS" : "❌ MISSING") . "</li>";
echo "<li>full_name: " . ($has_full_name ? "✅ EXISTS" : "❌ MISSING") . "</li>";
echo "</ul>";

if (!$has_first_name || !$has_last_name) {
    echo "<h3 style='color: red;'>⚠️ ACTION REQUIRED:</h3>";
    echo "<p>You need to run the SQL migration script: <strong>update_employee_name_columns.sql</strong></p>";
    echo "<p>Steps:</p>";
    echo "<ol>";
    echo "<li>Open phpMyAdmin or your MySQL client</li>";
    echo "<li>Select your database: <strong>tailormadedb</strong></li>";
    echo "<li>Go to SQL tab</li>";
    echo "<li>Copy and paste the contents of <strong>update_employee_name_columns.sql</strong></li>";
    echo "<li>Click 'Go' to execute</li>";
    echo "<li>Refresh this page to verify</li>";
    echo "</ol>";
} else {
    echo "<h3 style='color: green;'>✅ All required columns exist!</h3>";
    echo "<p>The employee editing should work now.</p>";
    
    // Show sample data
    $sample = $conn->query("SELECT id, first_name, middle_name, last_name, full_name FROM employees LIMIT 5");
    if ($sample && $sample->num_rows > 0) {
        echo "<h3>Sample Employee Data:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>First Name</th><th>Middle Name</th><th>Last Name</th><th>Full Name</th></tr>";
        while ($row = $sample->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['first_name'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['middle_name'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['last_name'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['full_name'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

$conn->close();
?>
