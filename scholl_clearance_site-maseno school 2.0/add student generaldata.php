<?php
// Ensure error reporting is enabled for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database connection file.
// Assuming 'database_connect.php' establishes a connection
// and stores the connection object in the variable $connecting_to_the_database
include 'database_connect.php'; 

// Initialize message variables
$message = '';
$alert = '';

// Check if the form was submitted
if(isset($_POST['addstudent'])){
    
    // --- 1. Sanitize and Validate Input ---
    
    // Using simple trimming for general cleaning; prepared statements handle SQL injection
    $name = trim($_POST['name']);
    $admission = trim($_POST['admission']);
    $year = trim($_POST['year']);
    
    // Set default values for optional/status fields if not set or empty, and ensure type consistency
    $feebalance = (isset($_POST['feebalance']) && is_numeric($_POST['feebalance'])) ? $_POST['feebalance'] : 0;
    $feestatus = trim($_POST['feestatus']) ?? 'uncleared';
    $bookslost = trim($_POST['bookslost']) ?? 'none';
    $booksmarketvalue = (isset($_POST['booksmarketvalue']) && is_numeric($_POST['booksmarketvalue'])) ? $_POST['booksmarketvalue'] : 0;
    $librarystatus = trim($_POST['librarystatus']) ?? 'uncleared';
    $boardingitemsdamaged = trim($_POST['boardingitemsdamaged']) ?? 'none';
    $boardingitemsvalue = (isset($_POST['boardingitemsvalue']) && is_numeric($_POST['boardingitemsvalue'])) ? $_POST['boardingitemsvalue'] : 0;
    $boardingstatus = trim($_POST['boardingstatus']) ?? 'uncleared';
    $unpaidaccessories = trim($_POST['unpaidaccessories']) ?? 'none';
    $unpaidaccessoriesvalue = (isset($_POST['unpaidaccessoriesvalue']) && is_numeric($_POST['unpaidaccessoriesvalue'])) ? $_POST['unpaidaccessoriesvalue'] : 0;
    $accessoriesstatus = trim($_POST['accessoriesstatus']) ?? 'uncleared';
    $gamesitemslost = trim($_POST['gamesitemslost']) ?? 'none';
    $gamesitemsvalue = (isset($_POST['gamesitemsvalue']) && is_numeric($_POST['gamesitemsvalue'])) ? $_POST['gamesitemsvalue'] : 0;
    $gamesstatus = trim($_POST['gamesstatus']) ?? 'uncleared';
    $labfee = (isset($_POST['labfee']) && is_numeric($_POST['labfee'])) ? $_POST['labfee'] : 200; // Use submitted value or default
    $labitemsdamaged = trim($_POST['labitemsdamaged']) ?? 'none';
    $labitemsdamagedvalue = (isset($_POST['labitemsdamagedvalue']) && is_numeric($_POST['labitemsdamagedvalue'])) ? $_POST['labitemsdamagedvalue'] : 0;
    $labstatus = trim($_POST['labstatus']) ?? 'uncleared';
    $clearancestatus = trim($_POST['clearancestatus']) ?? 'uncleared';


    // --- 2. Handle Profile Picture Upload ---
    $profilepic_name = '';
    if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
        $target_dir = "profile pictures/";
        // Create a unique name to prevent overwriting
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $unique_filename = uniqid('student_') . '.' . $file_extension;
        $target_file = $target_dir . $unique_filename;
        
        $valid_types = array('jpg','jpeg','png','gif');
        
        if(in_array($file_extension, $valid_types)){
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target_file)){
                // Only store the filename in the database
                $profilepic_name = $unique_filename; 
            } else {
                // Handle file move error
                $alert .= " Error uploading file.";
            }
        } else {
            // Handle invalid file type error
            $alert .= " Invalid file type for profile picture.";
        }
    }

    // --- 3. Check if Student Data Exists (Prepared Statement) ---
    
    // Check if a student with the same admission number already exists
    $check_sql = "SELECT admission FROM studentgeneraldata WHERE admission = ?";
    
    // Use the connection object to prepare the statement
    $stmt_check = $connecting_to_the_database->prepare($check_sql);
    
    if ($stmt_check === false) {
        $alert .= " Database prepare error (check): " . $connecting_to_the_database->error;
    } else {
        // 's' means the parameter is a string
        $stmt_check->bind_param("s", $admission); 
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            // Student data exists
            $alert = "Student with admission number **" . htmlspecialchars($admission) . "** is already in the database. Please use the update function if you wish to modify their data.";
        } else {
            // --- 4. Insert New Student Data (Prepared Statement) ---

            // The original query had an error in column/value order related to lab items.
            // Corrected and using placeholders (?) for security.
            $insert_sql = "INSERT INTO studentgeneraldata 
            (username, admission, year, feebalance, feestatus, bookslost, bookmarketvalue, librarystatus, 
             boardingitemsdamaged, boardingitemsvalue, boardingstatus, unpaidaccessories, unpaidaccessoriesvalue, accessoriesstatus, 
             gamesitemslost, gamesitemvalue, gamesstatus, labfee, labitemsdamaged, labitemvalue, labstatus, clearancestatus, userprofilepic) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt_insert = $connecting_to_the_database->prepare($insert_sql);
            
            if ($stmt_insert === false) {
                $alert .= " Database prepare error (insert): " . $connecting_to_the_database->error;
            } else {
                // 'ssisssisissisisiisisss' - String, String, Integer, String, ...
                // You need to match the type of each column. Use 's' for strings and 'i' for integers/numbers.
                // Assuming year, feebalance, booksmarketvalue, boardingitemsvalue, unpaidaccessoriesvalue, gamesitemvalue, labfee, labitemvalue are INT/DECIMAL.
                $stmt_insert->bind_param("ssisssisissisisiissss", 
                    $name, $admission, $year, $feebalance, $feestatus, $bookslost, $booksmarketvalue, $librarystatus, 
                    $boardingitemsdamaged, $boardingitemsvalue, $boardingstatus, $unpaidaccessories, $unpaidaccessoriesvalue, $accessoriesstatus, 
                    $gamesitemslost, $gamesitemsvalue, $gamesstatus, $labfee, $labitemsdamaged, $labitemsdamagedvalue, $labstatus, 
                    $clearancestatus, $profilepic_name
                );

                if ($stmt_insert->execute()) {
                    $message = "Student data upload was successful!";
                } else {
                    $alert .= " Error inserting data: " . $stmt_insert->error;
                }
                
                $stmt_insert->close();
            }
        }
        
        $stmt_check->close();
    }
}

