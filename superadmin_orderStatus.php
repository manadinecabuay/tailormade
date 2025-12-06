<?php
ini_set('display_errors', 1);
include('connection.php');
require 'superadmin_session.php';

// === AJAX: Update order status ===
if (isset($_POST['action']) && $_POST['action'] === 'updateStatus') {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['new_status'];

    // Define the correct order sequence
    $status_sequence = [
        'Order Confirmation',
        'Order Processing',
        'Quality Check',
        'Order Pack',
        'Out for Delivery',
        'Complete'
    ];
    
    // Get current status
    $currentStmt = $conn->prepare("SELECT order_status FROM confirmed_order WHERE orderID = ?");
    $currentStmt->bind_param("i", $order_id);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    $currentData = $currentResult->fetch_assoc();
    $current_status = $currentData['order_status'] ?? '';
    
    // Find positions in sequence
    $current_index = array_search($current_status, $status_sequence);
    $new_index = array_search($new_status, $status_sequence);
    
    // Validation: Cannot go backwards or skip steps
    if ($current_index === false || $new_index === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    if ($new_index < $current_index) {
        echo json_encode(['success' => false, 'message' => 'Cannot move backwards in order status. Current status: ' . $current_status]);
        exit;
    }
    
    if ($new_index > $current_index + 1) {
        $next_status = $status_sequence[$current_index + 1];
        echo json_encode(['success' => false, 'message' => 'Cannot skip steps. Next status should be: ' . $next_status]);
        exit;
    }

    // Special validation for Order Pack: Check if quota is met
    if ($new_status === 'Order Pack') {
        // Get total ordered from orders table
        $orderedStmt = $conn->prepare("SELECT total_garments FROM orders WHERE orderID = ?");
        $orderedStmt->bind_param("i", $order_id);
        $orderedStmt->execute();
        $orderedResult = $orderedStmt->get_result();
        $orderedData = $orderedResult->fetch_assoc();
        $total_ordered = intval($orderedData['total_garments'] ?? 0);
        
        // Get total made (quality checked goods) from dashboard table
        $dashboardStmt = $conn->prepare("SELECT total_quality_check FROM dashboard WHERE order_id = ?");
        $dashboardStmt->bind_param("i", $order_id);
        $dashboardStmt->execute();
        $dashboardResult = $dashboardStmt->get_result();
        $dashboardData = $dashboardResult->fetch_assoc();
        $total_made = intval($dashboardData['total_quality_check'] ?? 0);
        
        // Check if quota is met
        if ($total_made < $total_ordered) {
            echo json_encode([
                'success' => false, 
                'message' => "Status update failed: Production quota not yet met. Please complete the required quota before advancing to Order Pack status."
            ]);
            exit;
        }
    }

    // Update the order status in confirmed_order table
    $stmt = $conn->prepare("UPDATE confirmed_order SET order_status = ? WHERE orderID = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    $success = $stmt->execute();
    
    // Also update in orders table to keep them in sync
    $stmt2 = $conn->prepare("UPDATE orders SET status = ? WHERE orderID = ?");
    $stmt2->bind_param("si", $new_status, $order_id);
    $stmt2->execute();
    
    // Trigger reset when order is marked as Complete and no other active orders exist
    if ($success && $new_status === 'Complete') {
        include_once 'reset_for_new_order.php';
        
        // Check if this was the last active order
        if (!hasActiveOrders($conn)) {
            $resetResult = resetDashboardForNewOrder($conn, $order_id);
            
            if (!$resetResult['success']) {
                error_log("Reset failed after completing order #$order_id: " . $resetResult['message']);
            }
        }
    }

    echo json_encode(['success' => $success, 'message' => $success ? 'Status updated successfully' : 'Failed to update status']);
    exit;
}

// === AJAX: Update Order Pack data ===
if (isset($_POST['action']) && $_POST['action'] === 'updateOrderPack') {
    $order_id = intval($_POST['order_id']);
    $goods_count = intval($_POST['goods_count']);
    $defects_count = intval($_POST['defects_count']);
    
    // Update confirmed_order table with Order Pack data (no photos)
    $stmt = $conn->prepare("UPDATE confirmed_order SET goods_count = ?, defects_count = ?, order_status = 'Order Pack' WHERE orderID = ?");
    $stmt->bind_param("iii", $goods_count, $defects_count, $order_id);
    
    $success = $stmt->execute();
    
    // Also update orders table
    $stmt2 = $conn->prepare("UPDATE orders SET status = 'Order Pack' WHERE orderID = ?");
    $stmt2->bind_param("i", $order_id);
    $stmt2->execute();
    
    echo json_encode(['success' => $success, 'message' => $success ? 'Order Pack data updated successfully! Customers can now see this information in their tracking page.' : 'Failed to update Order Pack data']);
    exit;
}

// === OWNER info ===
$owner_sql = "SELECT first_name, last_name, profile_photo FROM owner LIMIT 1";
$owner_res = $conn->query($owner_sql);
$owner = $owner_res ? $owner_res->fetch_assoc() : null;

$owner_first = $owner['first_name'] ?? 'Owner';
$owner_last = $owner['last_name'] ?? '';
$owner_name = trim($owner_first . ' ' . $owner_last);

$owner_photo = $owner['profile_photo'] ?? '';
$default_photo = 'assets/images/default_profile.png';
$photo_path = (!empty($owner_photo) && file_exists($owner_photo))
    ? $owner_photo
    : $default_photo;

// Fetch owner's logo from business_info
$logo_path = 'tailor.jpg'; // default logo
$logo_sql = "SELECT logo_path FROM business_info LIMIT 1";
$logo_result = $conn->query($logo_sql);
if ($logo_result && $logo_result->num_rows > 0) {
    $logo_row = $logo_result->fetch_assoc();
    if (!empty($logo_row['logo_path']) && file_exists($logo_row['logo_path'])) {
        $logo_path = $logo_row['logo_path'];
    }
}

// === Fetch orders ===
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Fetch orders from confirmed_order table
$sql = "
    SELECT 
        orderID,
        customer,
        garment_type,
        fabric_type,
        due_date,
        order_status AS status,
        goods_count,
        defects_count
    FROM confirmed_order
    WHERE 1=1
";

if (!empty($statusFilter)) {
    $sql .= " AND order_status = ?";
}

$sql .= " ORDER BY orderID DESC";

$stmt = $conn->prepare($sql);
if (!empty($statusFilter)) {
    $stmt->bind_param("s", $statusFilter);
}
$stmt->execute();
$result = $stmt->get_result();

$statuses = ['Order Confirmation', 'Order Processing', 'Quality Check', 'Order Pack', 'Out for Delivery', 'Complete'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Status - Owner</title>
  
  <!-- Include Theme Loader for Custom Colors -->
  <?php include('theme_loader.php'); ?>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    * {
      margin:0;
      padding:0;
      box-sizing:border-box;
      font-family:'Poppins',sans-serif;
    }

    body{
      display:flex;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      min-height:100vh;
      overflow-x:hidden;
    }

    .sidebar{
      width:260px;
      background:rgba(255,255,255,0.98);
      display:flex;
      flex-direction:column;
      justify-content:space-between;
      box-shadow:4px 0 20px rgba(0,0,0,0.1);
      backdrop-filter:blur(10px);
    }

    .sidebar-logo {
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 25px 20px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .sidebar-logo img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid var(--theme-bg);
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .sidebar a{
      color:#333;
      text-decoration:none;
      padding:18px 30px;
      display:flex;
      align-items:center;
      gap:15px;
      font-weight:500;
      font-size:15px;
      transition:all 0.3s ease;
      border-left:4px solid transparent;
    }

    .sidebar a:hover{
      background:var(--theme-sidebar-hover);
      border-left-color:var(--theme-bg);
      color:var(--theme-sidebar-hover-font);
    }

    .sidebar a.active{
      background:var(--theme-sidebar-hover);
      border-left-color:var(--theme-bg);
      color:var(--theme-sidebar-hover-font);
      font-weight:500;
    }

    .sidebar a i{
      font-size:18px;
      width:24px;
      text-align:center;
    }

    .sidebar .bottom{
      border-top:1px solid rgba(0,0,0,0.1);
      padding:20px;
    }

    .logout-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
      color: white !important;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
      margin-top: 10px;
      border-left: 4px solid transparent !important;
    }

    .logout-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%) !important;
    }

    .logout-btn i {
      margin-right: 8px;
    }

    .main{
      flex:1;
      padding:40px;
      overflow-y:auto;
    }

    .header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:40px;
      background:rgba(255,255,255,0.95);
      padding:25px 35px;
      border-radius:20px;
      box-shadow:0 10px 30px rgba(0,0,0,0.15);
    }

    .header h2{
      font-size:28px;
      font-weight:700;
      color:var(--theme-bg);
      letter-spacing:0.5px;
    }

    .header-left h1{
      font-size:28px;
      font-weight:700;
      color:var(--theme-bg);
      letter-spacing:0.5px;
      margin-bottom:5px;
    }

    .header-left h1 i{
      margin-right:10px;
    }

    .header-left p{
      color:#666;
      font-size:15px;
      margin:0;
    }

    .header-right{
      display:flex;
      align-items:center;
      gap:15px;
    }

    .header-right i{
      font-size:20px;
      color:var(--theme-bg);
      cursor:pointer;
      transition:transform 0.3s ease;
    }

    .header-right i:hover{
      transform:scale(1.2);
    }

    .header-right img{
      width:45px;
      height:45px;
      border-radius:50%;
      border:3px solid var(--theme-bg);
      object-fit:cover;
    }

    .header-right span{
      font-weight:600;
      color:#333;
      font-size:15px;
    }

    .update-status-section{
      background:rgba(255,255,255,0.95);
      border-radius:20px;
      padding:30px;
      box-shadow:0 10px 30px rgba(0,0,0,0.15);
      transition:all 0.3s ease;
    }

    .update-status-section:hover{
      box-shadow:0 15px 40px rgba(102,126,234,0.3);
    }

    .update-status-section h4{
      margin-bottom:25px;
      letter-spacing:1px;
      color:var(--theme-bg);
      font-size:20px;
      font-weight:700;
    }

    table{
      width:100%;
      border-collapse:collapse;
      background:transparent;
    }

    thead{
      background:transparent;
    }
    thead th{
      padding:14px 16px;
      text-align:left;
      font-size:13px;
      letter-spacing:1px;
      color:var(--theme-bg);
      font-weight:600;
      text-transform:uppercase;
      border-bottom:2px solid rgba(102,126,234,0.2);
    }

    tbody tr{
      background:transparent;
      border-bottom:1px solid rgba(0,0,0,0.1);
      transition:all 0.2s ease;
    }

    tbody tr:hover{
      background:rgba(102,126,234,0.05);
    }

    tbody td{
      padding:14px 16px;
      font-size:14px;
      position:relative;
      color:#333;
    }

    .status-container{
      display:flex;
      align-items:center;
      justify-content:space-between;
      background:linear-gradient(135deg,var(--theme-bg) 0%,var(--theme-button) 100%);
      border-radius:10px;
      padding:8px 14px;
      min-width:200px;
      position:relative;
      height:40px;
      box-shadow:0 3px 10px rgba(102,126,234,0.3);
    }

    .current-status{
      font-weight:600;
      color:#fff;
      text-transform:capitalize;
      flex:1;
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
      font-size:13px;
    }

    .status-btn{
      background:none;
      border:none;
      color:#fff;
      font-size:1rem;
      cursor:pointer;
      margin-left:8px;
      position:absolute;
      right:10px;
      top:50%;
      transform:translateY(-50%);
      transition:all 0.3s ease;
    }

    .status-btn:hover{
      transform:translateY(-50%) scale(1.2);
    }

    .dropdown {
      position: absolute;
      right: 100%;
      top: 45px;
      background-color: #fff;
      border-radius: 12px;
      display: none;
      flex-direction: column;
      min-width: 220px;
      z-index: 10;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
      overflow:hidden;
    }

    .dropdown button {
      background: none;
      border: none;
      color: #333;
      text-align: left;
      padding: 12px 18px;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s;
      letter-spacing: 0.5px;
      font-weight:500;
      border-bottom:1px solid #f0f0f0;
    }

    .dropdown button:last-child {
      border-bottom:none;
    }

    .dropdown button:hover {
      background:rgba(102,126,234,0.1);
      color:var(--theme-bg);
    }

    .dropdown.upward{
      top:auto;
      bottom:45px;
      overflow:visible;
    }
    
    .nested-dropdown{
      position:relative;
    }

    .nested-btn{
      background:none;
      border:none;
      color:white;
      text-align:left;
      padding:10px 15px;
      font-size:0.85rem;
      cursor:pointer;
      width:100%;
    }

    .nested-dropdown-content {
      display: none;
      position: absolute;
      right: 100%;
      top: 0;
      background:#fff;
      border-radius: 12px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
      flex-direction: column;
      min-width: 280px;
      z-index: 20;
      padding: 20px;
    }
    
    .nested-dropdown-content button {
      padding: 10px 14px;
      font-size: 13px;
      text-align: left;
      background: none;
      border: none;
      color: #333;
      cursor: pointer;
      font-weight:500;
    }
    
    .nested-dropdown-content button:hover {
      background:rgba(102,126,234,0.1);
      color:var(--theme-bg);
    }

    .nested-dropdown-content.upward{
      top:auto;
      bottom:0;
    
    }

    .nested-dropdown:hover .nested-dropdown-content{
      display:flex;
    }

    .form-group{
      background:#f8f9fa;
      border:2px solid #e0e0e0;
      border-radius:10px;
      padding:15px;
      margin-bottom:15px;
      display:flex;
      flex-direction:column;
      gap:8px;
    }

    .form-group label{
      font-size:12px;
      color:var(--theme-bg);
      letter-spacing:0.5px;
      font-weight:600;
      text-transform:uppercase;
    }

    .form-group input[type="number"]{
      padding:10px 12px;
      border:2px solid #e0e0e0;
      border-radius:8px;
      background:#fff;
      color:#333;
      font-size:14px;
      transition:all 0.3s ease;
    }

    .form-group input[type="number"]:focus{
      outline:none;
      border-color:var(--theme-bg);
      box-shadow:0 0 0 3px rgba(102,126,234,0.1);
    }
    
    .upload-btn,.update-btn{
      background:linear-gradient(135deg,var(--theme-bg) 0%,var(--theme-button) 100%);
      color:var(--theme-font);
      border:none;
      border-radius:10px;
      padding:10px 18px;
      cursor:pointer;
      font-weight:600;
      font-size:13px;
      transition:all 0.3s ease;
      text-transform:uppercase;
      letter-spacing:0.5px;
    }

    .upload-btn:hover,.update-btn:hover{
      transform:translateY(-2px);
      box-shadow:0 5px 15px rgba(102,126,234,0.4);
    }

    .preview{
      display:block;
      max-width:100%;
      border-radius:6px;
      margin-top:6px;
      border:1px solid #101844ff;
    }

    /* Modal Styles */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      justify-content: center;
      align-items: center;
      z-index: 9999;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-content {
      background: #fff;
      padding: 40px;
      border-radius: 20px;
      width: 450px;
      max-width: 90%;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideIn 0.3s ease;
      position: relative;
    }

    @keyframes slideIn {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .modal-content.success {
      border-top: 5px solid #28a745;
    }

    .modal-content.error {
      border-top: 5px solid #dc3545;
    }

    .modal-content.confirm {
      border-top: 5px solid var(--theme-bg);
    }

    .modal-icon {
      font-size: 60px;
      margin-bottom: 20px;
    }

    .modal-icon.success { color: #28a745; }
    .modal-icon.error { color: #dc3545; }
    .modal-icon.confirm { color: var(--theme-bg); }

    .modal-content h3 {
      color: #333;
      margin-bottom: 15px;
      font-size: 24px;
      font-weight: 700;
    }

    .modal-content p {
      color: #666;
      margin-bottom: 25px;
      line-height: 1.6;
      font-size: 15px;
    }

    .modal-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
    }

    .modal-btn {
      padding: 12px 30px;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .modal-btn-primary {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
    }

    .filter-label {
      color: var(--theme-bg) !important;
    }

    .filter-btn-submit {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%) !important;
      color: var(--theme-font) !important;
    }

    .modal-btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .modal-btn-success {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: #fff;
    }

    .modal-btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
    }

    .modal-btn-secondary {
      background: #f8f9fa;
      color: #333;
      border: 2px solid #e0e0e0;
    }

    .modal-btn-secondary:hover {
      background: #e9ecef;
      transform: translateY(-2px);
    }

    .close-modal {
      position: absolute;
      top: 15px;
      right: 20px;
      font-size: 24px;
      color: #999;
      cursor: pointer;
      transition: all 0.3s ease;
      background: none;
      border: none;
      padding: 0;
      width: 30px;
      height: 30px;
      line-height: 30px;
    }

    .close-modal:hover {
      color: #333;
      transform: scale(1.1);
    }
  </style>

</head>
<body>
  <div class="sidebar">
    <div>
      <div class="sidebar-logo">
        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Business Logo">
      </div>
      <a href="superadmin_dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
      <a href="superadmin_employees.php"><i class="fa-solid fa-users"></i> Employees</a>
      <a href="superadmin_payroll.php"><i class="fa-solid fa-money-bill"></i> Payroll</a>
      <a href="superadmin_order.php"><i class="fa-solid fa-box"></i> Orders</a>
      <a href="superadmin_orderStatus.php" class="active"><i class="fa-solid fa-chart-bar"></i> Order Status</a>
      <a href="customize.php"><i class="fa-solid fa-gear"></i> Customize</a>
    </div>
    <div class="bottom">
      <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </div>

  <div class="main">
    <div class="header">
      <div class="header-left">
        <h1><i class="fa-solid fa-chart-bar"></i> Order Status Tracking</h1>
        <p>Monitor order progress and update statuses</p>
      </div>
      <div class="header-right">
        <img src="<?= htmlspecialchars($photo_path) ?>" alt="Profile">
        <span><?= htmlspecialchars($owner_name) ?></span>
      </div>
    </div>

    <div class="update-status-section">
      <h4>UPDATE ORDER STATUS</h4>
      <div class="filter-container" style="margin-bottom:25px; display:flex; align-items:center; gap:15px;">
        <form method="get" style="display:flex; align-items:center; gap:15px;">
          <label for="status" style="font-weight:600; font-size:14px;" class="filter-label">Filter by status:</label>
          <select name="status" id="status" style="padding:10px 16px; border:2px solid #e0e0e0; border-radius:10px; background:#fff; color:#333; font-weight:500; cursor:pointer; transition:all 0.3s ease; font-size:14px;">
            <option value="">All</option>
            <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>" <?= ($statusFilter == $s) ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="filter-btn-submit" style="padding:10px 20px; border:none; border-radius:10px; font-weight:600; cursor:pointer; transition:all 0.3s ease; font-size:14px;">Filter</button>
        </form>
      </div>

      <table>
        <thead>
          <tr>
            <th>ORDER ID</th>
            <th>NAME OF CUSTOMER</th>
            <th>TYPE OF GARMENT AND FABRIC</th>
            <th>DUE DATE</th>
            <th>STATUS</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['orderID']) ?></td>
                <td><?= htmlspecialchars($row['customer']) ?></td>
                <td><?= htmlspecialchars($row['garment_type'] . ' (' . $row['fabric_type'] . ')') ?></td>
                <td><?= htmlspecialchars(date('M d, Y', strtotime($row['due_date']))) ?></td>
                <td>
                  <div class="status-container">
                    <span class="current-status"><?= htmlspecialchars($row['status']) ?></span>
                    <button class="status-btn">▼</button>
                    <div class="dropdown">
                      <?php 
                      $current_index = array_search($row['status'], $statuses);
                      foreach ($statuses as $index => $s): 
                        // Only show next status or current status
                        $is_next = ($index == $current_index + 1);
                        $is_current = ($index == $current_index);
                        $is_disabled = !$is_next && !$is_current;
                      ?>
                        <?php if (!$is_disabled): ?>
                          <button onclick="updateStatus(<?= $row['orderID'] ?>, '<?= $s ?>')" <?= $is_current ? 'style="opacity:0.5; cursor:not-allowed;" disabled' : '' ?>><?= strtoupper($s) ?><?= $is_next ? ' →' : '' ?></button>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" style="text-align:center;">No orders found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="modal-overlay">
    <div class="modal-content confirm">
      <button class="close-modal" onclick="closeModal('confirmModal')">&times;</button>
      <div class="modal-icon confirm">❓</div>
      <h3>Confirm Status Update</h3>
      <p id="confirmMessage"></p>
      <div class="modal-buttons">
        <button class="modal-btn modal-btn-primary" id="confirmYes">Yes, Update</button>
        <button class="modal-btn modal-btn-secondary" onclick="closeModal('confirmModal')">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="modal-overlay">
    <div class="modal-content success">
      <button class="close-modal" onclick="closeModal('successModal')">&times;</button>
      <div class="modal-icon success">✅</div>
      <h3>Success!</h3>
      <p id="successMessage"></p>
      <div class="modal-buttons">
        <button class="modal-btn modal-btn-success" onclick="location.reload()">OK</button>
      </div>
    </div>
  </div>

  <!-- Error Modal -->
  <div id="errorModal" class="modal-overlay">
    <div class="modal-content error">
      <button class="close-modal" onclick="closeModal('errorModal')">&times;</button>
      <div class="modal-icon error">❌</div>
      <h3>Error!</h3>
      <p id="errorMessage"></p>
      <div class="modal-buttons">
        <button class="modal-btn modal-btn-secondary" onclick="closeModal('errorModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Order Pack Confirmation Modal -->
  <div id="orderPackConfirmModal" class="modal-overlay">
    <div class="modal-content confirm">
      <button class="close-modal" onclick="closeModal('orderPackConfirmModal')">&times;</button>
      <div class="modal-icon confirm">📦</div>
      <h3>Confirm Order Pack Upload</h3>
      <p id="orderPackConfirmMessage"></p>
      <div class="modal-buttons">
        <button class="modal-btn modal-btn-primary" id="confirmOrderPackUpload">Upload</button>
        <button class="modal-btn modal-btn-secondary" onclick="closeModal('orderPackConfirmModal')">Cancel</button>
      </div>
    </div>
  </div>

