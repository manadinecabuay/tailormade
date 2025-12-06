<?php
session_start();
include 'connection.php';

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: customer_login.php?status=error&msg=" . urlencode("Please login first"));
    exit();
}

$customer_id = $_SESSION['customer_id'];

// Check if customer has an active order (confirmed or being processed)
$hasActiveOrder = false;
$activeOrder = null;

// Check 1: If there's a "Confirmed" status in orders table
$confirmedStatusCheck = $conn->query("
    SELECT orderID, status, garment_type 
    FROM orders 
    WHERE customer_id = $customer_id 
    AND status = 'Confirmed'
    LIMIT 1
");

if ($confirmedStatusCheck && $confirmedStatusCheck->num_rows > 0) {
    $hasActiveOrder = true;
    $activeOrder = $confirmedStatusCheck->fetch_assoc();
}

// Check 2: If there's an "Order Processing" status in confirmed_order table
if (!$hasActiveOrder) {
    $processingOrderCheck = $conn->query("
        SELECT co.orderID, co.order_status as status, co.garment_type 
        FROM confirmed_order co
        JOIN orders o ON co.orderID = o.orderID
        WHERE o.customer_id = $customer_id 
        AND co.order_status != 'Complete'
        LIMIT 1
    ");
    
    if ($processingOrderCheck && $processingOrderCheck->num_rows > 0) {
        $hasActiveOrder = true;
        $activeOrder = $processingOrderCheck->fetch_assoc();
    }
}

if ($hasActiveOrder) {
    $_SESSION['error_message'] = "Cannot request — an existing garment order is still in process. Order #{$activeOrder['orderID']} must be completed or cancelled before placing a new service request.";
    header("Location: user_home.php");
    exit();
}

// Fetch customer info
$stmt = $conn->prepare("
    SELECT full_name, address, contact 
    FROM customers 
    WHERE customerID = ?
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

// Fetch machine availability message, machine count, and logo from business_info
$machineMsg = "We have machines available for your order."; // Default message
$machineCount = 0; // Default count
$logo_path = 'tailor.jpg'; // default logo
$machineQuery = $conn->query("SELECT machine_availability_message, machine_count, logo_path FROM business_info LIMIT 1");
if ($machineQuery && $machineQuery->num_rows > 0) {
    $machineData = $machineQuery->fetch_assoc();
    $machineMsg = $machineData['machine_availability_message'] ?? $machineMsg;
    $machineCount = $machineData['machine_count'] ?? 0;
    if (!empty($machineData['logo_path']) && file_exists($machineData['logo_path'])) {
        $logo_path = $machineData['logo_path'];
    }
}

// Fetch theme settings
$theme_settings = $conn->query("SELECT * FROM theme_settings WHERE id = 1")->fetch_assoc();

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TailorMade - Service Request</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    :root {
      --theme-bg: <?= $theme_settings['bg_color'] ?? '#667eea' ?>;
      --theme-font: <?= $theme_settings['font_color'] ?? '#FFFFFF' ?>;
      --theme-button: <?= $theme_settings['button_color'] ?? '#764ba2' ?>;
      --theme-container: <?= $theme_settings['container_color'] ?? '#d9d9d9' ?>;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Poppins', 'Segoe UI', Arial, sans-serif;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: white;
      min-height: 100vh;
      overflow-x: hidden;
      overflow-y: auto;
    }

    .navbar {
      padding: 15px 25px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .navbar .logo {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .navbar .logo img { 
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

    .form-container {
      max-width: 700px;
      margin: 30px auto;
      background: rgba(255, 255, 255, 0.95);
      padding: 35px;
      border-radius: 20px;
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
      color: #333;
    }

    .form-container h2 {
      text-align: center;
      margin-bottom: 30px;
      letter-spacing: 1.5px;
      color: var(--theme-bg);
      font-size: 28px;
      font-weight: 700;
    }

    .input-group {
      margin-bottom: 16px;
    }

    .input-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      font-size: 13px;
    }

    .input-group input, .input-group textarea {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 14px;
      color: #333;
      transition: all 0.3s ease;
      font-family: inherit;
    }

    .input-group input:focus, .input-group textarea:focus {
      outline: none;
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .input-group textarea {
      resize: vertical;
      min-height: 100px;
    }

    .form-row {
      display: flex;
      gap: 20px;
    }

    .form-row .input-group {
      flex: 1;
    }

    button {
      width: 100%;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      padding: 14px;
      border: none;
      border-radius: 15px;
      font-weight: 700;
      font-size: 15px;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 15px;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background: white;
      color: #0d1033;
      padding: 30px;
      border-radius: 15px;
      text-align: center;
      width: 90%;
      max-width: 450px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    .modal-content h3 {
      margin-bottom: 15px;
      font-size: 22px;
    }

    .modal-content p {
      margin-bottom: 20px;
      line-height: 1.6;
      color: #333;
    }

    .modal-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
    }

    .modal-buttons button {
      width: auto;
      padding: 10px 30px;
      margin: 0;
    }

    .btn-cancel {
      background: #dc3545;
      color: white;
    }

    .btn-cancel:hover {
      background: #c82333;
    }

    .btn-confirm {
      background: #28a745;
      color: white;
    }

    .btn-confirm:hover {
      background: #218838;
    }

    .error-text {
      color: #ff6b6b;
      font-size: 12px;
      margin-top: 5px;
      display: none;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
      .navbar {
        padding: 15px 20px;
        flex-wrap: wrap;
        gap: 15px;
      }

      .navbar .logo img {
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

      .form-container {
        margin: 20px auto;
        padding: 25px 20px;
        border-radius: 15px;
        max-width: 95%;
      }

      .form-container h2 {
        font-size: 24px;
        margin-bottom: 25px;
      }

      .input-group {
        margin-bottom: 14px;
      }

      .input-group label {
        font-size: 12px;
        margin-bottom: 5px;
      }

      .input-group input,
      .input-group textarea {
        padding: 10px 14px;
        font-size: 13px;
      }

      .form-row {
        flex-direction: column;
        gap: 0;
      }

      button {
        padding: 12px;
        font-size: 14px;
      }

      .modal-content {
        width: 95%;
        padding: 25px 20px;
      }

      .modal-content h3 {
        font-size: 20px;
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

      .navbar .logo img {
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

      .form-container {
        margin: 15px auto;
        padding: 20px 15px;
        border-radius: 12px;
      }

      .form-container h2 {
        font-size: 20px;
        margin-bottom: 20px;
        letter-spacing: 1px;
      }

      .input-group {
        margin-bottom: 12px;
      }

      .input-group label {
        font-size: 11px;
      }

      .input-group input,
      .input-group textarea {
        padding: 9px 12px;
        font-size: 12px;
      }

      .input-group textarea {
        min-height: 80px;
      }

      button {
        padding: 11px;
        font-size: 13px;
      }

      .modal-content {
        padding: 20px 15px;
        border-radius: 12px;
      }

      .modal-content h3 {
        font-size: 18px;
        margin-bottom: 12px;
      }

      .modal-content p {
        font-size: 13px;
        margin-bottom: 15px;
      }

      .modal-buttons button {
        padding: 10px 20px;
        font-size: 13px;
      }

      .error-text {
        font-size: 11px;
      }
    }
  </style>
</head>
<body>
  <div class="navbar">
    <div class="logo">
      <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Business Logo">
      <span class="logo-text">TailorMade</span>
    </div>
    <ul>
      <li><a href="user_home.php">Home</a></li>
      <li><a href="orders.php">Order</a></li>
      <li><a href="track.php">Track</a></li>
      <li><a href="customer_edit_info.php">Account</a></li>
    </ul>
  </div>

  <div class="form-container">
    <h2>SERVICE REQUEST FORM</h2>
    <form id="serviceForm" action="order_form_process.php">
      <div class="input-group">
        <label for="full_name">Full Name *</label>
        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($customer['full_name']); ?>" required>
      </div>

      <div class="input-group">
        <label for="address">Address *</label>
        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($customer['address']); ?>" required>
      </div>

      <div class="input-group">
        <label for="contact">Contact Number *</label>
        <input type="text" id="contact" name="contact" value="<?php echo htmlspecialchars($customer['contact']); ?>" required>
      </div>

      <div class="input-group">
        <label for="garment_type">Garment Type *</label>
        <input type="text" id="garment_type" name="garment_type" placeholder="e.g., T-shirt, Pants, Dress" required>
      </div>

      <div class="input-group">
        <label for="fabric_type">Fabric Type *</label>
        <input type="text" id="fabric_type" name="fabric_type" placeholder="e.g., Cotton, Polyester, Silk" required>
      </div>

      <div class="form-row">
        <div class="input-group">
          <label for="quantity">Quantity * (500-3000)</label>
          <input type="number" id="quantity" name="total_garments" min="500" max="3000" required>
          <span class="error-text" id="quantityError">Quantity must be between 500 and 3000</span>
        </div>

        <div class="input-group">
          <label for="price_per_piece">Price per Piece *</label>
          <input type="number" id="price_per_piece" name="garment_price" step="0.01" min="1" required>
        </div>
      </div>

      <div class="input-group">
        <label for="due_date">Due Date *</label>
        <input type="date" id="due_date" name="due_date" required>
        <span class="error-text" id="dueDateError">Due date does not match quantity requirements</span>
      </div>

      <div class="input-group">
        <label for="message">Additional Message (Optional)</label>
        <textarea id="message" name="message" placeholder="Any special instructions or requirements"></textarea>
      </div>

      <button type="submit">SUBMIT REQUEST</button>
    </form>
  </div>

  <!-- Due Date Warning Modal -->
  <div id="dueDateModal" class="modal">
    <div class="modal-content">
      <h3>⚠️ Due Date Mismatch</h3>
      <p id="dueDateMessage"></p>
      <button onclick="closeDueDateModal()">OK, I'll Adjust</button>
    </div>
  </div>

  <!-- Machine Availability Modal -->
  <div id="machineModal" class="modal">
    <div class="modal-content">
      <h3>🏭 Machine Availability</h3>
      <p><?php echo htmlspecialchars($machineMsg); ?></p>
      <?php if ($machineCount > 0): ?>
        <p style="margin-top: 15px; font-weight: 600; color: #28a745; font-size: 16px;">
          <i class="fa-solid fa-gears"></i> Available Machines: <?php echo htmlspecialchars($machineCount); ?>
        </p>
      <?php endif; ?>
      <div class="modal-buttons">
        <button class="btn-cancel" onclick="cancelService()">Cancel</button>
        <button class="btn-confirm" onclick="confirmService()">Avail Service</button>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div id="successModal" class="modal">
    <div class="modal-content">
      <h3>✅ Success!</h3>
      <p>Your service request has been submitted successfully!</p>
      <button onclick="window.location.href='orders.php'">View Orders</button>
    </div>
  </div>

  <script>
    const form = document.getElementById('serviceForm');
    const quantityInput = document.getElementById('quantity');
    const dueDateInput = document.getElementById('due_date');
    let formData = null;

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    dueDateInput.setAttribute('min', today);

    // Quantity validation
    quantityInput.addEventListener('blur', function() {
      const quantity = parseInt(this.value);
      const error = document.getElementById('quantityError');
      
      if (quantity < 500 || quantity > 3000) {
        error.style.display = 'block';
      } else {
        error.style.display = 'none';
      }
    });

    // Calculate recommended due date based on quantity
    quantityInput.addEventListener('change', function() {
      const quantity = parseInt(this.value);
      if (quantity >= 500 && quantity <= 3000) {
        const today = new Date();
        let daysToAdd = 0;
        
        if (quantity >= 500 && quantity <= 1500) {
          daysToAdd = 30;
        } else if (quantity >= 1501 && quantity <= 3000) {
          daysToAdd = 60;
        }
        
        const recommendedDate = new Date(today);
        recommendedDate.setDate(today.getDate() + daysToAdd);
        
        // Set the recommended date
        dueDateInput.value = recommendedDate.toISOString().split('T')[0];
      }
    });

    // Due date validation
    dueDateInput.addEventListener('change', function() {
      const quantity = parseInt(quantityInput.value);
      const selectedDate = new Date(this.value);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      const daysDiff = Math.ceil((selectedDate - today) / (1000 * 60 * 60 * 24));
      
      let requiredDays = 0;
      if (quantity >= 500 && quantity <= 1500) {
        requiredDays = 30;
      } else if (quantity >= 1501 && quantity <= 3000) {
        requiredDays = 60;
      }
      
      if (daysDiff < requiredDays) {
        const message = `For ${quantity} garments, the due date must be at least ${requiredDays} days from today. Please select a date on or after ${new Date(today.getTime() + requiredDays * 24 * 60 * 60 * 1000).toLocaleDateString()}.`;
        document.getElementById('dueDateMessage').textContent = message;
        document.getElementById('dueDateModal').style.display = 'flex';
        document.getElementById('dueDateError').style.display = 'block';
      } else {
        document.getElementById('dueDateError').style.display = 'none';
      }
    });

    // Form submission
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Validate all fields
      const quantity = parseInt(quantityInput.value);
      const selectedDate = new Date(dueDateInput.value);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      const daysDiff = Math.ceil((selectedDate - today) / (1000 * 60 * 60 * 24));
      
      let requiredDays = 0;
      if (quantity >= 500 && quantity <= 1500) {
        requiredDays = 30;
      } else if (quantity >= 1501 && quantity <= 3000) {
        requiredDays = 60;
      }
      
      if (daysDiff < requiredDays) {
        const message = `For ${total_garments} garments, the due date must be at least ${requiredDays} days from today.`;
        document.getElementById('dueDateMessage').textContent = message;
        document.getElementById('dueDateModal').style.display = 'flex';
        return;
      }
      
      // Store form data
      formData = new FormData(form);
      
      // Show machine availability modal
      document.getElementById('machineModal').style.display = 'flex';
    });

    function closeDueDateModal() {
      document.getElementById('dueDateModal').style.display = 'none';
    }

    function cancelService() {
      document.getElementById('machineModal').style.display = 'none';
      formData = null;
    }

    function confirmService() {
      if (!formData) return;
      
      // Submit the form via AJAX
      fetch('submit_service_request.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        document.getElementById('machineModal').style.display = 'none';
        
        if (data.status === 'success') {
          document.getElementById('successModal').style.display = 'flex';
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
      });
    }
  </script>
</body>
</html>
