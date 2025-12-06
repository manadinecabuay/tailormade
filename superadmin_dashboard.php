<?php
include 'connection.php';
require 'superadmin_session.php';


if (!isset($_SESSION['owner_id'])) {
    die("Error: No super admin logged in. Please log in again.");
}

$owner_id = intval($_SESSION['owner_id']);

// --------------------
// Fetch Super Admin Info
// --------------------
$sqlOwner = "SELECT first_name, last_name, profile_photo FROM owner WHERE id = $owner_id";
$resOwner = mysqli_query($conn, $sqlOwner);
if (!$resOwner) die("SQL Error (Owner Info): " . mysqli_error($conn));
$owner = mysqli_fetch_assoc($resOwner);

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

// --------------------
// Fetch one order currently in production from confirmed_order
// --------------------
$sqlOrders = "
    SELECT orderID, order_status as status 
    FROM confirmed_order 
    WHERE order_status NOT IN ('Complete', 'Cancelled') 
    ORDER BY updated_at DESC 
    LIMIT 1
";
$resOrders = mysqli_query($conn, $sqlOrders);
if (!$resOrders) die("SQL Error (Orders): " . mysqli_error($conn));
$orderData = mysqli_fetch_assoc($resOrders);
$orderId = $orderData['orderID'] ?? null;

// Check if there's an active order
$showNoData = ($orderId === null);

// Initialize totals
$totalOrdered = $totalMade = $totalGoods = $totalDefect = 0;

if ($orderId) {
    // --- Fetch total quantity ordered for this order ---
    $stmtOrdered = $conn->prepare("SELECT total_garments AS total_ordered FROM orders WHERE orderID = ?");
    $stmtOrdered->bind_param("i", $orderId);
    $stmtOrdered->execute();
    $totalOrdered = $stmtOrdered->get_result()->fetch_assoc()['total_ordered'] ?? 0;
    $stmtOrdered->close();

    // --- Get total input from employee_daily_records (all employees for current order) ---
    $inputQuery = $conn->query("SELECT COALESCE(SUM(input_value), 0) AS total_input FROM employee_daily_records");
    if ($inputQuery) {
        $inputData = $inputQuery->fetch_assoc();
        $totalMade = intval($inputData['total_input'] ?? 0);
    }
    
    // --- Get total goods (quality_check) from quality_check_logs table ---
    $goodsQuery = $conn->query("SELECT COALESCE(SUM(goods), 0) AS total_goods FROM quality_check_logs");
    if ($goodsQuery) {
        $goodsData = $goodsQuery->fetch_assoc();
        $totalGoods = intval($goodsData['total_goods'] ?? 0);
    }
    
    // --- Get total defects from quality_check_logs table ---
    $defectsQuery = $conn->query("SELECT COALESCE(SUM(defects), 0) AS total_defects FROM quality_check_logs");
    if ($defectsQuery) {
        $defectsData = $defectsQuery->fetch_assoc();
        $totalDefect = intval($defectsData['total_defects'] ?? 0);
    }
    
    // Ensure values are not negative
    if ($totalGoods < 0) $totalGoods = 0;
    if ($totalMade < 0) $totalMade = 0;
    if ($totalDefect < 0) $totalDefect = 0;
}

$quotaDisplay = "{$totalMade} / {$totalOrdered}";

// --------------------
// Initial Revenue Chart Data (This Week) - Only Completed Orders
// --------------------
$sqlChart = "
    SELECT DATE(co.updated_at) AS order_date, co.total_price AS daily_sales, co.orderID
    FROM confirmed_order co
    WHERE co.order_status = 'Complete'
    AND YEARWEEK(co.updated_at, 1) = YEARWEEK(CURRENT_DATE(), 1)
    ORDER BY co.updated_at ASC
";


$resChart = mysqli_query($conn, $sqlChart);
$chartData = [];
while ($row = mysqli_fetch_assoc($resChart)) $chartData[] = $row;

