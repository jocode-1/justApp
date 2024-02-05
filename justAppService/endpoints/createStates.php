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
    //echo $token;
    $brand_name =  trim(mysqli_real_escape_string($conn, !empty($data['brand_name']) ? $data['brand_name'] : ""));
    $brand_description =  trim(mysqli_real_escape_string($conn, !empty($data['brand_description']) ? $data['brand_description'] : ""));

    echo $portal->createProductBrand($conn, $token, $brand_name, $brand_description);
} else {

    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
