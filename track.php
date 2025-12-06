<?php
include 'connection.php';
include 'session_customer.php'; 
include 'business_function.php';

$business = getBusinessInfo($conn);

// Now you can access values like:
$business_name = $business['business_name'];
$logo_path = $business['logo_path'];
$about_us = $business['about_us'];
$email = $business['email'];
$contact = $business['contact'];
$machine_message = $business['machine_availability_message'];


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

// Get the most recent confirmed order from confirmed_order table
$stmt = $conn->prepare("SELECT orderID as id, garment_type, fabric_type, order_status as status, due_date, created_at 
                        FROM confirmed_order 
                        WHERE customer = ? AND order_status NOT IN ('Complete', 'Cancelled')
                        ORDER BY created_at DESC 
                        LIMIT 1");
$stmt->bind_param("s", $customer_name);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  $noOrders = true;
  $order = null;
} else {
  $noOrders = false;
  $order = $result->fetch_assoc();
  
  // Fetch order pack data if status is Order Pack
  $images = ['goods' => null, 'defects' => null, 'packed' => null];
  $goods_count = 0;
  $defects_count = 0;
  
  if ($order['status'] === 'Order Pack') {
    // Get goods and defects count from confirmed_order table
    $packStmt = $conn->prepare("SELECT goods_count, defects_count, goods_photo, defects_photo FROM confirmed_order WHERE orderID = ?");
    $packStmt->bind_param("i", $order['id']);
    $packStmt->execute();
    $packResult = $packStmt->get_result();
    
    if ($packResult->num_rows > 0) {
      $packData = $packResult->fetch_assoc();
      $goods_count = $packData['goods_count'] ?? 0;
      $defects_count = $packData['defects_count'] ?? 0;
      $images['goods'] = $packData['goods_photo'] ?? null;
      $images['defects'] = $packData['defects_photo'] ?? null;
    }
  }
}

// Status progression - matching superadmin_orderStatus.php exactly
$statuses = [
  'Order Confirmation',
  'Order Processing',
  'Quality Check',
  'Order Pack',
  'Out for Delivery',
  'Complete'
];

$currentStatusIndex = $order ? array_search($order['status'], $statuses) : -1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Tracking | TailorMade</title>

<?php include('theme_loader.php'); ?>

