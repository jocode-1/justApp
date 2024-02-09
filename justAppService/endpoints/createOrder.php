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
        if (empty($token)) {
            http_response_code(401); // Unauthorized
            echo json_encode(array('status' => 'error', 'message' => 'Invalid or missing token'));
            exit;
        }
        $tokenValidationResult = $portal->validateToken($token);

        if ($tokenValidationResult === "true") {

            $user_id = trim(mysqli_real_escape_string($conn, !empty($data['user_id']) ? $data['user_id'] : ""));
            $cart_id = trim(mysqli_real_escape_string($conn, !empty($data['cart_id']) ? $data['cart_id'] : ""));
            $pickup_station = trim(mysqli_real_escape_string($conn, !empty($data['pickup_station']) ? $data['pickup_station'] : ""));
            $pickup_fee = trim(mysqli_real_escape_string($conn, !empty($data['pickup_fee']) ? $data['pickup_fee'] : ""));
            $payment_method = trim(mysqli_real_escape_string($conn, !empty($data['payment_method']) ? $data['payment_method'] : ""));

            $response = $portal->orderConfirmation($conn, $token, $user_id, $cart_id, $pickup_station, $pickup_fee, $payment_method);


            if ($response) {
                http_response_code(200);
                echo $response;
            }
        } else {
            // Token is expired or invalid
            http_response_code(401); // Unauthorized
            echo json_encode(array('status' => 'error', 'message' => 'Expired or invalid token'));
        }
    } else {
        http_response_code(405);
        $response = array('status' => 'error', 'message' => 'Invalid request method');
        echo json_encode($response);
    }

