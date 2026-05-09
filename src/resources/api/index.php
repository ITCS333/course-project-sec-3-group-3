<?php
/**
 * Course Resources API
 *
 * RESTful API for CRUD operations on course resources and their comments.
 * Uses PDO + MySQL.
 */
 
// ============================================================================
// HELPER FUNCTIONS  (defined first so catch blocks and router can call them)
// ============================================================================
 
/**
 * Send a JSON response and stop execution.
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    if (!is_array($data)) {
        $data = ['data' => $data];
    }
    echo json_encode($data);
    exit;
}
 
/**
 * Validate a URL string.
 */
function validateUrl($url) {
    return (bool) filter_var($url, FILTER_VALIDATE_URL);
}
 
/**
 * Sanitize a single input string: trim -> strip_tags -> htmlspecialchars.
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
 
/**
 * Validate that all required fields exist and are non-empty in $data.
 * Returns ['valid' => bool, 'missing' => string[]]
 */
function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            $missing[] = $field;
        }
    }
    return [
        'valid'   => count($missing) === 0,
        'missing' => $missing,
    ];
}
 
 
// ============================================================================
// RESOURCE FUNCTIONS
// ============================================================================
 
function getAllResources($db) {
    $sql = 'SELECT id, title, description, link, created_at FROM resources';
 
    $search = (isset($_GET['search']) && $_GET['search'] !== '') ? $_GET['search'] : null;
    if ($search !== null) {
        $sql .= ' WHERE title LIKE :search OR description LIKE :search';
    }
 
    $allowedSorts = ['title', 'created_at'];
    $sort = (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts))
        ? $_GET['sort'] : 'created_at';
 
    $allowedOrders = ['asc', 'desc'];
    $order = (isset($_GET['order']) && in_array(strtolower($_GET['order']), $allowedOrders))
        ? strtolower($_GET['order']) : 'desc';
 
    $sql .= " ORDER BY {$sort} {$order}";
    $stmt = $db->prepare($sql);
 
    if ($search !== null) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
 
    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
    sendResponse(['success' => true, 'data' => $resources]);
}
 
function getResourceById($db, $resourceId) {
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing resource ID.'], 400);
    }
 
    $stmt = $db->prepare('SELECT id, title, description, link, created_at FROM resources WHERE id = ?');
    $stmt->execute([(int) $resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
 
    if ($resource) {
        sendResponse(['success' => true, 'data' => $resource]);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
}
 
function createResource($db, $data) {
    $validation = validateRequiredFields($data ?? [], ['title', 'link']);
    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $validation['missing']) . '.',
        ], 400);
    }
 
    $title       = sanitizeInput($data['title']);
    $link        = sanitizeInput($data['link']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';
 
    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL provided for link.'], 400);
    }
 
    $stmt = $db->prepare('INSERT INTO resources (title, description, link) VALUES (?, ?, ?)');
    $stmt->execute([$title, $description, $link]);
 
    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Resource created successfully.',
            'id'      => (int) $db->lastInsertId(),
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create resource.'], 500);
    }
}
 
function updateResource($db, $data) {
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing resource ID.'], 400);
    }
 
    $resourceId = (int) $data['id'];
 
    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$resourceId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
 
    $fields = [];
    $values = [];
 
    if (isset($data['title']) && $data['title'] !== '') {
        $fields[] = 'title = ?';
        $values[] = sanitizeInput($data['title']);
    }
    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $values[] = sanitizeInput($data['description']);
    }
    if (isset($data['link']) && $data['link'] !== '') {
        $link = sanitizeInput($data['link']);
        if (!validateUrl($link)) {
            sendResponse(['success' => false, 'message' => 'Invalid URL provided for link.'], 400);
        }
        $fields[] = 'link = ?';
        $values[] = $link;
    }
 
    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No valid fields provided for update.'], 400);
    }
 
    $values[] = $resourceId;
    $stmt = $db->prepare('UPDATE resources SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($values);
 
    sendResponse(['success' => true, 'message' => 'Resource updated successfully.']);
}
 
function deleteResource($db, $resourceId) {
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing resource ID.'], 400);
    }
 
    $resourceId = (int) $resourceId;
 
    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$resourceId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
 
    $stmt = $db->prepare('DELETE FROM resources WHERE id = ?');
    $stmt->execute([$resourceId]);
 
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Resource deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete resource.'], 500);
    }
}
 
 
// ============================================================================
// COMMENT FUNCTIONS
// ============================================================================
 
function getCommentsByResourceId($db, $resourceId) {
    if (empty($resourceId) || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing resource ID.'], 400);
    }
 
    $stmt = $db->prepare(
        'SELECT id, resource_id, author, text, created_at
         FROM comments_resource
         WHERE resource_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([(int) $resourceId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
    sendResponse(['success' => true, 'data' => $comments]);
}
 
function createComment($db, $data) {
    $validation = validateRequiredFields($data ?? [], ['resource_id', 'author', 'text']);
    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $validation['missing']) . '.',
        ], 400);
    }
 
    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'resource_id must be a numeric value.'], 400);
    }
 
    $resourceId = (int) $data['resource_id'];
 
    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$resourceId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }
 
    $author = sanitizeInput($data['author']);
    $text   = sanitizeInput($data['text']);
 
    $stmt = $db->prepare(
        'INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)'
    );
    $stmt->execute([$resourceId, $author, $text]);
 
    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id'      => (int) $db->lastInsertId(),
        ], 201);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
    }
}
 
function deleteComment($db, $commentId) {
    if (empty($commentId) || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid or missing comment ID.'], 400);
    }
 
    $commentId = (int) $commentId;
 
    $check = $db->prepare('SELECT id FROM comments_resource WHERE id = ?');
    $check->execute([$commentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }
 
    $stmt = $db->prepare('DELETE FROM comments_resource WHERE id = ?');
    $stmt->execute([$commentId]);
 
    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
    }
}
 
 
// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================
 
// Suppress header warnings when running in CLI (test environment)
if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}
 
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}
 
// Use __DIR__ so the path resolves correctly regardless of CWD
require_once __DIR__ . '/config/Database.php';
 
$database = new Database();
$db       = $database->getConnection();
 
$method     = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawData    = file_get_contents('php://input');
$data       = json_decode($rawData, true);
 
$action     = $_GET['action']       ?? null;
$id         = $_GET['id']           ?? null;
$resourceId = $_GET['resource_id']  ?? null;
$commentId  = $_GET['comment_id']   ?? null;
 
 
// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================
 
try {
 
    if ($method === 'GET') {
 
        if ($action === 'comments') {
            getCommentsByResourceId($db, $resourceId);
        } elseif ($id !== null) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }
 
    } elseif ($method === 'POST') {
 
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }
 
    } elseif ($method === 'PUT') {
 
        updateResource($db, $data);
 
    } elseif ($method === 'DELETE') {
 
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteResource($db, $id);
        }
 
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
 
} catch (PDOException $e) {
    error_log('PDOException in Course Resources API: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'A database error occurred. Please try again later.'], 500);
 
} catch (Exception $e) {
    error_log('Exception in Course Resources API: ' . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'An unexpected error occurred. Please try again later.'], 500);
}
