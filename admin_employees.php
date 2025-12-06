<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('connection.php');
require 'admin_session.php';

// Fetch supervisor info
$supervisor_id = $_SESSION['supervisor_id'] ?? null;
$supervisor_name = "Supervisor";

if ($supervisor_id) {
    $sql = "SELECT first_name, last_name FROM supervisors WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $supervisor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $supervisor_name = $row['first_name'] . ' ' . $row['last_name'];
    }
}

// Fetch owner's logo
$logo_path = 'tailor.jpg'; // default logo
$logo_sql = "SELECT logo_path FROM business_info LIMIT 1";
$logo_result = $conn->query($logo_sql);
if ($logo_result && $logo_result->num_rows > 0) {
    $logo_row = $logo_result->fetch_assoc();
    if (!empty($logo_row['logo_path']) && file_exists($logo_row['logo_path'])) {
        $logo_path = $logo_row['logo_path'];
    }
}

// Handle AJAX requests for fetching employees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'fetch_employees') {
        $statusFilter = $_POST['status_filter'] ?? 'all';
        $searchTerm = $_POST['search_term'] ?? '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $perPage = 5; // 5 employees per page
        $offset = ($page - 1) * $perPage;
        
        // Count total matching employees
        $countSql = "SELECT COUNT(*) as total FROM employees WHERE 1=1";
        $params = [];
        $types = '';
        
        // Apply status filter
        if ($statusFilter !== 'all') {
            $countSql .= " AND status = ?";
            $params[] = $statusFilter;
            $types .= 's';
        }
        
        // Apply search filter
        if (!empty($searchTerm)) {
            $countSql .= " AND (id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?)";
            $searchPattern = "%{$searchTerm}%";
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $types .= 'ssss';
        }
        
        $countStmt = $conn->prepare($countSql);
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalEmployees = $countResult->fetch_assoc()['total'];
        $totalPages = ceil($totalEmployees / $perPage);
        
        // Fetch employees for current page
        $sql = "SELECT id, first_name, last_name, contact, status FROM employees WHERE 1=1";
        $params2 = [];
        $types2 = '';
        
        // Apply status filter
        if ($statusFilter !== 'all') {
            $sql .= " AND status = ?";
            $params2[] = $statusFilter;
            $types2 .= 's';
        }
        
        // Apply search filter
        if (!empty($searchTerm)) {
            $sql .= " AND (id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?)";
            $searchPattern = "%{$searchTerm}%";
            $params2[] = $searchPattern;
            $params2[] = $searchPattern;
            $params2[] = $searchPattern;
            $params2[] = $searchPattern;
            $types2 .= 'ssss';
        }
        
        $sql .= " ORDER BY first_name ASC, last_name ASC LIMIT ? OFFSET ?";
        $params2[] = $perPage;
        $params2[] = $offset;
        $types2 .= 'ii';
        
        $stmt = $conn->prepare($sql);
        if (!empty($params2)) {
            $stmt->bind_param($types2, ...$params2);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $employees = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'employees' => $employees,
            'total' => $totalEmployees,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage
        ]);
        exit;
    }
}

// Initial page load - fetch first 5 employees
$employees = [];
$perPage = 5;
$sql = "SELECT id, first_name, last_name, contact, status FROM employees ORDER BY first_name ASC, last_name ASC LIMIT ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $perPage);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    $employees = $res->fetch_all(MYSQLI_ASSOC);
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM employees";
$countResult = $conn->query($countSql);
$totalEmployees = $countResult->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employees - Supervisor</title>

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
  z-index: 1000;
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
  font-weight: 600;
}

.sidebar a i {
  font-size: 18px;
  width: 24px;
  text-align: center;
}

