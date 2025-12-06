<?php
include 'connection.php';
require 'admin_session.php';
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
// Fetch one order currently in production
// --------------------
$sqlOrders = "
    SELECT orderID, status 
    FROM orders 
    WHERE status IN ('Order Processing', 'Quality Check', 'Order Pack') 
    ORDER BY updated_at DESC 
    LIMIT 1
";
$resOrders = mysqli_query($conn, $sqlOrders);
if (!$resOrders) die("SQL Error (Orders): " . mysqli_error($conn));
$orderData = mysqli_fetch_assoc($resOrders);
$orderId = $orderData['id'] ?? null;

// Initialize totals
$totalOrdered = $totalMade = $totalGoods = $totalDefect = 0;

if ($orderId) {
    // --- Fetch total quantity ordered for this order ---
    $stmtOrdered = $conn->prepare("SELECT SUM(quantity) AS total_ordered FROM order_items WHERE order_id = ?");
    $stmtOrdered->bind_param("i", $orderId);
    $stmtOrdered->execute();
    $totalOrdered = $stmtOrdered->get_result()->fetch_assoc()['total_ordered'] ?? 0;
    $stmtOrdered->close();

    // --- Fetch goods and defects from employees ---
    $resTotals = $conn->query("SELECT SUM(output) AS total_goods, SUM(defects) AS total_defects, SUM(output+defects) AS total_made FROM employees");
    if ($resTotals && $resTotals->num_rows > 0) {
        $row = $resTotals->fetch_assoc();
        $totalGoods = $row['total_goods'] ?? 0;
        $totalDefect = $row['total_defects'] ?? 0;
        $totalMade = $row['total_made'] ?? 0;
    }
}

$sqlChart = "
    SELECT DATE(o.created_at) AS order_date, 
           SUM(oi.total_price) AS daily_sales
    FROM orders o
    JOIN order_items oi ON o.orderID = oi.order_id
    WHERE o.status = 'complete'
    GROUP BY DATE(o.created_at)
    ORDER BY DATE(o.created_at) ASC
";

$resChart = mysqli_query($conn, $sqlChart);

$chartData = [];
while ($row = mysqli_fetch_assoc($resChart)) {
    $chartData[] = $row;
}

$chartLabels = array_column($chartData, 'order_date');
$chartValues = array_column($chartData, 'daily_sales');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
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
      background: url('adminbg.png') no-repeat center center/cover;
      background-color: #5c5f66;
      color: #fff;
      height: 100vh;
      overflow: hidden;
    }

    .sidebar {
      padding-top: 60px;
      width: 230px;
      background-color: #d9d9d9;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .sidebar a {
      color: #000;
      text-decoration: none;
      padding: 15px 25px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
      transition: background 0.3s;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background-color: #bfbfbf;
    }

    .sidebar .bottom {
      padding-bottom: 20px;
    }

    .menu {
      list-style: none;
    }

    .menu li {
      padding: 15px 25px;
      font-weight: 500;
      color: #1a1a1a;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: 0.3s;
    }

    .menu li:hover, .menu li.active {
      background-color: #3b3d42;
      color: #fff;
    }

    .menu li i {
      font-size: 16px;
    }

    .bottom-menu {
      padding: 0 25px 20px;
    }

    .bottom-menu li {
      color: #1a1a1a;
      padding: 10px 0;
      cursor: pointer;
      transition: 0.3s;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .bottom-menu li:hover {
      color: #fff;
    }

    /* Main content */
    .main {
      flex: 1;
      padding: 30px;
      background-color: #5c5f66;
      overflow-y: auto;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
    }

    .header h2 {
        font-size: 22px;
        font-weight: 600;
        color: #fff;
    }

    .profile {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .profile img {
      width: 35px;
      height: 35px;
      border-radius: 50%;
    }

    .profile i {
      font-size: 18px;
    }

      .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 0 10px;
  }

  .header-actions {
    display: flex;
    align-items: center;
    gap: 20px;
  }

  .notification {
    position: relative;
    cursor: pointer;
    font-size: 20px;
    color: #fff;
    transition: color 0.3s;
  }

  .notification:hover {
    color: #4d90fe;
  }

  .notification .badge {
    position: absolute;
    top: -6px;
    right: -8px;
    background-color: #ff4d4f;
    color: #fff;
    font-size: 10px;
    font-weight: 600;
    border-radius: 50%;
    padding: 2px 5px;
  }

  .profile {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .profile img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid #4d90fe;
  }

  .profile span {
    font-weight: 500;
    color: #fff;
  }


    /* Cards */
    .cards {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 40px;
    }

    .card {
      background-color: #0a0a3d;
      padding: 20px;
      border-radius: 10px;
      text-align: center;
    }

    .card h3 {
      font-size: 2rem;
      margin-bottom: 10px;
    }

    .card p {
      font-size: 0.9rem;
      opacity: 0.8;
    }

    /* Charts */
    .charts {
      display: flex;
      gap: 20px;
      margin-top: 20px;
    }

    .chart-box {
      flex: 1;
      background-color: #0a0a3d;
      padding: 20px;
      border-radius: 10px;
    }

    .chart-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }

    .chart-header h3 {
      font-size: 1rem;
      font-weight: 600;
    }

    .dropdown {
      background-color: #4d90fe;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 5px 10px;
      cursor: pointer;
    }

    canvas {
      width: 100% !important;
      height: 280px !important;
    }

    ::-webkit-scrollbar {
      width: 0px;
      background: transparent;
    }
  </style>
