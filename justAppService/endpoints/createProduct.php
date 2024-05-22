<?php
include_once('../inc/portal.php');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");

$data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

$token = $portal->getBearerToken();

   
    $staff_id =  trim(mysqli_real_escape_string($conn, !empty($data['staff_id']) ? $data['staff_id'] : ""));
    $brand_id =  trim(mysqli_real_escape_string($conn, !empty($data['brand_id']) ? $data['brand_id'] : ""));
    $category_id =  trim(mysqli_real_escape_string($conn, !empty($data['category_id']) ? $data['category_id'] : ""));
    $product_name =  trim(mysqli_real_escape_string($conn, !empty($data['product_name']) ? $data['product_name'] : ""));
    $product_description =  trim(mysqli_real_escape_string($conn, !empty($data['product_description']) ? $data['product_description'] : ""));
    $product_price =  trim(mysqli_real_escape_string($conn, !empty($data['product_price']) ? $data['product_price'] : ""));
    $product_stock_quantity =  trim(mysqli_real_escape_string($conn, !empty($data['product_stock_quantity']) ? $data['product_stock_quantity'] : ""));
    $product_weight =  trim(mysqli_real_escape_string($conn, !empty($data['product_weight']) ? $data['product_weight'] : ""));
    $product_model_number =  trim(mysqli_real_escape_string($conn, !empty($data['product_model_number']) ? $data['product_model_number'] : ""));
    $product_color =  trim(mysqli_real_escape_string($conn, !empty($data['product_color']) ? $data['product_color'] : ""));
    $product_power_output =  trim(mysqli_real_escape_string($conn, !empty($data['product_power_output']) ? $data['product_power_output'] : ""));
    $product_voltage =  trim(mysqli_real_escape_string($conn, !empty($data['product_voltage']) ? $data['product_voltage'] : ""));
    $product_inverter_type =  trim(mysqli_real_escape_string($conn, !empty($data['product_inverter_type']) ? $data['product_inverter_type'] : ""));
    $product_discount_percentage =  trim(mysqli_real_escape_string($conn, !empty($data['product_discount_percentage']) ? $data['product_discount_percentage'] : ""));
    $product_barcode =  trim(mysqli_real_escape_string($conn, !empty($data['product_barcode']) ? $data['product_barcode'] : ""));
    $product_warranty_period =  trim(mysqli_real_escape_string($conn, !empty($data['product_warranty_period']) ? $data['product_warranty_period'] : ""));
    $product_status =  trim(mysqli_real_escape_string($conn, !empty($data['product_status']) ? $data['product_status'] : ""));

    echo $portal->createProduct($conn, $token, $staff_id, $brand_id, $category_id, $product_name, $product_description, $product_price, $product_stock_quantity, $product_weight, $product_model_number, $product_color, $product_power_output,
        $product_voltage, $product_inverter_type, $product_discount_percentage, $product_barcode, $product_warranty_period, $product_status );
