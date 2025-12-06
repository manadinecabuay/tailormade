
<?php
session_start();
require 'connection.php'; // Your DB connection

// --- Ensure valid employee session ---
if (isset($_GET['id'])) {
    $_SESSION['selected_employee_id'] = $_GET['id'];
}

if (!isset($_SESSION['selected_employee_id'])) {
    header("Location: employee_homepage.html");
    exit;
}

$employee_id = intval($_SESSION['selected_employee_id']);
$employee_name = $_SESSION['selected_employee_name'] ?? '';

// Check if there are active orders in the system
include_once 'reset_for_new_order.php';
$hasActiveOrders = hasActiveOrders($conn);

// === Handle AJAX actions ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    // --- Check if already submitted today ---
    if ($_POST['ajax_action'] === 'check_submission') {
        $employee_id = intval($_POST['employee_id']);
        $today = date('Y-m-d');

        $checkStmt = $conn->prepare("SELECT id FROM employee_daily_records WHERE employee_id = ? AND work_date = ?");
        if (!$checkStmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            exit;
        }

        $checkStmt->bind_param("is", $employee_id, $today);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            echo json_encode(['status' => 'already_submitted', 'message' => 'Already submitted input']);
            exit;
        } else {
            echo json_encode(['status' => 'can_submit']);
            exit;
        }
    }

    // --- Verify employee credentials (contact OR middle name) ---
    if ($_POST['ajax_action'] === 'verify_employee') {
        $employee_id = intval($_POST['employee_id']);
        $contact = trim($_POST['contact']);
        $middle_name = trim($_POST['middle_name']);

        $stmt = $conn->prepare("SELECT contact, middle_name FROM employees WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            exit;
        }

        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $employee = $result->fetch_assoc();

        if (!$employee) {
            echo json_encode(['status' => 'error', 'message' => 'Employee not found.']);
            exit;
        }

        // Check if either contact number OR middle name matches
        $contactMatch = !empty($contact) && $employee['contact'] === $contact;
        $middleNameMatch = !empty($middle_name) && strcasecmp($employee['middle_name'], $middle_name) === 0;

        if ($contactMatch || $middleNameMatch) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Contact number or middle name does not match our records.']);
        }
        exit;
    }

    // --- Get employee input records ---
    if ($_POST['ajax_action'] === 'get_records') {
        $employee_id = intval($_POST['employee_id']);
        
        // Calculate current week (Monday to Sunday)
        $mondayThisWeek = date('Y-m-d', strtotime('monday this week'));
        $sundayThisWeek = date('Y-m-d', strtotime('sunday this week'));
        
        // Fetch all daily records for current week from employee_daily_records table
        $stmt = $conn->prepare("
            SELECT work_date, input_value 
            FROM employee_daily_records 
            WHERE employee_id = ? 
            AND work_date BETWEEN ? AND ?
            ORDER BY work_date ASC
        ");
        
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            exit;
        }
        
        $stmt->bind_param("iss", $employee_id, $mondayThisWeek, $sundayThisWeek);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $records = [];
        while ($row = $result->fetch_assoc()) {
            $workDate = strtotime($row['work_date']);
            $dayNumber = date('N', $workDate); // 1=Monday, 7=Sunday
            $dayName = date('l', $workDate); // Full day name (Monday, Tuesday, etc.)
            
            $records[] = [
                'day_number' => $dayNumber,
                'day_name' => $dayName,
                'work_date' => date('M d, Y', $workDate),
                'input' => $row['input_value']
            ];
        }
        
        echo json_encode(['status' => 'success', 'records' => $records]);
        exit;
    }

    // --- Submit employee record ---
    if ($_POST['ajax_action'] === 'submit_record') {
        $employee_id = intval($_POST['employee_id']);
        $input = intval($_POST['input']);
        $today = date('Y-m-d');

        // Check if employee already submitted input today
        $checkStmt = $conn->prepare("SELECT id FROM employee_daily_records WHERE employee_id = ? AND work_date = ?");
        if (!$checkStmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            exit;
        }

        $checkStmt->bind_param("is", $employee_id, $today);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'You have already submitted input for today.']);
            exit;
        }

        // Insert into employee_daily_records table
        $stmt = $conn->prepare("INSERT INTO employee_daily_records (employee_id, work_date, input_value) VALUES (?, ?, ?)");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            exit;
        }

        $stmt->bind_param("isi", $employee_id, $today, $input);
        if ($stmt->execute()) {
            // Also update the employees table for backward compatibility
            $updateStmt = $conn->prepare("UPDATE employees SET input = ?, work_date = NOW() WHERE id = ?");
            $updateStmt->bind_param("ii", $input, $employee_id);
            $updateStmt->execute();
            
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to submit record.']);
        }
        exit;
    }
}

