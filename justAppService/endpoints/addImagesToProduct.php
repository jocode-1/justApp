<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

$token = $portal->getBearerToken();

// $agent_id = trim(mysqli_real_escape_string($conn, !empty($data['agent_id']) ? $data['agent_id'] : ""));
$product_id =  trim(mysqli_real_escape_string($conn, !empty($data['product_id']) ? $data['product_id'] : ""));
$image_url =  trim(mysqli_real_escape_string($conn, !empty($data['image_url']) ? $data['image_url'] : ""));
$filepath = '';
$file = '';
$filestring = substr(str_shuffle(str_repeat("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ", 20)), 0, 20);

$filepath = "documents/product/$filestring.png";

$file = 'http://localhost/justApp/justAppService/documents/product/' . $filestring . '.png';
file_put_contents($filepath, base64_decode($image_url));

echo $portal->addImageTOProduct($conn, $product_id, $image_url, $token);
