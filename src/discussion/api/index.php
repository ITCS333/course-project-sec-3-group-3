<?php
/**
 * Discussion Board API
 *
 * RESTful API for CRUD operations on topics and replies.
 */

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
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$topicId = $_GET['topic_id'] ?? null;

/**
 * Get all topics.
 */
function getAllTopics(PDO $db): void
{
    $sql = 'SELECT id, subject, message, author, created_at FROM topics';
    $search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

    if ($search !== '') {
        $sql .= ' WHERE subject LIKE :search OR message LIKE :search OR author LIKE :search';
    }

    $allowedSorts = ['subject', 'author', 'created_at'];
    $sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts, true)
        ? $_GET['sort']
        : 'created_at';

    $order = strtolower((string) ($_GET['order'] ?? 'desc'));
    $order = in_array($order, ['asc', 'desc'], true) ? $order : 'desc';

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);
    if ($search !== '') {
        $stmt->bindValue(':search', '%' . $search . '%');
    }
    $stmt->execute();

    sendResponse([
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/**
 * Get one topic by id.
 */
function getTopicById(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing topic ID.'
        ], 400);
    }

    $stmt = $db->prepare('SELECT id, subject, message, author, created_at FROM topics WHERE id = ?');
    $stmt->execute([(int) $id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$topic) {
        sendResponse([
            'success' => false,
            'message' => 'Topic not found.'
        ], 404);
    }

    sendResponse([
        'success' => true,
        'data' => $topic
    ]);
}

/**
 * Create a topic.
 */
function createTopic(PDO $db, array $data): void
{
    $subject = isset($data['subject']) ? sanitizeInput((string) $data['subject']) : '';
    $message = isset($data['message']) ? sanitizeInput((string) $data['message']) : '';
    $author = isset($data['author']) ? sanitizeInput((string) $data['author']) : '';

    if ($subject === '' || $message === '' || $author === '') {
        sendResponse([
            'success' => false,
            'message' => 'subject, message, and author are required.'
        ], 400);
    }

    $stmt = $db->prepare('INSERT INTO topics (subject, message, author) VALUES (?, ?, ?)');
    $stmt->execute([$subject, $message, $author]);

    if ($stmt->rowCount() <= 0) {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create topic.'
        ], 500);
    }

    sendResponse([
        'success' => true,
        'message' => 'Topic created successfully.',
        'id' => (int) $db->lastInsertId()
    ], 201);
}

/**
 * Update a topic.
 */
function updateTopic(PDO $db, array $data): void
{
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing topic ID.'
        ], 400);
    }

    $topicId = (int) $data['id'];
    $existsStmt = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $existsStmt->execute([$topicId]);
    if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Topic not found.'
        ], 404);
    }

    $fields = [];
    $values = [];

    if (array_key_exists('subject', $data)) {
        $subject = sanitizeInput((string) $data['subject']);
        if ($subject !== '') {
            $fields[] = 'subject = ?';
            $values[] = $subject;
        }
    }

    if (array_key_exists('message', $data)) {
        $message = sanitizeInput((string) $data['message']);
        if ($message !== '') {
            $fields[] = 'message = ?';
            $values[] = $message;
        }
    }

    if (count($fields) === 0) {
        sendResponse([
            'success' => false,
            'message' => 'No valid fields provided for update.'
        ], 400);
    }

    $values[] = $topicId;
    $sql = 'UPDATE topics SET ' . implode(', ', $fields) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse([
        'success' => true,
        'message' => 'Topic updated successfully.'
    ]);
}

/**
 * Delete a topic.
 */
function deleteTopic(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing topic ID.'
        ], 400);
    }

    $topicId = (int) $id;
    $existsStmt = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $existsStmt->execute([$topicId]);
    if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Topic not found.'
        ], 404);
    }

    $stmt = $db->prepare('DELETE FROM topics WHERE id = ?');
    $stmt->execute([$topicId]);

    if ($stmt->rowCount() <= 0) {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete topic.'
        ], 500);
    }

    sendResponse([
        'success' => true,
        'message' => 'Topic deleted successfully.'
    ]);
}

/**
 * Get replies by topic id.
 */
function getRepliesByTopicId(PDO $db, $topicId): void
{
    if ($topicId === null || !is_numeric($topicId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing topic_id.'
        ], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, topic_id, text, author, created_at
         FROM replies
         WHERE topic_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([(int) $topicId]);

    sendResponse([
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/**
 * Create a reply.
 */
function createReply(PDO $db, array $data): void
{
    $topicId = $data['topic_id'] ?? null;
    $text = isset($data['text']) ? sanitizeInput((string) $data['text']) : '';
    $author = isset($data['author']) ? sanitizeInput((string) $data['author']) : '';

    if ($topicId === null || $text === '' || $author === '') {
        sendResponse([
            'success' => false,
            'message' => 'topic_id, text, and author are required.'
        ], 400);
    }

    if (!is_numeric($topicId)) {
        sendResponse([
            'success' => false,
            'message' => 'topic_id must be numeric.'
        ], 400);
    }

    $topicId = (int) $topicId;
    $topicStmt = $db->prepare('SELECT id FROM topics WHERE id = ?');
    $topicStmt->execute([$topicId]);
    if (!$topicStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Topic not found.'
        ], 404);
    }

    $insertStmt = $db->prepare('INSERT INTO replies (topic_id, text, author) VALUES (?, ?, ?)');
    $insertStmt->execute([$topicId, $text, $author]);

    if ($insertStmt->rowCount() <= 0) {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create reply.'
        ], 500);
    }

    $replyId = (int) $db->lastInsertId();
    $replyStmt = $db->prepare(
        'SELECT id, topic_id, text, author, created_at
         FROM replies
         WHERE id = ?'
    );
    $replyStmt->execute([$replyId]);
    $reply = $replyStmt->fetch(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'message' => 'Reply created successfully.',
        'id' => $replyId,
        'data' => $reply
    ], 201);
}

/**
 * Delete one reply.
 */
function deleteReply(PDO $db, $replyId): void
{
    if ($replyId === null || !is_numeric($replyId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing reply ID.'
        ], 400);
    }

    $replyId = (int) $replyId;
    $existsStmt = $db->prepare('SELECT id FROM replies WHERE id = ?');
    $existsStmt->execute([$replyId]);
    if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
        sendResponse([
            'success' => false,
            'message' => 'Reply not found.'
        ], 404);
    }

    $stmt = $db->prepare('DELETE FROM replies WHERE id = ?');
    $stmt->execute([$replyId]);

    if ($stmt->rowCount() <= 0) {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete reply.'
        ], 500);
    }

    sendResponse([
        'success' => true,
        'message' => 'Reply deleted successfully.'
    ]);
}

try {
    if ($method === 'GET') {
        if ($action === 'replies') {
            getRepliesByTopicId($db, $topicId);
        } elseif ($id !== null) {
            getTopicById($db, $id);
        } else {
            getAllTopics($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'reply') {
            createReply($db, $data);
        } else {
            createTopic($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateTopic($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_reply') {
            deleteReply($db, $id);
        } else {
            deleteTopic($db, $id);
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
        'message' => 'A database error occurred. Please try again later.'
    ], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ], 500);
}

/**
 * Send JSON response and stop.
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

/**
 * Sanitize plain text input.
 */
function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
