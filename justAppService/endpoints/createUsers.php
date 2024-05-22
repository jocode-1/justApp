<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = trim(mysqli_real_escape_string($conn, !empty($data['username']) ? $data['username'] : ""));
    $user_email = trim(mysqli_real_escape_string($conn, !empty($data['user_email']) ? $data['user_email'] : ""));
    $user_password = trim(mysqli_real_escape_string($conn, !empty($data['user_password']) ? $data['user_password'] : ""));
    $user_phone_number = trim(mysqli_real_escape_string($conn, !empty($data['user_phone_number']) ? $data['user_phone_number'] : ""));

    $user_exists = $portal->checkUserExists($conn, $user_email);
    if ($user_exists) {
        $response = array('status' => false, 'message' => 'User email already exists');
        http_response_code(400);
        echo json_encode($response);
    } else {
        $user = $portal->createUser($conn, $username, $user_email, $user_password, $user_phone_number);
        http_response_code(201);
        echo $user;
    }
} else {
    $response = array('status' => false, 'message' => 'Invalid request method');
    http_response_code(405);
    echo json_encode($response);
}
