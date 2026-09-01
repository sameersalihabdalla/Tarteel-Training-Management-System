<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== TRUE) {
  echo "<script>window.location.href='./login.php';</script>";
  exit;
}
require_once "./db_conn.php";

$msg = "";

/* إضافة دورة جديدة */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_course'])) {
  $title = $_POST['title'];
  $start_date = $_POST['start_date'];
  $end_date = $_POST['end_date'];
  $fee = $_POST['fee'];

  $sql = "INSERT INTO courses (title, start_date, end_date, fee) VALUES ('$title','$start_date','$end_date','$fee')";
  if ($link->query($sql) === TRUE) {
    $msg = "✅ تم إضافة الدورة بنجاح";
  } else {
    $msg = "❌ خطأ: " . $link->error;
  }
}

/* تعديل دورة */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_course'])) {
  $id = $_POST['id'];
  $title = $_POST['title'];
  $start_date = $_POST['start_date'];
  $end_date = $_POST['end_date'];
  $fee = $_POST['fee'];

  $sql = "UPDATE courses SET title='$title', start_date='$start_date', end_date='$end_date', fee='$fee' WHERE id=$id";
  $link->query($sql);
  $msg = "✏️ تم تعديل بيانات الدورة بنجاح";
}

/* حذف دورة */
if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  $link->query("DELETE FROM courses WHERE id=$id");
  header("Location: courses.php");
  exit;
}

/* البحث والفلترة */
$where = "";
if (isset($_GET['search']) && $_GET['search'] != "") {
  $search = $link->real_escape_string($_GET['search']);
  $where = "WHERE c.title LIKE '%$search%'";
}
if (isset($_GET['min_fee']) && $_GET['min_fee'] != "") {
  $min_fee = floatval($_GET['min_fee']);
  $where .= ($where ? " AND" : "WHERE") . " c.fee >= $min_fee";
}
if (isset($_GET['max_fee']) && $_GET['max_fee'] != "") {
  $max_fee = floatval($_GET['max_fee']);
  $where .= ($where ? " AND" : "WHERE") . " c.fee <= $max_fee";
}

/* الاستعلام مع إحصائيات */
$sql = "SELECT c.*, 
               COUNT(e.id) AS students_count,
               IFNULL(SUM(p.amount),0) AS total_paid,
               (c.fee*COUNT(e.id) - IFNULL(SUM(p.amount),0)) AS remaining
        FROM courses c
        LEFT JOIN enrollments e ON c.id=e.course_id
        LEFT JOIN payments p ON e.id=p.enrollment_id
        $where
        GROUP BY c.id
        ORDER BY c.id DESC";
$result = $link->query($sql);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>إدارة الدورات</title>
  <link href="./css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./css/main.css">
     <link rel="shortcut icon" href="./img/logo.jpg" type="image/x-icon">

</head>
<body>
<?php include('navbar.php'); ?>

<div class="container mt-4">
  <h2 class="mb-4">📚 إدارة الدورات</h2>

  <?php if (!empty($msg)) echo "<div class='alert alert-info'>$msg</div>"; ?>

  <!-- نموذج إضافة دورة جديدة -->


  <!-- نموذج إضافة دورة جديدة -->
<div class="card mb-4">
  <div class="card-header">➕ إضافة دورة جديدة</div>
  <div class="card-body">
    <form method="post" action="courses.php">
      <div class="row">
        <div class="col-md-3 mb-2">
          <input type="text" name="title" class="form-control" placeholder="عنوان الدورة" required>
        </div>
        <div class="col-md-3 mb-2">
          <input type="date" name="start_date" class="form-control" placeholder="تاريخ البداية" required>
        </div>
        <div class="col-md-3 mb-2">
          <input type="date" name="end_date" class="form-control" placeholder="تاريخ النهاية" required>
        </div>
        <div class="col-md-3 mb-2">
          <input type="number" step="0.01" name="fee" class="form-control" placeholder="الرسوم" required>
        </div>
      </div>
      <button type="submit" name="add_course" class="btn btn-primary mt-2">إضافة</button>
    </form>
  </div>
</div>



  <!-- نموذج البحث والفلترة -->
  <!-- نموذج البحث والفلترة -->
<form method="get" class="card p-3 mb-4">
  <h5>🔍 البحث والفلترة</h5>
  <div class="row">
    <div class="col-md-4 mb-2">
      <input type="text" name="search" class="form-control" placeholder="ابحث بعنوان الدورة">
    </div>
    <div class="col-md-4 mb-2">
      <input type="number" step="0.01" name="min_fee" class="form-control" placeholder="الرسوم من">
    </div>
    <div class="col-md-4 mb-2">
      <input type="number" step="0.01" name="max_fee" class="form-control" placeholder="الرسوم إلى">
    </div>
  </div>
  <button type="submit" class="btn btn-primary mt-2">بحث / فلترة</button>
</form>


  <!-- جدول عرض الدورات -->
  <h3>📋 قائمة الدورات</h3>
  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>العنوان</th>
        <th>تاريخ البداية</th>
        <th>تاريخ النهاية</th>
        <th>الرسوم</th>
        <th>عدد الطلاب</th>
        <th>مدفوع</th>
        <th>المتبقي</th>
        <th>إجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php
      if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
          echo "<tr>";
          echo "<td>".$row['title']."</td>";
          echo "<td>".$row['start_date']."</td>";
          echo "<td>".$row['end_date']."</td>";
          echo "<td>".$row['fee']."</td>";
          echo "<td>".$row['students_count']."</td>";
          echo "<td>".$row['total_paid']."</td>";
          echo "<td>".$row['remaining']."</td>";
          echo "<td>
                  <button class='btn btn-warning btn-sm' onclick=\"editCourse('".$row['id']."','".$row['title']."','".$row['start_date']."','".$row['end_date']."','".$row['fee']."')\">✏️ تعديل</button>
                  <a href='courses.php?delete=".$row['id']."' class='btn btn-danger btn-sm' onclick=\"return confirm('هل أنت متأكد من الحذف؟')\">🗑️ حذف</a>
                </td>";
          echo "</tr>";
        }
      } else {
        echo "<tr><td colspan='8' class='text-center'>لا توجد بيانات</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>

<!-- نافذة تعديل -->
<div id="editModal" class="modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content p-3">
      <h5>تعديل بيانات الدورة</h5>
      <form method="post">
        <input type="hidden" name="id" id="edit_id">
        <input type="text" name="title" id="edit_title" class="form-control mb-2" required>
        <input type="date" name="start_date" id="edit_start_date" class="form-control mb-2" required>
        <input type="date" name="end_date" id="edit_end_date" class="form-control mb-2" required>
        <input type="number" step="0.01" name="fee" id="edit_fee" class="form-control mb-2" required>
        <button type="submit" name="edit_course" class="btn btn-primary">💾 حفظ التعديلات</button>
      </form>
    </div>
  </div>
</div>

<script>
function editCourse(id,title,start_date,end_date,fee){
  document.getElementById('edit_id').value=id;
  document.getElementById('edit_title').value=title;
  document.getElementById('edit_start_date').value=start_date;
  document.getElementById('edit_end_date').value=end_date;
  document.getElementById('edit_fee').value=fee;
  document.getElementById('editModal').style.display='block';
}
</script>
</body>
</html>