<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
    color: #333;
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

  .track-container {
    width: 90%;
    max-width: 1200px;
    min-height: 400px;
    background: rgba(255, 255, 255, 0.95);
    margin: 60px auto;
    padding: 50px;
    border-radius: 30px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    color: #333;
  }

  .track-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 3px solid var(--theme-bg);
    padding-bottom: 20px;
    margin-bottom: 50px;
  }

  .track-header h2 {
    font-size: 28px;
    color: var(--theme-bg);
    font-weight: 700;
    letter-spacing: 1px;
  }

  .track-header p {
    font-size: 15px;
    color: #666;
    font-weight: 500;
  }

  .steps {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 50px;
    position: relative;
  }

  .step {
    text-align: center;
    position: relative;
    z-index: 2;
    flex: 1;
  }

  .circle {
    width: 55px;
    height: 55px;
    background: linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%);
    border: 4px solid #ddd;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0 auto 15px;
    cursor: pointer;
    color: #999;
    font-weight: bold;
    font-size: 18px;
    transition: all 0.3s ease;
  }

  .circle.checked {
    background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
    border-color: var(--theme-bg);
    color: var(--theme-font);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    transform: scale(1.1);
  }

  .step p {
    font-size: 13px;
    color: #666;
    margin-top: 8px;
    font-weight: 500;
  }

  .no-orders-message {
    text-align: center;
    padding: 80px 40px;
    color: #666;
  }

  .no-orders-message h3 {
    font-size: 28px;
    margin-bottom: 20px;
    color: var(--theme-bg);
    font-weight: 700;
  }

  .no-orders-message p {
    font-size: 16px;
    margin: 10px 0;
  }

  .no-orders-message a {
    color: var(--theme-bg);
    text-decoration: none;
    font-weight: 600;
    border-bottom: 2px solid var(--theme-bg);
    padding-bottom: 2px;
    transition: all 0.3s ease;
  }

  .no-orders-message a:hover {
    color: var(--theme-button);
    border-bottom-color: var(--theme-button);
  }

  .modal {
    display: none;
    position: fixed;
    z-index: 20;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(11, 14, 39, 0.8);
    justify-content: center;
    align-items: center;
  }

  .modal-content {
    background-color: rgba(65, 66, 80, 0.95);
    margin: auto;
    padding: 30px;
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    text-align: center;
    color: white;
    box-shadow: 0 0 20px rgba(0,0,0,0.5);
  }

  .modal-content h3 {
    background-color: #0b0e27;
    padding: 12px;
    border-radius: 8px;
    font-size: 18px;
    margin-bottom: 20px;
    letter-spacing: 1px;
  }

  .message-box {
    background-color: #10143a;
    border-radius: 10px;
    padding: 15px;
    margin: 15px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .message-box span {
    font-size: 16px;
    font-weight: bold;
  }

  .view-btn {
    background-color: #0b0e27;
    color: white;
    border: 1px solid white;
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    transition: 0.3s;
  }

  .view-btn:hover {
    background-color: #28325a;
  }

  .close-btn {
    background: #ff4c4c;
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 20px;
    font-weight: bold;
  }

  .close-btn:hover {
    background: #e03a3a;
  }

  .photo-popup {
    display: none;
    position: fixed;
    z-index: 30;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.85);
    justify-content: center;
    align-items: center;
  }

  .photo-popup-content {
    margin: auto;
    width: 90%;
    max-width: 600px;
    background-color: #1a1f4d;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
  }

  .photo-popup-content img {
    width: 100%;
    max-height: 500px;
    object-fit: contain;
    border-radius: 8px;
  }

  .photo-popup-content button {
    margin-top: 15px;
    padding: 10px 20px;
    background-color: #ff4c4c;
    border: none;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
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

    .track-container {
      width: 95%;
      margin: 30px auto;
      padding: 30px 20px;
      border-radius: 20px;
    }

    .track-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 10px;
      padding-bottom: 15px;
      margin-bottom: 30px;
    }

    .track-header h2 {
      font-size: 22px;
    }

    .track-header p {
      font-size: 13px;
    }

    .steps {
      flex-direction: column;
      gap: 20px;
      margin-top: 30px;
    }

    .step {
      display: flex;
      align-items: center;
      width: 100%;
      text-align: left;
      gap: 15px;
    }

    .circle {
      width: 50px;
      height: 50px;
      margin: 0;
      flex-shrink: 0;
    }

    .step p {
      font-size: 14px;
      margin: 0;
      text-align: left;
    }

    .no-orders-message {
      padding: 50px 20px;
    }

    .no-orders-message h3 {
      font-size: 24px;
    }

    .no-orders-message p {
      font-size: 15px;
    }

    .modal-content {
      width: 95%;
      padding: 25px 20px;
    }

    .modal-content h3 {
      font-size: 16px;
      padding: 10px;
    }

    .message-box {
      flex-direction: column;
      gap: 10px;
      padding: 12px;
    }

    .message-box span {
      font-size: 15px;
    }

    .view-btn {
      width: 100%;
      padding: 10px;
    }

    .photo-popup-content {
      width: 95%;
      padding: 15px;
    }

    .photo-popup-content img {
      max-height: 400px;
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

    .track-container {
      width: 95%;
      margin: 20px auto;
      padding: 25px 15px;
      border-radius: 15px;
    }

    .track-header h2 {
      font-size: 20px;
    }

    .track-header p {
      font-size: 12px;
    }

    .steps {
      gap: 15px;
      margin-top: 25px;
    }

    .circle {
      width: 45px;
      height: 45px;
      font-size: 16px;
    }

    .step p {
      font-size: 13px;
    }

    .no-orders-message {
      padding: 40px 15px;
    }

    .no-orders-message h3 {
      font-size: 22px;
      margin-bottom: 15px;
    }

    .no-orders-message p {
      font-size: 14px;
    }

    .modal-content {
      padding: 20px 15px;
      border-radius: 12px;
    }

    .modal-content h3 {
      font-size: 15px;
      padding: 8px;
    }

    .message-box {
      padding: 10px;
    }

    .message-box span {
      font-size: 14px;
    }

    .view-btn {
      font-size: 12px;
      padding: 8px;
    }

    .close-btn {
      padding: 8px 16px;
      font-size: 14px;
    }

    .photo-popup-content {
      padding: 12px;
    }

    .photo-popup-content img {
      max-height: 350px;
    }

    .photo-popup-content button {
      padding: 8px 16px;
      font-size: 14px;
    }
  }
