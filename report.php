<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== TRUE) {
  echo "<script>window.location.href='./login.php';</script>";
  exit;
}
require_once "./db_conn.php";

/* فلترة حسب التاريخ والدورة */
$where = "";
if (isset($_GET['from_date']) && $_GET['from_date'] != "" && isset($_GET['to_date']) && $_GET['to_date'] != "") {
  $from = $_GET['from_date'];
  $to = $_GET['to_date'];
  $where = "WHERE p.payment_date BETWEEN '$from' AND '$to'";
}
if (isset($_GET['course_id']) && $_GET['course_id'] != "") {
  $course_id = intval($_GET['course_id']);
  $where .= ($where ? " AND" : "WHERE") . " e.course_id=$course_id";
}

/* الاستعلام */
$sql = "SELECT s.name AS student_name, c.title AS course_title, e.custom_fee, e.status,
               IFNULL(SUM(p.amount),0) AS total_paid,
               (e.custom_fee - IFNULL(SUM(p.amount),0)) AS balance
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN payments p ON e.id = p.enrollment_id
        $where
        GROUP BY e.id";
$result = $link->query($sql);

$courses = $link->query("SELECT * FROM courses ORDER BY title");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>التقارير</title>
  <link href="./css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./css/main.css">
     <link rel="shortcut icon" href="./img/logo.jpg" type="image/x-icon">

<style>

  @media print {
  body * {
    visibility: hidden; /* إخفاء كل العناصر */
  }
  .report-container, .report-container * {
    visibility: visible; /* إظهار محتوى التقرير فقط */
  }
  .report-container {
    position: absolute;
    font-family:"arial";
    left: 0;
    top: 0;
    width: 100%; /* ملء الصفحة */
  }
  #printBtn, #filterForm {
    display: none; /* إخفاء زر الطباعة والفلاتر عند الطباعة */
  }
}








table {
  font-size: 14px;
  border-collapse: collapse;
  width: 100%;
}

table th, table td {
  border: 1px solid #000;
  padding: 8px;
  text-align: center;
}

table thead {
  background-color: #343a40;
  color: #fff;
}

@media print {
  body {
    background: #fff;
  }
  #printBtn, #filterForm, .navbar {
    display: none; /* إخفاء العناصر غير المرغوبة */
  }
  .report-container {
    box-shadow: none;
    margin: 0;
    width: 100%;
  }
  table {
    font-size: 12pt; /* حجم خط مناسب للطباعة */
    border: 1px solid #000;
  }
  table th {
    padding:0,0,0,0;
    background-color: #e0e0e0 !important; /* خلفية خفيفة للرأس عند الطباعة */
    color: #000 !important;
    -webkit-print-color-adjust: exact; /* ضمان ظهور الألوان في الطباعة */
  }
  table td {
        padding:0,0,0,0;

    background-color: #fff !important;
    color: #000 !important;
  }
}

</style>
  </head>
<body>
<?php include('navbar.php'); ?>
<div class="container mt-4">

  <!-- نموذج الفلترة -->
  <form method="get" id="filterForm" class="card p-3 mb-4">
    <h5>🔍 البحث والفلترة</h5>
    <div class="row">
      <div class="col-md-4">
        <label>من تاريخ</label>
        <input type="date" name="from_date" class="form-control">
      </div>
      <div class="col-md-4">
        <label>إلى تاريخ</label>
        <input type="date" name="to_date" class="form-control">
      </div>
      <div class="col-md-4">
        <label>الدورة</label>
        <select name="course_id" class="form-control">
          <option value="">جميع الدورات</option>
          <?php while($c=$courses->fetch_assoc()){ ?>
            <option value="<?= $c['id'] ?>"><?= $c['title'] ?></option>
          <?php } ?>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary mt-3">تطبيق الفلترة</button>
  </form>

  <!-- زر الطباعة -->
  <button id="printBtn" class="btn btn-success mb-3" onclick="window.print()">🖨️ طباعة التقرير</button>

  <!-- التقرير -->
  <div class="report-container">
    <h2 class="text-center mb-4">📊 تقرير المدفوعات وكشف الحساب</h2>
    <table class="table table-bordered table-striped">
      <thead class="table-dark">
        <tr>
          <th>اسم الطالب</th>
          <th>الدورة</th>
          <th>الرسوم المخصصة</th>
          <th>الحالة</th>
          <th>إجمالي المدفوع</th>
          <th>الرصيد المتبقي</th>
        </tr>
      </thead>
      <tbody>
        <?php while($row=$result->fetch_assoc()){ 
          $status_text = ($row['balance'] <= 0) ? "خالص الرسوم" : "متبقي عليه";
          $watermark = ($row['balance'] <= 0) ? "سداد مكتمل" : "سداد جزئي";
        ?>
          <tr>
            <td><?= $row['student_name'] ?></td>
            <td><?= $row['course_title'] ?></td>
            <td><?= $row['custom_fee'] ?></td>
            <td><?= $status_text ?></td>
            <td><?= $row['total_paid'] ?></td>
            <td><?= ($row['balance'] <= 0) ? "0 (خالص)" : $row['balance'] ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
    <!-- العلامة المائية العامة للتقرير -->
    
  </div>
</div>
</body>
</html>
