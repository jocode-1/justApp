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

        $user_id =  trim(mysqli_real_escape_string($conn, !empty($data['user_id']) ? $data['user_id'] : ""));

        $response = $portal->getUserBillingAddress($conn, $token, $user_id);

        // Check if the response indicates success
        $responseData = json_decode($response, true);
        if ($responseData['status'] === true) {
            http_response_code(200);
            echo $response;
        } elseif ($responseData['status'] === false && $responseData['message'] === 'User has no billing address') {
            // User has no billing address
            http_response_code(404); // Not Found
            echo $response;
        } else {
            // Failed to fetch billing address
            http_response_code(500); // Internal Server Error
            echo json_encode(array('status' => 'error', 'message' => 'Failed to fetch billing address'));
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
