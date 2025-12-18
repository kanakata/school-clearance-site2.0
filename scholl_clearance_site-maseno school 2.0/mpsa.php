<?php
// Ensure this file is accessed via HTTPS in a production environment!

// Includes for database connection, session, and student data
include 'student_general_data_collection.php';
// Include 'phy.php' if necessary for department-specific data, though $info should suffice.

// Check for required session data and department
if (!isset($_SESSION['admission'], $_SESSION['username']) || !isset($_GET['dept'])) {
    die("Error: Missing student or department data.");
}

$admission = $_SESSION['admission'];
$username = $_SESSION['username'];
$department = htmlspecialchars($_GET['dept']);

// --- 1. CONFIGURATION: M-Pesa Credentials (Replace with your actual keys) ---
// NOTE: These must be stored securely, ideally outside the document root and fetched
// from environment variables or a secure configuration file.
$consumerKey = "YOUR_CONSUMER_KEY"; 
$consumerSecret = "YOUR_CONSUMER_SECRET";
$businessShortCode = 174379; // C2B Paybill/Lipa Na M-Pesa Shortcode. Use 174379 for sandbox.
$passkey = "YOUR_MPESA_PASSKEY";
$lipaNaMpesaUrl = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest"; 
$callbackUrl = "https://yourdomain.com/mpesa_callback.php"; // MUST be HTTPS and accessible

// --- 2. PAYMENT DETAILS (You must fetch these dynamically) ---
// Fetch the amount and phone number from $info or a form submission.
// For this example, we'll use hardcoded data/placeholders.
$amount = $info['unpaidaccessoriesvalue'] ?? 1.00; // Use actual debt amount
$phone_number = "2547XXXXXXXX"; // Should come from $info or user input/session

// Prepend 254 and ensure 12 digits (M-Pesa API requirement)
$phone_number = preg_replace('/^0/', '254', $phone_number);
if (strlen($phone_number) !== 12) {
    // Basic validation, production code requires more robust checks
    die("Error: Invalid phone number format.");
}

// --- 3. Custom Message Generation ---
$custom_message = "Payment for missing accessories (Admission: {$admission}) to clear with {$department} department.";

// --- 4. Generate M-Pesa Base64 Password and Timestamp ---
$timestamp = date('YmdHis');
$password = base64_encode($businessShortCode . $passkey . $timestamp);

// --- 5. Get API Access Token ---
$auth_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
$curl = curl_init($auth_url);
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ':' . $consumerSecret);
$curl_response = curl_exec($curl);
curl_close($curl);
$access_token = json_decode($curl_response)->access_token;


// --- 6. Prepare STK Push Payload ---
$stk_payload = [
    'BusinessShortCode' => $businessShortCode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline', // Or 'CustomerBuyGoodsOnline'
    'Amount' => $amount, 
    'PartyA' => $phone_number, // Student's phone number
    'PartyB' => $businessShortCode, 
    'PhoneNumber' => $phone_number,
    'CallBackURL' => $callbackUrl,
    'AccountReference' => $admission, // Used for reconciliation
    'TransactionDesc' => "Clearance Fee - {$department}",
];

// --- 7. Initiate STK Push (API Call) ---
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $lipaNaMpesaUrl);
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($stk_payload));

$response = curl_exec($curl);
$stk_response = json_decode($response, true);
curl_close($curl);

// Check API response for success
if (isset($stk_response['ResponseCode']) && $stk_response['ResponseCode'] == 0) {
    $merchantRequestId = $stk_response['MerchantRequestID'];
    $checkoutRequestId = $stk_response['CheckoutRequestID'];
    
    // --- 8. Record Pending Transaction in 'payments' table ---
    $insert_payment = $connecting_to_the_database->prepare(
        "INSERT INTO payments (username, admission, phone_number, dept, amount, mpesa_message, merchant_request_id, checkout_request_id, transaction_status) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')"
    );
    
    $insert_payment->bind_param(
        "ssssdsss", 
        $username, 
        $admission, 
        $phone_number, 
        $department, 
        $amount, 
        $custom_message, 
        $merchantRequestId, 
        $checkoutRequestId
    );
    
    if ($insert_payment->execute()) {
        $_SESSION['success_message'] = "M-Pesa prompt sent! Check your phone to complete the payment.";
    } else {
        $_SESSION['error_message'] = "Payment initiation successful, but failed to log transaction in database.";
    }
    
    $insert_payment->close();

} else {
    // Log API failure details
    error_log("M-Pesa STK Push API Failure: " . $response);
    $_SESSION['error_message'] = "M-Pesa payment initiation failed. Please try again. Code: " . ($stk_response['ResponseCode'] ?? 'N/A');
}

// Redirect back to the accessories department page
header("location: accessoriesdept.php");
exit();

?>







<?php

include 'student_general_data_collection.php';

// --- Configuration Array for Departments and Database Columns ---
// This centralizes all the information that was previously repeated in the if/else blocks.
$dept_mapping = [
    'library'    => ['column' => 'bookmarketvalue', 'payment_desc' => 'book(s) lost'],
    'finance'    => ['column' => 'feebalance', 'payment_desc' => 'fee balance'],
    'boarding'   => ['column' => 'boardingitemsvalue', 'payment_desc' => 'damaged boarding items'],
    'accessories'=> ['column' => 'unpaidaccessoriesvalue', 'payment_desc' => 'unpaid accessories'],
    'games'      => ['column' => 'gamesitemvalue', 'payment_desc' => 'lost games item'],
    'laboratory' => ['column' => 'labfee', 'payment_desc' => 'lab fee'],
];

// Initialize variables outside the if block to prevent "Undefined variable" warnings in HTML
$dept = 'general';
$admission = isset($_SESSION['admission']) ? $_SESSION['admission'] : '';
$username = 'N/A';
$amount = 0;
$payment = 'General Payment';


