<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

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
        $reference = $data['data']['reference'];
        $amountInKobo = $data['data']['amount'];
        $user_id = $data['data']['customer']['id'];
        $user_email = $data['data']['customer']['email'];
        $status = $data['data']['status'];

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
?>
