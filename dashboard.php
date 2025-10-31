<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['user_name'])) {
  header("Location: login.php");
  exit;
}

$DB_HOST = 'localhost';
$DB_USER = 's67160383';
$DB_PASS = 'sa3k4SXy';
$DB_NAME = 's67160383';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
  die('Database connection failed: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) return [];
  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;
  $res->free();
  return $rows;
}

$monthly      = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales ORDER BY ym");
$category     = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region       = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
$topProducts  = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products");
$payment      = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly       = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales ORDER BY hour_of_day");
$newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");

function nf($n) { return number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">

  <style>
  body {
    font-family: 'Prompt', sans-serif;
    background: linear-gradient(135deg, #ffe6f0 0%, #e3f2fd 100%);
    color: #333;
    min-height: 100vh;
    margin: 0;
    overflow-x: hidden;
    padding-top: 90px;
  }

  .navbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 1000;
    background: #ffffffee;
    backdrop-filter: blur(8px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 40px;
    border-bottom: 3px solid #ffd6e8;
  }
  .navbar-brand {
    font-weight: 700;
    color: #f06292 !important;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .nav-user {
    font-weight: 500;
    color: #333;
    margin-right: 10px;
  }
  .btn-logout {
    background: linear-gradient(90deg, #f48fb1, #f06292);
    border: none;
    color: white;
    border-radius: 12px;
    padding: 6px 18px;
    font-size: 15px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .btn-logout:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(240,98,146,0.3);
  }

  h2 {
    color: #f06292;
    font-weight: 700;
    margin-bottom: 25px;
  }

  .card {
    background: #fff;
    border-radius: 20px;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    padding: 25px;
    transition: transform 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
  }
  .card:hover { transform: translateY(-3px); }

  .grid {
    display: grid;
    gap: 1.8rem;
    grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
  }

  canvas {
    width: 100% !important;
    height: auto !important;
    aspect-ratio: 16 / 9;
    max-height: 320px;
    max-width: 95%;
    object-fit: contain;
  }

  @media (min-width: 1400px) {
    canvas { max-height: 380px; }
    .grid { gap: 2rem; }
  }

  @media (max-width: 768px) {
    canvas {
      aspect-ratio: 1 / 1;
      max-height: 260px;
    }
  }

  .floating-emoji {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    pointer-events: none;
    overflow: hidden;
    z-index: 0;
  }
  .floating-emoji span {
    position: absolute;
    top: -2rem;
    opacity: 0.8;
    animation: emojiFall linear forwards;
  }
  @keyframes emojiFall {
    0% { transform: translateY(0) rotate(0deg); opacity: 1; }
    50% { transform: translateY(60vh) rotate(180deg); opacity: 0.9; }
    100% { transform: translateY(110vh) rotate(360deg); opacity: 0; }
  }
  </style>
</head>
<body>

<nav class="navbar">
  <span class="navbar-brand">🥤 Retail Dashboard</span>
  <div class="d-flex align-items-center gap-3">
    <span class="nav-user">Hello! , <?= htmlspecialchars($_SESSION['user_name']) ?></span>
    <a href="logout.php" class="btn-logout">Log out</a>
  </div>
</nav>

<div class="floating-emoji" id="emojiArea"></div>

<div class="container-fluid px-4">
  <h2>Dashboard รายงานยอดขาย</h2>
  <div class="grid">
    <div class="card"><h5>ยอดขายรายเดือน</h5><canvas id="chartMonthly"></canvas></div>
    <div class="card"><h5>ยอดขายตามหมวดสินค้า</h5><canvas id="chartCategory"></canvas></div>
    <div class="card"><h5>Top 10 สินค้าขายดี</h5><canvas id="chartTopProducts"></canvas></div>
    <div class="card"><h5>ยอดขายตามภูมิภาค</h5><canvas id="chartRegion"></canvas></div>
    <div class="card"><h5>ช่องทางการชำระเงิน</h5><canvas id="chartPayment"></canvas></div>
    <div class="card"><h5>ยอดขายตามช่วงเวลา (ชั่วโมง)</h5><canvas id="chartHourly"></canvas></div>
    <div class="card" style="grid-column: span 2;"><h5>ลูกค้าใหม่ vs ลูกค้าเดิม</h5><canvas id="chartNewReturning"></canvas></div>
  </div>
</div>

<script>
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => +o[y]) });
const pastelColors = ['#f48fb1','#81d4fa','#a5d6a7','#ce93d8','#ffcc80','#b39ddb','#80cbc4','#e6ee9c'];

