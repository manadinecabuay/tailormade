<?php
// AJAX endpoint to get dashboard metrics for current order
session_start();
include 'connection.php';

header('Content-Type: application/json');

// Get current order
$sqlOrders = "
    SELECT orderID 
    FROM confirmed_order 
    WHERE order_status NOT IN ('Complete', 'Cancelled') 
    ORDER BY updated_at DESC 
    LIMIT 1
";
$resOrders = mysqli_query($conn, $sqlOrders);
$orderData = mysqli_fetch_assoc($resOrders);
$orderId = $orderData['orderID'] ?? null;

$response = [
    'success' => false,
    'orderId' => $orderId,
    'totalInput' => 0,
    'totalGoods' => 0,
    'totalDefects' => 0,
    'progressPercentage' => 0
];

if ($orderId) {
    // Include and run the update function
    include_once 'update_dashboard_metrics.php';
    updateDashboardMetrics($conn, $orderId);
    
    // Get total input from employee_daily_records (all employees for current order)
    $inputQuery = $conn->query("SELECT COALESCE(SUM(input_value), 0) AS total_input FROM employee_daily_records");
    $totalInput = 0;
    if ($inputQuery) {
        $inputData = $inputQuery->fetch_assoc();
        $totalInput = intval($inputData['total_input'] ?? 0);
    }
    
    // Get total goods (quality_check) from quality_check_logs table
    $goodsQuery = $conn->query("SELECT COALESCE(SUM(goods), 0) AS total_goods FROM quality_check_logs");
    $totalGoods = 0;
    if ($goodsQuery) {
        $goodsData = $goodsQuery->fetch_assoc();
        $totalGoods = intval($goodsData['total_goods'] ?? 0);
    }
    
    // Get total defects from quality_check_logs table
    $defectsQuery = $conn->query("SELECT COALESCE(SUM(defects), 0) AS total_defects FROM quality_check_logs");
    $totalDefects = 0;
    if ($defectsQuery) {
        $defectsData = $defectsQuery->fetch_assoc();
        $totalDefects = intval($defectsData['total_defects'] ?? 0);
    }
    
    // Get total ordered
    $orderedQuery = $conn->prepare("SELECT total_garments FROM orders WHERE orderID = ?");
    $orderedQuery->bind_param("i", $orderId);
    $orderedQuery->execute();
    $orderedResult = $orderedQuery->get_result();
    $totalOrdered = $orderedResult->fetch_assoc()['total_garments'] ?? 0;
    $orderedQuery->close();
    
    $response['success'] = true;
    $response['totalInput'] = $totalInput;
    $response['totalGoods'] = $totalGoods;
    $response['totalDefects'] = $totalDefects;
    
    if ($totalOrdered > 0) {
        $response['progressPercentage'] = min(100, round(($totalInput / $totalOrdered) * 100, 1));
    }
}

$conn->close();
echo json_encode($response);
?>
