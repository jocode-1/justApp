<?php
include_once('../inc/portal.php');

// $data = json_decode(@file_get_contents("php://input"), true);

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
    
    $input = @file_get_contents("php://input");
    $event = json_decode($input);
    http_response_code(200); // PHP 5.4 or greater

    echo $event;

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
