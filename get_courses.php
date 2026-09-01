<?php
require_once "./db_conn.php";
$student_id = intval($_GET['student_id']);
$sql = "SELECT e.course_id, c.title 
        FROM enrollments e 
        JOIN courses c ON e.course_id=c.id 
        WHERE e.student_id=? AND e.status='مؤكد'";
$stmt = $link->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$res = $stmt->get_result();
while($row=$res->fetch_assoc()){
  echo "<option value='".$row['course_id']."'>".$row['title']."</option>";
}
?>
