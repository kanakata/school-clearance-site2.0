<?php

session_start();

include 'database_connect.php';

if(isset($_POST['login'])){
    $username = $_POST['username'];
    $admission = $_POST['admission'];
    $index = $_POST['index'];
    $year = $_POST['year'];
    $password = $_POST['password'];

    $_SESSION['admission'] = $admission;
    $_SESSION['username'] = $username;
    $_SESSION['year'] = $year;

    $collecting_data_from_login = "SELECT * FROM login WHERE username='".$username."' AND admissionnumber='".$admission."' AND indexnumber='".$index."' AND year='".$year."' AND password='".$password."' ";

    $collecting_information_from_logintable = mysqli_query($connecting_to_the_database, $collecting_data_from_login);

    //returns data in array form
    $collecting_students_data = mysqli_fetch_array($collecting_information_from_logintable);

    if($collecting_students_data['usertype'] == 'student'){

        header('location: studentdash.php');

    }elseif($collecting_students_data['usertype'] == 'admin'){

        header('location: admindash.php');

    }else{

        $message = "Username, admission, index or password is incorrect";

        $_SESSION['login_failmessage'] = $message;

        header(header: "location: index.php");

        echo $message;

    }

}