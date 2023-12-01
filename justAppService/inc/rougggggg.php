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
}
