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
    $staff_id =  trim(mysqli_real_escape_string($conn, !empty($data['staff_id']) ? $data['staff_id'] : ""));
    $brand_id =  trim(mysqli_real_escape_string($conn, !empty($data['brand_id']) ? $data['brand_id'] : ""));
    $category_id =  trim(mysqli_real_escape_string($conn, !empty($data['category_id']) ? $data['category_id'] : ""));
    $product_name =  trim(mysqli_real_escape_string($conn, !empty($data['product_name']) ? $data['product_name'] : ""));
    $product_description =  trim(mysqli_real_escape_string($conn, !empty($data['product_description']) ? $data['product_description'] : ""));
    $product_price =  trim(mysqli_real_escape_string($conn, !empty($data['product_price']) ? $data['product_price'] : ""));
    $product_stock_quantity =  trim(mysqli_real_escape_string($conn, !empty($data['product_stock_quantity']) ? $data['product_stock_quantity'] : ""));
    $product_weight =  trim(mysqli_real_escape_string($conn, !empty($data['product_weight']) ? $data['product_weight'] : ""));
    $product_category =  trim(mysqli_real_escape_string($conn, !empty($data['product_category']) ? $data['product_category'] : ""));
    $product_brand =  trim(mysqli_real_escape_string($conn, !empty($data['product_brand']) ? $data['product_brand'] : ""));
    $product_discount_percentage =  trim(mysqli_real_escape_string($conn, !empty($data['product_discount_percentage']) ? $data['product_discount_percentage'] : ""));
    $product_tax_percentage =  trim(mysqli_real_escape_string($conn, !empty($data['product_tax_percentage']) ? $data['product_tax_percentage'] : ""));
    $product_barcode =  trim(mysqli_real_escape_string($conn, !empty($data['product_barcode']) ? $data['product_barcode'] : ""));
    $product_tags =  trim(mysqli_real_escape_string($conn, !empty($data['product_tags']) ? $data['product_tags'] : ""));
    $product_warranty_information =  trim(mysqli_real_escape_string($conn, !empty($data['product_warranty_information']) ? $data['product_warranty_information'] : ""));
    $product_warranty_type =  trim(mysqli_real_escape_string($conn, !empty($data['product_warranty_type']) ? $data['product_warranty_type'] : ""));
    $product_warranty_duration =  trim(mysqli_real_escape_string($conn, !empty($data['product_warranty_duration']) ? $data['product_warranty_duration'] : ""));
    $product_warranty_details =  trim(mysqli_real_escape_string($conn, !empty($data['product_warranty_details']) ? $data['product_warranty_details'] : ""));
    $product_rating_count =  trim(mysqli_real_escape_string($conn, !empty($data['product_rating_count']) ? $data['product_rating_count'] : ""));
    $product_status =  trim(mysqli_real_escape_string($conn, !empty($data['product_status']) ? $data['product_status'] : ""));

    echo $portal->createProduct(
        $conn,
        $token,
        $staff_id,
        $brand_id,
        $category_id,
        $product_name,
        $product_description,
        $product_price,
        $product_stock_quantity,
        $product_weight,
        $product_category,
        $product_brand,
        $product_discount_percentage,
        $product_tax_percentage,
        $product_barcode,
        $product_tags,
        $product_warranty_information,
        $product_warranty_type,
        $product_warranty_duration,
        $product_warranty_details,
        $product_rating_count,
        $product_status
    );

} else {
    
    $response = array('status' => 'error', 'message' => 'Invalid request method');
    echo json_encode($response);
}
