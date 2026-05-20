<?php
/**
 * Assignment Management API
 *
 * RESTful API for CRUD operations on course assignments and their
 * discussion comments. Uses PDO to interact with the MySQL database
 * defined in schema.sql.
 *
 * Database Tables (ground truth: schema.sql):
 *
 * Table: assignments
 * id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 * title       VARCHAR(200)  NOT NULL
 * description TEXT
 * due_date    DATE          NOT NULL
 * files       TEXT          — JSON-encoded array of file URL strings
 * created_at  TIMESTAMP
 * updated_at  TIMESTAMP     — updated automatically by MySQL ON UPDATE
 *
 * Table: comments_assignment
 * id            INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 * assignment_id INT UNSIGNED  NOT NULL — FK → assignments.id (ON DELETE CASCADE)
 * author        VARCHAR(100)  NOT NULL
 * text          TEXT          NOT NULL
 * created_at    TIMESTAMP
 *
 * HTTP Methods Supported:
 * GET    — Retrieve assignment(s) or comments
 * POST   — Create a new assignment or comment
 * PUT    — Update an existing assignment
 * DELETE — Delete an assignment (cascade removes its comments) or a comment
 *
 * URL scheme (all requests go to index.php):
 *
 * Assignments:
 * GET    ./api/index.php                  — list all assignments
 * GET    ./api/index.php?id={id}           — get one assignment by integer id
 * POST   ./api/index.php                  — create a new assignment
 * PUT    ./api/index.php                  — update an assignment (id in JSON body)
 * DELETE ./api/index.php?id={id}           — delete an assignment
 *
 * Comments (action parameter selects the comments sub-resource):
 * GET    ./api/index.php?action=comments&assignment_id={id}
 * — list comments for an assignment
 * POST   ./api/index.php?action=comment   — create a comment
 * DELETE ./api/index.php?action=delete_comment&comment_id={id}
 * — delete a single comment
 *
 * Query parameters for GET all assignments:
 * search — filter rows where title LIKE or description LIKE the term
 * sort   — column to sort by; allowed: title, due_date, created_at
 * (default: due_date)
 * order  — sort direction; allowed: asc, desc (default: asc)
 *
 * Response format: JSON
 * Success: { "success": true,  "data": ... }
 * Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// TODO: Set headers for JSON response and CORS.
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// TODO: Handle preflight OPTIONS request.
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){
    http_response_code(200);
    exit();
}

// TODO: Include the shared database connection file.
require_once __DIR__ . '/../../common/db.php';

// TODO: Get the PDO database connection.
$db = getDBConnection();

// TODO: Read the HTTP request method.
$method = $_SERVER['REQUEST_METHOD'];

// TODO: Read and decode the request body for POST and PUT requests.
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

// TODO: Read query parameters.
$action       = $_GET['action']        ?? null;
$id           = $_GET['id']            ?? null;
$assignmentId = $_GET['assignment_id'] ?? null;
$commentId    = $_GET['comment_id']    ?? null;

// ============================================================================
// ASSIGNMENT FUNCTIONS
// ============================================================================

/**
 * Get all assignments (with optional search and sort).
 */
function getAllAssignments(PDO $db): void
{
    // TODO: Build the base SELECT query.
    $sql="SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments";
    
    // TODO: If $_GET['search'] is provided and non-empty, append:
    $search=null;
    if(!empty($_GET['search'])){
        $search=$_GET['search'];
        $sql.=" WHERE title LIKE :search OR description LIKE :search";
    }

    // TODO: Validate $_GET['sort'] against the whitelist
    $allowedSort=['title', 'due_date', 'created_at'];
    $sort=$_GET['sort']??'due_date';
    if(!in_array($sort, $allowedSort)){
        $sort='due_date';
    }
    
    // TODO: Validate $_GET['order'] against [asc, desc].
    $order=strtolower($_GET['order']??'asc');
    if(!in_array($order, ['asc', 'desc'])){
        $order='asc';
    }
    
    // TODO: Append ORDER BY {sort} {order} to the query.
    $sql.=" ORDER BY $sort $order";
    
    // TODO: Prepare, bind (if searching), and execute the statement.
    $stmt=$db->prepare($sql);
    if($search!==null){
        $stmt->bindValue(':search', '%'.$search.'%');
    }
    $stmt->execute();
    
    // TODO: Fetch all rows as an associative array.
    $assignments=$stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // TODO: For each row, decode the files column:
    foreach($assignments as &$row){
        $row['files']=json_decode($row['files'],true)??[];
    }
    unset($row);
    
    // TODO: Call sendResponse(['success' => true, 'data' => $assignments]);
    sendResponse(['success'=>true,'data'=>$assignments]);
}


