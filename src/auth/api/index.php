<?php
/**
 * Authentication Handler for Login Form
 * * This PHP script handles user authentication via POST requests from the Fetch API.
 * It validates credentials against a MySQL database using PDO,
 * creates sessions, and returns JSON responses.
 */

// --- Session Management ---
// TODO: Start a PHP session using session_start()
session_start();

// --- Set Response Headers ---
// TODO: Set the Content-Type header to 'application/json'
header('Content-Type: application/json');

// --- Check Request Method ---
// TODO: Verify that the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

// --- Get POST Data ---
// TODO: Retrieve the raw POST data
$raw_post_data = file_get_contents('php://input');

// TODO: Decode the JSON data into a PHP associative array
$decoded_data = json_decode($raw_post_data, true);

// TODO: Extract the email and password from the decoded data
if (!isset($decoded_data['email']) || !isset($decoded_data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing email or password'
    ]);
    exit;
}

// TODO: Store the email and password in variables
$email = trim($decoded_data['email']);
$password = $decoded_data['password'];

// --- Server-Side Validation ---
// TODO: Validate the email format on the server side
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

// TODO: Validate the password length (minimum 8 characters)
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email or password'
    ]);
    exit;
}

// --- Database Connection ---
try {
    // Including the db.php file located inside the common folder
    require_once '../../common/db.php';

    // --- Prepare SQL Query ---
    // TODO: Write a SQL SELECT query to find the user by email
    // --- Prepare the Statement ---
    $stmt = $pdo->prepare("SELECT id, name, email, password, is_admin FROM users WHERE email = ?");

    // --- Execute the Query ---
    // TODO: Execute the prepared statement with the email parameter
    $stmt->execute([$email]);

    // --- Fetch User Data ---
    // TODO: Fetch the user record from the database
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Verify User Exists and Password Matches ---
    if ($user && password_verify($password, $user['password'])) {

        // --- Handle Successful Authentication ---
        // TODO: Store user information in session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['logged_in'] = true;

        // TODO: Prepare a success response array
        $success_response = [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => $user['is_admin']
            ]
        ];

        // TODO: Encode the response array as JSON and echo it
        http_response_code(200);
        echo json_encode($success_response);
        exit;

    } else {
        // --- Handle Failed Authentication ---
        // TODO: Prepare an error response array
        $error_response = [
            'success' => false,
            'message' => 'Invalid email or password'
        ];

        // TODO: Encode the error response as JSON and echo it
        http_response_code(401);
        echo json_encode($error_response);
        exit;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal server error occurred'
    ]);
    exit;
}
?>