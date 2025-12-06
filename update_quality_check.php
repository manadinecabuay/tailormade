<?php
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = intval($_POST['employee_id'] ?? 0);
    $quality_val = intval($_POST['quality_check'] ?? 0);

    if ($emp_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
        exit;
    }

    // Get employee input
    $stmt = $conn->prepare("SELECT `input` FROM employees WHERE id = ?");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $inputVal = intval($res['input'] ?? 0);

    $defects = max(0, $inputVal - $quality_val);
    $output = $quality_val;

    $upd = $conn->prepare("UPDATE employees SET quality_check=?, defects=?, output=?, updated_at=NOW() WHERE id=?");
    $upd->bind_param("iiii", $quality_val, $defects, $output, $emp_id);
    $upd->execute();

    // Get total defects
    $totalRes = $conn->query("SELECT COALESCE(SUM(defects),0) AS total_defects FROM employees");
    $totalDefects = $totalRes->fetch_assoc()['total_defects'] ?? 0;

    // Check if quota is met for current order
    $orderQuery = $conn->query("
        SELECT orderID 
        FROM confirmed_order 
        WHERE order_status NOT IN ('Complete', 'Cancelled') 
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
    
    if ($orderQuery && $orderQuery->num_rows > 0) {
        $orderData = $orderQuery->fetch_assoc();
        $orderId = $orderData['orderID'];
        
        // Get total ordered
        $orderedStmt = $conn->prepare("SELECT total_garments FROM orders WHERE orderID = ?");
        $orderedStmt->bind_param("i", $orderId);
        $orderedStmt->execute();
        $orderedResult = $orderedStmt->get_result()->fetch_assoc();
        $totalOrdered = intval($orderedResult['total_garments'] ?? 0);
        $orderedStmt->close();
        
        // Get total made (input)
        $madeQuery = $conn->query("SELECT COALESCE(SUM(input),0) AS total_made FROM employees WHERE status = 'active'");
        $totalMade = intval($madeQuery->fetch_assoc()['total_made'] ?? 0);
        
        // Check if quota just met and notification not already sent
        if ($totalMade >= $totalOrdered && $totalOrdered > 0) {
            // Check if notification already exists for this order
            $checkNotif = $conn->prepare("SELECT id FROM owner_notifications WHERE notification_type = 'quota_met' AND order_id = ?");
            $checkNotif->bind_param("i", $orderId);
            $checkNotif->execute();
            $notifExists = $checkNotif->get_result()->num_rows > 0;
            $checkNotif->close();
            
            // Only send notification if it doesn't exist yet
            if (!$notifExists) {
                $notif_message = "Production quota met for order #$orderId ($totalMade/$totalOrdered garments completed).";
                $notif_link = "superadmin_orderStatus.php";
                
                $notifStmt = $conn->prepare("INSERT INTO owner_notifications (notification_type, order_id, message, link_url) VALUES ('quota_met', ?, ?, ?)");
                $notifStmt->bind_param("iss", $orderId, $notif_message, $notif_link);
                $notifStmt->execute();
                $notifStmt->close();
            }
        }
    }

    echo json_encode([
        'success' => true,
        'defects' => $defects,
        'output' => $output,
        'total_defects' => $totalDefects
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