/**
 * Get a single assignment by its integer primary key.
 */
function getAssignmentById(PDO $db, $id): void
{
    // TODO: Validate that $id is provided and numeric.
    if($id === null || $id === '' || !is_numeric($id)){
        sendResponse(['success'=>false,'message'=>'Invalid assignment ID'],400);
        return;
    }
    // TODO: SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments WHERE id = ?
    $sql="SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments WHERE id = ?";
    
    // TODO: Fetch one row. Decode the files JSON:
    $stmt=$db->prepare($sql);
    $stmt->execute([$id]);
    $assignment=$stmt->fetch(PDO::FETCH_ASSOC);
    
    if($assignment){
        $assignment['files']=json_decode($assignment['files'],true)??[];
    }
    
    // TODO: If found, sendResponse success with the assignment.
    // If not found, sendResponse error with HTTP 404.
    if($assignment){
        sendResponse(['success'=>true,'data'=>$assignment]);
    } else {
        sendResponse(['success'=>false,'message'=>'Assignment not found'],404);
    }
}


/**
 * Create a new assignment.
 */
function createAssignment(PDO $db, array $data): void
{
    // TODO: Validate that title, description, and due_date are present
    if(!isset($data['title']) || !isset($data['description']) || !isset($data['due_date']) || $data['title']==='' || $data['description']==='' || $data['due_date']===''){
        sendResponse(['success'=>false,'message'=>'Missing required fields'],400);
        return;
    }
    
    // TODO: Trim title, description, and due_date.
    $title=trim($data['title']);
    $description=trim($data['description']);
    $due_date=trim($data['due_date']);
    
    // TODO: Validate due_date format using
    $date=DateTime::createFromFormat('Y-m-d',$due_date);
    if(!$date || $date->format('Y-m-d')!==$due_date){
        sendResponse(['success'=>false,'message'=>'Invalid due date format'],400);
        return;
    }
    
    // TODO: Handle files: if provided and is an array, json_encode it.
    $files=isset($data['files']) && is_array($data['files']) ? json_encode($data['files']) : json_encode([]);
    
    // TODO: INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)
    $sql="INSERT INTO assignments (title, description, due_date, files) VALUES (?, ?, ?, ?)";
    $stmt=$db->prepare($sql);
    $stmt->execute([$title,$description,$due_date,$files]);
    
    // TODO: If rowCount() > 0, sendResponse HTTP 201 with the new integer id
    if($stmt->rowCount() > 0){
        sendResponse(['success'=>true,'id'=>(int)$db->lastInsertId()],201);
    } else {
        sendResponse(['success'=>false,'message'=>'Insert failed'],500);
    }
}


/**
 * Update an existing assignment.
 */
