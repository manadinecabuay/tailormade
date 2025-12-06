<?php
// admin_qualityCheck.php (fixed)
// Move processing BEFORE any HTML output to avoid JSON parse / network errors

error_reporting(E_ALL);
ini_set('display_errors', 1);

include('connection.php');
require 'admin_session.php'; // must come before processing so we can get $supervisor_id

// Ensure supervisor id (from session)
$supervisor_id = $_SESSION['supervisor_id'] ?? null;

// -------------------------------
// AJAX: Handle quality update POST
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_quality') {
    // Return JSON only
    header('Content-Type: application/json');

    try {
        $employee_id = intval($_POST['employee_id'] ?? 0);
        $quality_check = intval($_POST['quality_check'] ?? 0);
        $check_date = $_POST['check_date'] ?? date('Y-m-d');

        if ($employee_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid employee ID.']);
            exit;
        }

        // Fetch daily record (input_value and created_at)
        $getInput = $conn->prepare("SELECT input_value, created_at FROM employee_daily_records WHERE employee_id = ? AND work_date = ? LIMIT 1");
        $getInput->bind_param("is", $employee_id, $check_date);
        $getInput->execute();
        $inputRes = $getInput->get_result()->fetch_assoc();

        $inputVal = intval($inputRes['input_value'] ?? 0);
        $inputSubmittedAt = $inputRes['created_at'] ?? null;

        // If no daily record, fallback to employees table input
        if ($inputVal === 0) {
            $getInput2 = $conn->prepare("SELECT input FROM employees WHERE id = ? LIMIT 1");
            $getInput2->bind_param("i", $employee_id);
            $getInput2->execute();
            $inputRes2 = $getInput2->get_result()->fetch_assoc();
            $inputVal = intval($inputRes2['input'] ?? 0);
            // If fallback came from employees table, we don't have created_at for daily record
            // That means editing window check based on created_at can't be enforced here.
        }

        // Validation: Employee must have input before quality check (except when both zero)
        if ($inputVal === 0 && $quality_check > 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid: This employee has no recorded input for this date.']);
            exit;
        }

        // Validation: within 7 days if there's a daily record timestamp
        if (!empty($inputSubmittedAt)) {
            $submissionDate = new DateTime($inputSubmittedAt);
            $currentDate = new DateTime();
            $daysDifference = (int)$currentDate->diff($submissionDate)->days;

            if ($daysDifference > 7) {
                $submissionDateFormatted = $submissionDate->format('M d, Y');
                echo json_encode([
                    'success' => false,
                    'message' => "Cannot edit quality check. The 7-day editing window has expired. Employee submitted input on {$submissionDateFormatted} ({$daysDifference} days ago)."
                ]);
                exit;
            }
        }

        // Validation: quality_check bounds
        if ($quality_check < 0) {
            echo json_encode(['success' => false, 'message' => 'Quality check value cannot be negative.']);
            exit;
        }
        if ($quality_check > $inputVal) {
            echo json_encode(['success' => false, 'message' => "Quality check value ({$quality_check}) cannot be higher than employee input ({$inputVal})."]);
            exit;
        }

        // Compute goods and defects
        $goods = $quality_check;
        $defects = max(0, $inputVal - $quality_check);

        // Insert or update quality_check_logs (unique_employee_date exists)
        $checked_by = $supervisor_id ?? null;

        $logStmt = $conn->prepare("
            INSERT INTO quality_check_logs (employee_id, check_date, input_value, quality_check, goods, defects, checked_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                input_value = VALUES(input_value),
                quality_check = VALUES(quality_check),
                goods = VALUES(goods),
                defects = VALUES(defects),
                checked_by = VALUES(checked_by),
                updated_at = NOW()
        ");
        // bind_param types: i s i i i i i  => "isiiiii"
        $logStmt->bind_param("isiiiii", $employee_id, $check_date, $inputVal, $quality_check, $goods, $defects, $checked_by);

        if (!$logStmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database error (logging): ' . $logStmt->error]);
            exit;
        }

        // Update employees table (current/latest values)
        $updateEmp = $conn->prepare("UPDATE employees SET quality_check = ?, defects = ?, output = ?, updated_at = NOW() WHERE id = ?");
        $output = $goods;
        $updateEmp->bind_param("iiii", $quality_check, $defects, $output, $employee_id);
        if (!$updateEmp->execute()) {
            // Not fatal for user but return warning
            echo json_encode(['success' => false, 'message' => 'Failed to update employee record: ' . $updateEmp->error]);
            exit;
        }

        // ✅ UPDATE PAYROLL TABLE
        // Aggregate data from employee_daily_records and quality_check_logs
        // and insert/update into payroll table
        try {
            // Get employee details
            $empDetails = $conn->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
            $empDetails->bind_param("i", $employee_id);
            $empDetails->execute();
            $empResult = $empDetails->get_result()->fetch_assoc();
            
            if ($empResult) {
                $first_name = $empResult['first_name'];
                $last_name = $empResult['last_name'];
                
                // Aggregate overall data for this employee
                $aggregateSQL = "
                    SELECT 
                        COALESCE(SUM(edr.input_value), 0) as total_input,
                        COALESCE(SUM(qcl.goods), 0) as total_output,
                        COALESCE(SUM(qcl.defects), 0) as total_defects
                    FROM employees e
                    LEFT JOIN employee_daily_records edr ON e.id = edr.employee_id
                    LEFT JOIN quality_check_logs qcl ON e.id = qcl.employee_id AND edr.work_date = qcl.check_date
                    WHERE e.id = ?
                ";
                
                $aggStmt = $conn->prepare($aggregateSQL);
                $aggStmt->bind_param("i", $employee_id);
                $aggStmt->execute();
                $aggData = $aggStmt->get_result()->fetch_assoc();
                
                $overall_input = intval($aggData['total_input'] ?? 0);
                $overall_output = intval($aggData['total_output'] ?? 0);
                $overall_defects = intval($aggData['total_defects'] ?? 0);
                
                // Get price_per_piece (default to 0 if not set)
                $price_per_piece = 0.00;
                $total_salary = $overall_output * $price_per_piece;
                
                // Insert or update payroll table
                $payrollSQL = "
                    INSERT INTO payroll (
                        employee_id, first_name, last_name,
                        overall_input, overall_output, overall_defects,
                        price_per_piece, total_salary, payroll_date, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'pending')
                    ON DUPLICATE KEY UPDATE
                        overall_input = VALUES(overall_input),
                        overall_output = VALUES(overall_output),
                        overall_defects = VALUES(overall_defects),
                        price_per_piece = VALUES(price_per_piece),
                        total_salary = VALUES(total_salary)
                ";
                
                $payrollStmt = $conn->prepare($payrollSQL);
                $payrollStmt->bind_param(
                    "issiidd",
                    $employee_id, $first_name, $last_name,
                    $overall_input, $overall_output, $overall_defects,
                    $price_per_piece, $total_salary
                );
                $payrollStmt->execute();
            }
        } catch (Exception $payrollEx) {
            // Log error but don't fail the quality check update
            error_log("Payroll update error: " . $payrollEx->getMessage());
        }

        // Success
        echo json_encode([
            'success' => true,
            'goods' => $goods,
            'defects' => $defects,
            'output' => $output,
            'message' => "Quality check logged successfully for {$check_date}."
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------
// If not AJAX POST update, continue to page rendering
// -------------------------------

// Check if there are active orders in the system
include_once 'reset_for_new_order.php';
$hasActiveOrders = hasActiveOrders($conn);

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
$logo_path = 'tailor.jpg';
$logo_sql = "SELECT logo_path FROM business_info LIMIT 1";
$logo_result = $conn->query($logo_sql);
if ($logo_result && $logo_result->num_rows > 0) {
    $logo_row = $logo_result->fetch_assoc();
    if (!empty($logo_row['logo_path']) && file_exists($logo_row['logo_path'])) {
        $logo_path = $logo_row['logo_path'];
    }
}

// Handle GET requests for daily records, edit window check, and AJAX employees list
if (isset($_GET['get_daily_records']) && isset($_GET['employee_id'])) {
    header('Content-Type: application/json');

    $employee_id = intval($_GET['employee_id']);

    // Fetch daily records for the employee
    $sql = "
        SELECT 
            work_date,
            input_value,
            created_at
        FROM employee_daily_records
        WHERE employee_id = ?
        ORDER BY work_date DESC
        LIMIT 30
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $records = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $records[] = $row;
        }
    }

    echo json_encode(['success' => true, 'records' => $records]);
    exit;
}

// Check edit allowed (within 7 days)
if (isset($_GET['check_edit_allowed']) && isset($_GET['date'])) {
    header('Content-Type: application/json');

    $check_date = $_GET['date'];

    $sql = "
        SELECT 
            e.id,
            e.first_name,
            e.last_name,
            edr.input_value,
            edr.created_at,
            DATEDIFF(NOW(), edr.created_at) as days_since_submission
        FROM employees e
        INNER JOIN employee_daily_records edr ON e.id = edr.employee_id
        WHERE edr.work_date = ? AND e.status = 'active'
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $check_date);
    $stmt->execute();
    $res = $stmt->get_result();

    $canEdit = true;
    $expiredEmployees = [];

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if ($row['days_since_submission'] > 7) {
                $canEdit = false;
                $expiredEmployees[] = [
                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                    'days' => $row['days_since_submission'],
                    'submitted_at' => $row['created_at']
                ];
            }
        }
    }

    echo json_encode(['success' => true, 'canEdit' => $canEdit, 'expiredEmployees' => $expiredEmployees]);
    exit;
}

