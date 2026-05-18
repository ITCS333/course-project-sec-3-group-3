<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

$raw_post_data = file_get_contents('php://input');
$decoded_data = json_decode($raw_post_data, true);

if (!isset($decoded_data['email']) || !isset($decoded_data['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing email or password'
    ]);
    exit;
}

$email = trim($decoded_data['email']);
$password = $decoded_data['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email or password'
    ]);
    exit;
}

try {
    $db_path = dirname(__DIR__, 2) . '/common/db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
    }

    $db = isset($pdo) ? $pdo : null;

    if ($db) {
        $stmt = $db->prepare("SELECT id, name, email, password, is_admin FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['is_admin'] = $user['is_admin'];
            $_SESSION['logged_in'] = true;

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'is_admin' => $user['is_admin']
                ]
            ]);
            exit;
        }
    }

    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email or password'
    ]);
    exit;

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