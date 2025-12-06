<?php
error_reporting(E_ALL);
include ('connection.php');
require 'superadmin_session.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Handle SET PRICE action
    if (isset($_POST['action']) && $_POST['action'] === 'set_price') {
        $new_price = floatval($_POST['price'] ?? 0);
        
        // Validate price: must be greater than 0
        if ($new_price <= 0) {
            echo json_encode(['success' => false, 'message' => 'Price must be greater than zero.']);
            exit;
        }
        
        // Insert new price into price_log
        $stmt = $conn->prepare("INSERT INTO price_log (price, created_at) VALUES (?, NOW())");
        $stmt->bind_param("d", $new_price);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Price updated successfully!', 'new_price' => $new_price]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update price']);
        }
        $stmt->close();
        exit;
    }
    
    // Handle STATUS CHANGE action (Unpaid to Paid)
    if (isset($_POST['action']) && $_POST['action'] === 'change_status') {
        $employee_id = intval($_POST['employee_id'] ?? 0);
        $new_status = $_POST['status'] ?? 'unpaid';
        
        if ($employee_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID']);
            exit;
        }
        
        // Check if all employees are now marked as paid
        // Get total employee count
        $total_employees_query = "SELECT COUNT(*) as total FROM employees";
        $total_result = $conn->query($total_employees_query);
        $total_row = $total_result->fetch_assoc();
        $total_employees = $total_row['total'];
        
        // Get count of paid employees (including the one being marked now)
        $paid_count_query = "SELECT COUNT(DISTINCT employee_id) as paid_count FROM payroll WHERE status = 'paid' AND payroll_date = CURDATE()";
        $paid_result = $conn->query($paid_count_query);
        $paid_row = $paid_result->fetch_assoc();
        $paid_count = $paid_row['paid_count'] + 1; // +1 for the current one being marked
        
        // Update the current employee's status in payroll table
        $update_stmt = $conn->prepare("UPDATE payroll SET status = 'paid', paid_at = NOW() WHERE employee_id = ? AND payroll_date = CURDATE()");
        $update_stmt->bind_param("i", $employee_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        $reset_performed = false;
        
        // If all employees are paid, reset the data
        if ($paid_count >= $total_employees) {
            // Delete all employee daily records
            $conn->query("DELETE FROM employee_daily_records");
            
            // Delete all quality check logs
            $conn->query("DELETE FROM quality_check_logs");
            
            // Reset employee table values
            $conn->query("UPDATE employees SET input = 0, quality_check = 0, defects = 0, output = 0");
            
            $reset_performed = true;
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Status updated to ' . $new_status,
            'reset_performed' => $reset_performed,
            'reset_message' => $reset_performed ? 'All employees paid! Data has been reset for next cycle.' : ''
        ]);
        exit;
    }
    
    // Handle SAVE PAYROLL action
    if (isset($_POST['action']) && $_POST['action'] === 'save_payroll') {
        // Check if today is Saturday (6) or Sunday (0)
        $current_day = date('w'); // 0 = Sunday, 6 = Saturday
        if ($current_day != 0 && $current_day != 6) {
            $day_name = date('l'); // Get full day name
            echo json_encode(['success' => false, 'message' => "Payroll can only be saved on Saturday or Sunday. Today is $day_name."]);
            exit;
        }
        
        $payroll_data = json_decode($_POST['payroll_data'] ?? '[]', true);
        
        // Get current price
        $price_query = "SELECT price FROM price_log ORDER BY created_at DESC LIMIT 1";
        $price_result = $conn->query($price_query);
        $current_price = 0;
        if ($price_result && $price_result->num_rows > 0) {
            $price_row = $price_result->fetch_assoc();
            $current_price = floatval($price_row['price']);
        }
        
        $saved_count = 0;
        $payroll_date = date('Y-m-d');
        
        foreach ($payroll_data as $employee) {
            $employee_id = intval($employee['id'] ?? 0);
            $first_name = $employee['first_name'] ?? '';
            $last_name = $employee['last_name'] ?? '';
            $overall_input = intval($employee['overall_input'] ?? 0);
            $overall_output = intval($employee['overall_output'] ?? 0);
            $overall_defects = intval($employee['overall_defects'] ?? 0);
            $total_salary = floatval($employee['total_salary'] ?? 0);
            
            if ($employee_id > 0) {
                $stmt = $conn->prepare("INSERT INTO payroll (employee_id, first_name, last_name, overall_input, overall_output, overall_defects, price_per_piece, total_salary, payroll_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("issiiidds", $employee_id, $first_name, $last_name, $overall_input, $overall_output, $overall_defects, $current_price, $total_salary, $payroll_date);
                
                if ($stmt->execute()) {
                    $saved_count++;
                }
                $stmt->close();
            }
        }
        
        if ($saved_count > 0) {
            echo json_encode(['success' => true, 'message' => "Payroll saved successfully! ($saved_count employees)"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save payroll']);
        }
        exit;
    }
}

// === Calculate Week Ranges (Monday to Sunday) ===
function getWeekRanges($year, $month) {
    $weeks = [];
    $firstDay = new DateTime("$year-$month-01");
    $lastDay = new DateTime($firstDay->format('Y-m-t'));
    
    // Find the first Monday of the month (or the Monday before if month doesn't start on Monday)
    $currentMonday = clone $firstDay;
    if ($currentMonday->format('N') != 1) {
        $currentMonday->modify('last monday');
    }
    
    $weekNum = 1;
    while ($currentMonday <= $lastDay) {
        $sunday = clone $currentMonday;
        $sunday->modify('+6 days');
        
        // Only include weeks that have days in the current month
        if ($currentMonday->format('m') == $month || $sunday->format('m') == $month) {
            $weeks[$weekNum] = [
                'start' => $currentMonday->format('M d'),
                'end' => $sunday->format('M d'),
                'start_full' => $currentMonday->format('Y-m-d'),
                'end_full' => $sunday->format('Y-m-d')
            ];
            $weekNum++;
        }
        
        $currentMonday->modify('+7 days');
    }
    
    return $weeks;
}

$currentYear = date('Y');
$currentMonth = date('m');
$weekRanges = getWeekRanges($currentYear, $currentMonth);

// Determine which week we're currently in
$today = date('Y-m-d');
$currentWeekNum = 0;
foreach ($weekRanges as $weekNum => $range) {
    if ($today >= $range['start_full'] && $today <= $range['end_full']) {
        $currentWeekNum = $weekNum;
        break;
    }
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

// ✅ Fetch single business ID
$businessQuery = $conn->query("SELECT id FROM business_info LIMIT 1");
$business = $businessQuery->fetch_assoc();
$business_id = $business ? $business['id'] : null;

if (!$business_id) {
    echo "<p style='color:red;'>⚠️ No business found in the system.</p>";
    exit;
}

// Get the latest price from price_log table
$price_query = "SELECT price FROM price_log ORDER BY created_at DESC LIMIT 1";
$price_result = $conn->query($price_query);
$current_price = 0;
if ($price_result && $price_result->num_rows > 0) {
    $price_row = $price_result->fetch_assoc();
    $current_price = floatval($price_row['price']);
}

// Get the current order being processed (not completed or cancelled)
$current_order_query = "SELECT orderID, order_status FROM confirmed_order
                        WHERE order_status NOT IN ('completed', 'cancelled') 
                        ORDER BY updated_at DESC LIMIT 1";
$current_order_result = $conn->query($current_order_query);
$current_order_id = 'N/A';
$current_order_status = 'N/A';
if ($current_order_result && $current_order_result->num_rows > 0) {
    $order_row = $current_order_result->fetch_assoc();
    $current_order_id = $order_row['orderID'];
    $current_order_status = ucfirst($order_row['order_status']);
}

// Set default selected date to today
$selectedDate = date('Y-m-d');

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payroll - Owner</title>

<!-- Include Theme Loader for Custom Colors -->
<?php include('theme_loader.php'); ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    html {
      scroll-behavior: smooth;
    }

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

    .menu-toggle {
      display: none;
      font-size: 22px;
      cursor: pointer;
      margin-right: 15px;
    }

    /* ===== Responsive Layout ===== */
    @media (max-width: 900px) {
      .sidebar {
        transform: translateX(-100%);
      }
      .sidebar.active {
        transform: translateX(0);
      }

      .main {
        margin-left: 0;
        width: 100%;
        padding: 20px;
      }

      .menu-toggle {
        display: inline-block;
      }

      .header {
        justify-content: flex-start;
        gap: 15px;
      }

      .header h2 {
        font-size: 18px;
      }

      .table-container {
        overflow-x: auto;
      }

      .control-box,
      .settings-box {
        flex-direction: column;
        align-items: stretch;
      }

      .settings-container {
        flex-direction: column;
      }

      .search-box input {
        font-size: 13px;
        
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

    .header h2 {
      font-size: 28px;
      font-weight: 700;
      color: var(--theme-bg);
      letter-spacing: 0.5px;
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

    .profile {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .profile img {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      border: 3px solid var(--theme-bg);
      object-fit: cover;
    }

    .profile span {
      font-weight: 600;
      color: #333;
      font-size: 15px;
    }
    .header-right span {
      font-weight: 600;
      color: #333;
      font-size: 15px;
    }

    .payroll-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: white;
      margin-bottom: 10px;
    }

    .info {
      font-style: italic;
      font-size: 16px;
    }

    .date-input {
      margin-left: 8px;
      padding: 4px 8px;
      border-radius: 4px;
      border: none;
      outline: none;
    }

    .week-selector {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      margin-top: 6px;
    }

    /* ===== Week Selector ===== */
    .week-selector {
      display: flex;
      flex-wrap: wrap;
      gap: 4px;
      margin-top: 6px;
    }

    .week-selector label {
      margin-right: 10px;
      font-size: 14px;
      cursor: pointer;
      padding: 8px 16px;
      border-radius: 10px;
      transition: all 0.3s ease;
      background: #fff;
      color: #667eea;
      border: 2px solid #e0e0e0;
      font-weight: 600;
    }

    .week-selector label:hover {
      border-color: var(--theme-bg);
      transform: translateY(-2px);
    }

    .week-selector label.active {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      font-weight: 700;
      border-color: var(--theme-bg);
      box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
    }

    /* ===== Table ===== */

    .table-container {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
      overflow-x: auto;
      margin-top: 25px;
      transition: all 0.3s ease;
    }

    .table-container:hover {
      box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      min-width: 700px;
      margin-bottom: 20px;
    }

    .table th {
      color: var(--theme-bg);
      font-weight: 600;
      text-transform: uppercase;
      font-size: 13px;
      border-bottom: 2px solid rgba(102, 126, 234, 0.2);
      padding: 14px 12px;
      letter-spacing: 0.5px;
    }

    .table td {
      text-align: center;
      padding: 12px 10px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.1);
      color: #333;
      font-size: 14px;
    }

    .table tbody tr:hover {
      background: rgba(102, 126, 234, 0.05);
    }

    .table td:last-child {
      font-weight: bold;
    }

    .status-btn {
      background: #28a745 !important;
      color: white !important;
      border: none;
      padding: 8px 18px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
      font-size: 13px;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .status-btn.unpaid {
      background: #dc3545 !important;
      color: white !important;
    }

    .status-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
    }

    .status-btn.unpaid:hover {
      box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
    }

    .edit-btn {
      width: 100%;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      padding: 15px;
      font-weight: 700;
      text-transform: uppercase;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .edit-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .settings-container {
      display: flex;
      gap: 20px;
      margin-top: 25px;
      flex-wrap: wrap;
    }

    .settings {
      display: flex;
      justify-content: space-between;
      gap: 20px;
      margin-top: 25px;
    }
    
    .settings-box {
      flex: 1;
      min-width: 280px;
      background: rgba(255, 255, 255, 0.95);
      padding: 20px 25px;
      border-radius: 15px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
    }

    .settings-box label {
      font-weight: 600;
      margin-bottom: 6px;
      color: var(--theme-bg);
      font-size: 14px;
    }

    .settings-box input {
      flex: 1;
      min-width: 140px;
      padding: 10px 14px;
      border-radius: 10px;
      border: 2px solid #e0e0e0;
      margin-right: 10px;
      transition: all 0.3s ease;
      font-size: 14px;
      color: #333;
    }

    .settings-box input:focus {
      outline: none;
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .settings select,
    .settings input {
      background: #fff;
      color: #333;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      padding: 10px 14px;
      font-weight: 500;
    }
    
    .apply-btn {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      border: none;
      color: var(--theme-font);
      padding: 10px 20px;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
      margin-top: 8px;
      transition: all 0.3s ease;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .apply-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .settings small {
      font-style: italic;
      font-size: 13px;
      opacity: 0.9;
    }

    .top-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
      margin-bottom: 15px;
      width: 100%;
      flex-wrap: wrap;
    }

    .control-box {
      flex: 1;
      min-width: 300px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(255, 255, 255, 0.95);
      padding: 20px 25px;
      border-radius: 15px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
      flex-wrap: wrap;
      gap: 15px;
    }

    .control-box label {
      margin-right: 10px;
      font-weight: 600;
      color: var(--theme-bg);
      font-size: 14px;
    }

    .control-box select,
    .control-box input {
      background: #fff;
      color: #333;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      padding: 10px 14px;
      font-weight: 500;
      margin-right: 10px;
      transition: all 0.3s ease;
      font-size: 14px;
    }

    .control-box select:focus,
    .control-box input:focus {
      outline: none;
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }


    .search-and-price-container {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 25px;
      flex-wrap: wrap;
    }

    .search-box-wrapper {
      flex: 1;
      min-width: 300px;
      max-width: 800px;
    }

    .search-box-wrapper input {
      width: 100%;
      padding: 14px 20px;
      border-radius: 15px;
      border: 2px solid #e0e0e0;
      outline: none;
      font-size: 15px;
      background: rgba(255, 255, 255, 0.95);
      color: #333;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      font-weight: 500;
    }

    .search-box-wrapper input:focus {
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .price-box-wrapper {
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255, 255, 255, 0.95);
      padding: 12px 20px;
      border-radius: 15px;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }

    .price-box-wrapper label {
      font-weight: 600;
      color: var(--theme-bg);
      font-size: 14px;
      white-space: nowrap;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .price-box-wrapper input {
      width: 120px;
      padding: 10px 14px;
      border-radius: 10px;
      border: 2px solid #e0e0e0;
      outline: none;
      font-size: 15px;
      color: #333;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .price-box-wrapper input:focus {
      border-color: var(--theme-bg);
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .price-box-wrapper .apply-btn {
      margin: 0;
      padding: 10px 20px;
      white-space: nowrap;
    }

    /* ===== MOBILE ADJUSTMENTS ===== */
    @media (max-width: 900px) {
      .top-controls {
        flex-direction: column;
        align-items: stretch;
      }

      .control-box {
        flex-direction: column;
        align-items: stretch;
        text-align: left;
        gap: 10px;
      }

      .control-box select,
      .control-box input {
        width: 100%;
        margin-right: 0;
      }

      .settings-container {
        flex-direction: column;
        gap: 15px;
      }

      .settings-box {
        flex-direction: column;
        align-items: stretch;
      }

      .settings-box input {
        width: 100%;
        margin-right: 0;
      }

      .apply-btn {
        width: 100%;
      }

      .table-container {
        padding: 10px;
        border-radius: 8px;
      }

      .table th, .table td {
        font-size: 12px;
        padding: 8px;
      }

      .search-and-price-container {
        flex-direction: column;
        align-items: stretch;
      }

      .search-box-wrapper {
        max-width: 100%;
      }

      .search-box-wrapper input {
        font-size: 13px;
        padding: 10px 14px;
      }

      .price-box-wrapper {
        flex-wrap: wrap;
        justify-content: center;
      }

      .price-box-wrapper input {
        width: 100px;
      }

      .header h2 {
        font-size: 18px;
      }

      .menu-toggle {
        font-size: 20px;
      }
    }

    .settings-container {
      display: flex;
      gap: 20px;
      margin-top: 25px;
    }

    .settings-container .settings-box {
      flex: 1;
      background-color: #0a0a3d;
      padding: 15px 20px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .settings-container input {
      width: 60%;
      padding: 8px 10px;
      border-radius: 6px;
      border: none;
    }

    .modal-overlay {
      position: fixed;
      top:0; left:0; width:100%; height:100%;
      background:rgba(0,0,0,0.7);
      display:none;
      justify-content:center;
      align-items:center;
      z-index:9999;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-box {
      background:#fff;
      padding:35px;
      border-radius:20px;
      width:450px;
      max-width:90%;
      color:#333;
      text-align:center;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
      from { transform: translateY(-50px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .modal-box h3 {
      margin-bottom: 15px;
      font-weight: 700;
      color: var(--theme-bg);
    }

    .modal-box p {
      line-height: 1.6;
      color: #666;
    }

    .modal-confirm, .modal-cancel {
      padding: 12px 30px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
      margin: 5px;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .modal-confirm {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
    }

    .modal-confirm:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .modal-cancel {
      background: #f8f9fa;
      color: #333;
      border: 2px solid #e0e0e0;
    }

    .modal-cancel:hover {
      background: #e9ecef;
      transform: translateY(-2px);
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
      }

      .date-input,
      .clear-btn {
        width: 100%;
      }

      .table-container {
        padding: 15px;
        border-radius: 15px;
        overflow-x: auto;
      }

      .table {
        font-size: 13px;
        min-width: 700px;
      }

      .table th,
      .table td {
        padding: 10px 8px;
        font-size: 12px;
      }

      .table th {
        font-size: 11px;
      }

      .modal-content {
        width: 95%;
        padding: 25px 20px;
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

      .filter-section h3 {
        font-size: 16px !important;
      }

      .search-bar,
      .date-input,
      .clear-btn {
        padding: 10px 14px;
        font-size: 13px;
      }

      .table-container {
        padding: 10px;
      }

      .table {
        min-width: 650px;
      }

      .table th,
      .table td {
        padding: 8px 6px;
        font-size: 11px;
      }

      .modal-content {
        padding: 20px 15px;
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
        <a href="superadmin_payroll.php" class="active"><i class="fa-solid fa-calculator"></i> Payroll</a>
        <a href="payroll_history.php"><i class="fa-solid fa-history"></i> History</a>
      </div>
    </div>
    <a href="superadmin_order.php"><i  class="fa-solid fa-box"></i> Orders</a>
    <a href="superadmin_orderStatus.php"><i class="fa-solid fa-chart-bar"></i> Order Status</a>
    <a href="customize.php"><i class="fa-solid fa-gear"></i> Customize</a>
  </div>
  <div class="bottom">
    <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</div>

<div class="main">
    <div class="header">
      <i class="fa-solid fa-bars menu-toggle" id="menuToggle"></i>
      <div class="header-left">
        <h1><i class="fa-solid fa-money-bill"></i> Payroll Management</h1>
        <p>Manage employee salaries and payroll records with automated calculations</p>
      </div>
      <div class="header-right">
        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profile">
        <span><?php echo htmlspecialchars($owner_name); ?></span>
      </div>
    </div>


  <div class="payroll-container">

<div class="top-controls">
  <div class="control-box">
    <label><strong>ORDER ID:</strong></label>
    <span style="padding: 10px 14px; background: #fff; border: 2px solid #e0e0e0; border-radius: 10px; font-weight: 600; color: #333;">
      <?php echo htmlspecialchars($current_order_id); ?>
    </span>

    <label><strong>STATUS:</strong></label>
    <span style="padding: 10px 14px; background: <?php echo ($current_order_status === 'N/A') ? '#f8f9fa' : '#e3f2fd'; ?>; border: 2px solid #e0e0e0; border-radius: 10px; font-weight: 600; color: <?php echo ($current_order_status === 'N/A') ? '#999' : 'var(--theme-bg)'; ?>;">
      <?php echo htmlspecialchars($current_order_status); ?>
    </span>

    <label for="dateFilter"><strong>DATE:</strong></label>
    <input type="date" id="dateFilter" class="date-input" value="<?php echo htmlspecialchars($selectedDate); ?>">

    <label><strong>WEEK:</strong></label>
    <div class="week-selector" id="weekSelector">
      <?php foreach ($weekRanges as $weekNum => $range): ?>
        <label data-week="<?php echo $weekNum; ?>" 
               class="<?php echo ($weekNum == $currentWeekNum) ? 'active' : ''; ?>" 
               title="<?php echo $range['start'] . ' - ' . $range['end']; ?>">
          <?php echo $weekNum; ?><?php echo ($weekNum == 1) ? 'ST' : (($weekNum == 2) ? 'ND' : (($weekNum == 3) ? 'RD' : 'TH')); ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="search-and-price-container">
  <div class="search-box-wrapper">
    <input type="text" id="searchInput" placeholder="Search employee by name...">
  </div>
  <div class="price-box-wrapper">
    <button type="button" class="apply-btn" id="setPriceBtn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
      <i class="fa-solid fa-tag"></i> SET PRICE
    </button>
    <div style="display: flex; align-items: center; gap: 15px; background: white; padding: 12px 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-left: 10px;">
      <span style="font-weight: 600; color: #555;">Current Price:</span>
      <span style="font-size: 20px; font-weight: 700; color: var(--theme-bg);">₱<?php echo number_format($current_price, 2); ?></span>
    </div>
    <button type="button" class="apply-btn" id="savePayrollBtn" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); margin-left: 10px;">
      <i class="fa-solid fa-save"></i> SAVE PAYROLL
    </button>
  </div>
</div>

<?php
// Fetch payroll data with calculations from employee_daily_records and quality_check_logs
// Only show employees with submitted inputs AND quality checks
$payroll_sql = "
    SELECT 
        e.id,
        e.first_name,
        e.last_name,
        COALESCE(SUM(edr.input_value), 0) as overall_input,
        COALESCE(SUM(qcl.quality_check), 0) as overall_quality_check,
        COALESCE(SUM(qcl.quality_check), 0) as overall_output,
        (COALESCE(SUM(edr.input_value), 0) - COALESCE(SUM(qcl.quality_check), 0)) as overall_defects,
        (SELECT status FROM payroll WHERE employee_id = e.id ORDER BY payroll_date DESC, created_at DESC LIMIT 1) as payroll_status
    FROM employees e
    INNER JOIN employee_daily_records edr ON e.id = edr.employee_id
    INNER JOIN quality_check_logs qcl ON e.id = qcl.employee_id AND edr.work_date = qcl.check_date
    WHERE e.status = 'active'
    GROUP BY e.id, e.first_name, e.last_name
    HAVING overall_input > 0 AND overall_output > 0
    ORDER BY e.id ASC
";

$payroll_result = $conn->query($payroll_sql);
?>

<div class="table-container">
  <table class="table" id="employeeTable">
    <thead>
      <tr>
        <th>ID</th>
        <th>NAME</th>
        <th>OUTPUT</th>
        <th>QUALITY CHECK</th>
        <th>GOODS</th>
        <th>DEFECT</th>
        <th>SALARY</th>
        <th>STATUS</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($payroll_result && $payroll_result->num_rows > 0): ?>
        <?php while ($row = $payroll_result->fetch_assoc()): 
          // Calculate salary using price from price_log
          $total_salary = $row['overall_output'] * $current_price;
          
          // Determine status from payroll table (default to 'unpaid' if no record)
          $status = $row['payroll_status'] ?? 'unpaid';
          $status_class = ($status === 'paid') ? '' : 'unpaid';
          $status_text = ucfirst($status);
        ?>
          <tr>
            <td><?php echo htmlspecialchars($row['id']); ?></td>
            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
            <td><?php echo number_format($row['overall_input']); ?></td>
            <td><?php echo number_format($row['overall_quality_check']); ?></td>
            <td><?php echo number_format($row['overall_output']); ?></td>
            <td><?php echo number_format($row['overall_defects']); ?></td>
            <td>₱<?php echo number_format($total_salary, 2); ?></td>
            <td>
              <button class="status-btn <?php echo $status_class; ?>" 
                      data-id="<?php echo $row['id']; ?>" 
                      data-status="<?php echo $status; ?>">
                <?php echo $status_text; ?>
              </button>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="8" style="text-align: center; padding: 20px; color: #666;">
            No employee data available
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
  
  <!-- Pagination Controls -->
  <div id="paginationControls" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; padding: 15px;">
    <button onclick="previousPage()" id="prevBtn" style="padding: 10px 20px; background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); color: var(--theme-font); border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
      <i class="fa-solid fa-chevron-left"></i> Previous
    </button>
    <span id="pageInfo" style="font-weight: 600; color: #333; font-size: 15px;">Page 1 of 1</span>
    <button onclick="nextPage()" id="nextBtn" style="padding: 10px 20px; background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); color: var(--theme-font); border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
      Next <i class="fa-solid fa-chevron-right"></i>
    </button>
  </div>
</div>

<!-- Set Price Modal -->
<div id="setPriceModal" class="modal-overlay" style="display: none;">
  <div class="modal-content">
    <div class="modal-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
      <i class="fa-solid fa-tag"></i>
    </div>
    <h3>Set Price Per Piece</h3>
    <p>Enter the new price per piece for payroll calculation</p>
    <div class="form-group">
      <label>Current Price: ₱<?php echo number_format($current_price, 2); ?></label>
      <input type="number" id="newPriceInput" placeholder="Enter new price" min="0" step="0.01" style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; margin-top: 10px;">
    </div>
    <div class="modal-buttons">
      <button onclick="confirmSetPrice()" class="btn-confirm">
        <i class="fa-solid fa-check"></i> Confirm
      </button>
      <button onclick="closeSetPriceModal()" class="btn-cancel">
        <i class="fa-solid fa-times"></i> Cancel
      </button>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="modal-overlay" style="display: none;">
  <div class="modal-content">
    <div class="modal-icon success-icon">
      <i class="fa-solid fa-check-circle"></i>
    </div>
    <h3>Success!</h3>
    <p id="successMessage">Operation completed successfully</p>
    <div class="modal-buttons">
      <button onclick="closeSuccessModal()" class="btn-confirm">OK</button>
    </div>
  </div>
</div>

<!-- Status Change Confirmation Modal -->
<div id="statusModal" class="modal-overlay" style="display: none;">
  <div class="modal-content">
    <div class="modal-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
      <i class="fa-solid fa-exchange-alt"></i>
    </div>
    <h3>Change Payment Status</h3>
    <p id="statusMessage">Are you sure you want to mark this employee as paid?</p>
    <div class="modal-buttons">
      <button onclick="confirmStatusChange()" class="btn-confirm">
        <i class="fa-solid fa-check"></i> Confirm
      </button>
      <button onclick="closeStatusModal()" class="btn-cancel">
        <i class="fa-solid fa-times"></i> Cancel
      </button>
    </div>
  </div>
</div>

<!-- Save Payroll Confirmation Modal -->
<div id="savePayrollModal" class="modal-overlay" style="display: none;">
  <div class="modal-content">
    <div class="modal-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
      <i class="fa-solid fa-save"></i>
    </div>
    <h3>Save Payroll</h3>
    <p id="savePayrollMessage">Are you sure you want to save this payroll?</p>
    <div class="modal-buttons">
      <button onclick="confirmSavePayroll()" class="btn-confirm" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
        <i class="fa-solid fa-check"></i> Yes, Save
      </button>
      <button onclick="closeSavePayrollModal()" class="btn-cancel">
        <i class="fa-solid fa-times"></i> Cancel
      </button>
    </div>
  </div>
</div>

<!-- Error Modal -->
<div id="errorModal" class="modal-overlay" style="display: none;">
  <div class="modal-content">
    <div class="modal-icon error-icon">
      <i class="fa-solid fa-exclamation-triangle"></i>
    </div>
    <h3>Invalid Price</h3>
    <p id="errorMessage">Price must be greater than 0 and cannot be negative.</p>
    <div class="modal-buttons">
      <button onclick="closeErrorModal()" class="btn-confirm" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
        <i class="fa-solid fa-times"></i> Close
      </button>
    </div>
  </div>
</div>

<style>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 10000;
  animation: fadeIn 0.3s ease;
  backdrop-filter: blur(5px);
}

.modal-content {
  background: white;
  padding: 40px;
  border-radius: 20px;
  text-align: center;
  min-width: 400px;
  max-width: 500px;
  box-shadow: 0 25px 70px rgba(0, 0, 0, 0.5);
  animation: slideInModal 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
  color: #333;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideInModal {
  from { transform: scale(0.7) translateY(-50px); opacity: 0; }
  to { transform: scale(1) translateY(0); opacity: 1; }
}

.modal-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 20px;
  background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: bounceIn 0.6s ease;
}

.modal-icon i {
  font-size: 35px;
  color: white;
}

.success-icon {
  background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.error-icon {
  background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

@keyframes bounceIn {
  0% { transform: scale(0); }
  50% { transform: scale(1.2); }
  100% { transform: scale(1); }
}

.modal-content h3 {
  margin: 0 0 15px;
  font-size: 28px;
  font-weight: 700;
  color: #333;
}

.modal-content p {
  font-size: 16px;
  margin: 0 0 25px;
  color: #666;
  line-height: 1.7;
}

.form-group {
  margin: 20px 0;
  text-align: left;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #555;
}

.modal-buttons {
  display: flex;
  gap: 15px;
  margin-top: 25px;
}

.modal-buttons button {
  flex: 1;
  padding: 14px 20px;
  border: none;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.btn-confirm {
  background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
  color: white;
}

.btn-confirm:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.btn-cancel {
  background: #f0f0f0;
  color: #333;
}

.btn-cancel:hover {
  background: #e0e0e0;
  transform: translateY(-2px);
}

@media (max-width: 480px) {
  .modal-content {
    padding: 30px 20px;
    min-width: 300px;
  }
  
  .modal-buttons {
    flex-direction: column;
  }
}
</style>

<script>
// Dropdown toggle function
function toggleDropdown(event) {
  event.preventDefault();
  const dropdown = event.target.closest('.sidebar-dropdown');
  dropdown.classList.toggle('open');
}

let pendingEmployeeId = null;
let pendingStatus = null;

// SET PRICE functionality
document.getElementById('setPriceBtn').addEventListener('click', function() {
  document.getElementById('setPriceModal').style.display = 'flex';
  document.getElementById('newPriceInput').value = '';
  document.getElementById('newPriceInput').focus();
});

function closeSetPriceModal() {
  document.getElementById('setPriceModal').style.display = 'none';
}

function confirmSetPrice() {
  const newPriceInput = document.getElementById('newPriceInput').value;
  const newPrice = parseFloat(newPriceInput);
  
  // Validate: Price must be greater than 0
  if (!newPriceInput || isNaN(newPrice) || newPrice <= 0) {
    closeSetPriceModal();
    document.getElementById('errorMessage').textContent = 'Price must be greater than zero.';
    document.getElementById('errorModal').style.display = 'flex';
    return;
  }
  
  const formData = new FormData();
  formData.append('action', 'set_price');
  formData.append('price', newPrice);
  
  fetch('', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
      closeSetPriceModal();
      if (data.success) {
        document.getElementById('successMessage').textContent = `Price updated to ₱${data.new_price.toFixed(2)}. Page will reload to reflect changes.`;
        document.getElementById('successModal').style.display = 'flex';
        setTimeout(() => {
          location.reload();
        }, 2000);
      } else {
        document.getElementById('errorMessage').textContent = data.message || 'Failed to update price';
        document.getElementById('errorModal').style.display = 'flex';
      }
    })
    .catch(error => {
      closeSetPriceModal();
      document.getElementById('errorMessage').textContent = 'Error updating price: ' + error;
      document.getElementById('errorModal').style.display = 'flex';
    });
}

function closeSuccessModal() {
  document.getElementById('successModal').style.display = 'none';
  location.reload();
}

function closeErrorModal() {
  document.getElementById('errorModal').style.display = 'none';
}

// SAVE PAYROLL functionality with confirmation modal
let pendingPayrollData = null;

document.getElementById('savePayrollBtn').addEventListener('click', function() {
  const rows = document.querySelectorAll('#employeeTable tbody tr');
  const payrollData = [];
  
  rows.forEach(row => {
    const cells = row.cells;
    if (cells.length >= 7 && !cells[0].hasAttribute('colspan')) {
      payrollData.push({
        id: cells[0].textContent.trim(),
        first_name: cells[1].textContent.split(' ')[0] || '',
        last_name: cells[1].textContent.split(' ').slice(1).join(' ') || '',
        overall_input: parseInt(cells[2].textContent.replace(/,/g, '')) || 0,
        overall_output: parseInt(cells[4].textContent.replace(/,/g, '')) || 0,
        overall_defects: parseInt(cells[5].textContent.replace(/,/g, '')) || 0,
        total_salary: parseFloat(cells[6].textContent.replace(/[₱,]/g, '')) || 0
      });
    }
  });
  
  if (payrollData.length === 0) {
    document.getElementById('errorMessage').textContent = 'No employee data to save';
    document.getElementById('errorModal').style.display = 'flex';
    return;
  }
  
  // Store data and show confirmation modal
  pendingPayrollData = payrollData;
  document.getElementById('savePayrollMessage').textContent = `You are about to save payroll for ${payrollData.length} employee(s). This action will make the payroll visible in the history. Continue?`;
  document.getElementById('savePayrollModal').style.display = 'flex';
});

function closeSavePayrollModal() {
  document.getElementById('savePayrollModal').style.display = 'none';
  pendingPayrollData = null;
}

function confirmSavePayroll() {
  if (!pendingPayrollData) return;
  
  const formData = new FormData();
  formData.append('action', 'save_payroll');
  formData.append('payroll_data', JSON.stringify(pendingPayrollData));
  
  closeSavePayrollModal();
  
  fetch('', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.getElementById('successMessage').textContent = data.message + ' Employees can now view their payroll.';
        document.getElementById('successModal').style.display = 'flex';
      } else {
        document.getElementById('errorMessage').textContent = data.message || 'Failed to save payroll';
        document.getElementById('errorModal').style.display = 'flex';
      }
    })
    .catch(error => {
      document.getElementById('errorMessage').textContent = 'Error saving payroll: ' + error;
      document.getElementById('errorModal').style.display = 'flex';
    });
}

// STATUS CHANGE functionality
function attachStatusButtonListeners() {
  document.querySelectorAll('.status-btn').forEach(btn => {
    // Remove existing listeners by cloning
    const newBtn = btn.cloneNode(true);
    btn.parentNode.replaceChild(newBtn, btn);
    
    newBtn.addEventListener('click', function() {
      pendingEmployeeId = this.getAttribute('data-id');
      const currentStatus = this.getAttribute('data-status') || 'unpaid';
      pendingStatus = currentStatus === 'unpaid' ? 'paid' : 'unpaid';
      
      const statusText = pendingStatus === 'paid' ? 'paid' : 'unpaid';
      document.getElementById('statusMessage').textContent = `Are you sure you want to mark this employee as ${statusText}?`;
      document.getElementById('statusModal').style.display = 'flex';
    });
  });
}

// Initialize status button listeners
attachStatusButtonListeners();

function closeStatusModal() {
  document.getElementById('statusModal').style.display = 'none';
  pendingEmployeeId = null;
  pendingStatus = null;
}

function confirmStatusChange() {
  if (!pendingEmployeeId) return;
  
  const formData = new FormData();
  formData.append('action', 'change_status');
  formData.append('employee_id', pendingEmployeeId);
  formData.append('status', pendingStatus);
  
  fetch('', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
      closeStatusModal();
      if (data.success) {
        // Update the button without reloading the page
        const btn = document.querySelector(`.status-btn[data-id="${pendingEmployeeId}"]`);
        if (btn) {
          btn.setAttribute('data-status', pendingStatus);
          btn.textContent = pendingStatus.charAt(0).toUpperCase() + pendingStatus.slice(1);
          
          if (pendingStatus === 'paid') {
            btn.classList.remove('unpaid');
          } else {
            btn.classList.add('unpaid');
          }
        }
        
        // Show success message with reset info if applicable
        let message = data.message;
        if (data.reset_performed && data.reset_message) {
          message += '\n\n' + data.reset_message;
        }
        document.getElementById('successMessage').textContent = message;
        document.getElementById('successModal').style.display = 'flex';
        
        // Only reload if reset was performed
        if (data.reset_performed) {
          setTimeout(() => {
            location.reload();
          }, 2500);
        }
      } else {
        document.getElementById('errorMessage').textContent = data.message || 'Failed to update status';
        document.getElementById('errorModal').style.display = 'flex';
      }
    })
    .catch(error => {
      closeStatusModal();
      document.getElementById('errorMessage').textContent = 'Error updating status: ' + error;
      document.getElementById('errorModal').style.display = 'flex';
    });
}

// Close modals when clicking outside
document.querySelectorAll('.modal-overlay').forEach(modal => {
  modal.addEventListener('click', function(e) {
    if (e.target === this) {
      this.style.display = 'none';
    }
  });
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
      modal.style.display = 'none';
    });
  }
});

// ===== PAGINATION FUNCTIONALITY =====
let currentPage = 1;
const rowsPerPage = 5;
let allRows = [];

function initPagination() {
  const table = document.getElementById('employeeTable');
  const tbody = table.querySelector('tbody');
  allRows = Array.from(tbody.querySelectorAll('tr')).filter(row => !row.querySelector('td[colspan]'));
  
  if (allRows.length > 0) {
    showPage(1);
  }
}

function showPage(page) {
  const totalPages = Math.ceil(allRows.length / rowsPerPage);
  
  if (page < 1) page = 1;
  if (page > totalPages) page = totalPages;
  
  currentPage = page;
  
  // Hide all rows
  allRows.forEach(row => row.style.display = 'none');
  
  // Show only rows for current page
  const start = (page - 1) * rowsPerPage;
  const end = start + rowsPerPage;
  
  for (let i = start; i < end && i < allRows.length; i++) {
    allRows[i].style.display = '';
  }
  
  // Update pagination controls
  document.getElementById('pageInfo').textContent = `Page ${page} of ${totalPages}`;
  document.getElementById('prevBtn').disabled = (page === 1);
  document.getElementById('nextBtn').disabled = (page === totalPages);
  
  // Re-attach status button listeners after pagination
  attachStatusButtonListeners();
}

function previousPage() {
  showPage(currentPage - 1);
}

function nextPage() {
  showPage(currentPage + 1);
}

// Initialize pagination on page load
document.addEventListener('DOMContentLoaded', function() {
  initPagination();
});

// Re-initialize pagination after search
const originalSearchInput = document.getElementById('searchInput');
if (originalSearchInput) {
  originalSearchInput.addEventListener('input', function() {
    setTimeout(() => {
      initPagination();
      attachStatusButtonListeners();
    }, 100);
  });
}
</script>

</body>
</html>
