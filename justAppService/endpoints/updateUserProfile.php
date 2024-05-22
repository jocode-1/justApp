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
    if (empty($token)) {
        http_response_code(401); // Unauthorized
        echo json_encode(array('status' => 'error', 'message' => 'Invalid or missing token'));
        exit;
    }
    $tokenValidationResult = $portal->validateToken($token);

    if ($tokenValidationResult === "true") {

        $user_id = trim(mysqli_real_escape_string($conn, !empty($data['user_id']) ? $data['user_id'] : ""));
        $user_firstname = trim(mysqli_real_escape_string($conn, !empty($data['user_firstname']) ? $data['user_firstname'] : ""));
        $user_lastname = trim(mysqli_real_escape_string($conn, !empty($data['user_lastname']) ? $data['user_lastname'] : ""));
        $user_address = trim(mysqli_real_escape_string($conn, !empty($data['user_address']) ? $data['user_address'] : ""));
        $user_email = trim(mysqli_real_escape_string($conn, !empty($data['user_email']) ? $data['user_email'] : ""));
        $user_phone_number = trim(mysqli_real_escape_string($conn, !empty($data['user_phone_number']) ? $data['user_phone_number'] : ""));
        $user_gender = trim(mysqli_real_escape_string($conn, !empty($data['user_gender']) ? $data['user_gender'] : ""));
        $user_dob = trim(mysqli_real_escape_string($conn, !empty($data['user_dob']) ? $data['user_dob'] : ""));
        $user_state = trim(mysqli_real_escape_string($conn, !empty($data['user_state']) ? $data['user_state'] : ""));

// Validate if any required fields are empty
if (empty($user_id) || empty($user_firstname) || empty($user_lastname) || empty($user_address) || empty($user_email) || empty($user_phone_number) || empty($user_gender) || empty($user_dob) || empty($user_state)) {
    http_response_code(400); // Bad Request
    echo json_encode(array('status' => 'error', 'message' => 'One or more required fields are empty'));
    exit;
}

        $response = $portal->update_user_profile($conn, $token, $user_id, $user_firstname, $user_lastname, $user_address, $user_email, $user_phone_number, $user_gender, $user_dob, $user_state);

        if ($response) {
            http_response_code(200);
            echo $response;
        } else {
            http_response_code(500);
            echo json_encode(array('status' => 'error', 'message' => 'Failed to Update Profile'));
            // Internal Server Error
        }
    } else {
        // Token is expired or invalid
        http_response_code(401); // Unauthorized
        echo json_encode(array('status' => 'error', 'message' => 'Expired or invalid token'));
    }
} else {
    http_response_code(405);
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
