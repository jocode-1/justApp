<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

$token = $portal->getBearerToken();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    
    if (empty($token)) {
        http_response_code(401); // Unauthorized
        echo json_encode(array('status' => 'error', 'message' => 'Invalid or missing token'));
        exit;
    }
    
    $tokenValidationResult = $portal->validateToken($token);

    if ($tokenValidationResult === "true") {
    $product_id =  trim(mysqli_real_escape_string($conn, !empty($data['product_id']) ? $data['product_id'] : ""));

    $response = $portal->viewProductByProductID($conn, $product_id, $token);
    
    if ($response) {
         http_response_code(200);
         echo $response;
    } else {
            echo json_encode(array('status' => 'error', 'message' => 'Failed to fetch product'));
            http_response_code(500); // Internal Server Error
        }
    
    
    } else {
        // Token is expired or invalid
        http_response_code(401); // Unauthorized
        echo json_encode(array('status' => 'error', 'message' => 'Expired or invalid token'));
        
    }

} else {
    
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
