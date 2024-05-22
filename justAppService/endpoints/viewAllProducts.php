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

    // Validate token
    $tokenValidationResult = $portal->validateToken($token);

    if ($tokenValidationResult === "true") {
        // Token is valid, proceed with fetching product data
        $result = $portal->viewAllProduct($conn, $token);

        if ($result) {
            http_response_code(200); // OK
            echo $result;
            
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(array('status' => 'error', 'message' => 'Internal Server Error'));
        }
    } else {
        // Token is expired or invalid
         http_response_code(401); // Unauthorized
        $response = array('status' => 'error', 'message' => 'Expired or invalid token');
        echo json_encode($response);
       
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
?>
