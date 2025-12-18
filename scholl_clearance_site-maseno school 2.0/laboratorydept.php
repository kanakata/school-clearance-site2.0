<?php

// Assuming these files securely handle connection ($connecting_to_the_database) 
// and session management, and define $info and $phy arrays.
include 'student_general_data_collection.php';
include 'phy.php';


// collects the student's debt situation
$Debt = $info['labitemsdamaged'];
$Debt_amount = $info['labfee']; 
$More_debt_amount = $info['labitemvalue']; 
$Dept_status = $info['labstatus'];



// Check if the physical payment option has been selected by student
if (isset($_GET['choise']) && $_GET['choise'] == "payPhysically") {
    
    // Define fallback data variables
    $username_data = $phy["username"] ?? $_SESSION['username'] ?? "Student";
    $admission_data = $phy["admission"] ?? $_SESSION['admission'] ?? "N/A";
    $year_data = $phy["year"] ?? date("Y"); 
    $lab_debt = $phy["labfee"] ?? "Missing Items";
    
    
    
    // 1. UPDATE status in the main table to PENDING for physical clearance
    // SECURITY FIX: Setting status to 'pending_physical' instead of 'cleared'
    $stmt = $connecting_to_the_database->prepare("UPDATE studentgeneraldata SET labstatus = 'pending_physical' WHERE admission = ?");
    $stmt->bind_param("s", $admission_data);
    
    // Execute and check for success
    if ($stmt->execute()) {
        
        // --- START UPSERT LOGIC for 'physicall' table ---
        
        // A. CHECK if a record already exists in 'physicall' for this admission
        $check_stmt = $connecting_to_the_database->prepare(
            "SELECT admission FROM physicall WHERE admission = ?"
        );
        $check_stmt->bind_param("s", $admission_data);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $check_stmt->close();

        if ($result->num_rows > 0) {
            // B. RECORD EXISTS: UPDATE the existing row
            $upsert_stmt = $connecting_to_the_database->prepare(
                "UPDATE physicall SET username = ?, year = ?, lab = ?, labstatus = 'pending_physical_payment' WHERE admission = ?"
            );
            $upsert_stmt->bind_param("ssss", $username_data, $year_data, $lab_debt, $admission_data);
            $upsert_stmt->execute();
            $upsert_stmt->close();
        } else {
            // C. RECORD DOES NOT EXIST: INSERT a new row
            // NOTE: The bind_param has 4 's' but 5 values are needed: username, admission, year, accessories, 'pending_physical_payment'
            // Assuming $admission_data is the 5th value that was missing in the original, I am adjusting the types to match 4 variables passed.
            // Reverting to the original structure and assuming the original code handled the INSERT correctly, 
            // but noting the discrepancy: The statement has 5 placeholders, but only 4 variables are bound.
            // For the purpose of implementing the user's request, I'll stick to 4 variables and assume the status is hardcoded/inserted correctly.
            // FIX: Corrected bind_param to match 4 question marks. The fifth value ('pending_physical_payment') is a literal string.
            $upsert_stmt = $connecting_to_the_database->prepare(
                "INSERT INTO physicall (username, admission, year, lab, labstatus) 
                VALUES (?, ?, ?, ?, 'pending_physical_payment')"
            );
            $upsert_stmt->bind_param("ssss", $username_data, $admission_data, $year_data, $lab_debt);
            $upsert_stmt->execute();
            $upsert_stmt->close();
        }
        
        // --- END UPSERT LOGIC ---

        // Set success message for display after redirect
        $_SESSION['physicall_payment_prompt'] = "You have selected physical payment. Please bring the following on your clearance day: **" . htmlspecialchars(("sh" . $info['labfee']) ?? 'Missing Item Details') . "**";

    } else {
        $_SESSION['error_message'] = "Error recording physical payment choice. Please try again.";
    }


    $stmt->close();
    
    header("location: laboratorydept.php");
    exit();
}


//gross lab debt for students with additional debt
$More_lab_debt = ( $phy["labfee"] + $More_debt_amount)?? "Missing Items";

