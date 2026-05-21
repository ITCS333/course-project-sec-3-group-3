<?php

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function getAllWeeks(PDO $db): void
{
    $sql = 'SELECT id, title, start_date, description, links, created_at, updated_at FROM weeks';

    $search = isset($_GET['search']) && $_GET['search'] !== '' ? $_GET['search'] : null;

    if ($search !== null) {
        $sql .= ' WHERE title LIKE :search OR description LIKE :search';
    }

    $allowedSorts = ['title', 'start_date'];
    $sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts)
        ? $_GET['sort']
        : 'start_date';

    $allowedOrders = ['asc', 'desc'];
    $order = isset($_GET['order']) && in_array(strtolower($_GET['order']), $allowedOrders)
        ? strtolower($_GET['order'])
        : 'asc';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);

    if ($search !== null) {
        $stmt->bindValue(':search', '%' . $search . '%');
    }

    $stmt->execute();
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weeks as &$week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
    }

    sendResponse([
        'success' => true,
        'data' => $weeks
    ]);
}

function getWeekById(PDO $db, $id): void
{
    if (empty($id) || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing week ID.'
        ], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, title, start_date, description, links, created_at, updated_at
         FROM weeks
         WHERE id = ?'
    );

    $stmt->execute([(int) $id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($week) {
        $week['links'] = json_decode($week['links'], true) ?? [];

        sendResponse([
            'success' => true,
            'data' => $week
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Week not found.'
        ], 404);
    }
}

function createWeek(PDO $db, array $data): void
{
    if (
        !isset($data['title']) || trim($data['title']) === '' ||
        !isset($data['start_date']) || trim($data['start_date']) === ''
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields.'
        ], 400);
    }

    $title = sanitizeInput($data['title']);
    $startDate = sanitizeInput($data['start_date']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : '';

    if (!validateDate($startDate)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid start_date.'
        ], 400);
    }

    $links = isset($data['links']) && is_array($data['links'])
        ? json_encode($data['links'])
        : json_encode([]);

    $stmt = $db->prepare(
        'INSERT INTO weeks (title, start_date, description, links)
         VALUES (?, ?, ?, ?)'
    );

    $stmt->execute([$title, $startDate, $description, $links]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Week created successfully.',
            'id' => (int) $db->lastInsertId()
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create week.'
        ], 500);
    }
}

function updateWeek(PDO $db, array $data): void
{
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing week ID.'
        ], 400);
    }

    $weekId = (int) $data['id'];

    $check = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $check->execute([$weekId]);

    if (!$check->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Week not found.'
        ], 404);
    }

    $fields = [];
    $values = [];

    if (isset($data['title']) && trim($data['title']) !== '') {
        $fields[] = 'title = ?';
        $values[] = sanitizeInput($data['title']);
    }

    if (isset($data['start_date']) && trim($data['start_date']) !== '') {
        $startDate = sanitizeInput($data['start_date']);

        if (!validateDate($startDate)) {
            sendResponse([
                'success' => false,
                'message' => 'Invalid start_date.'
            ], 400);
        }

        $fields[] = 'start_date = ?';
        $values[] = $startDate;
    }

    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $values[] = sanitizeInput($data['description']);
    }

    if (isset($data['links'])) {
        $fields[] = 'links = ?';
        $values[] = is_array($data['links']) ? json_encode($data['links']) : json_encode([]);
    }

    if (count($fields) === 0) {
        sendResponse([
            'success' => false,
            'message' => 'No valid fields provided for update.'
        ], 400);
    }

    $values[] = $weekId;

    $sql = 'UPDATE weeks SET ' . implode(', ', $fields) . ' WHERE id = ?';

    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse([
        'success' => true,
        'message' => 'Week updated successfully.'
    ]);
}

function deleteWeek(PDO $db, $id): void
{
    if (empty($id) || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing week ID.'
        ], 400);
    }

    $weekId = (int) $id;

    $check = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $check->execute([$weekId]);

    if (!$check->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Week not found.'
        ], 404);
    }

    $stmt = $db->prepare('DELETE FROM weeks WHERE id = ?');
    $stmt->execute([$weekId]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Week deleted successfully.'
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete week.'
        ], 500);
    }
}

function getCommentsByWeek(PDO $db, $weekId): void
{
    if (empty($weekId) || !is_numeric($weekId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing week ID.'
        ], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, week_id, author, text, created_at
         FROM comments_week
         WHERE week_id = ?
         ORDER BY created_at ASC'
    );

    $stmt->execute([(int) $weekId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data' => $comments
    ]);
}

function createComment(PDO $db, array $data): void
{
    if (
        empty($data['week_id']) ||
        empty($data['author']) ||
        !isset($data['text']) ||
        trim($data['text']) === ''
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields.'
        ], 400);
    }

    if (!is_numeric($data['week_id'])) {
        sendResponse([
            'success' => false,
            'message' => 'week_id must be numeric.'
        ], 400);
    }

    $weekId = (int) $data['week_id'];

    $check = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $check->execute([$weekId]);

    if (!$check->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Week not found.'
        ], 404);
    }

    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);

    $stmt = $db->prepare(
        'INSERT INTO comments_week (week_id, author, text)
         VALUES (?, ?, ?)'
    );

    $stmt->execute([$weekId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = (int) $db->lastInsertId();

        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id' => $newId,
            'data' => [
                'id' => $newId,
                'week_id' => $weekId,
                'author' => $author,
                'text' => $text
            ]
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create comment.'
        ], 500);
    }
}

function deleteComment(PDO $db, $commentId): void
{
    if (empty($commentId) || !is_numeric($commentId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing comment ID.'
        ], 400);
    }

    $commentId = (int) $commentId;

    $check = $db->prepare('SELECT id FROM comments_week WHERE id = ?');
    $check->execute([$commentId]);

    if (!$check->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Comment not found.'
        ], 404);
    }

    $stmt = $db->prepare('DELETE FROM comments_week WHERE id = ?');
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Comment deleted successfully.'
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete comment.'
        ], 500);
    }
}

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

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = json_decode(file_get_contents('php://input'), true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$weekId = $_GET['week_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif ($id !== null) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateWeek($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'A database error occurred.'
    ], 500);
} catch (Exception $e) {
    error_log($e->getMessage());

    sendResponse([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ], 500);
}
    // TODO: return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
