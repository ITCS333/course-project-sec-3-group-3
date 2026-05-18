<?php
/**
 * User Management API
 *
 * A RESTful API that handles all CRUD operations for user management
 * and password changes for the Admin Portal.
 * Uses PDO to interact with a MySQL database.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the database connection file directly as expected by the environment
require_once '../../common/db.php';

// Assign the exact global PDO instance directly
$db = $pdo;

$method = $_SERVER['REQUEST_METHOD'];

$raw_body = file_get_contents('php://input');
$data = json_decode($raw_body, true) ?? [];

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

function getUsers($db) {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : '';
    $order = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';

    $allowed_sorts = ['name', 'email', 'is_admin'];
    if (!in_array($sort, $allowed_sorts)) { $sort = ''; }
    if ($order !== 'asc' && $order !== 'desc') { $order = 'asc'; }

    $sql = "SELECT id, name, email, is_admin, created_at FROM users";
    $params = [];

    if ($search !== '') {
        $sql .= " WHERE (name LIKE :search OR email LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($sort !== '') {
        $sql .= " ORDER BY " . $sort . " " . strtoupper($order);
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 200);
}

function getUserById($db, $id) {
    $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        sendResponse("User not found", 404);
    } else {
        sendResponse($user, 200);
    }
}

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

    $check = $db->prepare("SELECT id FROM users WHERE email = :email");
    $check->execute([':email' => $email]);
    if ($check->fetch()) { 
        sendResponse("Email already exists", 409); 
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $is_admin = isset($data['is_admin']) ? intval($data['is_admin']) : 0;

    $stmt = $db->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)");
    if ($stmt->execute([':name' => $name, ':email' => $email, ':password' => $hashed, ':is_admin' => $is_admin])) {
        sendResponse(['id' => $db->lastInsertId()], 201);
    } else {
        sendResponse("Internal server error", 500);
    }
}

function updateUser($db, $data) {
    if (!isset($data['id']) || empty($data['id'])) { 
        sendResponse("Missing user id", 400); 
    }

    $user_id = intval($data['id']);
    $check = $db->prepare("SELECT id FROM users WHERE id = :id");
    $check->execute([':id' => $user_id]);
    if (!$check->fetch()) { 
        sendResponse("User not found", 404); 
    }

    $fields = [];
    $params = [':id' => $user_id];

    if (isset($data['name'])) { 
        $fields[] = "name = :name"; 
        $params[':name'] = trim($data['name']); 
    }
    if (isset($data['email'])) {
        $email = trim($data['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { 
            sendResponse("Invalid email format", 400); 
        }
        $check_email = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
        $check_email->execute([':email' => $email, ':id' => $user_id]);
        if ($check_email->fetch()) { 
            sendResponse("Email already in use", 409); 
        }
        $fields[] = "email = :email"; 
        $params[':email'] = $email;
    }
    if (isset($data['is_admin'])) { 
        $fields[] = "is_admin = :is_admin"; 
        $params[':is_admin'] = intval($data['is_admin']); 
    }

    if (empty($fields)) { 
        sendResponse("No modifications provided", 200); 
    }

    $stmt = $db->prepare("UPDATE users SET " . implode(", ", $fields) . " WHERE id = :id");
    if ($stmt->execute($params)) {
        sendResponse("User updated successfully!", 200);
    } else {
        sendResponse("Internal server error", 500);
    }
}

function deleteUser($db, $id) {
    if (!$id) { 
        sendResponse("Missing user id", 400); 
    }

    $check = $db->prepare("SELECT id FROM users WHERE id = :id");
    $check->execute([':id' => $id]);
    if (!$check->fetch()) { 
        sendResponse("User not found", 404); 
    }

    $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
    if ($stmt->execute([':id' => $id])) {
        sendResponse("User deleted successfully!", 200);
    } else {
        sendResponse("Internal server error", 500);
    }
}

function changePassword($db, $data) {
    if (!isset($data['id']) || !isset($data['current_password']) || !isset($data['new_password'])) {
        sendResponse("Missing parameters", 400);
    }

    $user_id = intval($data['id']);
    $new_password = $data['new_password'];

    if (strlen($new_password) < 8) { 
        sendResponse("Password must be at least 8 characters.", 400); 
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) { 
        sendResponse("User not found", 404); 
    }
    if (!password_verify($data['current_password'], $user['password'])) { 
        sendResponse("Unauthorized access", 401); 
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $update = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
    if ($update->execute([':password' => $new_hash, ':id' => $user_id])) {
        sendResponse("Password updated successfully!", 200);
    } else {
        sendResponse("Internal server error", 500);
    }
}

try {
    if ($method === 'GET') {
        if ($id !== 0) { getUserById($db, $id); } else { getUsers($db); }
    } elseif ($method === 'POST') {
        if ($action === 'change_password') { changePassword($db, $data); } else { createUser($db, $data); }
    } elseif ($method === 'PUT') {
        updateUser($db, $data);
    } elseif ($method === 'DELETE') {
        deleteUser($db, $id);
    } else {
        sendResponse("Method Not Allowed", 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse("Database error", 500);
} catch (Exception $e) {
    sendResponse($e->getMessage(), 500);
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if ($statusCode < 400) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => $data]);
    }
    exit;
}

function validateEmail($email) { return (bool) filter_var($email, FILTER_VALIDATE_EMAIL); }
function sanitizeInput($data) { return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8'); }
?>