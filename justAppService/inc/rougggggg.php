<?php

use ReallySimpleJWT\Token;
use function PHPSTORM_META\type;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
// Load Composer's autoloader
require '../vendor/autoload.php';

require 'vendor/autoload.php';
include('dbconnection.php');
$database = new database();
$conn = $database->getConnection();

class PortalUtility
{

    public function orderConfirmation($conn, $token, $user_id, $cart_id, $pickup_address, $pickup_fee, $payment_method) {
        $status = "";
        $order_id = $this->generateOrderId();
        $reference_id = $this->generateRefrenceID();
    
        $user_details = $this->fetch_user_details($conn, $user_id);
        $user_email = $user_details['user_email'];
    
        if (empty($token) || $this->validateToken($token)!== "true") {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $cart_total = $this->calculateCartTotal($conn, $user_id);
            $total_item_cost = $cart_total + $pickup_fee;
    
            $sql = "INSERT INTO `orders`(`order_id`, `reference_id`, `user_id`, `cart_id`, `order_date`, `total_amount`, `total_item_cost`, `pickup_fees`, `status`, `pickup_station`, `payment_method`, `payment_status`, `shipping_status`) VALUES ('$order_id', '$reference_id', '$user_id', '$cart_id', NOW(), '$cart_total', '$total_item_cost', '$pickup_fee', 'A', '$pickup_address', '$payment_method', 'Pending', 'Not Shipped')";
    
            $result = mysqli_query($conn, $sql);
    
            if ($result) {
                if ($payment_method === "paystack") {
                    $paymentResult = $this->handlePaystackPayment($conn, $token, $user_id, $total_item_cost, $order_id, $user_email);
                } else if ($payment_method === "wallet") {
                    $paymentResult = $this->handleWalletPayment($conn, $user_id, $total_item_cost, $order_id);
                } else {
                    $paymentResult = json_encode(array("status" => false, "message" => "Invalid payment method", "timestamp" => date('d-M-Y H:i:s')));
                }
                return $paymentResult;
            } else {
                $status = json_encode(array("status" => false, "message" => "Failed to create order", "timestamp" => date('d-M-Y H:i:s')));
            }
        }
    
        $this->server_logs($status);
        return $status;
    }
    
