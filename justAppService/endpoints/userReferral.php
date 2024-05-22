<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
         $referralCode =  trim(mysqli_real_escape_string($conn, !empty($data['referralCode ']) ? $data['referralCode '] : ""));
    $newUserId =  trim(mysqli_real_escape_string($conn, !empty($data['newUserId']) ? $data['newUserId'] : ""));
        
      
            $response = $portal->handleReferral($conn, $referralCode, $newUserId);

            if ($response) {
                http_response_code(200);
                echo $response;
            } else {
                http_response_code(500); // Internal Server Error
                echo json_encode(array('status' => 'error', 'message' => 'Failed to upload profile picture'));
            }
       

} else {
    $response = array('status' => false, 'message' => 'Invalid request method');
    echo json_encode($response);
}

