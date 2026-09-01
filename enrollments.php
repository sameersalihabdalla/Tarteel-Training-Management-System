<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== TRUE) {
  echo "<script>window.location.href='./login.php';</script>";
  exit;
}
require_once "./db_conn.php";

$msg = "";

/* إضافة تسجيل جديد */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_enrollment'])) {
  $student_id = $_POST['student_id'];
  $course_id = $_POST['course_id'];
  $custom_fee = $_POST['custom_fee'];

  $stmt = $link->prepare("INSERT INTO enrollments (student_id, course_id, custom_fee, status) VALUES (?, ?, ?, 'مؤكد')");
  $stmt->bind_param("iid", $student_id, $course_id, $custom_fee);
  if ($stmt->execute()) {
    $msg = "✅ تم إضافة التسجيل بنجاح";
  } else {
    $msg = "❌ خطأ: " . $stmt->error;
  }
  $stmt->close();
}

/* تعديل تسجيل */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_enrollment'])) {
  $id = $_POST['id'];
  $custom_fee = $_POST['custom_fee'];
  $status = $_POST['status'];

  $stmt = $link->prepare("UPDATE enrollments SET custom_fee=?, status=? WHERE id=?");
  $stmt->bind_param("dsi", $custom_fee, $status, $id);
  $stmt->execute();
  $msg = "✏️ تم تعديل بيانات التسجيل بنجاح";
  $stmt->close();
}

/* حذف تسجيل */
if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  $stmt = $link->prepare("DELETE FROM enrollments WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  header("Location: enrollments.php");
  exit;
}

/* البحث والفلترة */
$where = "";
if (isset($_GET['student']) && $_GET['student'] != "") {
  $student = intval($_GET['student']);
  $where = "WHERE e.student_id=$student";
}
if (isset($_GET['course']) && $_GET['course'] != "") {
  $course = intval($_GET['course']);
  $where .= ($where ? " AND" : "WHERE") . " e.course_id=$course";
}
if (isset($_GET['status']) && $_GET['status'] != "") {
  $status = $link->real_escape_string($_GET['status']);
  $where .= ($where ? " AND" : "WHERE") . " e.status='$status'";
}

/* الاستعلام مع إحصائيات */
$sql = "SELECT e.*, s.name AS student, c.title AS course,
               IFNULL(SUM(p.amount),0) AS total_paid,
               (e.custom_fee - IFNULL(SUM(p.amount),0)) AS remaining
        FROM enrollments e
        JOIN students s ON e.student_id=s.id
        JOIN courses c ON e.course_id=c.id
        LEFT JOIN payments p ON e.id=p.enrollment_id
        $where
        GROUP BY e.id
        ORDER BY e.id DESC";
$result = $link->query($sql);

$students = $link->query("SELECT * FROM students ORDER BY name");
$courses = $link->query("SELECT * FROM courses ORDER BY title");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>إدارة التسجيلات</title>
  <link href="./css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./css/main.css">
  <link rel="shortcut icon" href="./img/logo.jpg" type="image/x-icon">
  <style>
    @media (min-width: 768px) {
      .form-inline-row { display: flex; gap: 10px; }
      .form-inline-row > * { flex: 1; }
    }
    @media (max-width: 767px) {
      .form-inline-row { display: block; }
    }
  </style>
