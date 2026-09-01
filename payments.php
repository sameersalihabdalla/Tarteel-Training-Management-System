<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== TRUE) {
  echo "<script>window.location.href='./login.php';</script>";
  exit;
}
require_once "./db_conn.php";

$msg = "";
$msg_type = "";

/* حذف إيصال */
if (isset($_GET['delete'])) {
  $delete_id = intval($_GET['delete']);
  $stmt = $link->prepare("DELETE FROM payments WHERE id=?");
  $stmt->bind_param("i", $delete_id);
  if ($stmt->execute()) {
    $msg = "🗑️ تم حذف الإيصال بنجاح";
    $msg_type = "success";
  } else {
    $msg = "❌ خطأ أثناء الحذف: " . $stmt->error;
    $msg_type = "error";
  }
  $stmt->close();
}



/* إضافة دفع */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pay'])) {

  $student_id = $_POST['student_id'];
  $course_id = $_POST['course_id'];
  $amount = $_POST['amount'];
  $method = $_POST['method'];
  $transaction_number = ($method == "تحويل بنكي") ? $_POST['transaction_number'] : NULL;

  $stmt = $link->prepare("SELECT id, custom_fee FROM enrollments WHERE student_id=? AND course_id=? AND status='مؤكد'");
  $stmt->bind_param("ii", $student_id, $course_id);
  $stmt->execute();
  $res = $stmt->get_result();

  if ($res->num_rows > 0) {
    $enrollment = $res->fetch_assoc();
    $enrollment_id = $enrollment['id'];

    $stmt2 = $link->prepare("INSERT INTO payments (enrollment_id, amount, payment_date, method, transaction_number) VALUES (?,?,NOW(),?,?)");
    $stmt2->bind_param("idss", $enrollment_id, $amount, $method, $transaction_number);
    if ($stmt2->execute()) {
      $last_id = $stmt2->insert_id;
      $year = date("Y");
      $receipt_number = "INV-" . $year . "-" . str_pad($last_id, 4, "0", STR_PAD_LEFT);

      $stmt3 = $link->prepare("UPDATE payments SET receipt_number=? WHERE id=?");
      $stmt3->bind_param("si", $receipt_number, $last_id);
      $stmt3->execute();

      $sum_sql = "SELECT SUM(amount) AS total_paid, e.custom_fee 
                  FROM payments p 
                  JOIN enrollments e ON p.enrollment_id=e.id 
                  WHERE e.id=?";
      $stmt4 = $link->prepare($sum_sql);
      $stmt4->bind_param("i", $enrollment_id);
      $stmt4->execute();
      $sum_result = $stmt4->get_result();
      $sum_row = $sum_result->fetch_assoc();

      $status = "غير مدفوع";
      if ($sum_row['total_paid'] >= $sum_row['custom_fee']) {
        $status = "مدفوع بالكامل";
      } elseif ($sum_row['total_paid'] > 0) {
        $status = "مدفوع جزئيًا";
      }

      $stmt5 = $link->prepare("UPDATE payments SET payment_status=? WHERE id=?");
      $stmt5->bind_param("si", $status, $last_id);
      $stmt5->execute();

      $remaining = $sum_row['custom_fee'] - $sum_row['total_paid'];
      $msg = "✅ تم الدفع بنجاح. رقم الإيصال: $receipt_number ( المتبقي: $remaining ج)";
      $msg_type = "success";
    } else {
      $msg = "❌ خطأ: " . $stmt2->error;
      $msg_type = "error";
    }
    $stmt2->close();
  } else {
    $msg = "⚠️ لا يوجد تسجيل للطالب رقم $student_id في الدورة رقم $course_id.";
    $msg_type = "warning";
  }
  $stmt->close();
}

/* جلب المدفوعات */
$sql = "SELECT p.*, s.name AS student, c.title AS course, e.custom_fee,
               (e.custom_fee - (SELECT IFNULL(SUM(amount),0) FROM payments WHERE enrollment_id=e.id)) AS remaining
        FROM payments p 
        JOIN enrollments e ON p.enrollment_id=e.id 
        JOIN students s ON e.student_id=s.id 
        JOIN courses c ON e.course_id=c.id
        ORDER BY p.id DESC";
$payments = $link->query($sql);

