<?php
// PHP Logic จากไฟล์ที่ 1 และ 2

// 1. นำเข้า Config และเชื่อมต่อ DB (จากตัวที่ 1)
require __DIR__ . '/config_mysqli.php';
// config_mysqli.php จะสร้างตัวแปร $mysqli และจัดการ session_start() ให้แล้ว

// 2. ตรวจสอบการล็อกอิน (จากตัวที่ 1)
if (empty($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

// ----------------------------------------------------------------------
// 3. Logic การดึงข้อมูล Dashboard (จากตัวที่ 2)
// ใช้ $mysqli ที่ถูกสร้างจาก config_mysqli.php

function fetch_all($mysqli, $sql) {
    // ฟังก์ชันช่วยดึงข้อมูล
    $res = $mysqli->query($sql);
    if (!$res) { return []; } 
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    $res->free();
    return $rows;
}

// เตรียมข้อมูลสำหรับกราฟต่าง ๆ
// หมายเหตุ: โค้ดนี้สมมติว่าตารางข้อมูล (v_monthly_sales, fact_sales ฯลฯ) อยู่ในฐานข้อมูลที่ config_mysqli.php เชื่อมต่ออยู่
$monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
$category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
$topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products ORDER BY qty_sold DESC LIMIT 10"); // จำกัด 10 อันดับ
$payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
$newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");
$kpis = fetch_all($mysqli, "
    SELECT
      (SELECT SUM(net_amount) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
      (SELECT SUM(quantity) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
      (SELECT COUNT(DISTINCT customer_id) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
");
$kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];

// Helper for number format
function nf($n) { return number_format((float)$n, 2); }

?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Retail DW Dashboard (Secured)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        /* CSS ปรับปรุงตามโจทย์: ธีมสว่าง, สีตัวอักษร, ฟอนต์เป็นมิตร, โค้งมน */
        :root {
            --bg-color: #FFF5EE; /* สีพื้นหลังหลัก Seashell */
            --text-color: #2F4F4F; /* สีตัวอักษรหลัก Dark Slate Gray */
            --card-bg: #ffffff; /* สีพื้นหลังการ์ด */
            --sub-color: #778899; /* สีรอง Light Slate Gray */
            --primary-color: #2F4F4F; /* สีเน้น */
            --border-color: rgba(47, 79, 79, 0.15); /* สีเส้นขอบจาง ๆ */
        }
        
        body { 
            background: var(--bg-color); 
            color: var(--text-color); 
            /* ใช้ฟอนต์ที่ดูเป็นมิตร/โค้งมน: Prompt, Mitr, Itim, Comic Sans MS */
            font-family: 'Prompt', 'Mitr', 'Krub', 'Itim', 'Comic Sans MS', 'Chalkboard SE', sans-serif;
            min-height: 100vh;
        }
        
        /* องค์ประกอบหลัก: การ์ด */
        .card { 
            background: var(--card-bg); 
            border: 1px solid var(--border-color); 
            border-radius: 1.5rem; /* เพิ่มความโค้งมน */
            box-shadow: 0 5px 15px -5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-1px); /* ลดการเคลื่อนที่ลงเล็กน้อย */
            box-shadow: 0 6px 15px -3px rgba(0, 0, 0, 0.15);
        }
        .card h5 { 
            color: var(--primary-color); 
            font-weight: 700;
        }
        
        /* KPI และ Sub Text */
        .kpi { 
            font-size: 1.8rem;
            font-weight: 800; 
            color: #000000;
        }
        .sub { 
            color: var(--sub-color); 
            font-size: .85rem; 
            font-weight: 500;
        }
        
        /* Navbar และ Title */
        .navbar {
            background-color: var(--card-bg) !important;
            border-bottom: 2px solid var(--border-color);
            box-shadow: 0 2px 5px -2px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem !important; /* ลด margin-bottom */
        }
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 900;
            color: #000000 !important; /* สีดำตัวหนา */
        }
        /* จัดวาง Navbar Brand ให้อยู่กึ่งกลาง */
        .navbar-container {
             display: flex;
             justify-content: space-between;
             align-items: center;
             width: 100%;
        }
        .navbar-brand-wrapper {
             flex-grow: 1;
             text-align: center;
        }
        .user-info {
            min-width: 150px; /* ให้พื้นที่สำหรับ user info */
            text-align: right;
        }

        /* Grid Layout: ลด Gap ให้ชิดกันขึ้น */
        .grid { 
            display: grid; 
            gap: 1rem; /* ปรับลดจาก 1.5rem เป็น 1rem */
            grid-template-columns: repeat(12, 1fr); 
        }
        .col-12 { grid-column: span 12; }
        .col-6 { grid-column: span 6; }
        .col-4 { grid-column: span 4; }
        .col-8 { grid-column: span 8; }
        
        @media (max-width: 991px) {
            .col-6, .col-4, .col-8 { grid-column: span 12; }
        }
        
        canvas { 
            max-height: 380px; 
            background: #ffffff;
            padding: 0.5rem;
            border-radius: 1rem;
        }
        
        /* Main Header: ลด margin-bottom */
        .main-header {
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 0.8rem; /* ลด padding */
            margin-bottom: 1rem !important; /* ลด margin-bottom */
        }

    </style>
</head>
<body class="p-3 p-md-4"> <!-- ลด padding ของ Body -->
    <!-- ปรับปรุง Navbar ให้ Brand (Retail Dashboard) อยู่กึ่งกลาง -->
    <nav class="navbar navbar-expand-lg border-bottom">
      <div class="container-fluid navbar-container">
        
        <!-- Placeholder ซ้าย (ใช้เพื่อผลักดัน Brand ไปกึ่งกลาง) -->
        <div class="user-info d-flex align-items-center justify-content-start invisible">
            <span class="small">Hi, Placeholder</span>
            <a class="btn btn-outline-secondary btn-sm" href="#">Logout</a>
        </div>

        <!-- Brand Center (Retail Dashboard) -->
        <div class="navbar-brand-wrapper">
             <span class="navbar-brand">Retail Dashboard</span>
        </div>
        
        <!-- User Info ขวา -->
        <div class="user-info d-flex align-items-center gap-3 justify-content-end">
          <span class="small" style="color: var(--text-color);">Hi, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
          <a class="btn btn-outline-secondary btn-sm" style="color: var(--primary-color); border-color: var(--primary-color);" href="logout.php">Logout</a>
        </div>
      </div>
    </nav>

    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between mb-3 main-header"> <!-- ลด mb-4 เป็น mb-3 -->
            <h2 class="mb-0" style="color: var(--primary-color);">ยอดขาย (Retail DW) — Dashboard</h2>
            <span class="sub">แหล่งข้อมูล: MySQL (mysqli)</span>
        </div>

        <!-- KPI Cards -->
        <div class="grid mb-3"> <!-- ลด mb-4 เป็น mb-3 -->
            <div class="card p-4 col-4">
                <h5>ยอดขาย 30 วัน</h5>
                <div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div>
            </div>
            <div class="card p-4 col-4">
                <h5>จำนวนชิ้นขาย 30 วัน</h5>
                <div class="kpi"><?= number_format((int)$kpi['qty_30d']) ?> ชิ้น</div>
            </div>
            <div class="card p-4 col-4">
                <h5>จำนวนผู้ซื้อ 30 วัน</h5>
                <div class="kpi"><?= number_format((int)$kpi['buyers_30d']) ?> คน</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid">
            <!-- ยอดขายรายเดือน (Line Chart) -->
            <div class="card p-4 col-8">
                <h5 class="mb-3">ยอดขายรายเดือน (2 ปี)</h5>
                <canvas id="chartMonthly"></canvas>
            </div>
            
            <!-- สัดส่วนยอดขายตามหมวด (Doughnut Chart) -->
            <div class="card p-4 col-4">
                <h5 class="mb-3">สัดส่วนยอดขายตามหมวด</h5>
                <canvas id="chartCategory"></canvas>
            </div>
            
            <!-- Top 10 สินค้าขายดี (Bar Chart - Horizontal) -->
            <div class="card p-4 col-6">
                <h5 class="mb-3">Top 10 สินค้าขายดี</h5>
                <canvas id="chartTopProducts"></canvas>
            </div>
            
            <!-- ยอดขายตามภูมิภาค (Bar Chart) -->
            <div class="card p-4 col-6">
                <h5 class="mb-3">ยอดขายตามภูมิภาค</h5>
                <canvas id="chartRegion"></canvas>
            </div>
            
            <!-- วิธีการชำระเงิน (Pie Chart) -->
            <div class="card p-4 col-6">
                <h5 class="mb-3">วิธีการชำระเงิน</h5>
                <canvas id="chartPayment"></canvas>
            </div>
            
            <!-- ยอดขายรายชั่วโมง (Bar Chart) -->
            <div class="card p-4 col-6">
                <h5 class="mb-3">ยอดขายรายชั่วโมง</h5>
                <canvas id="chartHourly"></canvas>
            </div>
            
            <!-- ลูกค้าใหม่ vs ลูกค้าเดิม (Line Chart - 12 cols) -->
            <div class="card p-4 col-12">
                <h5 class="mb-3">ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h5>
                <canvas id="chartNewReturning"></canvas>
            </div>
        </div>
    </div>

<script>
// JavaScript Logic สำหรับ Chart.js (ปรับสีให้เข้ากับธีมสว่าง)
const primaryColor = '#2F4F4F'; // สีตัวอักษรหลัก
const gridColor = 'rgba(47, 79, 79, 0.1)'; // สีเส้น Grid จาง ๆ
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

// Utility: pick labels & values
const toXY = (arr, x, y) => ({ labels: arr.map(o => o[x]), values: arr.map(o => parseFloat(o[y])) });

// Chart Defaults (ปรับฟอนต์และสี default)
Chart.defaults.font.family = "'Prompt', 'Mitr', 'Krub', 'Itim', 'Comic Sans MS', 'Chalkboard SE', sans-serif";
Chart.defaults.color = primaryColor;

// Function to get common chart options for light theme
const getCommonOptions = (indexAxis = 'x') => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: { 
        legend: { 
            labels: { 
                color: primaryColor,
                font: { size: 12 }
            } 
        },
        tooltip: {
            backgroundColor: 'rgba(0,0,0,0.7)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderRadius: 8
        }
    }, 
    scales: {
        x: { 
            ticks: { color: primaryColor, maxTicksLimit: indexAxis === 'y' ? 10 : 15 }, 
            grid: { color: gridColor, drawBorder: false } 
        },
        y: { 
            ticks: { color: primaryColor }, 
            grid: { color: gridColor, drawBorder: false } 
        }
    }
});

// Monthly Chart (Line)
(() => {
    const {labels, values} = toXY(monthly, 'ym', 'net_sales');
    const options = getCommonOptions();
    options.scales.x.grid.display = false;
    
    new Chart(document.getElementById('chartMonthly'), {
        type: 'line',
        data: { 
            labels, 
            datasets: [{ 
                label: 'ยอดขาย (฿)', 
                data: values, 
                tension: .4, 
                fill: true, 
                backgroundColor: 'rgba(54, 162, 235, 0.4)',
                borderColor: 'rgb(54, 162, 235)',
                pointBackgroundColor: 'rgb(54, 162, 235)'
            }] 
        },
        options
    });
})();

// Category Chart (Doughnut)
(() => {
    const {labels, values} = toXY(category, 'category', 'net_sales');
    new Chart(document.getElementById('chartCategory'), {
        type: 'doughnut',
        data: { labels, datasets: [{ data: values }] },
        options: { 
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    position: 'bottom', 
                    labels: { color: primaryColor } 
                }
            } 
        }
    });
})();

