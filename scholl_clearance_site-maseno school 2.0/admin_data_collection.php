<?php


include 'database_connect.php';

$collecting_data_from_studentgeneraldata = "SELECT * FROM studentgeneraldata LIMIT $start, $rows_per_page";
$collecting_admin_data_from_studentgeneraldatatable = mysqli_query($connecting_to_the_database,$collecting_data_from_studentgeneraldata);

