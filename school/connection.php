<?php


$host = "localhost";
$user = "root";
$password = "";
$database = "school_db" ;

$conn = mysqli_connect($host,$user,$password,$database);

if(!$conn) {
    die(mysqli_connect_error());
}
