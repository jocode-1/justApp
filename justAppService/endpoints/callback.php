
<?php
include_once('../inc/portal.php');

$payment_details = json_decode(file_get_contents("php://input"), true);
//echo $payment_details;

$portal = new PortalUtility();

if (!empty($data['event'])) {
    $isValidSignature = $portal->validatePaystackSignature();
    if (!$isValidSignature) {
        $response = array('status' => 'error', 'message' => 'Invalid Paystack signature');
        http_response_code(403); // Forbidden
        echo json_encode($response);
        exit();
    }
// Get the payment status
$payment_status = $payment_details["data"]["status"];
$payment_reference = $payment_details["data"]["reference"];
$payment_amount = $payment_details["data"]["amount"];
    $customer_code = $data['data']['customer']['customer_code'];
$payment_currency = $payment_details["data"]["currency"];

if ($payment_status === "charge.success") {
    // Payment was successful, update the database
   $result = $this->updateOrderStatus($conn, $payment_reference, $payment_amount, $customer_code, $payment_status);
} else {
    // Payment failed, send an email or take appropriate action
//    send_failure_email($payment_reference);
}
echo $result;

}

?>