// Each completed order is a separate data point
$chartLabels = array_column($chartData, 'order_date');
$chartValues = array_column($chartData, 'daily_sales');

// If no data, show placeholder
if (empty($chartLabels)) {
    $chartLabels = ['No Data'];
    $chartValues = [0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Dashboard</title>

<!-- Include Theme Loader for Custom Colors -->
<?php include('theme_loader.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
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
      display: flex;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      min-height: 100vh;
      overflow-x: hidden;
    }

    .sidebar {
      width: 260px;
      background:rgba(255,255,255,0.98);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
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

    /* Main content */
    .main {
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

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
      background: rgba(255, 255, 255, 0.95);
      padding: 25px 35px;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    header h2 {
      font-size: 28px;
      font-weight: 700;
      color: var(--theme-bg);
      letter-spacing: 0.5px;
    }

    .profile {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .profile i {
      font-size: 20px;
      color: var(--theme-bg);
      cursor: pointer;
      transition: transform 0.3s ease;
    }

    .profile i:hover {
      transform: scale(1.2);
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

    /* Cards */
    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      margin-bottom: 35px;
    }

    .card {
      background: rgba(255, 255, 255, 0.95);
      padding: 20px 25px;
      border-radius: 15px;
      text-align: center;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
      transition: all 0.3s ease;
      border: 2px solid transparent;
      min-height: 120px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 35px rgba(102, 126, 234, 0.3);
      border-color: var(--theme-bg);
    }

    .card h3 {
      font-size: 2rem;
      margin-bottom: 8px;
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      font-weight: 700;
      line-height: 1;
    }

    .card p {
      font-size: 0.85rem;
      color: #666;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      line-height: 1.3;
    }

    /* Charts */
    .charts {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
      gap: 25px;
      margin-top: 20px;
    }

    .chart-box {
      background: rgba(255, 255, 255, 0.95);
      padding: 30px;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
      transition: all 0.3s ease;
      max-height: 450px;
    }

    .chart-box:hover {
      box-shadow: 0 15px 40px rgba(102, 126, 234, 0.3);
    }

    .chart-box canvas {
      max-height: 320px !important;
      width: 100% !important;
      height: auto !important;
    }

    .chart-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .chart-header h3 {
      font-size: 1.2rem;
      font-weight: 600;
      color: var(--theme-bg);
    }

    .dropdown {
      background: linear-gradient(135deg, var(--theme-bg) 0%, var(--theme-button) 100%);
      color: var(--theme-font);
      border: none;
      border-radius: 10px;
      padding: 8px 16px;
      cursor: pointer;
      font-weight: 500;
      font-size: 14px;
      transition: all 0.3s ease;
    }

    .dropdown:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    .dropdown option {
      color: #000;
      background: #fff;
      font-weight: 500;
      padding: 10px;
      transition: all 0.3s ease;
    }

    .dropdown option:hover {
      background: var(--theme-bg);
      color: #fff;
    }

    canvas {
      width: 100% !important;
      height: 300px !important;
    }

    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.5);
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

    /* Mobile Responsive Styles */
    @media (max-width: 1200px) {
      .charts {
        grid-template-columns: 1fr;
      }
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

      .cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
      }

      .card {
        padding: 15px;
        min-height: 100px;
      }

      .card h3 {
        font-size: 1.5rem;
      }

      .card p {
        font-size: 0.75rem;
      }

      .charts {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .chart-box {
        padding: 20px 15px;
      }

      .chart-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }

      .chart-header h3 {
        font-size: 1rem;
      }

      .dropdown {
        width: 100%;
        padding: 10px;
      }

      canvas {
        height: 250px !important;
      }
    }

    @media (max-width: 480px) {
      .main {
        padding: 70px 10px 15px 10px;
      }

      .cards {
        grid-template-columns: 1fr;
        gap: 12px;
      }

      .card {
        padding: 12px;
        min-height: 90px;
      }

      .card h3 {
        font-size: 1.3rem;
        margin-bottom: 5px;
      }

      .card p {
        font-size: 0.7rem;
      }

      .header {
        padding: 15px;
        border-radius: 15px;
      }

      .header h2 {
        font-size: 18px;
      }

      .header-right img {
        width: 35px;
        height: 35px;
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

      .chart-box {
        padding: 15px;
        border-radius: 15px;
      }

      .chart-header h3 {
        font-size: 0.9rem;
      }

      .dropdown {
        font-size: 12px;
        padding: 8px;
      }

      canvas {
        height: 220px !important;
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
    <a href="#" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href="superadmin_employees.php"><i class="fa-solid fa-users"></i> Employees</a>
    <a href="superadmin_payroll.php"><i class="fa-solid fa-money-bill"></i> Payroll</a>
    <a href="superadmin_order.php"><i  class="fa-solid fa-box"></i> Orders</a>
    <a href="superadmin_orderStatus.php"><i class="fa-solid fa-chart-bar"></i> Order Status</a>
    <a href="customize.php"><i class="fa-solid fa-gear"></i> Customize</a>
  </div>
  <div class="bottom">
    <a href="#" onclick="showLogoutModal(event)" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</div>

<div class="main">
<header>
  <h2>Welcome back, <?php echo $owner['first_name'] . ' ' . $owner['last_name']; ?>!</h2>
  <div class="profile">
    <img src="<?php echo $owner['profile_photo'] ?: 'https://via.placeholder.com/35'; ?>" alt="profile">
    <span><?php echo $owner['first_name'] . ' ' . $owner['last_name']; ?></span>
  </div>
</header>

<?php if ($showNoData): ?>
<!-- No Active Order Message -->
<div style="text-align: center; padding: 60px 20px; background: rgba(255,255,255,0.1); border-radius: 20px; margin: 20px 0;">
  <div style="font-size: 80px; margin-bottom: 20px;">📋</div>
  <h2 style="font-size: 32px; margin-bottom: 15px; color: white;">No Active Order</h2>
  <p style="font-size: 18px; opacity: 0.8; max-width: 500px; margin: 0 auto;">
    Dashboard will display production data when a new order is confirmed and in production.
  </p>
</div>
<?php else: ?>
<!-- Normal Dashboard Cards -->
<div class="cards">
  <div class="card">
    <h3><?php echo $orderData['orderID'] ?? '—'; ?></h3>
    <p><?php echo $orderData['status'] ?? 'No order in production'; ?></p>
  </div>
  <div class="card"><h3><?php echo $totalMade ?: 0; ?> / <?php echo $totalOrdered ?: 0; ?></h3><p>QUOTA (Made / Ordered)</p></div>
  <div class="card"><h3 id="goodsValue"><?php echo $totalGoods ?: 0; ?></h3><p>GOODS</p></div>
  <div class="card"><h3 id="defectsValue"><?php echo $totalDefect ?: 0; ?></h3><p>DEFECTS</p></div>
</div>

<div class="charts">
  <div class="chart-box">
    <div class="chart-header">
      <h3>Revenue Stats</h3>
      <select id="revenueFilter" class="dropdown">
        <option value="week">This Week</option>
        <option value="month">This Month</option>
        <option value="year">This Year</option>
      </select>
    </div>
    <canvas id="lineChart"></canvas>
  </div>

  <div class="chart-box">
    <div class="chart-header">
      <h3>Production Breakdown</h3>
    </div>
    <canvas id="doughnutChart"></canvas>
  </div>
</div>
<?php endif; ?>

<script>
// Mobile sidebar toggle
function toggleMobileSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.overlay');
  sidebar.classList.toggle('mobile-open');
  overlay.classList.toggle('active');
}

// Get theme colors from CSS variables
const rootStyles = getComputedStyle(document.documentElement);
const themeBg = rootStyles.getPropertyValue('--theme-bg').trim();
const themeButton = rootStyles.getPropertyValue('--theme-button').trim();
const themeFont = rootStyles.getPropertyValue('--theme-font').trim();

// Line chart
let lineChartCtx = document.getElementById('lineChart').getContext('2d');
let lineChart = new Chart(lineChartCtx,{
    type:'line',
    data:{
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets:[{
            label:'Revenue',
            data: <?php echo json_encode($chartValues); ?>,
            borderColor: themeBg,
            backgroundColor: themeBg,
            tension:0.4,
            pointRadius: 6,
            pointHoverRadius: 8,
            pointBackgroundColor: themeBg,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointHoverBackgroundColor: themeBg,
            pointHoverBorderColor: '#fff',
            pointHoverBorderWidth: 3
        }]
    },
    options:{ 
        responsive: true,
        maintainAspectRatio: true,
        aspectRatio: 2,
        plugins:{ legend:{ display:false } },
        scales: {
            y: {
                beginAtZero: true,
                min: 0,
                max: 50000,
                ticks: {
                    stepSize: 10000,
                    callback: function(value) {
                        if (value === 0) return '0';
                        if (value === 1000) return '1,000';
                        if (value === 10000) return '10,000';
                        if (value === 20000) return '20,000';
                        if (value === 30000) return '30,000';
                        if (value === 40000) return '40,000';
                        if (value === 50000) return '50,000';
                        return value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Doughnut Production chart
let doughnutCtx = document.getElementById('doughnutChart').getContext('2d');
let totalRemaining = Math.max(0, <?php echo $totalOrdered; ?> - <?php echo $totalMade; ?>);

let doughnutChart = new Chart(doughnutCtx,{
    type:'doughnut',
    data:{
        labels:['Goods','Defects','Remaining'],
        datasets:[{
            data:[<?php echo $totalGoods; ?>, <?php echo $totalDefect; ?>, totalRemaining],
            backgroundColor:[themeBg,'#dc3545','#95a5a6'],
            borderWidth:3,
            borderColor:'#fff',
            hoverOffset: 10
        }]
    },
    options:{
        plugins:{
            legend:{ 
                position:'bottom', 
                labels:{ 
                    color: themeBg, 
                    padding:20,
                    font: {
                        size: 13,
                        weight: '600'
                    }
                } 
            },
            tooltip:{ 
                callbacks:{ 
                    label:function(ctx){ 
                        return ctx.label + ': ' + ctx.raw; 
                    } 
                },
                backgroundColor: themeBg,
                titleColor: themeFont,
                bodyColor: themeFont,
                borderColor: themeBg,
                borderWidth: 2
            }
        }
    }
});

// Dynamic Revenue Filter
document.getElementById('revenueFilter').addEventListener('change',function(){
    fetch(`?ajax_revenue=1&filter=${this.value}`)
    .then(res=>res.json())
    .then(data=>{
        lineChart.data.labels = data.labels;
        lineChart.data.datasets[0].data = data.values;
        lineChart.update();
    });
});

setInterval(() => {
  fetch('session_check.php')
    .then(res => res.json())
    .then(data => {
      if (!data.active) {
        alert("Your session has expired or you have logged out in another tab.");
        window.location.href = "login_admins.html";
      }
    });
}, 15000); // check every 15 seconds

// Notification functions removed
</script>

<?php
// AJAX handler for revenue filter (same file)
if(isset($_GET['ajax_revenue'])){
    $filter = $_GET['filter'] ?? 'week';
    switch($filter){
        case 'month':
            $sql = "SELECT DATE(co.updated_at) AS order_date, co.total_price AS sales, co.orderID
                    FROM confirmed_order co
                    WHERE co.order_status = 'Complete'
                    AND MONTH(co.updated_at)=MONTH(CURRENT_DATE())
                    ORDER BY co.updated_at ASC";
            break;
        case 'year':
            $sql = "SELECT DATE(co.updated_at) AS order_date, co.total_price AS sales, co.orderID
                    FROM confirmed_order co
                    WHERE co.order_status = 'Complete'
                    AND YEAR(co.updated_at)=YEAR(CURRENT_DATE())
                    ORDER BY co.updated_at ASC";
            break;
        default: // week
            $sql = "SELECT DATE(co.updated_at) AS order_date, co.total_price AS sales, co.orderID
                    FROM confirmed_order co
                    WHERE co.order_status = 'Complete'
                    AND YEARWEEK(co.updated_at,1)=YEARWEEK(CURRENT_DATE(),1)
                    ORDER BY co.updated_at ASC";
    }
    $res = mysqli_query($conn,$sql);
    $labels=[];$values=[];
    while($row=mysqli_fetch_assoc($res)){
        // Each completed order is a separate point
        $labels[] = $row['order_date'] . ' (Order #' . $row['orderID'] . ')';
        $values[] = $row['sales'];
    }
    echo json_encode(['labels'=>$labels,'values'=>$values]);
    exit;
}
?>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="logout-modal-overlay" style="display: none;">
  <div class="logout-modal">
    <div class="logout-modal-icon">
      <i class="fa-solid fa-right-from-bracket"></i>
    </div>
    <h3>Confirm Logout</h3>
    <p>Are you sure you want to logout?</p>
    <div class="logout-modal-buttons">
      <button onclick="confirmLogout()" class="btn-logout">Logout</button>
      <button onclick="closeLogoutModal()" class="btn-cancel">Cancel</button>
    </div>
  </div>
</div>

<style>
.logout-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 10000;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.logout-modal {
  background: white;
  padding: 30px;
  border-radius: 12px;
  text-align: center;
  width: 90%;
  max-width: 380px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
  animation: slideInModal 0.3s ease;
  position: relative;
}

@keyframes slideInModal {
  from { transform: scale(0.9) translateY(-20px); opacity: 0; }
  to { transform: scale(1) translateY(0); opacity: 1; }
}

.logout-modal-icon {
  width: 60px;
  height: 60px;
  margin: 0 auto 20px;
  background: rgba(102, 126, 234, 0.15);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logout-modal-icon i {
  font-size: 28px;
  color: var(--theme-bg);
}

.logout-modal h3 {
  margin: 0 0 10px;
  font-size: 20px;
  font-weight: 600;
  color: #333;
}

.logout-modal p {
  font-size: 14px;
  margin: 0 0 25px;
  color: #999;
  line-height: 1.5;
}

.logout-modal-buttons {
  display: flex;
  gap: 10px;
  justify-content: center;
}

.logout-modal-buttons button {
  flex: 1;
  padding: 12px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-logout {
  background: var(--theme-bg);
  color: white;
  border: none;
}

.btn-logout:hover {
  opacity: 0.9;
  transform: translateY(-2px);
}

.btn-cancel {
  background: transparent;
  color: #666;
  border: 2px solid #ddd;
}

.btn-cancel:hover {
  border-color: #999;
  color: #333;
  transform: translateY(-2px);
}

@media (max-width: 480px) {
  .logout-modal {
    padding: 25px 20px;
    max-width: 320px;
  }
  
  .logout-modal h3 {
    font-size: 18px;
  }
  
  .logout-modal p {
    font-size: 14px;
  }
  
  .logout-modal-buttons {
    flex-direction: column;
  }
  
  .logout-modal-buttons button {
    width: 100%;
  }
}
</style>

<script>
function showLogoutModal(event) {
  event.preventDefault();
  document.getElementById('logoutModal').style.display = 'flex';
}

function closeLogoutModal() {
  document.getElementById('logoutModal').style.display = 'none';
}

function confirmLogout() {
  window.location.href = 'logout.php';
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
  const modal = document.getElementById('logoutModal');
  if (e.target === modal) {
    closeLogoutModal();
  }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeLogoutModal();
  }
});
</script>

</body>
</html>
