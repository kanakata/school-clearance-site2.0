<?php

error_reporting(1);
session_start();

echo $_SERVER['REMOTE_ADDR'];

include 'database_connect.php';

$collecting_data_from_login = "SELECT * FROM login";

$collecting_admin_data_from_login = mysqli_query($connecting_to_the_database,$collecting_data_from_login);

$info = mysqli_fetch_array($collecting_admin_data_from_login);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>admin dash</title>
    <link rel="stylesheet" href="style.css">
     <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">dashboard</span>admin dashboard</h2>
        <div class="links">
             <a href="#footer"><span class="material-symbols-outlined">contact_page</span>contact us</a>
             <a href="index.html"><span class="material-symbols-outlined">logout</span>log out</a>
             <h2><span class="material-symbols-outlined">online_prediction</span>online</h2>
        </div>
    </nav>

    <div class="contentholder">
        <div class="adminprofile">
            <img src="profile pictures/<?php echo "{$info['profilepictures']}"; ?>" alt="admin profile picture" class="profilepicture">

            <h2><span class="material-symbols-outlined">id_card</span>name: <span id="username"><?php echo "{$info['username']}"; ?></span></h2>
            <h2><span class="material-symbols-outlined">work</span>position : <?php echo "{$info['position']}"; ?></h2>

        </div>
        <div class="adnimdashboard">
            <div class="admindash">
            <div class="depts">
                <a href="updatestudent.php">update student(s)</a> <!--done--> 
                <a href="add student.php">add student(s)</a> <!--done--> 
                <a href="add student generaldata.php">add student(s) general data</a> <!--done--> 
                <a href="viewstudents.php">view student(s)</a> <!--done--> 

                <a href="cleared school fees.php">cleared school fees</a> <!--done--> 
                <a href="unpaid school fees.php">uncleared school fees</a> <!--done--> 

                <a href="cleared laboratory fees.php">cleared laboratory dept</a> <!--done--> 
                <a href="unpaid laboratory fee.php">uncleared laboratory dept</a> <!--done--> 

                <a href="cleared library fees.php">cleared library dept</a> <!--done-->
                <a href="unpaid library fee.php">uncleared library dept</a> <!--done-->

                <a href="cleared boarding fees.php">cleared boarding dept</a> <!--done-->
                <a href="unpaid boarding fees.php">uncleared boarding dept</a> <!--done-->

                <a href="cleared accessories fees.php">cleared accessories dept</a> <!--done-->
                <a href="unpaid accessories fees.php">uncleared accessories dept</a> <!--done-->

                <a href="cleared games fees.php">cleared games dept</a> <!--done-->
                <a href="unpaid games fees.php">uncleared games dept</a> <!--done-->

                <a href="students to replace books physically.php">students to replace books lost physically</a> <!--done-->
                <a href="students that have replaced books by payment online.php">students that have paid for books lost online</a> <!--done-->
                
                <a href="students to replace accessories physically.php">students to replace missing accessories physically</a> <!--done-->
                <a href="students that have replaced accessories by payment online.php">students that have paid for missing accessories online</a> <!--done-->
                
                <a href="students to replace games items physically.php">students to replace lost games items physically</a> <!--done-->
                <a href="students that have replaced games items by payment online.php">students that have paid for games items lost online</a> <!--done-->

                <a href="students to replace boarding items physically.php">students to replace lost/damaged boarding items physically</a> <!--done-->
                <a href="students that have replaced boarding items by payment online.php">students that have paid for boarding items lost/damaged online</a> <!--done-->

                <a href="students to replace damaged laboratory items physically.php">students to replace damaged laboratory items physically</a> <!--done-->
                <a href="students that have paid for damaged laboratory items online.php">students that have paid for damaged laboratory items online</a> <!--done-->

                <a href="students to pay fees physically by cheque.php">students to pay fees physically by cheque</a> <!--done-->
                <a href="students that have paid fee balance items  online.php">students that have paid fee balance items  online</a> <!--done-->
               
            </div>
        </div>
    </div>
        </div>
    <footer id="footer">
        <div class="contacts">
            <h2><span class="material-symbols-outlined">contact_page</span>contacts us</h2>
            <a href="tel: 0793317819"><span class="material-symbols-outlined">phone</span>tel: 0793317819</a><br>
            <a href="mailto: patrick37668@gmail.com"><span class="material-symbols-outlined">mail</span>email: patrick37668@gmail.com</a>
        </div>
    </footer>
<script src="app.js"></script>    
</body>
</body>
</html>