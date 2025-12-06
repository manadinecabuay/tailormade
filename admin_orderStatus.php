<?php
ini_set('display_errors', 1);
include('connection.php');
require 'admin_session.php';

// === AJAX: Update order status ===
if (isset($_POST['action']) && $_POST['action'] === 'updateStatus') {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['new_status'];

    $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    $success = $stmt->execute();

    echo json_encode(['success' => $success]);
    exit;
}

$supervisor_id = $_SESSION['supervisor_id'] ?? null;
$supervisor_name = "Supervisor";
$photo_path = "profile.jpg";

if ($supervisor_id) {
    $sql = "SELECT first_name, last_name, profile_photo FROM supervisors WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $supervisor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $supervisor_name = $row['first_name'] . ' ' . $row['last_name'];
        $photo_path = $row['profile_photo'] ?: "profile.jpg";
    }
}

// === Fetch orders ===
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

$sql = "
    SELECT 
        o.id AS order_id,
        c.full_name AS customer_name,
        oi.garment_type,
        oi.fabric_type,
        o.due_date,
        o.status
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    JOIN order_items oi ON oi.order_id = o.id
";

if (!empty($statusFilter)) {
    $sql .= " WHERE o.status = ?";
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($statusFilter)) {
    $stmt->bind_param("s", $statusFilter);
}
$stmt->execute();
$result = $stmt->get_result();

