<?php
include_once('../inc/portal.php');

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

// if ($_SERVER['REQUEST_METHOD'] == 'POST') {

// Handle Paystack webhook payload
if (!empty($data['event']) && $data['event'] === 'charge.success') {
    // Validate the Paystack signature
    $isValidSignature = $portal->validatePaystackSignature();
    if (!$isValidSignature) {
        $response = array('status' => 'error', 'message' => 'Invalid Paystack signature');
        http_response_code(403); // Forbidden
        echo json_encode($response);
        exit();
    }

    // Extract relevant information from the payload
    // $event = $data['data']['event'];
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

    echo json_encode($response);
} else {
    $response = array('status' => 'error', 'message' => 'Invalid Paystack webhook event');
    http_response_code(400); // Bad Request
    echo json_encode($response);
}
// } else {
//     $response = array('status' => 'error', 'message' => 'Invalid request method');
//     http_response_code(405); // Method Not Allowed
//     echo json_encode($response);
// }
