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
    'library'=> ['column' => 'bookmarketvalue', 'payment_desc' => 'book(s) lost', 'status_col' => 'librarystatus', 'dept_page' => 'librarydept.php'],
    'finance'=> ['column' => 'feebalance', 'payment_desc' => 'fee balance', 'status_col' => 'financemstatus', 'dept_page' => 'financedept.php'],
    'boarding' => ['column' => 'boardingitemsvalue', 'payment_desc' => 'damaged boarding items', 'status_col' => 'boardingstatus', 'dept_page' => 'boardingdept.php'],
    'accessories'=> ['column' => 'unpaidaccessoriesvalue', 'payment_desc' => 'unpaid accessories', 'status_col' => 'accessoriesstatus', 'dept_page' => 'accessoriesdept.php'],
    'games'=> ['column' => 'gamesitemvalue', 'payment_desc' => 'lost games item', 'status_col' => 'gamesstatus', 'dept_page' => 'gamesdept.php'],
    'laboratory' => ['column' => 'labfee', 'payment_desc' => 'lab fee', 'status_col' => 'laboratorystatus', 'dept_page' => 'laboratorydept.php'],
];

// Initialize variables
$requested_dept = $_GET['dept'] ?? 'general';
$dept_code = $dept_mapping[$requested_dept]['dept_page'] ?? 'generaldept.php'; // Used for redirect
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
if (isset($_GET["dept"]) && array_key_exists($requested_dept, $dept_mapping)) {
    $config = $dept_mapping[$requested_dept];
    
    // $dept_code already set above
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
    // IMPORTANT: Use the actual public HTTPS URL for your callback handler (mpesa_callback.php)
    $callbackUrl = "https://thirdly-ateliotic-katina.ngrok-free.dev/mpesa_callback.php"; 
    
    //2. Data Validation and Formatting
    $phone_number = preg_replace('/^0/', '254', $phone_number_raw);
    $payment_amount = max(1.00, (float)$payment_amount); 

    if (strlen($phone_number) !== 12 || $payment_amount <= 0) {
        $_SESSION['error_message'] = "Invalid phone number or amount. Please check your input.";
        header("location: {$dept_code}");
        exit();
    }
    
    //3. Custom Message Generation
    $transaction_dept = $_GET['dept'] ?? 'general';
    $custom_message = "Payment of Ksh {$payment_amount} for {$payment_desc} Admission: {$admission} to {$transaction_dept} dept.";
    
    //4. Generate M-Pesa Base64 Password and Timestamp
    $timestamp = date('YmdHis');
    $password = base64_encode($businessShortCode . $passkey . $timestamp);

    //5. Get API Access Token --- (Omitted for brevity, assuming successful token fetch)
    // ... token fetch logic here ...
    $access_token = 'your_fetched_access_token'; // Placeholder

    if (!$access_token || $access_token === 'your_fetched_access_token') {
        // Re-implement the real token fetch logic here or use a stored/cached one
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
             $_SESSION['error_message'] = "Could not get M-Pesa access token. Check API credentials.";
             header("location: {$dept_code}");
             exit();
        }
    }

    //6. Prepare STK Push Payload ---
    $stk_payload = [
        'BusinessShortCode' => $businessShortCode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline', 
        'Amount' => (int)$payment_amount, // Safaricom expects integer amount in the API call
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

    } else {
        // Log API failure details
        error_log("M-Pesa STK Push API Failure: " . $response);
        $message = $stk_response['CustomerMessage'] ?? "Unknown Error.";
        $_SESSION['error_message'] = "M-Pesa payment initiation failed. {$message}. Please check your phone number and try again.";
    }

    // Redirect back to the department page
    header("location: {$dept_code}");
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
        .info-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <nav>
        <h2><span class="material-symbols-outlined">money</span>Online Payment</h2>
        <div class="links">
            <a href="#footer"><span class="material-symbols-outlined">contact_page</span>Contact Us</a>
            <a href="<?php echo htmlspecialchars($dept_code) ?>"><span class="material-symbols-outlined">arrow_back</span>Back</a>
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
                <a href="<?php echo htmlspecialchars($dept_code) ?>?online_payment_success=true" class="info-link">
                    Click here to confirm clearance status (Simulated)
                </a>
            </div>
        <?php endif; ?>

        <form action="mblpmt.php?dept=<?php echo htmlspecialchars($requested_dept) ?>" method="POST">
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
                    <input type="number" name="phone" placeholder="M-Pesa Phone (e.g., 07XXXXXXXX)" required>
                </label> 
                
                <label for="money" class="input_label">
                    <span class="material-symbols-outlined">money</span>
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