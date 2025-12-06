<?php include 'session_customer.php'; ?>

<?php
include 'connection.php';
include 'business_function.php';
include('theme_loader.php');

$business = getBusinessInfo($conn);

// Now you can access values like:
$business_name = $business['business_name'];
$logo_path = $business['logo_path'];
$about_us = $business['about_us'];
$email = $business['email'];
$contact = $business['contact'];
$machine_message = $business['machine_availability_message'];

// Check if customer has an active order (confirmed or being processed)
$customer_id = $_SESSION['customerID'] ?? 0;
$hasActiveOrder = false;
$activeOrderInfo = null;

// Check 1: If there's a "Confirmed" status in orders table
$confirmedStatusQuery = $conn->query("
    SELECT o.orderID, o.status, o.garment_type 
    FROM orders o
    WHERE o.customer_id = $customer_id 
    AND o.status = 'Confirmed'
    LIMIT 1
");

if ($confirmedStatusQuery && $confirmedStatusQuery->num_rows > 0) {
    $hasActiveOrder = true;
    $activeOrderInfo = $confirmedStatusQuery->fetch_assoc();
}

// Check 2: If there's an "Order Processing" status in confirmed_order table
if (!$hasActiveOrder) {
    $processingOrderQuery = $conn->query("
        SELECT co.orderID, co.order_status as status, co.garment_type 
        FROM confirmed_order co
        JOIN orders o ON co.orderID = o.orderID
        WHERE o.customer_id = $customer_id 
        AND co.order_status != 'Complete'
        LIMIT 1
    ");
    
    if ($processingOrderQuery && $processingOrderQuery->num_rows > 0) {
        $hasActiveOrder = true;
        $activeOrderInfo = $processingOrderQuery->fetch_assoc();
    }
}

// Fetch notifications from orders table for this customer
$notifications = [];
$notif_query = $conn->query("
    SELECT orderID, status, updated_at, notification_seen 
    FROM orders 
    WHERE customer_id = $customer_id 
    AND status IN ('Confirmed', 'Packed', 'Shipped', 'Out for Delivery', 'Delivered', 'Cancelled')
    ORDER BY updated_at DESC 
    LIMIT 5
");
if ($notif_query) {
    while ($row = $notif_query->fetch_assoc()) {
        $notifications[] = $row;
    }
}
// Count only unseen notifications
$notif_count = 0;
foreach ($notifications as $notif) {
    if ($notif['notification_seen'] == 0) {
        $notif_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TailorMade - Home</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      min-height: 100vh;
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

    .notification-bell {
      position: relative;
      cursor: pointer;
      font-size: 20px;
      color: white;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 15px;
    }

    .notification-bell:hover {
      transform: scale(1.15);
      color: #ffc107;
    }

    .notification-badge {
      position: absolute;
      top: -8px;
      right: -8px;
      background: #ff4444;
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: bold;
      border: 2px solid white;
    }

    .notification-dropdown {
      display: none;
      position: absolute;
      top: 50px;
      right: 20px;
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      width: 350px;
      max-height: 400px;
      overflow-y: auto;
      z-index: 1000;
    }

    .notification-dropdown.show {
      display: block;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .notification-header {
      padding: 15px 20px;
      border-bottom: 2px solid #f0f0f0;
      font-weight: 700;
      color: #333;
      font-size: 16px;
    }

    .notification-item {
      padding: 15px 20px;
      border-bottom: 1px solid #f0f0f0;
      transition: background 0.2s ease;
      cursor: pointer;
    }

    .notification-item:hover {
      background: #f8f9fa;
    }

    .notification-item:last-child {
      border-bottom: none;
    }

    .notification-title {
      font-weight: 600;
      color: #333;
      margin-bottom: 5px;
      font-size: 14px;
    }

    .notification-time {
      font-size: 12px;
      color: #999;
    }

    .notification-status {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
      margin-top: 5px;
    }

    .status-confirmed {
      background: #d4edda;
      color: #155724;
    }

    .status-cancelled {
      background: #f8d7da;
      color: #721c24;
    }

    .no-notifications {
      padding: 30px 20px;
      text-align: center;
      color: #999;
      font-size: 14px;
    }


    .hero {
      height: 75vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: 60px;
      text-align: center;
      padding: 40px 20px;
    }

    .hero h1 {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      padding: 60px 100px;
      border-radius: 30px;
      font-size: 52px;
      font-weight: 700;
      color: white;
      line-height: 1.4;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
      text-transform: lowercase;
      letter-spacing: 1px;
    }

    .btn {
      background: white;
      color: #667eea;
      padding: 18px 70px;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 700;
      letter-spacing: 2px;
      font-size: 18px;
      display: inline-block;
      transition: all 0.3s ease;
      text-transform: uppercase;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .btn:hover {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    }
    /* --- Enhanced Modal Styles --- */
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    animation: fadeIn 0.3s ease;
  }

  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  @keyframes slideIn {
    from { 
      transform: translate(-50%, -60%);
      opacity: 0;
    }
    to { 
      transform: translate(-50%, -50%);
      opacity: 1;
    }
  }

  .modal-content {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    color: #333;
    padding: 40px 35px;
    border-radius: 20px;
    width: 420px;
    max-width: 90%;
    text-align: center;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    border-top: 5px solid #ffc107;
    animation: slideIn 0.3s ease;
  }

  .modal-icon {
    font-size: 60px;
    margin-bottom: 20px;
    animation: bounce 0.5s ease;
  }

  @keyframes bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
  }

  .modal-content p {
    font-size: 16px;
    line-height: 1.6;
    margin-bottom: 25px;
    color: #555;
    font-weight: 500;
  }

  .modal-content button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 35px;
    border-radius: 25px;
    cursor: pointer;
    font-weight: 600;
    font-size: 15px;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
  }

  .modal-content button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
  }

  .modal-content button:active {
    transform: translateY(0);
  }

  /* Mobile Menu Toggle */
  .mobile-menu-toggle {
    display: none;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 10px 15px;
    border-radius: 10px;
    cursor: pointer;
    font-size: 20px;
    transition: all 0.3s ease;
  }

  .mobile-menu-toggle:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.05);
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

    .notification-bell {
      font-size: 18px;
      padding: 8px 12px;
    }

    .notification-badge {
      width: 18px;
      height: 18px;
      font-size: 10px;
    }

    .notification-dropdown {
      width: 90%;
      right: 5%;
      max-height: 350px;
    }

    .hero {
      height: auto;
      min-height: 70vh;
      gap: 40px;
      padding: 40px 15px;
    }

    .hero h1 {
      padding: 40px 30px;
      font-size: 32px;
      border-radius: 20px;
    }

    .btn {
      padding: 15px 50px;
      font-size: 16px;
      letter-spacing: 1.5px;
    }

    .modal-content {
      width: 90%;
      padding: 30px 25px;
    }

    .modal-icon {
      font-size: 50px;
    }

    .modal-content p {
      font-size: 15px;
    }

    .modal-content button {
      padding: 10px 30px;
      font-size: 14px;
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

    .notification-bell {
      font-size: 16px;
      padding: 6px 10px;
    }

    .notification-dropdown {
      width: 95%;
      right: 2.5%;
      top: 60px;
    }

    .notification-header {
      padding: 12px 15px;
      font-size: 14px;
    }

    .notification-item {
      padding: 12px 15px;
    }

    .notification-title {
      font-size: 13px;
    }

    .notification-time {
      font-size: 11px;
    }

    .hero {
      gap: 30px;
      padding: 30px 10px;
    }

    .hero h1 {
      padding: 30px 20px;
      font-size: 24px;
      border-radius: 15px;
      letter-spacing: 0.5px;
    }

    .btn {
      padding: 12px 40px;
      font-size: 14px;
      letter-spacing: 1px;
    }

    .modal-content {
      width: 95%;
      padding: 25px 20px;
      border-radius: 15px;
    }

    .modal-icon {
      font-size: 45px;
      margin-bottom: 15px;
    }

    .modal-content p {
      font-size: 14px;
      margin-bottom: 20px;
    }

    .modal-content button {
      padding: 10px 25px;
      font-size: 13px;
    }
  }
  </style>
</head>
<body>
  <?php if (isset($_GET['msg'])): ?>
<script>
  window.onload = function() {
    showModal("<?php echo htmlspecialchars($_GET['msg']); ?>");
  };
</script>
<?php endif; ?>

<!-- Enhanced Custom Modal -->
<div id="messageModal" class="modal">
  <div class="modal-content">
    <div class="modal-icon">⏳</div>
    <p id="modalMessage"></p>
    <button id="closeModal">OK</button>
  </div>
</div>

<div class="navbar">
  <div class="logo">
    <img src="<?php echo $business['logo_path']; ?>" alt="TailorMade Logo">
    <span class="logo-text">TailorMade</span>
  </div>
  <ul id="menu">
    <li>
      <div class="notification-bell" onclick="toggleNotifications()">
        <i class="fa-solid fa-bell"></i>
        <?php if ($notif_count > 0): ?>
          <span class="notification-badge"><?php echo $notif_count; ?></span>
        <?php endif; ?>
      </div>
    </li>
    <li><a href="user_home.php" class="active">Home</a></li>
    <li><a href="orders.php">Order</a></li>
    <li><a href="track.php">Track</a></li>
    <li><a href="customer_edit_info.php">Account</a></li>
  </ul>
</div>

<!-- Notification Dropdown -->
<div class="notification-dropdown" id="notificationDropdown">
  <div class="notification-header">
    Notifications
  </div>
  <?php if (count($notifications) > 0): ?>
    <?php foreach ($notifications as $notif): ?>
      <div class="notification-item">
        <div class="notification-title">
          Your Order #<?php echo $notif['orderID']; ?> has been <?php echo $notif['status']; ?>.
        </div>
        <div class="notification-time">
          <?php echo date('M d, Y h:i A', strtotime($notif['updated_at'])); ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="no-notifications">
      <i class="fa-solid fa-bell-slash" style="font-size: 40px; color: #ddd; margin-bottom: 10px;"></i>
      <p>No notifications yet</p>
    </div>
  <?php endif; ?>
</div>

  <section class="hero">
    <h1>tailored to fit, designed to impress</h1>
    <?php if ($hasActiveOrder): ?>
      <button onclick="showActiveOrderModal()" class="btn">AVAIL SERVICE</button>
    <?php else: ?>
      <a href="order_form.php" class="btn">AVAIL SERVICE</a>
    <?php endif; ?>
  </section>

  <!-- Active Order Restriction Modal -->
  <div id="activeOrderModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 450px; padding: 35px 30px; text-align: center; border-radius: 20px; background: white; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
      <span class="close" onclick="closeActiveOrderModal()" style="position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; color: #999; cursor: pointer; transition: all 0.3s ease;">&times;</span>
      
      <!-- Icon Section -->
      <div style="margin-bottom: 25px;">
        <div style="width: 70px; height: 70px; margin: 0 auto 15px; background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4); animation: pulse 2s infinite;">
          <i class="fa-solid fa-exclamation-triangle" style="font-size: 35px; color: white;"></i>
        </div>
        <h3 style="margin: 0; color: #333; font-size: 22px; font-weight: 700; letter-spacing: 0.5px;">Cannot Request Service</h3>
      </div>
      
      <!-- Message Section -->
      <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%); padding: 20px; border-radius: 12px; margin-bottom: 25px; border-left: 4px solid #ffc107;">
        <p style="color: #856404; margin: 0; font-size: 14px; line-height: 1.6; font-weight: 600;">
          <i class="fa-solid fa-ban" style="margin-right: 6px;"></i>
          Cannot request — an existing garment order is still in process.
        </p>
        <p style="color: #856404; margin: 12px 0 0 0; font-size: 13px; line-height: 1.5;">
          Your order <strong style="color: #667eea;">#<?= $activeOrderInfo['orderID'] ?? '' ?></strong> is currently being processed. Please wait until it's completed or cancel it before placing a new service request.
        </p>
      </div>
      
      <!-- Compact Button Section -->
      <div style="display: flex; gap: 10px; justify-content: center;">
        <a href="orders.php" style="text-decoration: none; flex: 1;">
          <button class="modal-btn-primary" style="width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); display: flex; align-items: center; justify-content: center; gap: 8px;">
            <i class="fa-solid fa-list"></i>
            <span>View Orders</span>
          </button>
        </a>
        <button onclick="closeActiveOrderModal()" class="modal-btn-secondary" style="flex: 1; background: #f8f9fa; color: #6c757d; border: 2px solid #dee2e6; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px;">
          <i class="fa-solid fa-times"></i>
          <span>Close</span>
        </button>
      </div>
    </div>
  </div>

  <style>
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.05); }
    }
    
    .modal-btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }
    
    .modal-btn-secondary:hover {
      background: #e9ecef;
      border-color: #adb5bd;
      transform: translateY(-2px);
    }
    
    .modal-btn-primary:active,
    .modal-btn-secondary:active {
      transform: translateY(0);
    }
    
    .close:hover {
      color: #333;
      transform: rotate(90deg);
    }
    
    @media (max-width: 480px) {
      #activeOrderModal .modal-content {
        max-width: 95%;
        padding: 25px 20px;
      }
      
      #activeOrderModal h3 {
        font-size: 20px;
      }
      
      #activeOrderModal .modal-btn-primary,
      #activeOrderModal .modal-btn-secondary {
        font-size: 13px;
        padding: 10px 16px;
      }
    }
  </style>
