<?php
session_start();
require 'connection.php';
include 'business_function.php';

// ---------------------------
// Validate Employee ID
// ---------------------------
if (isset($_GET['id'])) {
    $employee_id = intval($_GET['id']);
} elseif (isset($_SESSION['selected_employee_id'])) {
    $employee_id = intval($_SESSION['selected_employee_id']);
} else {
    header("Location: employee_homepage.html");
    exit;
}

// ---------------------------
// Check if payroll has been saved for this employee
// ---------------------------
$payroll_check_query = "SELECT COUNT(*) as count FROM payroll WHERE employee_id = ?";
$payroll_check_stmt = $conn->prepare($payroll_check_query);
$payroll_check_stmt->bind_param("i", $employee_id);
$payroll_check_stmt->execute();
$payroll_check_result = $payroll_check_stmt->get_result();
$payroll_saved = false;
if ($payroll_check_result) {
    $check_row = $payroll_check_result->fetch_assoc();
    $payroll_saved = ($check_row['count'] > 0);
}

// ---------------------------
// Fetch Business Info
// ---------------------------
$business = getBusinessInfo($conn);
$logo_path = $business['logo_path'] ?? '';

// ---------------------------
// Fetch Employee Basic Info
// ---------------------------
$stmt = $conn->prepare("
    SELECT id, first_name, last_name, contact, salary, payroll_status, work_date, input, quality_check, defects 
    FROM employees 
    WHERE id = ?
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    die("Employee not found.");
}

