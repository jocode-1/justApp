<?php
session_start();

if(isset($_GET['token'])){
    
    $token = $_GET['token'];
    $user_id = $_GET['user_id'];
    $email = $_GET['user_email']; 
    $username = $_GET['username']; 
    // $company_name = $_GET['company_name'];
   // $password = $_GET['password_status'];

   $userDetails = array("agent_id"=>$user_id,"token"=>$token,"user_email"=>$email,"username"=>$username);
   // var_dump($userDetails);
    $_SESSION['login_user'] = $userDetails;
    header("Location: dashboard");
    //exit();
}