function updateAssignment(PDO $db, array $data): void
{
    // TODO: Validate that $data['id'] is present.
    if (!isset($data['id']) || !is_numeric($data['id'])){
        sendResponse(['success'=>false,'message'=>'Missing id'],400);
        return;
    }
    
    // TODO: Check that an assignment with this id exists.
    $stmt=$db->prepare("SELECT id FROM assignments WHERE id = ?");
    $stmt->execute([$data['id']]);
    if(!$stmt->fetch(PDO::FETCH_ASSOC)){
        sendResponse(['success'=>false,'message'=>'Assignment not found'],404);
        return;
    }
    
    // TODO: Dynamically build the SET clause
    $fields=[];
    $params=[];
    
    if(isset($data['title'])){
        $fields[]="title = ?";
        $params[]=$data['title'];
    }
    if(isset($data['description'])){
        $fields[]="description = ?";
        $params[]=$data['description'];
    }
    if(isset($data['due_date'])){
        $due_date=$data['due_date'];
        $date=DateTime::createFromFormat('Y-m-d',$due_date);
        if(!$date || $date->format('Y-m-d')!==$due_date){
            sendResponse(['success'=>false,'message'=>'Invalid due date format'],400);
            return;
        }
        $fields[]="due_date = ?";
        $params[]=$due_date;
    }
    if(isset($data['files'])){
        $fields[]="files = ?";
        $params[]=is_array($data['files']) ? json_encode($data['files']) : json_encode([]);
    }
    
    // TODO: If no updatable fields are present, sendResponse HTTP 400.
    if(empty($fields)){
        sendResponse(['success'=>false,'message'=>'No fields to update'],400);
        return;
    }
    
    // TODO: Build: UPDATE assignments SET {clauses} WHERE id = ?
    $sql="UPDATE assignments SET ".implode(', ',$fields)." WHERE id = ?";
    $stmt=$db->prepare($sql);
    $params[]=$data['id'];
    $result=$stmt->execute($params);
    
    // TODO: sendResponse HTTP 200 on success, HTTP 500 on failure.
    if($result){
        sendResponse(['success'=>true],200);
    } else {
        sendResponse(['success'=>false,'message'=>'Update failed'],500);
    }
}


/**
 * Delete an assignment by integer id.
 */
function deleteAssignment(PDO $db, $id): void
{
    // TODO: Validate that $id is provided and numeric.
    if($id === null || $id === '' || !is_numeric($id)){
        sendResponse(['success'=>false,'message'=>'Invalid id'],400);
        return;
    }
    
    // TODO: Check that an assignment with this id exists.
    $stmt=$db->prepare("SELECT id FROM assignments WHERE id = ?");
    $stmt->execute([$id]);
    if(!$stmt->fetch(PDO::FETCH_ASSOC)){
        sendResponse(['success'=>false,'message'=>'Assignment not found'],404);
        return;
    }
    
    // TODO: DELETE FROM assignments WHERE id = ?
    $stmt=$db->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->execute([$id]);
    
    // TODO: If rowCount() > 0, sendResponse HTTP 200.
    if($stmt->rowCount() > 0){
        sendResponse(['success'=>true],200);
    } else {
        sendResponse(['success'=>false,'message'=>'Delete failed'],500);
    }
}


// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

/**
 * Get all comments for a specific assignment.
 */
function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    // TODO: Validate that $assignmentId is provided and numeric.
    if($assignmentId === null || $assignmentId === '' || !is_numeric($assignmentId)){
        sendResponse(['success'=>false,'message'=>'Invalid assignment_id'],400);
        return;
    }
    
    // TODO: SELECT id, assignment_id, author, text, created_at
    $sql="SELECT id, assignment_id, author, text, created_at FROM comments_assignment WHERE assignment_id = ? ORDER BY created_at ASC";
    $stmt=$db->prepare($sql);
    $stmt->execute([$assignmentId]);
    
    // TODO: Fetch all rows. Return sendResponse with the array
    $comments=$stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(['success'=>true,'data'=>$comments]);
}


/**
 * Create a new comment.
 */
function createComment(PDO $db, array $data): void
{
    // TODO: Validate that assignment_id, author, and text are all present
    $assignment_id=trim($data['assignment_id']??'');
    $author=trim($data['author']??'');
    $text=trim($data['text']??'');
    
    if($assignment_id==='' || $author==='' || $text===''){
        sendResponse(['success'=>false,'message'=>'Missing required fields'],400);
        return;
    }
    
    // TODO: Validate that assignment_id is numeric.
    if(!is_numeric($assignment_id)){
        sendResponse(['success'=>false,'message'=>'Invalid assignment_id'],400);
        return;
    }
    
    // TODO: Check that an assignment with this id exists in the assignments table.
    $stmt=$db->prepare("SELECT id FROM assignments WHERE id = ?");
    $stmt->execute([$assignment_id]);
    if(!$stmt->fetch(PDO::FETCH_ASSOC)){
        sendResponse(['success'=>false,'message'=>'Assignment not found'],404);
        return;
    }
    
    // TODO: INSERT INTO comments_assignment (assignment_id, author, text)
    $sql="INSERT INTO comments_assignment (assignment_id, author, text) VALUES (?, ?, ?)";
    $stmt=$db->prepare($sql);
    $stmt->execute([$assignment_id, $author, $text]);
    
    // TODO: If rowCount() > 0, sendResponse HTTP 201 with the new id
    if($stmt->rowCount() > 0){
        $id=$db->lastInsertId();
        sendResponse(['success'=>true,'data'=>['id'=>(int)$id,'assignment_id'=>(int)$assignment_id,'author'=>$author,'text'=>$text]],201);
    } else {
        sendResponse(['success'=>false,'message'=>'Insert failed'],500);
    }
}


