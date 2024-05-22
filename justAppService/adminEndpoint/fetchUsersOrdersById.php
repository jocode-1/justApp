<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: https://admin.enerjust.org.ng");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

// $token = $portal->getBearerToken();

$user_id =  trim(mysqli_real_escape_string($conn, !empty($data['user_id']) ? $data['user_id'] : ""));

echo $portal->fetchUsersOrdersById($conn, $user_id);
