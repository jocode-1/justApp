<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

$agent_id = trim(mysqli_real_escape_string($conn, !empty($data['agent_id']) ? $data['agent_id'] : ""));


$user = $portal->settleAgentAppzoneAccount($conn,$agent_id);

echo $user;
