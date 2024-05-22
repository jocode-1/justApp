<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: https://admin.enerjust.org.ng");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

    $staff_email = trim(mysqli_real_escape_string($conn, !empty($data['staff_email']) ? $data['staff_email'] : ""));
    // $username = trim(mysqli_real_escape_string($conn, !empty($data['username']) ? $data['username'] : ""));
    $staff_password = trim(mysqli_real_escape_string($conn, !empty($data['staff_password']) ? $data['staff_password'] : ""));
    
    echo $portal->login_staff($conn, $staff_email, $staff_password);
    