// Ensure $message and $alert are sanitized before outputting in JavaScript
$js_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$js_alert = htmlspecialchars($alert, ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script>
        // Only show alerts if a message or alert is present
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
            <h1><span class="material-symbols-outlined">add</span>add student general data</h1>
            name <br>
            <input type="text" name="name" required autocomplete="off"><br>

            admission <br>
            <input type="number" name="admission" required autocomplete="off"><br>

            year <br>
            <input type="number" name="year" required><br>

            fee balance <br>
            <input type="number" name="feebalance" value="0" required autocomplete="off"><br>
            fee status <br>
            <input type="text" name="feestatus" value="uncleared" required><br>

            books lost <br>
            <input type="text" name="bookslost" required autocomplete="off" value="none"><br>
            books market value <br>
            <input type="number" name="booksmarketvalue" value="0" required autocomplete="off"><br>

            library status <br>
            <input type="text" name="librarystatus" value="uncleared" required><br>

            boarding items damaged <br>
            <input type="text" name="boardingitemsdamaged" required autocomplete="off" value="none"><br>
            boardingitemsvalue <br>
            <input type="number" name="boardingitemsvalue" value="0" required autocomplete="off"><br>
            boarding status <br>
            <input type="text" name="boardingstatus" value="uncleared" required><br>

            unpaid accessories <br>
            <input type="text" name="unpaidaccessories" required autocomplete="off" value="none"><br>
            unpaid accessories value <br>
            <input type="number" name="unpaidaccessoriesvalue" value="0" required autocomplete="off"><br>
            accessories status <br>
            <input type="text" name="accessoriesstatus" value="uncleared" required><br>

            games items lost <br>
            <input type="text" name="gamesitemslost" required autocomplete="off" value="none"><br>
            games items value <br>
            <input type="number" name="gamesitemsvalue" value="0" required autocomplete="off"><br>
            games status <br>
            <input type="text" name="gamesstatus" value="uncleared" required><br>

            mandatory lab fee <br>
            <input type="number" name="labfee" value="200" required><br>
            lab items damaged <br>
            <input type="text" name="labitemsdamaged" value="none" required><br>
            lab items damaged value<br>
            <input type="number" name="labitemsdamagedvalue" value="0" required><br>
            lab status <br>
            <input type="text" name="labstatus" value="uncleared" required><br>

            clearance status <br>
            <input type="text" name="clearancestatus" value="uncleared" required><br>

            profile picture <br>
            <input type="file" name="image"><br>

            <input type="submit" value="add student" name="addstudent">   
          </Label>
    </form>
    </div>
</body>
</html>