</head>
<body>
<?php include('navbar.php'); ?>
<div class="container mt-4">
  <h2>📑 إدارة التسجيلات</h2>

  <?php if (!empty($msg)) echo "<div class='alert alert-info'>$msg</div>"; ?>

  <!-- نموذج إضافة تسجيل جديد -->
  <form method="post" class="card p-3 mb-4">
    <h5>➕ إضافة تسجيل جديد</h5>
    <div class="form-inline-row">
      <select name="student_id" class="form-control" required>
        <option value="">اختر الطالب</option>
        <?php while($s=$students->fetch_assoc()){ ?>
          <option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
        <?php } ?>
      </select>
      <select name="course_id" class="form-control" required>
        <option value="">اختر الدورة</option>
        <?php while($c=$courses->fetch_assoc()){ ?>
          <option value="<?= $c['id'] ?>"><?= $c['title'] ?></option>
        <?php } ?>
      </select>
      <input type="number" step="0.01" name="custom_fee" class="form-control" placeholder="الرسوم المخصصة" required>
      <button type="submit" name="add_enrollment" class="btn btn-primary">إضافة</button>
    </div>
  </form>

  <!-- نموذج البحث والفلترة -->
  <form method="get" class="card p-3 mb-4">
    <h5>🔍 البحث والفلترة</h5>
    <div class="form-inline-row">
      <select name="student" class="form-control">
        <option value="">اختر الطالب</option>
        <?php 
        $students2 = $link->query("SELECT * FROM students ORDER BY name");
        while($s=$students2->fetch_assoc()){ ?>
          <option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
        <?php } ?>
      </select>
      <select name="course" class="form-control">
        <option value="">اختر الدورة</option>
        <?php 
        $courses2 = $link->query("SELECT * FROM courses ORDER BY title");
        while($c=$courses2->fetch_assoc()){ ?>
          <option value="<?= $c['id'] ?>"><?= $c['title'] ?></option>
        <?php } ?>
      </select>
      <select name="status" class="form-control">
        <option value="">اختر الحالة</option>
        <option value="معلق">معلق</option>
        <option value="مؤكد">مؤكد</option>
        <option value="مكتمل">مكتمل</option>
        <option value="ملغى">ملغى</option>
      </select>
      <button type="submit" class="btn btn-primary">بحث / فلترة</button>
    </div>
  </form>

  <!-- جدول التسجيلات -->
  <h3>📋 قائمة التسجيلات</h3>
  <table class="table table-bordered">
    <thead class="table-dark">
      <tr>
        <th>الطالب</th>
        <th>الدورة</th>
        <th>الرسوم</th>
        <th>مدفوع</th>
        <th>المتبقي</th>
        <th>الحالة</th>
        <th>إجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php if($result->num_rows > 0){ 
        while($row=$result->fetch_assoc()){ ?>
          <tr>
            <td><?= $row['student'] ?></td>
            <td><?= $row['course'] ?></td>
            <td><?= $row['custom_fee'] ?></td>
            <td><?= $row['total_paid'] ?></td>
            <td><?= $row['remaining'] ?></td>
            <td><?= $row['status'] ?></td>
            <td>
              <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal" 
                onclick="fillEditForm('<?= $row['id'] ?>','<?= $row['custom_fee'] ?>','<?= $row['status'] ?>')">✏️ تعديل</button>
              <a href="enrollments.php?delete=<?= $row['id'] ?>" onclick="return confirm('هل أنت متأكد من الحذف؟')" class="btn btn-danger btn-sm">🗑️ حذف</a>
              <a href="payments.php?student_id=<?= $row['student_id'] ?>&course_id=<?= $row['course_id'] ?>" class="btn btn-success btn-sm">💵 دفع</a>
            </td>
          </tr>






                <?php } } else { ?>
          <tr><td colspan="7" class="text-center">لا توجد تسجيلات</td></tr>
      <?php } ?>
    </tbody>
  </table>
</div>

<!-- نافذة تعديل باستخدام Bootstrap Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-3">
      <h5 class="modal-title">✏️ تعديل بيانات التسجيل</h5>
      <form method="post">
        <input type="hidden" name="id" id="edit_id">
        <div class="form-inline-row mt-3">
          <input type="number" step="0.01" name="custom_fee" id="edit_fee" class="form-control" placeholder="الرسوم المخصصة" required>
          <select name="status" id="edit_status" class="form-control">
            <option value="معلق">معلق</option>
            <option value="مؤكد">مؤكد</option>
            <option value="مكتمل">مكتمل</option>
            <option value="ملغى">ملغى</option>
          </select>
          <button type="submit" name="edit_enrollment" class="btn btn-primary">💾 حفظ</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="./js/bootstrap.bundle.min.js"></script>
<script>
function fillEditForm(id, fee, status){
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_fee').value = fee;
  document.getElementById('edit_status').value = status;
}
</script>
</body>
</html>
