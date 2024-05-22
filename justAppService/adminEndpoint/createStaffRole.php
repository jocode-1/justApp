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
    $staff_role_name =  trim(mysqli_real_escape_string($conn, !empty($data['staff_role_name']) ? $data['staff_role_name'] : ""));
    $staff_role_description =  trim(mysqli_real_escape_string($conn, !empty($data['staff_role_description']) ? $data['staff_role_description'] : ""));
    // $category_id =  trim(mysqli_real_escape_string($conn, !empty($data['category_id']) ? $data['category_id'] : ""));

    echo $portal->createRoles($conn, $token, $staff_role_name, $staff_role_description);

} else {
    
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
