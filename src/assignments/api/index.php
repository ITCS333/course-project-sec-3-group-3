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

function getAllAssignments(PDO $db): void
{
    $sql = 'SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments';

    $search = isset($_GET['search']) && $_GET['search'] !== '' ? $_GET['search'] : null;

    if ($search !== null) {
        $sql .= ' WHERE title LIKE :search OR description LIKE :search';
    }

    $allowedSorts = ['title', 'due_date', 'created_at'];
    $sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts)
        ? $_GET['sort']
        : 'due_date';

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
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assignments as &$assignment) {
        $assignment['files'] = json_decode($assignment['files'], true) ?? [];
    }

    sendResponse([
        'success' => true,
        'data' => $assignments
    ]);
}

function getAssignmentById(PDO $db, $id): void
{
    if (empty($id) || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing assignment ID.'
        ], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, title, description, due_date, files, created_at, updated_at
         FROM assignments
         WHERE id = ?'
    );

    $stmt->execute([(int) $id]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($assignment) {
        $assignment['files'] = json_decode($assignment['files'], true) ?? [];

        sendResponse([
            'success' => true,
            'data' => $assignment
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Assignment not found.'
        ], 404);
    }
}

function createAssignment(PDO $db, array $data): void
{
    if (
        !isset($data['title']) || trim($data['title']) === '' ||
        !isset($data['description']) || trim($data['description']) === '' ||
        !isset($data['due_date']) || trim($data['due_date']) === ''
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields.'
        ], 400);
    }

    $title = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description']);
    $dueDate = sanitizeInput($data['due_date']);

    if (!validateDate($dueDate)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid due_date.'
        ], 400);
    }

    $files = isset($data['files']) && is_array($data['files'])
        ? json_encode($data['files'])
        : json_encode([]);

    $stmt = $db->prepare(
        'INSERT INTO assignments (title, description, due_date, files)
         VALUES (?, ?, ?, ?)'
    );

    $stmt->execute([$title, $description, $dueDate, $files]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Assignment created successfully.',
            'id' => (int) $db->lastInsertId()
        ], 201);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to create assignment.'
        ], 500);
    }
}

function updateAssignment(PDO $db, array $data): void
{
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing assignment ID.'
        ], 400);
    }

    $assignmentId = (int) $data['id'];

    $check = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $check->execute([$assignmentId]);

    if (!$check->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment not found.'
        ], 404);
    }

    $fields = [];
    $values = [];

    if (isset($data['title']) && trim($data['title']) !== '') {
        $fields[] = 'title = ?';
        $values[] = sanitizeInput($data['title']);
    }

    if (isset($data['description'])) {
        $fields[] = 'description = ?';
        $values[] = sanitizeInput($data['description']);
    }

    if (isset($data['due_date']) && trim($data['due_date']) !== '') {
        $dueDate = sanitizeInput($data['due_date']);

        if (!validateDate($dueDate)) {
            sendResponse([
                'success' => false,
                'message' => 'Invalid due_date.'
            ], 400);
        }

        $fields[] = 'due_date = ?';
        $values[] = $dueDate;
    }

    if (isset($data['files'])) {
        $fields[] = 'files = ?';
        $values[] = is_array($data['files']) ? json_encode($data['files']) : json_encode([]);
    }

    if (count($fields) === 0) {
        sendResponse([
            'success' => false,
            'message' => 'No valid fields provided for update.'
        ], 400);
    }

    $values[] = $assignmentId;

    $sql = 'UPDATE assignments SET ' . implode(', ', $fields) . ' WHERE id = ?';

    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse([
        'success' => true,
        'message' => 'Assignment updated successfully.'
    ]);
}

function deleteAssignment(PDO $db, $id): void
{
    if (empty($id) || !is_numeric($id)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing assignment ID.'
        ], 400);
    }

    $assignmentId = (int) $id;

    $check = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $check->execute([$assignmentId]);

    if (!$check->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment not found.'
        ], 404);
    }

    $stmt = $db->prepare('DELETE FROM assignments WHERE id = ?');
    $stmt->execute([$assignmentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Assignment deleted successfully.'
        ]);
    } else {
        sendResponse([
            'success' => false,
            'message' => 'Failed to delete assignment.'
        ], 500);
    }
}

function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    if (empty($assignmentId) || !is_numeric($assignmentId)) {
        sendResponse([
            'success' => false,
            'message' => 'Invalid or missing assignment ID.'
        ], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, assignment_id, author, text, created_at
         FROM comments_assignment
         WHERE assignment_id = ?
         ORDER BY created_at ASC'
    );

    $stmt->execute([(int) $assignmentId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse([
        'success' => true,
        'data' => $comments
    ]);
}

function createComment(PDO $db, array $data): void
{
    if (
        empty($data['assignment_id']) ||
        empty($data['author']) ||
        !isset($data['text']) ||
        trim($data['text']) === ''
    ) {
        sendResponse([
            'success' => false,
            'message' => 'Missing required fields.'
        ], 400);
    }

    if (!is_numeric($data['assignment_id'])) {
        sendResponse([
            'success' => false,
            'message' => 'assignment_id must be numeric.'
        ], 400);
    }

    $assignmentId = (int) $data['assignment_id'];

    $check = $db->prepare('SELECT id FROM assignments WHERE id = ?');
    $check->execute([$assignmentId]);

    if (!$check->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Assignment not found.'
        ], 404);
    }

    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);

    $stmt = $db->prepare(
        'INSERT INTO comments_assignment (assignment_id, author, text)
         VALUES (?, ?, ?)'
    );

    $stmt->execute([$assignmentId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = (int) $db->lastInsertId();

        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id' => $newId,
            'data' => [
                'id' => $newId,
                'assignment_id' => $assignmentId,
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

    $check = $db->prepare('SELECT id FROM comments_assignment WHERE id = ?');
    $check->execute([$commentId]);

    if (!$check->fetch()) {
        sendResponse([
            'success' => false,
            'message' => 'Comment not found.'
        ], 404);
    }

    $stmt = $db->prepare('DELETE FROM comments_assignment WHERE id = ?');
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
$assignmentId = $_GET['assignment_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByAssignment($db, $assignmentId);
        } elseif ($id !== null) {
            getAssignmentById($db, $id);
        } else {
            getAllAssignments($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createAssignment($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateAssignment($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteAssignment($db, $id);
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
