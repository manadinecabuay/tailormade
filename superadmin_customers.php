<?php
include 'connection.php';
require 'superadmin_session.php';

if (!isset($_SESSION['owner_id'])) {
    die("Error: No super admin logged in. Please log in again.");
}

$owner_id = intval($_SESSION['owner_id']);

// Fetch Super Admin Info
$sqlOwner = "SELECT first_name, last_name, profile_photo FROM owner WHERE id = $owner_id";
$resOwner = mysqli_query($conn, $sqlOwner);
if (!$resOwner) die("SQL Error (Owner Info): " . mysqli_error($conn));
$owner = mysqli_fetch_assoc($resOwner);

// Fetch owner's logo from business_info
$logo_path = 'tailor.jpg';
$logo_sql = "SELECT logo_path FROM business_info LIMIT 1";
$logo_result = $conn->query($logo_sql);
if ($logo_result && $logo_result->num_rows > 0) {
    $logo_row = $logo_result->fetch_assoc();
    if (!empty($logo_row['logo_path']) && file_exists($logo_row['logo_path'])) {
        $logo_path = $logo_row['logo_path'];
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $customer_id = intval($_POST['customer_id']);
    $new_password = $_POST['new_password'];
    
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE customers SET password = ? WHERE customerID = ?");
        $update_stmt->bind_param("si", $hashed_password, $customer_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Password updated successfully!";
        } else {
            $error_message = "Failed to update password.";
        }
        $update_stmt->close();
    }
}

// Pagination setup
$customers_per_page = 5;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $customers_per_page;

// Count total customers
$count_query = "SELECT COUNT(*) as total FROM customers";
$count_result = mysqli_query($conn, $count_query);
$total_customers = $count_result ? mysqli_fetch_assoc($count_result)['total'] : 0;
$total_pages = ceil($total_customers / $customers_per_page);