    private function handlePaystackPayment($conn, $token, $user_id, $total_amount, $order_id, $user_email) {
        $paystack = new Paystack($this->paystack_secret_key);
        $response = $paystack->transaction->initialize([
            'amount' => $total_amount * 100, // Convert to kobo
            'email' => $user_email,
            'eference' => $order_id,
            'callback_url' => 'https://example.com/payment/callback'
        ]);
    
        if ($response['status'] === true) {
            $transaction_ref = $response['data']['reference'];
            $sql = "UPDATE `orders` SET `payment_status` = 'Pending', `transaction_ref` = '$transaction_ref' WHERE `order_id` = '$order_id'";
            mysqli_query($conn, $sql);
    
            $paymentResult = json_encode(array("status" => true, "message" => "Order created successfully", "payment_method" => "Paystack", "transaction_ref" => $transaction_ref, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $paymentResult = json_encode(array("status" => false, "message" => "Failed to initiate payment", "timestamp" => date('d-M-Y H:i:s')));
        }
    
        return $paymentResult;
    }
    
    private function handleWalletPayment($conn, $user_id, $total_amount, $order_id) {
        $wallet_balance = $this->fetch_user_wallet_balance($conn, $user_id);
    
        if ($wallet_balance >= $total_amount) {
            $sql = "UPDATE `users` SET `wallet_balance` = `wallet_balance` - '$total_amount' WHERE `user_id` = '$user_id'";
            mysqli_query($conn, $sql);
    
            $sql = "UPDATE `orders` SET `payment_status` = 'Paid', `transaction_ref` = 'Wallet' WHERE `order_id` = '$order_id'";
            mysqli_query($conn, $sql);
    
            $paymentResult = json_encode(array("status" => true, "message" => "Order paid using wallet", "payment_method" => "Wallet", "transaction_ref" => "Wallet", "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $paymentResult = json_encode(array("status" => false, "message" => "Insufficient wallet balance", "timestamp" => date('d-M-Y H:i:s')));
        }
    
        return $paymentResult;
    }
    

    public function orderConfirmation($conn, $token, $user_id, $cart_id, $pickup_address, $pickup_fee, $payment_method)
    {
        $status = "";
        // $json = array();
        $order_id = $this->generateOrderId();
        $reference_id = $this->generateRefrenceID();

        $user_details = $this->fetch_user_details($conn, $user_id);
        $user_email = $user_details['user_email'];

        // Check if token is valid
        if (empty($token) || $this->validateToken($token) !== "true") {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else {

            $cart_total = $this->calculateCartTotal($conn, $user_id);
            $total_item_cost = $cart_total + $pickup_fee;

            $sql = "INSERT INTO `orders`(`order_id`, `reference_id`, `user_id`, `cart_id`, `order_date`, `total_amount`, `total_item_cost`, `pickup_fees`, `status`, `pickup_station`, `payment_method`, `payment_status`, `shipping_status`) VALUES 
        ('$order_id', '$reference_id', '$user_id', '$cart_id', NOW(), '$cart_total', '$total_item_cost', '$pickup_fee', 'A', '$pickup_address', '$payment_method', 'Pending', 'Not Shipped')";

            $result = mysqli_query($conn, $sql);

            if ($result) {
                $status =  json_encode(array("status" => true, "message" => "success", "order_id" => $order_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status =  json_encode(array("status" => false, "message" => "fail", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
           
        }

        // Log server response
        $this->server_logs($status);
        return $status;
    }
    public function updateOrderStatusWallet($conn, $user_id, $status)
    {
        $sql = "UPDATE `orders` SET  `payment_status` = '$status' WHERE `user_id` = '$user_id'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            return true;
        } else {
            // error_log("Error updating order status: " . mysqli_error($conn));
            return false;
        }
    }
    public function deductFromWallet($conn, $token, $user_id, $amount)
    {
        $status = "";

        if (empty($token)) {
            $status = json_encode(array("success" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } elseif ($this->validateToken($token) === "true") {
            // Fetch the current wallet balance
            $qry = mysqli_query($conn, "SELECT balance FROM user_wallet WHERE user_id = '$user_id'");
            $currentWalletBalance = mysqli_fetch_assoc($qry)['balance'];

            // Check if the wallet balance is sufficient
            if ($currentWalletBalance >= $amount) {
                // Deduct the amount from the wallet
                $newWalletBalance = $currentWalletBalance - $amount;
                $updateWalletQuery = mysqli_query($conn, "UPDATE user_wallet SET balance = '$newWalletBalance' WHERE user_id = '$user_id'");

                if ($updateWalletQuery) {
                    // Wallet deduction successful
                    $status =  json_encode(array("status" => true, "message" => "$amount Deducted from wallet", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                } else {
                    // Wallet deduction failed
                    $status =  json_encode(array("status" => false, "message" => "Deduction Failed", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));

                }
            } else {
                // Insufficient funds in the wallet
                $status =  json_encode(array("status" => false, "message" => "Insufficient Amount", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));

            }
        }
    }

$result = mysqli_query($conn, $sql);
if ($result) {
    // Check the chosen payment method
if ($payment_method === 'wallet') {
    // Handle wallet payment
$walletDeductionResult = $this->deductFromWallet($conn, $token, $user_id, $cart_total);

if ($walletDeductionResult['status']) {
    // Wallet deduction successful, update order status
$this->updateOrderStatusWallet($conn, $user_id, 'Paid');
$status = json_encode(array("status" => true, "message" => "success", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
} else {
    // Wallet deduction failed
    $status = json_encode(array("status" => false, "message" => "wallet_deduction_fail", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
}

} elseif ($payment_method === 'paystack') {
    // Handle Paystack payment
$paystackPaymentResult = $this->initiatePayment($conn, $user_id, $user_email);

if ($paystackPaymentResult['status']) {
    // Payment process initiated successfully
    $status = json_encode(array(
        "status" => true,
        "message" => "success",
        "order_id" => $order_id,
        "token" => $token,
        "timestamp" => date('d-M-Y H:i:s'),

    ));
} else {
    // Payment initiation with Paystack failed
    $status = json_encode(array("status" => false, "message" => "paystack_payment_fail", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
}


    public function applyCoupon($conn, $cartId, $couponCode)
    {
        // Validate coupon code
        $couponData = $this->validateCoupon($conn, $couponCode);

        if ($couponData) {
            // Coupon is valid
            $discountAmount = $this->calculateDiscount($cartId, $couponData['DiscountType'], $couponData['DiscountValue']);

            // Apply discount to the cart
            $this->applyDiscountToCart($conn, $cartId, $discountAmount);

            return json_encode(array(
                "responseCode" => "00",
                "message" => "Coupon applied successfully",
                "discountAmount" => $discountAmount
            ));
        } else {
            // Invalid coupon
            return json_encode(array(
                "responseCode" => "08",
                "message" => "Invalid coupon code"
            ));
        }
    }

    private function validateCoupon($conn, $couponCode)
    {
        // Query the Coupons table to check if the coupon code is valid
        $sql = "SELECT * FROM Coupons WHERE Code = '$couponCode' AND ExpirationDate >= NOW()";
        $result = mysqli_query($conn, $sql);

        if ($result && $row = mysqli_fetch_assoc($result)) {
            return $row;
        }

        return null;
    }

    private function calculateDiscount($cartId, $discountType, $discountValue)
    {
        // Implement logic to calculate the discount based on the discount type and value
        // For example, percentage or fixed amount

        // For simplicity, let's assume a percentage discount
        $cartTotal = $this->getCartTotal($cartId);
        $discountAmount = ($discountType == 'percentage') ? ($cartTotal * $discountValue / 100) : $discountValue;

        return $discountAmount;
    }

    private function applyDiscountToCart($conn, $cartId, $discountAmount)
    {
        // Update the Cart table to apply the discount
        $sql = "UPDATE Carts SET DiscountAmount = $discountAmount WHERE CartID = $cartId";
        mysqli_query($conn, $sql);
    }

    private function getCartTotal($conn, $cartId)
    {
        // Query the database to get the total amount of the items in the cart
        $sql = "SELECT SUM(ci.Quantity * p.Price) AS TotalAmount
            FROM CartItems ci
            JOIN Products p ON ci.ProductID = p.ProductID
            WHERE ci.CartID = $cartId";

        $result = mysqli_query($conn, $sql);

        if ($result && $row = mysqli_fetch_assoc($result)) {
            // Return the calculated total amount
            return $row['TotalAmount'];
        }

        // Return 0 if there are no items in the cart or an error occurs
        return 0.00;
    }


    // Function to get user's cart ID


    // Function to create a new cart for the user


    // Function to get cart item from the database
    private function getCartItemFromDatabase($cartId, $productId)
    {
        $sql = "SELECT * FROM CartItems WHERE CartID = $cartId AND ProductID = $productId";
        $result = mysqli_query($this->conn, $sql);

        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return $row;
        }

        return null;
    }


    public function initiateCheckout($conn, $token, $user_id)
    {
        $json = array();

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // Fetch cart items for the user
            $cartItems = $this->getCartItems($conn, $user_id);

            if (!empty($cartItems)) {
                // Calculate total price and other necessary details for the checkout
                $totalPrice = $this->calculateTotalPrice($cartItems);

                // Here, you might want to store the checkout details in a temporary order table
                $orderID = $this->createTemporaryOrder($conn, $user_id, $totalPrice);

                // Provide the necessary information to the frontend for the checkout process
                $checkoutInfo = array(
                    "order_id" => $orderID,
                    "total_price" => $totalPrice,
                    "cart_items" => $cartItems,
                );

                $status = json_encode(array("responseCode" => "00", "message" => "success", "token" => $token, "checkout_info" => $checkoutInfo, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("responseCode" => "02", "message" => "empty_cart", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    // Example function to calculate total price from cart items
    private function calculateTotalPrice($cartItems)
    {
        $totalPrice = 0;
        foreach ($cartItems as $item) {
            $totalPrice += $item['price_at_purchase'] * $item['product_quantity'];
        }
        return $totalPrice;
    }

    // Example function to create a temporary order in the database
    private function createTemporaryOrder($conn, $user_id, $totalPrice)
    {
        // Insert order details into a temporary order table
        $insertOrderSQL = "INSERT INTO `temporary_orders` (`user_id`, `total_price`) VALUES ('$user_id', '$totalPrice')";
        mysqli_query($conn, $insertOrderSQL);

        // Retrieve the order ID
        return mysqli_insert_id($conn);
    }

   

 

        public function orderConfirmation($conn, $token, $user_id, $cart_id, $product_id, $shipping_address, $payment_method, $shipping_method) {
            $status = "";
            $order_id = $this->generateOrderId();
            $order_date = date('Y-m-d H:i:s');
        
            // Check if token is valid
            if (empty($token) || $this->validateToken($token)!== "true") {
                $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                // Get cart items
                $cart_items = $this->getCartItem($conn, $cart_id, $product_id);
                $product_name = $cart_items['product_name'];
                $product_quantity = $cart_items['product_quantity'];
                $total_product_amount = 0;
        
                // Calculate total product amount
                foreach ($cart_items as $cart_item) {
                    if (isset($cart_item['product_price']) && is_numeric($cart_item['product_price'])) {
                        $total_product_amount += floatval($cart_item['product_price']);
                    }
                }
        
                // Insert order into database
                $sql = "INSERT INTO `orders`(`order_id`, `product_id`, `user_id`, `order_date`, `product_name`, `product_quantity`, `total_amount`, `status`, `shipping_address`, `payment_method`, `payment_status`, `shipping_method`, `shipping_status`) VALUES ('$order_id', '$product_id', '$user_id', '$order_date', '$product_name', '$product_quantity', '$total_product_amount', 'A', '$shipping_address', '$payment_method', 'Pending', '$shipping_method', 'Not Shipped')";
                $result = mysqli_query($conn, $sql);
        
                if ($result) {
                    $status =  json_encode(array("status" => true, "message" => "success", "order_id" => $order_id, "product_id" => $product_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                } else {
                    $status =  json_encode(array("status" => false, "message" => "fail", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                }
            }
        
            // Log server response
            $this->server_logs($status);
            return $status;
        }
        
    }


    <?php
include_once('../inc/portal.php');

// $data = json_decode(@file_get_contents("php://input"), true);

$portal = new PortalUtility();

// Set your Paystack secret key
$secretKey = 'sk_test_a6e2ff7cb98a4357c9bcce766eeb67cbaa58b0f4';

// Retrieve the request body and signature header
$input = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : null;

// Verify the webhook signature
$expectedSignature = hash_hmac('sha256', $input, $secretKey);
if ($signature !== $expectedSignature) {
    error_log('Invalid Paystack signature');
    header('HTTP/1.1 403 Forbidden');
    die('Invalid signature');
}

// Process the webhook payload
$payload = json_decode($input, true);

// Extract relevant data from the payload
$event = $payload['body']['event'];
$transactionId = $payload['body']['data']['id'];
$reference = $payload['body']['data']['reference'];
$amount = $payload['body']['data']['amount'];
$status = $payload['body']['data']['status'];
$customerEmail = $payload['body']['data']['customer']['email'];

// Your webhook handling logic here
// For example, you can log the extracted data
$logData = "Event: $event, Transaction ID: $transactionId, Amount: $amount, Customer Email: $customerEmail";
file_put_contents('paystack_webhook.log', $logData . PHP_EOL, FILE_APPEND);

try {
    // Update order status in your orders table
    if ($status === 'success') {
        // Assuming you have an 'orders' table
       $portal->updateOrderStatus($conn, $reference, $amountInNaira, $user_id, $user_email, $status);
    } else {
        // Log unsuccessful charge
        // $logUnsuccessfulChargeQuery = "INSERT INTO unsuccessful_charges (transaction_id, reference, amount, status, customer_email) 
        //                               VALUES ('$transactionId', '$reference', '$amount', '$status', '$customerEmail')";
        // $conn->query($logUnsuccessfulChargeQuery);

        // Additional actions for unsuccessful charge
        // You might want to send an email notification, update the user's account, etc.
        // Example: Send email
        // mail($customerEmail, 'Payment Unsuccessful', 'Your payment was unsuccessful. Please contact support for assistance.');
    }

    // Insert data into the transactions table
    $insertTransactionQuery = "INSERT INTO transactions (transaction_id, reference, amount, status, customer_email) 
                              VALUES ('$transactionId', '$reference', '$amount', '$status', '$customerEmail')";
    $conn->query($insertTransactionQuery);

    // Close the database connection
    $conn->close();

    // Send a 200 OK response to Paystack
    http_response_code(200);
    echo 'Webhook received successfully';
} catch (Exception $e) {
    // Log any exceptions
    error_log("Exception: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    die('Internal Server Error');
}


public function viewProductByProductID($conn, $product_id, $token) {
    $status = [
        "status" => false,
        "message" => "invalid_token",
        "product_id" => $product_id,
        "token" => $token,
        "timestamp" => date('d-M-Y H:i:s')
    ];

    if (!empty($token) && $this->validateToken($token) === "true") {
        $sql = "SELECT products.*, products_images.image_url FROM products INNER JOIN products_images ON products.product_id = products_images.product_id WHERE products.product_id = '$product_id' ORDER BY products.stampdate DESC";
        $result = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
            $status = [
                "status" => true,
                "message" => "success",
                "token" => $token,
                "data" => [
                    "product" => $row,
                    "images" => [
                        "image_url" => $row["image_url"]
                    ]
                ],
                "timestamp" => date('d-M-Y H:i:s')
            ];
        }
    } else {
        $status = [
            "status" => false,
            "message" => "expired_token",
            "token" => $token,
            "timestamp" => date('d-M-Y H:i:s')
        ];
    }

    $this->server_logs($status);
    return $status;
}


}
