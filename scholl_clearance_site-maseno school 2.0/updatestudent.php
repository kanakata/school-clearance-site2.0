<?php

// Include the database connection file.
include 'database_connect.php';

// Initialize message variable
$message = '';
$alert = '';
$dual_update_successful = false; // Flag to track overall success

if(isset($_POST['update'])){
    
    // --- 1. Get Old and New Input ---
    
    // OLD VALUES (Used for identifying the record)
    $old_admission = trim($_POST['oldadmission']); 
    $old_name = trim($_POST['oldname']);
    $old_password = $_POST['oldpassword']; 
    
    // NEW VALUES (Used for the SET clause in the UPDATE query)
    $new_name = trim($_POST['newname']);
    $new_admission = trim($_POST['newadmission']); 
    $new_raw_password = $_POST['newpassword'];
    
    
    // CRITICAL SECURITY FIX: Hash the NEW password before updating the database
    $new_hashed_password = password_hash($new_raw_password, PASSWORD_DEFAULT); 
    
    // --- 2. Check and Execute Update (login table) ---
    
    if (!empty($old_admission) && !empty($new_name) && !empty($new_admission) && !empty($new_raw_password)) {
        
        // Update 1: login table (username, admissionnumber, password)
        $update_login_sql = "UPDATE login SET username = ?, admissionnumber = ?, password = ? WHERE admissionnumber = ?";
        
        $stmt_login_update = $connecting_to_the_database->prepare($update_login_sql);
        
        if ($stmt_login_update === false) {
            $alert = "Database prepare error (login): " . $connecting_to_the_database->error;
        } else {
            // 'ssss' -> bind parameters: new_name, new_admission, new_hashed_password, old_admission
            $stmt_login_update->bind_param("ssss", $new_name, $new_admission, $new_hashed_password, $old_admission);
            
            if ($stmt_login_update->execute()) {
                if ($stmt_login_update->affected_rows > 0) {
                    $message = "Login details successfully updated. ";
                    $dual_update_successful = true;
                } else {
                    $alert = "Login update skipped: No record found with old admission **" . htmlspecialchars($old_admission) . "**, or no login data was changed. ";
                    $dual_update_successful = false; // Prevent second update if first failed to find a record
                }
            } else {
                $alert = "Error executing login update query: " . $stmt_login_update->error;
                $dual_update_successful = false;
            }
            $stmt_login_update->close();
        }
        
        // --- 3. Execute Second Update (studentgeneraldata table) ---
        
        // Only attempt the second update if the first update found a record
        if ($dual_update_successful) {
            
            // Update 2: studentgeneraldata table (username, admission)
            // Note: studentgeneraldata may have multiple records per admission (e.g., across years).
            // We use the old admission to find all relevant records.
            $update_general_sql = "UPDATE studentgeneraldata SET username = ?, admission = ? WHERE admission = ?";
            
            $stmt_general_update = $connecting_to_the_database->prepare($update_general_sql);
            
            if ($stmt_general_update === false) {
                $alert .= " Database prepare error (general data): " . $connecting_to_the_database->error;
                $dual_update_successful = false;
            } else {
                // 'sss' -> bind parameters: new_name, new_admission, old_admission
                $stmt_general_update->bind_param("sss", $new_name, $new_admission, $old_admission);
                
                if ($stmt_general_update->execute()) {
                    // Check if any rows were affected in the second table
                    if ($stmt_general_update->affected_rows > 0) {
                        $message .= "General data updated for **" . $stmt_general_update->affected_rows . "** record(s).";
                        $message .= " (Admission changed from **" . htmlspecialchars($old_admission) . "** to **" . htmlspecialchars($new_admission) . "**.)";
                    } else {
                        $alert .= " General data update skipped: No corresponding records found in studentgeneraldata.";
                    }
                } else {
                    $alert .= " Error executing general data update query: " . $stmt_general_update->error;
                    $dual_update_successful = false;
                }
                $stmt_general_update->close();
            }
        }

    } else {
        $alert = "Please fill in all required fields (Current Admission, New Name, New Admission, and New Password).";
    }
}

// Sanitize messages for output in JavaScript
$js_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$js_alert = htmlspecialchars($alert, ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>update student dept</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script>
        // Display confirmation or error message using JavaScript alert
        <?php if (!empty($js_message)): ?>
            alert('✅ Success: <?php echo $js_message; ?>');
        <?php endif; ?>
        <?php if (!empty($js_alert)): ?>
            alert('❌ Error: <?php echo $js_alert; ?>');
        <?php endif; ?>
    </script>
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">dashboard</span>student log in details update</h2>
        <div class="links">
             <a href="#footer"><span class="material-symbols-outlined">contact_page</span>contact us</a>
             <a href="admindash.php"><span class="material-symbols-outlined">arrow_back</span>back</a>
        </div>
    </nav>
    <div class="update">
        <form action="#" method="post">
          <Label>
            <h1>update student log in info</h1>
            
            <br>
            <input type="text" name="oldname" required placeholder="current username"><br>
            
            <br>
            <input type="number" name="oldadmission" required placeholder="current admission"><br>
            
            <br>
            <input type="password" name="oldpassword" required placeholder="current password"><br>
            
            <br>
            <input type="text" name="newname" required placeholder="new username"><br>
            
            <br>
            <input type="number" name="newadmission" required placeholder="new admission"><br>
            
            <br>
            <input type="password" name="newpassword" required placeholder="new password"><br>
            
            <input type="submit" value="update" name="update">
          </Label>
    </form>
    </div>
</body>
</html>