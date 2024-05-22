<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: https://admin.enerjust.org.ng");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

$token = $portal->getBearerToken();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    //echo $token;
    $category_name =  trim(mysqli_real_escape_string($conn, !empty($data['category_name']) ? $data['category_name'] : ""));
    $category_description =  trim(mysqli_real_escape_string($conn, !empty($data['category_description']) ? $data['category_description'] : ""));

    echo $portal->createProductCategory($conn, $token, $category_name, $category_description);
} else {

    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
