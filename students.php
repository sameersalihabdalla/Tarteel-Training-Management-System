<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== TRUE) {
  echo "<script>window.location.href='./login.php';</script>";
  exit;
}
require_once "./db_conn.php";

$msg = "";

/* إضافة طالب جديد */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_student'])) {
  $name = $_POST['name'];
  $national_id = $_POST['national_id'];
  $phone = $_POST['phone'];
  $email = $_POST['email'];

  $sql = "INSERT INTO students (name, national_id, phone, email) VALUES ('$name','$national_id','$phone','$email')";
  if ($link->query($sql) === TRUE) {
    $msg = "✅ تم إضافة الطالب بنجاح";
  } else {
    $msg = "❌ خطأ: " . $link->error;
  }
}

/* تعديل طالب */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_student'])) {
  $id = $_POST['id'];
  $name = $_POST['name'];
  $national_id = $_POST['national_id'];
  $phone = $_POST['phone'];
  $email = $_POST['email'];

  $sql = "UPDATE students SET name='$name', national_id='$national_id', phone='$phone', email='$email' WHERE id=$id";
  $link->query($sql);
  $msg = "✏️ تم تعديل بيانات الطالب بنجاح";
}

/* حذف طالب */
if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  $sql = "DELETE FROM students WHERE id=$id";
  $link->query($sql);
  header("Location: students.php");
  exit;
}

/* البحث والفلترة */
$where = "";
if (isset($_GET['search']) && $_GET['search'] != "") {
  $search = $link->real_escape_string($_GET['search']);
  $where = "WHERE s.name LIKE '%$search%' OR s.national_id LIKE '%$search%' OR s.phone LIKE '%$search%' OR s.email LIKE '%$search%'";
}
if (isset($_GET['course']) && $_GET['course'] != "") {
  $course = intval($_GET['course']);
  $where .= ($where ? " AND" : "WHERE") . " e.course_id=$course";
}
if (isset($_GET['status']) && $_GET['status'] != "") {
  $status = $link->real_escape_string($_GET['status']);
  if ($status == "مدفوع بالكامل") {
    $where .= ($where ? " AND" : "WHERE") . " (SUM(p.amount) >= SUM(e.custom_fee))";
  } elseif ($status == "متبقي عليه") {
    $where .= ($where ? " AND" : "WHERE") . " (SUM(p.amount) < SUM(e.custom_fee))";
  }
}

/* جلب قائمة الطلاب مع إحصائيات الدفع */
$sql = "SELECT s.*, 
       IFNULL(SUM(p.amount),0) AS total_paid, 
       IFNULL(SUM(e.custom_fee),0) AS total_fee,
       (IFNULL(SUM(e.custom_fee),0) - IFNULL(SUM(p.amount),0)) AS remaining,
       GROUP_CONCAT(DISTINCT c.title SEPARATOR ', ') AS course,
       MAX(p.payment_date) AS last_payment_date
FROM students s
LEFT JOIN enrollments e ON s.id=e.student_id
LEFT JOIN payments p ON e.id=p.enrollment_id
LEFT JOIN courses c ON e.course_id=c.id
$where
GROUP BY s.id
ORDER BY s.name
";
$result = $link->query($sql);

$courses = $link->query("SELECT * FROM courses ORDER BY title");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>إدارة الطلاب</title>
  <link href="./css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./css/main.css">
  <link rel="shortcut icon" href="./img/logo.jpg" type="image/x-icon">
</head>
<body>
<?php include('navbar.php'); ?>

