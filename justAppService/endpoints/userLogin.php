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
    
    echo $portal->login_users($conn, $user_email, $user_password);
    
} else {
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}