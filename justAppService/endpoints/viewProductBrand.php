<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

$token = $portal->getBearerToken();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    
    echo $portal->viewProductBrand($conn, $token);

} else {
    
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