// Check if a valid department is set in the URL and exists in our mapping
if (isset($_GET["dept"]) && array_key_exists($_GET["dept"], $dept_mapping)) {
    
    $requested_dept = $_GET['dept'];
    $config = $dept_mapping[$requested_dept];

    
    // Set variables for use in HTML
    $dept = $requested_dept . "dept";
    $payment = $config['payment_desc'];

    // Ensure admission is safe for use in the query
    $safe_admission = mysqli_real_escape_string($connecting_to_the_database, $admission);
    
    // Consolidated Query to fetch the student's data
    $stmt = "SELECT username, {$config['column']} FROM studentgeneraldata WHERE admission='$safe_admission' ";
    
    $query = mysqli_query($connecting_to_the_database, $stmt);

    // Check if the query ran successfully and returned a row
    if ($query && $info = mysqli_fetch_assoc($query)) {
        $username = $info['username'];
        $amount = $info[$config['column']];
    } else {
        // Fallback or error handling if student data couldn't be fetched
        // error_log("Database error or student not found for admission: " . $admission);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Clearance Payment</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">money</span>Online Payment</h2>
        <div class="links">
            <a href="#footer"><span class="material-symbols-outlined">contact_page</span>Contact Us</a>
            <!-- Back link directs to the root department page path -->
            <a href="<?php echo $dept?>.php"><span class="material-symbols-outlined">arrow_back</span>Back</a>
        </div>
    </nav>
    <div class="loginform">
        <form action="login_check.php" method="POST">
            <label for="login" class="login">
                <span class="material-symbols-outlined login_logo"><span class="material-symbols-outlined">money</span></span>
                <h2><?php echo htmlspecialchars($payment) ?> Payment</h2>
                <h3>A prompt will be sent to your phone, <br> check to complete the transaction.</h3>
                
                <label for="username" class="input_label">
                    <span class="material-symbols-outlined">id_card</span>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($username)?>" readonly>
                </label> 
                
                <label for="admission" class="input_label">
                    <span class="material-symbols-outlined">confirmation_number</span>
                    <input type="number" name="admission" placeholder="admission" value="<?php echo htmlspecialchars($admission) ?>" readonly>
                </label> 
                
                <label for="phone" class="input_label">
                    <span class="material-symbols-outlined">phone</span>
                    <input type="number" name="phone" placeholder="phone number" required>
                </label> 
                
                <label for="money" class="input_label">
                    <span class="material-symbols-outlined">money</span>
                    <input type="number" name="amount" placeholder="amount" value="<?php echo htmlspecialchars($amount)?>" readonly>
                </label> 
                
                <label for="password" class="input_label">
                    <span class="material-symbols-outlined">password_2</span>
                    <input type="password" name="password" placeholder="password" required>
                </label> 
                
                <input type="submit" value="Make Payment" name="payment">
                <div class="support">
                    <h2>Hi there, encountering any problems?</h2>
                    <a href="tel: 0793317819">Feel free to contact our student help line.</a>
                </div>
            </label>
        </form>
    </div>
    <footer id="footer">
        <div class="contacts">
            <h2><span class="material-symbols-outlined">contact_page</span>Contact us for any inquiries or difficulty making payment</h2>
            <a href="tel: 0793317819"><span class="material-symbols-outlined">contact_phone</span>tel: 0793317819</a><br>
            <a href="mailto: patrick37668@gmail.com"><span class="material-symbols-outlined">mail</span>email: patrick37668@gmail.com</a><br>
            <a href="https://kanakata.github.io/pegpem-portfolio/">&copy; copyright all rights reserved by peGPeM 2025</a><br>
            <a href="https://kanakata.github.io/pegpem-portfolio/"><span class="material-symbols-outlined">design_services</span>designed by pegpem.com</a><br>
        </div>
    </footer>
</body>
</html> 











<?php
// Ensure session is started if not already handled by included files
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// This file assumes $connecting_to_the_database is defined in student_general_data_collection.php
include 'student_general_data_collection.php';

// --- Configuration Array for Departments and Database Columns ---
$dept_mapping = [
    'library'=> ['column' => 'bookmarketvalue', 'payment_desc' => 'book(s) lost', 'status_col' => 'librarystatus'],
    'finance'=> ['column' => 'feebalance', 'payment_desc' => 'fee balance', 'status_col' => 'financemstatus'],
    'boarding' => ['column' => 'boardingitemsvalue', 'payment_desc' => 'damaged boarding items', 'status_col' => 'boardingstatus'],
    'accessories'=> ['column' => 'unpaidaccessoriesvalue', 'payment_desc' => 'unpaid accessories', 'status_col' => 'accessoriesstatus'],
    'games'=> ['column' => 'gamesitemvalue', 'payment_desc' => 'lost games item', 'status_col' => 'gamesstatus'],
    'laboratory' => ['column' => 'labfee', 'payment_desc' => 'lab fee', 'status_col' => 'laboratorystatus'],
];

// Initialize variables
$dept_code = 'general'; // Used for redirect: generaldept.php
$admission = isset($_SESSION['admission']) ? $_SESSION['admission'] : '';
$username = 'N/A';
$amount = 0.00;
$payment_desc = 'General Payment';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Fetch dynamic data based on URL department parameter
if (isset($_GET["dept"]) && array_key_exists($_GET["dept"], $dept_mapping)) {
    $requested_dept = $_GET['dept'];
    $config = $dept_mapping[$requested_dept];
    
    $dept_code = $requested_dept . "dept";
    $payment_desc = $config['payment_desc'];

    // Use prepared statement to securely fetch student data
    $stmt = $connecting_to_the_database->prepare("SELECT username, {$config['column']} FROM studentgeneraldata WHERE admission = ?");
    $stmt->bind_param("s", $admission);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $info = $result->fetch_assoc()) {
        $username = $info['username'];
        $amount = $info[$config['column']];
    }
    $stmt->close();
}

// --- START M-PESA STK PUSH LOGIC (Handles form submission) ---
if (isset($_POST["payment"])) {
    $phone_number_raw = $_POST['phone'];
    $payment_amount = $_POST['amount'];
    
    // --- 1. M-Pesa Configuration (REPLACE THESE PLACEHOLDERS) ---
    $consumerKey = "YOUR_CONSUMER_KEY"; 
    $consumerSecret = "YOUR_CONSUMER_SECRET";
    $businessShortCode = 174379; // Use 174379 for sandbox.
    $passkey = "YOUR_MPESA_PASSKEY";
    $lipaNaMpesaUrl = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest"; 
    // IMPORTANT: Callback URL MUST be HTTPS and accessible publicly.
    $callbackUrl = "https://yourdomain.com/mpesa_callback.php"; 
    
    // --- 2. Data Validation and Formatting ---
    // M-Pesa requires 254 format and a minimum amount (1.00 for testing)
    $phone_number = preg_replace('/^0/', '254', $phone_number_raw);
    if (strlen($phone_number) !== 12 || $payment_amount <= 0) {
        $_SESSION['error_message'] = "Invalid phone number or amount. Please check your input.";
        header("location: $dept_code.php");
        exit();
    }
    
    // --- 3. Custom Message Generation ---
    $transaction_dept = $_GET['dept'];
    $custom_message = "Payment of Ksh {$payment_amount} for {$payment_desc} (Admission: {$admission}) to {$transaction_dept} dept.";
    
    // --- 4. Generate M-Pesa Base64 Password and Timestamp ---
    $timestamp = date('YmdHis');
    $password = base64_encode($businessShortCode . $passkey . $timestamp);

    // --- 5. Get API Access Token ---
    $auth_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $curl = curl_init($auth_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ':' . $consumerSecret);
    $curl_response = curl_exec($curl);
    curl_close($curl);
    $access_token = json_decode($curl_response, true)['access_token'] ?? null;
    
    if (!$access_token) {
        $_SESSION['error_message'] = "Could not get M-Pesa access token.";
        header("location: $dept_code.php");
        exit();
    }

    // --- 6. Prepare STK Push Payload ---
    $stk_payload = [
        'BusinessShortCode' => $businessShortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline', 
        'Amount' => $payment_amount, 
        'PartyA' => $phone_number, 
        'PartyB' => $businessShortCode, 
        'PhoneNumber' => $phone_number,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => $admission, 
        'TransactionDesc' => "Clearance Fee - {$transaction_dept}",
    ];

    // --- 7. Initiate STK Push (API Call) ---
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $lipaNaMpesaUrl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($stk_payload));

    $response = curl_exec($curl);
    $stk_response = json_decode($response, true);
    curl_close($curl);

    if (isset($stk_response['ResponseCode']) && $stk_response['ResponseCode'] == 0) {
        $merchantRequestId = $stk_response['MerchantRequestID'];
        $checkoutRequestId = $stk_response['CheckoutRequestID'];
        
        // --- 8. Record Pending Transaction in 'payments' table ---
        // Record all details before redirecting
        $insert_payment = $connecting_to_the_database->prepare(
            "INSERT INTO payments (username, admission, phone_number, dept, amount, mpesa_message, merchant_request_id, checkout_request_id, transaction_status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')"
        );
        
        $insert_payment->bind_param(
            "ssssdsss", 
            $username, 
            $admission, 
            $phone_number, 
            $transaction_dept, 
            $payment_amount, 
            $custom_message, 
            $merchantRequestId, 
            $checkoutRequestId
        );
        
        if ($insert_payment->execute()) {
            $_SESSION['success_message'] = "M-Pesa prompt sent! Check your phone to complete the payment. (Ref: {$merchantRequestId})";
        } else {
            // Log this internally as the API call was successful
            error_log("DB INSERT FAILED for Mpesa. ID: {$merchantRequestId}. Error: " . $connecting_to_the_database->error);
            $_SESSION['error_message'] = "Payment initiated successfully, but failed to log transaction in database. Contact support with your admission and time of transaction.";
        }
        $insert_payment->close();

    } else {
        // Log API failure details
        error_log("M-Pesa STK Push API Failure: " . $response);
        $message = $stk_response['CustomerMessage'] ?? "Unknown Error.";
        $_SESSION['error_message'] = "M-Pesa payment initiation failed. {$message}. Please check your phone number and try again.";
    }

    // Redirect back to the department page
    header("location: $dept_code.php");
    exit();
}

// Re-initialize error/success messages for display in the form below
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Clearance Payment</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body>
    <nav>
       <h2><span class="material-symbols-outlined">money</span>Online Payment</h2>
        <div class="links">
            <a href="#footer"><span class="material-symbols-outlined">contact_page</span>Contact Us</a>
            <!-- Back link directs to the root department page path -->
            <a href="<?php echo htmlspecialchars($dept_code) ?>.php"><span class="material-symbols-outlined">arrow_back</span>Back</a>
    </div>
    </nav>
    <div class="loginform">

        <?php if ($error_message): ?>
            <div class="error-box">
                <span class="material-symbols-outlined">error</span>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success-box">
                <span class="material-symbols-outlined">check_circle</span>
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

    <!-- Form submits to this same file (mblpmt.php) -->
    <form action="mblpmt.php?dept=<?php echo htmlspecialchars($_GET['dept'] ?? 'general') ?>" method="POST">
    <label for="login" class="login">
    <span class="material-symbols-outlined login_logo"><span class="material-symbols-outlined">money</span></span>
    <h2><?php echo htmlspecialchars($payment_desc) ?> Payment</h2>
    <h3>A prompt will be sent to your M-Pesa phone number, <br> check and enter your PIN to complete the transaction.</h3>
    
    <label for="username" class="input_label">
    <span class="material-symbols-outlined">id_card</span>
    <input type="text" name="username" value="<?php echo htmlspecialchars($username)?>" readonly>
    </label> 
    
    <label for="admission" class="input_label">
    <span class="material-symbols-outlined">confirmation_number</span>
    <input type="number" name="admission" placeholder="admission" value="<?php echo htmlspecialchars($admission) ?>" readonly>
    </label> 
    
    <label for="phone" class="input_label">
    <span class="material-symbols-outlined">phone</span>
    <!-- User must enter their M-Pesa registered phone number -->
    <input type="number" name="phone" placeholder="M-Pesa Phone (e.g., 07XXXXXXXX)" required>
    </label> 
    
    <label for="money" class="input_label">
    <span class="material-symbols-outlined">money</span>
    <!-- Amount is readonly and fetched from the database -->
    <input type="number" name="amount" placeholder="amount" value="<?php echo htmlspecialchars($amount)?>" readonly>
    </label> 
    
    <input type="submit" value="Make Payment" name="payment">
    <div class="support">
    <h2>Hi there, encountering any problems?</h2>
    <a href="tel: 0793317819">Feel free to contact our student help line.</a>
    </div>
    </label>
    </form>
    </div>
    <footer id="footer">
    <div class="contacts">
    <h2><span class="material-symbols-outlined">contact_page</span>Contact us for any inquiries or difficulty making payment</h2>
    <a href="tel: 0793317819"><span class="material-symbols-outlined">contact_phone</span>tel: 0793317819</a><br>
    <a href="mailto: patrick37668@gmail.com"><span class="material-symbols-outlined">mail</span>email: patrick37668@gmail.com</a><br>
    <a href="https://kanakata.github.io/pegpem-portfolio/">&copy; copyright all rights reserved by peGPeM 2025</a><br>
    <a href="https://kanakata.github.io/pegpem-portfolio/"><span class="material-symbols-outlined">design_services</span>designed by pegpem.com</a><br>
    </div>
    </footer>