<div class="container mt-4">
  <h2 class="mb-4">👨‍🎓 إدارة الطلاب</h2>

  <?php if (!empty($msg)) echo "<div class='alert alert-info'>$msg</div>"; ?>

  <!-- نموذج إضافة طالب جديد -->
  <div class="card mb-4">
    <div class="card-header">➕ إضافة طالب جديد</div>
    <div class="card-body">
      <form method="post" action="students.php">
        <div class="row">
          <div class="col-md-3 mb-2">
            <input type="text" name="name" class="form-control" placeholder="اسم الطالب" required>
          </div>
          <div class="col-md-3 mb-2">
            <input type="text" name="national_id" class="form-control" placeholder="الرقم الوطني">
          </div>
          <div class="col-md-3 mb-2">
            <input type="text" name="phone" class="form-control" placeholder="رقم الهاتف (واتساب)" required>
          </div>
          <div class="col-md-3 mb-2">
            <input type="email" name="email" class="form-control" placeholder="البريد الإلكتروني">
          </div>
        </div>
        <button type="submit" name="add_student" class="btn btn-primary mt-2">إضافة</button>
      </form>
    </div>
  </div>

  <!-- نموذج البحث والفلترة -->
  <form method="get" class="card p-3 mb-4">
    <h5>🔍 البحث والفلترة</h5>
    <div class="row">
      <div class="col-md-4 mb-2">
        <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم أو الرقم الوطني أو الهاتف أو البريد">
      </div>
      <div class="col-md-3 mb-2">
        <select name="course" class="form-control">
          <option value="">اختر الدورة</option>
          <?php while($c=$courses->fetch_assoc()){ ?>
            <option value="<?= $c['id'] ?>"><?= $c['title'] ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="col-md-3 mb-2">
        <select name="status" class="form-control">
          <option value="">اختر حالة الدفع</option>
          <option value="مدفوع بالكامل">مدفوع بالكامل</option>
          <option value="متبقي عليه">متبقي عليه</option>
        </select>
      </div>
      <div class="col-md-2 mb-2">
        <button type="submit" class="btn btn-primary w-100">بحث / فلترة</button>
      </div>
    </div>
  </form>

  <!-- جدول عرض الطلاب -->
  <h3>📋 قائمة الطلاب</h3>
  <table class="table table-bordered table-striped">
    <thead class="table-dark">
      <tr>
        <th>الاسم</th>
        <th>الرقم الوطني</th>
        <th>الهاتف</th>
        <th>البريد الإلكتروني</th>
        <th>إجمالي الرسوم</th>
        <th>مدفوع</th>
        <th>المتبقي</th>
        <th>إجراءات</th>
      </tr>
    </thead>
    <tbody>
      <?php
      if ($result->num_rows > 0) {

      while($row = $result->fetch_assoc()) {

// تحديد حالة الدفع
if ($row['total_paid'] >= $row['total_fee']) {
    $payment_status = "مدفوع بالكامل";
} elseif ($row['total_paid'] > 0) {
    $payment_status = "مدفوع جزئيًا";
} else {
    $payment_status = "غير مدفوع";
}


      // بناء الرسالة
$text  = $row['name'].urlencode("\n\n");
$text .= "**فاتورة الدورة: _".$row['course']."_**".urlencode("\n");
$text .= "_*إجمالي الرسوم:*_ *".number_format($row['total_fee'], 2, '.', ',')."* ج".urlencode("\n");
$text .= "_*مدفوع:*_ *".number_format($row['total_paid'], 2, '.', ',')."* ج".urlencode("\n");
$text .= "_*المتبقي:*_ *".number_format($row['remaining'], 2, '.', ',')."* ج".urlencode("\n");
$text .= "_*الحالة:*_ ".$payment_status.urlencode("\n");

// معالجة التاريخ
if (!empty($row['last_payment_date'])) {
    $text .= "_*آخر عملية دفع بتاريخ:*_ ".date("d-m-Y", strtotime($row['last_payment_date'])).urlencode("\n");
} else {
    $text .= "_*آخر عملية دفع بتاريخ:*_ لا توجد مدفوعات".urlencode("\n");
}

// زر واتساب

// إذا كان هناك تاريخ دفع



  echo "<tr>";
  echo "<td>".$row['name']."</td>";
  echo "<td>".$row['national_id']."</td>";
  echo "<td>".$row['phone']."</td>";
  echo "<td>".$row['email']."</td>";
  echo "<td>".$row['total_fee']."</td>";
  echo "<td>".$row['total_paid']."</td>";
  echo "<td>".$row['remaining']."</td>";
  echo "<td>
          <button class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#editModal'
            onclick=\"editStudent('".$row['id']."','".$row['name']."','".$row['national_id']."','".$row['phone']."','".$row['email']."')\">✏️ تعديل</button>
      ";

echo'<a href="https://wa.me/249'.substr($row['phone'],1).'?text='.$text.'" target="_blank" class="btn btn-success btn-sm">📲 إرسال واتساب</a>';
      echo"
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
<!-- نافذة تعديل بيانات الطالب -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content p-3">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">✏️ تعديل بيانات الطالب</h5>
        <!-- زر إغلاق في أعلى المودال -->
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
      </div>
      <div class="modal-body">
        <form method="post">
          <input type="hidden" name="id" id="edit_id">
          <input type="text" name="name" id="edit_name" class="form-control mb-2" placeholder="اسم الطالب" required>
          <input type="text" name="national_id" id="edit_national_id" class="form-control mb-2" placeholder="الرقم الوطني">
          <input type="text" name="phone" id="edit_phone" class="form-control mb-2" placeholder="رقم الهاتف" required>
          <input type="email" name="email" id="edit_email" class="form-control mb-2" placeholder="البريد الإلكتروني">
          <div class="d-flex justify-content-between">
            <button type="submit" name="edit_student" class="btn btn-primary">💾 حفظ التعديلات</button>
            <!-- زر إلغاء يغلق المودال -->
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- تضمين Bootstrap JS من الـ CDN -->
<script src="./js/bootstrap.bundle.min.js"></script>

<script>
function editStudent(id,name,national_id,phone,email){
  document.getElementById('edit_id').value=id;
  document.getElementById('edit_name').value=name;
  document.getElementById('edit_national_id').value=national_id;
  document.getElementById('edit_phone').value=phone;
  document.getElementById('edit_email').value=email;
  // لا حاجة لاستدعاء style.display هنا، Bootstrap يتكفل بفتح وإغلاق المودال
}
</script>
</body>
</html>
