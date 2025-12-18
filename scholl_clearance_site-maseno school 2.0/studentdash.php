<?php   

error_reporting(0);

session_start();

include 'student_general_data_collection.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>student dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="shortcut icon" href="" type="image/x-icon">
     <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">dashboard</span>student dashboard</h2>
        <div class="links">
            <a href="#footer"><span class="material-symbols-outlined">contact_phone</span>contact us</a>
            <a href="index.html"><span class="material-symbols-outlined">logout</span>log out</a> 
            <h2><span class="material-symbols-outlined">online_prediction</span>online</h2>
        </div>
    </nav>
    <div class="studentdashboard">
        <h1>hii there <?php echo "{$info['username']}"; ?>, congratulations for completing your four year course.</h1>
        <!-- <h1><span class="material-symbols-outlined">waving_hand</span>welcome to your dash.</h1>  -->
        <div class="studentprofile">
            <div class="img_holder">
                <img src="profile pictures/<?php echo "{$info['userprofilepic']}"?>" alt="profilepicture" class="profilepicture">
                <div class="student_tutorial">
                    <h1>We're glad you're here to begin your clearance process.</h1>
                    <h2>This system is designed to make your final steps with us quick, clear, and efficient<span class="material-symbols-outlined">automation</span>.</h2>
                    <h3>What You Can Do Here :</h3>
                    <ol>
                        <li>View your status : See exactly which departments (e.g., Library, Finance & laboratory) still require your clearance.</li>
                        <li>Resolve holds : Find instructions and contact information for any outstanding obligations you may have.</li>
                        <li>Complete debts : Submit any necessary debts online through safaricom m-pesa through the school's paybill<span class="material-symbols-outlined">digital_wellbeing</span>.</li>
                    </ol>
                    <h1>please note :</h1>
                    <h2>Your final clearance status will be issued only after all departments have confirmed that you have met all your obligations.</h2>
                    <h2 class="red">You should not forget your allocated clearance date !!!!</h2>
                    <h3>Ready to get started? proceed to the departments bellow <span class="material-symbols-outlined">swipe_down</span>.</h3>
                </div>
            </div>
            <div class="studentdetails">
                <h2><span class="material-symbols-outlined">id_card</span>name: <span id="username"><?php echo "{$info['username']}"; ?></span></h2>
                <h2><span class="material-symbols-outlined">confirmation_number</span>admission: <?php echo "{$info['admission']}"; ?></h2>
                <h2><span class="material-symbols-outlined">calendar_month</span>year: <?php echo "{$info['year']}"; ?></h2>
                <div class="clearancestatus">
                    <span class="material-symbols-outlined">progress_activity</span>
                    <h1 class="totalpercentage"> </h1>
                    <span>cleared</span>
                </div> 
                <div class="picupdate">
                    <span id="day"><span class="material-symbols-outlined">calendar_month</span>Your pic up date will be displayed here !!</span>
                </div> 
            </div>
        </div>

        <div class="graph">
            <canvas id="myChart"></canvas>
        </div>

        <div class="studentdash">
            <h2>hii there student!! make sure you clear from all the departments bellow.</h2>
            <div class="depts">

                <div class="dept1">
                   <div class="deptanimation1">
                       <a href="librarydept.php"><span class="material-symbols-outlined">book_2</span>library dept</a>
                       <h4 class="deptstatus1"><?php echo "{$info['librarystatus']}"?></h4>
                       <h4 class="percentage1"></h4>
                  </div>
                </div>  
                <div class="dept2">
                   <div class="deptanimation2">
                       <a href="financedept.php"><span class="material-symbols-outlined">finance</span>finance dept</a>
                       <h4 class="deptstatus2"><?php echo "{$info['feestatus']}"?></h4>
                       <h4 class="percentage2"></h4>
                  </div>
                </div>  
                <div class="dept3">
                   <div class="deptanimation3">
                       <a href="laboratorydept.php"><span class="material-symbols-outlined">experiment</span>laboratory dept</a>
                       <h4 class="deptstatus3"><?php echo "{$info['labstatus']}"?></h4>
                       <h4 class="percentage3"></h4>
                  </div>
                </div>  
                <div class="dept4">
                   <div class="deptanimation4">
                       <a href="gamesdept.php"><span class="material-symbols-outlined">sports_soccer</span>games dept</a>
                       <h4 class="deptstatus4"><?php echo "{$info['gamesstatus']}"?></h4>
                       <h4 class="percentage4"></h4>
                  </div>
                </div>  
                <div class="dept5">
                   <div class="deptanimation5">
                       <a href="boardingdept.php"><span class="material-symbols-outlined">hotel</span>boarding dept</a>
                       <h4 class="deptstatus5"><?php echo "{$info['boardingstatus']}"?></h4>
                       <h4 class="percentage5"></h4>
                  </div>
                </div>  
                <div class="dept6">
                   <div class="deptanimation6">
                       <a href="accessoriesdept.php"><span class="material-symbols-outlined">computer</span>accessories dept</a>
                       <h4 class="deptstatus6"><?php echo "{$info['accessoriesstatus']}"?></h4>
                       <h4 class="percentage6"></h4>
                  </div>
                </div>  

            </div>
        </div>

    </div>
    <footer id="footer">
        <div class="contacts">
            <h2><span class="material-symbols-outlined">contact_page</span>contact us</h2>
            <a href="tel: 0793317819"><span class="material-symbols-outlined">contact_phone</span>tel: 0793317819</a><br>
            <a href="mailto: patrick37668@gmail.com"><span class="material-symbols-outlined">email</span>email: patrick37668@gmail.com</a>
        </div>
        <div class="downloadablefiles">
            <h2><span class="material-symbols-outlined">files</span>your files</h2>
            <a href="#" download>academic transcript</a> <br>
            <a href="#" download>fee stracture</a>
        </div>
    </footer>
<script src="app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="script.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.0/chart.min.js"></script>
</body>
</html>