$students = $link->query("SELECT * FROM students ORDER BY name");
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>💵 إدارة مدفوعات الطلاب</title>
  <link href="./css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./css/main.css">
  <link rel="shortcut icon" href="./img/logo.jpg" type="image/x-icon">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
  <h2>💵 إدارة مدفوعات الطلاب</h2>

  <?php if (!empty($msg)) { ?>
    <script>
      Swal.fire({
        title: 'تنبيه',
        text: '<?= $msg ?>',
        icon: '<?= $msg_type ?>',
        confirmButtonText: 'موافق'
      });
    </script>
  <?php } ?>

  <!-- نموذج إضافة دفع جديد -->
  <form method="post" class="card p-3 mb-4">
    <h5>➕ إضافة عملية دفع</h5>
    <div class="form-inline-row">
      <select name="student_id" class="form-control" onchange="loadCourses(this.value)" required>
        <option value="">اختر الطالب</option>
        <?php while($s=$students->fetch_assoc()){ ?>
          <option value="<?= $s['id'] ?>"><?= $s['name'] ?></option>
        <?php } ?>
      </select>
      <select name="course_id" id="course_id" class="form-control" required>
        <option value="">اختر الدورة</option>
      </select>
      <input type="number" step="0.01" name="amount" class="form-control" placeholder="المبلغ" required>
      <select name="method" class="form-control" onchange="toggleTransaction(this.value)" required>
        <option value="">طريقة الدفع</option>
        <option value="نقدي">نقدي</option>
        <option value="تحويل بنكي">تحويل بنكي</option>
      </select>
      <input type="text" name="transaction_number" id="transaction_number" class="form-control" placeholder="رقم التحويل" style="display:none;">
      <button type="submit" name="pay" class="btn btn-success">💵 دفع</button>
    </div>
  </form>

  <!-- جدول المدفوعات -->
  <h3>📋 قائمة المدفوعات</h3>
  <div class="table-responsive">
    <table class="table table-bordered table-striped">
      <thead class="table-dark">
        <tr>
          <th>رقم الإيصال</th>
          <th>الطالب</th>
          <th>الدورة</th>
          <th>المبلغ</th>
          <th>طريقة الدفع</th>
          <th>رقم التحويل</th>
          <th>الحالة</th>
          <th>المتبقي حاليا</th>
          <th>التاريخ</th>
          <th>إجراءات</th>
        </tr>
      </thead>
      <tbody>
        <?php if($payments->num_rows > 0){ 
          while($row=$payments->fetch_assoc()){ ?>
            <tr>
              <td><?= $row['receipt_number'] ?></td>
              <td><?= $row['student'] ?></td>
              <td><?= $row['course'] ?></td>
              <td><?= $row['amount'] ?></td>
              <td><?= $row['method'] ?></td>
              <td><?= $row['transaction_number'] ?></td>
              <td><?= $row['payment_status'] ?></td>
              <td><?= $row['remaining'] ?></td>
              <td><?= $row['payment_date'] ?></td>
                            <td>
                <a href="payments.php?delete=<?= $row['id'] ?>" 
                   onclick="return confirm('هل أنت متأكد من الحذف؟')" 
                   class="btn btn-danger btn-sm">🗑️ حذف</a>
               
                   <a href="receipt.php?id=<?= $row['id'] ?>" 
                   
                   class="btn btn-danger btn-sm">🖨️ طباعة</a>


              </td>




            </tr>
        <?php } } else { ?>
            <tr><td colspan="10" class="text-center">لا توجد مدفوعات</td></tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>

<script src="./js/bootstrap.bundle.min.js"></script>
<script>
function toggleTransaction(method){
  document.getElementById('transaction_number').style.display = (method === "تحويل بنكي") ? 'block' : 'none';
}
function loadCourses(studentId){
  if(studentId === "") {
    document.getElementById('course_id').innerHTML = "<option value=''>اختر الدورة</option>";
    return;
  }
  var xhr = new XMLHttpRequest();
  xhr.open("GET", "get_courses.php?student_id=" + studentId, true);
  xhr.onload = function(){
    if(this.status == 200){
      document.getElementById('course_id').innerHTML = this.responseText;
    }
  };
  xhr.send();
}
</script>
</body>
</html>
