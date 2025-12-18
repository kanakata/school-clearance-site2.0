<?php
// mpesa_callback.php

// 1. Get the raw M-Pesa data
$callback_data = file_get_contents('php://input');
$data = json_decode($callback_data, true);

// Log the callback data for debugging (Crucial for live systems)
error_log("M-Pesa Callback Received: " . print_r($data, true));

// Check if the transaction was successful
$result_code = $data['Body']['stkCallback']['ResultCode'] ?? null;
$checkoutRequestId = $data['Body']['stkCallback']['CheckoutRequestID'] ?? null;

if ($result_code === 0 && $checkoutRequestId) {
    
    // Successful payment confirmed by M-Pesa!
    
    $admission = $data['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'] ?? null; // Assuming Admission is the AccountReference
    $amount = $data['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'] ?? 0.00;
    $mpesa_receipt_number = $data['Body']['stkCallback']['CallbackMetadata']['Item'][2]['Value'] ?? 'N/A';
    $phone_number = $data['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'] ?? null;
    $transaction_date = date('Y-m-d H:i:s');
    
    // Connect to the database
    // Assume $connecting_to_the_database is available here, e.g., include 'db_connection.php';
    
    if ($connecting_to_the_database) {
        // A. Update the 'payments' table status to SUCCESS and record MPESA details
        $update_payment = $connecting_to_the_database->prepare(
            "UPDATE payments SET transaction_status = 'SUCCESS', mpesa_receipt_number = ?, transaction_date = ? 
             WHERE checkout_request_id = ? AND transaction_status = 'PENDING'"
        );
        $update_payment->bind_param("sss", $mpesa_receipt_number, $transaction_date, $checkoutRequestId);
        $update_payment->execute();
        
        if ($update_payment->affected_rows > 0) {
            
            // B. Fetch the department status column based on the successful transaction
            $fetch_dept = $connecting_to_the_database->prepare(
                "SELECT dept FROM payments WHERE checkout_request_id = ?"
            );
            $fetch_dept->bind_param("s", $checkoutRequestId);
            $fetch_dept->execute();
            $result = $fetch_dept->get_result();
            $payment_info = $result->fetch_assoc();
            $department = $payment_info['dept'] ?? null;

            // Map department name to the status column
            $dept_mapping = [
                'library' => 'librarystatus',
                'finance' => 'financemstatus',
                'boarding' => 'boardingstatus',
                'accessories' => 'accessoriesstatus',
                'games' => 'gamesstatus',
                'laboratory' => 'laboratorystatus',
            ];
            $status_column = $dept_mapping[$department] ?? null;

            // C. CRITICAL STEP: CLEAR THE STUDENT'S DEPARTMENT STATUS
            if ($status_column && $admission) {
                $update_status = $connecting_to_the_database->prepare(
                    "UPDATE studentgeneraldata SET {$status_column} = 'cleared' WHERE admission = ?"
                );
                $update_status->bind_param("s", $admission);
                $update_status->execute();
                
                // Set a successful message for the student to retrieve later if needed
                // Since this is a server-to-server call, we can't use sessions here.
                // We'd rely on a separate query from the student's dashboard to check the status.
                error_log("STUDENT CLEARED: Admission: {$admission}, Dept: {$department}");
            }
        }
    }
} else {
    // Payment failed or was cancelled by user
    if ($checkoutRequestId) {
        // Update the 'payments' table status to FAILED
        $update_payment = $connecting_to_the_database->prepare(
            "UPDATE payments SET transaction_status = 'FAILED' WHERE checkout_request_id = ?"
        );
        $update_payment->bind_param("s", $checkoutRequestId);
        $update_payment->execute();
    }
}

// M-Pesa expects a specific JSON response regardless of success or failure
echo '{"ResultCode":0,"ResultDesc":"Confirmation received successfully"}';

?>