// AJAX employees list for refresh
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');

    $selectedDate = $_GET['date'] ?? date('Y-m-d');

    $employees = [];

    $sql = "
        SELECT 
            e.id,
            e.first_name,
            e.last_name,
            COALESCE(edr.input_value, e.input, 0) as input,
            COALESCE(qcl.quality_check, e.quality_check, 0) as quality_check,
            COALESCE(qcl.goods, e.output, 0) as output,
            COALESCE(qcl.defects, e.defects, 0) as defects
        FROM employees e
        LEFT JOIN employee_daily_records edr ON e.id = edr.employee_id AND edr.work_date = ?
        LEFT JOIN quality_check_logs qcl ON e.id = qcl.employee_id AND qcl.check_date = ?
        WHERE e.status = 'active'
        ORDER BY e.id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $selectedDate, $selectedDate);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $employees[] = $row;
        }
    }

    echo json_encode(['success' => true, 'employees' => $employees]);
    exit;
}

// PAGE: normal rendering below
$selectedDate = $_GET['date'] ?? date('Y-m-d');
if ($selectedDate > date('Y-m-d')) $selectedDate = date('Y-m-d');

$search_query = $_GET['search'] ?? '';

// Calculate the week range based on selected date
$selected_date_obj = new DateTime($selectedDate);
$day_of_week = $selected_date_obj->format('N'); // 1 (Monday) to 7 (Sunday)

// Calculate Monday of the selected week
$week_start = clone $selected_date_obj;
$week_start->modify('-' . ($day_of_week - 1) . ' days');

// Calculate Sunday of the selected week
$week_end = clone $week_start;
$week_end->modify('+6 days');

$week_start_str = $week_start->format('Y-m-d');
$week_end_str = $week_end->format('Y-m-d');

$employees = [];
$sql = "
    SELECT 
        e.id,
        e.first_name,
        e.last_name,
        COALESCE(edr.input_value, e.input, 0) as input,
        COALESCE(qcl.quality_check, e.quality_check, 0) as quality_check,
        COALESCE(qcl.goods, e.output, 0) as output,
        COALESCE(qcl.defects, e.defects, 0) as defects,
        e.updated_at
    FROM employees e
    LEFT JOIN employee_daily_records edr ON e.id = edr.employee_id AND edr.work_date = ?
    LEFT JOIN quality_check_logs qcl ON e.id = qcl.employee_id AND qcl.check_date = ?
    WHERE e.status = 'active'
    ORDER BY e.id ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssii", $selectedDate, $selectedDate, $employees_per_page, $offset);
$stmt->execute();
$res = $stmt->get_result();
if ($res) $employees = $res->fetch_all(MYSQLI_ASSOC);

// Calculate overall goods and defects across ALL employees and ALL their daily records

$overall_sql = "
    SELECT 
        SUM(COALESCE(edr.input_value, 0)) AS total_input,
        SUM(COALESCE(qcl.quality_check, 0)) AS total_goods,
        SUM(COALESCE(qcl.defects, 0)) AS total_defects
    FROM employees e
    LEFT JOIN employee_daily_records edr ON e.id = edr.employee_id
    LEFT JOIN quality_check_logs qcl ON e.id = qcl.employee_id
    WHERE e.status = 'active'