</body>
</html>




<!-- lacks the logic to update the studentgeneraldata table -->
<?php

error_reporting(0);
// Ensure session is started if not already handled by included files
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// This file assumes $connecting_to_the_database is defined in student_general_data_collection.php
include 'student_general_data_collection.php';

// --- Configuration Array for Departments and Database Columns ---
$dept_mapping = [
    'library'=> ['column' => 'bookmarketvalue', 'payment_desc' => 'book(s) lost', 'status_col' => 'librarystatus'],
    'finance'=> ['column' => 'feebalance', 'payment_desc' => 'fee balance', 'status_col' => 'financemstatus'],
    'boarding' => ['column' => 'boardingitemsvalue', 'payment_desc' => 'damaged boarding items', 'status_col' => 'boardingstatus'],
    'accessories'=> ['column' => 'unpaidaccessoriesvalue', 'payment_desc' => 'unpaid accessories', 'status_col' => 'accessoriesstatus'],
    'games'=> ['column' => 'gamesitemvalue', 'payment_desc' => 'lost games item', 'status_col' => 'gamesstatus'],
    'laboratory' => ['column' => 'labfee', 'payment_desc' => 'lab fee', 'status_col' => 'laboratorystatus'],
];

// Initialize variables
$dept_code = 'general'; // Used for redirect: generaldept.php
$admission = isset($_SESSION['admission']) ? $_SESSION['admission'] : '';
$username = 'N/A';
$amount = 0.00;
$payment_desc = 'General Payment';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Fetch dynamic data based on URL department parameter
if (isset($_GET["dept"]) && array_key_exists($_GET["dept"], $dept_mapping)) {
    $requested_dept = $_GET['dept'];
    $config = $dept_mapping[$requested_dept];
    
    $dept_code = $requested_dept . "dept";
    $payment_desc = $config['payment_desc'];

    // Use prepared statement to securely fetch student data
    $stmt = $connecting_to_the_database->prepare("SELECT username, {$config['column']} FROM studentgeneraldata WHERE admission = ?");
    $stmt->bind_param("s", $admission);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $info = $result->fetch_assoc()) {
        $username = $info['username'];
        $amount = $info[$config['column']];
    }
    $stmt->close();
}

// --- START M-PESA STK PUSH LOGIC (Handles form submission) ---
if (isset($_POST["payment"])) {
    $phone_number_raw = $_POST['phone'];
    $payment_amount = $_POST['amount'];
    
    // --- 1. M-Pesa Configuration (REPLACE THESE PLACEHOLDERS) ---
    $consumerKey = "YOUR_CONSUMER_KEY"; 
    $consumerSecret = "YOUR_CONSUMER_SECRET";
    $businessShortCode = 174379; // Use 174379 for sandbox.
    $passkey = "YOUR_MPESA_PASSKEY";
    $lipaNaMpesaUrl = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest"; 
    // IMPORTANT: Callback URL MUST be HTTPS and accessible publicly.
    $callbackUrl = "https://yourdomain.com/mpesa_callback.php"; 
    
    // --- 2. Data Validation and Formatting ---
    // M-Pesa requires 254 format and a minimum amount (1.00 for testing)
    $phone_number = preg_replace('/^0/', '254', $phone_number_raw);
    if (strlen($phone_number) !== 12 || $payment_amount <= 0) {
        $_SESSION['error_message'] = "Invalid phone number or amount. Please check your input.";
        header("location: $dept_code.php");
        exit();
    }
    
    // --- 3. Custom Message Generation ---
    $transaction_dept = $_GET['dept'];
    $custom_message = "Payment of Ksh {$payment_amount} for {$payment_desc} (Admission: {$admission}) to {$transaction_dept} dept.";
    
    // --- 4. Generate M-Pesa Base64 Password and Timestamp ---
    $timestamp = date('YmdHis');
    $password = base64_encode($businessShortCode . $passkey . $timestamp);

    // --- 5. Get API Access Token ---
    $auth_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $curl = curl_init($auth_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ':' . $consumerSecret);
    $curl_response = curl_exec($curl);
    curl_close($curl);
    $access_token = json_decode($curl_response, true)['access_token'] ?? null;
    
    if (!$access_token) {
        $_SESSION['error_message'] = "Could not get M-Pesa access token.";
        header("location: $dept_code.php");
        exit();
    }

    // --- 6. Prepare STK Push Payload ---
    $stk_payload = [
        'BusinessShortCode' => $businessShortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline', 
        'Amount' => $payment_amount, 
        'PartyA' => $phone_number, 
        'PartyB' => $businessShortCode, 
        'PhoneNumber' => $phone_number,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => $admission, 
        'TransactionDesc' => "Clearance Fee - {$transaction_dept}",
    ];

    // --- 7. Initiate STK Push (API Call) ---
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $lipaNaMpesaUrl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($stk_payload));

    $response = curl_exec($curl);
    $stk_response = json_decode($response, true);
    curl_close($curl);

    if (isset($stk_response['ResponseCode']) && $stk_response['ResponseCode'] == 0) {
        $merchantRequestId = $stk_response['MerchantRequestID'];
        $checkoutRequestId = $stk_response['CheckoutRequestID'];
        
        // --- 8. Record Pending Transaction in 'payments' table ---
        // Record all details before redirecting
        $insert_payment = $connecting_to_the_database->prepare(
            "INSERT INTO payments (username, admission, phone_number, dept, amount, mpesa_message, merchant_request_id, checkout_request_id, transaction_status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')"
        );
        
        $insert_payment->bind_param(
            "ssssdsss", 
            $username, 
            $admission, 
            $phone_number, 
            $transaction_dept, 
            $payment_amount, 
            $custom_message, 
            $merchantRequestId, 
            $checkoutRequestId
        );
        
        if ($insert_payment->execute()) {
            $_SESSION['success_message'] = "M-Pesa prompt sent! Check your phone to complete the payment. (Ref: {$merchantRequestId})";
        } else {
            // Log this internally as the API call was successful
            error_log("DB INSERT FAILED for Mpesa. ID: {$merchantRequestId}. Error: " . $connecting_to_the_database->error);
            $_SESSION['error_message'] = "Payment initiated successfully, but failed to log transaction in database. Contact support with your admission and time of transaction.";
        }
        $insert_payment->close();

    } else {
        // Log API failure details
        error_log("M-Pesa STK Push API Failure: " . $response);
        $message = $stk_response['CustomerMessage'] ?? "Unknown Error.";
        $_SESSION['error_message'] = "M-Pesa payment initiation failed. {$message}. Please check your phone number and try again.";
    }

    // Redirect back to the department page
    header("location: $dept_code.php");
    exit();
}

// Re-initialize error/success messages for display in the form below
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

?>

