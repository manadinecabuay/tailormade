<?php 
include('connection.php');
include 'session_customer.php'; ?>

<?php
include 'business_function.php';

$business = getBusinessInfo($conn);

// Now you can access values like:
$business_name = $business['business_name'];
$logo_path = $business['logo_path'];
$about_us = $business['about_us'];
$email = $business['email'];
$contact = $business['contact'];
$machine_message = $business['machine_availability_message'];


// Check if logged in
if (!isset($_SESSION['customerID'])) {
  header("Location: customer_login.php?status=error&msg=" . urlencode("Please login first"));
  exit();
}

$customer_id = $_SESSION['customerID'];

// Get customer name for matching with confirmed_order table
$customerStmt = $conn->prepare("SELECT full_name FROM customers WHERE customerID = ?");
$customerStmt->bind_param("i", $customer_id);
$customerStmt->execute();
$customerResult = $customerStmt->get_result();
$customerData = $customerResult->fetch_assoc();
$customer_name = $customerData['full_name'] ?? '';

// Fetch current orders (not completed or cancelled)
// Join with confirmed_order to get real-time status
// Exclude orders that are Complete in confirmed_order table
$stmt_current = $conn->prepare("SELECT o.orderID, o.garment_type, o.fabric_type, o.total_garments, o.garment_price, o.total_price, o.due_date, o.status, o.updated_at,
                        (SELECT COUNT(*) FROM confirmed_order WHERE orderID = o.orderID) as is_confirmed,
                        (SELECT order_status FROM confirmed_order WHERE orderID = o.orderID LIMIT 1) as confirmed_status
                        FROM orders o
                        LEFT JOIN confirmed_order co ON o.orderID = co.orderID
                        WHERE o.customer_id = ? 
                        AND o.status NOT IN ('confirmed', 'cancelled', 'Complete')
                        AND (co.order_status IS NULL OR co.order_status != 'Complete')
                        ORDER BY o.orderID DESC");
$stmt_current->bind_param("i", $customer_id);
$stmt_current->execute();
$current_orders = $stmt_current->get_result();

// Fetch completed orders - check confirmed_order table for Complete status
$stmt_completed = $conn->prepare("SELECT o.orderID, o.garment_type, o.fabric_type, o.total_garments, o.garment_price, o.total_price, o.due_date, 
                        COALESCE(co.order_status, o.status) as status, 
                        COALESCE(co.updated_at, o.updated_at) as updated_at
                        FROM orders o
                        LEFT JOIN confirmed_order co ON o.orderID = co.orderID
                        WHERE o.customer_id = ? AND (co.order_status = 'Complete' OR o.status = 'Complete')
                        ORDER BY o.orderID DESC");
$stmt_completed->bind_param("i", $customer_id);
$stmt_completed->execute();
$completed_orders = $stmt_completed->get_result();

// Fetch cancelled orders
$stmt_cancelled = $conn->prepare("SELECT orderID, garment_type, fabric_type, total_garments, garment_price, total_price, due_date, status, updated_at 
                        FROM orders 
                        WHERE customer_id = ? AND status = 'Cancelled'
                        ORDER BY orderID DESC");

$stmt_cancelled->bind_param("i", $customer_id);
$stmt_cancelled->execute();
$cancelled_orders = $stmt_cancelled->get_result();

// Check for unviewed Order Pack notifications
//$notificationQuery = $conn->prepare("
//  SELECT COUNT(*) as unviewed_count 
//  FROM orders o
//  LEFT JOIN order_image_views oiv ON o.id = oiv.order_id AND oiv.customer_id = ?
//  WHERE o.customer_id = ? AND o.status = 'Order Pack' AND oiv.id IS NULL
//");
//$notificationQuery->bind_param("ii", $customer_id, $customer_id);
//$notificationQuery->execute();
//$notificationResult = $notificationQuery->get_result();
//$notificationData = $notificationResult->fetch_assoc();
//$hasNotification = $notificationData['unviewed_count'] > 0;

// Function to calculate progress percentage
function getProgressPercentage($status) {
  $statusMap = [
    'Order Confirmation' => 17,
    'Order Processing' => 33,
    'Quality Check' => 50,
    'Order Pack' => 67,
    'Out for Delivery' => 83,
    'Complete' => 100
  ];
  return $statusMap[$status] ?? 0;
}

// Function to get progress color
function getProgressColor($percentage) {
  if ($percentage <= 25) return '#dc3545'; // Red
  if ($percentage <= 50) return '#fd7e14'; // Orange
  if ($percentage <= 75) return '#ffc107'; // Yellow
  return '#28a745'; // Green
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TailorMade - My Orders</title>

  <?php include('theme_loader.php'); ?>

  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      min-height: 100vh;
      color: #333;
    }

    .navbar {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      padding: 20px 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .logo img { 
      width: 45px;
      height: 45px;
      object-fit: cover;
      border-radius: 50%;
      border: 2px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .logo-text {
      font-size: 20px;
      font-weight: 700;
      color: white;
      letter-spacing: 0.5px;
    }

    .navbar ul {
      list-style: none;
      display: flex;
      gap: 20px;
    }

    .navbar ul li a {
      text-decoration: none;
      color: white;
      padding: 10px 20px;
      border-radius: 25px;
      transition: all 0.3s ease;
      font-weight: 500;
      border: 2px solid transparent;
    }

    .navbar ul li a:hover, .navbar ul li a.active {
      border: 2px solid white;
      background: rgba(255, 255, 255, 0.1);
    }

    .notification-badge {
      position: relative;
      display: inline-block;
    }

    .notification-badge::after {
      content: '';
      position: absolute;
      top: -5px;
      right: -5px;
      width: 12px;
      height: 12px;
      background: #dc3545;
      border-radius: 50%;
      border: 2px solid #0b0c2a;
    }

    .container {
      max-width: 1100px;
      margin: 40px auto;
      padding: 30px;
    }

    h2 {
      color: white;
      margin-bottom: 0;
      font-size: 24px;
      font-weight: 600;
    }

    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 30px;
      margin-bottom: 25px;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      padding: 20px 35px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
      transition: all 0.3s ease;
    }
    
    .section-header:first-of-type {
      margin-top: 0;
    }

    .section-header:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
    }

    .section-header h2 {
      color: var(--theme-bg);
      font-size: 22px;
      letter-spacing: 1px;
    }

    .dropdown-btn {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      border: none;
      padding: 10px 18px;
      border-radius: 25px;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 16px;
      font-weight: 600;
    }

    .dropdown-btn:hover {
      transform: scale(1.05);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.4);
    }

    .dropdown-btn.rotate {
      transform: rotate(180deg);
    }

    .orders-container {
      transition: max-height 0.5s ease, opacity 0.5s ease;
      overflow: hidden;
      margin-top: 5px;
      max-height: 5000px;
      opacity: 1;
    }

    .orders-container.hidden {
      max-height: 0;
      opacity: 0;
    }

    .order-card {
      background: rgba(255, 255, 255, 0.95);
      color: #333;
      padding: 30px;
      border-radius: 20px;
      margin-bottom: 20px;
      position: relative;
      transition: all 0.3s ease;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .order-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
    }

    .order-card h3 {
      margin: 0 0 20px;
      font-size: 20px;
      color: var(--theme-bg);
      font-weight: 700;
    }

    .order-card p {
      margin: 10px 0;
      font-size: 15px;
      color: #555;
      line-height: 1.6;
    }

    .order-card strong {
      color: #333;
      font-weight: 600;
    }

    .order-date {
      position: absolute;
      top: 20px;
      right: 20px;
      font-size: 13px;
      color: #aaa;
    }

    .progress-bar-container {
      width: 100%;
      height: 25px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 12px;
      overflow: hidden;
      margin: 15px 0;
    }

    .progress-bar {
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 12px;
      transition: width 0.5s ease;
    }

    .cancel-btn {
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
      color: white;
      border: none;
      padding: 10px 25px;
      border-radius: 25px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      margin-top: 15px;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .cancel-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
    }

    .no-orders {
      text-align: center;
      padding: 60px 40px;
      color: white;
      font-size: 16px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 15px;
      backdrop-filter: blur(10px);
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.6);
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background: white;
      padding: 40px;
      border-radius: 25px;
      text-align: center;
      width: 90%;
      max-width: 450px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .modal-icon {
      font-size: 60px;
      margin-bottom: 20px;
      animation: bounce 0.5s ease;
    }

    .success-icon {
      color: #28a745;
    }

    .error-icon {
      color: #dc3545;
    }

    @keyframes bounce {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }

    .modal-content h3 {
      margin-bottom: 15px;
      color: #333;
      font-size: 26px;
      font-weight: 700;
    }

    .modal-content p {
      margin-bottom: 25px;
      color: #666;
      font-size: 16px;
      line-height: 1.6;
    }

    .modal-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
    }

    .modal-buttons button {
      padding: 12px 30px;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      font-size: 15px;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn-yes {
      background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
      color: white;
    }

    .btn-yes:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
    }

    .btn-no {
      background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
      color: white;
    }

    .btn-no:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(127, 140, 141, 0.4);
    }

    .btn-success {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
      padding: 12px 30px;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      font-size: 15px;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
    }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
      .navbar {
        padding: 15px 20px;
        flex-wrap: wrap;
        gap: 15px;
      }

      .logo img {
        width: 40px;
        height: 40px;
      }

      .logo-text {
        font-size: 18px;
      }

      .navbar ul {
        width: 100%;
        justify-content: space-around;
        gap: 10px;
        flex-wrap: wrap;
      }

      .navbar ul li a {
        padding: 8px 15px;
        font-size: 14px;
      }

      .container {
        margin: 20px auto;
        padding: 15px;
      }

      .section-header {
        padding: 15px 20px;
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }

      .section-header h2 {
        font-size: 18px;
      }

      .dropdown-btn {
        align-self: flex-end;
        padding: 8px 15px;
        font-size: 14px;
      }

      /* Make tables scrollable horizontally on mobile */
      .orders-container > div {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }

      .orders-container table {
        min-width: 800px;
        font-size: 13px;
      }

      .orders-container th,
      .orders-container td {
        padding: 10px 8px !important;
        font-size: 12px !important;
      }

      .cancel-btn {
        padding: 8px 15px;
        font-size: 12px;
      }

      .no-orders {
        padding: 40px 20px;
        font-size: 14px;
      }

      .modal-content {
        width: 95%;
        padding: 30px 20px;
      }

      .modal-icon {
        font-size: 50px;
      }

      .modal-content h3 {
        font-size: 22px;
      }

      .modal-content p {
        font-size: 14px;
      }

      .modal-buttons {
        flex-direction: column;
        gap: 10px;
      }

      .modal-buttons button {
        width: 100%;
      }
    }

    @media (max-width: 480px) {
      .navbar {
        padding: 12px 15px;
      }

      .logo img {
        width: 35px;
        height: 35px;
      }

      .logo-text {
        font-size: 16px;
      }

      .navbar ul {
        gap: 5px;
      }

      .navbar ul li a {
        padding: 6px 12px;
        font-size: 13px;
      }

      .container {
        margin: 15px auto;
        padding: 10px;
      }

      .section-header {
        padding: 12px 15px;
        border-radius: 15px;
      }

      .section-header h2 {
        font-size: 16px;
      }

      .dropdown-btn {
        padding: 6px 12px;
        font-size: 13px;
      }

      .orders-container table {
        min-width: 750px;
        font-size: 11px;
      }

      .orders-container th,
      .orders-container td {
        padding: 8px 6px !important;
        font-size: 11px !important;
      }

      .cancel-btn {
        padding: 6px 12px;
        font-size: 11px;
      }

      .no-orders {
        padding: 30px 15px;
        font-size: 13px;
      }

      .modal-content {
        padding: 25px 15px;
        border-radius: 15px;
      }

      .modal-icon {
        font-size: 45px;
        margin-bottom: 15px;
      }

      .modal-content h3 {
        font-size: 20px;
        margin-bottom: 12px;
      }

      .modal-content p {
        font-size: 13px;
        margin-bottom: 20px;
      }

      .modal-buttons button {
        padding: 10px 20px;
        font-size: 13px;
      }
    }
  </style>
