<?php
include("process.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        body{
            color: green;
            background-color: yellow;
        }
    </style>
</head>
<body>
    <?php foreach($my_single_data as $data){?>
    <h2>my name is <?php echo $data['stu_name'] ?></h2>
    <h2> Age <?php echo $data['stu_age']?></h2>
    <h2>I am a student of Class <?php echo $data['classname']?></h2>
    <?php } ?>
    <a href="school.php"><button>back</button></a>
</body>
</html>