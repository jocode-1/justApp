<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();
$token = $portal->getBearerToken();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = trim(mysqli_real_escape_string($conn, !empty($data['user_id']) ? $data['user_id'] : ""));
    $cart_id = trim(mysqli_real_escape_string($conn, !empty($data['cart_id']) ? $data['cart_id'] : ""));
    $product_id = trim(mysqli_real_escape_string($conn, !empty($data['product_id']) ? $data['product_id'] : ""));
    $shipping_address = trim(mysqli_real_escape_string($conn, !empty($data['shipping_address']) ? $data['shipping_address'] : ""));
    $payment_method = trim(mysqli_real_escape_string($conn, !empty($data['payment_method']) ? $data['payment_method'] : ""));
    $shipping_method = trim(mysqli_real_escape_string($conn, !empty($data['shipping_method']) ? $data['shipping_method'] : ""));

    echo $portal->orderConfirmation($conn, $token, $user_id, $cart_id, $product_id, $shipping_address, $payment_method, $shipping_method);
} else {
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
?>