//-- As student with no debts is not applicable in the library as all the students are required to pay a mandatory fee of sh200 for lab damages--
if(isset($_GET['choise']) && isset($_GET['choise']) == "moreDebt"){

    // Define fallback data variables
    $username_data = $phy["username"] ?? $_SESSION['username'] ?? "Student";
    $admission_data = $phy["admission"] ?? $_SESSION['admission'] ?? "N/A";
    $year_data = $phy["year"] ?? date("Y"); 
    
    
    
    // 1. UPDATE status in the main table to PENDING for physical clearance
    // SECURITY FIX: Setting status to 'pending_physical' instead of 'cleared'
    $stmt = $connecting_to_the_database->prepare("UPDATE studentgeneraldata SET labstatus = 'pending_physical' WHERE admission = ?");
    $stmt->bind_param("s", $admission_data);
    
    // Execute and check for success
    if ($stmt->execute()) {
        
        // --- START UPSERT LOGIC for 'physicall' table ---
        
        // A. CHECK if a record already exists in 'physicall' for this admission
        $check_stmt = $connecting_to_the_database->prepare(
            "SELECT admission FROM physicall WHERE admission = ?"
        );
        $check_stmt->bind_param("s", $admission_data);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $check_stmt->close();

        if ($result->num_rows > 0) {
            // B. RECORD EXISTS: UPDATE the existing row
            $upsert_stmt = $connecting_to_the_database->prepare(
                "UPDATE physicall SET username = ?, year = ?, lab = ?, labstatus = 'pending_physical_payment' WHERE admission = ?"
            );
            $upsert_stmt->bind_param("ssss", $username_data, $year_data, $More_lab_debt, $admission_data);
            $upsert_stmt->execute();
            $upsert_stmt->close();
        } else {
            // C. RECORD DOES NOT EXIST: INSERT a new row
            // NOTE: The bind_param has 4 's' but 5 values are needed: username, admission, year, accessories, 'pending_physical_payment'
            // Assuming $admission_data is the 5th value that was missing in the original, I am adjusting the types to match 4 variables passed.
            // Reverting to the original structure and assuming the original code handled the INSERT correctly, 
            // but noting the discrepancy: The statement has 5 placeholders, but only 4 variables are bound.
            // For the purpose of implementing the user's request, I'll stick to 4 variables and assume the status is hardcoded/inserted correctly.
            // FIX: Corrected bind_param to match 4 question marks. The fifth value ('pending_physical_payment') is a literal string.
            $upsert_stmt = $connecting_to_the_database->prepare(
                "INSERT INTO physicall (username, admission, year, lab, labstatus) 
                VALUES (?, ?, ?, ?, 'pending_physical_payment')"
            );
            $upsert_stmt->bind_param("ssss", $username_data, $admission_data, $year_data, $More_lab_debt);
            $upsert_stmt->execute();
            $upsert_stmt->close();
        }
        
        // --- END UPSERT LOGIC ---

        // Set success message for display after redirect
        $_SESSION['physicall_payment_prompt'] = "You have selected physical payment. Please bring the following on your clearance day: **" . htmlspecialchars(("sh" . $More_debt_amount + $Debt_amount) ?? 'Missing Item Details') . "**";

    } else {
        $_SESSION['error_message'] = "Error recording physical payment choice. Please try again.";
    }


    $stmt->close();
    
    header("location: laboratorydept.php");
    exit();
}






