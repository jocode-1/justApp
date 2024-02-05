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
        $user_id = trim(mysqli_real_escape_string($conn, !empty($data['user_id']) ? $data['user_id'] : ""));
        $state_id = trim(mysqli_real_escape_string($conn, !empty($data['state_id']) ? $data['state_id'] : ""));
        $address_type = trim(mysqli_real_escape_string($conn, !empty($data['address_type']) ? $data['address_type'] : ""));
        $address_name = trim(mysqli_real_escape_string($conn, !empty($data['address_name']) ? $data['address_name'] : ""));
        $address_street = trim(mysqli_real_escape_string($conn, !empty($data['address_street']) ? $data['address_street'] : ""));
        $address_city = trim(mysqli_real_escape_string($conn, !empty($data['address_city']) ? $data['address_city'] : ""));
        $address_state = trim(mysqli_real_escape_string($conn, !empty($data['address_state']) ? $data['address_state'] : ""));
        $address_zip_code = trim(mysqli_real_escape_string($conn, !empty($data['address_zip_code']) ? $data['address_zip_code'] : ""));
        $address_country = trim(mysqli_real_escape_string($conn, !empty($data['address_country']) ? $data['address_country'] : ""));


        if (empty($user_id) || empty($address_type) || empty($address_name) || empty($address_street) || empty($address_city) || empty($address_state) || empty($address_zip_code) || empty($address_country)) {
            $response = array('status' => false, 'message' => 'Incomplete data. Please provide all required fields.');
            echo json_encode($response);
            http_response_code(400); // Bad Request
            exit();
        }
        $response = $portal->addUserAddress($conn, $token, $user_id, $state_id, $address_type, $address_name, $address_street, $address_city, $address_state, $address_zip_code, $address_country);

        if ($response) {
            http_response_code(200); // OK
            echo $response;
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(array('status' => false, 'message' => 'Failed to process the request'));
        }
    } else {
// Token is expired or invalid
        http_response_code(401); // Unauthorized
        echo json_encode(array('status' => false, 'message' => 'Expired or invalid token'));
    }

} else {

    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
    http_response_code(405); // Method Not Allowed
}
