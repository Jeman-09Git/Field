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
    </style>
</head>
<body>
    <h2>A simple form for editing data </h2>
    <?php foreach($edit_data as $data){?> 

    <form action="process.php?url=<?php echo $data['sno']; ?>" method="POST">
        
        <label>Student Name:</label>
        <input type="text" name="stu_name" id="stu_name" required value="<?php echo $data('stu_name')?>"><br><br>
        <label> Class:</label>
        <input type="text" name="classname" id="classname" required value="<?php echo $data('classname')?>" ><br><br>
        <label>Age:</label>
        <input type="number" name="stu_age" id="stu_age" required value="<?php echo $data('stu_age')?>" ><br><br>
        <input type="submit" name="update" value="Update" id="up">
    </form>
    <?php } ?>
    </body>
    </html>