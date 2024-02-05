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
        $image_url = trim(mysqli_real_escape_string($conn, !empty($data['profile_image']) ? $data['profile_image'] : ""));

        $filepath = '';
        $file = '';
        $filestring = substr(str_shuffle(str_repeat("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ", 20)), 0, 20);

        $filepath = "documents/profileImages/$filestring.png";

        // $file = 'http://localhost/adminEnerJust/adminAppService/endpoints/documents/product/' . $filestring . '.png';
        $file = 'https://api.donchimerk.org/justApp/justAppService/endpoints/document/profileImages/' . $filestring . '.png';
        file_put_contents($filepath, base64_decode($image_url));

        $response = $portal->upload_profile_picture($conn, $token, $user_id, $image_url);

        if ($response) {
            http_response_code(200);
            echo $response;
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(array('status' => 'error', 'message' => 'Failed to fetch product'));
        }
    } else {
        http_response_code(401); // Unauthorized
        echo json_encode(array('status' => 'error', 'message' => 'Expired or invalid token'));
    }

} else {
    $response = array('status' => false, 'message' => 'Invalid request method');
    echo json_encode($response);
}
