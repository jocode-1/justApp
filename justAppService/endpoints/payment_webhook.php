<?php
include_once('../inc/portal.php');

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

// Handle Paystack webhook payload
if (!empty($data['event'])) {
    // Validate the Paystack signature
    $isValidSignature = $portal->validatePaystackSignature();
    if (!$isValidSignature) {
        $response = array('status' => 'error', 'message' => 'Invalid Paystack signature');
        http_response_code(403); // Forbidden
        echo json_encode($response);
        exit();
    }

    $logData = json_encode($data); // Convert array to JSON for logging
    file_put_contents('webhook.log', $logData . PHP_EOL, FILE_APPEND);

    // Extract relevant information from the payload
    switch ($data['event']) {
        case 'charge.success':
            $transactionId = $data['data']['id'];
            $refrence_id = $data['data']['reference'];
            $amountInKobo = $data['data']['amount'];
            // $user_id = $data['data']['customer']['id'];
            $customer_code = $data['data']['customer']['customer_code'];
            $get_user_email = $data['data']['customer']['email'];
            $status = $data['data']['status'];

           $user_email = $portal->fetchUserDetailsByEmail($conn, $token, $get_user_email);
           $user_id = $user_email['user_id'];
            // Log the event data
            $logData = "Transaction ID: $transactionId, Reference ID: $refrence_id, Amount: $amountInKobo, Customer Email: $user_email";
            file_put_contents('payment_webhook.log', $logData . PHP_EOL, FILE_APPEND);

            // Convert amount to Naira
            $amountInNaira = $amountInKobo / 100;

            // Perform actions based on status
            if ($status === 'success') {
                // Check if it's a wallet transaction
                if ($data['data']['channel'] === 'dedicated_nuban') {
                    // Perform wallet update based on your logic
                    $walletUpdateResult = $portal->updateWallet($conn, $customer_code, $amountInNaira, $status);

                    if ($walletUpdateResult) {
                        // Log the wallet transaction
                        $walletTransactionLogged = $portal->logTransaction($conn, $transactionId, $user_id, $refrence_id, "Wallet Credited", $amount, $payment_method, $status);
                        if ($walletTransactionLogged) {
                            http_response_code(200);
                            $response = array('status' => 'success', 'message' => 'Wallet updated successfully');
                            // OK
                        } else {
                            http_response_code(500);
                            $response = array('status' => 'error', 'message' => 'Failed to log wallet transaction');
                             // Internal Server Error
                        }
                    } else {
                        http_response_code(500);
                        $response = array('status' => 'error', 'message' => 'Failed to update wallet');
                         // Internal Server Error
                    }
                } else {
                    // Perform order status update based on your logic
                    $orderUpdateResult = $portal->updateOrderStatus($conn, $reference, $amountInNaira, $customer_code, $status);

                    if ($orderUpdateResult) {
                        $orderTransactionLogged = $portal->logTransaction($conn, $transactionId, $user_id, $refrence_id, "Purchased an Item", $amount, $payment_method, $status);
                        if ($orderTransactionLogged) {
                            $response = array('status' => 'success', 'message' => 'Order status updated successfully');
                            http_response_code(200); // OK
                        } else {
                            http_response_code(500);
                            $response = array('status' => 'error', 'message' => 'Failed to log order transaction');
                            // Internal Server Error
                        }
                    } else {
                        http_response_code(500);
                        $response = array('status' => 'error', 'message' => 'Failed to update order status');
                         // Internal Server Error
                    }
                }
            } else {
                $response = array('status' => 'error', 'message' => 'Payment processing failed');
                http_response_code(500); // Internal Server Error
            }
            break;

        default:
            // Handle unknown event
            $response = array('status' => 'error', 'message' => 'Unknown Paystack webhook event');
            http_response_code(400); // Bad Request
            echo json_encode($response);
            exit();
    }

    echo json_encode($response);
} else {
    $response = array('status' => 'error', 'message' => 'Invalid Paystack webhook event');
    http_response_code(400); // Bad Request
    echo json_encode($response);
}
?>
