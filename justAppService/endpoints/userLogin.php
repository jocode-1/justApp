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
    $user_password = trim(mysqli_real_escape_string($conn, !empty($data['user_password']) ? $data['user_password'] : ""));

    $login_result =  $portal->login_users($conn, $user_email, $user_password);

    $response = json_decode($login_result, true);

    if ($response['status'] === false) {
        http_response_code(401); // Unauthorized

        if ($response['message'] === 'missingCredentials') {
            // Incorrect email or password
            $response = array('status' => 'error', 'message' => 'Incorrect email or password');
        } elseif ($response['message'] === 'wrongPassword') {
            // Incorrect password
            $response = array('status' => 'error', 'message' => 'Incorrect email or password');
        } elseif ($response['message'] === 'wrongEmail') {
            // User does not exist
            $response = array('status' => 'error', 'message' => 'Incorrect email or password');
        }

        echo json_encode($response);
    } else {
        http_response_code(200);
        echo $login_result;
    }
} else {
    // Invalid request method
    http_response_code(405); // Method Not Allowed
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