$statuses = ['Order Confirmation', 'Order Processing', 'Quality Check', 'Order Pack', 'Out for Delivery', 'Complete'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Status - Owner</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    /* === Same CSS as the confirmed final version === */
    * {
      margin:0;
      padding:0;
      box-sizing:border-box;
      font-family:'Poppins',sans-serif;
    }

    body{
      display:flex;
      background-color:#5c5f66;
      color:#fff;
      height:100vh;
      overflow:hidden;
    }

    .sidebar {
      width: 230px;
      background-color: #d9d9d9;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding-top: 60px;
      position: fixed;       
      top: 0;
      left: 0;
      height: 100vh;         
      z-index: 1000;        
    }

    .sidebar a{
      color: #000;
      text-decoration:none;
      padding:15px 25px;
      display:flex;
      align-items:center;
      gap:12px;
      font-weight:500;
      transition:background 0.3s;
    }

    .sidebar a:hover,.sidebar a.active{
      background-color:#bfbfbf;
    }

    .logout {
      position: absolute;
      bottom: 25px; 
      width: 100%;
    }

    .main{
      margin-left: 230px;
      flex:1;
      padding:30px 40px;
    }

    .header{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:25px;
    }

    .header h2{
      font-size:22px;
      font-weight:600;
    }

    .header-right{
      display:flex;
      align-items:center;
      gap:10px;
    }

    .header-right img{
      width:38px;
      height:38px;
      border-radius:50%;
      border:2px solid #fff;
    }

    .update-status-section{
      background-color: #0a0a3d;
      border-radius:10px;
      padding:20px;
      box-shadow:0px 2px 10px rgba(0,0,0,0.1);
    }

    .update-status-section h4{
      margin-bottom:20px;
      letter-spacing:1px;
    }

    table{
      width:100%;
      border-collapse:collapse;
      background-color: #0a0a3d;
    }

    thead{
      background-color: #0a0a3d;
      color:#ccc;
    }
    thead th{
      padding:12px;
      text-align:left;
      font-size:0.9rem;
      letter-spacing:0.5px;
    }

    tbody tr{
      background-color: #0a0a3d;
      border-bottom:1px solid #ddd;
    }

    tbody td{
      padding:10px 12px;
      font-size:0.9rem;
      position:relative;
    }

    .status-container{
      display:flex;
      align-items:center;
      justify-content:space-between;
      background-color:#1c2143;
      border-radius:6px;
      padding:6px 10px;
      min-width:180px;
      position:relative;
      height:36px;
    }

    .current-status{
      font-weight:500;
      color:#fff;
      text-transform:capitalize;
      flex:1;
      overflow:hidden;
      text-overflow:ellipsis;
      white-space:nowrap;
    }

    .status-btn{
      background:none;
      border:none;
      color:#fff;
      font-size:1rem;
      cursor:pointer;
      margin-left:8px;
      position:absolute;
      right:8px;
      top:50%;
      transform:translateY(-50%);
    }

    .status-btn:hover{
      color:#aab4ff;
    }

    .dropdown {
      position: absolute;
      right: 100%;
      top: 45px;
      background-color: #ffffff; /* changed from #0b1531 */
      border-radius: 8px;
      display: none;
      flex-direction: column;
      min-width: 200px;
      z-index: 10;
      box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.3);
    }

    .dropdown button {
      background: none;
      border: none;
      color: #000; /* changed to black text */
      text-align: left;
      padding: 10px 15px;
      font-size: 0.85rem;
      cursor: pointer;
      transition: 0.3s;
      letter-spacing: 0.5px;
    }

    .dropdown button:hover {
      background-color: #e0e6ff; /* light hover color */
    }

    .dropdown.upward{
      top:auto;
      bottom:45px;
      overflow:visible;
    }
    
    .nested-dropdown{
      position:relative;
    }

    .nested-btn{
      background:none;
      border:none;
      color:white;
      text-align:left;
      padding:10px 15px;
      font-size:0.85rem;
      cursor:pointer;
      width:100%;
    }

    .nested-dropdown-content {
      display: none;
      position: absolute;
      right: 100%;
      top: 0;
      background-color: #ffffff; /* changed from #0b1531 */
      border-radius: 8px;
      box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
      flex-direction: column;
      min-width: 180px;
      z-index: 20;
      padding: 12px;
    }
    
    .nested-dropdown-content button {
      padding: 8px 12px;
      font-size: 0.85rem;
      text-align: left;
      background: none;
      border: none;
      color: #000; /* changed to black text */
      cursor: pointer;
    }

    
    .nested-dropdown-content button:hover {
      background-color: #e0e6ff; /* light hover */
    }

    .nested-dropdown-content.upward{
      top:auto;
      bottom:0;
    
    }

    .nested-dropdown:hover .nested-dropdown-content{
      display:flex;
    }

    .form-group{
      background-color: #d4d1d1ff;
      border: 1px solid #353434ff;
      border-radius:8px;
      padding:10px;
      margin-bottom:12px;
      color: #000;
      display:flex;
      flex-direction:column;
      gap:6px;
    }

    .form-group label{
      font-size:0.8rem;
      color: #333;
      letter-spacing:0.5px;
    }

    .form-group input[type="number"]{
      padding:6px 8px;
      border:1px solid #2f3f9e;
      border-radius:5px;
      background-color: #f8f8f8;
      color: white;
      font-size:0.85rem;
    }
    
    .upload-btn,.update-btn{
      background-color:white;
      color: #2a4eff;
      border:2px solid #131b44ff;
      border-radius:25px;
      padding:8px 16px;
      cursor:pointer;
      font-weight:600;
      font-size:0.9rem;
      transition:all 0.3s ease;
    }

    .upload-btn:hover,.update-btn:hover{
      background-color: #1a35cc;
      color: #000;
    }

    .preview{
      display:block;
      max-width:100%;
      border-radius:6px;
      margin-top:6px;
      border:1px solid #101844ff;
    }
  </style>

</head>
<body>

<div class="sidebar">
  <div>
    <a href="admin_dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href="admin_employees.php"><i class="fa-solid fa-users"></i> Employees</a>
    <a href="admin_qualityCheck.php"><i class="fa-solid fa-check"></i> Quality Check</a>
    <a href="admin_orderStatus.php" class="active"><i class="fa-solid fa-box"></i> Order Status</a>
  </div>
  <div class="logout" style="margin-top:auto;">
    <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</div>

