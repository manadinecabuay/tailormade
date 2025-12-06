<?php
// Function to reset dashboard and employee data when an order is completed
include_once 'connection.php';

/**
 * Check if there are any active orders in the system
 * An order is considered active if (goods_count + defects_count) < total_garments
 * 
 * @param mysqli $conn Database connection
 * @return bool True if active orders exist, false otherwise
 */
function hasActiveOrders($conn) {
    $sql = "SELECT COUNT(*) as active_count 
            FROM confirmed_order co
            INNER JOIN orders o ON co.orderID = o.orderID
            WHERE (co.goods_count + co.defects_count) < o.total_garments";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        // If query fails, assume no active orders (safe default)
        return false;
    }
    
    $row = $result->fetch_assoc();
    return ($row['active_count'] > 0);
}

function resetDashboardForNewOrder($conn, $completedOrderId) {
    try {
        // Safety check: Only reset if no active orders exist
        if (hasActiveOrders($conn)) {
            return [
                'success' => false,
                'message' => "Cannot reset: Active orders still exist in the system"
            ];
        }
        
        // Verify payroll data exists before reset (for logging purposes)
        $payrollCheck = $conn->query("SELECT COUNT(*) as count FROM payroll");
        $payrollCount = $payrollCheck ? $payrollCheck->fetch_assoc()['count'] : 0;
        
        // Verify dashboard_history data exists before reset (for logging purposes)
        $historyCheck = $conn->query("SELECT COUNT(*) as count FROM dashboard_history");
        $historyCount = $historyCheck ? $historyCheck->fetch_assoc()['count'] : 0;
        
        // Start transaction
        $conn->begin_transaction();
        
        // 1. Archive the completed order data to dashboard_history
        $archiveQuery = $conn->prepare("
            INSERT INTO dashboard_history (order_id, total_input, total_quality_check, total_defects, completed_at)
            SELECT order_id, total_input, total_quality_check, total_defects, NOW()
            FROM dashboard
            WHERE order_id = ?
        ");
        $archiveQuery->bind_param("i", $completedOrderId);
        $archiveQuery->execute();
        $archiveQuery->close();
        
        // 2. Delete from current dashboard table
        $deleteQuery = $conn->prepare("DELETE FROM dashboard WHERE order_id = ?");
        $deleteQuery->bind_param("i", $completedOrderId);
        $deleteQuery->execute();
        $deleteQuery->close();
        
        // 3. Clear quality_check_logs table (before employee_daily_records)
        $conn->query("DELETE FROM quality_check_logs");
        
        // 4. Clear employee_daily_records table (after quality_check_logs)
        $conn->query("DELETE FROM employee_daily_records");
        
        // 5. Reset employees table (input, quality_check, defects, output to 0)
        $conn->query("
            UPDATE employees 
            SET input = 0, quality_check = 0, defects = 0, output = 0, updated_at = NOW() 
            WHERE status != 'banned'
        ");
        
        // Commit transaction
        $conn->commit();
        
        // Verify payroll and dashboard_history data preserved after reset
        $payrollCheckAfter = $conn->query("SELECT COUNT(*) as count FROM payroll");
        $payrollCountAfter = $payrollCheckAfter ? $payrollCheckAfter->fetch_assoc()['count'] : 0;
        
        $historyCheckAfter = $conn->query("SELECT COUNT(*) as count FROM dashboard_history");
        $historyCountAfter = $historyCheckAfter ? $historyCheckAfter->fetch_assoc()['count'] : 0;
        
        // Log reset action with verification
        error_log("Dashboard reset completed successfully for Order #$completedOrderId at " . date('Y-m-d H:i:s'));
        error_log("Payroll records preserved: $payrollCount before, $payrollCountAfter after");
        error_log("Dashboard history records preserved: $historyCount before, $historyCountAfter after");
        
        return [
            'success' => true,
            'message' => "Dashboard reset successfully for Order #$completedOrderId"
        ];
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        
        error_log("Dashboard reset failed for Order #$completedOrderId: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => "Error resetting dashboard: " . $e->getMessage()
        ];
    }
}

// If called directly (for manual reset)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    if (isset($_GET['order_id'])) {
        $orderId = intval($_GET['order_id']);
        $result = resetDashboardForNewOrder($conn, $orderId);
        
        if ($result['success']) {
            echo "✅ " . $result['message'];
        } else {
            echo "❌ " . $result['message'];
        }
    } else {
        echo "⚠️ Please provide order_id parameter. Example: reset_for_new_order.php?order_id=123";
    }
    
    $conn->close();
}
?>