<!DOCTYPE html>
<html lang="en">
<head> <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Online Clearance Payment</title> <link rel="stylesheet" href="style.css"> <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <!-- Custom styles for the prompt/alert boxes (matching site primary colors) -->
    <style>
        .error-box, .success-box {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px 20px;
            margin: 15px auto;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            max-width: 90%;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        /* Success Prompt - Using a prominent green color */
        .success-box {
            background-color: #e6ffed; /* Light green background */
            color: #1a7f37; /* Dark green text */
            border: 1px solid #7bc67b;
        }
        .success-box .material-symbols-outlined {
            font-size: 28px;
            margin-right: 10px;
            color: #28a745; /* Darker green icon */
        }

        /* Error Prompt - Using a prominent red color */
        .error-box {
            background-color: #ffebe9; /* Light red background */
            color: #a72323; /* Dark red text */
            border: 1px solid #d9534f;
        }
        .error-box .material-symbols-outlined {
            font-size: 28px;
            margin-right: 10px;
            color: #dc3545; /* Darker red icon */
        }
    </style>
</head>
<body> <nav>   <h2><span class="material-symbols-outlined">money</span>Online Payment</h2>   <div class="links">    <a href="#footer"><span class="material-symbols-outlined">contact_page</span>Contact Us</a>    <!-- Back link directs to the root department page path -->    <a href="<?php echo htmlspecialchars($dept_code) ?>.php"><span class="material-symbols-outlined">arrow_back</span>Back</a>   </div> </nav> <div class="loginform">
        <?php if ($error_message): ?>
            <div class="error-box">
                <span class="material-symbols-outlined">error</span>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success-box">
                <span class="material-symbols-outlined">check_circle</span>
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>
   <!-- Form submits to this same file (mblpmt.php) -->   <form action="mblpmt.php?dept=<?php echo htmlspecialchars($_GET['dept'] ?? 'general') ?>" method="POST">    <label for="login" class="login">      <span class="material-symbols-outlined login_logo"><span class="material-symbols-outlined">money</span></span>      <h2><?php echo htmlspecialchars($payment_desc) ?> Payment</h2>      <h3>A prompt will be sent to your M-Pesa phone number, <br> check and enter your PIN to complete the transaction.</h3>            <label for="username" class="input_label">       <span class="material-symbols-outlined">id_card</span>       <input type="text" name="username" value="<?php echo htmlspecialchars($username)?>" readonly>      </label>             <label for="admission" class="input_label">       <span class="material-symbols-outlined">confirmation_number</span>       <input type="number" name="admission" placeholder="admission" value="<?php echo htmlspecialchars($admission) ?>" readonly>      </label>             <label for="phone" class="input_label">       <span class="material-symbols-outlined">phone</span>       <!-- User must enter their M-Pesa registered phone number -->       <input type="number" name="phone" placeholder="M-Pesa Phone (e.g., 07XXXXXXXX)" required>      </label>             <label for="money" class="input_label">       <span class="material-symbols-outlined">money</span>       <!-- Amount is readonly and fetched from the database -->       <input type="number" name="amount" placeholder="amount" value="<?php echo htmlspecialchars($amount)?>" readonly>      </label>             <input type="submit" value="Make Payment" name="payment">      <div class="support">       <h2>Hi there, encountering any problems?</h2>       <a href="tel: 0793317819">Feel free to contact our student help line.</a>      </div>    </label>   </form> </div> <footer id="footer">   <div class="contacts">    <h2><span class="material-symbols-outlined">contact_page</span>Contact us for any inquiries or difficulty making payment</h2>    <a href="tel: 0793317819"><span class="material-symbols-outlined">contact_phone</span>tel: 0793317819</a><br>    <a href="mailto: patrick37668@gmail.com"><span class="material-symbols-outlined">mail</span>email: patrick37668@gmail.com</a><br>    <a href="https://kanakata.github.io/pegpem-portfolio/">&copy; copyright all rights reserved by peGPeM 2025</a><br>    <a href="https://kanakata.github.io/pegpem-portfolio/"><span class="material-symbols-outlined">design_services</span>designed by pegpem.com</a><br>   </div> </footer>
</body>
</html>

<!-- oudated clearance boilerplate -->

<?php

// Assuming these files securely handle connection ($connecting_to_the_database) 
// and session management, and define $info and $phy arrays.
include 'student_general_data_collection.php';
include 'phy.php';

// Check if the physical payment option has been selected
if (isset($_GET['choise']) && $_GET['choise'] == "payPhysically") {
    
    // Define fallback data variables
    $username_data = $phy["username"] ?? $_SESSION['username'] ?? "Student";
    $admission_data = $phy["admission"] ?? $_SESSION['admission'] ?? "N/A";
    $year_data = $phy["year"] ?? date("Y"); 
    $accessories_data = $phy["accessories"] ?? "Missing Items";
    
    // 1. UPDATE status in the main table to PENDING for physical clearance
    // SECURITY FIX: Setting status to 'pending_physical' instead of 'cleared'
    $stmt = $connecting_to_the_database->prepare(
        "UPDATE studentgeneraldata SET accessoriesstatus = 'pending_physical' WHERE admission = ?"
    );
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
                "UPDATE physicall SET username = ?, year = ?, accessories = ?, accessoriesstatus = 'pending_physical_payment' WHERE admission = ?"
            );
            $upsert_stmt->bind_param("ssss", $username_data, $year_data, $accessories_data, $admission_data);
            $upsert_stmt->execute();
            $upsert_stmt->close();
        } else {
            // C. RECORD DOES NOT EXIST: INSERT a new row
            $upsert_stmt = $connecting_to_the_database->prepare(
                "INSERT INTO physicall (username, admission, year, accessories, accessoriesstatus) 
                VALUES (?, ?, ?, ?, 'pending_physical_payment')"
            );
            $upsert_stmt->bind_param("sssss", $username_data, $admission_data, $year_data, $accessories_data);
            $upsert_stmt->execute();
            $upsert_stmt->close();
        }
        
        // --- END UPSERT LOGIC ---

        // Set success message for display after redirect
        $_SESSION['physicall_payment_prompt'] = "You have selected physical payment. Please bring the following on your clearance day: **" . htmlspecialchars($info['unpaidaccessories'] ?? 'Missing Item Details') . "**";

    } else {
        $_SESSION['error_message'] = "Error recording physical payment choice. Please try again.";
    }

    $stmt->close();
    
    header("location: accessoriesdept.php");
    exit();
}

// Check if a prompt/message exists from a previous redirection
$physicall_payment_prompt = $_SESSION['physicall_payment_prompt'] ?? '';
unset($_SESSION['physicall_payment_prompt']); // Display once, then clear

$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accessories Clearance</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">computer</span>Accessories Clearance</h2>
        <div class="links">
            <a href="#footer"><span class="material-symbols-outlined">contact_page</span>Contact Us</a>
            <a href="studentdash.php"><span class="material-symbols-outlined">arrow_back</span>Back</a>
        </div>
    </nav>
    <div class="studentdashboard">
        <div class="studentprofile">
            <div class="img_holder">
                <img src="profile pictures/admn4761.jpg" alt="profilepicture" class="profilepicture">
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
    
    <hr>
    
    <div class="details">
        <div class="table">
            <h2><span class="material-symbols-outlined">id_card</span>Name: <?php echo htmlspecialchars($info['username'] ?? 'N/A') ?></h2>
            <h2><span class="material-symbols-outlined">confirmation_number</span>Admission: <?php echo htmlspecialchars($info['admission'] ?? 'N/A') ?> </h2>
            
            <?php if ($error_message): ?>
                <h2 class="red"><span class="material-symbols-outlined">error</span> Error: <?php echo htmlspecialchars($error_message) ?></h2>
            <?php endif; ?>

            <h4>
                Missing Items
                <ol>
                    <li><?php echo htmlspecialchars($info['unpaidaccessories'] ?? 'None found.') ?></li>
                </ol>
            </h4>
            
            <h4>
                Item's Value
                <ol>
                    <li><?php echo htmlspecialchars($info['unpaidaccessoriesvalue'] ?? '0.00') ?></li>
                </ol>
            </h4>
            
            <hr>
            
            <?php 
                $status = $info['accessoriesstatus'] ?? 'uncleared';
                if ($status == 'cleared'): 
            ?>
                <h2 class="green"><span class="material-symbols-outlined">check_circle</span> Status: CLEARED</h2>

            <?php elseif ($status == 'pending_physical'): ?>

                <h2 class="green"><span class="material-symbols-outlined">schedule</span> Status: PENDING PHYSICAL RESOLUTION</h2>

            <?php else: ?>

                <h3><a href="mblpmt.php?dept=<?php echo urlencode("accessories")?>"><span class="material-symbols-outlined">payments</span>Pay Online (M-Pesa)</a></h3>
                
                <h3><a href="?choise=<?php echo urlencode("payPhysically")?>"><span class="material-symbols-outlined">footprint</span>Pay Physically (Mark Intention)</a></h3>
                
            <?php endif; ?>

            <?php if (!empty($physicall_payment_prompt)): ?>
                <h2 class="prompt-msg">📝 **Action Taken:** <?php echo htmlspecialchars($physicall_payment_prompt) ?></h2>
            <?php endif; ?>

            <h2><span class="material-symbols-outlined">event_available</span>Availability: <?php echo htmlspecialchars($info['availability'] ?? 'Check with Department') ?></h2>
            <h2><span class="material-symbols-outlined">progress_activity</span>Clearance Status: <?php echo htmlspecialchars($status) ?></h2>
        </div>
    </div>
    
    <footer id="footer">
    </footer>
</body>
</html>



<!-- oudated mblpmt.php -->

<?php
error_reporting(0);
// Ensure session is started if not already handled by included files
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// This file assumes $connecting_to_the_database is defined in student_general_data_collection.php
include 'student_general_data_collection.php';




//COLLECTING THE PAYMENT DATA LOGIC
// --- Configuration Array for Departments and Database Columns ---
$dept_mapping = [
    'library'=> ['column' => 'bookmarketvalue', 'payment_desc' => 'book(s) lost', 'status_col' => 'librarystatus'],
    'finance'=> ['column' => 'feebalance', 'payment_desc' => 'fee balance', 'status_col' => 'financemstatus'],
    'boarding' => ['column' => 'boardingitemsvalue', 'payment_desc' => 'damaged boarding items', 'status_col' => 'boardingstatus'],
    'accessories'=> ['column' => 'unpaidaccessoriesvalue', 'payment_desc' => 'unpaid accessories', 'status_col' => 'accessoriesstatus'],
    'games'=> ['column' => 'gamesitemvalue', 'payment_desc' => 'lost games item', 'status_col' => 'gamesstatus'],
    'laboratory' => ['column' => 'labfee', 'payment_desc' => 'lab fee', 'status_col' => 'laboratorystatus'],
];

// Initialize variables
$dept_code = 'general'; // Used for redirect: generaldept.php
$admission = isset($_SESSION['admission']) ? $_SESSION['admission'] : '';
$username = 'N/A';
$amount = 0.00;
$payment_desc = 'General Payment';
$status_column = ''; // Initialize the column name for the status update
// Re-initialize error/success messages from session
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// Fetch dynamic data based on URL department parameter
if (isset($_GET["dept"]) && array_key_exists($_GET["dept"], $dept_mapping)) {
    $requested_dept = $_GET['dept'];
    $config = $dept_mapping[$requested_dept];
    
    $dept_code = $requested_dept . "dept";
    $payment_desc = $config['payment_desc'];
    $status_column = $config['status_col']; // Store the status column name
    
    // Use prepared statement to securely fetch student data
    $stmt = $connecting_to_the_database->prepare("SELECT username, {$config['column']} FROM studentgeneraldata WHERE admission = ?");
    $stmt->bind_param("s", $admission);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $info = $result->fetch_assoc()) {
        $username = $info['username'];
        $amount = $info[$config['column']];
    }
    $stmt->close();
}





