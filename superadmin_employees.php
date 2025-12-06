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

// Pagination setup
$employees_per_page = 5;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $employees_per_page;

// Count total employees
$count_sql = "SELECT COUNT(*) as total FROM employees";
$count_res = $conn->query($count_sql);
$total_employees = $count_res ? $count_res->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_employees / $employees_per_page);

// Fetch employees with pagination
$employees = [];
$sql = "SELECT id, first_name, middle_name, last_name, contact, status FROM employees ORDER BY first_name ASC, last_name ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $employees_per_page, $offset);
$stmt->execute();
$res = $stmt->get_result();
if ($res) {
    $employees = $res->fetch_all(MYSQLI_ASSOC);
}

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    // Fetch Employees with filters
    if ($action === 'fetch_employees') {
        header('Content-Type: application/json');
        
        $statusFilter = $_POST['status_filter'] ?? 'all';
        $searchTerm = $_POST['search_term'] ?? '';
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = 5;
        $offset = ($page - 1) * $per_page;
        
        // Build count query
        $count_sql = "SELECT COUNT(*) as total FROM employees WHERE 1=1";
        $sql = "SELECT id, first_name, middle_name, last_name, contact, status FROM employees WHERE 1=1";
        $params = [];
        $types = '';
        
        // Apply status filter
        if ($statusFilter !== 'all') {
            $count_sql .= " AND status = ?";
            $sql .= " AND status = ?";
            $params[] = $statusFilter;
            $types .= 's';
        }
        
        // Apply search filter
        if (!empty($searchTerm)) {
            $search_condition = " AND (id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?)";
            $count_sql .= $search_condition;
            $sql .= $search_condition;
            $searchPattern = "%{$searchTerm}%";
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $types .= 'ssss';
        }
        
        // Get total count
        $count_stmt = $conn->prepare($count_sql);
        if (!empty($params)) {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $total = $count_stmt->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total / $per_page);
        
        // Get paginated results
        $sql .= " ORDER BY first_name ASC, last_name ASC LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $employees = $result->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'employees' => $employees,
            'total' => $total,
            'current_page' => $page,
            'total_pages' => $total_pages
        ]);
        exit;
    }

    // Add Employee
    if ($action === 'add') {
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        
        if ($first_name === '' || $last_name === '' || $contact === '') {
            echo json_encode(['status' => 'error', 'msg' => 'Please fill in all required fields (First Name, Last Name, Contact)']);
            exit;
        }
        
        // Check if employee with same name is banned
        $checkBanned = $conn->prepare("SELECT id FROM employees WHERE first_name = ? AND last_name = ? AND status = 'banned'");
        $checkBanned->bind_param("ss", $first_name, $last_name);
        $checkBanned->execute();
        $bannedResult = $checkBanned->get_result();

        if ($bannedResult->num_rows > 0) {
            echo json_encode(['status' => 'error', 'msg' => 'Cannot add employee. A banned employee with this name already exists.']);
            $checkBanned->close();
            exit;
        }
        $checkBanned->close();
        
        $stmt = $conn->prepare("INSERT INTO employees (first_name, middle_name, last_name, contact, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssss", $first_name, $middle_name, $last_name, $contact);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'id' => $conn->insert_id, 'first_name' => $first_name, 'middle_name' => $middle_name, 'last_name' => $last_name, 'contact' => $contact]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Database insert failed']);
        }
        exit;
    }

    // Edit Employee
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        
        if ($first_name === '' || $last_name === '' || $contact === '') {
            echo json_encode(['status' => 'error', 'msg' => 'Please fill in all required fields (First Name, Last Name, Contact)']);
            exit;
        }
        
        // Check if employee is currently banned
        $checkStatus = $conn->prepare("SELECT status FROM employees WHERE id = ?");
        $checkStatus->bind_param("i", $id);
        $checkStatus->execute();
        $currentStatus = $checkStatus->get_result()->fetch_assoc();
        
        if ($currentStatus && $currentStatus['status'] === 'banned') {
            echo json_encode(['status' => 'error', 'msg' => 'Cannot edit banned employee information']);
            $checkStatus->close();
            exit;
        }
        $checkStatus->close();
        
        // Check if new name conflicts with banned employee
        $checkBanned = $conn->prepare("SELECT id FROM employees WHERE first_name = ? AND last_name = ? AND status = 'banned' AND id != ?");
        $checkBanned->bind_param("ssi", $first_name, $last_name, $id);
        $checkBanned->execute();
        $bannedResult = $checkBanned->get_result();
        
        if ($bannedResult->num_rows > 0) {
            echo json_encode(['status' => 'error', 'msg' => 'Cannot use this name. A banned employee with this name exists.']);
            $checkBanned->close();
            exit;
        }
        $checkBanned->close();
        
        $stmt = $conn->prepare("UPDATE employees SET first_name = ?, middle_name = ?, last_name = ?, contact = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $first_name, $middle_name, $last_name, $contact, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Failed to update employee']);
        }
        exit;
    }

    // Change Employee Status
    if ($action === 'change_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['active', 'leave', 'banned'])) {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid status']);
            exit;
        }if ($action === 'change_status' && ['status'] === 'banned') {
            echo json_encode(['status' => 'error', 'msg' => 'Cannot change status of banned employee']);
            $checkStatus->close();
            exit;
        }
        
        // Check if employee is already banned
        $checkStatus = $conn->prepare("SELECT status FROM employees WHERE id = ?");
        $checkStatus->bind_param("i", $id);
        $checkStatus->execute();
        $currentStatus = $checkStatus->get_result()->fetch_assoc();
        
        if ($currentStatus && $currentStatus['status'] === 'banned') {
            echo json_encode(['status' => 'error', 'msg' => 'Cannot change status of banned employee']);
            $checkStatus->close();
            exit;
        }
        $checkStatus->close();

        
        
        $stmt = $conn->prepare("UPDATE employees SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Failed to update status']);
        }
        exit;
    }

    


    // Search Suggestions
    if ($action === 'search_suggestions') {
        $term = "%" . ($_POST['term'] ?? '') . "%";
        $stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', middle_name, ' ', last_name) as full_name FROM employees WHERE first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ? LIMIT 5");
        $stmt->bind_param("sss", $term, $term, $term);
        $stmt->execute();
        $res = $stmt->get_result();
        $suggestions = [];
        while ($row = $res->fetch_assoc()) {
            $suggestions[] = $row;
        }
        echo json_encode($suggestions);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employees - Owner</title>

<!-- Include Theme Loader for Custom Colors -->
<?php include('theme_loader.php'); ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',sans-serif; }
body { display:flex; background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); min-height:100vh; overflow-x:hidden; }