// === Fetch employee info ===
$stmt = $conn->prepare("SELECT id, first_name, last_name, contact, middle_name, input, work_date FROM employees WHERE id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    die("Employee not found.");
}

// === Calculate current week (Monday to Sunday) ===
$mondayThisWeek = date('Y-m-d', strtotime('monday this week'));
$sundayThisWeek = date('Y-m-d', strtotime('sunday this week'));

// === Fetch employee records for the current week from employee_daily_records ===
$recordsStmt = $conn->prepare("
    SELECT work_date, input_value 
    FROM employee_daily_records 
    WHERE employee_id = ? 
    AND work_date BETWEEN ? AND ?
    ORDER BY work_date ASC
");
$recordsStmt->bind_param("iss", $employee_id, $mondayThisWeek, $sundayThisWeek);
$recordsStmt->execute();
$recordsResult = $recordsStmt->get_result();

$records = [];
while ($row = $recordsResult->fetch_assoc()) {
    $workDate = strtotime($row['work_date']);
    $dayNumber = date('N', $workDate); // 1=Monday, 7=Sunday
    $dayName = date('l', $workDate); // Full day name (Monday, Tuesday, etc.)
    
    $records[] = [
        'input' => $row['input_value'],
        'work_date' => $row['work_date'],
        'day_number' => $dayNumber,
        'day_name' => $dayName
    ];
}

// === Check if employee already submitted today ===
$today = date('Y-m-d');
$checkStmt = $conn->prepare("SELECT id FROM employee_daily_records WHERE employee_id = ? AND work_date = ?");
$checkStmt->bind_param("is", $employee_id, $today);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$hasSubmittedToday = $checkResult->num_rows > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Employee Record</title>

<?php include('theme_loader.php'); ?>

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

.card { 
  background:#fff; 
  color:#0d1033; 
  border-radius:24px; 
  padding:40px; 
  box-shadow:0 10px 30px rgba(0,0,0,0.25); 
  width:min(450px,95%);
}

.card-header { 
  margin-bottom:30px; 
  text-align:center; 
}

.employee-info { 
  font-size:16px; 
  margin-bottom:20px; 
  color:#333;
}

.action-buttons { 
  display:flex; 
  gap:15px; 
  margin-top:20px; 
  justify-content:center;
}

button { 
  background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
  color: var(--theme-font); 
  border:none; 
  padding:12px 24px; 
  border-radius:8px; 
  cursor:pointer; 
  font-weight:600;
  font-size:14px;
  transition:all .3s ease;
}

button:hover { 
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.payroll-btn { 
  background:#28a745; 
  color:#fff; 
  border:none; 
  padding:12px 24px; 
  border-radius:8px; 
  cursor:pointer; 
  text-decoration:none; 
  display:inline-block; 
  font-weight:600;
  font-size:14px;
  transition:all .3s ease;
}

.payroll-btn:hover { 
  background:#218838; 
  transform: translateY(-2px);
}

.records-table { 
  width:100%; 
  border-collapse:collapse; 
  margin-top:20px; 
  table-layout:fixed;
}

.records-table th, .records-table td { 
  border:none; 
  padding:12px; 
  text-align:center; 
}

.records-table th { 
  border-bottom:2px solid #333; 
  background:transparent; 
  color:#333; 
  font-weight:600; 
}

.records-table td { 
  color:#333; 
}

.records-table tbody tr {
  background: transparent;
}

.records-table th:nth-child(1), 
.records-table td:nth-child(1) { 
  width:10%; 
}

.records-table th:nth-child(2), 
.records-table td:nth-child(2) { 
  width:50%; 
}

.records-table th:nth-child(3), 
.records-table td:nth-child(3) { 
  width:40%; 
}

.modal { 
  display:none; 
  position:fixed; 
  z-index:9999; 
  inset:0; 
  background:rgba(0,0,0,0.5); 
  justify-content:center; 
  align-items:center; 
}

.modal-content { 
  background:#fff; 
  color:#333; 
  padding:30px; 
  border-radius:12px; 
  text-align:center; 
  width:320px;
  box-shadow:0 10px 30px rgba(0,0,0,0.3);
}

.modal-content input { 
  width:100%; 
  padding:10px; 
  margin:10px 0; 
  border-radius:6px; 
  border:1px solid #ccc; 
  box-sizing: border-box;
}

.modal-content h3 {
  color: #333;
  margin-top: 0;
}

.modal-content label {
  display: block;
  text-align: left;
  margin-top: 10px;
  font-weight: 600;
  color: #333;
}
</style>
</head>

<body>
<div class="card">
    <div class="card-header">
      <div class="employee-info" style="display: flex; justify-content: center; align-items: center;">
        <span><strong>ID:</strong> <?= htmlspecialchars($employee['id']) ?></span>
        <span style="margin: 0 20px;">|</span> 
        <span><strong>Name:</strong> <?= htmlspecialchars($employee['first_name']." ".$employee['last_name']); ?></span>
      </div>
      <div id="currentDateTime" style="text-align: center; margin-top: 15px; font-size: 14px; color: #666;"></div>
    </div>

    <?php if (!$hasActiveOrders): ?>
    <div style="text-align: center; padding: 30px; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-radius: 12px; margin: 20px 0; border: 2px solid #ffc107;">
      <i class="fa-solid fa-exclamation-triangle" style="font-size: 48px; color: #ff9800; margin-bottom: 15px; display: block;"></i>
      <h3 style="color: #856404; font-size: 20px; margin-bottom: 10px; font-weight: 700;">No Active Orders</h3>
      <p style="color: #856404; font-size: 14px; line-height: 1.6; margin: 0;">
        There are currently no active orders. Input submission is disabled until a new order is confirmed.
      </p>
    </div>
    <?php endif; ?>

    <table class="records-table">
      <thead>
        <tr>
          <th>Day</th>
          <th>Date</th>
          <th>Input</th>
        </tr>
      </thead>
      <tbody id="recordsTableBody">
        <?php if (empty($records)): ?>
          <tr>
            <td colspan="3" style="text-align:center; color:#999;">No records for this week</td>
          </tr>
        <?php else: ?>
          <?php foreach ($records as $r): ?>
            <tr>
              <td><?= $r['day_name'] ?></td>
              <td><?= date('M d, Y', strtotime($r['work_date'])) ?></td>
              <td><?= $r['input'] ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    
    <div class="action-buttons" style="margin-top:20px;">
      <button onclick="window.location.href='employee_homepage.php'" style="background:#6c757d;">
        ← Back to Home
      </button>
      <button id="editInputBtn" <?= !$hasActiveOrders ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
        Submit Input
      </button>
      <a href="employee_payroll.php?id=<?= $employee['id'] ?>" class="payroll-btn">Payroll</a>
    </div>
  </div>
</div>

<!-- Modal 1: Verify Employee -->
<div id="verifyModal" class="modal">
  <div class="modal-content">
    <h3>Verify Your Identity</h3>
    <form id="verifyForm">
      <label>Employee ID</label>
      <input type="text" name="employee_id" value="<?= htmlspecialchars($employee_id) ?>" readonly>
      
      <label>Contact Number OR Middle Name:</label>
      <div style="position: relative;">
        <input type="password" id="contact" name="contact" placeholder="Enter contact number" autocomplete="off">
        <button type="button" id="toggleContact" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; color: #666; padding: 5px 10px; font-size: 12px;">Show</button>
      </div>
      <p style="text-align:center; margin:5px 0; color:#999;">- OR -</p>
      <div style="position: relative;">
        <input type="password" id="middle_name" name="middle_name" placeholder="Enter middle name" autocomplete="off">
        <button type="button" id="toggleMiddleName" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; color: #666; padding: 5px 10px; font-size: 12px;">Show</button>
      </div><br><br>
      
      <button type="submit">Verify</button>
      <button type="button" id="closeVerify">Cancel</button>
    </form>
  </div>
</div>

<!-- Modal 2: Enter Input -->
<div id="inputModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3>Enter Your Input</h3>
    <form id="inputForm">
      <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee_id); ?>">
      <label>Input:</label>
      <input type="number" id="inputValue" name="input" min="0" required><br><br>
      <button type="button" id="showConfirmBtn">Submit</button>
      <button type="button" id="closeInput">Cancel</button>
    </form>
  </div>
</div>

<!-- Modal 3: Confirmation Modal -->
<div id="confirmModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3>Want to submit record?</h3>
    <p style="font-size: 16px; margin: 20px 0;">Are you sure you want to submit this record?</p>
    <div style="display:flex; gap:10px; justify-content:center;">
      <button type="button" id="confirmSubmit">Submit</button>
      <button type="button" id="cancelSubmit" style="background:#6c757d;">Cancel</button>
    </div>
  </div>
</div>

<!-- Modal 4: Error Modal -->
<div id="errorModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3 style="color: #dc3545;">❌ Error</h3>
    <p id="errorMessage" style="font-size: 16px; margin: 20px 0;">Incorrect Contact Number or Middle Name</p>
    <button type="button" id="closeError" style="background:#dc3545;">OK</button>
  </div>
</div>

<!-- Modal 5: Already Submitted Input Modal -->
<div id="alreadySubmittedModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3 style="color: #ff9800;">⚠️ Already Submitted Input</h3>
    <p style="font-size: 16px; margin: 20px 0;">Already submitted input</p>
    <button type="button" id="closeAlreadySubmitted" style="background:#ff9800;">OK</button>
  </div>
</div>


<script>
const employeeId = <?= $employee_id ?>;

// Function to update date and day in real-time
function updateDateTime() {
  const now = new Date();
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  const dateString = now.toLocaleDateString('en-US', options);
  document.getElementById('currentDateTime').textContent = dateString;
}

// Update immediately and then every second
updateDateTime();
setInterval(updateDateTime, 1000);

// Toggle visibility for contact number
document.getElementById('toggleContact').addEventListener('click', function() {
  const contactInput = document.getElementById('contact');
  if (contactInput.type === 'password') {
    contactInput.type = 'text';
    this.textContent = 'Hide';
  } else {
    contactInput.type = 'password';
    this.textContent = 'Show';
  }
});

// Toggle visibility for middle name
document.getElementById('toggleMiddleName').addEventListener('click', function() {
  const middleNameInput = document.getElementById('middle_name');
  if (middleNameInput.type === 'password') {
    middleNameInput.type = 'text';
    this.textContent = 'Hide';
  } else {
    middleNameInput.type = 'password';
    this.textContent = 'Show';
  }
});

// Function to load records via AJAX
async function loadRecords() {
  const formData = new FormData();
  formData.append('ajax_action', 'get_records');
  formData.append('employee_id', employeeId);

  const res = await fetch('', { method: 'POST', body: formData });
  const data = await res.json();

  if (data.status === 'success') {
    const tbody = document.getElementById('recordsTableBody');
    tbody.innerHTML = '';

    if (data.records.length === 0) {
      tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#999;">No records for this week</td></tr>';
    } else {
      data.records.forEach(record => {
        const row = `
          <tr>
            <td>${record.day_name}</td>
            <td>${record.work_date}</td>
            <td>${record.input}</td>
          </tr>
        `;
        tbody.innerHTML += row;
      });
    }
  }
}

// Load records on page load
document.addEventListener('DOMContentLoaded', function() {
  loadRecords();
});

// Open verification modal when Submit Input is clicked
document.getElementById('editInputBtn').addEventListener('click', async (e) => {
  // Check if button is disabled (no active orders)
  if (e.target.disabled) {
    return;
  }
  
  // First check with backend if already submitted today
  const formData = new FormData();
  formData.append('ajax_action', 'check_submission');
  formData.append('employee_id', employeeId);

  const res = await fetch('', { method: 'POST', body: formData });
  const data = await res.json();

  if (data.status === 'already_submitted') {
    // Show "already submitted" modal immediately
    document.getElementById('alreadySubmittedModal').style.display = 'flex';
    return;
  }
  
  // If not submitted, proceed with verification modal
  document.getElementById('verifyModal').style.display = 'flex';
});

// Close modals
document.getElementById('closeVerify').addEventListener('click', () => {
  document.getElementById('verifyModal').style.display = 'none';
});

document.getElementById('closeInput').addEventListener('click', () => {
  document.getElementById('inputModal').style.display = 'none';
});

document.getElementById('closeError').addEventListener('click', () => {
  document.getElementById('errorModal').style.display = 'none';
});

document.getElementById('closeAlreadySubmitted').addEventListener('click', () => {
  document.getElementById('alreadySubmittedModal').style.display = 'none';
});

// Cancel button - go back to input modal to edit (allow editing)
document.getElementById('cancelSubmit').addEventListener('click', () => {
  document.getElementById('confirmModal').style.display = 'none';
  document.getElementById('inputModal').style.display = 'flex';
  // Keep the input value so employee can edit it
});

// Verify employee (contact OR middle name)
document.getElementById('verifyForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const contact = document.getElementById('contact').value.trim();
  const middleName = document.getElementById('middle_name').value.trim();
  
  // Ensure at least one field is filled
  if (!contact && !middleName) {
    document.getElementById('errorMessage').textContent = 'Please enter either contact number or middle name.';
    document.getElementById('errorModal').style.display = 'flex';
    return;
  }
  
  const formData = new FormData(this);
  formData.append('ajax_action', 'verify_employee');

  const res = await fetch('', { method: 'POST', body: formData });
  const data = await res.json();

  if (data.status === 'success') {
    // After successful verification, check if already submitted today
    const checkData = new FormData();
    checkData.append('ajax_action', 'check_submission');
    checkData.append('employee_id', employeeId);

    const checkRes = await fetch('', { method: 'POST', body: checkData });
    const checkResult = await checkRes.json();

    if (checkResult.status === 'already_submitted') {
      document.getElementById('verifyModal').style.display = 'none';
      document.getElementById('alreadySubmittedModal').style.display = 'flex';
      return;
    }
    
    // If not submitted, show input modal
    document.getElementById('verifyModal').style.display = 'none';
    document.getElementById('inputModal').style.display = 'flex';
  } else {
    document.getElementById('verifyModal').style.display = 'none';
    document.getElementById('errorMessage').textContent = data.message || 'Verification failed.';
    document.getElementById('errorModal').style.display = 'flex';
  }
});