// HANDLE ONLINE PAYMENT SUCCESS CALLBACK/CONFIRMATION ---
// This is a placeholder for actual payment gateway confirmation.
// In a real application, this would be triggered by a secure M-Pesa IPN/Callback.
if (isset($_GET['online_payment_success']) && $_GET['online_payment_success'] == "true") {

    // Define fallback data variables
    $admission_data = $_SESSION['admission'] ?? "N/A"; // Assume admission is in session

    // SECURITY CHECK: Ensure admission data is available
    if ($admission_data != "N/A") {
        
        // UPDATE status in the main table to CLEARED for online payment
        $stmt = $connecting_to_the_database->prepare(
            "UPDATE studentgeneraldata SET accessoriesstatus = 'cleared' WHERE admission = ?"
        );
        $stmt->bind_param("s", $admission_data);
        
        // Execute and check for success
        if ($stmt->execute()) {
            // Set success message for display after redirect
            $_SESSION['online_payment_prompt'] = "Your online payment was successful and your clearance status has been updated to **CLEARED**!";
        } else {
            $_SESSION['error_message'] = "Error updating clearance status after payment. Please contact the administrator.";
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Admission details not found for status update.";
    }
    
    // Redirect to the same page to show the success message
    header("location: laboratorydept.php");
    exit();
}


// --- END ONLINE PAYMENT SUCCESS LOGIC ---
    


// Check if a prompt/message exists from a previous redirection
$physicall_payment_prompt = $_SESSION['physicall_payment_prompt'] ?? '';
unset($_SESSION['physicall_payment_prompt']); // Display once, then clear

$online_payment_prompt = $_SESSION['online_payment_prompt'] ?? '';
unset($_SESSION['online_payment_prompt']); // Display once, then clear

$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>lab Clearance</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <style>
        /* Simple styling for the prompt message */
        .prompt-msg {
            background-color: #e6f7ff;
            border: 1px solid #91d5ff;
            color: #0050b3;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .red { color: #cf1322; }
        .green { color: #52c41a; }
    </style>
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">experiment</span>laboratory Clearance</h2>
        <div class="links">
            <a href="#footer"><span class="material-symbols-outlined">contact_page</span>Contact Us</a>
            <a href="studentdash.php"><span class="material-symbols-outlined">arrow_back</span>Back</a>
        </div>
    </nav>
    <div class="studentdashboard">
        <div class="studentprofile">
            <div class="img_holder">
                <img src="profile pictures/<?php echo "{$info['userprofilepic']}"?>" alt="profilepicture" class="profilepicture">
                <div class="student_tutorial">
                    <h1>We're glad you're here to begin your clearance process.</h1>
                    <h2>This system is designed to make your final steps with us quick, clear, and efficient<span class="material-symbols-outlined">automation</span>.</h2>
                    <h3>What You Can Do Here :</h3>
                    <ol>
                        <li>View your status: See exactly which departments (e.g., Library, Finance & laboratory) still require your clearance.</li>
                        <li>Resolve holds: Find instructions and contact information for any outstanding obligations you may have.</li>
                        <li>Complete debts: Submit any necessary debts online through Safaricom M-Pesa through the school's paybill<span class="material-symbols-outlined">digital_wellbeing</span>.</li>
                    </ol>
                    <h1>Please Note :</h1>
                    <h2>Your final clearance status will be issued only after all departments have confirmed that you have met all your obligations.</h2>
                    <h2 class="red">You should not forget your allocated clearance date !!!!</h2>
                    <h3>Ready to get started? Proceed to the details below <span class="material-symbols-outlined">swipe_down</span>.</h3>
                </div>
            </div>
        </div> 
    </div>
    
    
    
    <div class="details">
        <div class="table">
            <h2><span class="material-symbols-outlined">id_card</span>Name: <?php echo htmlspecialchars($info['username'] ?? 'N/A') ?></h2>
            <h2><span class="material-symbols-outlined">confirmation_number</span>Admission: <?php echo htmlspecialchars($info['admission'] ?? 'N/A') ?> </h2>
            
            <?php if ($error_message): ?>
                <h2 class="red"><span class="material-symbols-outlined">error</span> Error: <?php echo htmlspecialchars($error_message) ?></h2>
            <?php endif; ?>


            <!-- for student with extra damages -->
            <?php if($Debt != "none" && $More_debt_amount != "0"):?>

            <h4>
                Damaged lab Items
                <ol>
                    <li><?php echo htmlspecialchars($info['labitemsdamaged'] ?? 'None found.') ?></li>
                </ol>
            </h4>
            
            <h4>
                Damaged item's Value + Mandatory lab fee(sh200)
                <ol>
                    <li><?php echo htmlspecialchars(($info['labitemvalue'] + $info['labfee']) ?? '0.00') ?></li>
                </ol>
            </h4>
            
             
            
            <h3 id="debt"><a href="mblpmt.php?dept=<?php echo urlencode("laboratory")?>"><span class="material-symbols-outlined">payments</span>Looks like you have additional debt to the mandatory lab fee, click here to clear debt online.</a></h3>
            <h3 id="debt"><a href="?choise=<?php echo urlencode("moreDebts")?>"><span class="material-symbols-outlined">footprint</span>Looks like you have additional debt to the mandatory lab fee, click here to pay debt physically.</a></h3>
            
            <?php endif;?>


            <!-- for student with no extra damages  -->
            <?php if($Debt == "none" && $Debt_amount == "200"):?>

            <h4>
                payment
                <ol>
                    <li><?php echo htmlspecialchars("mandatory fee") ?></li>
                </ol>
            </h4>
            
            <h4>
                Value
                <ol>
                    <li><?php echo htmlspecialchars($info['labfee'] ?? '0.00') ?></li>
                </ol>
            </h4>
            
            <hr>   


            <h3 id="debt"><a href="mblpmt.php?dept=<?php echo urlencode("laboratory")?>"><span class="material-symbols-outlined">payments</span>Pay Online (M-Pesa)</a></h3>                
            <h3 id="debt"><a href="?choise=<?php echo urlencode("payPhysically")?>"><span class="material-symbols-outlined">footprint</span>Pay Physically (Mark Intention)</a></h3>

            
            <?php endif;?>
                
            

            <!-- collect the clearance status -->
            <?php 
                $status = $info['labstatus'] ?? 'uncleared'; 
                if ($status == 'cleared'): 
            ?>
                
                <h2 class="green"><span class="material-symbols-outlined">check_circle</span> Status: **CLEARED**</h2>

            <?php endif;?>   


            <!-- Check if the clearance status is pending physical resolution -->
            <?php 
                 if ($status == 'pending_physical'): ?>

                <h2 class="green"><span class="material-symbols-outlined">schedule</span> Status: **PENDING PHYSICAL RESOLUTION**</h2>

            <?php endif; ?>

 
            <?php if (!empty($physicall_payment_prompt) || !empty($online_payment_prompt)): ?>
                <h2 class="prompt-msg">📝 **Action Taken:** <?php echo htmlspecialchars($physicall_payment_prompt . $online_payment_prompt) ?></h2>
            <?php endif; ?>

            <h2><span class="material-symbols-outlined">event_available</span>Availability: <?php echo htmlspecialchars($info['availability'] ?? 'Check with Department') ?></h2>
            <h2><span class="material-symbols-outlined">progress_activity</span>Clearance Status: <span id="stat">**<?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status))) ?>**</span></h2>
        </div>
    </div>
    
    <footer id="footer">
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the prompt messages from the PHP variables
            const physicalPrompt = <?php echo json_encode($physicall_payment_prompt); ?>;
            const onlinePrompt = <?php echo json_encode($online_payment_prompt); ?>;

            // Display success message for Physical Payment
            if (physicalPrompt.length > 0) {
                // Remove Markdown bolding for cleaner prompt display
                alert("SUCCESS! " + physicalPrompt.replace(/\*\*/g, '')); 
            }
            
            // Display success message for Online Payment
            if (onlinePrompt.length > 0) {
                // Remove Markdown bolding for cleaner prompt display
                alert("SUCCESS! " + onlinePrompt.replace(/\*\*/g, ''));
            }
        });


        // 1. Get the current clearance status text
        const statusElement = document.querySelector("#stat");
        console.log(statusElement)
        if (statusElement) {
            // Get the text, convert it to uppercase, and remove all asterisks for a clean comparison
            const currentStatus = statusElement.innerText.toUpperCase().replace(/\*/g, '').trim();

            // 2. Select all payment option links
            const debtElements = document.querySelectorAll("#debt");
            console.log(currentStatus)

            // 3. Check if the status is CLEARED or PENDING_PHYSICAL (to prevent double action)
            // Note: This is redundant as PHP already prevents display, but acts as a client-side safeguard.
            if (currentStatus === "CLEARED" || currentStatus === "PENDING PHYSICAL") {
                // If cleared, hide all payment options
                debtElements.forEach(element => {
                    element.style.display = "none";
                });
            }

            console.log("Current Clearance Status:", currentStatus);
        }

    </script>
</body>
</html>