// Fetch customers with pagination
$customers_query = "SELECT customerID, full_name, username, email FROM customers ORDER BY full_name ASC LIMIT $customers_per_page OFFSET $offset";
$customers_result = mysqli_query($conn, $customers_query);
if (!$customers_result) die("SQL Error (Customers): " . mysqli_error($conn));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Management - TailorMade</title>
  
  <?php include('theme_loader.php'); ?>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    html {
      scroll-behavior: smooth;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      display: flex;
      min-height: 100vh;
    }

    .sidebar {
      width: 260px;
      background: rgba(255, 255, 255, 0.98);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
      backdrop-filter: blur(10px);
      position: fixed;
      height: 100vh;
      overflow-y: auto;
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
      color: white;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
      margin-top: 10px;
    }

    .logout-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
    }

    .logout-btn i {
      margin-right: 8px;
    }

    .main-content {
      margin-left: 260px;
      flex: 1;
      padding: 40px;
      overflow-y: auto;
      animation: fadeIn 0.4s ease-in-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .header {
      background: rgba(255, 255, 255, 0.95);
      padding: 25px 35px;
      border-radius: 20px;
      margin-bottom: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
      display: flex;
      justify-content: space-between;
      align-items: center;
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

    .controls {
      display: flex;
      gap: 15px;
      align-items: center;
      margin-bottom: 25px;
      flex-wrap: wrap;
      background: rgba(255, 255, 255, 0.95);
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .search-wrapper {
      position: relative;
      flex: 1;
      max-width: 1100px;
    }

    .search-bar {
      padding: 12px 18px;
      border-radius: 10px;
      border: 2px solid #e0e0e0;
      background: #fff;
      color: #333;
      font-weight: 500;
      width: 100%;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .search-bar:focus {
      outline: none;
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .search-bar::placeholder {
      color: #999;
    }

    .clear-btn {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      border: none;
      border-radius: 10px;
      padding: 12px 18px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 14px;
    }

    .clear-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .customers-table {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead tr {
      border-bottom: 2px solid var(--theme-bg);
    }

    th {
      padding: 15px;
      text-align: left;
      color: var(--theme-bg);
      font-weight: 600;
      font-size: 14px;
    }

    tbody tr {
      border-bottom: 1px solid #e0e0e0;
      transition: all 0.3s ease;
    }

    tbody tr:hover {
      background: rgba(102, 126, 234, 0.05);
    }

    td {
      padding: 15px;
      color: #555;
      font-size: 14px;
    }

    .password-cell {
      font-family: monospace;
      color: #999;
      letter-spacing: 2px;
    }

    .edit-btn {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      border: none;
      padding: 8px 20px;
      border-radius: 20px;
      cursor: pointer;
      font-weight: 600;
      font-size: 13px;
      transition: all 0.3s ease;
    }

    .edit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
      width: 90%;
      max-width: 500px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .modal-content h2 {
      color: var(--theme-bg);
      margin-bottom: 25px;
      font-size: 24px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: 600;
      font-size: 14px;
    }

    .form-group input {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group input[readonly] {
      background: #f5f5f5;
      color: #999;
    }

    .modal-buttons {
      display: flex;
      gap: 15px;
      margin-top: 30px;
    }

    .modal-buttons button {
      flex: 1;
      padding: 12px;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 600;
      font-size: 15px;
      transition: all 0.3s ease;
    }

    .btn-save {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
    }

    .btn-save:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
    }

    .btn-cancel {
      background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
      color: white;
    }

    .btn-cancel:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(127, 140, 141, 0.4);
    }

    .alert {
      padding: 15px 20px;
      border-radius: 15px;
      margin-bottom: 20px;
      font-size: 14px;
      font-weight: 500;
    }

    .alert-success {
      background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
      color: #155724;
      border-left: 5px solid #28a745;
    }

    .alert-error {
      background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
      color: #721c24;
      border-left: 5px solid #dc3545;
    }

    /* Pagination Styles */
    #paginationControls button:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      pointer-events: none;
    }

    #paginationControls button i {
      font-size: 12px;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div>
      <div class="sidebar-logo">
        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Business Logo">
      </div>
      <a href="superadmin_dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
      <a href="superadmin_employees.php"><i class="fa-solid fa-users"></i> Employees</a>
      <a href="superadmin_payroll.php"><i class="fa-solid fa-money-bill"></i> Payroll</a>
      <a href="superadmin_order.php"><i class="fa-solid fa-box"></i> Orders</a>
      <a href="superadmin_orderStatus.php"><i class="fa-solid fa-chart-bar"></i> Order Status</a>
      <a href="superadmin_customers.php" class="active"><i class="fa-solid fa-user-group"></i> Customers</a>
      <a href="customize.php"><i class="fa-solid fa-gear"></i> Customize</a>
    </div>
    <div class="bottom">
      <a href="logout.php" class="logout-btn">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
      </a>
    </div>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="header">
      <div class="header-left">
        <h1><i class="fa-solid fa-user-group"></i> Customer Management</h1>
        <p>View and manage customer accounts and modify passwords</p>
      </div>
      <div class="header-right">
        <img src="<?php echo !empty($owner['profile_photo']) ? htmlspecialchars($owner['profile_photo']) : 'profile.jpg'; ?>" alt="Profile">
        <span><?php echo htmlspecialchars($owner['first_name'] . ' ' . $owner['last_name']); ?></span>
      </div>
    </div>

    <?php if (isset($success_message)): ?>
      <div class="alert alert-success">
        ✅ <?php echo htmlspecialchars($success_message); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
      <div class="alert alert-error">
        ❌ <?php echo htmlspecialchars($error_message); ?>
      </div>
    <?php endif; ?>

    <div class="controls">
      <div class="search-wrapper">
        <input type="text" id="searchInput" class="search-bar" placeholder="Search customer by name, username, or email...">
      </div>
      <button class="clear-btn" onclick="clearSearch()">Clear</button>
    </div>

    <div class="customers-table">
      <table id="customersTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Full Name</th>
            <th>Username</th>
            <th>Email</th>
            <th>Password</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($customer = mysqli_fetch_assoc($customers_result)): ?>
            <tr>
              <td><?php echo htmlspecialchars($customer['customerID']); ?></td>
              <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
              <td><?php echo htmlspecialchars($customer['username']); ?></td>
              <td><?php echo htmlspecialchars($customer['email']); ?></td>
              <td class="password-cell">••••••••</td>
              <td>
                <button class="edit-btn" onclick="openEditModal(<?php echo $customer['customerID']; ?>, '<?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($customer['username'], ENT_QUOTES); ?>')">
                  <i class="fa-solid fa-key"></i> Change Password
                </button>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <!-- Pagination Controls -->
      <div id="paginationControls" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 2px solid rgba(0,0,0,0.1);">
        <button id="prevBtn" class="edit-btn" onclick="changePage('prev')" style="padding: 10px 20px;">
          <i class="fa-solid fa-chevron-left"></i> Previous
        </button>
        <span id="pageInfo" style="font-weight: 600; color: var(--theme-bg); font-size: 15px;">
          Page <?= $current_page ?> of <?= max(1, $total_pages) ?>
        </span>
        <button id="nextBtn" class="edit-btn" onclick="changePage('next')" style="padding: 10px 20px;">
          Next <i class="fa-solid fa-chevron-right"></i>
        </button>
      </div>
    </div>
  </div>

  <!-- Edit Password Modal -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <h2><i class="fa-solid fa-key"></i> Change Customer Password</h2>
      <form method="POST" action="">
        <input type="hidden" name="customer_id" id="modal_customer_id">
        
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" id="modal_full_name" readonly>
        </div>

        <div class="form-group">
          <label>Username</label>
          <input type="text" id="modal_username" readonly>
        </div>

        <div class="form-group">
          <label>New Password</label>
          <input type="password" name="new_password" id="modal_new_password" placeholder="Enter new password" required>
        </div>

        <div class="modal-buttons">
          <button type="submit" name="update_password" class="btn-save">
            <i class="fa-solid fa-check"></i> Update Password
          </button>
          <button type="button" class="btn-cancel" onclick="closeEditModal()">
            <i class="fa-solid fa-times"></i> Cancel
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openEditModal(customerId, fullName, username) {
      document.getElementById('modal_customer_id').value = customerId;
      document.getElementById('modal_full_name').value = fullName;
      document.getElementById('modal_username').value = username;
      document.getElementById('modal_new_password').value = '';
      document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
      if (e.target.id === 'editModal') {
        closeEditModal();
      }
    });

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const customersTable = document.getElementById('customersTable');

    searchInput.addEventListener('keyup', function() {
      const searchTerm = this.value.toLowerCase().trim();
      const rows = customersTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

      for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const fullName = row.cells[1].textContent.toLowerCase();
        const username = row.cells[2].textContent.toLowerCase();
        const email = row.cells[3].textContent.toLowerCase();

        if (fullName.includes(searchTerm) || username.includes(searchTerm) || email.includes(searchTerm)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      }
    });

    function clearSearch() {
      searchInput.value = '';
      const rows = customersTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
      for (let i = 0; i < rows.length; i++) {
        rows[i].style.display = '';
      }
    }

    // =====================
    // PAGINATION
    // =====================
    let currentPage = <?= $current_page ?>;
    let totalPages = <?= max(1, $total_pages) ?>;

    function changePage(direction) {
      if (direction === 'prev' && currentPage > 1) {
        currentPage--;
      } else if (direction === 'next' && currentPage < totalPages) {
        currentPage++;
      }
      
      // Navigate to the new page
      window.location.href = `superadmin_customers.php?page=${currentPage}`;
    }

    function updatePaginationControls() {
      document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
      document.getElementById('prevBtn').disabled = currentPage <= 1;
      document.getElementById('nextBtn').disabled = currentPage >= totalPages;
      
      // Update button styles for disabled state
      const prevBtn = document.getElementById('prevBtn');
      const nextBtn = document.getElementById('nextBtn');
      
      if (currentPage <= 1) {
        prevBtn.style.opacity = '0.5';
        prevBtn.style.cursor = 'not-allowed';
      } else {
        prevBtn.style.opacity = '1';
        prevBtn.style.cursor = 'pointer';
      }
      
      if (currentPage >= totalPages) {
        nextBtn.style.opacity = '0.5';
        nextBtn.style.cursor = 'not-allowed';
      } else {
        nextBtn.style.opacity = '1';
        nextBtn.style.cursor = 'pointer';
      }
    }

    // Initialize pagination controls
    updatePaginationControls();
  </script>
</body>
</html>