Chart.defaults.color = "#444";
Chart.defaults.font.family = "Prompt";

(()=>{const {labels,values}=toXY(monthly,'ym','net_sales');
new Chart(chartMonthly,{type:'line',data:{labels,datasets:[{label:'ยอดขาย (บาท)',data:values,borderColor:'#f48fb1',backgroundColor:'#f8bbd0',fill:true,tension:0.35,pointRadius:5,pointBackgroundColor:'#f06292'}]},options:{plugins:{legend:{labels:{color:'#f06292'}}},scales:{x:{ticks:{color:'#666'}},y:{ticks:{color:'#666'}}}}});})();

(()=>{const {labels,values}=toXY(category,'category','net_sales');
new Chart(chartCategory,{type:'doughnut',data:{labels,datasets:[{data:values,backgroundColor:pastelColors,borderWidth:1,borderColor:'#fff'}]},options:{plugins:{legend:{position:'bottom',labels:{color:'#444'}}}}});})();

(()=>{const labels=topProducts.map(o=>o.product_name);const qty=topProducts.map(o=>+o.qty_sold);
new Chart(chartTopProducts,{type:'bar',data:{labels,datasets:[{label:'จำนวนขาย',data:qty,backgroundColor:'#81d4fa',borderRadius:6}]},options:{indexAxis:'y',plugins:{legend:{labels:{color:'#444'}}},scales:{x:{ticks:{color:'#666'}},y:{ticks:{color:'#666'}}}}});})();

(()=>{const {labels,values}=toXY(region,'region','net_sales');
new Chart(chartRegion,{type:'bar',data:{labels,datasets:[{label:'ยอดขาย (บาท)',data:values,backgroundColor:'#a5d6a7',borderRadius:6}]},options:{plugins:{legend:{labels:{color:'#444'}}},scales:{x:{ticks:{color:'#666'}},y:{ticks:{color:'#666'}}}}});})();

(()=>{const {labels,values}=toXY(payment,'payment_method','net_sales');
new Chart(chartPayment,{type:'pie',data:{labels,datasets:[{data:values,backgroundColor:pastelColors}]},options:{plugins:{legend:{position:'bottom',labels:{color:'#444'}}}}});})();

(()=>{const {labels,values}=toXY(hourly,'hour_of_day','net_sales');
new Chart(chartHourly,{type:'bar',data:{labels,datasets:[{label:'ยอดขาย (บาท)',data:values,backgroundColor:'#f48fb1',borderRadius:6}]},options:{plugins:{legend:{labels:{color:'#444'}}},scales:{x:{ticks:{color:'#666'}},y:{ticks:{color:'#666'}}}}});})();

(()=>{const labels=newReturning.map(o=>o.date_key);const newC=newReturning.map(o=>+o.new_customer_sales);const retC=newReturning.map(o=>+o.returning_sales);
new Chart(chartNewReturning,{type:'line',data:{labels,datasets:[{label:'ลูกค้าใหม่ (บาท)',data:newC,borderColor:'#81d4fa',backgroundColor:'#b3e5fc',fill:true,tension:0.3,pointRadius:4},{label:'ลูกค้าเดิม (บาท)',data:retC,borderColor:'#f48fb1',backgroundColor:'#f8bbd0',fill:true,tension:0.3,pointRadius:4}]},options:{plugins:{legend:{labels:{color:'#444'}}},scales:{x:{ticks:{color:'#666'}},y:{ticks:{color:'#666'}}}}});})();

const emojis = ['☕','💖','🥐','✨','🍰','🩷','🥞','🧋'];
const emojiArea = document.getElementById('emojiArea');

function createEmoji() {
  const el = document.createElement('span');
  el.textContent = emojis[Math.floor(Math.random() * emojis.length)];
  el.style.left = Math.random() * 100 + 'vw';
  el.style.animationDuration = (4 + Math.random() * 6) + 's';
  el.style.fontSize = (1.5 + Math.random() * 1.5) + 'rem';
  emojiArea.appendChild(el);
  setTimeout(() => el.remove(), 8000);
}
setInterval(createEmoji, 700);
</script>
</body>
</html>