</head>
<body>

<body>

<div class="sidebar">
  <div>
    <a href="admin_dashboard.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
    <a href="admin_employees.php"><i class="fa-solid fa-users"></i> Employees</a>
    <a href="admin_qualityCheck.php"><i class="fa-solid fa-check"></i> Quality Check</a>
    <a href="admin_orderStatus.php"><i class="fa-solid fa-box"></i> Order Status</a>
  </div>
  <div class="logout">
    <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</div>

<div class="main">
  <header class="header">
    <h2>Welcome back, <?php echo htmlspecialchars($supervisor_name); ?>!</h2>
    <div class="header-actions">
      <div class="notification">
        <i class="fa-solid fa-bell"></i>
        <span class="badge">3</span> <!-- Example notification count -->
      </div>
      <div class="profile">
        <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profile">
        <span><?php echo htmlspecialchars($supervisor_name); ?></span>
      </div>
    </div>
  </header>



<div class="cards">
  <div class="card">
    <h3><?php echo $orderData['id'] ?? '—'; ?></h3>
    <p><?php echo $orderData['status'] ?? 'No order in production'; ?></p>
  </div>
  <div class="card"><h3><?php echo $quotaDisplay; ?></h3><p>QUOTA (Made / Ordered)</p></div>
  <div class="card"><h3><?php echo $totalGoods ?: 0; ?></h3><p>GOODS</p></div>
  <div class="card"><h3><?php echo $totalDefect ?: 0; ?></h3><p>DEFECT</p></div>
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

<script>
// Line chart
let lineChartCtx = document.getElementById('lineChart').getContext('2d');
let lineChart = new Chart(lineChartCtx,{
    type:'line',
    data:{
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets:[{
            label:'Revenue',
            data: <?php echo json_encode($chartValues); ?>,
            borderColor:'#4d90fe',
            backgroundColor:'#4d90fe33',
            tension:0.4
        }]
    },
    options:{ plugins:{ legend:{ display:false } } }
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
            backgroundColor:['#4d90fe','#ff4d4f','#888'],
            borderWidth:2,
            borderColor:'#5c5f66'
        }]
    },
    options:{
        plugins:{
            legend:{ position:'bottom', labels:{ color:'#fff', padding:20 } },
            tooltip:{ callbacks:{ label:function(ctx){ return ctx.label + ': ' + ctx.raw; } } }
        }
    },
    plugins:[{
        id:'centerText',
        beforeDraw(chart){
            const {ctx, chartArea:{width,height}} = chart;
            ctx.save();
            ctx.font = 'bold 18px Poppins';
            ctx.fillStyle = '#fff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('<?php echo $totalMade; ?> / <?php echo $totalOrdered; ?>', width/2, height/2);
        }
    }]
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

<?php
// AJAX handler for revenue filter (same file)
if(isset($_GET['ajax_revenue'])){
    $filter = $_GET['filter'] ?? 'week';
    switch($filter){
        case 'month':
            $sql = "SELECT DATE(o.created_at) AS order_date,SUM(oi.total_price) AS sales
                    FROM orders o JOIN order_items oi ON o.id = oi.order_id
                    WHERE MONTH(o.created_at)=MONTH(CURRENT_DATE())
                    GROUP BY DATE(o.created_at)
                    ORDER BY order_date ASC";
            break;
        case 'year':
            $sql = "SELECT MONTH(o.created_at) AS month,SUM(oi.total_price) AS sales
                    FROM orders o JOIN order_items oi ON o.id = oi.order_id
                    WHERE YEAR(o.created_at)=YEAR(CURRENT_DATE())
                    GROUP BY MONTH(o.created_at)
                    ORDER BY month ASC";
            break;
        default: // week
            $sql = "SELECT DATE(o.created_at) AS order_date,SUM(oi.total_price) AS sales
                    FROM orders o JOIN order_items oi ON o.id = oi.order_id
                    WHERE YEARWEEK(o.created_at,1)=YEARWEEK(CURRENT_DATE(),1)
                    GROUP BY DATE(o.created_at)
                    ORDER BY order_date ASC";
    }
    $res = mysqli_query($conn,$sql);
    $labels=[];$values=[];
    while($row=mysqli_fetch_assoc($res)){
        $labels[] = $filter==='year'?date('F',mktime(0,0,0,$row['month'],10)):$row['order_date'];
        $values[] = $row['sales'];
    }
    echo json_encode(['labels'=>$labels,'values'=>$values]);
    exit;
}
?>


</body>
</html>