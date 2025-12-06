<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
include('connection.php');
require 'superadmin_session.php';

// Fetch owner info
$owner_sql = "SELECT first_name, last_name, profile_photo FROM owner LIMIT 1";
$owner_res = $conn->query($owner_sql);
$owner = $owner_res ? $owner_res->fetch_assoc() : null;

$owner_first = $owner['first_name'] ?? 'Owner';
$owner_last = $owner['last_name'] ?? '';
$owner_name = trim($owner_first . ' ' . $owner_last);

$owner_photo = $owner['profile_photo'] ?? '';
$default_photo = 'assets/images/default_profile.png';
$photo_path = (!empty($owner_photo) && file_exists($owner_photo)) ? $owner_photo : $default_photo;

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

// Handle AJAX request for cancelled orders pagination
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch_cancelled') {
    header('Content-Type: application/json');
    
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 5;
    $offset = ($page - 1) * $per_page;
    
    // Count total cancelled orders
    $count_query = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'Cancelled'");
    $total_cancelled = $count_query->fetch_assoc()['total'];
    $total_pages = ceil($total_cancelled / $per_page);
    
    // Fetch cancelled orders
    $query = $conn->query("
        SELECT 
            o.orderID,
            o.garment_type AS garment,
            o.fabric_type,
            o.garment_price,
            o.total_garments,
            o.total_price,
            o.due_date,
            o.status,
            o.message,
            o.updated_at,
            c.full_name AS customer_name,
            c.contact AS contact,
            c.address
        FROM orders o
        JOIN customers c ON o.customer_id = c.customerID
        WHERE o.status = 'Cancelled'
        ORDER BY o.updated_at DESC
        LIMIT {$per_page} OFFSET {$offset}
    ");
    
    $orders = [];
    while ($row = $query->fetch_assoc()) {
        $orders[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'currentPage' => $page,
        'totalPages' => $total_pages,
        'totalOrders' => $total_cancelled
    ]);
    exit;
}

// Check if viewing cancelled orders
$viewCancelled = isset($_GET['view']) && $_GET['view'] === 'cancelled';

// Check if there's an active order being processed
$activeOrderQuery = $conn->query("SELECT orderID, order_status FROM confirmed_order WHERE order_status != 'Complete' LIMIT 1");
$hasActiveOrder = false;
$activeOrderInfo = null;
if ($activeOrderQuery && $activeOrderQuery->num_rows > 0) {
    $hasActiveOrder = true;
    $activeOrderInfo = $activeOrderQuery->fetch_assoc();
}

// ==========================================================
// ✅ CONFIRM / CANCEL ORDER SECTION
// ==========================================================


