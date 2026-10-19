<?php
include("connection.php");

if(isset($_POST["submit"])){
    $stu_name = $_POST["stu_name"];
    $classname = $_POST["classname"];
    $stu_age = $_POST["stu_age"];

    $form_data = "INSERT INTO student(stu_name,classname,stu_age) VALUES('$stu_name','$classname',$stu_age)";

    //execute query
    mysqli_query($conn, $form_data);

    //redirect to the form page
    header("location:school.php");

}

$datas= "SELECT * FROM student";
$students = mysqli_query( $conn, $datas);

if (isset($_GET['sno'])) {
    $sno = $_GET['sno'];
    $single_data= "SELECT * FROM student WHERE sno=$sno";
    $my_single_data = mysqli_query($conn, $single_data);
}
 
if (isset($_GET['edit'])) {
    $sno = $_GET['edit'];
    $editdt= "SELECT * FROM student WHERE sno=$sno";
    $edit_data = mysqli_query($conn,$editdt);
   
}
 
if(isset($_POST["update"])&&isset($_GET["up"])){
    $stu_name = $_POST["stu_name"];
    $classname = $_POST["classname"];
    $stu_age = $_POST["stu_age"];

    $form_data = "INSERT INTO student(stu_name,classname,stu_age) VALUES('$stu_name','$classname',$stu_age)";

    //execute query
    mysqli_query($conn, $form_data);

    //redirect to the form page
    header("location:school.php");
}

if (isset($_GET['del'])) {
    $sno = $_GET['del'];
    $delete= "DELETE FROM student WHERE sno=$sno";
     mysqli_query($conn,$delete);
   header("location:school.php");
}