.sidebar {
  width:260px; background:rgba(255,255,255,0.98); display:flex; flex-direction:column; justify-content:space-between;
  box-shadow:4px 0 20px rgba(0,0,0,0.1); backdrop-filter:blur(10px);
}
.sidebar-logo {
  display:flex; justify-content:center; align-items:center; padding:25px 20px;
  border-bottom:1px solid rgba(0,0,0,0.1);
}
.sidebar-logo img {
  width:80px; height:80px; border-radius:50%; object-fit:cover;
  border:3px solid var(--theme-bg); box-shadow:0 4px 15px rgba(102,126,234,0.3);
}
.sidebar a { 
  color:#333; text-decoration:none; padding:18px 30px; display:flex; align-items:center; gap:15px; 
  font-weight:500; font-size:15px; transition:all 0.3s ease; border-left:4px solid transparent; 
}
.sidebar a:hover { background:var(--theme-sidebar-hover); border-left-color:var(--theme-bg); color:var(--theme-sidebar-hover-font); }
.sidebar a.active { 
  background:var(--theme-sidebar-hover); 
  border-left-color:var(--theme-bg); color:var(--theme-sidebar-hover-font); font-weight:500; 
}
.sidebar a i { font-size:18px; width:24px; text-align:center; }
.sidebar .bottom { border-top:1px solid rgba(0,0,0,0.1); padding:20px; }

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

.main { flex:1; padding:40px; overflow-y:auto; }

