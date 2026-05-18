<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    // Secure absolute path inclusion
    require_once __DIR__ . '/../../common/db.php';
    global $pdo;
    $db = $pdo;

    $method = $_SERVER['REQUEST_METHOD'];
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = $_GET['id'] ?? 0;
    $action = $_GET['action'] ?? '';

    if ($method === 'GET') {
        if ($id) {
            $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) { echo json_encode(['success' => true, 'data' => $user]); }
            else { http_response_code(404); echo json_encode(['success' => false, 'message' => 'User not found']); }
        } else {
            $search = $_GET['search'] ?? '';
            $sort = $_GET['sort'] ?? '';
            $order = strtolower($_GET['order'] ?? 'asc');
            $allowed_sorts = ['name', 'email', 'is_admin'];
            $sort = in_array($sort, $allowed_sorts) ? $sort : '';
            $order = in_array($order, ['asc', 'desc']) ? $order : 'asc';

            $sql = "SELECT id, name, email, is_admin, created_at FROM users";
            $params = [];
            if ($search) {
                $sql .= " WHERE name LIKE ? OR email LIKE ?";
                $params = ["%$search%", "%$search%"];
            }
            if ($sort) { $sql .= " ORDER BY $sort $order"; }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
    } elseif ($method === 'POST') {
        if ($action === 'change_password') {
            if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {
                http_response_code(400); echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
            }
            if (strlen($data['new_password']) < 8) {
                http_response_code(400); echo json_encode(['success' => false, 'message' => 'Short password']); exit;
            }
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$data['id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Not found']); exit; }
            if (!password_verify($data['current_password'], $user['password'])) {
                http_response_code(401); echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
            }
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([password_hash($data['new_password'], PASSWORD_DEFAULT), $data['id']]);
            echo json_encode(['success' => true, 'data' => 'Password updated']);
        } else {
            if (empty(trim($data['name'] ?? '')) || empty(trim($data['email'] ?? '')) || empty(trim($data['password'] ?? ''))) {
                http_response_code(400); echo json_encode(['success' => false, 'message' => 'Missing fields']); exit;
            }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['password']) < 8) {
                http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid input']); exit;
            }
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([trim($data['email'])]);
            if ($stmt->fetch()) { http_response_code(409); echo json_encode(['success' => false, 'message' => 'Email exists']); exit; }

            $stmt = $db->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (?, ?, ?, ?)");
            $stmt->execute([trim($data['name']), trim($data['email']), password_hash($data['password'], PASSWORD_DEFAULT), intval($data['is_admin'] ?? 0)]);
            http_response_code(201);
            echo json_encode(['success' => true, 'data' => ['id' => $db->lastInsertId()]]);
        }
    } elseif ($method === 'PUT') {
        if (empty($data['id'])) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Missing id']); exit; }
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$data['id']]);
        if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Not found']); exit; }
        
        $fields = []; $params = [];
        if (isset($data['name'])) { $fields[] = "name = ?"; $params[] = trim($data['name']); }
        if (isset($data['email'])) {
            $email = trim($data['email']);
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $data['id']]);
            if ($stmt->fetch()) { http_response_code(409); echo json_encode(['success' => false, 'message' => 'Email exists']); exit; }
            $fields[] = "email = ?"; $params[] = $email;
        }
        if (isset($data['is_admin'])) { $fields[] = "is_admin = ?"; $params[] = intval($data['is_admin']); }
        
        if (empty($fields)) { echo json_encode(['success' => true, 'data' => 'No changes']); exit; }
        $params[] = $data['id'];
        $stmt = $db->prepare("UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?");
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => 'Updated']);
    } elseif ($method === 'DELETE') {
        if (!$id) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Missing id']); exit; }
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['success' => false, 'message' => 'Not found']); exit; }
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'data' => 'Deleted']);
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>