/**
 * Delete a single comment.
 */
function deleteComment(PDO $db, $commentId): void
{
    // TODO: Validate that $commentId is provided and numeric.
    if($commentId === null || $commentId === '' || !is_numeric($commentId)){
        sendResponse(['success'=>false,'message'=>'Invalid comment_id'],400);
        return;
    }
    
    // TODO: Check that the comment exists in comments_assignment.
    $stmt=$db->prepare("SELECT id FROM comments_assignment WHERE id = ?");
    $stmt->execute([$commentId]);
    if(!$stmt->fetch(PDO::FETCH_ASSOC)){
        sendResponse(['success'=>false,'message'=>'Comment not found'],404);
        return;
    }
    
    // TODO: DELETE FROM comments_assignment WHERE id = ?
    $stmt=$db->prepare("DELETE FROM comments_assignment WHERE id = ?");
    $stmt->execute([$commentId]);
    
    // TODO: If rowCount() > 0, sendResponse HTTP 200.
    if($stmt->rowCount() > 0){
        sendResponse(['success'=>true],200);
    } else {
        sendResponse(['success'=>false,'message'=>'Delete failed'],500);
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {
    if ($method === 'GET') {
        // TODO: if $action === 'comments', call getCommentsByAssignment($db, $assignmentId)
        if($action==='comments'){
            getCommentsByAssignment($db,$assignmentId);
        }
        // TODO: elseif $id is set, call getAssignmentById($db, $id)
        elseif($id !== null){
            getAssignmentById($db,$id);
        }
        // TODO: else call getAllAssignments($db)
        else{
            getAllAssignments($db);
        }
    } elseif ($method === 'POST') {
        // TODO: if $action === 'comment', call createComment($db, $data)
        if($action==='comment'){
            createComment($db,$data);
        }
        // TODO: else call createAssignment($db, $data)
        else{
            createAssignment($db,$data);
        }
    } elseif ($method === 'PUT') {
        // Update an assignment; id comes from the JSON body
        // TODO: call updateAssignment($db, $data)
        updateAssignment($db,$data);
    } elseif ($method === 'DELETE') {
        // TODO: if $action === 'delete_comment', call deleteComment($db, $commentId)
        if($action==='delete_comment'){
            deleteComment($db,$commentId);
        }
        // TODO: else call deleteAssignment($db, $id)
        else{
            deleteAssignment($db,$id);
        }
    } else {
        // TODO: sendResponse HTTP 405 Method Not Allowed.
        sendResponse(['success'=>false,'message'=>'Method Not Allowed'],405);
    }

} catch (PDOException $e) {
    // TODO: Log the error with error_log().
    error_log($e);
    sendResponse(['success'=>false,'message'=>'Internal Server Error'],500);
} catch (Exception $e) {
    // TODO: Log the error with error_log().
    error_log($e);
    sendResponse(['success'=>false,'message'=>'Internal Server Error'],500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    // TODO: http_response_code($statusCode);
    http_response_code($statusCode);
    // TODO: echo json_encode($data, JSON_PRETTY_PRINT);
    echo json_encode($data, JSON_PRETTY_PRINT);
    // TODO: exit;
    exit;
}

function validateDate(string $date): bool
{
    // TODO: $d = DateTime::createFromFormat('Y-m-d', $date);
    $d=DateTime::createFromFormat('Y-m-d', $date);
    // TODO: return $d && $d->format('Y-m-d') === $date;
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    // TODO: return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>