</style>
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <img src="<?php echo $business ['logo_path']; ?>" alt="TailorMade Logo">
      <span class="logo-text">TailorMade</span>
    </div>

    <ul>
      <li><a href="user_home.php">Home</a></li>
      <li><a href="orders.php">Order</a></li>
      <li><a href="track.php" class="active">Track</a></li>
      <li><a href="customer_edit_info.php">Account</a></li>
    </ul>
  </div>

  <div class="track-container">
    <?php if ($noOrders): ?>
      <div class="no-orders-message">
        <h3>No Active Orders</h3>
        <p>You don't have any active orders to track.</p>
        <p><a href="order_form.php">Place a new order</a></p>
      </div>
    <?php else: ?>
      <div class="track-header">
        <h2>ORDER STATUS</h2>
        <p><strong>ORDER ID:</strong> <?= $order['id'] ?>&nbsp;&nbsp;&nbsp;&nbsp;<strong>DUE:</strong> <?= date('M d, Y', strtotime($order['due_date'])) ?></p>
      </div>

      <div class="steps">
        <?php foreach ($statuses as $index => $status): ?>
          <div class="step">
            <div class="circle <?= $index <= $currentStatusIndex ? 'checked' : '' ?>" 
                 <?= ($status === 'Order Pack' && $index === $currentStatusIndex) ? 'onclick="openPackModal()"' : '' ?>>
              ✓
            </div>
            <p><?= strtoupper($status) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$noOrders && $order['status'] === 'Order Pack'): ?>
  <!-- Order Pack Modal -->
  <div id="packModal" class="modal">
    <div class="modal-content">
      <h3>ORDER PACK DETAILS</h3>

      <div class="message-box">
        <span><?= $goods_count ?> GOODS</span>
        <?php if ($images['goods']): ?>
          <button class="view-btn" onclick="openPhoto('<?= htmlspecialchars($images['goods']) ?>')">VIEW PHOTO</button>
        <?php else: ?>
          <span style="font-size:12px; color:#aaa;">No photo</span>
        <?php endif; ?>
      </div>

      <div class="message-box">
        <span><?= $defects_count ?> DEFECTS</span>
        <?php if ($images['defects']): ?>
          <button class="view-btn" onclick="openPhoto('<?= htmlspecialchars($images['defects']) ?>')">VIEW PHOTO</button>
        <?php else: ?>
          <span style="font-size:12px; color:#aaa;">No photo</span>
        <?php endif; ?>
      </div>

      <?php if ($images['packed']): ?>
      <div class="message-box">
        <span>PACKED</span>
        <button class="view-btn" onclick="openPhoto('<?= htmlspecialchars($images['packed']) ?>')">VIEW PHOTO</button>
      </div>
      <?php endif; ?>

      <button class="close-btn" onclick="closePackModal()">Close</button>
    </div>
  </div>

  <!-- Photo Popup -->
  <div id="photoPopup" class="photo-popup">
    <div class="photo-popup-content">
      <img id="photoImage" src="" alt="Order Photo">
      <button onclick="closePhoto()">Close</button>
    </div>
  </div>
  <?php endif; ?>

  <script>
    function openPackModal() {
      document.getElementById('packModal').style.display = 'flex';
    }

    function closePackModal() {
      document.getElementById('packModal').style.display = 'none';
    }

    function openPhoto(src) {
      document.getElementById('photoImage').src = src;
      document.getElementById('photoPopup').style.display = 'flex';
    }

    function closePhoto() {
      document.getElementById('photoPopup').style.display = 'none';
    }

    window.addEventListener('click', function(e) {
      if (e.target.id === 'packModal') {
        closePackModal();
      }
      if (e.target.id === 'photoPopup') {
        closePhoto();
      }
    });

    // Auto-refresh every 10 seconds to check for status updates
    <?php if (!$noOrders): ?>
    let currentStatus = '<?= $order['status'] ?>';
    
    setInterval(function() {
      fetch('track_status_check.php')
        .then(response => response.json())
        .then(data => {
          if (data.status && data.status !== currentStatus) {
            // Status changed, reload page to show new status
            location.reload();
          }
        })
        .catch(err => console.log('Status check failed:', err));
    }, 10000); // Check every 10 seconds
    <?php endif; ?>
  </script>
</body>
</html>