// ---------------------------
// Fetch REAL-TIME rate_per_piece from price_log table
// ---------------------------
$priceStmt = $conn->prepare("
    SELECT price 
    FROM price_log 
    ORDER BY created_at DESC 
    LIMIT 1
");
$priceStmt->execute();
$priceResult = $priceStmt->get_result()->fetch_assoc();
$real_piece_rate = $priceResult['price'] ?? 0;

// If no price in price_log, try to get from orders table
if ($real_piece_rate == 0) {
    $orderStmt = $conn->prepare("
        SELECT price_per_piece 
        FROM orders 
        WHERE id = (SELECT order_id FROM employees WHERE id = ? LIMIT 1)
        LIMIT 1
    ");
    $orderStmt->bind_param("i", $employee_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result()->fetch_assoc();
    $real_piece_rate = $orderResult['price_per_piece'] ?? 0;
}

// ---------------------------
// Get Week + Date
// ---------------------------
$day_of_month = date('j');
$week_of_month = ceil($day_of_month / 7);

$week_suffix = [
    '',     // index 0 (unused)
    '1st',  // week 1
    '2nd',  // week 2
    '3rd',  // week 3
    '4th',  // week 4
    '5th'   // week 5 (fix added)
];

// fallback to prevent warnings
$current_week = ($week_suffix[$week_of_month] ?? 'Unknown') . ' Week';
$current_date = date('M d, Y');

// ---------------------------
// Fetch Payroll Data from payroll table (most recent record)
// ---------------------------
$payroll_stmt = $conn->prepare("
    SELECT 
        overall_input,
        overall_output,
        overall_defects,
        price_per_piece,
        total_salary,
        payroll_date
    FROM payroll
    WHERE employee_id = ?
    ORDER BY payroll_date DESC, created_at DESC
    LIMIT 1
");
$payroll_stmt->bind_param("i", $employee_id);
$payroll_stmt->execute();
$payroll_data = $payroll_stmt->get_result()->fetch_assoc();

// If payroll data exists, use it; otherwise fall back to real-time calculation
if ($payroll_data) {
    $agg = [
        'total_input' => $payroll_data['overall_input'],
        'total_goods' => $payroll_data['overall_output'],
        'total_defects' => $payroll_data['overall_defects'],
        'total_quality_check' => $payroll_data['overall_output']
    ];
    $real_piece_rate = $payroll_data['price_per_piece'];
    $gross_pay = $payroll_data['total_salary'];
} else {
    // Fallback: Fetch Aggregated Production Data from daily records
    $stmt2 = $conn->prepare("
        SELECT 
            e.id,
            COALESCE(edr.total_input, 0) AS total_input,
            COALESCE(qc.total_defects, 0) AS total_defects,
            COALESCE(qc.total_goods, 0) AS total_goods,
            COALESCE(qc.total_quality_check, 0) AS total_quality_check
        FROM employees e
        LEFT JOIN (
            SELECT employee_id, SUM(input_value) AS total_input
            FROM employee_daily_records
            GROUP BY employee_id
        ) edr ON e.id = edr.employee_id
        LEFT JOIN (
            SELECT employee_id, 
                   SUM(defects) AS total_defects, 
                   SUM(goods) AS total_goods,
                   SUM(quality_check) AS total_quality_check
            FROM quality_check_logs
            GROUP BY employee_id
        ) qc ON e.id = qc.employee_id
        WHERE e.id = ?
    ");
    $stmt2->bind_param("i", $employee_id);
    $stmt2->execute();
    $agg = $stmt2->get_result()->fetch_assoc();
    
    // Calculate Gross Pay (Salary)
    $gross_pay = $real_piece_rate * $agg['total_goods'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Employee Payroll</title>

<?php include('theme_loader.php'); ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

<style>
body { 
  font-family:'Poppins',sans-serif; 
  background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
  color:#fff; 
  margin:0; 
  min-height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
}

.payroll-card { 
  background:#fff; 
  color:#0d1033; 
  border-radius:24px; 
  padding:40px; 
  box-shadow:0 10px 30px rgba(0,0,0,0.25); 
  width:min(500px,95%);
  text-align: center;
}

.production-table {
  width: 100%;
  border-collapse: collapse;
  margin: 20px 0;
}

.production-table thead th {
  border-bottom: 2px solid #333;
  padding: 12px 8px;
  text-align: left;
  color: #333;
  font-weight: 600;
  font-size: 14px;
}

.production-table tbody td {
  padding: 12px 8px;
  text-align: left;
  color: #333;
  font-size: 14px;
}

.gross-pay {
  background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
  color: var(--theme-font);
  padding: 20px;
  border-radius: 12px;
  margin: 20px 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 16px;
}

.gross-pay .amount {
  font-size: 28px;
  font-weight: 700;
}

.back-btn {
  background: #6c757d;
  color: white;
  border: none;
  padding: 12px 30px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  text-transform: uppercase;
  transition: all 0.3s ease;
}

.back-btn:hover {
  background: #5a6268;
  transform: translateY(-2px);
}
</style>
</head>

<body>

<?php if (!$payroll_saved): ?>
<!-- Payroll Not Available Modal -->
<div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center; z-index: 9999;">
  <div style="background: white; padding: 40px; border-radius: 20px; text-align: center; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
    <div style="font-size: 60px; color: #ffc107; margin-bottom: 20px;">
      <i class="fa-solid fa-clock"></i>
    </div>
    <h2 style="color: #333; margin-bottom: 15px; font-size: 24px;">Payroll Not Available Yet</h2>
    <p style="color: #666; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
      The payroll information is currently being processed by management. Please check back later once the payroll has been finalized and saved.
    </p>
    <a href="employee_homepage.php?id=<?= $employee_id ?>" style="display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; border-radius: 10px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
      <i class="fa-solid fa-arrow-left"></i> Back to Homepage
    </a>
  </div>
</div>
<?php else: ?>

<div class="payroll-card">

  <!-- Header -->
  <div style="text-align: left; margin-bottom: 30px;">
    <div style="display: flex; align-items: center; justify-content: space-between;">
      <div>
        <p style="margin: 0; font-size: 14px;"><strong>ID:</strong> <?= htmlspecialchars($employee['id']) ?></p>
        <p style="margin: 5px 0 0 0; font-size: 16px;">
          <strong>Name:</strong>
          <?= htmlspecialchars($employee['first_name']." ".$employee['last_name']) ?>
        </p>
      </div>

      <img src="<?= htmlspecialchars($logo_path) ?>" 
           alt="Business Logo" 
           style="width: 60px; height: 60px; object-fit: cover; border-radius: 50%;">
    </div>

    <div style="display: flex; gap: 60px; font-size: 14px; margin-top: 15px;">
      <div>
        <strong>Piece Rate:</strong> 
        ₱<?= number_format($real_piece_rate, 2) ?>
      </div>
      <div><strong>Week:</strong> <?= $current_week ?></div>
      <div><strong>Date:</strong> <?= $current_date ?></div>
    </div>
  </div>

  <!-- Production Data -->
  <table class="production-table">
    <thead>
      <tr>
        <th>Description</th>
        <th>Count</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Quota (Overall Input)</strong></td>
        <td><strong><?= htmlspecialchars($agg['total_input']) ?></strong></td>
      </tr>
      <tr>
        <td>Quality Check</td>
        <td><?= htmlspecialchars($agg['total_quality_check']) ?></td>
      </tr>
     
      <tr>
        <td><strong>Defects (Overall)</strong></td>
        <td style="color: #dc3545; font-weight: 600;"><strong><?= htmlspecialchars($agg['total_defects']) ?></strong></td>
      </tr>
    </tbody>
  </table>

  <!-- Gross Pay -->
  <div class="gross-pay">
    <strong>Gross Pay (Total Salary):</strong>
    <span class="amount">₱<?= number_format($gross_pay, 2) ?></span>
  </div>
  
  <div style="text-align: center; font-size: 12px; color: #666; margin-top: 10px;">
    <em>Calculation: <?= $agg['total_goods'] ?> goods × ₱<?= number_format($real_piece_rate, 2) ?> = ₱<?= number_format($gross_pay, 2) ?></em>
  </div>

  <!-- Back Button -->
  <button class="back-btn" onclick="window.location.href='employee_record.php?id=<?= $employee['id'] ?>'">
    ← Back to Record
  </button>

</div>

<?php endif; ?>

</body>
</html>
