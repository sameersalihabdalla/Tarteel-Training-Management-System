<?php
$host = "localhost";
$user = "root";       // ضع اسم المستخدم الخاص بقاعدة البيانات
$password = "";       // ضع كلمة المرور الخاصة بقاعدة البيانات
$dbname = "myproject"; // ضع اسم قاعدة البيانات

$link = new mysqli($host, $user, $password, $dbname);

if ($link->connect_error) {
    die("فشل الاتصال: " . $link->connect_error);
}
?>
