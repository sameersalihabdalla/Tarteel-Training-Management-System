<?php
session_start();
require_once "./db_conn.php";

$id = $_GET['id'];
$sql = "SELECT p.*, s.name AS student, c.title AS course, e.custom_fee, e.id AS enrollment_id
        FROM payments p 
        JOIN enrollments e ON p.enrollment_id=e.id
        JOIN students s ON e.student_id=s.id
        JOIN courses c ON e.course_id=c.id
        WHERE p.id='$id'";
$result = $link->query($sql);
$row = $result->fetch_assoc();

$printed_by = $_SESSION["username"];

function tafqeet($number) {
    $number = floor($number);
    $words = [
        0=>"صفر",1=>"واحد",2=>"اثنان",3=>"ثلاثة",4=>"أربعة",5=>"خمسة",6=>"ستة",7=>"سبعة",8=>"ثمانية",9=>"تسعة",
        10=>"عشرة",11=>"أحد عشر",12=>"اثنا عشر",13=>"ثلاثة عشر",14=>"أربعة عشر",15=>"خمسة عشر",16=>"ستة عشر",
        17=>"سبعة عشر",18=>"ثمانية عشر",19=>"تسعة عشر",20=>"عشرون",30=>"ثلاثون",40=>"أربعون",50=>"خمسون",
        60=>"ستون",70=>"سبعون",80=>"ثمانون",90=>"تسعون"
    ];
    if ($number < 21) return $words[$number] . " جنيه سوداني";
    if ($number < 100) {
        $tens = intval($number/10)*10;
        $ones = $number % 10;
        return $words[$tens] . ($ones? " و" . $words[$ones]:"") . " جنيه سوداني";
    }
    return $number . " جنيه سوداني";
}

$sum_sql = "SELECT SUM(amount) AS total_paid, e.custom_fee 
            FROM payments p 
            JOIN enrollments e ON p.enrollment_id=e.id 
            WHERE e.id='".$row['enrollment_id']."'";
$sum_result = $link->query($sum_sql);
$sum_row = $sum_result->fetch_assoc();

$total_paid = $sum_row['total_paid'];
$total_fee  = $sum_row['custom_fee'];
$balance    = $total_fee - $total_paid;

if ($balance <= 0) {
    $payment_status = "مدفوع بالكامل";
    $watermark = "سداد مكتمل";
    $wm_color = "rgba(0,128,0,0.1)";
} elseif ($total_paid > 0) {
    $payment_status = "مدفوع جزئيًا";
    $watermark = "سداد جزئي";
    $wm_color = "rgba(255,0,0,0.1)";
} else {
    $payment_status = "غير مدفوع";
    $watermark = "لم يتم السداد";
    $wm_color = "rgba(0,0,0,0.1)";
}

$qr_text = "إيصال: ".$row['receipt_number']." | الطالب: ".$row['student']." | المبلغ: ".$row['amount']." جنيه سوداني | التاريخ: ".$row['payment_date'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>إيصال سداد</title>
  <link href="./css/bootstrap.min.css" rel="stylesheet">
  <style>

    @font-face {
  font-family: "myfont";
  src: url(css/myfont3.ttf);
  font-weight: normal;
}
    body { font-family:"myfont","Arial"; background: #f9f9f9; }
    .a4 {
      width: 210mm;
      min-height: 297mm;
      margin: auto;
      padding: 15mm;
      border: 1px solid #000;
      position: relative;
      background: #fff;
      box-sizing: border-box;
    }
    .header { text-align: center; margin-bottom: 20px; }
    .logo { width: 100px; height: 100px; background: #eee; margin: 0 auto 10px; }
    .institution { font-size: 24px; font-weight: bold; }
    table { width: 90%; border-collapse: collapse; margin: auto; }
    table, th, td { border: 1px solid #000; padding:1px; }
    th {
      padding: 10px;
      text-align: center;
      background-color: #f0f0f0; /* لون خلفية مختلف للعناوين */
      color: #000080; /* لون النص */
      font-weight: bold;
    }
    td { padding: 10px; text-align: center; }
    .footer { margin-top: 40px; text-align: center; font-size: 14px; }
    .print-btn { margin: 20px; text-align: center; }
    .watermark {
      position: absolute;
      top: 45%;
      left: 50%;
      transform: translate(-50%, -50%);
      font-size: 80px;
      color: <?= $wm_color ?>;
      white-space: nowrap;
      pointer-events: none;
    }
    @media print {
      .print-btn { display: none; }
      body { background: #fff; }
      .a4 { box-shadow: none; margin: 0; }
    }
    @page {
      size: A4;
      margin: 0;
    }
  </style>
</head>
<body>

<div class="print-btn">
  <button onclick="window.print()" class="btn btn-primary">🖨️ طباعة الإيصال</button>
</div>

<div class="a4">
  <div class="header">
    <div class="logo"><img src="./img/logo.jpg" width="128px"></div><br>
    <div class="institution "> أكاديمية ترتيل لتعليم القرآن الكريم و علومه </div>
    <div><hr><h4>إيصال سداد</h4> </div>
  </div>

  <!-- عرض البيانات الأساسية خارج الجدول -->
  <div style="margin-bottom:20px; text-align:right; font-size:18px; line-height:1.8;">
    <strong>اسم الطالب:</strong> <?= $row['student'] ?><br>
    <strong>اسم الدورة:</strong> <?= $row['course'] ?><br>
    <strong>تاريخ الدفع:</strong> <?= date("d-m-Y", strtotime($row['payment_date'])) ?><br>


    <strong> رقم الإيصال:</strong> <?= $row['receipt_number'] ?>



  </div>

  <!-- باقي التفاصيل داخل الجدول -->
  <table>
    <tr><th>الرسوم المقررة</th><td><?= $total_fee ?> جنيه سوداني</td></tr>
    <tr><th>إجمالي المدفوع</th><td><?= $total_paid ?> جنيه سوداني</td></tr>
    <tr><th>المتبقي</th><td><?= ($balance <= 0) ? "0 (خالص)" : $balance." جنيه سوداني" ?></td></tr>
    <tr><th>المبلغ في هذا الإيصال</th><td><u><b><?= $row['amount'] ?></b></u> جنيه سوداني</td></tr>
    <tr><th>طريقة الدفع</th><td><?= $row['method'] ?></td></tr>
    <tr><th>حالة السداد</th><td><?= $payment_status ?></td></tr>
  </table>




  <div class="watermark"><?= $watermark ?></div>

 
 <div class="footer">
  <br>
  <table style="width:100%; border-collapse:collapse; margin-top:20px;">
    <tr>
      <td style="width:50%; text-align:center; border:1px solid #8b8a8a; height:100px;">
        توقيع الإدارة
     <br>..................................................................
      </td>
      <td style="width:30%; text-align:center; border:1px solid #8b8a8a; height:100px;">
        ختم المؤسسة
      </td>
    </tr>
  </table>
</div>
<br>

    <strong>المحرر </strong> &nbsp;&nbsp;&nbsp;&nbsp;<?= $printed_by ?>

<br><br>
<center>
 أكاديمية ترتيل لتعليم القرآن الكريم و علومه - الخرطوم - السودان<br>
    هاتف: 0123031890 | 0994444822<br><br></center>
    
</div>



</body>
</html>