// Top products Chart (Horizontal Bar)
(() => {
    const labels = topProducts.map(o => o.product_name);
    const qty = topProducts.map(o => parseInt(o.qty_sold));
    const options = getCommonOptions('y'); 
    options.indexAxis = 'y';
    
    new Chart(document.getElementById('chartTopProducts'), {
        type: 'bar',
        data: { 
            labels, 
            datasets: [{ 
                label: 'ชิ้นที่ขาย', 
                data: qty, 
                backgroundColor: 'rgba(255, 99, 132, 0.6)',
                borderColor: 'rgb(255, 99, 132)',
                borderWidth: 1,
                borderRadius: 8
            }] 
        },
        options
    });
})();

// Region Chart (Bar)
(() => {
    const {labels, values} = toXY(region, 'region', 'net_sales');
    const options = getCommonOptions();
    
    new Chart(document.getElementById('chartRegion'), {
        type: 'bar',
        data: { 
            labels, 
            datasets: [{ 
                label: 'ยอดขาย (฿)', 
                data: values,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgb(75, 192, 192)',
                borderWidth: 1,
                borderRadius: 8
            }] 
        },
        options
    });
})();

// Payment Chart (Pie)
(() => {
    const {labels, values} = toXY(payment, 'payment_method', 'net_sales');
    new Chart(document.getElementById('chartPayment'), {
        type: 'pie',
        data: { labels, datasets: [{ data: values }] },
        options: { 
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { 
                    position: 'bottom', 
                    labels: { color: primaryColor } 
                }
            } 
        }
    });
})();

