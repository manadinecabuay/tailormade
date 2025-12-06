<?php
// Script to update dashboard metrics for the current order
include 'connection.php';

function updateDashboardMetrics($conn, $orderId) {
    if (!$orderId) {
        return false;
    }
    
    // Get order start date
    $orderStartQuery = $conn->prepare("SELECT created_at FROM confirmed_order WHERE orderID = ?");
    $orderStartQuery->bind_param("i", $orderId);
    $orderStartQuery->execute();
    $orderStartResult = $orderStartQuery->get_result();
    
    if ($orderStartResult->num_rows == 0) {
        return false;
    }
    
    $orderData = $orderStartResult->fetch_assoc();
    $orderStartDate = $orderData['created_at'];
    $orderStartQuery->close();
    
    // Calculate total input from employee_daily_records since order started
    $inputQuery = $conn->prepare("
        SELECT COALESCE(SUM(input_value), 0) AS total_input
        FROM employee_daily_records
        WHERE work_date >= DATE(?)
    ");
    $inputQuery->bind_param("s", $orderStartDate);
    $inputQuery->execute();
    $inputResult = $inputQuery->get_result();
    $totalInput = $inputResult->fetch_assoc()['total_input'] ?? 0;
    $inputQuery->close();
    
    // Calculate total quality check (goods) from employees table
    $qualityQuery = $conn->query("
        SELECT COALESCE(SUM(quality_check), 0) AS total_quality_check
        FROM employees
        WHERE status != 'banned'
    ");
    $totalQualityCheck = $qualityQuery->fetch_assoc()['total_quality_check'] ?? 0;
    
    // Calculate defects
    $totalDefects = max(0, $totalInput - $totalQualityCheck);
    
    // Insert or update dashboard table
    $updateDashboard = $conn->prepare("
        INSERT INTO dashboard (order_id, total_input, total_quality_check, total_defects)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_input = VALUES(total_input),
            total_quality_check = VALUES(total_quality_check),
            total_defects = VALUES(total_defects),
            last_updated = CURRENT_TIMESTAMP
    ");
    
    $updateDashboard->bind_param("iiii", $orderId, $totalInput, $totalQualityCheck, $totalDefects);
    $result = $updateDashboard->execute();
    $updateDashboard->close();
    
    return $result;
}

// If called directly (for testing or manual update)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // Get current order
    $currentOrderQuery = $conn->query("
        SELECT orderID 
        FROM confirmed_order 
        WHERE order_status NOT IN ('Complete', 'Cancelled') 
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
    
    if ($currentOrderQuery && $currentOrderQuery->num_rows > 0) {
        $currentOrder = $currentOrderQuery->fetch_assoc();
        $orderId = $currentOrder['orderID'];
        
        if (updateDashboardMetrics($conn, $orderId)) {
            echo "✅ Dashboard metrics updated successfully for Order #$orderId";
        } else {
            echo "❌ Failed to update dashboard metrics";
        }
    } else {
        echo "ℹ️ No active order found";
    }
    
    $conn->close();
}
?>
