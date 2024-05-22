<?php

include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: https://admin.enerjust.org.ng");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
// header("Access-Control-Allow-Request-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

$token = $portal->getBearerToken();

    //echo $token;
    // $staff_id =  trim(mysqli_real_escape_string($conn, !empty($data['staff_id']) ? $data['staff_id'] : ""));
    // $brand_id =  trim(mysqli_real_escape_string($conn, !empty($data['brand_id']) ? $data['brand_id'] : ""));
    // $category_id =  trim(mysqli_real_escape_string($conn, !empty($data['category_id']) ? $data['category_id'] : ""));
    $product_name =  trim(mysqli_real_escape_string($conn, !empty($data['product_name']) ? $data['product_name'] : ""));
    $product_description =  trim(mysqli_real_escape_string($conn, !empty($data['product_description']) ? $data['product_description'] : ""));
    $product_price =  trim(mysqli_real_escape_string($conn, !empty($data['product_price']) ? $data['product_price'] : ""));
    $product_stock_quantity =  trim(mysqli_real_escape_string($conn, !empty($data['product_stock_quantity']) ? $data['product_stock_quantity'] : ""));
    $product_weight =  trim(mysqli_real_escape_string($conn, !empty($data['product_weight']) ? $data['product_weight'] : ""));
    $product_category =  trim(mysqli_real_escape_string($conn, !empty($data['category_name']) ? $data['category_name'] : ""));
    $product_brand =  trim(mysqli_real_escape_string($conn, !empty($data['brand_name']) ? $data['brand_name'] : ""));
    $product_model_number =  trim(mysqli_real_escape_string($conn, !empty($data['product_model_number']) ? $data['product_model_number'] : ""));
    $product_color =  trim(mysqli_real_escape_string($conn, !empty($data['product_color']) ? $data['product_color'] : ""));
    $product_power_output =  trim(mysqli_real_escape_string($conn, !empty($data['product_power_output']) ? $data['product_power_output'] : ""));
    $product_power_input =  trim(mysqli_real_escape_string($conn, !empty($data['product_power_input']) ? $data['product_power_input'] : ""));
    $product_voltage =  trim(mysqli_real_escape_string($conn, !empty($data['product_voltage']) ? $data['product_voltage'] : ""));
    $product_barcode =  trim(mysqli_real_escape_string($conn, !empty($data['product_barcode']) ? $data['product_barcode'] : ""));
    $product_status =  trim(mysqli_real_escape_string($conn, !empty($data['product_status']) ? $data['product_status'] : ""));
    $image_url =  trim(mysqli_real_escape_string($conn, !empty($data['product_image']) ? $data['product_image'] : ""));
    
    $filepath = '';
    $file = '';
    $filestring = substr(str_shuffle(str_repeat("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ", 20)), 0, 20);

    $filepath = "documents/product/$filestring.png";

    // $file = 'http://localhost/adminEnerJust/adminAppService/endpoints/documents/product/' . $filestring . '.png';
    $file = 'https://api.enerjust.org.ng/justAppService/adminEndpoint/documents/product/' . $filestring . '.png';
    file_put_contents($filepath, base64_decode($image_url));

    $result = $portal->createProduct($conn, $token, $product_name, $product_description, $product_price, $product_stock_quantity, $product_weight, $product_category, $product_brand, 
    $product_model_number, $product_color, $product_power_output, $product_power_input, $file, $product_voltage, $product_barcode, $product_status);

if ($result) {
  http_response_code(201); // Set status code to 201 (Created) on success
  echo $result;
} else {
  http_response_code(500); // Set status code to 500 (Internal Server Error) on failure
  echo json_encode(["message" => "Failed to create product."]);
}