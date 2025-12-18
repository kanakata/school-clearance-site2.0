<?php
// Ensure error reporting is enabled for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database connection file.
include 'database_connect.php'; 

// Initialize message variables
$message = '';
$alert = '';

if(isset($_POST['addstudent'])){
    
    // --- 1. Clean and Prepare Input ---
    
    // Use trim for cleanliness. Prepared statements handle SQL injection protection.
    $name = trim($_POST['name']);
    $admission = trim($_POST['admission']);
    $index = trim($_POST['index']);
    $year = trim($_POST['year']);
    $usertype = trim($_POST['usertype']) ?? 'student'; // Default to 'student' if not provided
    $clearancestatus = trim($_POST['clearancestatus']) ?? 'uncleared';
    
    // CRITICAL: Hash the password before storing it
    $raw_password = $_POST['password']; 
    // Use PASSWORD_DEFAULT for the best current hashing algorithm (currently bcrypt)
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT); 


    // --- 2. Handle Profile Picture Upload ---
    $profilepic_name = '';
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $target_dir = "profile pictures/";
        
        // Create a unique name to prevent overwriting
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $unique_filename = uniqid('user_') . '.' . $file_extension;
        $target_file = $target_dir . $unique_filename;
        
        $valid_types = array('jpg','jpeg','png','gif');
        
        if(in_array($file_extension, $valid_types)){
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)){
                $profilepic_name = $unique_filename; 
            } else {
                $alert .= " Error uploading profile picture file.";
            }
        } else {
            $alert .= " Invalid file type for profile picture.";
        }
    }


    // --- 3. Check if Student (Admission Number) Already Exists ---
    $check_sql = "SELECT admissionnumber FROM login WHERE admissionnumber = ?";
    
    $stmt_check = $connecting_to_the_database->prepare($check_sql);
    
    if ($stmt_check === false) {
        $alert .= " Database prepare error (check): " . $connecting_to_the_database->error;
    } else {
        // 's' -> string (admissionnumber)
        $stmt_check->bind_param("s", $admission); 
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            // Student login data exists -> Alert the user
            $alert = "Login details for admission number **" . htmlspecialchars($admission) . "** already exist. Cannot add duplicate user.";
        } else {
            // --- 4. Insert New User Login Data (Prepared Statement & Hashed Password) ---

            $insert_sql = "INSERT INTO login 
            (username, admissionnumber, indexnumber, year, usertype, clearancestatus, profilepictures, password) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt_insert = $connecting_to_the_database->prepare($insert_sql);
            
            if ($stmt_insert === false) {
                $alert .= " Database prepare error (insert): " . $connecting_to_the_database->error;
            } else {
                // 'ssisssss' - String, String, Integer, String, String, String, String, String (hashed password is a string)
                $stmt_insert->bind_param("ssisssss", 
                    $name, $admission, $index, $year, $usertype, $clearancestatus, $profilepic_name, $hashed_password
                );

                if ($stmt_insert->execute()) {
                    $message = "Student login data upload was successful!";
                } else {
                    $alert .= " Error inserting data: " . $stmt_insert->error;
                }
                
                $stmt_insert->close();
            }
        }
        
        $stmt_check->close();
    }
}

// Sanitize messages for output
$js_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$js_alert = htmlspecialchars($alert, ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>add student</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script>
        <?php if (!empty($js_message)): ?>
            alert('<?php echo $js_message; ?>');
        <?php endif; ?>
        <?php if (!empty($js_alert)): ?>
            alert('<?php echo $js_alert; ?>');
        <?php endif; ?>
    </script>
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">dashboard</span>add student</h2>
        <div class="links">
             <a href="#footer"><span class="material-symbols-outlined">contact_page</span>contact us</a>
             <a href="admindash.php"><span class="material-symbols-outlined">arrow_back</span>back</a>
        </div>
    </nav>
    <div class="update">
        <form action="#" method="POST" enctype="multipart/form-data">
          <Label>
            <h1>add student log in details</h1>
            name <br>
            <input type="text" name="name" required><br>
            admission <br>
            <input type="number" name="admission" required><br>
            index <br>
            <input type="number" name="index"><br>
            year <br>
            <input type="number" name="year"><br>
            usertype <br>
            <input type="text" name="usertype" value="student" required><br>
            clearance status <br>
            <input type="text" name="clearancestatus" value="uncleared" required><br>
            profile picture <br>
            <input type="file" name="image"><br>
            password <br>
            <input type="password" name="password" required><br> <input type="submit" value="add student" name="addstudent">   
          </Label>
    </form>
    </div>
</body>
</html>