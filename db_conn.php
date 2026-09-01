<?php
$host = "localhost";
$user = "root";       // اسم المستخدم الخاص بقاعدة البيانات
$password = "";       // كلمة المرور الخاصة بقاعدة البيانات
$dbname = "myproject"; // اسم قاعدة البيانات

$link = new mysqli($host, $user, $password, $dbname);

if ($link->connect_error) {
    die("فشل الاتصال: " . $link->connect_error);
}

# ضبط الترميز لدعم العربية
$link->set_charset("utf8mb4");
?>
