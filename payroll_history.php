<?php
error_reporting(E_ALL);
include('connection.php');
require 'superadmin_session.php';

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
$logo_path = 'tailor.jpg';
$logo_sql = "SELECT logo_path FROM business_info LIMIT 1";
$logo_result = $conn->query($logo_sql);
if ($logo_result && $logo_result->num_rows > 0) {
    $logo_row = $logo_result->fetch_assoc();
    if (!empty($logo_row['logo_path']) && file_exists($logo_row['logo_path'])) {
        $logo_path = $logo_row['logo_path'];
    }
}

// Get filter parameters
$selected_date = $_GET['date'] ?? '';
$selected_employee = $_GET['employee'] ?? '';

// Fetch all unique payroll dates for filter
$dates_query = "SELECT DISTINCT payroll_date FROM payroll ORDER BY payroll_date DESC";
$dates_result = $conn->query($dates_query);
$payroll_dates = [];
if ($dates_result) {
    while ($row = $dates_result->fetch_assoc()) {
        $payroll_dates[] = $row['payroll_date'];
    }
}

// Fetch all employees for filter
$employees_query = "SELECT DISTINCT employee_id, first_name, last_name FROM payroll ORDER BY first_name, last_name";
$employees_result = $conn->query($employees_query);
$employees_list = [];
if ($employees_result) {
    while ($row = $employees_result->fetch_assoc()) {
        $employees_list[] = $row;
    }
}

// Build query with filters
$where_clauses = [];
$params = [];
$types = '';

if (!empty($selected_date)) {
    $where_clauses[] = "payroll_date = ?";
    $params[] = $selected_date;
    $types .= 's';
}