<script>
  // Toggle dropdown (left side, upward flip) - Click anywhere on status container
  document.querySelectorAll('.status-container').forEach(container => {
    container.addEventListener('click', function(e) {
      e.stopPropagation();
      const dropdown = this.querySelector('.dropdown');
      document.querySelectorAll('.dropdown').forEach(d => { if (d !== dropdown) d.style.display='none'; });
      const isVisible = dropdown.style.display === 'flex';
      dropdown.style.display = isVisible ? 'none' : 'flex';
      if (!isVisible) {
        const rect = container.getBoundingClientRect();
        const dropdownHeight = dropdown.offsetHeight;
        dropdown.classList.toggle('upward', rect.bottom + dropdownHeight > window.innerHeight);
      }
    });
  });

  document.addEventListener('click',()=>document.querySelectorAll('.dropdown').forEach(d=>d.style.display='none'));

  document.querySelectorAll('.nested-dropdown').forEach(nested=>{
    const content=nested.querySelector('.nested-dropdown-content');
    nested.addEventListener('mouseenter',()=>{
      content.classList.remove('upward');
      const rect=content.getBoundingClientRect();
      if(rect.bottom>window.innerHeight)content.classList.add('upward');
    });
  });

  document.querySelectorAll('.dropdown,.nested-dropdown-content').forEach(m=>m.addEventListener('click',e=>e.stopPropagation()));

  // Modal Functions
  function showModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
  }

  function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
  }

  // Close modal when clicking outside
  window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
      e.target.style.display = 'none';
    }
  });

  // Order Pack functionality removed - now uses simple status button like other statuses

  // Add event listener to confirmation modal Upload button
  document.addEventListener('DOMContentLoaded', function() {
    const uploadBtn = document.getElementById('confirmOrderPackUpload');
    if (uploadBtn) {
      uploadBtn.addEventListener('click', confirmOrderPackUpload);
    }
  });

  let pendingUpdate = null;

  function updateStatus(orderId, newStatus){
    pendingUpdate = { orderId, newStatus };
    document.getElementById('confirmMessage').innerHTML = `
      Are you sure you want to update<br>
      <strong>Order #${orderId}</strong> to<br>
      <strong>"${newStatus}"</strong>?
    `;
    showModal('confirmModal');
  }

  document.getElementById('confirmYes').addEventListener('click', function() {
    if (!pendingUpdate) return;
    
    closeModal('confirmModal');
    const { orderId, newStatus } = pendingUpdate;
    
    fetch('',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({action:'updateStatus',order_id:orderId,new_status:newStatus})
    }).then(r=>r.json()).then(data=>{
      if(data.success){
        document.getElementById('successMessage').textContent = 
          'Order status updated successfully! Customers can now see this update in their tracking page.';
        showModal('successModal');
      }
      else{
        document.getElementById('errorMessage').textContent = 
          'Failed to update status: ' + (data.message || 'Unknown error');
        showModal('errorModal');
      }
    }).catch(err=>{
      document.getElementById('errorMessage').textContent = 
        'Error updating status. Please try again.';
      showModal('errorModal');
      console.error(err);
    });
    
    pendingUpdate = null;
  });

</script>
</body>
</html>
