<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== TRUE) {
  echo "<script>window.location.href='./login.php';</script>";
  exit;
}
require_once "./db_conn.php";

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
  $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
  $id = $_SESSION["id"];
  $sql = "UPDATE users SET password='$new_pass' WHERE id=$id";
  if ($link->query($sql) === TRUE) {
    $msg = "✅ تم تغيير كلمة المرور بنجاح";
  } else {
    $msg = "❌ خطأ: " . $link->error;
  }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>الإعدادات</title>
  <link href="./css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet" href="./css/main.css">
     <link rel="shortcut icon" href="./img/logo.jpg" type="image/x-icon">

</head>
<body>
<?php include('navbar.php'); ?>
<div class="container mt-4">
  <h2>⚙️ الإعدادات</h2>
  <?php if (!empty($msg)) echo "<div class='alert alert-info'>$msg</div>"; ?>
  <form method="post" class="card p-3">
    <h5>تغيير كلمة المرور</h5>
    <input type="password" name="new_password" class="form-control mb-2" placeholder="كلمة المرور الجديدة" required>
    <button type="submit" name="change_password" class="btn btn-primary">تغيير</button>
  </form>
</div>
</body>
</html>
