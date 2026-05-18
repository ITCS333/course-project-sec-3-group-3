<?php
/**
 * Authentication Handler for Login Form
 * * This PHP script handles user authentication via POST requests from the Fetch API.
 * It validates credentials against a MySQL database using PDO,
 * creates sessions, and returns JSON responses.
 */

// --- Session Management ---
// TODO: Start a PHP session using session_start()
// This must be called before any output is sent to the browser
// Sessions allow us to store user data across multiple pages
session_start();

// --- Set Response Headers ---
// TODO: Set the Content-Type header to 'application/json'
// This tells the browser that we're sending JSON data back
header('Content-Type: application/json');

// TODO: (Optional) Set CORS headers if your frontend and backend are on different domains
// You'll need headers for Access-Control-Allow-Origin, Methods, and Headers

// --- Check Request Method ---
// TODO: Verify that the request method is POST
// Use the $_SERVER superglobal to check the REQUEST_METHOD
// If the request is not POST, return an error response and exit
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
// The Fetch API sends JSON data in the request body
// Use file_get_contents with 'php://input' to read the raw request body
$raw_post_data = file_get_contents('php://input');

// TODO: Decode the JSON data into a PHP associative array
// Use json_decode with the second parameter set to true
$decoded_data = json_decode($raw_post_data, true);

// TODO: Extract the email and password from the decoded data
// Check if both 'email' and 'password' keys exist in the array
// If either is missing, return an error response and exit
if (!isset($decoded_data['email']) || !isset($decoded_data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing email or password'
    ]);
    exit;
}

// TODO: Store the email and password in variables
// Trim any whitespace from the email
$email = trim($decoded_data['email']);
$password = $decoded_data['password'];

// --- Server-Side Validation (Optional but Recommended) ---
// TODO: Validate the email format on the server side
// Use the appropriate filter function for email validation
// If invalid, return an error response and exit
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

// TODO: Validate the password length (minimum 8 characters)
// If invalid, return an error response and exit
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email or password'
    ]);
    exit;
}

// --- Database Connection ---
// TODO: Get the database connection using the provided function
// Assume getDBConnection() returns a PDO instance with error mode set to exception
// The function is defined elsewhere (e.g., in a config file or db.php)

// TODO: Wrap database operations in a try-catch block to handle PDO exceptions
// This ensures you can return a proper JSON error response if something goes wrong
try {
    // Including the db.php file located inside the common folder
    require_once '../../common/db.php';

    // --- Prepare SQL Query ---
    // TODO: Write a SQL SELECT query to find the user by email
    // Select the following columns: id, name, email, password, is_admin
    // Use a WHERE clause to filter by email
    // IMPORTANT: Use a placeholder (? or :email) for the email value
    // This prevents SQL injection attacks

    // --- Prepare the Statement ---
    // TODO: Prepare the SQL statement using the PDO prepare method
    // Store the result in a variable
    // Prepared statements protect against SQL injection
    $stmt = $pdo->prepare("SELECT id, name, email, password, is_admin FROM users WHERE email = ?");

    // --- Execute the Query ---
    // TODO: Execute the prepared statement with the email parameter
    // Bind the email value to the placeholder
    $stmt->execute([$email]);

    // --- Fetch User Data ---
    // TODO: Fetch the user record from the database
    // Use the fetch method with PDO::FETCH_ASSOC
    // This returns an associative array of the user data, or false if no user found
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Verify User Exists and Password Matches ---
    // TODO: Check if a user was found
    // The fetch method returns false if no record matches

    // TODO: If user exists, verify the password
    // Use password_verify() to compare the submitted password with the hashed password from the database
    // This function returns true if they match, false otherwise
    //
    // NOTE: This assumes passwords are stored as hashes using password_hash() with PASSWORD_DEFAULT
    // (see database seed data). Never store passwords in plain text!
    if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {

        // --- Handle Successful Authentication ---
        // TODO: If password verification succeeds:
        
        // TODO: Store user information in session variables
        // Store: user_id, user_name, user_email, is_admin, logged_in
        // DO NOT store the password in the session!
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['logged_in'] = true;

        // TODO: Prepare a success response array
        // Include:
        // - 'success' => true
        // - 'message' => 'Login successful'
        // - 'user' => array with safe user details (id, name, email, is_admin)
        //
        // IMPORTANT: Do NOT include the password in the response
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
        
        // TODO: Exit the script to prevent further execution
        exit;

    } else {
        // --- Handle Failed Authentication ---
        // TODO: If user doesn't exist OR password verification fails:
        
        // TODO: Prepare an error response array
        // Include:
        // - 'success' => false
        // - 'message' => 'Invalid email or password'
        //
        // SECURITY NOTE: Don't specify whether email or password was wrong
        // This prevents attackers from enumerating valid email addresses
        $error_response = [
            'success' => false,
            'message' => 'Invalid email or password'
        ];

        // TODO: Encode the error response as JSON and echo it
        http_response_code(401);
        echo json_encode($error_response);
        
        // TODO: Exit the script
        exit;
    }

// TODO: Catch PDO exceptions in the catch block
// Catch PDOException type
} catch (PDOException $e) {
    // TODO: Log the error for debugging
    // Use error_log() to write the error message to the server error log
    error_log($e->getMessage());
    
    // TODO: Return a generic error message to the client
    // DON'T expose database details to the user for security reasons
    // Return a JSON response with success false and a generic message
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal server error occurred'
    ]);
    
    // TODO: Exit the script
    exit;
}

// --- End of Script ---
?>