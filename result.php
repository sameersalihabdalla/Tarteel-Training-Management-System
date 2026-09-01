<?php
include('config.php');
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== TRUE) {
  echo "<script>window.location.href='./login.php';</script>";
  exit;
}

// استلام قيم البحث
$insured = isset($_GET['insured_name']) ? trim($_GET['insured_name']) : '';
$chassis = isset($_GET['chassis_number']) ? trim($_GET['chassis_number']) : '';
$plate   = isset($_GET['plate_number']) ? trim($_GET['plate_number']) : '';

// بناء شرط البحث
$whereClauses = [];
if ($insured !== '') {
    $whereClauses[] = "d.name LIKE '%" . $link->real_escape_string($insured) . "%'";
}
if ($chassis !== '') {
    $whereClauses[] = "d.chassis LIKE '%" . $link->real_escape_string($chassis) . "%'";
}
if ($plate !== '') {
    $whereClauses[] = "(CONCAT(d.plate_char, d.Plate_no) LIKE '%" . $link->real_escape_string($plate) . "%')";
}

$whereSQL = '';
if (count($whereClauses) > 0) {
    $whereSQL = "WHERE " . implode(" OR ", $whereClauses);
}

// إعداد الصفحات
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// الاستعلام مع اللوحة والشاسيه
$sql = "SELECT d.id,
               d.name AS insured_name,
               d.date,
               d.document,
               d.broker_name,
               c.name AS insurance_type,
               d.plate_char,
               d.Plate_no,
               d.chassis
        FROM document d
        LEFT JOIN cat c ON d.type = c.id
        $whereSQL
        ORDER BY d.id DESC
        LIMIT $limit OFFSET $offset";

$result = $link->query($sql);

// حساب عدد الصفوف الكلي
$countSql = "SELECT COUNT(*) AS total FROM document d LEFT JOIN cat c ON d.type = c.id $whereSQL";
$countResult = $link->query($countSql);
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

if (!$result) {
    die("خطأ في الاستعلام: " . $link->error);
}
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<meta charset="UTF-8">
<title>Insurance plus</title>
<link href="./css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
<link rel="stylesheet" href="./css/main.css">
<script src='./js/jquery-3.7.0.js'></script>
<script src='./js/sweetalert.js'></script>
   <link rel="shortcut icon" href="./img/logo.jpg" type="image/x-icon">

</head>
<body dir="rtl" class="p-4">
<?php include('navbar.php'); ?>
<div class="container m-3 p-3">

<h2 class="mb-4">نتائج البحث</h2>

<table class="table table-striped table-hover">
  <thead class="table-dark">
    <tr>
      <th>تاريخ الإضافة</th>
      <th>نوع التأمين</th>
      <th>اسم المؤمن له</th>
      <th>الوسيط</th>
      <th>رقم اللوحة</th>
      <th>رقم الشاسيه</th>
      <th>رقم الوثيقة</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if ($result->num_rows > 0) {
      while($row = $result->fetch_assoc()) {
        $docPath = "doc/" . $row['document'] . ".pdf";
        $plateFull = $row['plate_char'] . " " . $row['Plate_no'];
        echo "<tr>
                <td>".$row['date']."</td>
                <td>".$row['insurance_type']."</td>
                <td>".$row['insured_name']."</td>
                <td>".$row['broker_name']."</td>
                <td>".$plateFull."</td>
                <td>".$row['chassis']."</td>
                <td>
                  <span onclick='copyDoc(\"".$row['document']."\")' style='cursor:pointer;color:blue;' title=\"نسخ رقم الوثيقة\">".$row['document']."</span>
                  &nbsp;|&nbsp;
                  <a href='".$docPath."' target='_blank' class='btn btn-sm btn-outline-primary'>فتح</a>
                </td>
              </tr>";
      }
    } else {
      echo "<tr><td colspan='7'>لا توجد نتائج</td></tr>";
    }
    ?>
  </tbody>
</table>

<!-- تقسيم الصفحات -->
<nav>
  <ul class="pagination justify-content-center">
    <?php
    for ($i = 1; $i <= $totalPages; $i++) {
      $active = ($i == $page) ? "active" : "";
      echo "<li class='page-item $active'><a class='page-link' href='?page=$i&insured_name=$insured&chassis_number=$chassis&plate_number=$plate'>$i</a></li>";
    }
    ?>
  </ul>
</nav>

</div>
<script>
function copyDoc(docNumber) {
  navigator.clipboard.writeText(docNumber).then(() => {
    swal("تم النسخ!", "رقم الوثيقة: " + docNumber, "success");
  });
}
</script>

</body>
</html>
