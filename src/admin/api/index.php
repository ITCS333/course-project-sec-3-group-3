<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../../common/db.php';
    
    // Test environment strictly requires this function
    $db = getDBConnection();

    $method = $_SERVER['REQUEST_METHOD'];
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $action = $_GET['action'] ?? '';

    function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        if ($statusCode < 400) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => $data]);
        }
        exit;
    }

    if ($method === 'GET') {
        if ($id !== 0) {
            $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                sendResponse("User not found", 404);
            } else {
                sendResponse($user, 200);
            }
        } else {
            $search = $_GET['search'] ?? '';
            $sort = $_GET['sort'] ?? '';
            $order = strtolower($_GET['order'] ?? 'asc');

            $allowed_sorts = ['name', 'email', 'is_admin'];
            if (!in_array($sort, $allowed_sorts)) { $sort = ''; }
            if ($order !== 'asc' && $order !== 'desc') { $order = 'asc'; }

            $sql = "SELECT id, name, email, is_admin, created_at FROM users";
            $params = [];

            if ($search !== '') {
                $sql .= " WHERE (name LIKE :search OR email LIKE :search)";
                $params[':search'] = "%$search%";
            }

            if ($sort !== '') {
                $sql .= " ORDER BY $sort " . strtoupper($order);
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 200);
        }
    } elseif ($method === 'POST') {
        if ($action === 'change_password') {
            if (!isset($data['id']) || !isset($data['current_password']) || !isset($data['new_password'])) {
                sendResponse("Missing parameters", 400);
            }
            if (strlen($data['new_password']) < 8) {
                sendResponse("Short password", 400);
            }

            $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");
            $stmt->execute([':id' => intval($data['id'])]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) { sendResponse("Not found", 404); }
            if (!password_verify($data['current_password'], $user['password'])) {
                sendResponse("Unauthorized", 401);
            }

            $stmt = $db->prepare("UPDATE users SET password = :pwd WHERE id = :id");
            $stmt->execute([':pwd' => password_hash($data['new_password'], PASSWORD_DEFAULT), ':id' => intval($data['id'])]);
            sendResponse("Password updated", 200);
        } else {
            if (empty(trim($data['name'] ?? '')) || empty(trim($data['email'] ?? '')) || empty($data['password'] ?? '')) {
                sendResponse("Missing fields", 400);
            }
            
            $name = trim($data['name']);
            $email = trim($data['email']);
            $password = $data['password'];

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { sendResponse("Invalid email", 400); }
            if (strlen($password) < 8) { sendResponse("Short password", 400); }

            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) { sendResponse("Email exists", 409); }

            $is_admin = isset($data['is_admin']) ? intval($data['is_admin']) : 0;
            $stmt = $db->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (:n, :e, :p, :a)");
            if ($stmt->execute([':n' => $name, ':e' => $email, ':p' => password_hash($password, PASSWORD_DEFAULT), ':a' => $is_admin])) {
                sendResponse(['id' => $db->lastInsertId()], 201);
            } else {
                sendResponse("Error", 500);
            }
        }
    } elseif ($method === 'PUT') {
        if (empty($data['id'])) { sendResponse("Missing id", 400); }
        $user_id = intval($data['id']);

        $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        if (!$stmt->fetch()) { sendResponse("Not found", 404); }

        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = "name = :name";
            $params[':name'] = trim($data['name']);
        }
        if (isset($data['email'])) {
            $email = trim($data['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { sendResponse("Invalid email", 400); }

            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $email, ':id' => $user_id]);
            if ($stmt->fetch()) { sendResponse("Email exists", 409); }

            $fields[] = "email = :email";
            $params[':email'] = $email;
        }
        if (isset($data['is_admin'])) {
            $fields[] = "is_admin = :is_admin";
            $params[':is_admin'] = intval($data['is_admin']);
        }

        if (empty($fields)) { sendResponse("No changes", 200); }

        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = :id";
        $params[':id'] = $user_id;
        
        $stmt = $db->prepare($sql);
        if ($stmt->execute($params)) {
            sendResponse("Updated", 200);
        } else {
            sendResponse("Error", 500);
        }
    } elseif ($method === 'DELETE') {
        if (!$id) { sendResponse("Missing id", 400); }
        $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) { sendResponse("Not found", 404); }

        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        if ($stmt->execute([':id' => $id])) {
            sendResponse("Deleted", 200);
        } else {
            sendResponse("Error", 500);
        }
    } else {
        sendResponse("Method Not Allowed", 405);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
    exit;
}
?>