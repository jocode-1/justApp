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
    $user_id = trim(mysqli_real_escape_string($conn, !empty($data['user_id']) ? $data['user_id'] : ""));
    $user_email = trim(mysqli_real_escape_string($conn, !empty($data['user_email']) ? $data['user_email'] : ""));
    // $reference = trim(mysqli_real_escape_string($conn, !empty($data['reference_id']) ? $data['reference_id'] : ""));

    echo $portal->initiatePayment($conn, $user_id, $user_email);
    
} else {
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
?>