// Hourly Chart (Bar)
(() => {
    const {labels, values} = toXY(hourly, 'hour_of_day', 'net_sales');
    const options = getCommonOptions();
    
    new Chart(document.getElementById('chartHourly'), {
        type: 'bar',
        data: { 
            labels, 
            datasets: [{ 
                label: 'ยอดขาย (฿)', 
                data: values,
                backgroundColor: 'rgba(153, 102, 255, 0.6)',
                borderColor: 'rgb(153, 102, 255)',
                borderWidth: 1,
                borderRadius: 8
            }] 
        },
        options
    });
})();

// New vs Returning Chart (Line)
(() => {
    const labels = newReturning.map(o => o.date_key);
    const newC = newReturning.map(o => parseFloat(o.new_customer_sales));
    const retC = newReturning.map(o => parseFloat(o.returning_sales));
    const options = getCommonOptions();
    options.scales.x.ticks.maxTicksLimit = 12;

    new Chart(document.getElementById('chartNewReturning'), {
        type: 'line',
        data: { labels,
            datasets: [
                { 
                    label: 'ลูกค้าใหม่ (฿)', 
                    data: newC, 
                    tension: .4, 
                    fill: false,
                    borderColor: 'rgb(255, 159, 64)', // สีส้ม
                    backgroundColor: 'rgba(255, 159, 64, 0.2)',
                    pointRadius: 3
                },
                { 
                    label: 'ลูกค้าเดิม (฿)', 
                    data: retC, 
                    tension: .4, 
                    fill: false,
                    borderColor: 'rgb(54, 162, 235)', // สีฟ้า
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    pointRadius: 3
                }
            ]
        },
        options
    });
})();
</script>

</body>
</html>