if (!empty($selected_employee)) {
    $where_clauses[] = "employee_id = ?";
    $params[] = $selected_employee;
    $types .= 'i';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Fetch payroll history
$history_sql = "
    SELECT 
        id,
        employee_id,
        first_name,
        last_name,
        overall_input,
        overall_output,
        overall_defects,
        price_per_piece,
        total_salary,
        payroll_date,
        status,
        created_at,
        paid_at
    FROM payroll
    $where_sql
    ORDER BY payroll_date DESC, created_at DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($history_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $history_result = $stmt->get_result();
} else {
    $history_result = $conn->query($history_sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payroll History - Owner</title>

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
  background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
  color: #fff;
  min-height: 100vh;
  overflow-y: auto;
  overflow-x: hidden;
}

.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  width: 260px;
  background: rgba(255, 255, 255, 0.98);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  z-index: 1000;
  overflow-y: auto;
  transition: transform 0.3s ease;
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

/* Dropdown Menu Styles */
.sidebar-dropdown {
  position: relative;
}

.sidebar-dropdown > a {
  cursor: pointer;
  position: relative;
}

.sidebar-dropdown > a::after {
  content: '\f078';
  font-family: 'Font Awesome 6 Free';
  font-weight: 900;
  position: absolute;
  right: 20px;
  transition: transform 0.3s ease;
  font-size: 12px;
}

.sidebar-dropdown.open > a::after {
  transform: rotate(180deg);
}

.dropdown-content {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease;
  background: rgba(0, 0, 0, 0.03);
}

.sidebar-dropdown.open .dropdown-content {
  max-height: 200px;
}

.dropdown-content a {
  padding: 14px 30px 14px 60px;
  font-size: 14px;
  border-left: 4px solid transparent;
}

.dropdown-content a:hover {
  background: var(--theme-sidebar-hover);
  border-left-color: var(--theme-bg);
  color: var(--theme-sidebar-hover-font);
}

.dropdown-content a.active {
  background: var(--theme-sidebar-hover);
  border-left-color: var(--theme-bg);
  color: var(--theme-sidebar-hover-font);
  font-weight: 600;
}

/* Touch-friendly dropdown for mobile */
@media (hover: none) and (pointer: coarse) {
  .sidebar-dropdown > a {
    -webkit-tap-highlight-color: rgba(0, 0, 0, 0.1);
    tap-highlight-color: rgba(0, 0, 0, 0.1);
  }

  .dropdown-content a {
    min-height: 44px;
    display: flex;
    align-items: center;
  }
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

.main {
  flex: 1;
  padding: 40px;
  margin-left: 260px;
  width: calc(100% - 260px);
  transition: margin-left 0.3s ease;
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
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 40px;
  background: rgba(255, 255, 255, 0.95);
  padding: 25px 35px;
  border-radius: 20px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
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

.filter-section {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 25px;
  background: rgba(255, 255, 255, 0.95);
  padding: 20px 25px;
  border-radius: 15px;
  box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
  flex-wrap: wrap;
}

.filter-section label {
  font-weight: 600;
  color: var(--theme-bg);
  font-size: 14px;
  text-transform: uppercase;
}

.filter-section select {
  padding: 10px 14px;
  border-radius: 10px;
  border: 2px solid #e0e0e0;
  background: #fff;
  color: #333;
  font-weight: 500;
  font-size: 14px;
  transition: all 0.3s ease;
  cursor: pointer;
}

.filter-section select:focus {
  outline: none;
  border-color: var(--theme-bg);
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-btn {
  background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
  color: var(--theme-font);
  border: none;
  border-radius: 10px;
  padding: 10px 20px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 14px;
  text-transform: uppercase;
}

.filter-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.table-container {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  padding: 30px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  overflow-x: auto;
  transition: all 0.3s ease;
}

.table-container:hover {
  box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
}

.table {
  width: 100%;
  border-collapse: collapse;
  min-width: 900px;
}

.table th {
  color: var(--theme-bg);
  font-weight: 600;
  text-transform: uppercase;
  font-size: 13px;
  border-bottom: 2px solid rgba(102, 126, 234, 0.2);
  padding: 14px 12px;
  letter-spacing: 0.5px;
  text-align: center;
}

.table td {
  text-align: center;
  padding: 14px 12px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
  color: #333;
  font-size: 14px;
}

.table tbody tr:hover {
  background: rgba(102, 126, 234, 0.05);
}

.table tbody tr:last-child td {
  border-bottom: none;
}

.status-badge {
  padding: 6px 16px;
  border-radius: 20px;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
}

.status-pending {
  background: #fff3cd;
  color: #856404;
}

.status-paid {
  background: #d4edda;
  color: #155724;
}

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
    margin-left: 0;
    padding: 80px 15px 20px 15px;
  }

  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    width: 280px;
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

  .filter-section {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
    padding: 15px;
  }

  .filter-section select,
  .filter-btn {
    width: 100%;
  }

  .table-container {
    padding: 15px;
    border-radius: 15px;
  }

  .table {
    font-size: 12px;
    min-width: 700px;
  }

  .table th,
  .table td {
    padding: 10px 6px;
    font-size: 11px;
  }

  /* Dropdown mobile adjustments */
  .sidebar-dropdown > a {
    padding: 16px 25px;
    font-size: 14px;
  }

  .dropdown-content a {
    padding: 12px 25px 12px 50px;
    font-size: 13px;
  }

  .sidebar-dropdown > a::after {
    right: 15px;
    font-size: 11px;
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

  .header-left h1 {
    font-size: 20px;
  }

  .header-left p {
    font-size: 13px;
  }

  .header-right img {
    width: 40px;
    height: 40px;
  }

  .header-right span {
    font-size: 13px;
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

  /* Dropdown mobile adjustments for small screens */
  .sidebar-dropdown > a {
    padding: 14px 20px;
    font-size: 13px;
  }

  .dropdown-content a {
    padding: 11px 20px 11px 45px;
    font-size: 12px;
  }

  .sidebar-dropdown > a::after {
    right: 12px;
    font-size: 10px;
  }

  .dropdown-content a i {
    font-size: 14px;
  }

  .filter-section {
    padding: 12px;
    gap: 10px;
  }

  .filter-section label {
    font-size: 12px;
  }

  .filter-section select {
    padding: 9px 12px;
    font-size: 13px;
  }

  .filter-btn {
    padding: 9px 16px;
    font-size: 13px;
  }

  .table-container {
    padding: 12px;
  }

  .table {
    font-size: 11px;
    min-width: 650px;
  }

  .table th,
  .table td {
    padding: 9px 5px;
    font-size: 10px;
  }

  .status-badge {
    padding: 5px 12px;
    font-size: 10px;
  }
}
</style>
</head>
<body>

<button class="mobile-menu-toggle" onclick="toggleMobileSidebar()">
  <i class="fa-solid fa-bars"></i>
</button>

<div class="overlay" onclick="toggleMobileSidebar()"></div>

<div class="sidebar">
  <div>
    <div class="sidebar-logo">
      <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Business Logo">
    </div>
    <a href="superadmin_dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href="superadmin_employees.php"><i class="fa-solid fa-users"></i> Employees</a>
    <div class="sidebar-dropdown open">
      <a href="javascript:void(0)" onclick="toggleDropdown(event)"><i class="fa-solid fa-money-bill"></i> Payroll</a>
      <div class="dropdown-content">
        <a href="superadmin_payroll.php"><i class="fa-solid fa-calculator"></i> Payroll</a>
        <a href="payroll_history.php" class="active"><i class="fa-solid fa-history"></i> History</a>
      </div>
    </div>
    <a href="superadmin_order.php"><i class="fa-solid fa-box"></i> Orders</a>
    <a href="superadmin_orderStatus.php"><i class="fa-solid fa-chart-bar"></i> Order Status</a>
    <a href="customize.php"><i class="fa-solid fa-gear"></i> Customize</a>
  </div>
  <div class="bottom">
    <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</div>

<div class="main">
  <div class="header">
    <div class="header-left">
      <h1><i class="fa-solid fa-history"></i> Payroll History</h1>
      <p>View all saved payroll records sorted by date</p>
    </div>
    <div class="header-right">
      <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profile">
      <span><?php echo htmlspecialchars($owner_name); ?></span>
    </div>
  </div>

  <form method="GET" class="filter-section">
    <label>Filter by Date:</label>
    <select name="date">
      <option value="">All Dates</option>
      <?php foreach ($payroll_dates as $date): ?>
        <option value="<?php echo htmlspecialchars($date); ?>" <?php echo ($selected_date === $date) ? 'selected' : ''; ?>>
          <?php echo date('M d, Y', strtotime($date)); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Filter by Employee:</label>
    <select name="employee">
      <option value="">All Employees</option>
      <?php foreach ($employees_list as $emp): ?>
        <option value="<?php echo $emp['employee_id']; ?>" <?php echo ($selected_employee == $emp['employee_id']) ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <button type="submit" class="filter-btn">
      <i class="fa-solid fa-filter"></i> Apply Filter
    </button>
    
    <a href="payroll_history.php" class="filter-btn" style="text-decoration: none; display: inline-block;">
      <i class="fa-solid fa-times"></i> Clear
    </a>
  </form>

  <div class="table-container">
    <table class="table">
      <thead>
        <tr>
          <th>Date Saved</th>
          <th>Employee ID</th>
          <th>Name</th>
          <th>Input</th>
          <th>Output</th>
          <th>Defects</th>
          <th>Price/Piece</th>
          <th>Total Salary</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($history_result && $history_result->num_rows > 0): ?>
          <?php while ($row = $history_result->fetch_assoc()): ?>
            <tr>
              <td><?php echo date('M d, Y', strtotime($row['payroll_date'])); ?></td>
              <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
              <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
              <td><?php echo number_format($row['overall_input']); ?></td>
              <td><?php echo number_format($row['overall_output']); ?></td>
              <td><?php echo number_format($row['overall_defects']); ?></td>
              <td>₱<?php echo number_format($row['price_per_piece'], 2); ?></td>
              <td>₱<?php echo number_format($row['total_salary'], 2); ?></td>
              <td>
                <span class="status-badge status-<?php echo $row['status']; ?>">
                  <?php echo ucfirst($row['status']); ?>
                </span>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="9" style="text-align: center; padding: 40px; color: #666;">
              <i class="fa-solid fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.5;"></i>
              No payroll history found
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Dropdown toggle function
function toggleDropdown(event) {
  event.preventDefault();
  const dropdown = event.target.closest('.sidebar-dropdown');
  dropdown.classList.toggle('open');
}

function toggleMobileSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.overlay');
  sidebar.classList.toggle('mobile-open');
  overlay.classList.toggle('active');
}
</script>

</body>
</html>