// Show confirmation modal when user clicks Submit in input form
document.getElementById('showConfirmBtn').addEventListener('click', function() {
  const inputValue = document.getElementById('inputValue').value;
  
  if (!inputValue || inputValue < 0) {
    alert('Please enter a valid input value.');
    return;
  }
  
  document.getElementById('inputModal').style.display = 'none';
  document.getElementById('confirmModal').style.display = 'flex';
});

// Submit record after confirmation
document.getElementById('confirmSubmit').addEventListener('click', async function() {
  const employeeId = document.querySelector('input[name="employee_id"]').value;
  const input = document.getElementById('inputValue').value;
  
  const formData = new FormData();
  formData.append('ajax_action', 'submit_record');
  formData.append('employee_id', employeeId);
  formData.append('input', input);

  const res = await fetch('', { method: 'POST', body: formData });
  const data = await res.json();

  if (data.status === 'success') {
    alert('Record submitted successfully!');
    document.getElementById('confirmModal').style.display = 'none';
    document.getElementById('inputValue').value = ''; // Clear input
    loadRecords(); // Reload records via AJAX
  } else {
    document.getElementById('confirmModal').style.display = 'none';
    document.getElementById('errorMessage').textContent = data.message || 'Failed to submit record.';
    document.getElementById('errorModal').style.display = 'flex';
  }
});

</script>
</body>
</html>