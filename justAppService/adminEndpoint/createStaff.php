<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: https://admin.enerjust.org.ng");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $staff_role_id = trim(mysqli_real_escape_string($conn, !empty($data['staff_role_id']) ? $data['staff_role_id'] : ""));
    $staff_fullname = trim(mysqli_real_escape_string($conn, !empty($data['staff_fullname']) ? $data['staff_fullname'] : ""));
    $staff_email = trim(mysqli_real_escape_string($conn, !empty($data['staff_email']) ? $data['staff_email'] : ""));
    $staff_phone_number = trim(mysqli_real_escape_string($conn, !empty($data['staff_phone_number']) ? $data['staff_phone_number'] : ""));
    $staff_address = trim(mysqli_real_escape_string($conn, !empty($data['staff_address']) ? $data['staff_address'] : ""));
    $staff_dob = trim(mysqli_real_escape_string($conn, !empty($data['staff_dob']) ? $data['staff_dob'] : ""));
    $staff_role = trim(mysqli_real_escape_string($conn, !empty($data['staff_role']) ? $data['staff_role'] : ""));

    $user_exists = $portal->checkStaffExists($conn, $staff_email);
    if ($user_exists) {
        $response = array("responseCode" => "100", 'status' => 'error', 'message' => 'User email already exists');
        echo json_encode($response);
    } else {
        $user = $portal->createStaff($conn, $staff_fullname, $staff_email, $staff_phone_number, $staff_address, $staff_dob, $staff_role);
        echo $user;
    }
} else {
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
