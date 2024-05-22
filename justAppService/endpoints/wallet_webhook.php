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
    
    $logData = json_encode($data); // Convert array to JSON for logging
    file_put_contents('wallet_webhook.log', $logData . PHP_EOL, FILE_APPEND);


} else {
    $response = array('status' => 'error', 'message' => 'Invalid Paystack webhook event');
    http_response_code(400); // Bad Request
    echo json_encode($response);
}