// --- START M-PESA STK PUSH LOGIC (Handles form submission) ---
if (isset($_POST["payment"])) {
    $phone_number_raw = $_POST['phone'];
    $payment_amount = $_POST['amount'];
    
    //1. M-Pesa Configuration 
    $consumerKey = "ePgSJtTDfiGQLWV4jvAwvPRAONbTDO5xuGz6jV1Gb33MUaSr"; 
    $consumerSecret = "McVEpAYtIvxbEltsrv970gdPUWr7iNWYFIkWIOfyYRVDBDX6a30WH6N0LQsBq7rM";
    $businessShortCode = 174379; // Use 174379 for sandbox.
    $passkey = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
    $lipaNaMpesaUrl = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest"; 
    // IMPORTANT: Callback URL MUST be HTTPS and accessible publicly. This URL receives payment confirmation.
    $callbackUrl = "https://thirdly-ateliotic-katina.ngrok-free.dev"; 
    
    //2. Data Validation and Formatting
    // M-Pesa requires 254 format and a minimum amount (1.00 for testing)
    $phone_number = preg_replace('/^0/', '254', $phone_number_raw);
    // Ensure amount is at least 1 for testing purposes or greater than 0
    $payment_amount = max(1.00, (float)$payment_amount); 


    //confirming the correct phone number length
    if (strlen($phone_number) !== 12 || $payment_amount <= 0) {
        $_SESSION['error_message'] = "Invalid phone number or amount. Please check your input.";
        header("location: $dept_code.php");
        exit();
    }
    
    //3. Custom Message Generation
    $transaction_dept = $_GET['dept'] ?? 'general';
    $custom_message = "Payment of Ksh {$payment_amount} for {$payment_desc} (Admission: {$admission}) to {$transaction_dept} dept.";
    
    //4. Generate M-Pesa Base64 Password and Timestamp
    $timestamp = date('YmdHis');
    $password = base64_encode($businessShortCode . $passkey . $timestamp);

    //5. Get API Access Token ---
    $auth_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $curl = curl_init($auth_url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_USERPWD, $consumerKey . ':' . $consumerSecret);
    $curl_response = curl_exec($curl);
    curl_close($curl);
    $access_token = json_decode($curl_response, true)['access_token'] ?? null;
    
    if (!$access_token) {
        $_SESSION['error_message'] = "Could not get M-Pesa access token.";
        header("location: $dept_code.php");
        exit();
    }

    //6. Prepare STK Push Payload ---
    $stk_payload = [
        'BusinessShortCode' => $businessShortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline', 
        'Amount' => $payment_amount, 
        'PartyA' => $phone_number, 
        'PartyB' => $businessShortCode, 
        'PhoneNumber' => $phone_number,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => $admission, 
        'TransactionDesc' => "Clearance Fee - {$transaction_dept}",
    ];

    //7. Initiate STK Push (API Call) ---
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $lipaNaMpesaUrl);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $access_token]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($stk_payload));

    $response = curl_exec($curl);
    $stk_response = json_decode($response, true);
    curl_close($curl);

    if (isset($stk_response['ResponseCode']) && $stk_response['ResponseCode'] == 0) {
        $merchantRequestId = $stk_response['MerchantRequestID'];
        $checkoutRequestId = $stk_response['CheckoutRequestID'];
        
        //8. Record Pending Transaction in 'payments' table ---
        $insert_payment = $connecting_to_the_database->prepare(
            "INSERT INTO payments (username, admission, phone_number, dept, amount, mpesa_message, merchant_request_id, checkout_request_id, transaction_status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')"
        );
        
        $insert_payment->bind_param(
            "ssssdsss", 
            $username, 
            $admission, 
            $phone_number, 
            $transaction_dept, 
            $payment_amount, 
            $custom_message, 
            $merchantRequestId, 
            $checkoutRequestId
        );
        
        if ($insert_payment->execute()) {
            $_SESSION['success_message'] = "M-Pesa prompt sent! Check your phone to complete the payment. (Ref: {$merchantRequestId})";
        } else {
            error_log("DB INSERT FAILED for Mpesa. ID: {$merchantRequestId}. Error: " . $connecting_to_the_database->error);
            $_SESSION['error_message'] = "Payment initiated successfully, but failed to log transaction in database. Contact support with your admission and time of transaction.";
        }
        $insert_payment->close();

        // =======================================================================
        // --- !!! CRITICAL SECURITY WARNING: CLEARANCE LOGIC PLACEMENT !!! ---
        // =======================================================================
        /* // DO NOT UNCOMMENT THIS BLOCK. THIS CLEARS THE STUDENT BEFORE PAYMENT IS CONFIRMED.
        // This logic is implemented as requested but MUST be executed in the 
        // M-Pesa Callback URL script (mpesa_callback.php) after payment confirmation.
        
        if (!empty($status_column) && $admission) {
            // Update the clearance status column to 'Cleared'
            $update_status = $connecting_to_the_database->prepare(
                "UPDATE studentgeneraldata SET {$status_column} = 'Cleared' WHERE admission = ?"
            );
            $update_status->bind_param("s", $admission);
            
            if ($update_status->execute()) {
                error_log("WARNING: Clearance status set to 'Cleared' on STK Push initiation for Admission: {$admission}, Dept: {$status_column}. THIS IS INSECURE.");
            } else {
                 error_log("Failed to execute INSECURE clearance update for Admission: {$admission}. Error: " . $connecting_to_the_database->error);
            }
            $update_status->close();
        }
        */
        // =======================================================================
        // --- END INSECURE CLEARANCE LOGIC BLOCK ---
        // =======================================================================

    } else {
        // Log API failure details
        error_log("M-Pesa STK Push API Failure: " . $response);
        $message = $stk_response['CustomerMessage'] ?? "Unknown Error.";
        $_SESSION['error_message'] = "M-Pesa payment initiation failed. {$message}. Please check your phone number and try again.";
    }

    // Redirect back to the department page
    header("location: $dept_code.php");
    exit();
}

