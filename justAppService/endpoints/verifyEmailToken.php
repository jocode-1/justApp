<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    //  if (empty($token)) {
    //     http_response_code(401); // Unauthorized
    //     echo json_encode(array('status' => 'error', 'message' => 'Invalid or missing token'));
    //     exit;
    // }
    
    // $tokenValidationResult = $portal->validateToken($token);

    // if ($tokenValidationResult === "true") {

    $user_email = trim(mysqli_real_escape_string($conn, !empty($data['user_email']) ? $data['user_email'] : ""));
    $token = trim(mysqli_real_escape_string($conn, !empty($data['token']) ? $data['token'] : ""));

    $verificationResult = $portal->verify_token($conn, $user_email, $token);

    if ($verificationResult) {
        http_response_code(200); // OK
        echo $verificationResult;
    } else {
        http_response_code(400); // Bad Request
        echo $verificationResult;
    }
    // } else {
    //      // Token is expired or invalid
    //     http_response_code(401); // Unauthorized
    //     echo json_encode(array('status' => 'error', 'message' => 'Expired or invalid token'));
    // }
} else {
    http_response_code(405); // Method Not Allowed
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
