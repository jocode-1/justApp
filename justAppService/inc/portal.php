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

    private $secret_key = 'sk_test_a6e2ff7cb98a4357c9bcce766eeb67cbaa58b0f4';
    // public $secret_key = "sk_live_2711dd151ab5268e2a566f4e799dd59fd45df9fb";
    // SIGNUP USER 

    public function createUser($conn, $username, $user_email, $user_password, $user_phone_number)
    {
        $json = array();
        $user_id = $this->generataUserID();
        $user_referral_code = $this->generataReferralID();
        // $user_last_loggedIn = date('Y-m-d H:i:s');
        $passwordFormated = password_hash($user_password, PASSWORD_DEFAULT);
        // $user_fullname = 

        $status = array();
        $sql = "INSERT INTO `users`(`user_id`, `username`, `user_email`, `user_password`, `user_phone_number`, `user_account_status`, `status`)
        VALUE('$user_id', '$username', '$user_email', '$passwordFormated', '$user_phone_number', 'Approved', 'N')";

        $result = mysqli_query($conn, $sql);
        if ($result) {
            $rows = $this->fetch_user_details($conn, $user_id);
            $json[] = $rows;
            $status = array("status" => "00", "message" => "success", "data" => $json);
            $this->welcomNewUserMail($conn, $user_email, $username);
            // $this->createCustomer($user_email, $user_firstname, $user_phone_number);
            // $this->createAccount($user_email);
        } else {
            $rows = $this->fetch_user_details($conn, $user_id);
            $status = array("status" => "error", "data" => $json);
        }

        return json_encode($status, JSON_PRETTY_PRINT);
    }

    public function login_users($conn, $user_email, $user_password)
    {
        $status = "";
        $json = array();
        $user_array = $this->validateUsers($conn, $user_email, $user_password);
        unset($user_array['user_password']);
        $json[] = $user_array;
        //  var_dump($user_array);
        if (sizeof($user_array) > 0) {
            $userId = $user_email . $user_password;
            $secret = 'sec!ReT423*&';
            $expiration = time() + 3600;
            $issuer = 'localhost';

            $token = Token::create($userId, $secret, $expiration, $issuer);
            //echo $token;
            $this->userLoginDate($conn, $user_email);
            $this->updateUserIP($conn, $user_email);
            $status =  json_encode(array("responseCode" => "00", "message" => "success", "data" => $json, "tokenType" => "Bearer", "expiresIn" => "3600", "accessToken" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            // Check specifically for wrong password
            $existingUser = $this->validateUsers($conn, $user_email, '');

            if (!empty($existingUser)) {
                // User exists but password is wrong
                $status = json_encode(array("responseCode" => "03", "message" => "wrongPassword", "timestamp" => date('d-M-Y H:i:s')));
            } else {
                // User does not exist
                $status = json_encode(array("responseCode" => "04", "message" => "invalidCredentials", "timestamp" => date('d-M-Y H:i:s')));
            }
        }
        $this->server_logs($status);
        return $status;
    }

    public function reset_user_password($conn, $user_email)
    {
        $status = array();
        $reset = $this->create_reset_code();
        $passwordFormated = password_hash($reset, PASSWORD_DEFAULT);
        $sql = "UPDATE `users` SET `user_password` = '$passwordFormated' WHERE `user_email` = '$user_email'";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            $status = array("status" => "success", "user_email" => $user_email);
            $this->forgotUserPasswordMail($conn, $user_email, $reset);
        } else {
            $status = array("status" => "error", "email" => "null");
        }
        return json_encode($status, JSON_PRETTY_PRINT);
    }

    public function userLoginDate($conn, $user_email)
    {
        $status = array();
        $date = date('Y-m-d H:i:s');
        $sql = "UPDATE `users` SET `user_last_loggedIn` = '$date' WHERE `user_email` = '$user_email'";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            $status = array("status" => "success", "user_email" => $user_email);
        } else {
            $status = array("status" => "error", "user_email" => null);
        }
        return json_encode($status, JSON_PRETTY_PRINT);
    }

    public function updateUserIP($conn, $user_email)
    {
        $status = array();
        $user_ip = $this->getIPAddress();
        $sql = "UPDATE `users` SET `user_IP_address` = '$user_ip' WHERE `user_email` = '$user_email'";
        $result = mysqli_query($conn, $sql);
        if ($result) {
            $status = array("status" => "success", "user_email" => $user_email);
        } else {
            $status = array("status" => "error", "user_email" => null);
        }
        return json_encode($status, JSON_PRETTY_PRINT);
    }

    public function send_verification_email($conn, $user_email)
    {
        $status = array();

        // Generate a unique verification code and expiration time
        $verification_code = $this->generate_verification_code();
        $expiration_time = time() + (15 * 60); // 24 hours validity

        // Update the user record with the verification code and expiration time
        $update_sql = "UPDATE `users` SET `verification_code` = '$verification_code', `verification_expires_at` = '$expiration_time' WHERE `user_email` = '$user_email'";
        $update_result = mysqli_query($conn, $update_sql);

        if ($update_result) {
            // Construct verification URL
            $verification_url = "http://localhost/justApp/justAppService/verify.php?email=$user_email&code=$verification_code";

            // Send a verification email with the URL
            $email_result = $this->sendVerificationMailInternal($conn, $user_email, $verification_url);

            if ($email_result) {
                $status = array("status" => "success", "user_email" => $user_email);
            } else {
                $status = array("status" => "error", "message" => "Failed to send verification email");
            }
        } else {
            $status = array("status" => "error", "message" => "Failed to update verification code");
        }

        return json_encode($status, JSON_PRETTY_PRINT);
    }

    public function getIPAddress()
    {
        $ip = $_SERVER['REMOTE_ADDR'];

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $ip;
    }

    public function update_user_password($conn, $user_email, $user_password, $token)
    {
        $status = "";
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $passwordFormated = password_hash($user_password, PASSWORD_DEFAULT);
            $sql = "UPDATE `users` SET `user_password` = '$passwordFormated'  WHERE `user_email` = '$user_email'";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $status = json_encode(array("responseCode" => "00", "message" => "success", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("responseCode" => "04", "message" => "fail", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }
    public function update_user_profile($conn, $token, $user_firstname, $user_lastname, $user_email, $user_address, $user_password, $user_phone_number, $user_gender, $user_dob, $user_profile_picture)
    {
        $status = "";
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $passwordFormated = password_hash($user_password, PASSWORD_DEFAULT);
            $sql = "UPDATE `users` SET `user_password` = '$passwordFormated'  WHERE `user_email` = '$user_email'";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $status = json_encode(array("responseCode" => "00", "message" => "success", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("responseCode" => "04", "message" => "fail", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function validateToken($token)
    {
        $secret = 'sec!ReT423*&';

        $result = Token::validate($token, $secret);
        $converted_res = $result ? 'true' : 'false';
        return '' . $converted_res;
    }

    function getBearerToken()
    {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    function getAuthorizationHeader()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    public function server_logs($log_msg)
    {

        $log_filename = "server_logs";
        if (!file_exists($log_filename)) {
            // create directory/folder uploads.
            mkdir($log_filename, 0777, true);
        }
        $log_file_data = $log_filename . '/log_' . date('d-M-Y') . '.log';
        // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
        file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
    }

    public function mailer_logs($log_msg)
    {

        $log_filename = "mail_logs";
        if (!file_exists($log_filename)) {
            // create directory/folder uploads.
            mkdir($log_filename, 0777, true);
        }
        $log_file_data = $log_filename . '/log_' . date('d-M-Y') . '.log';
        // if you don't add `FILE_APPEND`, the file will be erased each time you add a log
        file_put_contents($log_file_data, $log_msg . "\n", FILE_APPEND);
    }

    public function validateUsers($conn, $user_email, $user_password)
    {
        $json = array();
        $sql = "SELECT * FROM `users` WHERE `user_email` = '$user_email'";
        $result = mysqli_query($conn, $sql);
        $user_array = mysqli_fetch_array($result, MYSQLI_ASSOC);
        $hashed = $user_array['user_password'];
        if (password_verify($user_password, $hashed)) {
            if ($user_array == NULL) {
                $user_array = $json;
            } else {
                $json = $user_array;
            }
        } else {
            $user_array = $json;
        }

        return $json;
    }

    public function checkUserExists($conn, $user_email)
    {
        $sql = "SELECT * FROM `users` WHERE `user_email` = '$user_email'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_num_rows($result);
        return $row;
    }

    public function create_reset_code()
    {
        $uni = substr(str_shuffle(str_repeat("0123456789", 6)), 0, 6);

        return $uni;
    }
    public function generataUserID()
    {
        $uni = substr(str_shuffle(str_repeat("0123456789", 10)), 0, 10);

        return $uni;
    }

    public function generataReferralID()
    {
        $uni = substr(str_shuffle(str_repeat("ABCDEFGHIJKLMNOPQRSTUVWXYZ", 6)), 0, 6);

        return $uni;
    }

    public function fetch_user_details($conn, $user_id)
    {
        $json  = array();
        $sql = "SELECT `user_id`, `user_firstname`, `user_lastname`, `user_email`, `user_address`, `user_phone_number`, `user_gender`, `user_dob`, `user_profile_picture`, `user_last_loggedIn` FROM `users` WHERE user_id = '$user_id'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        return $row;
    }

    public function getUserInfoByEmail($conn, $user_email)
    {
        $sql = "SELECT * FROM `users` WHERE `user_email`  = '$user_email'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        return $row;
    }

    public function forgotUserPasswordMail($conn, $user_email, $code)
    {

        $template = 'http://localhost/justApp/justAppService/inc/templates/forgotTenantMail.phtml';
        $userMail =  $this->getUserInfoByEmail($conn, $user_email);
        $user_firstname = $userMail['user_firstname'];
        $id = $userMail['user_id'];

        $body = file_get_contents($template);
        $body = str_replace('%agent%', $id, $body);
        $body = str_replace('%name%', $user_firstname, $body);
        $body = str_replace('%code%', $code, $body);

        $mail = new PHPMailer(true);
        try {

            //  $mail->SMTPDebug = 3;                      
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host       = 'thirtyfour.qservers.net.';                    // Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = 'support@donchimerk.org';                     // SMTP username
            $mail->Password   = 'Cougar@123..??';                                 // SMTP password
            $mail->SMTPSecure  = 'ssl';
            $mail->Debugoutput = 'html';
            $mail->Port       = 465;
            $mail->setFrom('support@donchimerk.org', 'Just App Service');
            $mail->addAddress($user_email, $user_firstname);
            $mail->isHTML(true);                                  // Set email format to HTML  
            $mail->Subject = 'PASSWORD RESET';
            $mail->Body    = $body;
            // $mail->AddEmbeddedImage('logo-icon.png', 'logo_2u');

            $mail->send();
            //echo 'Message has been sent';
            $this->mailer_logs('Mail Sent Successfully To ' . $user_email . ' Firstname : ' . $user_firstname . ' TIMESTAMP : ' . date('Y-m-d : h:m:s'));
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$e}";
            $this->mailer_logs('Mail Sending Error ' . $user_email . ' Firstname : ' . $user_firstname . ' TIMESTAMP : ' . date('Y-m-d : h:m:s'));
        }
    }
    public function welcomNewUserMail($conn, $user_email, $username)
    {

        $template = 'http://localhost/justApp/justAppService/inc/templates/registerMail.phtml';
        $userMail =  $this->getUserInfoByEmail($conn, $user_email);
        $username = $userMail['username'];
        $id = $userMail['user_id'];

        $body = file_get_contents($template);
        $body = str_replace('%user_id%', $id, $body);
        $body = str_replace('%username%', $username, $body);

        $mail = new PHPMailer(true);
        try {

            //  $mail->SMTPDebug = 3;                      
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host       = 'thirtyfour.qservers.net.';                    // Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = 'support@donchimerk.org';                     // SMTP username
            $mail->Password   = 'Cougar@123..??';                                 // SMTP password
            $mail->SMTPSecure  = 'ssl';
            $mail->Debugoutput = 'html';
            $mail->Port       = 465;
            $mail->setFrom('support@donchimerk.org', 'Just App Service');
            $mail->addAddress($user_email, $username);
            $mail->isHTML(true);                                  // Set email format to HTML  
            $mail->Subject = 'WELCOME MAIL';
            $mail->Body    = $body;
            // $mail->AddEmbeddedImage('logo-icon.png', 'logo_2u');

            $mail->send();
            //echo 'Message has been sent';
            $this->mailer_logs('Mail Sent Successfully To ' . $user_email . ' Username : ' . $username . ' TIMESTAMP : ' . date('Y-m-d : h:m:s'));
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$e}";
            $this->mailer_logs('Mail Sending Error ' . $user_email . ' Username : ' . $username . ' TIMESTAMP : ' . date('Y-m-d : h:m:s'));
        }
    }

    public function sendVerificationMailInternal($conn, $user_email, $verification_url)
    {
        $template = 'http://localhost/justApp/justAppService/inc/templates/verificationMail.phtml';
        $userMail =  $this->getUserInfoByEmail($conn, $user_email);
        $username = $userMail['username'];
        $id = $userMail['user_id'];

        $body = file_get_contents($template);
        $body = str_replace('%user_id%', $id, $body);
        $body = str_replace('%username%', $username, $body);
        $body = str_replace('%verification_url%', $verification_url, $body);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'thirtyfour.qservers.net.';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'support@donchimerk.org';
            $mail->Password   = 'Cougar@123..??';
            $mail->SMTPSecure  = 'ssl';
            $mail->Debugoutput = 'html';
            $mail->Port       = 465;
            $mail->setFrom('support@donchimerk.org', 'Just App Service');
            $mail->addAddress($user_email, $username);
            $mail->isHTML(true);
            $mail->Subject = 'Email Verification';
            $mail->Body    = $body;

            $mail->send();
            $this->mailer_logs('Verification Mail Sent Successfully To ' . $user_email . ' Username : ' . $username . ' TIMESTAMP : ' . date('Y-m-d : h:m:s'));
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$e}";
            $this->mailer_logs('Verification Mail Sending Error ' . $user_email . ' Username : ' . $username . ' TIMESTAMP : ' . date('Y-m-d : h:m:s'));
        }
    }


    public function addImageTOProduct($conn, $product_id, $image_url, $token)
    {
        $status = "";
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "product_id" => $product_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $status = '';
            $row = $this->fetch_max_images($conn, $product_id);
            if ($row >= 6) {
                $status = json_encode(array("responseCode" => "08", "message" => "imageLimit",  "product_id" => $product_id, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $image_id = $this->create_image_id();
                $sql = "INSERT INTO `products_images`(`product_id`, `image_id`, `image_url`) 
            VALUES ('$product_id','$image_id','$image_url')";
                $result = mysqli_query($conn, $sql);
                if ($result) {
                    $status = json_encode(array("responseCode" => "00", "message" => "success", "product_id" => $product_id, "timestamp" => date('d-M-Y H:i:s')));
                } else {
                    $status = json_encode(array("responseCode" => "04", "message" => "fail",  "product_id" => $product_id, "timestamp" => date('d-M-Y H:i:s')));
                }
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "product_id" => $product_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function fetch_max_images($conn, $product_id)
    {
        $sql = "SELECT * FROM `products_images` WHERE `product_id` = '$product_id'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_num_rows($result);
        return $row;
    }

    public function create_image_id()
    {
        $uni = substr(str_shuffle(str_repeat("0123456789", 20)), 0, 20);

        return $uni;
    }

    public function createProduct(
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
    ) {

        $status = '';

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token",  "staff_id" => $staff_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {

            $product_id = $this->create_product_id('PRO');
            $discountAmount = intval($product_discount_percentage) / 100 * intval($product_price);
            $taxAmount = intval($product_tax_percentage) / 100 * intval($product_price);

            $sql = "INSERT INTO `products`(`staff_id`, `product_id`, `brand_id`, `category_id`, `product_name`, `product_description`, `product_price`, `product_stock_quantity`, `product_weight`, `product_category`,
                    `product_brand`, `product_discount_percentage`, `product_tax_percentage`, `product_barcode`, `product_tags`, `product_warranty_information`, 
                   `product_warranty_type`, `product_warranty_duration`, `product_warranty_details`, `product_rating_count`, `product_status`, `status`) 
                   VALUES('$staff_id', '$product_id', '$brand_id', '$category_id', '$product_name', '$product_description', '$product_price', '$product_stock_quantity', '$product_weight', '$product_category',
    '$product_brand', '$discountAmount', '$taxAmount', '$product_barcode', '$product_tags', '$product_warranty_information', '$product_warranty_type', '$product_warranty_duration',
    '$product_warranty_details', '$product_rating_count', '$product_status', 'A')";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $status = json_encode(array("responseCode" => "00", "message" => "success", "staff_id" => $staff_id, "product_id" => $product_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("responseCode" => "04", "message" => "fail",  "staff_id" => $staff_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token",  "staff_id" => $staff_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function create_product_id($name)
    {
        $user = substr($name, 0, 3);
        $uni = substr(str_shuffle(str_repeat("0123456789", 4)), 0, 4);

        return $user . '' . $uni;
    }

    public function create_category_id($name)
    {
        $user = substr($name, 0, 3);
        $uni = substr(str_shuffle(str_repeat("0123456789", 4)), 0, 4);

        return $user . '' . $uni;
    }

    public function create_brand_id($name)
    {
        $user = substr($name, 0, 3);
        $uni = substr(str_shuffle(str_repeat("0123456789", 4)), 0, 4);

        return $user . '' . $uni;
    }
    public function generate_verification_code()
    {

        $uni = substr(str_shuffle(str_repeat("MNOPQRSTUVWXYZ0123456789abcdefghijklm", 4)), 0, 7);
        return $uni;
    }

    public function createProductCategory($conn, $token, $category_name, $category_description)
    {

        $status = '';
        $category_id = $this->create_category_id("CAT");

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token",  "category_id" => $category_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "INSERT INTO `product_category`(`category_id`, `category_name`, `category_description`, `status`)
             VALUES('$category_id','$category_name','$category_description','A')";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $status = json_encode(array("responseCode" => "00", "message" => "success", "category_id" => $category_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("responseCode" => "04", "message" => "fail",  "category_id" => $category_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token",  "category_id" => $category_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function createProductBrand($conn, $token, $brand_name, $brand_description)
    {

        $status = '';
        $brand_id = $this->create_category_id("BRD");

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token",  "brand_id" => $brand_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "INSERT INTO `product_brand`(`brand_id`, `brand_name`, `brand_description`, `status`)
             VALUES('$brand_id','$brand_name','$brand_description','A')";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $status = json_encode(array("responseCode" => "00", "message" => "success", "brand_id" => $brand_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("responseCode" => "04", "message" => "fail",  "brand_id" => $brand_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token",  "brand_id" => $brand_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }


    public function viewProductCategory($conn, $token)
    {

        $json = array();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "SELECT * FROM `product_category` ORDER BY stampdate DESC";
            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("responseCode" => "00", "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return json_encode($json, JSON_PRETTY_PRINT);
    }
    public function viewProductBrand($conn, $token)
    {

        $json = array();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "SELECT * FROM `product_brand` ORDER BY stampdate DESC";
            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("responseCode" => "00", "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return json_encode($json, JSON_PRETTY_PRINT);
    }
    public function viewProductByProductID($conn, $product_id, $token)
    {
        $status = "";
        $json = array();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "product_id" => $product_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "SELECT * FROM `products` WHERE `product_id` = '$product_id' ORDER BY stampdate DESC";
            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("responseCode" => "00", "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function viewProductByCategoryID($conn, $category_id, $token)
    {
        $status = "";
        $json = array();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "category_id" => $category_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "SELECT * FROM `products` WHERE `category_id` = '$category_id' ORDER BY stampdate DESC";
            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("responseCode" => "00", "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }
    public function viewProductByBrandID($conn, $brand_id, $token)
    {
        $status = "";
        $json = array();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "brand_id" => $brand_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "SELECT * FROM `products` WHERE `brand_id` = '$brand_id' ORDER BY stampdate DESC";
            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("responseCode" => "00", "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function viewAllProduct($conn, $token)
    {

        $json = array();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // $sql = "SELECT CI.cart_id, CI.product_id, CI.product_quantity, CI.product_name, CI.price_at_purchase, UC.user_id FROM user_cart_item CI, user_cart UC WHERE CI.cart_id = UC.user_id AND UC.user_id = '$user_id'";
            $sql = "SELECT * FROM `products` WHERE product_status = 'Active' ORDER BY stampdate DESC";
            $sql = $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("responseCode" => "00", "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return json_encode($json, JSON_PRETTY_PRINT);
    }
    public function viewAllProductAdmin($conn, $token)
    {

        $json = array();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // $sql = "SELECT CI.cart_id, CI.product_id, CI.product_quantity, CI.product_name, CI.price_at_purchase, UC.user_id FROM user_cart_item CI, user_cart UC WHERE CI.cart_id = UC.user_id AND UC.user_id = '$user_id'";
            $sql = "SELECT * FROM `products` ORDER BY stampdate DESC";
            $sql = $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("responseCode" => "00", "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return json_encode($json, JSON_PRETTY_PRINT);
    }

    public function create_cart_id()
    {
        $uni = substr(str_shuffle(str_repeat("abcdefghi01234jklmnopqrs56789tuvwxyz", 5)), 0, 20);

        return $uni;
    }

    public function create_cart_item_id()
    {
        $uni = substr(str_shuffle(str_repeat("abcdefghi01234jklmnopqrs56789tuvwxyz", 5)), 0, 10);

        return $uni;
    }

    public function createUserCart($conn, $token, $user_id)
    {
        $status = '';
        $cart_id = $this->create_cart_id();

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "cart_id" => $cart_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // Check if the user already has an active cart
            $existingCartId = $this->getUserActiveCart($conn, $user_id);

            if ($existingCartId) {
                // User already has an active cart, handle accordingly
                $status = json_encode(array("responseCode" => "09", "message" => "user_has_active_cart", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                // Create a new cart
                $sql = "INSERT INTO `user_cart`(`cart_id`, `user_id`, `status`, `updatedAt`) VALUES ('$cart_id', '$user_id', 'A', NOW())";
                $result = mysqli_query($conn, $sql);

                if ($result) {
                    $status = json_encode(array("responseCode" => "00", "message" => "success", "cart_id" => $cart_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                } else {
                    $status = json_encode(array("responseCode" => "04", "message" => "fail", "cart_id" => $cart_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                }
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "cart_id" => $cart_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    // Function to get user's active cart ID
    public function getUserActiveCart($conn, $user_id)
    {
        $sql = "SELECT cart_id FROM user_cart WHERE user_id = '$user_id' AND status = 'A'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_array($result);
        return $row;
    }


    public function getUserCartId($conn, $user_id)
    {
        $sql = "SELECT `cart_id` FROM `user_cart` WHERE `user_id` = $user_id";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return ($row) ? $row['cart_id'] : null;
        }

        return null;
    }


    public function getCartItemFromDatabase($conn, $cart_id, $product_id)
    {
        $sql = "SELECT * FROM user_cart_item WHERE `cart_id` = '$cart_id' AND `product_id` = '$product_id'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_num_rows($result);
        return $row;
    }

    public function addItemToCart($conn, $token, $user_id, $product_id, $product_name, $product_quantity, $priceAtPurchase)
    {
        $status = '';
        $cart_item_id = $this->create_cart_item_id();
        $cart_id = $this->getUserCartId($conn, $user_id);

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "product_id" => $product_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {

            if (!$cart_id) {
                $this->createUserCart($conn, $token, $user_id);
            } else {
                // Check if the product is already in the cart
                $existingCartItem = $this->getCartItem($conn, $cart_id, $product_id);

                if ($existingCartItem) {
                    // Product exists in the cart, update quantity, update price
                    $newQuantity = intval($existingCartItem['product_quantity']) + 1;
                    $newPrice = intval($existingCartItem['price_at_purchase']) + $priceAtPurchase;
                    $sql = "UPDATE `user_cart_item` SET `product_quantity` = '$newQuantity', `price_at_purchase` = '$newPrice' WHERE `cart_id` = '$cart_id' AND `product_id` = '$product_id'";
                } else {
                    // Product is not in the cart, insert a new product 
                    $sql = "INSERT INTO `user_cart_item` (`user_id`, `cart_item_id`, `cart_id`, `product_id`, `product_name`, `cart_status`, `product_quantity`, `price_at_purchase`) VALUES ('$user_id', '$cart_item_id', '$cart_id', '$product_id', '$product_name', 'Pending', '$product_quantity', '$priceAtPurchase')";
                }
            }

            $result = mysqli_query($conn, $sql);

            if ($result) {
                return json_encode(array(
                    "responseCode" => "00", "message" => "Product added to cart successfully", "cart_item_id" => $cart_item_id, "timestamp" => date('d-M-Y H:i:s')
                ));
            } else {
                return json_encode(array(
                    "responseCode" => "99", "message" => "Error adding/updating product in cart", "cart_item_id" => $cart_item_id, "timestamp" => date('d-M-Y H:i:s')
                ));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }


    public function removeProductFromCart($conn, $token, $user_id, $product_id)
    {
        $status = '';
        $cart_id = $this->getUserCartId($conn, $user_id);

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // Check if the user has items in the cart
            $existingCartItem = $this->getCartItem($conn, $cart_id, $product_id);

            if (!$existingCartItem) {
                return json_encode(array("responseCode" => "02", "message" => "product not in cart", "timestamp" => date('d-M-Y H:i:s')));
            }

            // Remove the product from the cart
            $result = $this->deleteCartItem($conn, $cart_id, $product_id);

            if ($result) {
                return json_encode(array("responseCode" => "00", "message" => "product removed from cart", "product_id" => "$product_id", "timestamp" => date('d-M-Y H:i:s')));
            } else {
                return json_encode(array("responseCode" => "99", "message" => "product removed from cart", "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function removeQuantityFromAProduct($conn, $token, $user_id, $product_id, $quantityToRemove)
    {
        $status = '';
        $cart_id = $this->getUserCartId($conn, $user_id);

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // Check if the user has items in the cart
            $existingCartItem = $this->getCartItem($conn, $cart_id, $product_id);

            if (!$existingCartItem) {
                return json_encode(array("responseCode" => "02", "message" => "Product not in cart", "timestamp" => date('d-M-Y H:i:s')));
            }

            // Calculate the new quantity
            $newQuantity = max(0, $existingCartItem['product_quantity'] - $quantityToRemove);

            // Check if the quantity to be removed is greater than the current quantity in the cart
            if ($quantityToRemove > $existingCartItem['product_quantity']) {
                return json_encode(array("responseCode" => "03", "message" => "Quantity to remove is greater than current quantity in cart", "timestamp" => date('d-M-Y H:i:s')));
            }

            // Update the product quantity in the cart
            $result = $this->updateCartItemQuantity($conn, $cart_id, $product_id, $newQuantity);

            if ($result) {
                return json_encode(array("responseCode" => "00", "message" => "Quantity removed", "new_quantity" => $newQuantity, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                return json_encode(array("responseCode" => "99", "message" => "Error removing Quantity", "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    private function updateCartItemQuantity($conn, $cart_id, $product_id, $newQuantity)
    {
        $sql = "UPDATE `user_cart_item` SET `product_quantity` = '$newQuantity' WHERE `cart_id` = '$cart_id' AND `product_id` = '$product_id'";
        $result = mysqli_query($conn, $sql);

        return $result;
    }


    private function deleteCartItem($conn, $cart_id, $product_id)
    {
        $sql = "DELETE FROM `user_cart_item` WHERE `cart_id` = '$cart_id' AND `product_id` = '$product_id'";
        $result = mysqli_query($conn, $sql);

        return $result;
    }

    public function getCartItem($conn, $cart_id, $product_id)
    {
        $sql = "SELECT * FROM `user_cart_item` WHERE `cart_id` = '$cart_id' AND `product_id` = '$product_id'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }

        return null;
    }

    public function viewCartByUserID($conn, $token, $user_id)
    {
        $json = array();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // $sql = "SELECT CI.cart_id, CI.product_id, CI.product_quantity, CI.product_name, CI.price_at_purchase, UC.user_id FROM user_cart_item CI, user_cart UC WHERE CI.cart_id = UC.user_id AND UC.user_id = '$user_id'";
            $sql = "SELECT * FROM `user_cart_item` WHERE `user_id` = '$user_id' AND cart_status = 'Pending'";
            $sql = $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("responseCode" => "00", "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return json_encode($json, JSON_PRETTY_PRINT);
    }

    public function generateRefrenceID()
    {
        $uni = substr(str_shuffle(str_repeat("0123456789", 10)), 0, 10);

        return $uni;
    }

    public function calculateCartTotal($conn, $user_id)
    {
        $sql = "SELECT SUM(price_at_purchase) AS total_amount FROM user_cart_item WHERE user_id = '$user_id' AND cart_status = 'Pending'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return $row['total_amount'];
        } else {
            return 0; // Error in SQL query
        }
    }

    public function initiatePayment($conn, $user_id, $user_email)
    {
        // Calculate the total amount in the user's cart
        $amount = $this->calculateCartTotal($conn, $user_id);
        $reference = $this->generateRefrenceID();

        if ($amount <= 0) {
            return json_encode(array("status" => "error", "message" => "No items in the cart"));
        }

        $url = "https://api.paystack.co/transaction/initialize";

        $fields = [
            'email' => $user_email,
            'amount' => $amount * 100,
            'reference' => $reference,
        ];

        $fields_string = http_build_query($fields);

        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer '  . $this->secret_key,
            "Cache-Control: no-cache",
        ));

        //So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //execute post
        $result = curl_exec($ch);
        echo $result;
    }

    public function verifyTransaction($refrence_id)
    {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/{$refrence_id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer '  . $this->secret_key,
                "Cache-Control: no-cache",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            echo $response;
        }
    }
    // public function viewItemsInCart($conn, ) {

    //     $status = "";
    //     $json = array();
    //     if (empty($token)) {
    //         $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "product_id" => $product_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
    //     } else if ($this->validateToken($token) === "true") {
    //         $sql = "SELECT * FROM `products` WHERE `product_id` = '$product_id' ORDER BY stampdate DESC";
    //         $result = mysqli_query($conn, $sql);
    //         while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
    //             $json[] = $row;
    //         }
    //         $status = json_encode(array("responseCode" => "00", "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
    //     } else {
    //         $status = json_encode(array("responseCode" => "08", "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
    //     }

    //     $this->server_logs($status);
    //     return $status;

    // }
}

$portal = new PortalUtility();

// echo $portal->createUser($conn, "test","test","test","test","test","test","test","test","test");
// echo $portal->getAvailableBank();
// echo $portal->getCharge();
// echo $portal->createCustomer("akintolajohn41@gmail.com", "Olalekan", "08051022637");
// echo $portal->validateUsers($conn, "akintolajohn41@gmail.com", "12345");
// echo $portal->calculateCartTotal($conn, "1659004348");
// echo $portal->sendVerificationMailInternal($conn, 'akintolaolalekan2017@gmail.com', "http://localhost/justApp/justAppService/verify.php?email=akintolaolalekan2017@gmail.com&code=123456789");