<script>
// Active Order Modal Functions
function showActiveOrderModal() {
  document.getElementById('activeOrderModal').style.display = 'flex';
}

function closeActiveOrderModal() {
  document.getElementById('activeOrderModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('activeOrderModal');
  if (event.target === modal) {
    closeActiveOrderModal();
  }
}

function toggleNotifications() {
  const dropdown = document.getElementById('notificationDropdown');
  const isOpening = !dropdown.classList.contains('show');
  dropdown.classList.toggle('show');
  
  // Mark notifications as seen when opening the dropdown
  if (isOpening) {
    markNotificationsAsSeen();
  }
}

function markNotificationsAsSeen() {
  fetch('mark_notifications_seen.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    }
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Remove the badge
      const badge = document.querySelector('.notification-badge');
      if (badge) {
        badge.style.display = 'none';
      }
    }
  })
  .catch(error => {
    console.error('Error marking notifications as seen:', error);
  });
}

// Close notification dropdown when clicking outside
document.addEventListener('click', function(event) {
  const dropdown = document.getElementById('notificationDropdown');
  const bell = document.querySelector('.notification-bell');
  
  if (dropdown && bell && !bell.contains(event.target) && !dropdown.contains(event.target)) {
    dropdown.classList.remove('show');
  }
});

function showModal(message) {
  const modal = document.getElementById('messageModal');
  const msg = document.getElementById('modalMessage');
  msg.textContent = message;
  modal.style.display = 'block';

  document.getElementById('closeModal').onclick = function() {
    modal.style.display = 'none';
    clearMsgFromURL();
  };

  window.onclick = function(event) {
    if (event.target == modal) {
      modal.style.display = 'none';
      
    }
  };
}

// ✅ Function to remove message parameters from URL after showing
// This prevents the message from reappearing when user refreshes the page
function clearMsgFromURL() {
  if (window.history.replaceState) {
    const url = new URL(window.location);
    // Remove all message-related parameters
    url.searchParams.delete('msg');
    url.searchParams.delete('status');
    // Update the URL without refreshing the page
    window.history.replaceState({}, document.title, url.pathname);
  }
}

// ✅ Also clear URL parameters automatically after page loads
// This ensures refresh won't show the message again
<?php if (isset($_GET['msg']) || isset($_GET['status'])): ?>
window.addEventListener('load', function() {
  // Clear URL parameters after a short delay to ensure modal is shown first
  setTimeout(clearMsgFromURL, 100);
});
<?php endif; ?>
</script>



</body>
</html