<div class="main">
  <div class="header">
    <h2>Welcome back, <?php echo htmlspecialchars($supervisor_name); ?>!</h2>
    <div class="header-right">
      <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profile">
      <span><?php echo htmlspecialchars($supervisor_name); ?></span>
    </div>
  </div>

    <div class="update-status-section">
      <h4>UPDATE ORDER STATUS</h4>
      <div class="filter-container" style="margin-bottom:15px;">
        <form method="get">
          <label for="status">Filter by status:</label>
          <select name="status" id="status">
            <option value="">All</option>
            <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>" <?= ($statusFilter == $s) ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit">Filter</button>
        </form>
      </div>

      <table>
        <thead>
          <tr>
            <th>ORDER ID</th>
            <th>NAME OF CUSTOMER</th>
            <th>TYPE OF GARMENT AND FABRIC</th>
            <th>DUE DATE</th>
            <th>STATUS</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['order_id']) ?></td>
                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                <td><?= htmlspecialchars($row['garment_type'] . ' (' . $row['fabric_type'] . ')') ?></td>
                <td><?= htmlspecialchars(date('M d, Y', strtotime($row['due_date']))) ?></td>
                <td>
                  <div class="status-container">
                    <span class="current-status"><?= htmlspecialchars($row['status']) ?></span>
                    <button class="status-btn">▼</button>
                    <div class="dropdown">
                      <?php foreach ($statuses as $s): ?>
                        <?php if ($s === 'Order Pack'): ?>
                          <div class="nested-dropdown">
                            <button class="nested-btn"><?= strtoupper($s) ?> ▼</button>
                            <div class="nested-dropdown-content">
                              <div class="form-group">
                                <label>GOODS</label>
                                <input type="number" placeholder="0" min="0">
                                <input type="file" id="goodsPhoto_<?= $row['order_id'] ?>" accept="image/*" hidden>
                                <button class="upload-btn" onclick="document.getElementById('goodsPhoto_<?= $row['order_id'] ?>').click()">UPLOAD</button>
                                <img id="goodsPreview_<?= $row['order_id'] ?>" class="preview">
                              </div>
                              <div class="form-group">
                                <label>DEFECT</label>
                                <input type="number" placeholder="0" min="0">
                                <input type="file" id="defectPhoto_<?= $row['order_id'] ?>" accept="image/*" hidden>
                                <button class="upload-btn" onclick="document.getElementById('defectPhoto_<?= $row['order_id'] ?>').click()">UPLOAD</button>
                                <img id="defectPreview_<?= $row['order_id'] ?>" class="preview">
                              </div>
                              <button class="update-btn" onclick="updateData()">UPDATE</button>
                            </div>
                          </div>
                        <?php else: ?>
                          <button onclick="updateStatus(<?= $row['order_id'] ?>, '<?= $s ?>')"><?= strtoupper($s) ?></button>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" style="text-align:center;">No orders found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<script>
  // Toggle dropdown (left side, upward flip)
  document.querySelectorAll('.status-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.stopPropagation();
      const dropdown = this.nextElementSibling;
      document.querySelectorAll('.dropdown').forEach(d => { if (d !== dropdown) d.style.display='none'; });
      const isVisible = dropdown.style.display === 'flex';
      dropdown.style.display = isVisible ? 'none' : 'flex';
      if (!isVisible) {
        const rect = btn.getBoundingClientRect();
        const dropdownHeight = dropdown.offsetHeight;
        dropdown.classList.toggle('upward', rect.bottom + dropdownHeight > window.innerHeight);
      }
    });
  });

  document.addEventListener('click',()=>document.querySelectorAll('.dropdown').forEach(d=>d.style.display='none'));

  document.querySelectorAll('.nested-dropdown').forEach(nested=>{
    const content=nested.querySelector('.nested-dropdown-content');
    nested.addEventListener('mouseenter',()=>{
      content.classList.remove('upward');
      const rect=content.getBoundingClientRect();
      if(rect.bottom>window.innerHeight)content.classList.add('upward');
    });
  });

  document.querySelectorAll('.dropdown,.nested-dropdown-content').forEach(m=>m.addEventListener('click',e=>e.stopPropagation()));

  function updateData(){alert('Order Pack Updated!');}

  function updateStatus(orderId, newStatus){
    fetch('',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({action:'updateStatus',order_id:orderId,new_status:newStatus})
    }).then(r=>r.json()).then(data=>{
      if(data.success){location.reload();}
      else{alert('Failed to update status');}
    });
  }

  setInterval(() => {
  fetch('admin_session_check.php')
    .then(res => res.json())
    .then(data => {
      if (!data.active) {
        alert("Your session has expired or you have logged out in another tab.");
        window.location.href = "login_admins.html";
      }
    });
}, 15000); // check every 15 seconds

</script>
</body>
</html>
