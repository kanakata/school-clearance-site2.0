<?php

include 'login_check.php';

include 'database_connect.php';

$collecting_data_from_studentgeneraldata = "SELECT * FROM studentgeneraldata where admission='$_SESSION[admission]' ";

$collecting_data_from_studentgeneraldatatable = mysqli_query($connecting_to_the_database,$collecting_data_from_studentgeneraldata);

$info = mysqli_fetch_array($collecting_data_from_studentgeneraldatatable);