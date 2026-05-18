<?php
/**
 * User Management API
 *
 * A RESTful API that handles all CRUD operations for user management
 * and password changes for the Admin Portal.
 * Uses PDO to interact with a MySQL database.
 *
 * Database Table (ground truth: see schema.sql):
 * Table: users
 * Columns:
 * - id         (INT UNSIGNED, PRIMARY KEY, AUTO_INCREMENT)
 * - name       (VARCHAR(100), NOT NULL)
 * - email      (VARCHAR(100), NOT NULL, UNIQUE)
 * - password   (VARCHAR(255), NOT NULL) - bcrypt hash
 * - is_admin   (TINYINT(1), NOT NULL, DEFAULT 0)
 * - created_at (TIMESTAMP, NOT NULL, DEFAULT CURRENT_TIMESTAMP)
 *
 * HTTP Methods Supported:
 * - GET    : Retrieve all users (with optional search/sort query params)
 * - GET    : Retrieve a single user by id (?id=1)
 * - POST   : Create a new user
 * - POST   : Change a user's password (?action=change_password)
 * - PUT    : Update an existing user's name, email, or is_admin
 * - DELETE : Delete a user by id (?id=1)
 *
 * Response Format: JSON
 * All responses have the shape:
 * { "success": true,  "data": ... }
 * { "success": false, "message": "..." }
 */


// TODO: Set headers for JSON response and CORS.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// TODO: Handle preflight OPTIONS request.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// TODO: Include the database connection file.
require_once '../../common/db.php';

// TODO: Get the PDO database connection by calling getDBConnection().
// Fallback directly to $pdo if getDBConnection() is not defined in shared workspace
$db = function_exists('getDBConnection') ? getDBConnection() : $pdo;

// TODO: Read the HTTP request method from $_SERVER['REQUEST_METHOD'].
$method = $_SERVER['REQUEST_METHOD'];

// TODO: Read the raw request body for POST and PUT requests.
$raw_body = file_get_contents('php://input');
$data = json_decode($raw_body, true) ?? [];

// TODO: Read query string parameters.
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';
$order = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';


/**
 * Function: Get all users, or search/filter users.
 */
function getUsers($db) {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : '';
    $order = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';

    // Whitelist check
    $allowed_sorts = ['name', 'email', 'is_admin'];
    if (!in_array($sort, $allowed_sorts)) {
        $sort = '';
    }
    if ($order !== 'asc' && $order !== 'desc') {
        $order = 'asc';
    }

    $sql = "SELECT id, name, email, is_admin, created_at FROM users";
    $where_clauses = [];
    $params = [];

    if ($search !== '') {
        $where_clauses[] = "(name LIKE :search OR email LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    if ($sort !== '') {
        $sql .= " ORDER BY " . $sort . " " . strtoupper($order);
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse($users_list, 200);
}


/**
 * Function: Get a single user by primary key.
 */
function getUserById($db, $id) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_record) {
        sendResponse("User not found", 404);
    } else {
        sendResponse($user_record, 200);
    }
}


/**
 * Function: Create a new user.
 */
function createUser($db, $data) {
    if (!isset($data['name']) || !isset($data['email']) || !isset($data['password']) ||
        empty(trim($data['name'])) || empty(trim($data['email'])) || empty(trim($data['password']))) {
        sendResponse("Please fill out all required fields.", 400);
    }

    $name = trim($data['name']);
    $email = trim($data['email']);
    $password = trim($data['password']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse("Invalid email format", 400);
    }

    if (strlen($password) < 8) {
        sendResponse("Password must be at least 8 characters.", 400);
    }

    // Check unique constraint
    $check_stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $check_stmt->execute([':email' => $email]);
    if ($check_stmt->fetch()) {
        sendResponse("Email already exists", 409);
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $is_admin = isset($data['is_admin']) ? intval($data['is_admin']) : 0;
    if ($is_admin !== 0 && $is_admin !== 1) {
        $is_admin = 0;
    }

    $stmt = $db->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)");
    $success = $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => $hashed_password,
        ':is_admin' => $is_admin
    ]);

    if ($success) {
        sendResponse(['id' => $db->lastInsertId()], 201);
    } else {
        sendResponse("Internal server error", 500);
    }
}


/**
 * Function: Update an existing user.
 */
function updateUser($db, $data) {
    if (!isset($data['id']) || empty($data['id'])) {
        sendResponse("Missing user id", 400);
    }

    $user_id = intval($data['id']);

    $check_stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $check_stmt->execute([':id' => $user_id]);
    if (!$check_stmt->fetch()) {
        sendResponse("User not found", 404);
    }

    $update_fields = [];
    $params = [':id' => $user_id];

    if (isset($data['name'])) {
        $update_fields[] = "name = :name";
        $params[':name'] = trim($data['name']);
    }

    if (isset($data['email'])) {
        $email = trim($data['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse("Invalid email format", 400);
        }

        $email_stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $email_stmt->execute([':email' => $email, ':id' => $user_id]);
        if ($email_stmt->fetch()) {
            sendResponse("Email already in use", 409);
        }

        $update_fields[] = "email = :email";
        $params[':email'] = $email;
    }

    if (isset($data['is_admin'])) {
        $is_admin = intval($data['is_admin']);
        if ($is_admin === 0 || $is_admin === 1) {
            $update_fields[] = "is_admin = :is_admin";
            $params[':is_admin'] = $is_admin;
        }
    }

    if (empty($update_fields)) {
        sendResponse("No modifications provided", 200);
    }

    $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    
    if ($stmt->execute($params)) {
        sendResponse("User updated successfully!", 200);
    } else {
        sendResponse("Internal server error", 500);
    }
}


/**
 * Function: Delete a user by primary key.
 */
function deleteUser($db, $id) {
    if (!$id) {
        sendResponse("Missing user id", 400);
    }

    $check_stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
    $check_stmt->execute([':id' => $id]);
    if (!$check_stmt->fetch()) {
        sendResponse("User not found", 404);
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id