";

$overall_result = $conn->query($overall_sql);
$overall_data = $overall_result ? $overall_result->fetch_assoc() : [
    'total_input' => 0,
    'total_goods' => 0,
    'total_defects' => 0
];

$totalInput   = intval($overall_data['total_input']);
$totalGoods   = intval($overall_data['total_goods']);
$totalDefects = intval($overall_data['total_defects']);


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quality Check - Supervisor</title>

<?php include('theme_loader.php'); ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ... keep your CSS (unchanged) ... */
* { 
  margin: 0; 
  padding: 0; 
  box-sizing: border-box; 
  font-family: 'Poppins', sans-serif; 
}
/* trimmed in this snippet for brevity — include your full CSS here */

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

.filter-section {
  display: flex;
  align-items: center;
  gap: 31px;
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

.date-input {
  padding: 12px 18px;
  border-radius: 10px;
  border: 2px solid #e0e0e0;
  background: #fff;
  color: #333;
  font-weight: 500;
  font-size: 14px;
  transition: all 0.3s ease;
  cursor: pointer;
}

.date-input:focus {
  outline: none;
  border-color: var(--theme-bg);
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.week-selector {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.week-selector label {
  padding: 10px 18px;
  border-radius: 10px;
  transition: all 0.3s ease;
  background: #fff;
  color: var(--theme-bg);
  border: 2px solid #e0e0e0;
  font-weight: 600;
  cursor: pointer;
  font-size: 14px;
  text-transform: uppercase;
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

.table-container {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  padding: 30px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  overflow-x: auto;
  transition: all 0.3s ease;
  margin-bottom: 25px;
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

.quality-input {
  width: 80px;
  padding: 8px 12px;
  border: 2px solid #e0e0e0;
  border-radius: 8px;
  text-align: center;
  font-weight: 600;
  color: #333;
  background: #fff;
  transition: all 0.3s ease;
}

.quality-input:focus {
  outline: none;
  border-color: var(--theme-bg);
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.quality-input:disabled {
  background: #f5f5f5;
  cursor: not-allowed;
}

.edit-btn {
  background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
  color: var(--theme-font);
  border: none;
  padding: 12px 30px;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.3s ease;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-top: 20px;
}

.edit-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.stats-container {
  background: rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  padding: 25px 30px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-item {
  text-align: center;
}

.stat-label {
  font-size: 14px;
  color: var(--theme-bg);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Modal */
.modal {
  display: none;
  position: fixed;
  z-index: 9999;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  justify-content: center;
  align-items: center;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-content {
  background: #fff;
  padding: 40px;
  border-radius: 20px;
  text-align: center;
  width: 400px;
  max-width: 90%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from { transform: translateY(-50px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}

@keyframes slideInRight {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

@keyframes slideOutRight {
  from { transform: translateX(0); opacity: 1; }
  to { transform: translateX(100%); opacity: 0; }
}

@keyframes fadeOut {
  from { opacity: 1; }
  to { opacity: 0; }
}

.modal-content h3 {
  color: var(--theme-bg);
  margin-bottom: 20px;
  font-size: 24px;
  font-weight: 700;
}

.modal-content p {
  color: #666;
  margin-bottom: 25px;
  line-height: 1.6;
}

.modal-buttons {
  display: flex;
  gap: 15px;
  justify-content: center;
}

.modal-btn {
  padding: 12px 30px;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  font-size: 14px;
  text-transform: uppercase;
}

.btn-success {
  background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
  color: #fff;
}

.btn-success:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
}

.btn-cancel {
  background: #f8f9fa;
  color: #333;
  border: 2px solid #e0e0e0;
}

.btn-cancel:hover {
  background: #e9ecef;
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

  #refreshIndicator {
    top: 70px !important;
    right: 10px !important;
    padding: 8px 15px;
    font-size: 13px;
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

  .filter-section > div {
    justify-content: center !important;
  }

  .search-wrapper {
    max-width: 100%;
  }

  .date-input,
  .clear-btn {
    width: 100%;
  }

  .week-selector {
    justify-content: center;
  }

  .week-selector label {
    flex: 1;
    text-align: center;
    padding: 10px 12px;
    font-size: 13px;
  }

  .stats-container {
    flex-direction: column;
    gap: 20px;
    padding: 20px;
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

  .table th {
    font-size: 10px;
  }

  .quality-input {
    width: 60px;
    padding: 6px 8px;
    font-size: 12px;
  }

  .edit-btn {
    padding: 10px 20px;
    font-size: 13px;
  }

  #paginationControls {
    flex-direction: column;
    gap: 12px;
  }

  #paginationControls button {
    width: 100%;
    padding: 12px !important;
  }

  .modal-content {
    width: 95%;
    padding: 30px 20px;
  }

  .modal-content h3 {
    font-size: 20px;
  }

  .modal-buttons {
    flex-direction: column;
    width: 100%;
  }

  .modal-btn {
    width: 100%;
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

  #refreshIndicator {
    font-size: 12px;
    padding: 6px 12px;
  }

  .filter-section {
    padding: 12px;
    gap: 10px;
  }

  .filter-section h3 {
    font-size: 16px !important;
  }

  .filter-section > div span {
    font-size: 12px !important;
  }

  .filter-section > div span[id*="total"] {
    padding: 4px 12px !important;
    font-size: 14px !important;
  }

  .search-bar,
  .date-input,
  .clear-btn {
    padding: 10px 14px;
    font-size: 13px;
  }

  .week-selector label {
    padding: 8px 10px;
    font-size: 12px;
  }

  .table-container {
    padding: 10px;
    border-radius: 12px;
  }

  .table {
    min-width: 650px;
  }

  .table th,
  .table td {
    padding: 8px 4px;
    font-size: 10px;
  }

  .table th {
    font-size: 9px;
  }

  .quality-input {
    width: 50px;
    padding: 5px 6px;
    font-size: 11px;
  }

  .edit-btn {
    padding: 8px 16px;
    font-size: 12px;
  }

  .stat-label {
    font-size: 12px;
  }

  .stat-value {
    font-size: 24px;
  }

  .modal-content {
    padding: 25px 15px;
  }

  .modal-content h3 {
    font-size: 18px;
  }

  .modal-content p {
    font-size: 13px;
  }

  .modal-btn {
    padding: 10px 20px;
    font-size: 13px;
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
    <a href="admin_employees.php"><i class="fa-solid fa-users"></i> Employees</a>
    <a href="admin_qualityCheck.php" class="active"><i class="fa-solid fa-check"></i> Quality Check</a>
  </div>
  <div class="bottom">
    <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</div>

<div class="main">
  <div class="header">
    <div class="header-left">
      <h1><i class="fa-solid fa-check-circle"></i> Quality Check</h1>
      <p>View and evaluate all employee inputs, then update each garment's quality status.</p>
    </div>
    <div class="header-right">
      <span><?php echo htmlspecialchars($supervisor_name); ?></span>
    </div>
  </div>

  <!-- Auto-refresh indicator -->
  <div id="refreshIndicator" style="position: fixed; top: 20px; right: 20px; background: rgba(102, 126, 234, 0.9); color: white; padding: 10px 20px; border-radius: 10px; display: none; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.2); animation: slideIn 0.3s ease;">
    <i class="fa-solid fa-sync fa-spin"></i> Refreshing data...
  </div>

  <!-- Hidden date input for JavaScript reference -->
  <input type="hidden" id="dateFilter" value="<?php echo htmlspecialchars($selectedDate); ?>">

  <div class="filter-section" style="margin-top: 15px;">
    <div style="background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); color: white; padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 14px; white-space: nowrap;">
      <i class="fa-solid fa-calendar-week"></i> Week: <?php echo $week_start->format('M d'); ?> - <?php echo $week_end->format('M d, Y'); ?>
    </div>
    
    <div class="search-wrapper">
      <input type="text" id="searchInput" class="search-bar" placeholder="Search employee by name or ID...">
    </div>
    <button class="clear-btn" onclick="clearSearch()">Clear</button>
    
    <?php if ($hasActiveOrders): ?>
    <button class="clear-btn" id="editBtnTop">
      <i class="fa-solid fa-pen"></i> Edit Quality Check
    </button>
    <button class="clear-btn" id="saveBtnTop" style="display:none; background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
      <i class="fa-solid fa-save"></i> Save Changes
    </button>
    <button class="clear-btn" id="cancelBtnTop" style="display:none; background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);">
      <i class="fa-solid fa-times"></i> Cancel
    </button>
    <?php endif; ?>
  </div>

<?php
// Display "No Active Order" banner if no active orders exist
if (!$hasActiveOrders): ?>
  <div class="table-container" style="text-align: center; padding: 40px; margin-bottom: 25px; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 2px solid #ffc107;">
    <i class="fa-solid fa-exclamation-triangle" style="font-size: 64px; color: #ff9800; margin-bottom: 20px; display: block;"></i>
    <h3 style="color: #856404; font-size: 24px; margin-bottom: 15px; font-weight: 700;">No Active Orders</h3>
    <p style="color: #856404; font-size: 16px; line-height: 1.6; max-width: 600px; margin: 0 auto;">
      There are currently no active orders in the system. Quality check data has been cleared. 
      Once a new order is confirmed, you will be able to perform quality checks again.
    </p>
  </div>
<?php endif; ?>

<?php
// Fetch only employees who have submitted records during this week
$employees_list = [];
if (!empty($search_query)) {
    $emp_sql = "SELECT DISTINCT e.id, e.first_name, e.last_name 
                FROM employees e
                INNER JOIN employee_daily_records edr ON e.id = edr.employee_id
                WHERE e.status = 'active' 
                AND edr.work_date BETWEEN ? AND ?
                AND (e.id = ? OR e.first_name LIKE ? OR e.last_name LIKE ?)
                ORDER BY e.id ASC";
    $stmt = $conn->prepare($emp_sql);
    $search_like = "%{$search_query}%";
    $stmt->bind_param("ssiss", $week_start_str, $week_end_str, $search_query, $search_like, $search_like);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $employees_list = $result->fetch_all(MYSQLI_ASSOC);
} else {
    $emp_sql = "SELECT DISTINCT e.id, e.first_name, e.last_name 
                FROM employees e
                INNER JOIN employee_daily_records edr ON e.id = edr.employee_id
                WHERE e.status = 'active' 
                AND edr.work_date BETWEEN ? AND ?
                ORDER BY e.id ASC";
    $stmt = $conn->prepare($emp_sql);
    $stmt->bind_param("ss", $week_start_str, $week_end_str);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $employees_list = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<div id="employeeTablesContainer">
<?php if (count($employees_list) > 0): ?>
  <?php foreach ($employees_list as $employee): ?>
    <div class="table-container employee-table-section" style="margin-bottom: 30px;" data-employee-id="<?php echo $employee['id']; ?>">
      <div style="background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); color: white; padding: 15px 25px; border-radius: 15px 15px 0 0; margin-bottom: 0;">
        <h3 style="margin: 0; font-size: 18px; font-weight: 700;">
          <i class="fa-solid fa-user-circle"></i> 
          Employee ID: <?php echo $employee['id']; ?> | 
          <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
        </h3>
      </div>

      <?php
      // Fetch records for this employee for the selected week only (Monday to Sunday)
      $daily_records_sql = "
          SELECT 
              edr.work_date,
              edr.input_value,
              edr.created_at,
              COALESCE(qcl.quality_check, 0) as quality_check,
              COALESCE(qcl.goods, 0) as goods,
              COALESCE(qcl.defects, 0) as defects
          FROM employee_daily_records edr
          LEFT JOIN quality_check_logs qcl ON edr.employee_id = qcl.employee_id AND edr.work_date = qcl.check_date
          WHERE edr.employee_id = ?
          AND edr.work_date BETWEEN ? AND ?
          ORDER BY edr.work_date ASC
      ";
      $stmt = $conn->prepare($daily_records_sql);
      $stmt->bind_param("iss", $employee['id'], $week_start_str, $week_end_str);
      $stmt->execute();
      $records_result = $stmt->get_result();
      $daily_records = $records_result->fetch_all(MYSQLI_ASSOC);
      ?>

      <table class="table employee-table" style="margin-top: 0;">
        <thead>
          <tr>
            <th>Day</th>
            <th>Date Submitted</th>
            <th>Submitted Input</th>
            <th>Quality Check</th>
            <th>Goods</th>
            <th>Defects</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($daily_records) > 0): ?>
            <?php 
            $day_counter = count($daily_records);
            foreach ($daily_records as $record): 
              // Only show goods and defects if quality check has been saved (not 0)
              $has_quality_check = $record['quality_check'] > 0;
              $goods = $has_quality_check ? $record['quality_check'] : '-';
              $defects = $has_quality_check ? max(0, $record['input_value'] - $record['quality_check']) : '-';
            ?>
              <tr>
                <td style="font-weight: 700; text-align: center; color: var(--theme-bg);">
                  <?php 
                  $date = new DateTime($record['work_date']);
                  echo $date->format('l'); // Monday, Tuesday, Wednesday, etc.
                  ?>
                </td>
                <td style="text-align: center; font-weight: 600;">
                  <?php 
                  echo $date->format('M d, Y');
                  ?>
                </td>
                <td style="text-align: center; font-weight: 700; color: var(--theme-bg); fon16px;">
                  <?php echo $record['input_value']; ?>
                </td>
                <td style="text-align: center;">
                  <input type="number" 
                         class="quality-input-daily" 
                         data-employee-id="<?php echo $employee['id']; ?>"
                         data-work-date="<?php echo $record['work_date']; ?>"
                         data-input-value="<?php echo $record['input_value']; ?>"
                         data-original-value="<?php echo $record['quality_check']; ?>"
                         value="<?php echo $record['quality_check']; ?>" 
                         min="0"
                         max="<?php echo $record['input_value']; ?>"
                         disabled
                         style="width: 100px; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; text-align: center; font-weight: 700; font-size: 15px; cursor: not-allowed;">
                </td>
                <td class="goods-cell" style="text-align: center; color: #28a745; font-weight: 700; font-size: 16px;">
                  <?php echo $goods; ?>
                </td>
                <td class="defects-cell" style="text-align: center; color: #dc3545; font-weight: 700; font-size: 16px;">
                  <?php echo $defects; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align:center; padding:30px; color:#999; font-size: 14px;">
                <i class="fa-solid fa-inbox" style="font-size: 32px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                No daily records found for this employee.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <div class="table-container" style="text-align: center; padding: 60px;">
    <i class="fa-solid fa-user-slash" style="font-size: 64px; color: #ccc; margin-bottom: 20px; display: block;"></i>
    <h3 style="color: #999; font-size: 20px; margin-bottom: 10px;">No Employees Found</h3>
    <p style="color: #bbb; font-size: 14px;">
      <?php if (!empty($search_query)): ?>
        No employees found matching "<?php echo htmlspecialchars($search_query); ?>". Try a different search term.
      <?php else: ?>
        No active employees in the system.
      <?php endif; ?>
    </p>
  </div>
<?php endif; ?>
</div>
</div>

<!-- Modals (confirm, success, error, cancel, edit) -->
<div id="confirmModal" class="modal">
  <div class="modal-content">
    <h3>Confirm Quality Check Update</h3>
    <p id="confirmMessage"></p>
    <div class="modal-buttons">
      <button class="modal-btn btn-success" id="confirmYes">Confirm</button>
      <button class="modal-btn btn-cancel" id="confirmNo">Cancel</button>
    </div>
  </div>
</div>

<div id="successModal" class="modal">
  <div class="modal-content">
    <div style="font-size: 60px; color: #28a745; margin-bottom: 20px;">✅</div>
    <h3 style="color: #28a745;">Success!</h3>
    <p id="successMessage"></p>
    <div class="modal-buttons">
      <button class="modal-btn btn-success" onclick="closeModal('successModal')">OK</button>
    </div>
  </div>
</div>

<div id="errorModal" class="modal">
  <div class="modal-content">
    <div style="font-size: 60px; color: #dc3545; margin-bottom: 20px;">❌</div>
    <h3 style="color: #dc3545;">Error!</h3>
    <p id="errorMessage" style="color: #666; line-height: 1.6;"></p>
    <div class="modal-buttons">
      <button class="modal-btn btn-cancel" onclick="closeModal('errorModal')" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">OK</button>
    </div>
  </div>
</div>

<div id="cancelConfirmModal" class="modal">
  <div class="modal-content">
    <div style="font-size: 60px; color: #ff9800; margin-bottom: 20px;">⚠️</div>
    <h3 style="color: #ff9800;">Cancel Changes?</h3>
    <p style="color: #666; line-height: 1.6;">Are you sure you want to cancel?All unsaved changes will be lost.</p>
    <div class="modal-buttons">
      <button class="modal-btn btn-cancel" id="confirmCancelYes" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
        <i class="fa-solid fa-times"></i> Yes, Cancel
      </button>
      <button class="modal-btn btn-success" id="confirmCancelNo">
        <i class="fa-solid fa-arrow-left"></i> No, Go Back
      </button>
    </div>
  </div>
</div>

<div id="editQualityModal" class="modal">
  <div class="modal-content" style="max-width: 500px;">
    <span class="close" onclick="closeEditModal()" style="cursor:pointer; position:absolute; right:18px; top:12px; font-size:24px;">&times;</span>
    <div style="text-align: center; margin-bottom: 25px;">
      <div style="width: 70px; height: 70px; margin: 0 auto 15px; background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);">
        <i class="fa-solid fa-clipboard-check" style="font-size: 35px; color: white;"></i>
      </div>
      <h3 style="margin: 0; color: #333; font-size: 22px; font-weight: 700;">Edit Quality Check</h3>
      <p style="color: #666; font-size: 14px; margin-top: 5px;" id="editEmployeeName"></p>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
        <span style="color: #666; font-weight: 500;">Employee Input:</span>
        <span style="color: #333; font-weight: 700; font-size: 18px;" id="editEmployeeInput">0</span>
      </div>

      <div style="margin-bottom: 15px;">
        <label style="display: block; color: #666; font-weight: 600; margin-bottom: 8px;">
          <i class="fa-solid fa-check-circle" style="color: #28a745; margin-right: 5px;"></i>
          Quality Check (Goods):
        </label>
        <input type="number" 
               id="editQualityInput" 
               min="0" 
               style="width: 100%; padding: 12px; border: 2px solid #dee2e6; border-radius: 8px; font-size: 16px; font-weight: 600; text-align: center;"
               oninput="calculateDefects()">
        <small style="color: #999; font-size: 12px; display: block; margin-top: 5px;">
          Enter the number of good quality items
        </small>
      </div>

      <div style="display: flex; justify-content: space-between; padding-top: 15px; border-top: 2px solid #dee2e6;">
        <span style="color: #666; font-weight: 500;">
          <i class="fa-solid fa-times-circle" style="color: #dc3545; margin-right: 5px;"></i>
          Defects:
        </span>
        <span style="color: #dc3545; font-weight: 700; font-size: 18px;" id="editDefectsDisplay">0</span>
      </div>
    </div>

    <div class="modal-buttons" style="display: flex; gap: 10px;">
      <button onclick="saveQualityEdit()" class="modal-btn btn-success" style="flex: 1; padding: 12px; font-size: 15px;">
        <i class="fa-solid fa-save" style="margin-right: 5px;"></i>
        Save Changes
      </button>
      <button onclick="closeEditModal()" class="modal-btn btn-cancel" style="flex: 1; padding: 12px; font-size: 15px;">
        <i class="fa-solid fa-times" style="margin-right: 5px;"></i>
        Cancel
      </button>
    </div>
  </div>
</div>

<script>
// Helper UI functions
function showModal(modalId, message) {
  if (modalId === 'errorModal') {
    document.getElementById('errorMessage').innerHTML = message;
  } else if (modalId === 'successModal') {
    document.getElementById('successMessage').innerHTML = message;
  } else if (modalId === 'confirmModal') {
    document.getElementById('confirmMessage').innerHTML = message;
  }
  document.getElementById(modalId).style.display = 'flex';
}
function closeModal(modalId) {
  document.getElementById(modalId).style.display = 'none';
}

// Mobile sidebar toggle
function toggleMobileSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.overlay');
  sidebar.classList.toggle('mobile-open');
  overlay.classList.toggle('active');
}

// Basic element refs
const searchInput = document.getElementById('searchInput');
const editBtnTop = document.getElementById('editBtnTop');
const saveBtnTop = document.getElementById('saveBtnTop');
const cancelBtnTop = document.getElementById('cancelBtnTop');

// Store pending update for single-row confirm UX
let pendingUpdate = null;

// Search functionality
searchInput.addEventListener('keyup', function() {
  const filter = this.value.toLowerCase();
  const employeeSections = document.querySelectorAll('.employee-table-section');

  employeeSections.forEach(section => {
    const headerText = section.querySelector('h3').textContent.toLowerCase();
    if (headerText.includes(filter)) section.style.display = '';
    else section.style.display = 'none';
  });
});

function clearSearch() {
  searchInput.value = '';
  const employeeSections = document.querySelectorAll('.employee-table-section');
  employeeSections.forEach(section => section.style.display = '');
}

// Bulk edit mode (only if buttons exist - when there are active orders)
if (editBtnTop) {
editBtnTop.addEventListener('click', () => {
  const dailyInputs = document.querySelectorAll('.quality-input-daily');

  if (dailyInputs.length === 0) {
    showModal('errorModal', `<p style="font-size:16px;">No employee records found.</p><p style="font-size:14px;color:#666;">Please ensure employees have submitted their daily inputs.</p>`);
    return;
  }

  // Enable editing
  dailyInputs.forEach(input => {
    input.disabled = false;
    input.style.background = '#fffbea';
    input.style.borderColor = '#f59e0b';
    input.style.cursor = 'text';
  });

  editBtnTop.style.display = 'none';
  saveBtnTop.style.display = 'inline-block';
  cancelBtnTop.style.display = 'inline-block';

  showModal('successModal', 'Edit mode enabled! You can now modify quality check values for all employees.');
});
}

// Cancel bulk edit (only if buttons exist)
if (cancelBtnTop) {
document.getElementById('confirmCancelYes').addEventListener('click', () => {
  const dailyInputs = document.querySelectorAll('.quality-input-daily');
  dailyInputs.forEach(input => {
    input.value = input.dataset.originalValue;
    input.disabled = true;
    input.style.background = '';
    input.style.borderColor = '#e0e0e0';
    input.style.cursor = 'not-allowed';
  });
  saveBtnTop.style.display = 'none';
  cancelBtnTop.style.display = 'none';
  editBtnTop.style.display = 'inline-block';
  closeModal('cancelConfirmModal');
  showModal('successModal', 'Edit mode cancelled. All changes have been reverted.');
});
document.getElementById('confirmCancelNo').addEventListener('click', () => {
  closeModal('cancelConfirmModal');
});
}

// Save bulk edits (only if buttons exist)
if (saveBtnTop) {
saveBtnTop.addEventListener('click', async () => {
  const dailyInputs = document.querySelectorAll('.quality-input-daily');

  const changes = [];
  dailyInputs.forEach(input => {
    const originalValue = String(input.dataset.originalValue);
    const newValue = String(input.value);
    if (newValue !== originalValue) {
      changes.push({
        input: input,
        employeeId: input.dataset.employeeId,
        workDate: input.dataset.workDate,
        inputValue: parseInt(input.dataset.inputValue),
        qualityCheck: parseInt(input.value)
      });
    }
  });

  if (changes.length === 0) {
    dailyInputs.forEach(input => {
      input.disabled = true;
      input.style.background = '';
      input.style.borderColor = '#e0e0e0';
      input.style.cursor = 'not-allowed';
    });
    saveBtnTop.style.display = 'none';
    cancelBtnTop.style.display = 'none';
    editBtnTop.style.display = 'inline-block';
    showModal('successModal', 'No changes were made.');
    return;
  }

  saveBtnTop.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
  saveBtnTop.disabled = true;

  let successCount = 0, errorCount = 0;

  for (const change of changes) {
    try {
      const formData = new FormData();
      formData.append('action', 'update_quality');
      formData.append('employee_id', change.employeeId);
      formData.append('quality_check', change.qualityCheck);
      formData.append('check_date', change.workDate);

      const resp = await fetch('admin_qualityCheck.php', { method: 'POST', body: formData });
      const data = await resp.json();

      if (data.success) {
        successCount++;
        change.input.dataset.originalValue = String(change.qualityCheck);
        const row = change.input.closest('tr');
        if (row) {
          const goodsCell = row.querySelector('.goods-cell');
          const defectsCell = row.querySelector('.defects-cell');
          if (goodsCell) goodsCell.textContent = data.goods ?? change.qualityCheck;
          if (defectsCell) defectsCell.textContent = data.defects ?? (change.inputValue - change.qualityCheck);
        }
        change.input.style.borderColor = '#10b981';
        change.input.style.background = '#d1fae5';
      } else {
        errorCount++;
        change.input.value = change.input.dataset.originalValue;
        change.input.style.borderColor = '#ef4444';
        change.input.style.background = '#fee2e2';
      }
    } catch (err) {
      console.error('Save error', err);
      errorCount++;
      change.input.value = change.input.dataset.originalValue;
      change.input.style.borderColor = '#ef4444';
      change.input.style.background = '#fee2e2';
    }
  }

  // Disable inputs
  dailyInputs.forEach(input => {
    input.disabled = true;
    input.style.background = '';
    input.style.borderColor = '#e0e0e0';
    input.style.cursor = 'not-allowed';
  });

  saveBtnTop.innerHTML = '<i class="fa-solid fa-save"></i> Save Changes';
  saveBtnTop.disabled = false;
  saveBtnTop.style.display = 'none';
  cancelBtnTop.style.display = 'none';
  editBtnTop.style.display = 'inline-block';

  // Recalculate totals
  recalculateTotals();

  if (errorCount === 0) {
    showModal('successModal', `Successfully saved ${successCount} quality check(s)!`);
  } else {
    showModal('errorModal', `<p style="font-size:16px;">Saved ${successCount} quality check(s), but ${errorCount} failed.</p><p style="font-size:14px;color:#666;">Failed changes have been reverted. Please try again.</p>`);
  }
});
}

// Input validation while typing
document.addEventListener('input', function(e) {
  if (e.target.classList.contains('quality-input-daily') && !e.target.disabled) {
    const input = e.target;
    const inputValue = parseInt(input.dataset.inputValue);
    const qualityCheck = parseInt(input.value);
    if (isNaN(qualityCheck)) return;
    if (qualityCheck < 0 || qualityCheck > inputValue) {
      input.style.borderColor = '#ef4444';
      input.style.background = '#fee2e2';
    } else {
      input.style.borderColor = '#f59e0b';
      input.style.background = '#fffbea';
    }
  }
});

// Recalc totals
function recalculateTotals() {
  let totalGoods = 0, totalDefects = 0;
  document.querySelectorAll('.goods-cell').forEach(c => { 
    const val = c.textContent.trim();
    if (val !== '-') totalGoods += parseInt(val) || 0; 
  });
  document.querySelectorAll('.defects-cell').forEach(c => { 
    const val = c.textContent.trim();
    if (val !== '-') totalDefects += parseInt(val) || 0; 
  });
  document.getElementById('totalGoodsTop').textContent = totalGoods;
  document.getElementById('totalDefectsTop').textContent = totalDefects;
}

// Single-row inline change + confirm flow
// Attach change listeners to current inputs (the ones rendered)
document.querySelectorAll('.quality-input-daily').forEach(input => {
  input.addEventListener('change', function() {
    // Only handle when input was enabled
    if (input.disabled) return;

    const employeeId = input.dataset.employeeId;
    const workDate = input.dataset.workDate;
    const inputValue = parseInt(input.dataset.inputValue) || 0;
    const qualityValue = parseInt(input.value) || 0;
    const row = input.closest('tr');
    const employeeNameElem = input.closest('.employee-table-section')?.querySelector('h3');
    const employeeName = employeeNameElem ? employeeNameElem.textContent : `Employee ${employeeId}`;

    // Client-side validation
    if (qualityValue < 0 || qualityValue > inputValue) {
      // Show inline error feedback instead of modal
      input.style.borderColor = '#ef4444';
      input.style.background = '#fee2e2';
      input.value = input.dataset.originalValue;
      
      // Show temporary tooltip
      const tooltip = document.createElement('div');
      tooltip.textContent = `Value must be between 0 and ${inputValue}`;
      tooltip.style.cssText = 'position: absolute; background: #dc3545; color: white; padding: 8px 12px; border-radius: 6px; font-size: 12px; z-index: 10000; margin-top: -40px; white-space: nowrap; box-shadow: 0 4px 12px rgba(0,0,0,0.2);';
      input.parentElement.style.position = 'relative';
      input.parentElement.appendChild(tooltip);
      
      setTimeout(() => {
        tooltip.remove();
        input.style.borderColor = '#e0e0e0';
        input.style.background = '';
      }, 2000);
      return;
    }

    // Save directly without confirmation modal - smooth AJAX
    input.disabled = true;
    input.style.opacity = '0.6';
    
    const formData = new FormData();
    formData.append('action', 'update_quality');
    formData.append('employee_id', employeeId);
    formData.append('quality_check', qualityValue);
    formData.append('check_date', workDate);

    fetch('admin_qualityCheck.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        input.disabled = false;
        input.style.opacity = '1';
        
        if (data.success) {
          // Update UI smoothly
          input.dataset.originalValue = String(qualityValue);
          if (row) {
            const goodsCell = row.querySelector('.goods-cell');
            const defectsCell = row.querySelector('.defects-cell');
            if (goodsCell) goodsCell.textContent = data.goods ?? qualityValue;
            if (defectsCell) defectsCell.textContent = data.defects ?? (inputValue - qualityValue);
          }
          
          // Success feedback
          input.style.borderColor = '#28a745';
          input.style.background = '#d4edda';
          
          // Show success checkmark
          const checkmark = document.createElement('span');
          checkmark.innerHTML = '✓';
          checkmark.style.cssText = 'position: absolute; color: #28a745; font-size: 20px; font-weight: bold; margin-left: -25px; animation: fadeOut 1s ease;';
          input.parentElement.appendChild(checkmark);
          
          setTimeout(() => {
            input.style.borderColor = '#e0e0e0';
            input.style.background = '';
            checkmark.remove();
          }, 1200);
          
          recalculateTotals();
        } else {
          // Error feedback inline
          input.value = input.dataset.originalValue;
          input.style.borderColor = '#ef4444';
          input.style.background = '#fee2e2';
          
          // Show error message
          const errorMsg = document.createElement('div');
          errorMsg.textContent = data.message || 'Failed to update';
          errorMsg.style.cssText = 'position: absolute; background: #dc3545; color: white; padding: 8px 12px; border-radius: 6px; font-size: 12px; z-index: 10000; margin-top: -40px; white-space: nowrap; box-shadow: 0 4px 12px rgba(0,0,0,0.2);';
          input.parentElement.appendChild(errorMsg);
          
          setTimeout(() => {
            errorMsg.remove();
            input.style.borderColor = '#e0e0e0';
            input.style.background = '';
          }, 3000);
        }
      })
      .catch(err => {
        console.error(err);
        input.disabled = false;
        input.style.opacity = '1';
        input.value = input.dataset.originalValue;
        input.style.borderColor = '#ef4444';
        input.style.background = '#fee2e2';
        
        setTimeout(() => {
          input.style.borderColor = '#e0e0e0';
          input.style.background = '';
        }, 2000);
      });
  });
});

// Removed old confirm modal listeners - now using smooth inline AJAX

// Edit modal (detailed single employee update)
let currentEditEmployeeId = null;
let currentEditEmployeeInput = 0;

function openEditModal(employeeId, employeeName, input, currentQuality) {
  if (!input || input == 0) {
    // Show inline notification instead of modal
    const notification = document.createElement('div');
    notification.innerHTML = `<strong>${employeeName}</strong> has no input for the selected date. Quality checks can only be edited when input exists.`;
    notification.style.cssText = 'position: fixed; top: 80px; right: 20px; background: #fff3cd; color: #856404; padding: 15px 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 10000; max-width: 400px; border-left: 4px solid #ffc107; animation: slideInRight 0.3s ease;';
    document.body.appendChild(notification);
    
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease';
      setTimeout(() => notification.remove(), 300);
    }, 3000);
    return;
  }
  currentEditEmployeeId = employeeId;
  currentEditEmployeeInput = parseInt(input) || 0;
  document.getElementById('editEmployeeName').textContent = `Employee: ${employeeName}`;
  document.getElementById('editEmployeeInput').textContent = input;
  document.getElementById('editQualityInput').value = currentQuality;
  document.getElementById('editQualityInput').max = input;
  calculateDefects();
  document.getElementById('editQualityModal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editQualityModal').style.display = 'none';
  currentEditEmployeeId = null;
  currentEditEmployeeInput = 0;
}

function calculateDefects() {
  const qualityCheck = parseInt(document.getElementById('editQualityInput').value) || 0;
  const defects = Math.max(0, currentEditEmployeeInput - qualityCheck);
  document.getElementById('editDefectsDisplay').textContent = defects;
}

function saveQualityEdit() {
  const qualityCheck = parseInt(document.getElementById('editQualityInput').value) || 0;
  const inputField = document.getElementById('editQualityInput');
  
  if (qualityCheck < 0) {
    // Inline validation feedback
    inputField.style.borderColor = '#ef4444';
    inputField.style.background = '#fee2e2';
    setTimeout(() => {
      inputField.style.borderColor = '#e0e0e0';
      inputField.style.background = '';
    }, 1500);
    return;
  }
  if (qualityCheck > currentEditEmployeeInput) {
    // Inline validation feedback
    inputField.style.borderColor = '#ef4444';
    inputField.style.background = '#fee2e2';
    setTimeout(() => {
      inputField.style.borderColor = '#e0e0e0';
      inputField.style.background = '';
    }, 1500);
    return;
  }

  const selectedDate = document.getElementById('dateFilter').value;
  const formData = new FormData();
  formData.append('action', 'update_quality');
  formData.append('employee_id', currentEditEmployeeId);
  formData.append('quality_check', qualityCheck);
  formData.append('check_date', selectedDate);

  // Disable button during save
  const saveBtn = document.querySelector('#editQualityModal .btn-success');
  const originalText = saveBtn.innerHTML;
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

  fetch('admin_qualityCheck.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      saveBtn.disabled = false;
      saveBtn.innerHTML = originalText;
      
      if (data.success) {
        closeEditModal();
        
        // Show success notification
        const notification = document.createElement('div');
        notification.innerHTML = '<i class="fa-solid fa-check-circle"></i> Quality check updated successfully!';
        notification.style.cssText = 'position: fixed; top: 80px; right: 20px; background: #d4edda; color: #155724; padding: 15px 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 10000; border-left: 4px solid #28a745; animation: slideInRight 0.3s ease;';
        document.body.appendChild(notification);
        
        setTimeout(() => {
          notification.style.animation = 'slideOutRight 0.3s ease';
          setTimeout(() => notification.remove(), 300);
        }, 2000);
        
        // Update the table row if present
        document.querySelectorAll('.employee-table-section').forEach(section => {
          if (section.dataset.employeeId == currentEditEmployeeId) {
            section.querySelectorAll('.quality-input-daily').forEach(inp => {
              inp.dataset.originalValue = String(qualityCheck);
              inp.value = qualityCheck;
            });
            const goodsCell = section.querySelector('.goods-cell');
            const defectsCell = section.querySelector('.defects-cell');
            if (goodsCell) goodsCell.textContent = data.goods ?? qualityCheck;
            if (defectsCell) defectsCell.textContent = data.defects ?? 0;
          }
        });
        recalculateTotals();
      } else {
        // Show error notification
        const notification = document.createElement('div');
        notification.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> ${data.message || 'Failed to update quality check'}`;
        notification.style.cssText = 'position: fixed; top: 80px; right: 20px; background: #f8d7da; color: #721c24; padding: 15px 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 10000; border-left: 4px solid #dc3545; animation: slideInRight 0.3s ease;';
        document.body.appendChild(notification);
        
        setTimeout(() => {
          notification.style.animation = 'slideOutRight 0.3s ease';
          setTimeout(() => notification.remove(), 300);
        }, 3000);
      }
    })
    .catch(err => {
      console.error(err);
      saveBtn.disabled = false;
      saveBtn.innerHTML = originalText;
      
      // Show network error notification
      const notification = document.createElement('div');
      notification.innerHTML = '<i class="fa-solid fa-wifi"></i> Network error. Please try again.';
      notification.style.cssText = 'position: fixed; top: 80px; right: 20px; background: #f8d7da; color: #721c24; padding: 15px 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); z-index: 10000; border-left: 4px solid #dc3545; animation: slideInRight 0.3s ease;';
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    });
}

// Date reference for JavaScript (hidden input)
// The date is now managed through the week display only
// Users navigate weeks through URL parameters

// Session check
setInterval(() => {
  fetch('admin_session_check.php')
    .then(res => res.json())
    .then(data => {
      if (!data.active) {
        alert("Your session has expired or you have logged out in another tab.");
        window.location.href = "login_admins.html";
      }
    }).catch(err => console.error('Session check failed', err));
}, 15000);
</script>

</body>
</html>
