<?php
// Endpoint to fetch garment price (GET) or update piece rate price (POST)
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // GET: Fetch garment price for an employee's assigned order
    header('Content-Type: application/json');
    
    $employee_id = $_GET['employee_id'] ?? '';

    if (empty($employee_id)) {
        echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
        exit;
    }

    // Get the employee's assigned order_id
    $sql = "SELECT order_id FROM employees WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
        exit;
    }

    $employee = $result->fetch_assoc();
    $order_id = $employee['order_id'];

    if (empty($order_id)) {
        echo json_encode(['success' => false, 'message' => 'No order assigned']);
        exit;
    }

    // Get the garment price from the orders table
    $sql = "SELECT garment_price, price_per_piece FROM orders WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    $order = $result->fetch_assoc();
    $garment_price = $order['garment_price'] ?? $order['price_per_piece'] ?? null;

    if ($garment_price === null) {
        echo json_encode(['success' => false, 'message' => 'Price not available']);
        exit;
    }

    echo json_encode(['success' => true, 'garment_price' => $garment_price]);
    
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    // POST: Update piece rate price (for superadmin_payroll.php)
    $price = $_POST['price'] ?? '';

    if (!empty($price) && is_numeric($price)) {
        // Log the price update to price_log table if it exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'price_log'");
        
        if ($checkTable->num_rows > 0) {
            $insertSql = "INSERT INTO price_log (price, created_at) VALUES (?, NOW())";
            $stmt = $conn->prepare($insertSql);
            $stmt->bind_param("d", $price);
            $stmt->execute();
        }
        
        // Redirect back to superadmin_payroll.php with success
        header("Location: superadmin_payroll.php?price_updated=1");
        exit;
    } else {
        // Redirect back with error
        header("Location: superadmin_payroll.php?error=invalid_price");
        exit;
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>