.sidebar .bottom {
  border-top: 1px solid rgba(0, 0, 0, 0.1);
  padding: 10px 0;
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

.main {
  flex: 1;
  margin-left: 260px;
  padding: 40px;
  overflow-y: auto;
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



.header-right span {
  font-weight: 600;
  color: #333;
  font-size: 15px;
}

.filter-section {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 25px;
  background: rgba(255, 255, 255, 0.95);
  padding: 15px 20px;
  border-radius: 15px;
  box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.search-wrapper {
  position: relative;
  flex: 1;
  max-width: 700px;
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

.status-dropdown {
  background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
  color: var(--theme-font);
  border: none;
  border-radius: 10px;
  padding: 12px 18px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-dropdown:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.status-dropdown option {
  background: #fff;
  color: #333;
  font-weight: 600;
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

.pagination-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.pagination-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.pagination-container {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  margin-top: 25px;
  background: rgba(255, 255, 255, 0.95);
  padding: 20px;
  border-radius: 15px;
  box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
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
  min-width: 700px;
}

.table th {
  color: var(--theme-bg);
  font-weight: 600;
  text-transform: uppercase;
  font-size: 13px;
  border-bottom: 2px solid rgba(102, 126, 234, 0.2);
  padding: 14px 12px;
  letter-spacing: 0.5px;
  text-align: left;
}

.table td {
  text-align: left;
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
  display: inline-block;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.status-active {
  background: linear-gradient(135deg, #d1f4e0 0%, #a8e6cf 100%);
  color: #0f5132;
}

.status-leave {
  background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
  color: #856404;
}

.status-banned {
  background: linear-gradient(135deg, #f8d7da 0%, #f1aeb5 100%);
  color: #842029;
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

  .filter-section h3 {
    font-size: 18px !important;
  }

  .search-wrapper {
    max-width: 100%;
    order: 3;
  }

  .status-dropdown {
    order: 1;
    width: 100%;
  }

  .clear-btn {
    order: 4;
    width: 100%;
  }

  .filter-section > div:last-child {
    order: 2;
    justify-content: center !important;
  }

  .table-container {
    padding: 15px;
    border-radius: 15px;
  }

  .table {
    font-size: 13px;
    min-width: 600px;
  }

  .table th,
  .table td {
    padding: 10px 8px;
    font-size: 12px;
  }

  .table th {
    font-size: 11px;
  }

  .status-badge {
    padding: 4px 10px;
    font-size: 10px;
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

  .header-right i {
    font-size: 18px;
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

  .sidebar a i {
    font-size: 16px;
  }

  .sidebar-logo {
    padding: 20px;
  }

  .sidebar-logo img {
    width: 70px;
    height: 70px;
  }

  .filter-section {
    padding: 12px;
    gap: 10px;
  }

  .filter-section h3 {
    font-size: 16px !important;
  }

  .search-bar,
  .status-dropdown,
  .clear-btn {
    padding: 10px 14px;
    font-size: 13px;
  }

  .table-container {
    padding: 10px;
    border-radius: 12px;
  }

  .table {
    min-width: 550px;
  }

  .table th,
  .table td {
    padding: 8px 6px;
    font-size: 11px;
  }

  .table th {
    font-size: 10px;
  }

  .status-badge {
    padding: 3px 8px;
    font-size: 9px;
  }

  #totalCount {
    padding: 4px 12px;
    font-size: 14px;
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
    <a href="admin_dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href="admin_employees.php" class="active"><i class="fa-solid fa-users"></i> Employees</a>
    <a href="admin_qualityCheck.php"><i class="fa-solid fa-check"></i> Quality Check</a>
   
  </div>
  <div class="bottom">
    <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</div>

<div class="main">
  <div class="header">
    <div class="header-left">
      <h1><i class="fa-solid fa-users"></i>Employee Status Overview  </h1>
      <p>This page lets you view all employees, including those who are active, on leave, or banned.</p>
    </div>
   
  </div>

  <div class="filter-section">
    <select id="statusFilter" class="status-dropdown">
      <option value="all">Status</option>
      <option value="active">Active</option>
      <option value="leave">Leave</option>
      <option value="banned">Banned</option>
    </select>
    
    <div class="search-wrapper">
      <input type="text" id="searchInput" class="search-bar" placeholder="Search employee by name or ID...">
    </div>
    <button class="clear-btn" onclick="clearSearch()">Clear</button>
    <div style="display: flex; align-items: center; gap: 8px; white-space: nowrap;">
      <span style="color: var(--theme-bg); font-weight: 600; font-size: 14px; text-transform: uppercase;">Total Employee:</span>
      <span id="totalCount" style="background: var(--theme-bg); color: var(--theme-font); padding: 6px 16px; border-radius: 20px; font-weight: 700; font-size: 16px;"><?php echo $totalEmployees; ?></span>
    </div>
  </div>

  <div class="table-container">
    
    <table class="table" id="employeeTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name of Employees</th>
          <th>Contact</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($employees) > 0): ?>
          <?php foreach ($employees as $emp): ?>
            <?php 
              $full_name = trim($emp['first_name'] . ' ' .  $emp['last_name']);
            ?>
            <tr>
              <td><?php echo htmlspecialchars($emp['id']); ?></td>
              <td><?php echo htmlspecialchars($full_name); ?></td>
              <td><?php echo htmlspecialchars($emp['contact']); ?></td>
              <td>
                <span class="status-badge status-<?php echo strtolower($emp['status']); ?>">
                  <?php echo ucfirst($emp['status']); ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" style="text-align:center; padding:30px; color:#999;">
              No employees found.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination Controls -->
  <div class="pagination-container" style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 25px; background: rgba(255, 255, 255, 0.95); padding: 20px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);">
    <button id="prevPage" class="pagination-btn" style="background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); color: var(--theme-font); border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
      <i class="fa-solid fa-chevron-left"></i> Previous
    </button>
    <span id="pageInfo" style="color: var(--theme-bg); font-weight: 600; font-size: 15px;">Page 1 of 1</span>
    <button id="nextPage" class="pagination-btn" style="background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); color: var(--theme-font); border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
      Next <i class="fa-solid fa-chevron-right"></i>
    </button>
  </div>
</div>

<script>
// Mobile sidebar toggle
function toggleMobileSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.overlay');
  sidebar.classList.toggle('mobile-open');
  overlay.classList.toggle('active');
}

// Search and filter functionality using AJAX
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const employeeTable = document.getElementById('employeeTable');
const totalCount = document.getElementById('totalCount');
const prevPageBtn = document.getElementById('prevPage');
const nextPageBtn = document.getElementById('nextPage');
const pageInfo = document.getElementById('pageInfo');

let currentPage = 1;
let totalPages = 1;

// Function to fetch employees via AJAX
async function fetchEmployees(page = 1) {
  const searchValue = searchInput.value.trim();
  const statusValue = statusFilter.value;
  
  const formData = new FormData();
  formData.append('action', 'fetch_employees');
  formData.append('status_filter', statusValue);
  formData.append('search_term', searchValue);
  formData.append('page', page);
  
  try {
    const response = await fetch('', {
      method: 'POST',
      body: formData
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      updateEmployeeTable(data.employees);
      totalCount.textContent = data.total;
      currentPage = data.currentPage;
      totalPages = data.totalPages;
      updatePaginationControls();
    }
  } catch (error) {
    console.error('Error fetching employees:', error);
  }
}

// Function to update pagination controls
function updatePaginationControls() {
  pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
  prevPageBtn.disabled = currentPage <= 1;
  nextPageBtn.disabled = currentPage >= totalPages;
  
  // Update button styles
  if (prevPageBtn.disabled) {
    prevPageBtn.style.opacity = '0.5';
    prevPageBtn.style.cursor = 'not-allowed';
  } else {
    prevPageBtn.style.opacity = '1';
    prevPageBtn.style.cursor = 'pointer';
  }
  
  if (nextPageBtn.disabled) {
    nextPageBtn.style.opacity = '0.5';
    nextPageBtn.style.cursor = 'not-allowed';
  } else {
    nextPageBtn.style.opacity = '1';
    nextPageBtn.style.cursor = 'pointer';
  }
}

// Function to update the employee table
function updateEmployeeTable(employees) {
  const tbody = employeeTable.querySelector('tbody');
  tbody.innerHTML = '';
  
  if (employees.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" style="text-align:center; padding:30px; color:#999;">
          No employees found.
        </td>
      </tr>
    `;
    return;
  }
  
  employees.forEach(emp => {
    const statusClass = `status-${emp.status.toLowerCase()}`;
    const fullName = `${emp.first_name} ${emp.middle_name || ''} ${emp.last_name}`.replace(/\s+/g, ' ').trim();
    const row = `
      <tr>
        <td>${escapeHtml(emp.id)}</td>
        <td>${escapeHtml(fullName)}</td>
        <td>${escapeHtml(emp.contact)}</td>
        <td>
          <span class="status-badge ${statusClass}">
            ${capitalizeFirst(emp.status)}
          </span>
        </td>
      </tr>
    `;
    tbody.innerHTML += row;
  });
}

// Helper function to escape HTML
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Helper function to capitalize first letter
function capitalizeFirst(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

// Event listeners
searchInput.addEventListener('keyup', () => {
  currentPage = 1; // Reset to first page on search
  fetchEmployees(currentPage);
});

statusFilter.addEventListener('change', () => {
  currentPage = 1; // Reset to first page on filter change
  fetchEmployees(currentPage);
});

prevPageBtn.addEventListener('click', () => {
  if (currentPage > 1) {
    fetchEmployees(currentPage - 1);
  }
});

nextPageBtn.addEventListener('click', () => {
  if (currentPage < totalPages) {
    fetchEmployees(currentPage + 1);
  }
});

function clearSearch() {
  searchInput.value = '';
  statusFilter.value = 'all';
  currentPage = 1;
  fetchEmployees(currentPage);
}

// Initialize pagination on page load
document.addEventListener('DOMContentLoaded', () => {
  const initialTotal = <?php echo $totalEmployees; ?>;
  const perPage = 5;
  totalPages = Math.ceil(initialTotal / perPage);
  updatePaginationControls();
});

// Session check
setInterval(() => {
  fetch('admin_session_check.php')
    .then(res => res.json())
    .then(data => {
      if (!data.active) {
        alert("Your session has expired or you have logged out in another tab.");
        window.location.href = "login_admins.html";
      }
    });
}, 15000);
</script>

</body>
</html>
