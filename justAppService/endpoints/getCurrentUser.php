<?php
session_start();

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Example API endpoint to get user details by token
    $api_endpoint = "http://justApp/auth.php?token=$token";

    // Assuming the API requires an API key for authentication
    // $api_key = "your_api_key";

    // Set up cURL request
    $ch = curl_init($api_endpoint . "?token=$token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_HTTPHEADER, [
    //     "Authorization: Bearer $api_key",
    // ]);

    // Execute cURL request
    $response = curl_exec($ch);

    // Close cURL session
    curl_close($ch);

    // Decode the JSON response
    $userDetails = json_decode($response, true);

    // Check if the request was successful
    if ($userDetails && isset($userDetails['status']) && $userDetails['status'] === 'success') {
        // User details retrieved successfully
        $userData = $userDetails['data'];

        // Store user details in the session
        $_SESSION['login_user'] = $userData;

        // Return user details in JSON format
        header('Content-Type: application/json');
        echo json_encode($userData);
    } else {
        // Handle API error or invalid response
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error fetching user details from API']);
    }
}
