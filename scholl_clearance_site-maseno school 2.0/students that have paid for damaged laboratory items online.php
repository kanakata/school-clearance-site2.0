<?php

include 'database_connect.php';


$collecting_data_from_physicall = "SELECT * FROM physicall ";

$collecting_data_from_physicalltable = mysqli_query($connecting_to_the_database,$collecting_data_from_physicall);

if(isset($_GET['bookphyid'])){

    $updatestat = "UPDATE physicall SET libstatus='cleared' where admission=$_GET[bookphyid] ";

    $done = mysqli_query($connecting_to_the_database, $updatestat);
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>books physical replacement viewer</title>
    <link rel="stylesheet" href="style.css">
     <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">dashboard</span>students to replace books physically</h2>
        <div class="links">
             <a href="#footer"><span class="material-symbols-outlined">contact_page</span>contact us</a>
             <a href="admindash.php"><span class="material-symbols-outlined">arrow_back</span>back</a>
        </div>
    </nav>
    <div class="search">
        <form action="">
            <label for="">
            search
            <input type="search" name="" id="" placeholder="Type admission of student">
            <input type="submit" value="search">
            </label>
        </form>
    </div>
    <div class="searchoutput">
        <h2>search output</h2>
        <table class="table">
            <th>name</th>
            <th>admission</th>
            <th>year</th>
            <th>book(s) name</th>
            <th>clear the student</th>
            <th>clearance status</th>
            <tr>
                <td>patrick kiprop</td>
                <td>4761</td>
                <td>2024</td>
                <td>the samaritan</td>
                <td>clear</td>
                <td>uncleared</td>
            </tr>
        </table>
    </div>
    <div class="details">
        <h2>students to replace books physically</h2>
        <table>
            <th>name</th>
            <th>admission</th>
            <th>year</th>
            <th>book(s) name</th>
            <th>clear the student</th>
            <th>clearance status</th>

             <?php  while($info=$collecting_data_from_physicalltable->fetch_assoc()){ ?>

            <tr>
                <td><?php echo $info['username']?></td>
                <td><?php echo $info['admission']?></td>
                <td><?php echo $info['year']?></td>
                <td><?php echo $info['bookname']?></td>
                <td><?php echo "<a href='students to replace books physically.php?bookphyid={$info['admission']}  '>clear student</a>" ?></td>
                <td><?php echo $info['libstatus']?></td>
            </tr>


            <?php } ?>

        </table>
    </div>
</body>
</html>