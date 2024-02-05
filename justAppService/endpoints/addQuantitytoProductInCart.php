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

    $tokenValidationResult = $portal->validateToken($token);

    if ($tokenValidationResult === "true") {
        $userId = trim(mysqli_real_escape_string($conn, !empty($data['user_id']) ? $data['user_id'] : ""));
        $product_id = trim(mysqli_real_escape_string($conn, !empty($data['product_id']) ? $data['product_id'] : ""));
        $quantityToAdd = trim(mysqli_real_escape_string($conn, !empty($data['quantity_to_add']) ? $data['quantity_to_add'] : ""));

        $response = $portal->addQuantityToProduct($conn, $token, $userId, $product_id, $quantityToAdd);

        if ($response) {
            http_response_code(200); // OK
            echo $response;
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(array('status' => false, 'message' => 'Failed to add Quantity'));
        }

    } else {
        // Token is expired or invalid
        http_response_code(401); // Unauthorized
        echo json_encode(array('status' => false, 'message' => 'Expired or invalid token'));
    }
} else {
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