</head>
<body>
  <div class="navbar">

   
  <div class="logo">
    <img src="<?php echo $business ['logo_path']; ?>" alt="TailorMade Logo">
    <span class="logo-text">Tailormade</span>
  </div>

    <ul>
      <li><a href="user_home.php">Home</a></li>
      <li><a href="orders.php" class="active">Order</a></li>
      <li>
        <a href="track.php" class="<?php echo $hasNotification ? 'notification-badge' : ''; ?>">Track</a>
      </li>
      <li><a href="customer_edit_info.php">Account</a></li>
    </ul>
  </div>

  <div class="container">
    <!-- Current Orders Section -->
    <div class="section-header">
      <h2>CURRENT SERVICES</h2>
      <button id="toggleCurrentOrders" class="dropdown-btn">▼</button>
    </div>
    
    <div id="currentOrdersContainer" class="orders-container">
      <?php if ($current_orders->num_rows > 0): ?>
        <div style="background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 25px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 2px solid var(--theme-bg);">
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Order ID</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Garment & Fabric</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Quantity</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Total Price</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Due Date</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Status</th>
                <th style="padding: 15px; text-align: center; color: var(--theme-bg); font-weight: 600;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $current_orders->fetch_assoc()): 
                // Use confirmed_status if order is confirmed, otherwise use regular status
                $displayStatus = $row['is_confirmed'] > 0 && !empty($row['confirmed_status']) 
                    ? $row['confirmed_status'] 
                    : $row['status'];
                $progress = getProgressPercentage($displayStatus);
                $color = getProgressColor($progress);
              ?>
                <tr style="border-bottom: 1px solid #e0e0e0;">
                  <td style="padding: 15px; color: #333; font-weight: 600;"><?= htmlspecialchars($row['orderID']) ?></td>
                  <td style="padding: 15px; color: #555;">
                    <?= htmlspecialchars($row['garment_type']) ?><br>
                    <small style="color: #999;"><?= htmlspecialchars($row['fabric_type']) ?></small>
                  </td>
                  <td style="padding: 15px; color: #555;"><?= htmlspecialchars($row['total_garments']) ?> pcs</td>
                  <td style="padding: 15px; color: #333; font-weight: 600;">₱<?= number_format($row['total_garments'] * $row['garment_price'], 2) ?></td>
                  <td style="padding: 15px; color: #555;"><?= date('M d, Y', strtotime($row['due_date'])) ?></td>
                  <td style="padding: 15px;">
                    <div style="background-color: <?= $color ?>; color: white; padding: 8px 12px; border-radius: 20px; text-align: center; font-size: 12px; font-weight: 600;">
                      <?= htmlspecialchars($displayStatus) ?>
                    </div>
                    <div style="width: 100%; height: 6px; background: rgba(0,0,0,0.1); border-radius: 3px; margin-top: 8px; overflow: hidden;">
                      <div style="width: <?= $progress ?>%; height: 100%; background-color: <?= $color ?>; transition: width 0.3s ease;"></div>
                    </div>
                  </td>
                  <td style="padding: 15px; text-align: center;">
                    <?php if ($row['is_confirmed'] == 0): ?>
                      <button class="cancel-btn" onclick="confirmCancel(<?= $row['orderID'] ?>)" style="margin: 0;">Cancel</button>
                    <?php else: ?>
                      <span style="color: #999; font-size: 13px; font-style: italic;">Cannot Cancel<br><small>(Order Confirmed)</small></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="no-orders">No current request found.</div>
      <?php endif; ?>
    </div>

    <!-- Completed Orders Section -->
    <div class="section-header">
      <h2>COMPLETED SERVICES</h2>
      <button id="toggleCompletedOrders" class="dropdown-btn">▼</button>
    </div>
    
    <div id="completedOrdersContainer" class="orders-container">
      <?php if ($completed_orders->num_rows > 0): ?>
        <div style="background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 25px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 2px solid var(--theme-bg);">
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Order ID</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Garment & Fabric</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Quantity</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Total Price</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Due Date</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Status</th>
                <th style="padding: 15px; text-align: left; color: var(--theme-bg); font-weight: 600;">Completed</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $completed_orders->fetch_assoc()): 
                $progress = getProgressPercentage($row['status']);
                $color = getProgressColor($progress);
              ?>
                <tr style="border-bottom: 1px solid #e0e0e0;">
                  <td style="padding: 15px; color: #333; font-weight: 600;"><?= htmlspecialchars($row['orderID']) ?></td>
                  <td style="padding: 15px; color: #555;">
                    <?= htmlspecialchars($row['garment_type']) ?><br>
                    <small style="color: #999;"><?= htmlspecialchars($row['fabric_type']) ?></small>
                  </td>
                  <td style="padding: 15px; color: #555;"><?= htmlspecialchars($row['total_garments']) ?> pcs</td>
                  <td style="padding: 15px; color: #333; font-weight: 600;">₱<?= number_format($row['total_garments'] * $row['garment_price'], 2) ?></td>
                  <td style="padding: 15px; color: #555;"><?= date('M d, Y', strtotime($row['due_date'])) ?></td>
                  <td style="padding: 15px;">
                    <div style="background-color: <?= $color ?>; color: white; padding: 8px 12px; border-radius: 20px; text-align: center; font-size: 12px; font-weight: 600;">
                      <?= htmlspecialchars($row['status']) ?>
                    </div>
                  </td>
                  <td style="padding: 15px; color: #999; font-size: 13px;"><?= date('M d, Y', strtotime($row['updated_at'])) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="no-orders">No completed request found.</div>
      <?php endif; ?>
    </div>

    <!-- Cancelled Orders Section -->
    <div class="section-header">
      <h2>CANCELLED SERVICES</h2>
      <button id="toggleCancelledOrders" class="dropdown-btn">▼</button>
    </div>
    
    <div id="cancelledOrdersContainer" class="orders-container">
      <?php if ($cancelled_orders->num_rows > 0): ?>
        <div style="background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 25px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);">
          <table style="width: 100%; border-collapse: collapse;">
            <thead>
              <tr style="border-bottom: 2px solid #dc3545;">
                <th style="padding: 15px; text-align: left; color: #dc3545; font-weight: 600;">Order ID</th>
                <th style="padding: 15px; text-align: left; color: #dc3545; font-weight: 600;">Garment & Fabric</th>
                <th style="padding: 15px; text-align: left; color: #dc3545; font-weight: 600;">Quantity</th>
                <th style="padding: 15px; text-align: left; color: #dc3545; font-weight: 600;">Total Price</th>
                <th style="padding: 15px; text-align: left; color: #dc3545; font-weight: 600;">Due Date</th>
                <th style="padding: 15px; text-align: left; color: #dc3545; font-weight: 600;">Status</th>
                <th style="padding: 15px; text-align: left; color: #dc3545; font-weight: 600;">Cancelled</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $cancelled_orders->fetch_assoc()): ?>
                <tr style="border-bottom: 1px solid #e0e0e0;">
                  <td style="padding: 15px; color: #333; font-weight: 600;"><?= htmlspecialchars($row['orderID']) ?></td>
                  <td style="padding: 15px; color: #555;">
                    <?= htmlspecialchars($row['garment_type']) ?><br>
                    <small style="color: #999;"><?= htmlspecialchars($row['fabric_type']) ?></small>
                  </td>
                  <td style="padding: 15px; color: #555;"><?= htmlspecialchars($row['total_garments']) ?> pcs</td>
                  <td style="padding: 15px; color: #333; font-weight: 600;">₱<?= number_format($row['total_garments'] * $row['garment_price'], 2) ?></td>
                  <td style="padding: 15px; color: #555;"><?= date('M d, Y', strtotime($row['due_date'])) ?></td>
                  <td style="padding: 15px;">
                    <div style="background-color: #6c757d; color: white; padding: 8px 12px; border-radius: 20px; text-align: center; font-size: 12px; font-weight: 600;">
                      Cancelled
                    </div>
                  </td>
                  <td style="padding: 15px; color: #999; font-size: 13px;"><?= date('M d, Y', strtotime($row['updated_at'])) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="no-orders">No cancelled orders found.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Cancel Confirmation Modal -->
  <div id="cancelModal" class="modal">
    <div class="modal-content">
      <div class="modal-icon">⚠️</div>
      <h3>Cancel Service Request</h3>
      <p>Are you sure you want to cancel this service request?</p>
      <p style="font-size: 14px; color: #999; margin-top: -15px;">This action cannot be undone.</p>
      <div class="modal-buttons">
        <button class="btn-yes" onclick="cancelOrder()">Yes, Cancel Order</button>
        <button class="btn-no" onclick="closeCancelModal()">No, Keep Order</button>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="modal">
    <div class="modal-content">
      <div class="modal-icon success-icon">✅</div>
      <h3>Order Cancelled</h3>
      <p>Your service request has been cancelled successfully.</p>
      <div class="modal-buttons">
        <button class="btn-success" onclick="window.location.reload()">OK</button>
      </div>
    </div>
  </div>

  <!-- Error Modal -->
  <div id="errorModal" class="modal">
    <div class="modal-content">
      <div class="modal-icon error-icon">❌</div>
      <h3>Cancellation Failed</h3>
      <p id="errorMessage">Unable to cancel the order. Please try again.</p>
      <div class="modal-buttons">
        <button class="btn-no" onclick="closeErrorModal()">Close</button>
      </div>
    </div>
  </div>

  <script>
    let orderToCancel = null;

    // Toggle Current Orders
    document.getElementById('toggleCurrentOrders').addEventListener('click', function() {
      const container = document.getElementById('currentOrdersContainer');
      container.classList.toggle('hidden');
      this.classList.toggle('rotate');
    });

    // Toggle Completed Orders
    document.getElementById('toggleCompletedOrders').addEventListener('click', function() {
      const container = document.getElementById('completedOrdersContainer');
      container.classList.toggle('hidden');
      this.classList.toggle('rotate');
    });

    // Toggle Cancelled Orders
    document.getElementById('toggleCancelledOrders').addEventListener('click', function() {
      const container = document.getElementById('cancelledOrdersContainer');
      container.classList.toggle('hidden');
      this.classList.toggle('rotate');
    });

    function confirmCancel(orderId) {
      orderToCancel = orderId;
      document.getElementById('cancelModal').style.display = 'flex';
    }

    function closeCancelModal() {
      document.getElementById('cancelModal').style.display = 'none';
      orderToCancel = null;
    }

    function cancelOrder() {
      if (!orderToCancel) return;

      fetch('cancel_order.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'order_id=' + orderToCancel
      })
      .then(response => response.json())
      .then(data => {
        closeCancelModal();
        if (data.success) {
          document.getElementById('successModal').style.display = 'flex';
        } else {
          document.getElementById('errorMessage').textContent = data.message || 'Unable to cancel the order. Please try again.';
          document.getElementById('errorModal').style.display = 'flex';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        closeCancelModal();
        document.getElementById('errorMessage').textContent = 'An error occurred while cancelling the order.';
        document.getElementById('errorModal').style.display = 'flex';
      });
    }

    function closeErrorModal() {
      document.getElementById('errorModal').style.display = 'none';
    }

    // Auto-refresh every 10 seconds to check for status updates
    setInterval(function() {
      // Silently reload to get latest status from confirmed_order table
      fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
          // Parse the response and update only the status cells
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          const newRows = doc.querySelectorAll('#currentOrdersContainer tbody tr');
          const currentRows = document.querySelectorAll('#currentOrdersContainer tbody tr');
          
          // Update each row's status if it changed
          newRows.forEach((newRow, index) => {
            if (currentRows[index]) {
              const newStatusCell = newRow.querySelector('td:nth-child(6)');
              const currentStatusCell = currentRows[index].querySelector('td:nth-child(6)');
              if (newStatusCell && currentStatusCell) {
                const newStatus = newStatusCell.textContent.trim();
                const currentStatus = currentStatusCell.textContent.trim();
                if (newStatus !== currentStatus) {
                  // Status changed, update the cell with animation
                  currentStatusCell.style.transition = 'all 0.5s ease';
                  currentStatusCell.style.opacity = '0';
                  setTimeout(() => {
                    currentStatusCell.innerHTML = newStatusCell.innerHTML;
                    currentStatusCell.style.opacity = '1';
                  }, 250);
                }
              }
            }
          });
        })
        .catch(err => console.log('Status check failed:', err));
    }, 10000); // Check every 10 seconds
  </script>
</body>
</html>