.header { 
  display:flex; justify-content:space-between; align-items:center; margin-bottom:40px; 
  background:rgba(255,255,255,0.95); padding:25px 35px; border-radius:20px; 
  box-shadow:0 10px 30px rgba(0,0,0,0.15); 
}
.header h2 { font-size:28px; font-weight:700; color:var(--theme-bg); letter-spacing:0.5px; }
.header-left h1 { font-size:28px; font-weight:700; color:var(--theme-bg); letter-spacing:0.5px; margin-bottom:5px; }
.header-left h1 i { margin-right:10px; }
.header-left p { color:#666; font-size:15px; margin:0; }
.header-right { display:flex; align-items:center; gap:15px; }
.header-right i { font-size:20px; color:var(--theme-bg); cursor:pointer; transition:transform 0.3s ease; }
.header-right i:hover { transform:scale(1.2); }
.header-right img { width:45px; height:45px; border-radius:50%; border:3px solid var(--theme-bg); object-fit:cover; }
.header-right span { font-weight:600; color:#333; font-size:15px; }

.table-container { 
  background:rgba(255,255,255,0.95); border-radius:20px; padding:30px; 
  box-shadow:0 10px 30px rgba(0,0,0,0.15); transition:all 0.3s ease; 
}
.table-container:hover { box-shadow:0 15px 40px rgba(102,126,234,0.3); }
.table { width:100%; border-collapse:collapse; text-align:left; }
.table th, .table td { padding:14px 16px; border-bottom:1px solid rgba(0,0,0,0.1); }
.table th { text-transform:uppercase; letter-spacing:1px; font-size:13px; color:var(--theme-bg); font-weight:600; }
.table td { color:#333; }
.table td:last-child { padding-left:24px; }
.table tr:last-child td { border-bottom:none; }
.table tbody tr:hover { background:rgba(102,126,234,0.05); }

.status-badge {
  display:inline-block; padding:6px 14px; border-radius:8px; font-weight:600; 
  font-size:12px; text-transform:uppercase; letter-spacing:0.5px;
}
.status-active {
  background:linear-gradient(135deg,#d1f4e0 0%,#a8e6cf 100%); color:#0f5132;
}
.status-leave {
  background:linear-gradient(135deg,#fff3cd 0%,#ffe69c 100%); color:#856404;
}
.status-banned {
  background:linear-gradient(135deg,#f8d7da 0%,#f1aeb5 100%); color:#842029;
}

.filter-section { 
  display:flex; align-items:center; gap:20px; margin-bottom:25px; 
  background:rgba(255,255,255,0.95); padding:20px 25px; border-radius:15px; 
  box-shadow:0 5px 20px rgba(0,0,0,0.1); 
}

.search-wrapper { position:relative; flex:1; max-width:750px; }

.search-bar { 
  padding:12px 18px; border-radius:10px; border:2px solid #e0e0e0; 
  background:#fff; color:#333; font-weight:500; width:100%; font-size:14px; 
  transition:all 0.3s ease; 
}
.search-bar:focus { outline:none; border-color:var(--theme-bg); box-shadow:0 0 0 3px rgba(102,126,234,0.1); }
.search-bar::placeholder { color:#999; }
.dropdown { 
  background:linear-gradient(135deg,var(--theme-bg) 0%,var(--theme-button) 100%); color:var(--theme-font); 
  border:none; padding:12px 18px; font-weight:600; border-radius:10px; 
  cursor:pointer; transition:all 0.3s ease; font-size:14px; 
}
.dropdown:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(102,126,234,0.4); }

.clear-btn {
  background:linear-gradient(135deg,var(--theme-bg) 0%,var(--theme-button) 100%); color:var(--theme-font); border:none; 
  border-radius:10px; padding:12px 18px; font-weight:600; cursor:pointer; 
  transition:all 0.3s ease; font-size:14px; 
}
.clear-btn:hover { transform:translateY(-2px); box-shadow:0 5px 15px rgba(102,126,234,0.4); }

.add-btn {
  width:100%; background:linear-gradient(135deg,var(--theme-bg) 0%,var(--theme-button) 100%); color:var(--theme-font); 
  padding:16px; font-weight:700; text-transform:uppercase; border:none; border-radius:15px; 
  margin-top:25px; cursor:pointer; transition:all 0.3s ease; 
  box-shadow:0 5px 20px rgba(102,126,234,0.3); font-size:15px; letter-spacing:1px; 
}
.add-btn:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(102,126,234,0.4); }

.action-btn {
  border:none; padding:8px 16px; font-weight:600; border-radius:8px; 
  cursor:pointer; margin-right:8px; transition:all 0.3s ease; font-size:13px; 
}
.remove-btn { background:#fff; color:var(--theme-bg); border:2px solid var(--theme-bg); }
.remove-btn:hover { background:var(--theme-bg); color:var(--theme-font); }
.edit-btn { background:linear-gradient(135deg,var(--theme-bg) 0%,var(--theme-button) 100%); color:var(--theme-font); }
.edit-btn:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(102,126,234,0.3); }
.dropdown-action { position:relative; display:inline-block; margin-left:15px;}

.dropdown-menu {
  display:none; position:absolute; left:-110px; top:0; background:#fff; color:#333; 
  border-radius:10px; min-width:120px; box-shadow:0 5px 20px rgba(0,0,0,0.15); z-index:50; 
  overflow:hidden; 
}
.dropdown-menu div {
  padding:12px 16px; cursor:pointer; font-weight:500; transition:all 0.2s; 
  border-bottom:1px solid #f0f0f0; 
}
.dropdown-menu div:last-child { border-bottom:none; }
.dropdown-menu div:hover { background:rgba(102,126,234,0.1); color:var(--theme-bg); }

/* Modal style */
.modal { 
  display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
  background:rgba(0,0,0,0.7); justify-content:center; align-items:center; z-index:999; 
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-content { 
  background:#fff; padding:40px; border-radius:20px; width:420px; max-width:90%; 
  color:#333; text-align:center; position:relative; box-shadow:0 20px 60px rgba(0,0,0,0.3); 
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from { transform: translateY(-50px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

.modal-content h3 { 
  color:var(--theme-bg); margin-bottom:20px; font-size:26px; font-weight:700; 
}

.modal-content label {
  display:block; text-align:left; margin-top:10px; margin-bottom:5px;
  font-weight:600; color:var(--theme-bg); font-size:13px; text-transform:uppercase;
  letter-spacing:0.5px;
}

.modal-content input, .modal-content select { 
  width:100%; padding:12px 16px; margin:5px 0 10px 0; border-radius:10px; 
  border:2px solid #e0e0e0; font-size:14px; transition:all 0.3s ease; 
}

.modal-content input:focus, .modal-content select:focus { 
  outline:none; border-color:var(--theme-bg); box-shadow:0 0 0 3px rgba(102,126,234,0.1); 
}

.modal-content button { 
  width:100%; padding:14px; margin-top:15px; border-radius:10px; border:none; 
  cursor:pointer; font-weight:600; font-size:15px; transition:all 0.3s ease; 
  background:linear-gradient(135deg,var(--theme-bg) 0%,var(--theme-button) 100%); color:var(--theme-font); 
  text-transform:uppercase; letter-spacing:0.5px;
}

.modal-content button:hover { 
  transform:translateY(-2px); 
  box-shadow:0 5px 15px rgba(102,126,234,0.4); 
}

.modal-content .close { 
  position:absolute; top:15px; right:20px; background:#999; color:#fff; 
  border-radius:50%; width:32px; height:32px; line-height:30px; text-align:center; 
  cursor:pointer; font-size:20px; transition:all 0.3s ease; border:none;
  font-weight:bold;
}

.modal-content .close:hover { 
  background:#333; transform:scale(1.1); 
}

.suggestion-box { 
  position:absolute; top:100%; left:0; width:100%; background:#fff; color:#333; 
  border-radius:10px; box-shadow:0 5px 20px rgba(0,0,0,0.15); z-index:50; 
  display:none; max-height:200px; overflow-y:auto; margin-top:5px; 
}
.suggestion-item { padding:12px 16px; cursor:pointer; transition:0.2s; border-bottom:1px solid #f0f0f0; }
.suggestion-item:hover { background:rgba(102,126,234,0.1); color:var(--theme-bg); }
.suggestion-item:last-child { border-bottom:none; }

/* Pagination Styles */
#paginationControls button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

#paginationControls button i {
  font-size: 12px;
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
    overflow-x: auto;
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
    <a href="superadmin_dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href= "superadmin_employees.php" class="active"><i class="fa-solid fa-users"></i> Employees</a>
    <a href="superadmin_payroll.php"><i class="fa-solid fa-money-bill"></i> Payroll</a>
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
      <h1><i class="fa-solid fa-users"></i> Employee Management</h1>
      <p>View, add, and manage employee information</p>
    </div>
    <div class="header-right">
      <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profile">
      <span><?php echo htmlspecialchars($owner_name); ?></span>
    </div>
  </div>

  <div class="table-header">
    <div class="filter-section">
      <select id="statusFilter" class="dropdown">
        <option value="all">Status</option>
        <option value="active">Active</option>
        <option value="leave">Leave</option>
        <option value="banned">Banned</option>
      </select>
      <div class="search-wrapper">
        <input type="text" id="searchInput" class="search-bar" placeholder="Search employee...">
        <div id="suggestionBox" class="suggestion-box"></div>
      </div>
      <button class="clear-btn" onclick="clearSearch()">Clear</button>
      <button class="clear-btn" onclick="window.location.href='employee_homepage.php'"><i class="fa-solid fa-home"></i> Employee Home</button>
      <button class="clear-btn" onclick="openAddModal()">Add Employee</button>
    </div>
  </div>

  <div class="table-container">
    <table class="table" id="employeeTable">
      <thead>
        <tr><th>ID</th><th>First Name</th><th>Middle Name</th><th>LastName</th><th>Contact</th><th>Status</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach ($employees as $e): ?>
       
        <tr data-status="<?= htmlspecialchars($e['status']) ?>">
          <td><?= htmlspecialchars($e['id']) ?></td>
          <td><?= htmlspecialchars($e['first_name']) ?></td>
          <td><?= htmlspecialchars($e['middle_name']) ?></td>
          <td><?= htmlspecialchars($e['last_name']) ?></td>
          <td><?= htmlspecialchars($e['contact']) ?></td>
          <td>
            <span class="status-badge status-<?= strtolower($e['status']) ?>">
              <?= ucfirst($e['status']) ?>
            </span>
          </td>
          <td>
            <button class="action-btn edit-btn" onclick="openEditModal(<?= $e['id'] ?>, '<?= htmlspecialchars($e['first_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($e['middle_name'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($e['last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($e['contact'], ENT_QUOTES) ?>', '<?= $e['status'] ?>')">EDIT</button>
            <div class="dropdown-action" style="display:inline-block; position:relative;">
              <button class="action-btn edit-btn" onclick="toggleDropdown(this)">STATUS ▼</button>
              <div class="dropdown-menu">
                <div onclick="changeStatus(<?= $e['id'] ?>,'active')">Active</div>
                <div onclick="changeStatus(<?= $e['id'] ?>,'leave')">Leave</div>
                <div onclick="changeStatus(<?= $e['id'] ?>,'banned')">Banned</div>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    
    <!-- Pagination Controls -->
    <div id="paginationControls" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 2px solid rgba(0,0,0,0.1);">
      <button id="prevBtn" class="action-btn edit-btn" style="padding: 10px 20px;">
        <i class="fa-solid fa-chevron-left"></i> Previous
      </button>
      <span id="pageInfo" style="font-weight: 600; color: var(--theme-bg); font-size: 15px;">
        Page <?= $current_page ?> of <?= max(1, $total_pages) ?>
      </span>
      <button id="nextBtn" class="action-btn edit-btn" style="padding: 10px 20px;">
        Next <i class="fa-solid fa-chevron-right"></i>
      </button>
    </div>
  </div>
</div>


<!-- Add Employee Modal -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close" onclick="closeAddModal()">×</span>
    <h3>Add Employee</h3>
    <label>First Name *</label>
    <input type="text" id="addFirstName" placeholder="Enter first name">
    <label>Middle Name (Optional)</label>
    <input type="text" id="addMiddleName" placeholder="Enter middle name">
    <label>Last Name *</label>
    <input type="text" id="addLastName" placeholder="Enter last name">
    <label>Contact *</label>
    <input type="text" id="addContact" placeholder="Enter contact number">
    <button onclick="addEmployee()">Add</button>
  </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close" onclick="closeEditModal()">×</span>
    <h3>Edit Employee</h3>
    <input type="hidden" id="editId">
    <label>First Name *</label>
    <input type="text" id="editFirstName" placeholder="Enter first name">
    <label>Middle Name (Optional)</label>
    <input type="text" id="editMiddleName" placeholder="Enter middle name">
    <label>Last Name *</label>
    <input type="text" id="editLastName" placeholder="Enter last name">
    <label>Contact *</label>
    <input type="text" id="editContact" placeholder="Enter contact number">
    <button onclick="updateEmployee()">Update</button>
  </div>
</div>

<!-- Confirm Edit Modal -->
<div class="modal" id="editConfirmModal">
  <div class="modal-content">
    <span class="close" onclick="closeEditConfirmModal()">×</span>
    <h3>Confirm Status Change</h3>
    <p id="editConfirmText"></p>
    <button id="confirmEditBtn" class="edit-btn">Yes, Update</button>
    <button onclick="closeEditConfirmModal()" class="remove-btn">Cancel</button>
  </div>
</div>

<!-- Success Modal -->
<div class="modal" id="successModal">
  <div class="modal-content" style="border-top: 5px solid #28a745;">
    <span class="close" onclick="closeSuccessModal()">×</span>
    <div style="font-size: 60px; color: #28a745; margin-bottom: 20px;">✅</div>
    <h3 style="color: #28a745;">Success!</h3>
    <p id="successMessage" style="color: #555; margin: 20px 0;"></p>
    <button onclick="closeSuccessModal()" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">OK</button>
  </div>
</div>

<!-- Error Modal -->
<div class="modal" id="errorModal">
  <div class="modal-content" style="border-top: 5px solid #dc3545;">
    <span class="close" onclick="closeModal('errorModal')">×</span>
    <div style="font-size: 60px; color: #dc3545; margin-bottom: 20px;">❌</div>
    <h3 style="color: #dc3545;">Error!</h3>
    <p id="errorMessage" style="color: #555; margin: 20px 0;"></p>
    <button onclick="closeModal('errorModal')" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">Close</button>
  </div>
</div>

<!-- Warning Modal -->
<div class="modal" id="warningModal">
  <div class="modal-content" style="border-top: 5px solid #ffc107;">
    <span class="close" onclick="closeModal('warningModal')">×</span>
    <div style="font-size: 60px; color: #ffc107; margin-bottom: 20px;">⚠️</div>
    <h3 style="color: #ffc107;">Warning!</h3>
    <p id="warningMessage" style="color: #555; margin: 20px 0;"></p>
    <button onclick="closeModal('warningModal')" style="background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);">OK</button>
  </div>
</div>

<!-- Session Expired Modal -->
<div class="modal" id="sessionModal">
  <div class="modal-content" style="border-top: 5px solid #dc3545;">
    <div style="font-size: 60px; color: #dc3545; margin-bottom: 20px;">⏱️</div>
    <h3 style="color: #dc3545;">Session Expired</h3>
    <p id="sessionMessage" style="color: #555; margin: 20px 0;"></p>
    <button onclick="window.location.href='login_admins.html'">Go to Login</button>
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

// =====================
// MODAL HELPER FUNCTIONS
// =====================
function showModal(modalId) {
  document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
  document.getElementById(modalId).style.display = 'none';
}

function closeSuccessModal() {
  closeModal('successModal');
  location.reload();
}

function showSuccess(message) {
  document.getElementById('successMessage').textContent = message;
  showModal('successModal');
}

function showError(message) {
  document.getElementById('errorMessage').textContent = message;
  showModal('errorModal');
}

function showWarning(message) {
  document.getElementById('warningMessage').textContent = message;
  showModal('warningModal');
}

const searchInput = document.getElementById('searchInput');
const suggestionBox = document.getElementById('suggestionBox');

// =====================
// SEARCH SUGGESTIONS
// =====================
searchInput.addEventListener('keyup', () => {
  const term = searchInput.value.trim();
  if (term.length < 1) { suggestionBox.style.display = 'none'; return; }
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=search_suggestions&term=${encodeURIComponent(term)}`
  }).then(r => r.json()).then(data => {
    suggestionBox.innerHTML = '';
    if (data.length > 0) {
      data.forEach(item => {
        const div = document.createElement('div');
        div.classList.add('suggestion-item');
        div.textContent = item.full_name;
        div.onclick = () => {
          searchInput.value = item.full_name;
          suggestionBox.style.display = 'none';
          filterByName(item.full_name);
        };
        suggestionBox.appendChild(div);
      });
      suggestionBox.style.display = 'block';
    } else suggestionBox.style.display = 'none';
  });
});

function filterByName(name) {
  searchInput.value = name;
  fetchEmployees();
}

function clearSearch() {
  searchInput.value = '';
  suggestionBox.style.display = 'none';
  document.getElementById('statusFilter').value = 'all';
  currentPage = 1; // Reset to first page
  fetchEmployees();
}

// =====================
// PAGINATION
// =====================
let currentPage = <?= $current_page ?>;
let totalPages = <?= max(1, $total_pages) ?>;

function changePage(direction) {
  console.log('changePage called:', direction, 'currentPage:', currentPage, 'totalPages:', totalPages);
  
  let newPage = currentPage;
  
  if (direction === 'prev') {
    newPage = currentPage - 1;
  } else if (direction === 'next') {
    newPage = currentPage + 1;
  }
  
  // Validate page bounds
  if (newPage < 1 || newPage > totalPages) {
    console.log('Invalid page number:', newPage);
    return;
  }
  
  currentPage = newPage;
  fetchEmployees();
}

// Add event listeners for pagination buttons
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('prevBtn').addEventListener('click', function(e) {
    e.preventDefault();
    if (currentPage > 1) {
      changePage('prev');
    }
  });
  
  document.getElementById('nextBtn').addEventListener('click', function(e) {
    e.preventDefault();
    if (currentPage < totalPages) {
      changePage('next');
    }
  });
});

function updatePaginationControls(current, total) {
  currentPage = current;
  totalPages = total;
  
  document.getElementById('pageInfo').textContent = `Page ${current} of ${total}`;
  document.getElementById('prevBtn').disabled = current <= 1;
  document.getElementById('nextBtn').disabled = current >= total;
  
  // Update button styles for disabled state
  if (current <= 1) {
    document.getElementById('prevBtn').style.opacity = '0.5';
    document.getElementById('prevBtn').style.cursor = 'not-allowed';
  } else {
    document.getElementById('prevBtn').style.opacity = '1';
    document.getElementById('prevBtn').style.cursor = 'pointer';
  }
  
  if (current >= total) {
    document.getElementById('nextBtn').style.opacity = '0.5';
    document.getElementById('nextBtn').style.cursor = 'not-allowed';
  } else {
    document.getElementById('nextBtn').style.opacity = '1';
    document.getElementById('nextBtn').style.cursor = 'pointer';
  }
}

// =====================
// FETCH EMPLOYEES VIA AJAX
// =====================
async function fetchEmployees() {
  const searchValue = searchInput.value.trim();
  const statusValue = document.getElementById('statusFilter').value;
  
  const formData = new FormData();
  formData.append('action', 'fetch_employees');
  formData.append('status_filter', statusValue);
  formData.append('search_term', searchValue);
  formData.append('page', currentPage);
  
  try {
    const response = await fetch('', {
      method: 'POST',
      body: formData
    });
    
    const data = await response.json();
    
    if (data.status === 'success') {
      updateEmployeeTable(data.employees);
      updatePaginationControls(data.current_page, data.total_pages);
    }
  } catch (error) {
    console.error('Error fetching employees:', error);
  }
}

// Function to update the employee table
function updateEmployeeTable(employees) {
  const tbody = document.querySelector('#employeeTable tbody');
  tbody.innerHTML = '';
  
  if (employees.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" style="text-align:center; padding:30px; color:#999;">
          No employees found.
        </td>
      </tr>
    `;
    return;
  }
  
  employees.forEach(e => {
    const statusClass = `status-${e.status.toLowerCase()}`;
    const fName = `${e.first_name}`.replace(/\s+/g, ' ').trim();
    const mName = `${e.middle_name }`.replace(/\s+/g, ' ').trim();
    const lName = `${e.last_name}`.replace(/\s+/g, ' ').trim();
    const row = document.createElement('tr');
    row.dataset.status = e.status;
    row.innerHTML = `
      <td>${escapeHtml(e.id)}</td>
      <td>${escapeHtml(fName)}</td>
      <td>${escapeHtml(mName)}</td>
      <td>${escapeHtml(lName)}</td>
      <td>${escapeHtml(e.contact)}</td>
      <td>
        <span class="status-badge ${statusClass}">
          ${capitalizeFirst(e.status)}
        </span>
      </td>
      <td>
        <button class="action-btn edit-btn" onclick="openEditModal(${e.id}, '${escapeQuotes(e.first_name)}', '${escapeQuotes(e.middle_name || '')}', '${escapeQuotes(e.last_name)}', '${escapeQuotes(e.contact)}', '${e.status}')">EDIT</button>
        <div class="dropdown-action" style="display:inline-block; position:relative;">
          <button class="action-btn edit-btn" onclick="toggleDropdown(this)">STATUS ▼</button>
          <div class="dropdown-menu">
            <div onclick="changeStatus(${e.id},'active')">Active</div>
            <div onclick="changeStatus(${e.id},'leave')">Leave</div>
            <div onclick="changeStatus(${e.id},'banned')">Banned</div>
          </div>
        </div>
      </td>
    `;
    tbody.appendChild(row);
  });
}

// Helper functions
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function escapeQuotes(text) {
  return text.replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function capitalizeFirst(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

// =====================
// STATUS FILTER
// =====================
document.getElementById('statusFilter').addEventListener('change', function() {
  currentPage = 1; // Reset to first page when filter changes
  fetchEmployees();
});

// =====================
// DROPDOWN
// =====================
function toggleDropdown(btn) {
  const menu = btn.nextElementSibling;
  document.querySelectorAll('.dropdown-menu').forEach(m => { if (m !== menu) m.style.display = 'none'; });
  menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('.dropdown-action')) {
    document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
  }
});

// =====================
// ADD EMPLOYEE
// =====================
function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
function closeAddModal() { 
  document.getElementById('addModal').style.display = 'none';
  document.getElementById('addFirstName').value = '';
  document.getElementById('addMiddleName').value = '';
  document.getElementById('addLastName').value = '';
  document.getElementById('addContact').value = '';
}

function addEmployee() {
  const firstName = document.getElementById('addFirstName').value.trim();
  const middleName = document.getElementById('addMiddleName').value.trim();
  const lastName = document.getElementById('addLastName').value.trim();
  const contact = document.getElementById('addContact').value.trim();
  
  if (!firstName || !lastName || !contact) { 
    showWarning('Please fill in all required fields (First Name, Last Name, Contact)');
    return; 
  }
  
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=add&first_name=${encodeURIComponent(firstName)}&middle_name=${encodeURIComponent(middleName)}&last_name=${encodeURIComponent(lastName)}&contact=${encodeURIComponent(contact)}`
  }).then(r => r.json()).then(res => {
    closeAddModal();
    if (res.status === 'success') {
      showSuccess('Employee added successfully!');
    } else {
      showError(res.msg);
    }
  });
}

// =====================
// EDIT EMPLOYEE
// =====================
function openEditModal(id, firstName, middleName, lastName, contact, status) {
  // Check if employee is banned
  if (status === 'banned') {
    showWarning('Cannot edit banned employee information. Banned employees are locked from editing.');
    return;
  }
  
  document.getElementById('editId').value = id;
  document.getElementById('editFirstName').value = firstName;
  document.getElementById('editMiddleName').value = middleName;
  document.getElementById('editLastName').value = lastName;
  document.getElementById('editContact').value = contact;
  document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() { 
  document.getElementById('editModal').style.display = 'none';
}

function updateEmployee() {
  const id = document.getElementById('editId').value;
  const firstName = document.getElementById('editFirstName').value.trim();
  const middleName = document.getElementById('editMiddleName').value.trim();
  const lastName = document.getElementById('editLastName').value.trim();
  const contact = document.getElementById('editContact').value.trim();
  
  if (!firstName || !lastName || !contact) { 
    showWarning('Please fill in all required fields (First Name, Last Name, Contact)');
    return; 
  }
  
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=edit&id=${id}&first_name=${encodeURIComponent(firstName)}&middle_name=${encodeURIComponent(middleName)}&last_name=${encodeURIComponent(lastName)}&contact=${encodeURIComponent(contact)}`
  }).then(r => r.json()).then(res => {
    closeEditModal();
    if (res.status === 'success') {
      showSuccess('Employee information updated successfully!');
    } else {
      showError(res.msg);
    }
  });
}

// =====================
// DELETE CONFIRMATION
// =====================
let deleteId = 0;

function openDeleteModal(id, name) {
  deleteId = id;
  document.getElementById('deleteText').textContent = `Are you sure you want to remove "${name}"?`;
  document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
  document.getElementById('deleteModal').style.display = 'none';
}

function deleteEmployee() {
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=delete&id=${deleteId}`
  }).then(r => r.json()).then(res => {
    closeDeleteModal();
    if (res.status === 'success') {
      showSuccess('Employee removed successfully!');
    } else {
      showError(res.msg);
    }
  });
}

// =====================
// EDIT CONFIRMATION (Status Change)
// =====================
let editId = 0;
let newStatus = '';
let currentName = '';
let currentStatus = '';

function changeStatus(id, status) {
  // get name & current status from row
  const row = [...document.querySelectorAll('#employeeTable tbody tr')].find(r => r.children[0].textContent == id);
  if (!row) return;
  currentName = row.children[1].textContent;
  currentStatus = row.dataset.status;
  const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
  const currentLabel = currentStatus.charAt(0).toUpperCase() + currentStatus.slice(1);

  editId = id;
  newStatus = status;

  document.getElementById('editConfirmText').textContent = 
    `Change "${currentName}" from ${currentLabel} → ${statusLabel}?`;
  document.getElementById('editConfirmModal').style.display = 'flex';
}

function closeEditConfirmModal() {
  document.getElementById('editConfirmModal').style.display = 'none';
}

document.getElementById('confirmEditBtn').addEventListener('click', () => {
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=change_status&id=${editId}&status=${newStatus}`
  }).then(r => r.json()).then(res => {
    closeEditConfirmModal();
    if (res.status === 'success') {
      showSuccess('Employee status updated successfully!');
    } else {
      showError(res.msg);
    }
  });
});

// =====================
// NOTIFICATION DROPDOWN
// =====================
function toggleNotif() {
  const n = document.getElementById('notifDropdown');
  n.style.display = n.style.display === 'none' ? 'block' : 'none';
}

setInterval(() => {
  fetch('session_check.php')
    .then(res => res.json())
    .then(data => {
      if (!data.active) {
        document.getElementById('sessionMessage').textContent = 
          'Your session has expired or you have logged out in another tab. Please log in again.';
        showModal('sessionModal');
      }
    });
}, 15000); // check every 15 seconds
</script>


</body>
</html>
