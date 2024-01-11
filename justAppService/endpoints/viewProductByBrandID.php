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
    //echo $token;
    $brand_id =  trim(mysqli_real_escape_string($conn, !empty($data['brand_id']) ? $data['brand_id'] : ""));

    $result = $portal->viewProductByBrandID($conn, $category_id, $token);

    if ($result) {
        echo json_encode($result);
        http_response_code(200); // OK
    } else {
        echo json_encode($result);
        http_response_code(500); // Internal Server Error
    }
} else {
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
    http_response_code(405); // Method Not Allowed
}