// Check if the URL has both 'action' and 'orderID' parameters (e.g. ?action=confirm&orderID=3)
if (isset($_POST['status']) && isset($_POST['orderID'])) {
  $orderID = intval($_POST['orderID']); // Convert orderID from URL into an integer for safety
  $action = $_POST['status']; // Get the action type ("confirm" or "cancel")

  // ----------------------------------------------------------
  // 🧩 STEP 1: Fetch full order details from the database
  // ----------------------------------------------------------
  // We get details from both 'orders' and 'customer' tables to store later in orderStatus
  $stmt = $conn->prepare("
    SELECT 
      o.orderID,
      o.garment_type,
      o.garment_price,
      o.due_date,
      c.full_name,
      c.contact,
      c.address
    FROM orders o
    JOIN customers c ON o.customer_id = c.customerID
    WHERE o.orderID = ?
");

  // Check if the order exists before doing any action
  if ($orderData && $orderData->num_rows > 0) {
     $order = $orderData->fetch_assoc(); // Store the order details in an array

    // ----------------------------------------------------------
    // ✅ IF USER CLICKED "CONFIRM"
    // ----------------------------------------------------------
    if ($action === 'confirmed') {
      // 1️⃣ Update the order in the 'orders' table as Confirmed
      $conn->query("UPDATE orders SET status = 'Confirmed' WHERE orderID = $orderID");

      // 2️⃣ Insert this order into the 'orderStatus' table for tracking progress
      //    The status will start as "ORDER PROCESSING" by default.
      $stmt = $conn->prepare("
        INSERT INTO confirmed_order (orderID, customer, garment_type, fabric_type, due_date, status)
        VALUES (?, ?, ?, ?, ?, ?)
      ");
      // Bind values safely to prevent SQL injection
      $stmt->bind_param("isssss", $order['orderID'], $order['full_name'], $order['garment_type'], $order['fabric_type'], $order['due_date']);
      $stmt->execute();

      // 3️⃣ Notify the user
      echo "<script>alert('✅ Order #{$orderID} confirmed and added to Order Status!');</script>";

    // ----------------------------------------------------------
    // ❌ IF USER CLICKED "CANCEL"
    // ----------------------------------------------------------
    } elseif ($action === 'cancelled') {
      // 1️⃣ Remove the order completely from 'orders' table
      $conn->query("DELETE FROM orders WHERE orderID = $orderID");

      // 2️⃣ (Optional) You could also remove it from orderStatus if it was previously added
      // $conn->query("DELETE FROM orderStatus WHERE orderID = $orderID");

      // 3️⃣ Notify the user
      echo "<script>alert('❌ Order #{$orderID} has been cancelled and removed.');</script>";
    }

  } else {
    // If the order doesn’t exist, show a warning
    echo "<script>alert('⚠️ Order not found or already processed.');</script>";
  }

  // ----------------------------------------------------------
  // 🔄 Refresh the page to show updated order list
  // ----------------------------------------------------------
  echo "<script>window.location.href='superadmin_order.php';</script>";
  exit(); // Stop running further PHP code
}

// ==========================================================
// 📦 FETCH ALL ORDERS FROM DATABASE (to display on the page)
// ==========================================================

if ($viewCancelled) {
  // Pagination for cancelled orders
  $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
  $per_page = 5;
  $offset = ($page - 1) * $per_page;
  
  // Count total cancelled orders
  $count_query = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status = 'Cancelled'");
  $total_cancelled = $count_query->fetch_assoc()['total'];
  $total_pages = ceil($total_cancelled / $per_page);
  
  // Fetch cancelled orders with pagination (5 per page, sorted by most recent)
  $orderQuery = $conn->query("
    SELECT 
      o.orderID,
      o.garment_type AS garment,
      o.fabric_type,
      o.garment_price,
      o.total_garments,
      o.total_price,
      o.due_date,
      o.status,
      o.message,
      o.updated_at,
      c.full_name AS customer_name,
      c.contact AS contact,
      c.address
    FROM orders o
    JOIN customers c ON o.customer_id = c.customerID
    WHERE o.status = 'Cancelled'
    ORDER BY o.updated_at DESC
    LIMIT {$per_page} OFFSET {$offset}
  ");
} else {
  // Fetch ALL pending orders (excluding cancelled, confirmed, and orders in confirmed_order table)
  // Once an order is confirmed, it moves to Order Status page for tracking
  $orderQuery = $conn->query("
    SELECT 
      o.orderID,
      o.garment_type AS garment,
      o.fabric_type,
      o.garment_price,
      o.total_garments,
      o.total_price,
      o.due_date,
      o.status,
      o.message,
      c.full_name AS customer_name,
      c.contact AS contact,
      c.address
    FROM orders o
    JOIN customers c ON o.customer_id = c.customerID
    LEFT JOIN confirmed_order co ON o.orderID = co.orderID
    WHERE o.status NOT IN ('Cancelled', 'Confirmed', 'Order Processing', 'Complete') 
    AND co.orderID IS NULL
    ORDER BY o.orderID DESC
  ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Orders - Owner</title>

<!-- Include Theme Loader for Custom Colors -->
<?php include('theme_loader.php'); ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      display: flex;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ===== Sidebar ===== */
    .sidebar {
      width: 260px;
      background: rgba(255, 255, 255, 0.98);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
      backdrop-filter: blur(10px);
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
    .sidebar a {
      color: #333;
      text-decoration: none;
      padding: 18px 30px;
      display: flex;
      align-items: center;
      gap: 15px;
      font-weight: 500;
      font-size: 15px;
      transition: all 0.3s ease;
      border-left: 4px solid transparent;
    }
    .sidebar a:hover {
      background: var(--theme-sidebar-hover);
      border-left-color: var(--theme-bg);
      color: var(--theme-sidebar-hover-font);
    }
    .sidebar a.active {
      background: var(--theme-sidebar-hover);
      border-left-color: var(--theme-bg);
      color: var(--theme-sidebar-hover-font);
      font-weight: 500;
    }
    .sidebar a i {
      font-size: 18px;
      width: 24px;
      text-align: center;
    }
    .sidebar .bottom {
      border-top: 1px solid rgba(0, 0, 0, 0.1);
      padding: 20px;
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

    /* ===== Main Section ===== */
    .main {
      flex: 1;
      padding: 40px;
      overflow-y: auto;
    }

    /* ===== Header ===== */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
      background: rgba(255, 255, 255, 0.95);
      padding: 25px 35px;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }
    .header h2 {
      font-size: 28px;
      font-weight: 700;
      color: var(--theme-bg);
      letter-spacing: 0.5px;
    }
    .header-left h1 {
      font-size: 28px;
      font-weight: 700;
      color: var(--theme-bg);
      letter-spacing: 0.5px;
      margin-bottom: 5px;
    }
    .header-left h1 i {
      margin-right: 10px;
    }
    .header-left p {
      color: #666;
      font-size: 15px;
      margin: 0;
    }
    .header-right {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    .header-right i {
      font-size: 20px;
      color: var(--theme-bg);
      cursor: pointer;
      transition: transform 0.3s ease;
    }
    .header-right i:hover {
      transform: scale(1.2);
    }
    .header-right img {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      border: 3px solid var(--theme-bg);
      object-fit: cover;
    }
    .header-right span {
      font-weight: 600;
      color: #333;
      font-size: 15px;
    }

    /* ===== Manage Orders Section ===== */
    .manage-orders {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 15px;
      padding: 20px 30px;
      margin-bottom: 30px;
      text-align: left;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      color: var(--theme-bg);
      font-size: 18px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }

    /* ===== Table Container ===== */
    .table-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
      overflow-x: auto;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    .table thead {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
    }

    .table thead th {
      padding: 15px;
      text-align: left;
      font-weight: 600;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 3px solid rgba(0, 0, 0, 0.1);
    }

    .table tbody tr {
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }

    .table tbody tr:hover {
      background: rgba(102, 126, 234, 0.05);
      transform: scale(1.01);
    }

    .table tbody td {
      padding: 15px;
      color: #333;
      font-size: 13px;
      vertical-align: middle;
    }

    .table tbody td:first-child {
      font-weight: 600;
    }

    /* ===== Orders Grid (Legacy - keeping for compatibility) ===== */
    .orders-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 300px));
      gap: 20px;
    }

    .order-card {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 15px;
      padding: 18px;
      text-transform: uppercase;
      color: #333;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      border: 2px solid transparent;
    }

    .order-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
      border-color: var(--theme-bg);
    }

    .order-card table {
      letter-spacing: 0.3px;
    }

    .order-card table td {
      vertical-align: top;
      word-wrap: break-word;
    }

    .order-buttons {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-top: 12px;
    }

    .order-buttons button {
      flex: 1;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      font-weight: 600;
      border: none;
      padding: 8px 12px;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 11px;
      letter-spacing: 0.5px;
    }

    .order-buttons button:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
    }

    .order-buttons button.cancel-btn {
      background: rgba(220, 53, 69, 0.2) !important;
      color: #dc3545 !important;
      border: 2px solid #dc3545 !important;
    }

    .order-buttons button.cancel-btn:hover {
      background: #dc3545 !important;
      color: white !important;
      box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    }

    .order-id-btn {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      font-weight: 700;
      border: none;
      padding: 6px 12px;
      border-radius: 8px;
      cursor: pointer;
      margin-bottom: 10px;
      display: inline-block;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      font-size: 10px;
    }

    .order-id-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 999;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.7);
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-content {
      background-color: #fff;
      color: #333;
      margin: 10% auto;
      padding: 35px;
      border-radius: 20px;
      width: 400px;
      max-width: 90%;
      text-align: left;
      position: relative;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .modal-content h3 {
      margin-top: 0;
      margin-bottom: 20px;
      text-align: center;
      color: var(--theme-bg);
      font-size: 24px;
      font-weight: 700;
    }

    .modal-content p {
      margin: 12px 0;
      line-height: 1.6;
      color: #555;
    }

    .modal-content strong {
      color: var(--theme-bg);
      font-weight: 600;
    }

    .modal-content .close {
      position: absolute;
      top: 15px;
      right: 20px;
      font-size: 24px;
      font-weight: bold;
      color: #999;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .modal-content .close:hover {
      color: #333;
      transform: scale(1.1);
    }

    .alert {
      padding: 15px 25px;
      border-radius: 15px;
      margin-bottom: 25px;
      font-weight: 600;
      font-size: 15px;
      animation: slideIn 0.3s ease;
    }

    .alert-success {
      background: linear-gradient(135deg, #d1e7dd 0%, #a3cfbb 100%);
      color: #0f5132;
      border-left: 5px solid #28a745;
    }

    .alert-error {
      background: linear-gradient(135deg, #f8d7da 0%, #f1aeb5 100%);
      color: #842029;
      border-left: 5px solid #dc3545;
    }

    .filter-btn {
      transition: all 0.3s ease;
    }

    .filter-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .order-label {
      color: var(--theme-bg) !important;
    }

    .modal-table-label {
      color: var(--theme-bg) !important;
    }

    .cancelled-orders-btn {
      background: rgba(220, 53, 69, 0.2) !important;
      color: #dc3545 !important;
      border: 2px solid #dc3545 !important;
    }

    .cancelled-orders-btn:hover {
      background: #dc3545 !important;
      color: white !important;
    }

    .cancelled-orders-btn.active {
      background: #dc3545 !important;
      color: white !important;
    }

    /* Mobile Menu Toggle */
    .mobile-menu-toggle {
      display: none;
      position: fixed;
      top: 20px;
      left: 20px;
      z-index: 1100;
      background: var(--theme-bg);
      color: white;
      border: none;
      border-radius: 10px;
      padding: 12px 15px;
      cursor: pointer;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
      transition: all 0.3s ease;
    }

    .mobile-menu-toggle:hover {
      transform: scale(1.05);
    }

    .mobile-menu-toggle i {
      font-size: 20px;
    }

    .sidebar.mobile-open {
      transform: translateX(0) !important;
    }

    .overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
    }

    .overlay.active {
      display: block;
    }

    @media (max-width: 768px) {
      .mobile-menu-toggle {
        display: block;
      }

      .main {
        padding: 80px 15px 20px 15px;
      }

      .sidebar {
        position: fixed;
        height: 100vh;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        width: 280px;
        z-index: 1000;
      }

      .header {
        flex-direction: column;
        align-items: flex-start;
        padding: 20px;
        gap: 15px;
      }

      .header h2 {
        font-size: 20px;
      }

      .header-right {
        width: 100%;
        justify-content: space-between;
      }

      .table-container {
        padding: 10px;
        overflow-x: auto;
      }

      .table {
        font-size: 11px;
      }

      .table thead th {
        padding: 10px 8px;
        font-size: 10px;
      }

      .table tbody td {
        padding: 10px 8px;
        font-size: 11px;
      }

      .order-id-btn {
        font-size: 11px !important;
        padding: 6px 10px !important;
      }

      .orders-grid {
        grid-template-columns: 1fr;
        gap: 15px;
      }

      .order-card {
        padding: 20px;
      }

      .order-card table {
        font-size: 11px;
      }

      .order-card table td {
        padding: 5px 0 !important;
        font-size: 11px !important;
      }

      .order-buttons button {
        padding: 10px 20px;
        font-size: 13px;
      }

      .modal-content {
        width: 95%;
        padding: 25px 20px;
      }

      .modal-content h3 {
        font-size: 20px;
      }

      .modal-content table td {
        padding: 8px !important;
        font-size: 13px;
      }
    }

    @media (max-width: 480px) {
      .main {
        padding: 70px 10px 15px 10px;
      }

      .header {
        padding: 15px;
        border-radius: 15px;
      }

      .header h2 {
        font-size: 18px;
      }

      .sidebar {
        width: 260px;
      }

      .sidebar a {
        padding: 15px 20px;
        font-size: 14px;
      }

      .sidebar-logo img {
        width: 70px;
        height: 70px;
      }

      .table-container {
        padding: 5px;
      }

      .table {
        font-size: 10px;
      }

      .table thead th {
        padding: 8px 5px;
        font-size: 9px;
      }

      .table tbody td {
        padding: 8px 5px;
        font-size: 10px;
      }

      .order-id-btn {
        font-size: 10px !important;
        padding: 5px 8px !important;
      }

      .orders-grid {
        gap: 12px;
      }

      .order-card {
        padding: 15px;
      }

      .order-card table td {
        padding: 4px 0 !important;
        font-size: 10px !important;
      }

      .order-buttons {
        flex-direction: column;
        gap: 8px;
      }

      .order-buttons button {
        width: 100%;
        padding: 10px;
        font-size: 12px;
      }

      .modal-content {
        padding: 20px 15px;
      }

      .modal-content h3 {
        font-size: 18px;
      }

      .modal-content table td {
        padding: 6px !important;
        font-size: 12px;
      }
    }
</style>
</head>
<body>

<button class="mobile-menu-toggle" onclick="toggleMobileSidebar()">
  <i class="fa-solid fa-bars"></i>
</button>

<div class="overlay" onclick="toggleMobileSidebar()"></div>
     
<!-- Sidebar -->
<div class="sidebar">
  <div>
      <div class="sidebar-logo">
        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Business Logo">
      </div>
      <a href="superadmin_dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
      <a href="superadmin_employees.php"><i class="fa-solid fa-users"></i> Employees</a>
      <a href="superadmin_payroll.php"><i class="fa-solid fa-money-bill"></i> Payroll</a>
      <a href="superadmin_order.php" class="active"><i class="fa-solid fa-receipt"></i> Orders</a>
      <a href="superadmin_orderStatus.php"><i class="fa-solid fa-chart-bar"></i> Order Status</a>
      <a href="customize.php"><i class="fa-solid fa-gear"></i> Customize</a>
    </div>
  <div class="bottom">
    <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</div>

<!-- Main -->
<div class="main">
  <div class="header">
    <div class="header-left">
      <h1><i class="fa-solid fa-receipt"></i> Order Processing Control</h1>
      <p>Manage order decisions: confirm or cancel</p>
    </div>
    <div class="header-right">
      <img src="<?= htmlspecialchars($photo_path) ?>" alt="Profile">
      <span><?= htmlspecialchars($owner_name) ?></span>
    </div>
  </div>

  <div class="manage-orders" style="display: flex; justify-content: space-between; align-items: center;">
    <span><?= $viewCancelled ? 'CANCELLED ORDERS' : 'MANAGE ALL CUSTOMER ORDERS' ?></span>
    <div style="display: flex; gap: 10px;">
      <a href="superadmin_order.php" style="text-decoration: none;">
        <button class="filter-btn <?= !$viewCancelled ? 'active' : '' ?>" style="background: <?= !$viewCancelled ? 'linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%)' : 'white' ?>; color: <?= !$viewCancelled ? 'var(--theme-font)' : 'var(--theme-bg)' ?>; border: <?= !$viewCancelled ? 'none' : '2px solid var(--theme-bg)' ?>; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease;">
          ALL ORDERS
        </button>
      </a>
      <a href="superadmin_order.php?view=cancelled" style="text-decoration: none;">
        <button class="filter-btn cancelled-orders-btn <?= $viewCancelled ? 'active' : '' ?>" style="padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease;">
          CANCELLED ORDERS
        </button>
      </a>
    </div>
  </div>

  <?php if ($hasActiveOrder && !$viewCancelled): ?>
    <div class="alert" style="background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%); color: #856404; border-left: 5px solid #ffc107;">
      ⚠️ <strong>Active Order in Progress:</strong> Order #<?= $activeOrderInfo['orderID'] ?> is currently being processed (Status: <?= htmlspecialchars($activeOrderInfo['order_status']) ?>). 
      Please complete this order before confirming new orders. <a href="superadmin_orderStatus.php" style="color: #667eea; font-weight: 700; text-decoration: underline;">View Order Status →</a>
    </div>
  <?php endif; ?>

  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
      ✅ <?= htmlspecialchars($_SESSION['success_message']) ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-error">
      ❌ <?= htmlspecialchars($_SESSION['error_message']) ?>
    </div>
    <?php unset($_SESSION['error_message']); ?>
  <?php endif; ?>

  <?php if ($viewCancelled): ?>
    <!-- CANCELLED ORDERS - TABLE FORMAT WITH AJAX PAGINATION -->
    <div class="table-container" id="cancelledOrdersContainer">
      <div id="cancelledOrdersContent">
        <!-- Content will be loaded via AJAX -->
      </div>
      <div id="paginationControls" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px;">
        <!-- Pagination will be loaded via AJAX -->
      </div>
    </div>
  <?php else: ?>
    <!-- ALL ORDERS - CARD FORMAT -->
    <div class="orders-grid">
      <?php
      if ($orderQuery && $orderQuery->num_rows > 0) {
        while ($order = $orderQuery->fetch_assoc()) {
          $status = htmlspecialchars($order['status']);
          $statusColor = '';
          
          // Determine status color
          switch($status) {
            case 'Order Confirmation':
              $statusColor = 'background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);';
              break;
            case 'Order Processing':
              $statusColor = 'background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);';
              break;
            case 'Cancelled':
              $statusColor = 'background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);';
              break;
            case 'Completed':
              $statusColor = 'background: linear-gradient(135deg, #28a745 0%, #20c997 100%);';
              break;
            default:
              $statusColor = 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);';
          }
          
          // Calculate price offered (total_garments * garment_price)
          $total_garments = isset($order['total_garments']) ? $order['total_garments'] : 0;
          $price_offered = $total_garments * $order['garment_price'];
          $contact = isset($order['contact']) ? $order['contact'] : 'N/A';
          $address = isset($order['address']) ? $order['address'] : 'N/A';
          $message = isset($order['message']) && !empty($order['message']) ? $order['message'] : 'No message provided';
          
          echo "<div class='order-card' id='order-card-{$order['orderID']}'>
            <button class='order-id-btn' onclick='showPopup({$order['orderID']}, \"" . htmlspecialchars($order['customer_name']) . "\", \"" . htmlspecialchars($contact) . "\", \"" . htmlspecialchars($address) . "\", \"" . htmlspecialchars($message, ENT_QUOTES) . "\", {$price_offered})'>
              ORDER #{$order['orderID']}
            </button>
            <table style='width: 100%; font-size: 13px;'>
              <tr>
                <td style='padding: 8px 0; color: var(--theme-bg); font-weight: 600;'>Total Garments:</td>
                <td style='padding: 8px 0; color: #555;'>{$total_garments} pcs</td>
              </tr>
              <tr>
                <td style='padding: 8px 0; color: var(--theme-bg); font-weight: 600;'>Fabric Type:</td>
                <td style='padding: 8px 0; color: #555;'>" . htmlspecialchars($order['fabric_type']) . "</td>
              </tr>
              <tr>
                <td style='padding: 8px 0; color: var(--theme-bg); font-weight: 600;'>Due Date:</td>
                <td style='padding: 8px 0; color: #555;'>" . date('M d, Y', strtotime($order['due_date'])) . "</td>
              </tr>
              <tr>
                <td style='padding: 8px 0; color: var(--theme-bg); font-weight: 600;'>Price Offered:</td>
                <td style='padding: 8px 0; color: #555; font-weight: 600;'>₱" . number_format($price_offered, 2) . "</td>
              </tr>
              <tr>
                <td style='padding: 8px 0; color: var(--theme-bg); font-weight: 600;'>Status:</td>
                <td style='padding: 8px 0;'>
                  <span style='display: inline-block; padding: 6px 14px; border-radius: 20px; color: white; font-weight: 600; font-size: 11px; {$statusColor}'>
                    {$status}
                  </span>
                </td>
              </tr>
            </table>
            <div class='order-buttons'>";
          
          if ($hasActiveOrder) {
            // Disable confirm button if there's an active order
            echo "<button disabled style='opacity: 0.5; cursor: not-allowed; background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white;' title='Cannot confirm: There is already an active order being processed'>✓ CONFIRM</button>
              <button class='cancel-btn' onclick=\"cancelOrder({$order['orderID']})\">✗ CANCEL</button>";
          } else {
            // Normal buttons when no active order
            echo "<button onclick=\"confirmOrder({$order['orderID']})\" style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white;'>✓ CONFIRM</button>
              <button class='cancel-btn' onclick=\"cancelOrder({$order['orderID']})\">✗ CANCEL</button>";
          }
          
          echo "</div>
          </div>";
        }
      } else {
        echo "<p style='color: white; text-align: center; padding: 40px; font-size: 18px; grid-column: 1 / -1;'>No orders found.</p>";
      }
      ?>
    </div>
  <?php endif; ?>
</div>

<!-- Modal Popup -->
<div id="infoModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closePopup()">&times;</span>
    <h3>Client Info</h3>
    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
      <tr>
        <td class="modal-table-label" style="padding: 10px; border-bottom: 1px solid #e0e0e0; font-weight: 600; width: 40%;">Name:</td>
        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0; color: #555;" id="clientName"></td>
      </tr>
      <tr>
        <td class="modal-table-label" style="padding: 10px; border-bottom: 1px solid #e0e0e0; font-weight: 600;">Contact Number:</td>
        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0; color: #555;" id="clientContact"></td>
      </tr>
      <tr>
        <td class="modal-table-label" style="padding: 10px; border-bottom: 1px solid #e0e0e0; font-weight: 600;">Address:</td>
        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0; color: #555;" id="clientAddress"></td>
      </tr>
      <tr>
        <td class="modal-table-label" style="padding: 10px; border-bottom: 1px solid #e0e0e0; font-weight: 600; vertical-align: top;">Message:</td>
        <td style="padding: 10px; border-bottom: 1px solid #e0e0e0; color: #555;" id="clientMessage"></td>
      </tr>
      <tr>
        <td class="modal-table-label" style="padding: 10px; font-weight: 600;">Price Offered:</td>
        <td style="padding: 10px; color: #555;">₱<span id="clientPrice"></span></td>
      </tr>
    </table>
  </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('confirmModal')">&times;</span>
    <div style="text-align: center; margin-bottom: 20px;">
      <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3);">
        <i class="fa-solid fa-check" style="font-size: 40px; color: white;"></i>
      </div>
      <h3 style="margin: 0; color: #28a745; font-size: 26px; font-weight: 700;">Confirm Order</h3>
    </div>
    <p id="confirmMessage" style="color: #555; margin-bottom: 30px; font-size: 15px; line-height: 1.6;"></p>
    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
      <button id="confirmYes" style="flex: 1; min-width: 140px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 14px 28px; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); text-transform: uppercase; letter-spacing: 0.5px;">
        <i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i>Yes, Confirm
      </button>
      <button onclick="closeModal('confirmModal')" style="flex: 1; min-width: 140px; background: white; color: #6c757d; border: 2px solid #dee2e6; padding: 14px 28px; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px;">
        <i class="fa-solid fa-times-circle" style="margin-right: 8px;"></i>Cancel
      </button>
    </div>
  </div>
</div>

<!-- Cancel Modal -->
<div id="cancelModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('cancelModal')">&times;</span>
    <div style="text-align: center; margin-bottom: 20px;">
      <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 30px rgba(220, 53, 69, 0.3);">
        <i class="fa-solid fa-exclamation-triangle" style="font-size: 40px; color: white;"></i>
      </div>
      <h3 style="margin: 0; color: #dc3545; font-size: 26px; font-weight: 700;">Cancel Order</h3>
    </div>
    <p id="cancelMessage" style="color: #555; margin-bottom: 30px; font-size: 15px; line-height: 1.6;"></p>
    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
      <button id="cancelYes" style="flex: 1; min-width: 140px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; padding: 14px 28px; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3); text-transform: uppercase; letter-spacing: 0.5px;">
        <i class="fa-solid fa-ban" style="margin-right: 8px;"></i>Yes, Cancel
      </button>
      <button onclick="closeModal('cancelModal')" style="flex: 1; min-width: 140px; background: white; color: #6c757d; border: 2px solid #dee2e6; padding: 14px 28px; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 15px; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px;">
        <i class="fa-solid fa-shield-alt" style="margin-right: 8px;"></i>No, Keep It
      </button>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeSuccessModal()">&times;</span>
    <div style="text-align: center; margin-bottom: 20px;">
      <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 30px rgba(40, 167, 69, 0.3); animation: successPulse 0.6s ease;">
        <i class="fa-solid fa-check-circle" style="font-size: 45px; color: white;"></i>
      </div>
      <h3 style="margin: 0; color: #28a745; font-size: 28px; font-weight: 700;">Success!</h3>
    </div>
    <p id="successMessage" style="color: #555; margin-bottom: 30px; font-size: 15px; line-height: 1.6;"></p>
    <div style="display: flex; justify-content: center;">
      <button onclick="closeSuccessModal()" style="min-width: 160px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border: none; padding: 14px 32px; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 16px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); text-transform: uppercase; letter-spacing: 0.5px;">
        <i class="fa-solid fa-thumbs-up" style="margin-right: 8px;"></i>OK
      </button>
    </div>
  </div>
</div>

<!-- Error Modal -->
<div id="errorModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal('errorModal')">&times;</span>
    <div style="text-align: center; margin-bottom: 20px;">
      <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 30px rgba(220, 53, 69, 0.3); animation: errorShake 0.5s ease;">
        <i class="fa-solid fa-times-circle" style="font-size: 45px; color: white;"></i>
      </div>
      <h3 style="margin: 0; color: #dc3545; font-size: 28px; font-weight: 700;">Error!</h3>
    </div>
    <p id="errorMessage" style="color: #555; margin-bottom: 30px; font-size: 15px; line-height: 1.6;"></p>
    <div style="display: flex; justify-content: center;">
      <button onclick="closeModal('errorModal')" style="min-width: 160px; background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; border: none; padding: 14px 32px; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 16px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3); text-transform: uppercase; letter-spacing: 0.5px;">
        <i class="fa-solid fa-times" style="margin-right: 8px;"></i>Close
      </button>
    </div>
  </div>
</div>

<style>
  @keyframes successPulse {
    0% { transform: scale(0.8); opacity: 0; }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
  }
  
  @keyframes errorShake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
  }
  
  #confirmModal button:hover,
  #cancelModal button:hover,
  #successModal button:hover,
  #errorModal button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
  }
  
  #confirmModal button:active,
  #cancelModal button:active,
  #successModal button:active,
  #errorModal button:active {
    transform: translateY(0);
  }
