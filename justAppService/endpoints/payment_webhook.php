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
            $reference = $data['data']['reference'];
            $amountInKobo = $data['data']['amount'];
            $user_id = $data['data']['customer']['id'];
            $user_email = $data['data']['customer']['email'];
            $status = $data['data']['status'];

            $logData = "Transaction ID: $transactionId, Reference ID: $reference, Amount: $amountInKobo, Customer Email: $user_email";
            file_put_contents('paystack_webhook.log', $logData . PHP_EOL, FILE_APPEND);

            // Convert amount to Naira
            $amountInNaira = $amountInKobo / 100;

            // Perform order status update based on your logic
            $orderUpdateResult = $portal->updateOrderStatus($conn, $reference, $amountInNaira, $user_id, $user_email, $status);

            if ($orderUpdateResult) {
                $response = array('status' => 'success', 'message' => 'Order status updated successfully');
                http_response_code(200); // OK
            } else {
                $response = array('status' => 'error', 'message' => 'Failed to update order status');
                http_response_code(500); // Internal Server Error
            }
            break;

        case 'charge.success': // Assuming 'charge.success' is also used for wallet transactions
            $transactionId = $data['data']['id'];
            $reference = $data['data']['reference'];
            $amountInKobo = $data['data']['amount'];
            $user_id = $data['data']['customer']['id'];
            $user_email = $data['data']['customer']['email'];
            $status = $data['data']['status'];

            $logData = "Wallet Transaction ID: $transactionId, Reference ID: $reference, Amount: $amountInKobo, Customer Email: $user_email";
            file_put_contents('wallet_webhook.log', $logData . PHP_EOL, FILE_APPEND);

            // Convert amount to Naira
            $amountInNaira = $amountInKobo / 100;

            // Perform wallet update based on your logic
            $walletUpdateResult = $portal->updateWallet($conn, $user_id, $amountInNaira, $status);

            if ($walletUpdateResult) {
                $response = array('status' => 'success', 'message' => 'Wallet updated successfully');
                http_response_code(200); // OK
            } else {
                $response = array('status' => 'error', 'message' => 'Failed to update wallet');
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