// Re-initialize error/success messages for display in the form below
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Clearance Payment</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <!-- Custom styles for the prompt/alert boxes (matching site primary colors) -->
    <style>
        .error-box, .success-box {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px 20px;
            margin: 15px auto;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            max-width: 90%;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        /* Success Prompt - Using a prominent green color */
        .success-box {
            background-color: #e6ffed; /* Light green background */
            color: #1a7f37; /* Dark green text */
            border: 1px solid #7bc67b;
        }
        .success-box .material-symbols-outlined {
            font-size: 28px;
            margin-right: 10px;
            color: #28a745; /* Darker green icon */
        }

        /* Error Prompt - Using a prominent red color */
        .error-box {
            background-color: #ffebe9; /* Light red background */
            color: #a72323; /* Dark red text */
            border: 1px solid #d9534f;
        }
        .error-box .material-symbols-outlined {
            font-size: 28px;
            margin-right: 10px;
            color: #dc3545; /* Darker red icon */
        }
    </style>
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">money</span>Online Payment</h2>
        <div class="links">
            <a href="#footer"><span class="material-symbols-outlined">contact_page</span>Contact Us</a>
            <!-- Back link directs to the root department page path -->
            <a href="<?php echo htmlspecialchars($dept_code) ?>.php"><span class="material-symbols-outlined">arrow_back</span>Back</a>
        </div>
    </nav>
    <div class="loginform">
        <?php if ($error_message): ?>
            <div class="error-box">
                <span class="material-symbols-outlined">error</span>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success-box">
                <span class="material-symbols-outlined">check_circle</span>
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Form submits to this same file (mblpmt.php) -->
        <form action="mblpmt.php?dept=<?php echo htmlspecialchars($_GET['dept'] ?? 'general') ?>" method="POST">
            <label for="login" class="login">
                <span class="material-symbols-outlined login_logo"><span class="material-symbols-outlined">money</span></span>
                <h2><?php echo htmlspecialchars($payment_desc) ?> Payment</h2>
                <h3>A prompt will be sent to your M-Pesa phone number, <br> check and enter your PIN to complete the transaction.</h3>
                
                <label for="username" class="input_label">
                    <span class="material-symbols-outlined">id_card</span>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($username)?>" readonly>
                </label> 
                
                <label for="admission" class="input_label">
                    <span class="material-symbols-outlined">confirmation_number</span>
                    <input type="number" name="admission" placeholder="admission" value="<?php echo htmlspecialchars($admission) ?>" readonly>
                </label> 
                
                <label for="phone" class="input_label">
                    <span class="material-symbols-outlined">phone</span>
                    <!-- User must enter their M-Pesa registered phone number -->
                    <input type="number" name="phone" placeholder="M-Pesa Phone (e.g., 07XXXXXXXX)" required>
                </label> 
                
                <label for="money" class="input_label">
                    <span class="material-symbols-outlined">money</span>
                    <!-- Amount is readonly and fetched from the database -->
                    <input type="number" name="amount" placeholder="amount" value="<?php echo htmlspecialchars($amount)?>" readonly>
                </label> 
                
                <input type="submit" value="Make Payment" name="payment">
                <div class="support">
                    <h2>Hi there, encountering any problems?</h2>
                    <a href="tel: 0793317819">Feel free to contact our student help line.</a>
                </div>
            </label>
        </form>
    </div>
    <footer id="footer">
        <div class="contacts">
            <h2><span class="material-symbols-outlined">contact_page</span>Contact us for any inquiries or difficulty making payment</h2>
            <a href="tel: 0793317819"><span class="material-symbols-outlined">contact_phone</span>tel: 0793317819</a><br>
            <a href="mailto: patrick37668@gmail.com"><span class="material-symbols-outlined">mail</span>email: patrick37668@gmail.com</a><br>
            <a href="https://kanakata.github.io/pegpem-portfolio/">&copy; copyright all rights reserved by peGPeM 2025</a><br>
            <a href="https://kanakata.github.io/pegpem-portfolio/"><span class="material-symbols-outlined">design_services</span>designed by pegpem.com</a><br>
        </div>
    </footer>
</body>
</html>


<!-- outdated callback.php -->
 <?php
// mpesa_callback.php
// This script receives the transaction result (success or failure) from the M-Pesa API.
// It is critical for confirming payment before granting clearance.

// 1. --- Configuration & Setup ---

// Log all incoming requests for debugging purposes (IMPORTANT)
// Ensure this log file is writable by the web server
$logFile = 'mpesa_callback_log.txt';
$logEntry = date('Y-m-d H:i:s') . " - Incoming M-Pesa Callback:\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

// Get the raw POST data from the M-Pesa API
$callback_data = file_get_contents('php://input');
file_put_contents($logFile, $callback_data . "\n\n", FILE_APPEND);

// Decode the JSON data
$data = json_decode($callback_data, true);

// If the data is empty or corrupted, stop execution and return a success response to M-Pesa (to prevent retry attempts)
if (empty($data) || !isset($data['Body']['stkCallback'])) {
    // Respond to M-Pesa immediately with ResultCode 0 to acknowledge receipt
    echo json_encode(["ResultCode" => 0, "ResultDesc" => "C2B received successfully, but data was invalid."]);
    exit();
}

// Include database connection (assuming this file exists and defines $connecting_to_the_database)
// Ensure 'student_general_data_collection.php' establishes the database connection
include 'student_general_data_collection.php';


// 2. --- Extracting Key Transaction Data ---

$stkCallback = $data['Body']['stkCallback'];
$resultCode = $stkCallback['ResultCode'];
$merchantRequestId = $stkCallback['MerchantRequestID'];
$checkoutRequestId = $stkCallback['CheckoutRequestID'];

// Check if the payment was successful (ResultCode 0 is success)
if ($resultCode == 0) {
    // Payment was successful.
    $callbackMetadata = $stkCallback['CallbackMetadata']['Item'] ?? [];
    
    $mpesaReceiptNumber = '';
    $transactionDate = '';
    $phoneNumber = '';
    $amount = 0.00;

    // Iterate through the metadata to extract details
    foreach ($callbackMetadata as $item) {
        switch ($item['Name']) {
            case 'MpesaReceiptNumber':
                $mpesaReceiptNumber = $item['Value'];
                break;
            case 'TransactionDate':
                $transactionDate = $item['Value'];
                break;
            case 'PhoneNumber':
                $phoneNumber = $item['Value'];
                break;
            case 'Amount':
                $amount = (float)$item['Value'];
                break;
        }
    }
    
    // 3. --- Securely Retrieve Pending Transaction Details ---
    // Use the unique MerchantRequestID to find the original transaction in our 'payments' table
    $stmt_fetch = $connecting_to_the_database->prepare(
        "SELECT admission, dept FROM payments WHERE merchant_request_id = ? AND transaction_status = 'PENDING'"
    );
    $stmt_fetch->bind_param("s", $merchantRequestId);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    
    if ($result_fetch->num_rows === 1) {
        $pending_transaction = $result_fetch->fetch_assoc();
        $admission = $pending_transaction['admission'];
        $dept_name = $pending_transaction['dept'];
        $stmt_fetch->close();

        // 4. --- Map Department to Clearance Column ---
        // Must match the column names used in student_general_data_collection.php
        $dept_mapping = [
            'library'    => 'librarystatus',
            'finance'    => 'financemstatus', 
            'boarding'   => 'boardingstatus',
            'accessories'=> 'accessoriesstatus',
            'games'      => 'gamesstatus',
            'laboratory' => 'laboratorystatus',
        ];
        
        $status_column = $dept_mapping[$dept_name] ?? null;
        
        if ($status_column) {
            
            // 5. --- UPDATE: Grant Clearance and Update Transaction Log ---

            // A. Update the 'payments' table to CONFIRMED
            $stmt_update_payment = $connecting_to_the_database->prepare(
                "UPDATE payments SET transaction_status = 'CONFIRMED', mpesa_receipt = ?, transaction_date = ? WHERE merchant_request_id = ?"
            );
            $stmt_update_payment->bind_param("sss", $mpesaReceiptNumber, $transactionDate, $merchantRequestId);
            $stmt_update_payment->execute();
            $stmt_update_payment->close();

            // B. Update student clearance status in studentgeneraldata (SECURE CLEARANCE)
            $stmt_update_clearance = $connecting_to_the_database->prepare(
                "UPDATE studentgeneraldata SET {$status_column} = 'Cleared' WHERE admission = ?"
            );
            $stmt_update_clearance->bind_param("s", $admission);
            $stmt_update_clearance->execute();
            $stmt_update_clearance->close();

            // Log successful clearance
            file_put_contents($logFile, "SUCCESS: Clearance granted for Admission {$admission} in {$dept_name} (Column: {$status_column}). Receipt: {$mpesaReceiptNumber}\n", FILE_APPEND);

        } else {
            file_put_contents($logFile, "ERROR: Department mapping failed for '{$dept_name}' for MerchantRequestID: {$merchantRequestId}\n", FILE_APPEND);
        }
        
    } else {
        // Payment was successful but no matching PENDING transaction found (or multiple found, which is an error)
        file_put_contents($logFile, "ERROR: Successful payment confirmation received, but failed to find or match PENDING transaction for RequestID: {$merchantRequestId}\n", FILE_APPEND);
    }

} else {
    // 6. --- Handle Failed or Cancelled Payment ---
    $resultDesc = $stkCallback['ResultDesc'] ?? 'Payment Failed.';
    
    // Update the 'payments' table to FAILED (if it was pending)
    $stmt_fail = $connecting_to_the_database->prepare(
        "UPDATE payments SET transaction_status = 'FAILED', mpesa_message = ? WHERE merchant_request_id = ? AND transaction_status = 'PENDING'"
    );
    $stmt_fail->bind_param("ss", $resultDesc, $merchantRequestId);
    $stmt_fail->execute();
    $stmt_fail->close();

    file_put_contents($logFile, "FAILED: Transaction failed/cancelled for MerchantRequestID: {$merchantRequestId}. Reason: {$resultDesc}\n", FILE_APPEND);
}


// 7. --- Final API Response ---
// M-Pesa requires a specific JSON response to acknowledge receipt and prevent retries.
echo json_encode(["ResultCode" => 0, "ResultDesc" => "C2B received successfully."]);

// Note: It is vital that this script executes quickly and returns the success JSON above.
// No HTML output should be generated.
?>


<!-- outdated finance.php -->
 <?php

include "student_general_data_collection.php"

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>finanace</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">finance</span>finance</h2>
        <div class="links">
            <a href="#footer"><span class="material-symbols-outlined">contact_page</span>contact us</a>
            <a href="studentdash.php"><span class="material-symbols-outlined">arrow_back</span>back</a>
        </div>
    </nav>
    <div class="studentdashboard">
    <div class="studentprofile">
            <div class="img_holder">
                <img src="profile pictures/admn4761.jpg" alt="profilepicture" class="profilepicture">
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
    </div>  
    </div>
    <div class="details">
        <div class="table">
            <h2><span class="material-symbols-outlined">id_card</span>name : <?php echo "{$info['username']}"?></h2>
            <h2><span class="material-symbols-outlined">confirmation_number</span>admission : <?php echo "{$info['admission']}"?></h2>
            <h2><span class="material-symbols-outlined">finance</span>fee balance : sh<?php echo "{$info['feebalance']}"?></h2>
            <h3><a href="mblpmt.php?dept=<?= urlencode("finance")?>"><span class="material-symbols-outlined">payments</span>pay online</a></h3>
            <h3><a href="#"><span class="material-symbols-outlined">footprint</span>pay physically</a></h3>
            <h3><a href="#"><span class="material-symbols-outlined">clear_all</span>clear from department</a></h3>
            <h2><span class="material-symbols-outlined">progress_activity</span>clearance status : <?php echo "{$info['feestatus']}"?></h2>
        </div>
        <!-- <table>
            <th>name</th>
            <th>admission</th>
            <th>fee balance</th>
            <th>pay online</th>
            <th>pay physically</th>
            <th>clearance status</th>
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td id="pay"><a href="#">pay</a></td>
                <td id="pay"><a href="#">yes</a></td>
                <td></td>
            </tr>
        </table> -->
    </div>
</body>
</html>


<?php
error_reporting(0);
// Assuming these files securely handle connection ($connecting_to_the_database) 
// and session management, and define $info array.
include 'student_general_data_collection.php';
// Include phy.php if needed for physical payment insert, though finance often uses a different table.
// For now, assuming only the main status update is needed for the physical intent.

// Initialize messages from session and clear them
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

// --- CONSOLIDATED MESSAGE HANDLING ---
$physicall_payment_prompt = $_SESSION['physicall_payment_prompt'] ?? '';
unset($_SESSION['physicall_payment_prompt']);

$online_payment_prompt = $_SESSION['online_payment_prompt'] ?? '';
unset($_SESSION['online_payment_prompt']);
// -------------------------------------


// --- LOGIC FOR ONLINE PAYMENT SUCCESS (Simulated Callback Confirmation) ---
if (isset($_GET['online_payment_success']) && $_GET['online_payment_success'] === "true" && isset($_SESSION['admission'])) {
    
    // ⚠️ CRITICAL: In a real system, this update should ONLY occur in the M-Pesa Callback handler (mpesa_callback.php)
    
    $status_column = 'feestatus';
    
    // Update the clearance status column to 'cleared'
    $update_status = $connecting_to_the_database->prepare(
        "UPDATE studentgeneraldata SET {$status_column} = 'cleared' WHERE admission = ?"
    );
    $update_status->bind_param("s", $_SESSION['admission']);
    
    if ($update_status->execute()) {
        $_SESSION['online_payment_prompt'] = "✅ Online payment confirmed. Your **Finance** clearance status has been successfully updated to 'cleared'.";
        // Redirect to reflect the change immediately
        header("location: financedept.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating clearance status after online payment simulation.";
        header("location: financedept.php");
        exit();
    }
}


// --- LOGIC FOR PHYSICAL PAYMENT SELECTION ---
if (isset($_GET['choise']) && $_GET['choise'] == "payPhysically") {
    
    // 1. UPDATE status in the main table to PENDING for physical clearance
    // This tells the system the student intends to resolve this physically.
    $stmt = $connecting_to_the_database->prepare(
        "UPDATE studentgeneraldata SET feestatus = 'pending_physical' WHERE admission = ?"
    );
    $stmt->bind_param("s", $_SESSION['admission']);
    
    // Execute and check for success
    if ($stmt->execute()) {
        
        // 2. Insert record into the 'physicall' table (Finance resolution item)
        // This is a placeholder as finance departments usually have their own manual process tables.
        // Assuming we need to record the balance being paid physically.
        $fee_balance = $info["feebalance"] ?? '0.00'; 
        $username_data = $info["username"] ?? $_SESSION['username'] ?? "Student";
        $admission_data = $info["admission"] ?? $_SESSION['admission'] ?? "N/A";
        $year_data = date("Y"); 
        
        // Using a generalized table structure for physical clearance tracking
        $insert = $connecting_to_the_database->prepare(
             "INSERT INTO physicall (username, admission, year, dept_item, dept_status) 
             VALUES (?, ?, ?, ?, 'pending_physical_payment')"
        );
        // Note: For Finance, 'dept_item' is being used to store the outstanding fee balance
        $insert->bind_param("ssss", $username_data, $admission_data, $year_data, $fee_balance);
        @$insert->execute(); // @ suppresses errors on duplicate keys

        
        // Set success message for display after redirection
        $_SESSION['physicall_payment_prompt'] = "You have selected physical payment. Your status is now **PENDING PHYSICAL**. Please report to the Finance Office with your fee balance of **Sh" . htmlspecialchars($fee_balance) . "** for manual clearance.";

    } else {
        $_SESSION['error_message'] = "Error recording physical payment choice. Please try again.";
    }

    header("location: financedept.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Department Clearance</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <style>
        .green, .red { font-weight: bold; padding: 5px; border-radius: 5px; }
        .green { color: #1a7f37; background-color: #e6ffed; }
        .red { color: #a72323; background-color: #ffebe9; }
        .prompt-msg { 
            margin-top: 20px; 
            padding: 15px; 
            border: 1px solid #ddd; 
            border-radius: 8px;
            font-size: 1.05em;
            color: #333;
            background-color: #f9f9f9;
        }
        .info-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            font-size: 1.05em;
            color: #007bff;
        }
    </style>
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">finance</span>Finance</h2>
        <div class="links">
            <a href="#footer"><span class="material-symbols-outlined">contact_page</span>contact us</a>
            <a href="studentdash.php"><span class="material-symbols-outlined">arrow_back</span>back</a>
        </div>
    </nav>
    <div class="studentdashboard">
    <div class="studentprofile">
                <div class="img_holder">
                    <img src="profile pictures/admn4761.jpg" alt="profilepicture" class="profilepicture">
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
    </div>  
    </div>
    <div class="details">
        <div class="table">
            <h2><span class="material-symbols-outlined">id_card</span>Name: <?php echo htmlspecialchars($info['username'] ?? 'N/A') ?></h2>
            <h2><span class="material-symbols-outlined">confirmation_number</span>Admission: <?php echo htmlspecialchars($info['admission'] ?? 'N/A') ?> </h2>
            
            <?php if ($error_message): ?>
                <h2 class="red"><span class="material-symbols-outlined">error</span> Error: <?php echo htmlspecialchars($error_message) ?></h2>
            <?php endif; ?>

            <?php if (!empty($physicall_payment_prompt) || !empty($online_payment_prompt)): ?>
                <h2 class="prompt-msg">📝 **Action Taken:** <?php echo htmlspecialchars($physicall_payment_prompt . $online_payment_prompt) ?></h2>
            <?php endif; ?>
            
            <hr>

            <h2><span class="material-symbols-outlined">attach_money</span>Fee Balance: Ksh<?php echo htmlspecialchars($info['feebalance'] ?? '0.00') ?></h2>
            
            <hr>
            
            <?php $status = $info['feestatus'] ?? 'uncleared'; ?>

            <h2><span class="material-symbols-outlined">progress_activity</span>Clearance Status: 
                <?php 
                    if ($status == 'cleared') {
                        echo '<span class="green">CLEARED</span>';
                    } elseif ($status == 'pending_physical') {
                        echo '<span class="red">PENDING PHYSICAL RESOLUTION</span>';
                    } else {
                         echo '<span class="red">UNCLEARED</span>';
                    }
                ?>
            </h2>
            
            <hr>
            
            <?php if ($status == 'cleared'): ?>
                <h2 class="green"><span class="material-symbols-outlined">done_all</span> You are Cleared from the Finance Department.</h2>

            <?php elseif ($status == 'pending_physical'): ?>
                <h2 class="red"><span class="material-symbols-outlined">schedule</span> Status: PENDING PHYSICAL RESOLUTION. Please follow up at the Finance Office.</h2>

            <?php else: ?>
                <h3><a href="mblpmt.php?dept=<?php echo urlencode("finance")?>"><span class="material-symbols-outlined">payments</span>Pay Online (M-Pesa)</a></h3>
                
                <h3><a href="?choise=<?php echo urlencode("payPhysically")?>"><span class="material-symbols-outlined">footprint</span>Pay Physically (Mark Intention)</a></h3>
            <?php endif; ?>

            <a href="financedept.php?online_payment_success=true" class="info-link">
                (Developer Test: Simulate M-Pesa Payment Success)
            </a>
            
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check for clearance message content
            const phyPrompt = "<?php echo $physicall_payment_prompt; ?>";
            const onlinePrompt = "<?php echo $online_payment_prompt; ?>";
            
            if (phyPrompt.length > 0) {
                 alert("Action Success: " + phyPrompt.replace(/<\/?b>/g, '')); // Remove bold tags for alert
            }
            if (onlinePrompt.length > 0) {
                 alert("Clearance Success: " + onlinePrompt.replace(/<\/?b>/g, ''));
            }
        });
    </script>
</body>
</html>



<!-- outdated accessories logic -->
            <?php 
                $status = $info['accessoriesstatus'] ?? 'uncleared';
                // Check if cleared
                if ($status == 'cleared'): 
            ?>
                <h2 class="green"><span class="material-symbols-outlined">check_circle</span> Status: **CLEARED**</h2>

            <?php // Check if payment is pending physical resolution 
                 elseif ($status == 'pending_physical'): ?>

                <h2 class="green"><span class="material-symbols-outlined">schedule</span> Status: **PENDING PHYSICAL RESOLUTION**</h2>
                <?php // Show payment options if not cleared or pending physical
                  else: ?>
                <h3 id="debt"><a href="mblpmt.php?dept=<?php echo urlencode("accessories")?>"><span class="material-symbols-outlined">payments</span>Pay Online (M-Pesa)</a></h3>
                
                <h3 id="debt"><a href="?choise=<?php echo urlencode("payPhysically")?>"><span class="material-symbols-outlined">footprint</span>Pay Physically (Mark Intention)</a></h3>

                
                
            <?php endif; ?>


<!-- outdated boardingdept.php code -->
 <?php
error_reporting(0);
// Assuming these files securely handle connection ($connecting_to_the_database) 
// and session management, and define $info and $phy arrays.
include 'student_general_data_collection.php';
include 'phy.php';

// Initialize messages from session and clear them
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

// --- CONSOLIDATED MESSAGE HANDLING ---
// The prompt after selecting physical payment (moved from within the success block)
$physicall_payment_prompt = $_SESSION['physicall_payment_prompt'] ?? '';
unset($_SESSION['physicall_payment_prompt']);

// The prompt after a successful online payment (simulated callback)
$online_payment_prompt = $_SESSION['online_payment_prompt'] ?? '';
unset($_SESSION['online_payment_prompt']);
// -------------------------------------


// --- LOGIC FOR ONLINE PAYMENT SUCCESS (Simulated Callback Confirmation) ---
// This logic assumes the student is redirected here after a SUCCESSFUL M-Pesa Callback
if (isset($_GET['online_payment_success']) && $_GET['online_payment_success'] === "true" && isset($_SESSION['admission'])) {
    
    // ⚠️ CRITICAL: In a real system, this update should ONLY occur in the M-Pesa Callback handler (mpesa_callback.php)
    // We update here only for demonstration/simulation purposes of the clearance flow.
    
    $status_column = 'boardingstatus';
    
    // Update the clearance status column to 'cleared'
    $update_status = $connecting_to_the_database->prepare(
        "UPDATE studentgeneraldata SET {$status_column} = 'cleared' WHERE admission = ?"
    );
    $update_status->bind_param("s", $_SESSION['admission']);
    
    if ($update_status->execute()) {
        $_SESSION['online_payment_prompt'] = "✅ Online payment confirmed. Your **Boarding** clearance status has been successfully updated to 'cleared'.";
        // To reflect the change immediately, redirect without the GET parameter
        header("location: boardingdept.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Error updating clearance status after online payment simulation.";
        header("location: boardingdept.php");
        exit();
    }
}


// --- LOGIC FOR PHYSICAL PAYMENT SELECTION ---
if (isset($_GET['choise']) && $_GET['choise'] == "payPhysically") {
    
    // 1. UPDATE status in the main table to PENDING for physical clearance
    // This marks the student as having acknowledged the debt and committed to physical payment.
    $stmt = $connecting_to_the_database->prepare(
        "UPDATE studentgeneraldata SET boardingstatus = 'pending_physical' WHERE admission = ?"
    );
    $stmt->bind_param("s", $_SESSION['admission']);
    
    // Execute and check for success
    if ($stmt->execute()) {
        
        // 2. INSERT/UPDATE a record into the 'physicall' table 
        // This registers the request for the physical clearance staff.
        // NOTE: The original INSERT query syntax was incorrect (had a WHERE clause). 
        // A proper flow might use INSERT ON DUPLICATE KEY UPDATE, but for simplicity, 
        // we'll assume a standard INSERT if the record doesn't exist.
        
        // Use a more relevant column name for the value being resolved
        $boarding_data = $info["boardingitemsdamaged"] ?? "Missing/Damaged Items"; 
        $username_data = $info["username"] ?? $_SESSION['username'] ?? "Student";
        $admission_data = $info["admission"] ?? $_SESSION['admission'] ?? "N/A";
        $year_data = date("Y"); 
        
        $insert = $connecting_to_the_database->prepare(
            "INSERT INTO physicall (username, admission, year, dept_item, dept_status) 
             VALUES (?, ?, ?, ?, 'pending_physical_payment') 
             ON DUPLICATE KEY UPDATE dept_item = VALUES(dept_item), dept_status = VALUES(dept_status)"
        );
        
        // Assuming 'admission' is unique in 'physicall' table, or this needs to be scoped to the department.
        // I'm using 'dept_item' and 'dept_status' as generalized columns for the 'physicall' table.
        // The original logic seemed to insert 'boarding' data into the 'boarding' column of 'physicall' table.
        // I will use 'boarding' as the dept in the insert for consistency with the original code intent.
        
        $insert = $connecting_to_the_database->prepare(
             "INSERT INTO physicall (username, admission, year, boarding, boardingstatus) 
             VALUES (?, ?, ?, ?, 'pending_physical_payment')"
        );
        
        $insert->bind_param("ssss", $username_data, $admission_data, $year_data, $boarding_data);
        @$insert->execute(); // Execute with @ to suppress duplicate entry errors for this example
        
        // Set success message for display after redirection
        $_SESSION['physicall_payment_prompt'] = "You have selected physical payment. Your status is now **PENDING PHYSICAL**. Please report to the Boarding Department with the following items for clearance: **" . ($info['boardingitemsdamaged'] ?? 'Missing Item Details') . "**";

    } else {
        $_SESSION['error_message'] = "Error recording physical payment choice. Please try again.";
    }

    header("location: boardingdept.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boarding Department Clearance</title>
    <link rel="stylesheet" href="style.css">
      <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <style>
        .green, .red { font-weight: bold; padding: 5px; border-radius: 5px; }
        .green { color: #1a7f37; background-color: #e6ffed; }
        .red { color: #a72323; background-color: #ffebe9; }
        .prompt-msg { 
            margin-top: 20px; 
            padding: 15px; 
            border: 1px solid #ddd; 
            border-radius: 8px;
            font-size: 1.05em;
            color: #333;
            background-color: #f9f9f9;
        }
        .info-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            font-size: 1.05em;
            color: #007bff;
        }
    </style>
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">hotel</span>Boarding</h2>
        <div class="links">
            <a href="#footer"><span class="material-symbols-outlined">contact_page</span>contact us</a>
            <a href="studentdash.php"><span class="material-symbols-outlined">arrow_back</span>back</a>
        </div>
    </nav>
    <div class="studentdashboard">
    <div class="studentprofile">
                <div class="img_holder">
                    <img src="profile pictures/admn4761.jpg" alt="profilepicture" class="profilepicture">
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
    </div>  
    </div>
    <div class="details">
        <div class="table">
            <h2><span class="material-symbols-outlined">id_card</span>Name: <?php echo htmlspecialchars($info['username'] ?? 'N/A') ?></h2>
            <h2><span class="material-symbols-outlined">confirmation_number</span>Admission: <?php echo htmlspecialchars($info['admission'] ?? 'N/A') ?> </h2>
            
            <?php if ($error_message): ?>
                <h2 class="red"><span class="material-symbols-outlined">error</span> Error: <?php echo htmlspecialchars($error_message) ?></h2>
            <?php endif; ?>
            
            <?php if (!empty($physicall_payment_prompt) || !empty($online_payment_prompt)): ?>
                <h2 class="prompt-msg">📝 **Action Taken:** <?php echo htmlspecialchars($physicall_payment_prompt . $online_payment_prompt) ?></h2>
            <?php endif; ?>

            <h4>
                Damaged/Missing Items
                <ol>
                    <li><?php echo htmlspecialchars($info['boardingitemsdamaged'] ?? 'None found.') ?></li>
                </ol>
            </h4>
            
            <h4>
                Item's Value (Ksh)
                <ol>
                    <li><?php echo htmlspecialchars($info['boardingitemsvalue'] ?? '0.00') ?></li>
                </ol>
            </h4>
            
            <hr>
            
            <?php $status = $info['boardingstatus'] ?? 'uncleared'; ?>

            <h2><span class="material-symbols-outlined">progress_activity</span>Clearance Status: 
                <?php 
                    if ($status == 'cleared') {
                        echo '<span class="green">CLEARED</span>';
                    } elseif ($status == 'pending_physical') {
                        echo '<span class="red">PENDING PHYSICAL RESOLUTION</span>';
                    } else {
                         echo '<span class="red">UNCLEARED</span>';
                    }
                ?>
            </h2>
            
            <hr>
            
            <?php if ($status == 'cleared'): ?>
                <h2 class="green"><span class="material-symbols-outlined">done_all</span> You are Cleared from the Boarding Department.</h2>

            <?php elseif ($status == 'pending_physical'): ?>
                <h2 class="red"><span class="material-symbols-outlined">schedule</span> Status: PENDING PHYSICAL RESOLUTION. Please follow up at the department.</h2>

            <?php else: ?>
                <h3><a href="mblpmt.php?dept=<?php echo urlencode("boarding")?>"><span class="material-symbols-outlined">payments</span>Pay Online (M-Pesa)</a></h3>
                
                <h3><a href="?choise=<?php echo urlencode("payPhysically")?>"><span class="material-symbols-outlined">footprint</span>Pay Physically (Mark Intention)</a></h3>
            <?php endif; ?>

            <a href="mblpmt.php?dept=<?php echo urlencode("boarding")?>&online_payment_success=true" class="info-link">
                (Developer Test: Simulate M-Pesa Payment Success)
            </a>
            
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check for clearance message content
            const phyPrompt = "<?php echo $physicall_payment_prompt; ?>";
            const onlinePrompt = "<?php echo $online_payment_prompt; ?>";
            
            if (phyPrompt.length > 0) {
                 alert("Action Success: " + phyPrompt.replace(/<\/?b>/g, '')); // Remove bold tags for alert
            }
            if (onlinePrompt.length > 0) {
                 alert("Clearance Success: " + onlinePrompt.replace(/<\/?b>/g, ''));
            }
        });
    </script>
</body>
</html>


<!-- mblpmt.php form action data -->
 mblpmt.php?dept=<?php echo htmlspecialchars($requested_dept) ?>


 <!-- outdated view students code -->
  <?php

include 'database_connect.php';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>view student</title>
    <link rel="stylesheet" href="style.css">
     <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">dashboard</span>view  all student details</h2>
        <div class="links">
             <a href="#footer"><span class="material-symbols-outlined">contact_page</span>contact us</a>
             <a href="admindash.php"><span class="material-symbols-outlined">arrow_back</span>back</a>
        </div>
    </nav>
    <div class="search">
        <form action="" method="get">
            <label for="search">
            search
            <input type="search" name="search" placeholder="Type student admission">
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
            <th>fee balance</th>
            <th>books lost</th>
            <th>boarding items damaged</th>
            <th>unpaid accessories</th>
            <th>games items lost</th>
            <th>lab fee</th>
            <th>clearance status</th>
            <th>student profile picture</th>
            <?php  ?>
            <tr>
                <td><?php ?></td>
                <td><?php ?></td>
                <td><?php ?></td>
                <td><?php ?></td>
                <td><?php ?></td>
                <td><?php ?></td>
                <td><?php ?></td>
                <td><?php ?></td>
                <td><?php ?></td>
                <td><?php ?></td>
                <td><img src="profile pictures/<?php ?>" alt="" class="viewprofilepicture"></td>
            </tr>
            <?php ?>
        </table>
    </div>

     <div class="details">
        <h2>all students general data</h2>
        <table>
            <th>username</th>
            <th>admission</th>
            <th>year</th>
            <th>fee balance</th>
            <th>books lost</th>
            <th>boarding items damaged</th>
            <th>unpaid accessories</th>
            <th>games items lost</th>
            <th>lab fee </th>
            <th>clearance status </th>
            <th>update</th>
            <th>student profile picture </th>


            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td><img src="" alt="" class="viewprofilepicture"></td>
            </tr>

            
        </table>

        
    </div>
</body>
</html>