</style>

<script>
  // Mobile sidebar toggle
  function toggleMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.overlay');
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
  }

  let pendingOrderAction = null;

  function showPopup(orderID, name, contact, address, message, price) {
    document.getElementById("clientName").textContent = name;
    document.getElementById("clientContact").textContent = contact;
    document.getElementById("clientAddress").textContent = address;
    document.getElementById("clientMessage").textContent = message;
    document.getElementById("clientPrice").textContent = parseFloat(price).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById("infoModal").style.display = "block";
  }

  function closePopup() {
    document.getElementById("infoModal").style.display = "none";
  }

  function showModal(modalId) {
    document.getElementById(modalId).style.display = "block";
  }

  function closeModal(modalId) {
    document.getElementById(modalId).style.display = "none";
  }

  function closeSuccessModal() {
    closeModal('successModal');
    // No need to reload - card already hidden
  }

  function confirmOrder(orderID) {
    pendingOrderAction = { orderID, action: 'confirmed' };
    document.getElementById('confirmMessage').innerHTML = 
      `Are you sure you want to confirm <strong>Order #${orderID}</strong>?<br><br>` +
      `This will move the order to Order Status page for tracking.`;
    showModal('confirmModal');
  }

  function cancelOrder(orderID) {
    pendingOrderAction = { orderID, action: 'cancelled' };
    document.getElementById('cancelMessage').innerHTML = 
      `Are you sure you want to cancel <strong>Order #${orderID}</strong>?<br><br>` +
      `The order status will be changed to <strong style="color: #dc3545;">"Cancelled"</strong> and the customer will be able to submit a new service request.`;
    showModal('cancelModal');
  }

  document.getElementById('confirmYes').addEventListener('click', function() {
    if (!pendingOrderAction) return;
    closeModal('confirmModal');
    processOrderAction(pendingOrderAction.orderID, pendingOrderAction.action);
  });

  document.getElementById('cancelYes').addEventListener('click', function() {
    if (!pendingOrderAction) return;
    closeModal('cancelModal');
    processOrderAction(pendingOrderAction.orderID, pendingOrderAction.action);
  });

  function processOrderAction(orderID, action) {
    fetch('process_order_action.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        action: action,
        orderID: orderID
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const message = action === 'confirmed' 
          ? `Order #${orderID} has been confirmed successfully!<br><br>It's now visible in the Order Status page where you can track its progress.`
          : `Order #${orderID} has been cancelled successfully.<br><br>The customer can now submit a new service request.`;
        
        document.getElementById('successMessage').innerHTML = message;
        showModal('successModal');
        
        // Hide the order card with animation
        const orderCard = document.getElementById(`order-card-${orderID}`);
        if (orderCard) {
          orderCard.style.transition = 'all 0.3s ease';
          orderCard.style.opacity = '0';
          orderCard.style.transform = 'scale(0.8)';
          setTimeout(() => {
            orderCard.style.display = 'none';
          }, 300);
        }
      } else {
        document.getElementById('errorMessage').textContent = data.message || 'An error occurred. Please try again.';
        showModal('errorModal');
      }
      pendingOrderAction = null;
    })
    .catch(error => {
      console.error('Error:', error);
      // Silently handle network errors - just log to console
      pendingOrderAction = null;
    });
  }

  window.onclick = function(event) {
    const modal = document.getElementById("infoModal");
    if (event.target === modal) {
      modal.style.display = "none";
    }
    
    // Close other modals when clicking outside
    if (event.target.classList.contains('modal')) {
      event.target.style.display = "none";
    }
  }

  // AJAX Pagination for Cancelled Orders
  function loadCancelledOrders(page = 1) {
    const contentDiv = document.getElementById('cancelledOrdersContent');
    const paginationDiv = document.getElementById('paginationControls');
    
    // Show loading state
    contentDiv.innerHTML = '<p style="color: #666; text-align: center; padding: 40px; font-size: 16px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading cancelled orders...</p>';
    paginationDiv.innerHTML = '';
    
    fetch(`superadmin_order.php?ajax=fetch_cancelled&page=${page}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          if (data.orders.length > 0) {
            // Build table HTML
            let tableHTML = `
              <table class='table'>
                <thead>
                  <tr>
                    <th>Order ID</th>
                    <th>Total Garments</th>
                    <th>Fabric Type</th>
                    <th>Price Offered</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Cancelled On</th>
                  </tr>
                </thead>
                <tbody>`;
            
            data.orders.forEach(order => {
              const totalGarments = order.total_garments || 0;
              const priceOffered = totalGarments * order.garment_price;
              const contact = order.contact || 'N/A';
              const address = order.address || 'N/A';
              const message = order.message || 'No message provided';
              const cancelledDate = order.updated_at ? new Date(order.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
              const dueDate = new Date(order.due_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
              
              tableHTML += `
                <tr>
                  <td>
                    <button class='order-id-btn' onclick='showPopup(${order.orderID}, "${escapeHtml(order.customer_name)}", "${escapeHtml(contact)}", "${escapeHtml(address)}", "${escapeHtml(message)}", ${priceOffered})' style='background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s ease;'>
                      #${order.orderID}
                    </button>
                  </td>
                  <td>${totalGarments} pcs</td>
                  <td>${escapeHtml(order.fabric_type)}</td>
                  <td>₱${priceOffered.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})}<br><small style='color: #999;'>(${totalGarments} × ₱${parseFloat(order.garment_price).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})})</small></td>
                  <td>${dueDate}</td>
                  <td>
                    <span style='display: inline-block; padding: 6px 14px; border-radius: 20px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; font-weight: 600; font-size: 11px;'>
                      CANCELLED
                    </span>
                  </td>
                  <td style='color: #999; font-size: 13px;'>${cancelledDate}</td>
                </tr>`;
            });
            
            tableHTML += `
                </tbody>
              </table>`;
            
            contentDiv.innerHTML = tableHTML;
            
            // Build pagination controls
            if (data.totalPages > 1) {
              let paginationHTML = '';
              
              // Previous button
              if (data.currentPage > 1) {
                paginationHTML += `<button onclick='loadCancelledOrders(${data.currentPage - 1})' style='background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;'>← Previous</button>`;
              }
              
              // Page info
              paginationHTML += `<span style='color: white; font-weight: 600; font-size: 15px;'>Page ${data.currentPage} of ${data.totalPages}</span>`;
              
              // Next button
              if (data.currentPage < data.totalPages) {
                paginationHTML += `<button onclick='loadCancelledOrders(${data.currentPage + 1})' style='background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;'>Next →</button>`;
              }
              
              paginationDiv.innerHTML = paginationHTML;
            }
          } else {
            contentDiv.innerHTML = '<p style="color: white; text-align: center; padding: 40px; font-size: 18px;">No cancelled orders found.</p>';
          }
        } else {
          contentDiv.innerHTML = '<p style="color: #dc3545; text-align: center; padding: 40px; font-size: 16px;">Error loading cancelled orders. Please try again.</p>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        contentDiv.innerHTML = '<p style="color: #dc3545; text-align: center; padding: 40px; font-size: 16px;">Network error. Please check your connection and try again.</p>';
      });
  }

  // Helper function to escape HTML
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Load cancelled orders on page load if viewing cancelled orders
  <?php if ($viewCancelled): ?>
    document.addEventListener('DOMContentLoaded', function() {
      loadCancelledOrders(1);
    });
  <?php endif; ?>

</script>

</body>
</html>
