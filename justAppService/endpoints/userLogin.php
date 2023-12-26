<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $user_email = trim(mysqli_real_escape_string($conn, !empty($data['user_email']) ? $data['user_email'] : ""));
    // $username = trim(mysqli_real_escape_string($conn, !empty($data['username']) ? $data['username'] : ""));
    $user_password = trim(mysqli_real_escape_string($conn, !empty($data['user_password']) ? $data['user_password'] : ""));
    
    $login_result =  $portal->login_users($conn, $user_email, $user_password);
   
    if ($login_result === false) {
        $response = array('status' => 'error', 'message' => 'Incorrect password');
        http_response_code(401); // Unauthorized
        echo json_encode($response);
    } else {
        echo $login_result;
        http_response_code(201);
    }
} else {
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    http_response_code(405); // Method Not Allowed
    echo json_encode($response);
}
