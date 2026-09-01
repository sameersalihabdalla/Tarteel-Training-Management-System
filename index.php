<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== TRUE) {
  echo "<script>window.location.href='./login.php';</script>";
  exit;
}
require_once "./db_conn.php";

/* النصوص */
$texts = [
    'dashboard_title' => '📊 لوحة المعلومات',
    'students_count'  => '👨‍🎓 عدد الطلاب',
    'courses_count'   => '📚 عدد الدورات',
    'today_paid'      => '💵 مدفوع اليوم',
    'month_paid'      => '💵 مدفوع هذا الشهر',
    'remaining_fees'  => '💰 الرسوم المتبقية',
    'cash_payments'   => '🪙 عدد المدفوعات كاش',
    'bank_transfers'  => '🏦 عدد التحويلات البنكية',
    'daily_chart'     => '📈 المدفوعات اليومية خلال الشهر',
];

/* إحصائيات عامة */
$total_students = $link->query("SELECT COUNT(*) AS c FROM students")->fetch_assoc()['c'];
$total_courses  = $link->query("SELECT COUNT(*) AS c FROM courses")->fetch_assoc()['c'];
$today_paid     = $link->query("SELECT IFNULL(SUM(amount),0) AS s FROM payments WHERE DATE(payment_date)=CURDATE()")->fetch_assoc()['s'];
$month_paid     = $link->query("SELECT IFNULL(SUM(amount),0) AS s FROM payments WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())")->fetch_assoc()['s'];
$remaining      = $link->query("SELECT IFNULL(SUM(e.custom_fee),0)-IFNULL(SUM(p.amount),0) AS r 
                                FROM enrollments e 
                                LEFT JOIN payments p ON e.id=p.enrollment_id")->fetch_assoc()['r'];
$cash_count     = $link->query("SELECT COUNT(*) AS c FROM payments WHERE method='كاش'")->fetch_assoc()['c'];
$bank_count     = $link->query("SELECT COUNT(*) AS c FROM payments WHERE method='تحويل بنكي'")->fetch_assoc()['c'];

/* بيانات المدفوعات اليومية للشهر */
$chart_data = [];
$res = $link->query("SELECT DATE(payment_date) AS d, SUM(amount) AS total 
                     FROM payments 
                     WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())
                     GROUP BY DATE(payment_date)");
while($row=$res->fetch_assoc()){
  $chart_data[$row['d']] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>الرئيسية</title>
  <link href="./css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./css/main.css">
  <link rel="shortcut icon" href="./img/logo.jpg" type="image/x-icon">
  <script src="./js/chart.js"></script>
  <script src="./js/sweetalert.js"></script>
</head>
<body>
<?php include('navbar.php'); ?>
<div class="container mt-4">
  <h2><?= $texts['dashboard_title'] ?></h2>

  <!-- بطاقات الإحصائيات -->
  <div class="row mt-4">
    <div class="col-md-3">
      <div class="card text-center bg-primary text-white">
        <div class="card-body">
          <h5><?= $texts['students_count'] ?></h5>
          <h3><?= $total_students ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center bg-success text-white">
        <div class="card-body">
          <h5><?= $texts['courses_count'] ?></h5>
          <h3><?= $total_courses ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center bg-info text-white">
        <div class="card-body">
          <h5><?= $texts['today_paid'] ?></h5>
          <h3><?= $today_paid ?> ج</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center bg-warning text-dark">
        <div class="card-body">
          <h5><?= $texts['month_paid'] ?></h5>
          <h3><?= $month_paid ?> ج</h3>
        </div>
      </div>
    </div>
  </div>

  <div class="row mt-4">
    <div class="col-md-4">
      <div class="card text-center bg-danger text-white">
        <div class="card-body">
          <h5><?= $texts['remaining_fees'] ?></h5>
          <h3><?= $remaining ?> ج</h3>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center bg-secondary text-white">
        <div class="card-body">
          <h5><?= $texts['cash_payments'] ?></h5>
          <h3><?= $cash_count ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card text-center bg-dark text-white">
        <div class="card-body">
          <h5><?= $texts['bank_transfers'] ?></h5>
          <h3><?= $bank_count ?></h3>
        </div>
      </div>
    </div>
  </div>

  <!-- رسم بياني للمدفوعات اليومية -->
  <div class="card mt-5">
    <div class="card-header"><?= $texts['daily_chart'] ?></div>
    <div class="card-body">
      <canvas id="paymentsChart"></canvas>
    </div>
  </div>
</div>

<script>
const ctx = document.getElementById('paymentsChart').getContext('2d');
const chartData = {
  labels: <?= json_encode(array_keys($chart_data)) ?>,
  datasets: [{
    label: 'المدفوعات اليومية (ج)',
    data: <?= json_encode(array_values($chart_data)) ?>,
    borderColor: 'rgba(75, 192, 192, 1)',
    backgroundColor: 'rgba(75, 192, 192, 0.2)',
    fill: true,
    tension: 0.3
  }]
};
new Chart(ctx, {
  type: 'line',
  data: chartData,
  options: {
    responsive: true,
    plugins: {
      legend: { display: true }
    },
    scales: {
      x: { title: { display: true, text: 'التاريخ' } },
      y: { title: { display: true, text: 'المبلغ (ج)' } }
    }
  }
});
</script>
</body>
</html>
