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
    <h2>A simple form</h2>
    <form action="process.php" method="POST">
        <label>Student Name:</label>
        <input type="text" name="stu_name" id="stu_name" required><br><br>
        <label> Class:</label>
        <input type="text" name="classname" id="classname" required><br><br>
        <label>Age:</label>
        <input type="number" name="stu_age" id="stu_age" required><br><br>
        <input type="submit" name="submit" id="submit">
    </form>
<hr> 
<h2> List of Registered Students </h2>
<table border="2">
    <thead>
        <td>Student No</td>
        <td>Name</td>
        <td>Class</td>
        <td>Age</td>
        <td>Actions</td>
    </thead>
    <?php foreach($students as $student) { ?>
        <tr>
        <td><?php echo $student['sno']?></td>
        <td><?php echo $student['stu_name']?></td>
        <td><?php echo $student['classname']?></td>
        <td><?php echo $student['stu_age']?></td>
        <td><a href="view.php?sno=<?php echo $student['sno'];?>"><button>view</button></a>
       <a href="edit.php?edit=<?php echo $student['sno'];?>"> <button>edit</button></a>
       <a href="process.php?del=<?php echo $student['sno'];?>"><button>delete</button></a>
        </td>
        </tr>
<?php } ?>

</table>
</body>
</html>