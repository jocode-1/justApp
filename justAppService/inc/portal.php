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

    public $secret_key = 'sk_live_62187b1831186d549921b1ae47de420c3324ddcf';
    // public $secret_key = 'sk_test_33c7dc2bc7152cee928b62f034722419e5ea6319';

    // SIGNUP USER 
    public function createUser($conn, $username, $user_email, $user_password, $user_phone_number)
    {
        $json = array();
        $user_id = $this->generataUserID();
        $user_referral_code = $this->generataReferralID();
        // $user_last_loggedIn = date('Y-m-d H:i:s');
        $passwordFormated = password_hash($user_password, PASSWORD_DEFAULT);
        $status = array();
        $sql = "INSERT INTO `users`(`user_id`, `username`, `user_email`, `user_password`, `user_phone_number`, `user_account_status`, `status`)
        VALUE('$user_id', '$username', '$user_email', '$passwordFormated', '$user_phone_number', 'Approved', 'Active')";

        $result = mysqli_query($conn, $sql);
        if ($result) {
            $rows = $this->fetch_user_details($conn, $user_id);
            $json[] = $rows;
            $status = array("status" => true, "message" => "success", "data" => $json);
            // $this->welcomNewUserMail($conn, $user_email, $username);
            $this->send_verification_email($conn, $user_email);
            $this->updateUserIP($conn, $user_email);
            // $this->createCustomer($user_email, $user_firstname, $user_phone_number);
            // $this->createAccount($user_email);
        } else {
            $rows = $this->fetch_user_details($conn, $user_id);
            $status = array("status" => false, "message" => "error", "data" => $json);
        }

        return json_encode($status, JSON_PRETTY_PRINT);
    }

    public function login_users($conn, $user_email, $user_password)
    {
        $status = "";
        $json = array();

        // Check if both email and password are provided
        if (!empty($user_email) && !empty($user_password)) {
            $user_array = $this->validateUsers($conn, $user_email, $user_password);

            if (!empty($user_array)) {
                // User exists, validate password
                unset($user_array['user_password']);
                $json[] = $user_array;

                $userId = $user_email . $user_password;
                $secret = 'sec!ReT423*&';
                $expiration = time() + 86400;
                $issuer = 'localhost';

                $token = Token::create($userId, $secret, $expiration, $issuer);

                $this->userLoginDate($conn, $user_email);
                $this->updateUserIP($conn, $user_email);

                $status =  json_encode(array(
                    "status" => true, "message" => "success", "data" => $json, "tokenType" => "Bearer", "expiresIn" => "3600", "accessToken" => $token, "timestamp" => date('d-M-Y H:i:s')
                ));
            } elseif ($user_array !== null) {

                $status = json_encode(array("status" => false, "message" => "wrongEmail", "timestamp" => date('d-M-Y H:i:s')));
            } else {
                // User does not exist or password is incorrect
                $status = json_encode(array("status" => false, "message" => "wrongPassword", "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            // Both email and password are required
            $status = json_encode(array("status" => false, "message" => "missingCredentials", "timestamp" => date('d-M-Y H:i:s')));
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
        $status = "";
        $token = $this->generate_verification_code();
        $expiration_time = time() + 60;

        $update_sql = "UPDATE `users` SET `token` = '$token', `expires_at` = '$expiration_time' WHERE `user_email` = '$user_email'";
        $result = mysqli_query($conn, $update_sql);

        if ($result) {
            $status = json_encode(array("status" => "success", "user_email" => $user_email));
            $this->sendVerificationMail($conn, $user_email, $token);
        } else {
            $status = json_encode(array("status" => "error", "email" => "null"));
        }

        $this->server_logs($status);
        return $status;
    }

    public function getUserTokenByEmail($conn, $user_email)
    {
        $sql = "SELECT * FROM `users` WHERE `user_email`  = '$user_email'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        return $row;
    }

    public function verify_token($conn, $user_email, $token)
    {
        $status = '';
        $sql = "SELECT `user_id` FROM `users` WHERE `user_email` = '$user_email' AND `token` = '$token'";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) == 1) {
            $row = $this->getUserTokenByEmail($conn, $user_email);
            $token_expires_at = $row['expires_at'];

            if (time() < $token_expires_at) {
                $sql = "UPDATE `users` SET `verified` = 'verfied' WHERE `user_email` = '$user_email'";
                $result = mysqli_query($conn, $sql);

                if ($result) {
                    $status = json_encode(array("responseCode" => "00", "status" => true, "message" => "Email Verified Successfully", "user_email" => $user_email, "token" => $token));
                } else {
                    $status = json_encode(array("responseCode" => "04", "status" => false, "message" => "Email not Verified", "user_email" => $user_email, "token" => $token));
                }
            } else {
                $status = json_encode(array("status" => false, "message" => "Token Expired", "token" => "null"));
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "Token does not exist", "token" => "null"));
        }

        return $status;
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
            $status = json_encode(array("status" => false, "message" => "invalid_token", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $passwordFormated = password_hash($user_password, PASSWORD_DEFAULT);
            $sql = "UPDATE `users` SET `user_password` = '$passwordFormated'  WHERE `user_email` = '$user_email'";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $status = json_encode(array("status" => true, "message" => "success", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("status" => false, "message" => "fail", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "user_email" => $user_email, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }
    public function update_user_profile($conn, $token, $user_id, $user_firstname, $user_lastname, $user_address, $user_email, $user_phone_number, $user_gender, $user_dob)
    {
        $status = "";
        if (empty($token)) {
            $status = json_encode(array("status" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // $passwordFormated = password_hash($user_password, PASSWORD_DEFAULT);
            $sql = "UPDATE `users` SET `user_firstname` = '$user_firstname', `user_lastname` = '$user_lastname', `user_address` = '$user_address', `user_email` = '$user_email', `user_phone_number` = '$user_phone_number', `user_gender`= '$user_gender', `user_dob` = '$user_dob', `status` = 'Updated'  WHERE `user_id` = '$user_id'";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $status = json_encode(array("status" => true, "message" => "User profile updated", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                $this->createUserCart($conn, $token, $user_id);
                $this->createCustomer($conn, $user_email, $user_firstname, $user_lastname, $user_phone_number);
                $this->createVirtualAccount($conn, $user_id);
                //$this->fetchCustomers($user_email);
            } else {
                $status = json_encode(array("status" => false, "message" => "fail", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function fetchUserDetails($conn, $token, $user_id)
    {
        $status = "";
        $json = array();

        if (empty($token)) {
            $status = json_encode(array("status" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // $sql = "SELECT CI.cart_id, CI.product_id, CI.product_quantity, CI.product_name, CI.price_at_purchase, UC.user_id FROM user_cart_item CI, user_cart UC WHERE CI.cart_id = UC.user_id AND UC.user_id = '$user_id'";
            $sql = "SELECT * FROM `users` WHERE `user_id` = '$user_id'";
            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("status" => true, "message" => "success",  "data" => $json, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return $status;
    }

    public function  fetchUserDetailsByEmail($conn, $token, $email_id)
    {
        $sql = "SELECT * FROM `users` WHERE `user_email` = '$email_id'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }

        return null;
    }

    public function upload_profile_picture($conn, $token, $user_id, $image_url)
    {
        $status = "";
        if (empty($token)) {
            $status = json_encode(array("status" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "UPDATE `users` SET `user_profile_picture` = '$image_url' WHERE `user_id` = '$user_id'";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $status = json_encode(array("status" => true, "message" => "Profile Image Uploaded", "profile_image" => $image_url, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("status" => false, "message" => "failed", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
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
    public function generatAddressID()
    {
        $uni = substr(str_shuffle(str_repeat("0123456789", 10)), 0, 10);

        return $uni;
    }

    public function generateOrderId()
    {

        return uniqid();
    }

    public function generataReferralID()
    {
        $uni = substr(str_shuffle(str_repeat("ABCDEFGHIJKLMNOPQRSTUVWXYZ", 6)), 0, 6);

        return $uni;
    }

    public function fetch_user_details($conn, $user_id)
    {
        $json  = array();
        $sql = "SELECT `user_id`, `customer_code`, `user_firstname`, `user_lastname`, `user_email`, `user_address`, `user_phone_number`, `user_gender`, `user_dob`, `user_profile_picture`, `user_last_loggedIn` FROM `users` WHERE user_id = '$user_id'";
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
        // $send_token = $this->send_verification_email($conn, $user_email);
        // $token = $send_token['token'];
        $username = $userMail['username'];
        $id = $userMail['user_id'];

        $body = file_get_contents($template);
        $body = str_replace('%user_id%', $id, $body);
        $body = str_replace('%username%', $username, $body);
        // $body = str_replace('%token%', $token, $body);

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
            $this->mailer_logs('Mail Sent Successfully To ' . $user_email . ' Username : ' . $username .  ' TIMESTAMP : ' . date('Y-m-d : h:m:s'));
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$e}";
            $this->mailer_logs('Mail Sending Error ' . $user_email . ' Username : ' . $username . ' TIMESTAMP : ' . date('Y-m-d : h:m:s'));
        }
    }

    public function sendVerificationMail($conn, $user_email, $token)
    {
        $template = 'http://localhost/justApp/justAppService/inc/templates/verificationMail.phtml';
        $userMail =  $this->getUserInfoByEmail($conn, $user_email);
        $username = $userMail['username'];
        $id = $userMail['user_id'];

        $body = file_get_contents($template);
        $body = str_replace('%user_id%', $id, $body);
        $body = str_replace('%username%', $username, $body);
        $body = str_replace('%token%', $token, $body);

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
            $this->mailer_logs('Verification Mail Sent Successfully To ' . $user_email . 'username : ' . $username . 'IMESTAMP : ' . date('Y-m-d : h:m:s'));
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$e}";
            $this->mailer_logs('Verification Mail Sending Error ' . $user_email . 'username : ' . $username . 'IMESTAMP : ' . date('Y-m-d : h:m:s'));
        }
    }



    public function addImageTOProduct($conn, $product_id, $image_url, $token)
    {
        $status = "";
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "product_id" => $product_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
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
        $json = array();

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token",  "staff_id" => $staff_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {

            $product_id = $this->create_product_id('PRO');
            $discountAmount = intval($product_discount_percentage) / 100 * intval($product_price);
            $taxAmount = intval($product_tax_percentage) / 100 * intval($product_price);

            $sql = "INSERT INTO `products`(`staff_id`, `product_id`, `brand_id`, `category_id`, `product_name`, `product_description`, `product_price`, `product_stock_quantity`, `product_weight`, `product_category`,
                    `product_brand`, `product_discount_percentage`, `product_tax_percentage`, `product_barcode`, `product_tags`, `product_warranty_information`, 
                   `product_warranty_type`, `product_warranty_duration`, `product_warranty_details`, `product_rating_count`, `product_image`, `product_status`, `status`) 
                   VALUES('$staff_id', '$product_id', '$brand_id', '$category_id', '$product_name', '$product_description', '$product_price', '$product_stock_quantity', '$product_weight', '$product_category',
    '$product_brand', '$discountAmount', '$taxAmount', '$product_barcode', '$product_tags', '$product_warranty_information', '$product_warranty_type', '$product_warranty_duration',
    '$product_warranty_details', '$product_rating_count', 'https://www.gstatic.com/webp/gallery3/1.sm.png', '$product_status', 'A')";
            $result = mysqli_query($conn, $sql);
            $json[] = $result;
            if ($result) {
                $status = json_encode(array("responseCode" => "00", "message" => "success", "data" => $json, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
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

    public function create_state_id($name)
    {
        $user = substr($name, 0, 3);
        $uni = substr(str_shuffle(str_repeat("0123456789", 4)), 0, 4);

        return $user . '' . $uni;
    }

    public function pickup_id($name)
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
        $uni = substr(str_shuffle(str_repeat("MNOPQRSTUVWXYZ0123456789abcdefghijklm", 6)), 0, 6);
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
    public function addUserAddress($conn, $token, $user_id, $state_id, $address_type, $address_name, $address_street, $address_city, $address_state, $address_zip_code, $address_country)
    {
        $status = '';
        $json = array();
        $address_id = $this->generatAddressID();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token",  "address_id" => $address_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "INSERT INTO `user_address`(`address_id`, `user_id`, `state_id`, `address_type`, `address_name`, `address_street`, `address_city`, `address_state`, `address_zip_code`, `address_country`, `address_status`) 
            VALUES('$address_id','$user_id', '$state_id', '$address_type', '$address_name', '$address_street','$address_city','$address_state','$address_zip_code','$address_country','A')";
            $result = mysqli_query($conn, $sql);

            if ($result) {
                // $json[] = $result;
                $status = json_encode(array("status" => true, "message" => "success", "address_id" => $address_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("status" => false, "message" => "fail",  "address_id" => $address_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token",  "address_id" => $address_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
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
            $status = json_encode(array("status" => true, "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
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
            $status = json_encode(array("status" => true, "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return json_encode($json, JSON_PRETTY_PRINT);
    }
    public function viewProductByProductID($conn, $product_id, $token)
    {
        $status = "";
        $json = array();
        if (empty($token)) {
            $status = json_encode(array("status" => false, "message" => "invalid_token", "product_id" => $product_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql  = "SELECT products.*, products_images.image_url FROM products INNER JOIN products_images ON products.product_id = products_images.product_id
        WHERE products.product_id = '$product_id' ORDER BY products.stampdate DESC";
            $result = mysqli_query($conn, $sql);
            // Temporary array to aggregate image URLs
            $tempImageUrls = array();

            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                // Add the current image_url to the temporary array
                $tempImageUrls[] = $row["image_url"];
                // Remove the individual "image_url" key from the row
                unset($row["image_url"]);
                // Add the modified row to the $json array
                $json[] = $row;
            }

            // Add the aggregated image URLs to each row in the $json array
            foreach ($json as &$row) {
                $row["image_urls"] = $tempImageUrls;
            }
            $status = json_encode(array("status" => true, "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
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
            $status = json_encode(array("status" => true, "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
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
            $status = json_encode(array("status" => true, "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
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
            $status = json_encode(array("status" => true, "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
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

    public function addItemToCart($conn, $token, $user_id, $product_id, $product_name, $product_quantity, $priceAtPurchase, $product_image)
    {
        $status = '';
        $cart_item_id = $this->create_cart_item_id();
        $cart_id = $this->getUserCartId($conn, $user_id);

        if (empty($token)) {
            $status = json_encode(array("status" => false, "message" => "invalid_token", "product_id" => $product_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {

            if (!$cart_id) {
                $this->createUserCart($conn, $token, $user_id);
            } else {
                // Check if the product is already in the cart
                $existingCartItem = $this->getCartItem($conn, $cart_id, $product_id);

                if ($existingCartItem) {
                    // Product exists in the cart, update quantity, update price
                    $newQuantity = intval($existingCartItem['product_quantity']) + $product_quantity;
                    $newPrice = $priceAtPurchase * $product_quantity + $existingCartItem['price_at_purchase'];
                    //                    $newPrice = intval($existingCartItem['price_at_purchase']) + $priceAtPurchase;
                    $sql = "UPDATE `user_cart_item` SET `product_quantity` = '$newQuantity', `price_at_purchase` = '$newPrice' WHERE `cart_id` = '$cart_id' AND `product_id` = '$product_id'";
                } else {
                    // Product is not in the cart, insert a new product
                    $sql = "INSERT INTO `user_cart_item` (`user_id`, `cart_item_id`, `cart_id`, `product_id`, `product_name`, `cart_status`, `product_quantity`, `price_at_purchase`, `product_image`) VALUES 
            ('$user_id', '$cart_item_id', '$cart_id', '$product_id', '$product_name', 'Pending', '$product_quantity', '$priceAtPurchase', '$product_image')";
                }
            }

            $result = mysqli_query($conn, $sql);

            if ($result) {
                $status =  json_encode(array("status" => true, "message" => "Product added to cart successfully", "cart_item_id" => $cart_item_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status =  json_encode(array("status" => false, "message" => "Error adding/updating product in cart", "cart_item_id" => $cart_item_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
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
                return json_encode(array("status" => true, "message" => "product not in cart", "timestamp" => date('d-M-Y H:i:s')));
            }
            // Remove the product from the cart
            $result = $this->deleteCartItem($conn, $cart_id, $product_id);
            if ($result) {
                // $row[] = $result;
                $status = json_encode(array("status" => true, "message" => "product removed from cart", "product_id" => $product_id, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status =  json_encode(array("status" => false, "message" => "product removed from cart", "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function removeQuantityFromAProduct($conn, $token, $user_id, $product_id, $quantityToRemove)
    {
        $status = '';
        $cart_id = $this->getUserCartId($conn, $user_id);

        if (empty($token)) {
            $status = json_encode(array("status" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // Check if the user has items in the cart
            $existingCartItem = $this->getCartItem($conn, $cart_id, $product_id);

            if (!$existingCartItem) {
                return json_encode(array("status" => false, "message" => "Product not in cart", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }

            // Calculate the new quantity
            $newQuantity = max(0, $existingCartItem['product_quantity'] - $quantityToRemove);

            // Check if the quantity to be removed is greater than the current quantity in the cart
            if ($quantityToRemove > $existingCartItem['product_quantity']) {
                return json_encode(array("status" => false, "message" => "Quantity to remove is greater than current quantity in cart", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }

            // Update the product quantity in the cart
            $result = $this->updateCartItemQuantity($conn, $cart_id, $product_id, $newQuantity);

            if ($result) {
                return json_encode(array("status" => true, "message" => "Quantity removed", "new_quantity" => $newQuantity, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                return json_encode(array("status" => false, "message" => "Error removing Quantity", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function addQuantityToProduct($conn, $token, $user_id, $product_id, $quantityToAdd)
    {
        $status = '';
        $cart_id = $this->getUserCartId($conn, $user_id);

        if (empty($token)) {
            return json_encode(array("status" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } elseif ($this->validateToken($token) !== "true") {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            // Check if the user has items in the cart
            $existingCartItem = $this->getCartItem($conn, $cart_id, $product_id);

            // If the product is not in the cart, add it with the specified quantity
            if (!$existingCartItem) {
                $result = $this->addProductToCart($conn, $cart_id, $product_id, $quantityToAdd);

                if ($result) {
                    return json_encode(array("status" => true, "message" => "Product added to cart", "new_quantity" => $quantityToAdd, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                } else {
                    return json_encode(array("status" => false, "message" => "Error adding product to cart", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                }
            }

            // Calculate the new quantity
            $newQuantity = $existingCartItem['product_quantity'] + $quantityToAdd;

            // Update the product quantity in the cart
            $result = $this->updateCartItemQuantity($conn, $cart_id, $product_id, $newQuantity);

            if ($result) {
                return json_encode(array("status" => true, "message" => "Quantity added", "new_quantity" => $newQuantity, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                return json_encode(array("status" => false, "message" => "Error adding Quantity", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        }

        $this->server_logs($status);
        return $status;
    }

    public function addProductToCart($conn, $cart_id, $product_id, $quantityToAdd)
    {
        $sql = "INSERT INTO `cart_items`(`cart_id`, `product_id`, `product_quantity`) VALUES ('$cart_id', '$product_id', '$quantityToAdd')";
        return mysqli_query($conn, $sql);
    }


    public function updateCartItemQuantity($conn, $cart_id, $product_id, $newQuantity)
    {
        $sql = "UPDATE `user_cart_item` SET `product_quantity` = '$newQuantity' WHERE `cart_id` = '$cart_id' AND `product_id` = '$product_id'";
        $result = mysqli_query($conn, $sql);

        return $result;
    }


    public function deleteCartItem($conn, $cart_id, $product_id)
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

    public function getCartItemByCartId($conn, $cart_id)
    {
        $sql = "SELECT * FROM `user_cart_item` WHERE `cart_id` = '$cart_id'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }

        return null;
    }

    public function viewCartByUserID($conn, $token, $user_id)
    {
        $status = "";
        $json = array();
        $totalAmount = $this->calculateCartTotal($conn, $user_id);

        if (empty($token)) {
            $status = json_encode(array("status" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // $sql = "SELECT CI.cart_id, CI.product_id, CI.product_quantity, CI.product_name, CI.price_at_purchase, UC.user_id FROM user_cart_item CI, user_cart UC WHERE CI.cart_id = UC.user_id AND UC.user_id = '$user_id'";
            $sql = "SELECT * FROM `user_cart_item` WHERE `user_id` = '$user_id' AND cart_status = 'Pending'";
            $sql = $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("status" => true, "message" => "success", "totalAmount" => $totalAmount, "token" => $token, "data" => $json,  "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return $status;
    }


    public function generateRefrenceID()
    {
        $uni = substr(str_shuffle(str_repeat("0123456789", 10)), 0, 10);

        return $uni;
    }

    public function getTotalCost($conn, $user_id)
    {
        $sql = "SELECT total_item_cost FROM `orders` WHERE `user_id` = '$user_id'";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }

        return null;
    }
    public function calculateCartTotal($conn, $user_id)
    {
        $sql = "SELECT SUM(price_at_purchase) AS total_amount FROM user_cart_item WHERE user_id = '$user_id'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return $row['total_amount'];
        } else {
            return 0;
        }
    }

    // public function initiatePayment($conn, $reference_id, $user_id, $user_email, $total_cost)
    // {
    //     // Calculate the total amount in the user's cart
    //     $total_cost = $this->getTotalCost($conn, $user_id);

    //     if ($total_cost <= 0) {
    //         return json_encode(array("status" => "error", "message" => "No items in the cart"));
    //     }

    //     $url = "https://api.paystack.co/transaction/initialize";

    //     $fields = [
    //         'email' => $user_email,
    //         'amount' => $total_cost['total_item_cost'] * 100,
    //         'reference' => $reference_id,
    //         'callback_url' => 'https://example.com/payment/callback'
    //     ];

    //     $fields_string = http_build_query($fields);

    //     $ch = curl_init();

    //     curl_setopt($ch, CURLOPT_URL, $url);
    //     curl_setopt($ch, CURLOPT_POST, true);
    //     curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    //     curl_setopt($ch, CURLOPT_HTTPHEADER, [
    //         'Authorization: Bearer ' . $this->secret_key,
    //         "Cache-Control: no-cache",
    //     ]);

    //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    //     $result = curl_exec($ch);

    //     echo $result;

    //     if ($result === false) {
    //         return json_encode(array("status" => "error", "message" => "Failed to initialize payment"));
    //     }

    //     $response = json_decode($result, true);
    //     echo json_encode($response);


    // }


    public function initiatePayment($conn, $reference_id, $user_id, $user_email, $total_cost) {
        // Calculate the total amount in the user's cart
        $total_cost = $this->getTotalCost($conn, $user_id);
    
        if ($total_cost <= 0) {
            return json_encode(array(
                "status" => false,
                "message" => "No items in the cart",
                "timestamp" => date('d-M-Y H:i:s')
            ));
        }
    
        $url = "https://api.paystack.co/transaction/initialize";
    
        $fields = [
            'email' => $user_email,
            'amount' => $total_cost['total_item_cost'] * 100,
            'eference' => $reference_id,
            'callback_url' => 'https://example.com/payment/callback'
        ];
    
        $fields_string = http_build_query($fields);
    
        $ch = curl_init();
    
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '. $this->secret_key,
            "Cache-Control: no-cache",
        ]);
    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
        $result = curl_exec($ch);
    
        if ($result === false) {
            return json_encode(array(
                "status" => false,
                "message" => "Failed to initialize payment",
                "timestamp" => date('d-M-Y H:i:s')
            ));
        }
    
        $response = json_decode($result, true);
        $response["status"] = true;
        $response["message"] = "Order paid using paystack";
        $response["payment_method"] = "Paystack";
        $response["transaction_ref"] = $response["data"]["reference"];
        $response["authorization_url"] = $response["data"]["authorization_url"];
        $response["timestamp"] = date('d-M-Y H:i:s');
    
        echo json_encode($response);
    }
    



    public function createVirtualAccount($conn, $user_id)
    {
        $customer = $this->fetch_user_details($conn, $user_id);
        $user_id = $customer['user_id'];
        $customer_code = $customer['customer_code'];

        $url = "https://api.paystack.co/dedicated_account";

        $fields = [
            "customer" => $customer_code,
            // "preferred_bank" => "wema-bank"
        ];

        $fields_string = http_build_query($fields);

        // Open connection
        $ch = curl_init();

        // Set the URL, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->secret_key,
            "Cache-Control: no-cache",
        ]);

        // So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute post
        $result = curl_exec($ch);

        // echo $result;

        if (curl_errno($ch)) {
            echo json_encode(array("status" => "failed", "message" => "Curl Error: " . curl_error($ch)));
        } else {
            // Decode the JSON response
            $response = json_decode($result, true);

            if ($response && isset($response['status']) && $response['status']) {
                $customer_details = $response['data']['customer'];
                $bank_details = $response['data']['bank'];
                $bank_name = mysqli_real_escape_string($conn, $bank_details['name']);

                $first_name = mysqli_real_escape_string($conn, $customer_details['first_name']);
                $last_name = mysqli_real_escape_string($conn, $customer_details['last_name']);
                $email = mysqli_real_escape_string($conn, $customer_details['email']);
                $currency = $response['data']['currency'];
                $account_name = $response['data']['account_name'];
                $account_number = $response['data']['account_number'];
                $phone = mysqli_real_escape_string($conn, $customer_details['phone']);
                $wallet_id = $this->generateRefrenceID();

                // Assuming you have a users table with appropriate columns
                $update_query = "INSERT INTO `user_wallet`(`wallet_id`, `user_id`, `customer_code`, `account_number`, `account_name`, `bank_name`, `first_name`, `last_name`, `user_email`, `balance`, `wallet_status`, `currency`)
                            VALUES ('$wallet_id', '$user_id', '$customer_code', '$account_number', '$account_name', '$bank_name', '$first_name', '$last_name', '$email', '0', 'Active', '$currency')";

                // Execute the update query
                $result = mysqli_query($conn, $update_query);

                if ($result) {
                    echo json_encode(array("status" => "success", "message" => "User Wallet Created Successfully"));
                } else {
                    echo json_encode(array("status" => "failed", "message" => "User Wallet Failed"));
                }
            } else {
                // Handle the case where the Paystack API request was not successful
                echo json_encode(array("status" => "failed", "message" => "Paystack API request failed"));
            }
        }

        curl_close($ch);
    }


    public function createCustomer($conn, $user_email, $user_firstname, $user_lastname, $user_phone_number)
    {

        $url = "https://api.paystack.co/customer";

        $fields = [
            "email" => $user_email,
            "first_name" => $user_firstname,
            "last_name" => $user_lastname,
            "phone" => $user_phone_number
        ];

        $fields_string = http_build_query($fields);

        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->secret_key,
            "Cache-Control: no-cache",
        ));

        //So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //execute post
        $result = curl_exec($ch);
        // echo $result;

        $response = json_decode($result);

        if ($response->status == "true") {
            $customer_code = $response->data->customer_code;

            $query = "UPDATE users SET customer_code = '$customer_code' WHERE user_email = '$user_email'";
            $result = mysqli_query($conn, $query);

            if ($result) {
                return json_encode(array("status" => "success", "message" => "Customer Code Updated Successfully"));
            } else {
                return json_encode(array("status" => "failed", "message" => "Customer Code Not Updated"));
            }
        }
    }

    public function fetchCustomers($user_email)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/customer/$user_email",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $this->secret_key,
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


    // public function walletWebhook() {

    // }

    public function checkReferenceIdExist($conn, $paymentReference)
    {
        // Perform a database query to check if the payment reference exists
        // Example query: "SELECT COUNT(*) FROM orders WHERE paystack_reference = '$paymentReference'"
        $sql = "SELECT COUNT(*) as count FROM orders WHERE reference_id = '$paymentReference'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $count = $row['count'];

            return $count > 0; // Return true if count is greater than 0, else return false
        }

        return false; // Return false in case of an error
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

    public function validatePaystackSignature()
    {
        // Get the Paystack signature from the headers
        $paystackSignature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'];

        // Get the raw request payload
        $payload = @file_get_contents("php://input");

        // Calculate the expected signature
        $expectedSignature = hash_hmac('sha512', $payload, $this->secret_key);

        // Compare the signatures
        return hash_equals($expectedSignature, $paystackSignature);
    }


    public function getUserHomeAddress($conn, $token, $user_id)
    {
        $status = "";
        $json = array();
        if (empty($token)) {
            $status = json_encode(array("status" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "SELECT * FROM `user_address` WHERE `user_id` = '$user_id' AND `address_type` = 'Home'";
            $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("status" => true, "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return $status;
    }

    public function fetchPickupStationByID($conn, $token, $state_id)
    {
        $status = "";
        $json = array();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "SELECT * FROM `pickup_stations` WHERE `state_id` = '$state_id'";
            $result = mysqli_query($conn, $sql);
            if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                    $row['shipping_price'] = intval($row['shipping_price']);
                    $json[] = $row;
                }
                $status = array("status" => true, "message" => "success", "data" => $json, "token" => $token, "timestamp" => date('d-M-Y H:i:s'));
            } else {
                $status = json_encode(array("status" => false, "message" => "State has no Pickup Station", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return json_encode($status);
    }


    public function getUserBillingAddress($conn, $token, $user_id)
    {
        $status = "";
        $billingAddress = array();

        if (empty($token)) {
            $status = json_encode(array("status" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "SELECT * FROM `user_address` WHERE `user_id` = '$user_id' AND `address_type` = 'Billing'";
            $result = mysqli_query($conn, $sql);

            if (mysqli_num_rows($result) > 0) {
                $billingAddress = mysqli_fetch_array($result, MYSQLI_ASSOC);
                $status = json_encode(array("status" => true, "message" => "success", "token" => $token, "data" => $billingAddress, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("status" => false, "message" => "no_billing_address", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $billingAddress;
    }


    //    customer address
    // payment method
    //products details
    //delivery details ... pickup station ... delivery time ...
    //pickup station


    public function updateOrderStatus($conn, $reference, $amountInNaira, $customer_code, $status)
    {
        $sql = "UPDATE `orders` SET `total_amount` = '$amountInNaira', `customer_code` = '$customer_code', `payment_status` = 'paid', `payment_notification` = 'success' WHERE `reference_id` = '$reference'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            return true;
        } else {
            // error_log("Error updating order status: " . mysqli_error($conn));
            return false;
        }
    }

    public function updateCartItem($conn, $user_id)
    {
        $sql = "UPDATE `user_cart_item` SET `cart_status` = 'Paid' WHERE `user_id` = '$user_id'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            return true;
        } else {
            // error_log("Error updating order status: " . mysqli_error($conn));
            return false;
        }
    }

    public function updateWallet($conn, $customer_id, $amountInNaira, $status)
    {
        $sql = "UPDATE `user_wallet` SET `balance` = '$amountInNaira', `status` = '$status' WHERE `customer_id` = '$customer_id'";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            return true;
        } else {
            // error_log("Error updating order status: " . mysqli_error($conn));
            return false;
        }
    }

    public function deleteAllCartItems($conn, $token, $user_id)
    {
        // $json = array();
        $status = "";

        if (empty($token)) {
            $status = json_encode(array("success" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } elseif ($this->validateToken($token) === "true") {
            // Check if the user has any cart items
            $sqlCheckCartItems = "SELECT * FROM `user_cart_item` WHERE `user_id` = '$user_id'";
            $resultCheckCartItems = mysqli_query($conn, $sqlCheckCartItems);

            if ($resultCheckCartItems && mysqli_num_rows($resultCheckCartItems) > 0) {
                // Delete all cart items for the user
                $sqlDeleteAllCartItems = "DELETE FROM `user_cart_item` WHERE `user_id` = '$user_id'";
                $resultDeleteAllCartItems = mysqli_query($conn, $sqlDeleteAllCartItems);

                if ($resultDeleteAllCartItems) {
                    $status = json_encode(array("success" => true, "message" => "success", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                } else {
                    $status = json_encode(array("success" => false, "message" => "error deleting cart items", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                }
            } else {
                $status = json_encode(array("success" => false, "message" => "no cart items found", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("success" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function insertsStates($conn, $token, $state_name)
    {
        $status = '';
        $state_id = $this->create_state_id('STA');

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token",  "state_id" => $state_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "INSERT INTO `states`(`state_id`, `state_name`) VALUES('$state_id','$state_name')";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $status = json_encode(array("responseCode" => "00", "message" => "success", "state_id" => $state_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("responseCode" => "04", "message" => "fail",  "state_id" => $state_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token",  "state_id" => $state_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }

    public function viewAllStates($conn, $token)
    {

        $json = array();
        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            // $sql = "SELECT CI.cart_id, CI.product_id, CI.product_quantity, CI.product_name, CI.price_at_purchase, UC.user_id FROM user_cart_item CI, user_cart UC WHERE CI.cart_id = UC.user_id AND UC.user_id = '$user_id'";
            $sql = "SELECT * FROM `states`";
            $sql = $result = mysqli_query($conn, $sql);
            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $json[] = $row;
            }
            $status = json_encode(array("status" => true, "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
        $this->server_logs($status);
        return json_encode($json, JSON_PRETTY_PRINT);
    }

    public function createPickupStation($conn, $token, $state_id, $pickup_address, $shipping_price)
    {
        $status = '';
        $pickup_id = $this->pickup_id('PICK');

        if (empty($token)) {
            $status = json_encode(array("responseCode" => "08", "message" => "invalid_token",  "pickup_id" => $pickup_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "INSERT INTO `pickup_stations`(`pickup_id` , `state_id`, `pickup_address`, `shipping_price`)
             VALUES('$pickup_id', '$state_id','$pickup_address', '$shipping_price')";
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $status = json_encode(array("responseCode" => "00", "message" => "success", "pickup_id" => $pickup_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("responseCode" => "04", "message" => "fail",  "pickup_id" => $pickup_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
            }
        } else {
            $status = json_encode(array("responseCode" => "08", "message" => "expired_token",  "pickup_id" => $pickup_id, "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }

        $this->server_logs($status);
        return $status;
    }


    public function fetchUserWallet($conn, $token, $user_id)
    {
        $status = "";
        $json = array();
        if (empty($token)) {
            $status = json_encode(array("status" => false, "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        } else if ($this->validateToken($token) === "true") {
            $sql = "SELECT * FROM `user_wallet` WHERE `user_id` = '$user_id'";
            $result = mysqli_query($conn, $sql);

            if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                    $json[] = $row;
                }
                $status = json_encode(array("status" => true, "message" => "success", "token" => $token, "data" => $json, "timestamp" => date('d-M-Y H:i:s')));
            } else {
                $status = json_encode(array("status" => false, "message" => "No Available Wallet", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
                // Add a message or handle the case where no wallet is available
            }
        } else {
            $status = json_encode(array("status" => false, "message" => "expired_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
        }
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


    public function fetch_user_wallet_balance($conn, $user_id)
    {
        $sql = "SELECT `balance` FROM `user_wallet` WHERE `user_id` = '$user_id'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_assoc($result);
        return $row['balance'];
    }

    public function orderConfirmation($conn, $token, $user_id, $cart_id, $pickup_address, $pickup_fee, $payment_method)
    {
        $order_id = $this->generateOrderId();
        $reference_id = $this->generateRefrenceID();
        $transaction_id = $this->generateRefrenceID();

        $user_details = $this->fetch_user_details($conn, $user_id);
        $user_email = $user_details['user_email'];

        if (empty($token) || $this->validateToken($token) !== "true") {
            return json_encode(array(
                "status" => false,
                "message" => "invalid_token",
                "token" => $token,
                "timestamp" => date('d-M-Y H:i:s')
            ));
        } else {
            $cart_total = $this->calculateCartTotal($conn, $user_id);
            $total_item_cost = $cart_total + $pickup_fee;

            $sql = "INSERT INTO `orders`(`order_id`, `reference_id`, `user_id`, `cart_id`, `order_date`, `total_amount`, `total_item_cost`, `pickup_fees`, `status`, `pickup_station`, `payment_method`, `payment_status`, `shipping_status`) VALUES ('$order_id', '$reference_id', '$user_id', '$cart_id', NOW(), '$cart_total', '$total_item_cost', '$pickup_fee', 'A', '$pickup_address', '$payment_method', 'Pending', 'Not Shipped')";

            $result = mysqli_query($conn, $sql);

            if ($result) {
                $paymentResult = array(
                    "status" => true,
                    "message" => "Order created successfully",
                    "timestamp" => date('d-M-Y H:i:s')
                );

                if ($payment_method === "paystack") {
                    $paymentResult = $this->initiatePayment($conn, $reference_id, $user_id, $user_email, $total_item_cost);
                    $this->logTransaction($conn, $transaction_id, $user_id, $reference_id, "Purchased Items using paystack", "-$total_item_cost", "paystack", "Successful");
                    $this->updateCartItem($conn, $user_id);
                   
                } else if ($payment_method === "wallet") {
                    $paymentResult = $this->handleWalletPayment($conn, $user_id, $total_item_cost, $order_id);
                    $this->logTransaction($conn, $transaction_id, $user_id, $reference_id, "Purchased Items using wallet", "-$total_item_cost", "wallet", "Successful");
                    $this->updateCartItem($conn, $user_id);
                    
                } else {
                    $paymentResult = json_encode(array(
                        "status" => false,
                        "message" => "Invalid payment method",
                        "timestamp" => date('d-M-Y H:i:s')
                    ));
                }

                return $paymentResult;
            } else {
                $paymentResult = array(
                    "status" => false,
                    "message" => "Failed to create order",
                    "timestamp" => date('d-M-Y H:i:s')
                );
                $this->logTransaction($conn, $transaction_id, $user_id, $reference_id, "Purchased Items using wallet", "$total_item_cost", "wallet", "Failed");
                return $paymentResult;
            }
        }
    }

    // public function orderConfirmation($conn, $token, $user_id, $cart_id, $pickup_address, $pickup_fee, $payment_method)
    // {
    //     $status = "";
    //     $order_id = $this->generateOrderId();
    //     $reference_id = $this->generateRefrenceID();

    //     $user_details = $this->fetch_user_details($conn, $user_id);
    //     $user_email = $user_details['user_email'];

    //     if (empty($token) || $this->validateToken($token) !== "true") {
    //         $status = json_encode(array("responseCode" => "08", "message" => "invalid_token", "token" => $token, "timestamp" => date('d-M-Y H:i:s')));
    //     } else {
    //         $cart_total = $this->calculateCartTotal($conn, $user_id);
    //         $total_item_cost = $cart_total + $pickup_fee;

    //         $sql = "INSERT INTO `orders`(`order_id`, `reference_id`, `user_id`, `cart_id`, `order_date`, `total_amount`, `total_item_cost`, `pickup_fees`, `status`, `pickup_station`, `payment_method`, `payment_status`, `shipping_status`) VALUES ('$order_id', '$reference_id', '$user_id', '$cart_id', NOW(), '$cart_total', '$total_item_cost', '$pickup_fee', 'A', '$pickup_address', '$payment_method', 'Pending', 'Not Shipped')";

    //         $result = mysqli_query($conn, $sql);

    //         if ($result) {
    //             if ($payment_method === "paystack") {
    //                 $paymentResult = $this->initiatePayment($conn, $reference_id, $user_id, $user_email, $total_item_cost);
    //             } else if ($payment_method === "wallet") {
    //                 $paymentResult = $this->handleWalletPayment($conn, $user_id, $total_item_cost, $order_id);
    //             } else {
    //                 $paymentResult = json_encode(array("status" => false, "message" => "Invalid payment method", "timestamp" => date('d-M-Y H:i:s')));
    //             }
    //             return $paymentResult;
    //         } else {
    //             $status = json_encode(array("status" => false, "message" => "Failed to create order", "timestamp" => date('d-M-Y H:i:s')));
    //         }
    //     }

    //     $this->server_logs($status);
    //     return $status;
    // }

    public function handleWalletPayment($conn, $user_id, $total_amount, $order_id)
    {
        $wallet_balance = $this->fetch_user_wallet_balance($conn, $user_id);

        if ($wallet_balance >= $total_amount) {
            $sql = "UPDATE `user_wallet` SET `balance` = `balance` - '$total_amount' WHERE `user_id` = '$user_id'";
            mysqli_query($conn, $sql);

            $sql = "UPDATE `orders` SET `payment_status` = 'Paid', `transaction_ref` = 'Wallet' WHERE `order_id` = '$order_id'";
            mysqli_query($conn, $sql);

            $paymentResult = json_encode(array("status" => true, "message" => "Order paid using wallet", "payment_method" => "Wallet", "transaction_ref" => "Wallet", "timestamp" => date('d-M-Y H:i:s')));
        } else {
            $paymentResult = json_encode(array("status" => false, "message" => "Insufficient wallet balance", "timestamp" => date('d-M-Y H:i:s')));
        }

        return $paymentResult;
    }

    public function logTransaction($conn, $transaction_id, $user_id, $refrence_id, $transaction_type, $amount, $payment_method, $status)
    {
        $sql = "INSERT INTO `transaction_history` (`transaction_id`, `user_id`, `refrence_id`, `transaction_type`, `amount`, `payment_method`, `status`, `date`) VALUES 
        ('$transaction_id', '$user_id', '$refrence_id', '$transaction_type', '$amount', '$payment_method', '$status', NOW())";
        $result = mysqli_query($conn, $sql);

        if ($result) {
            return true; // Transaction logged successfully
        } else {
            return false; // Error logging transaction
        }
    }

    
}

$portal = new PortalUtility();

// echo $portal->createUser($conn, "test","test","test","test","test","test","test","test","test");
// echo $portal->getAvailableBank();
// echo $portal->getCharge();
// echo $portal->createCustomer("akintolajohn41@gmail.com", "Olalekan", "08051022637");
// echo $portal->validateUsers($conn, "akintolajohn41@gmail.com", "12345");
// echo $portal->calculateCartTotal($conn, "1659004348");
// echo $portal->sendVerificationMailInternal($conn, 'akintolaolalekan2017@gmail.com', '123456');
// echo $portal->send_verification_email($conn, 'akintolaolalekan2017@gmail.com');
// echo $portal->checkReferenceIdExist($conn, '3047621618');
// echo $portal->initiatePayment($conn, '3490937564', '3047621618', 'akintolaolalekan2017@gmail.com');
// echo $portal->createVirtualAccount("3490937564");
// echo $portal->deductFromWallet($conn, "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoiYWtpbnRvbGFvbGFsZWthbjIwMTdAZ21haWwuY29tMTIzNDUiLCJleHAiOjE3MDcxOTYxMTIsImlzcyI6ImxvY2FsaG9zdCIsImlhdCI6MTcwNzEwOTcxMn0.dqUv4jw5SQ2lFgUGiAD7kY-x6fezO156kZFkP1mKv7A", "3490937564", "100000");
// echo $portal->fetchPickupStationByStateId($conn, "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoiYWtpbnRvbGFvbGFsZWthbjIwMTdAZ21haWwuY29tMTIzNDUiLCJleHAiOjE3MDc0OTM0MTAsImlzcyI6ImxvY2FsaG9zdCIsImlhdCI6MTcwNzQwNzAxMH0.GXfwf5oKcrGIFl3sbM7QkxYMumZlnxWTfIk1NUEXudM", "STA4979");