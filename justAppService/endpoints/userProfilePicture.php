<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$portal = new PortalUtility();

$token = $portal->getBearerToken();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $tokenValidationResult = $portal->validateToken($token);

    if ($tokenValidationResult === "true") {
        $user_id = trim(mysqli_real_escape_string($conn, !empty($_POST['user_id']) ? $_POST['user_id'] : ""));
        
        // Check if the file was uploaded successfully
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            // Get file details
            $file_name = $_FILES['profile_image']['name'];
            $file_tmp = $_FILES['profile_image']['tmp_name'];

            // Generate a unique file string
            $filestring = substr(str_shuffle(str_repeat("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ", 20)), 0, 20);

            // Define file paths
            $filepath = "documents/profileImages/$filestring.png";
            $file_url = 'https://api.enerjust.org.ng/justAppService/endpoints/documents/profileImages/' . $filestring . '.png';

            // Move the uploaded file to the desired location
            move_uploaded_file($file_tmp, $filepath);

            // Call the method to upload profile picture
            $response = $portal->upload_profile_picture($conn, $token, $user_id, $file_url);

            if ($response) {
                http_response_code(200);
                echo $response;
            } else {
                http_response_code(500); // Internal Server Error
                echo json_encode(array('status' => 'error', 'message' => 'Failed to upload profile picture'));
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(array('status' => 'error', 'message' => 'Failed to receive image file'));
        }
    } else {
        http_response_code(401); // Unauthorized
        echo json_encode(array('status' => 'error', 'message' => 'Expired or invalid token'));
    }

} else {
    $response = array('status' => false, 'message' => 'Invalid request method');
    echo json_encode($response);
}
?>
