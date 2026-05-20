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
 *   id          INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   title       VARCHAR(200)  NOT NULL
 *   description TEXT
 *   due_date    DATE          NOT NULL
 *   files       TEXT          — JSON-encoded array of file URL strings
 *   created_at  TIMESTAMP
 *   updated_at  TIMESTAMP     — updated automatically by MySQL ON UPDATE
 *
 * Table: comments_assignment
 *   id            INT UNSIGNED  PRIMARY KEY AUTO_INCREMENT
 *   assignment_id INT UNSIGNED  NOT NULL — FK → assignments.id (ON DELETE CASCADE)
 *   author        VARCHAR(100)  NOT NULL
 *   text          TEXT          NOT NULL
 *   created_at    TIMESTAMP
 *
 * HTTP Methods Supported:
 *   GET    — Retrieve assignment(s) or comments
 *   POST   — Create a new assignment or comment
 *   PUT    — Update an existing assignment
 *   DELETE — Delete an assignment (cascade removes its comments) or a comment
 *
 * URL scheme (all requests go to index.php):
 *
 *   Assignments:
 *     GET    ./api/index.php                  — list all assignments
 *     GET    ./api/index.php?id={id}           — get one assignment by integer id
 *     POST   ./api/index.php                  — create a new assignment
 *     PUT    ./api/index.php                  — update an assignment (id in JSON body)
 *     DELETE ./api/index.php?id={id}           — delete an assignment
 *
 *   Comments (action parameter selects the comments sub-resource):
 *     GET    ./api/index.php?action=comments&assignment_id={id}
 *                                             — list comments for an assignment
 *     POST   ./api/index.php?action=comment   — create a comment
 *     DELETE ./api/index.php?action=delete_comment&comment_id={id}
 *                                             — delete a single comment
 *
 * Query parameters for GET all assignments:
 *   search — filter rows where title LIKE or description LIKE the term
 *   sort   — column to sort by; allowed: title, due_date, created_at
 *            (default: due_date)
 *   order  — sort direction; allowed: asc, desc (default: asc)
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

// TODO: Set headers for JSON response and CORS.
// Set Content-Type to application/json.
// Allow cross-origin requests (CORS) if needed.
// Allow HTTP methods: GET, POST, PUT, DELETE, OPTIONS.
// Allow headers: Content-Type, Authorization.
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// TODO: Handle preflight OPTIONS request.
// If the request method is OPTIONS, return HTTP 200 and exit.
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){
    http_response_code(200);
    exit();
}

// TODO: Include the shared database connection file.
// require_once __DIR__ . '/../../common/db.php';
require_once __DIR__ . '/../../common/db.php';

// TODO: Get the PDO database connection.
// $db = getDBConnection();
$db = getDBConnection();

// TODO: Read the HTTP request method.
// $method = $_SERVER['REQUEST_METHOD'];
$method = $_SERVER['REQUEST_METHOD'];

// TODO: Read and decode the request body for POST and PUT requests.
// $rawData = file_get_contents('php://input');
// $data    = json_decode($rawData, true) ?? [];
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

// TODO: Read query parameters.
// $action       = $_GET['action']        ?? null;  // 'comments', 'comment', 'delete_comment'
// $id           = $_GET['id']            ?? null;  // integer assignment id
// $assignmentId = $_GET['assignment_id'] ?? null;  // integer assignment id for comments queries
// $commentId    = $_GET['comment_id']    ?? null;  // integer comment id
$action       = $_GET['action']        ?? null;  // 'comments', 'comment', 'delete_comment'
$id           = $_GET['id']            ?? null;  // integer assignment id
$assignmentId = $_GET['assignment_id'] ?? null;  // integer assignment id for comments queries
$commentId    = $_GET['comment_id']    ?? null;  // integer comment id

// ============================================================================
// ASSIGNMENT FUNCTIONS
// ============================================================================

/**
 * Get all assignments (with optional search and sort).
 * Method: GET (no ?id or ?action parameter).
 *
 * Query parameters handled inside:
 *   search — filter by title LIKE or description LIKE
 *   sort   — allowed: title, due_date, created_at   (default: due_date)
 *   order  — allowed: asc, desc                     (default: asc)
 *
 * Each assignment row in the response has the files column decoded from
 * its JSON string to a PHP array before encoding the final JSON output.
 */
function getAllAssignments(PDO $db): void
{
    // TODO: Build the base SELECT query.
    // SELECT id, title, description, due_date, files, created_at, updated_at
    // FROM assignments
    $sql="SELECT id, title, description, due_date, files, created_at, updated_at FROM assignments";
    // TODO: If $_GET['search'] is provided and non-empty, append:
    // WHERE title LIKE :search OR description LIKE :search
    // Bind '%' . $search . '%' to :search.
    $search=null;
    if(!empty($_GET['search'])){
        $search=$_GET['search'];
        $sql.=" WHERE title LIKE :search OR description LIKE :search";
    }

    // TODO: Validate $_GET['sort'] against the whitelist
    // [title, due_date, created_at].
    // Default to 'due_date' if missing or invalid.
    $allowedSort=['title', 'due_date', 'created_at'];
    $sort=$_GET['sort']??'due_date';
    if(!in_array($sort, $allowedSort)){
        $sort='due_date';
    }
    // TODO: Validate $_GET['order'] against [asc, desc].
    // Default to 'asc' if missing or invalid.
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
    // $row['files'] = json_decode($row['files'], true) ?? [];
    foreach($assignments as &$row){
        $row['files']=json_decode($row['files'],true)??[];
    }
    unset($row);
    // TODO: Call sendResponse(['success' => true, 'data' => $assignments]);
    sendResponse(['success'=>true,'data'=>$assignments]);
}


/**
 * Get a single assignment by its integer primary key.
 * Method: GET with ?id={id}.
 *
 * Response (found):
 *   { "success": true, "data": { id, title, description, due_date,
 *                                 files, created_at, updated_at } }
 * Response (not found): HTTP 404.
 */
function getAssignmentById(PDO $db, $id): void
{
    // TODO: Validate that $id is provided and numeric.
    // If not, call sendResponse with HTTP 400.
    if(!isset($id) || !is_numeric($id)){
        sendResponse(['success'=>false,'message'=>'Invalid assignment ID'],400);
        return;
    }
    // TODO: SELECT id, title, description, due_date, files,
    //       created_at, updated_at FROM assignments WHERE id = ?
    $sql="SELECT id,title,description,due_date,files,created_at,updated_at FROM assignments WHERE id = ?";
    // TODO: Fetch one row. Decode the files JSON:
    // $assignment['files'] = json_decode($assignment['files'], true) ?? [];
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
        return;
    }
    else{
        sendResponse(['success'=>false,'message'=>'Assignment not found'],404);
        return;
    }
}


/**
 * Create a new assignment.
 * Method: POST (no ?action parameter).
 *
 * Required JSON body fields:
 *   title       — string (required)
 *   description — string (required)
 *   due_date    — string "YYYY-MM-DD" (required)
 *   files       — array of URL strings (optional, defaults to [])
 *
 * Response (success): HTTP 201 — { success, message, id }
 * Response (missing fields or invalid due_date): HTTP 400.
 *
 * Note: id, created_at, and updated_at are handled automatically by MySQL.
 */
function createAssignment(PDO $db, array $data): void
{
    // TODO: Validate that title, description, and due_date are present
    // and non-empty. If missing, sendResponse HTTP 400.
    if(empty($data['title'])||empty($data['description'])||empty($data['due_date'])){
        sendResponse(['success'=>false,'message'=>'Missing required fields'],400);
    }
    // TODO: Trim title, description, and due_date.
    $title=trim($data['title']);
    $description=trim($data['description']);
    $due_date=trim($data['due_date']);
    // TODO: Validate due_date format using
    // DateTime::createFromFormat('Y-m-d', $due_date).
    // If invalid, sendResponse HTTP 400.
    $date=DateTime::createFromFormat('Y-m-d',$due_date);
    if(!$date||$date->format('Y-m-d')!==$due_date){
        sendResponse(['success'=>false,'message'=>'Invalid due date format'],400);
    }
    // TODO: Handle files: if provided and is an array, json_encode it.
    // Otherwise use json_encode([]).
    $files=isset($data['files'])&&is_array($data['files'])?json_encode($data['files']):json_encode([]);
    // TODO: INSERT INTO assignments (title, description, due_date, files)
    //       VALUES (?, ?, ?, ?)
    // Note: id, created_at, and updated_at are set automatically by MySQL.
    $sql="INSERT INTO assignments(title,description,due_date,files)VALUES(?,?,?,?)";
    $stmt=$db->prepare($sql);
    $stmt->execute([$title,$description,$due_date,$files]);
    // TODO: If rowCount() > 0, sendResponse HTTP 201 with the new integer id
    // from $db->lastInsertId().
    // Otherwise sendResponse HTTP 500.
    if($stmt->rowCount()>0){
        sendResponse(['success'=>true,'id'=>(int)$db->lastInsertId()],201);
    }
    else{
        sendResponse(['success'=>false,'message'=>'Insert failed'],500);
    }
}


/**
 * Update an existing assignment.
 * Method: PUT.
 *
 * Required JSON body:
 *   id — integer primary key of the assignment to update (required).
 * Optional JSON body fields (at least one must be present):
 *   title, description, due_date, files.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 * Response (invalid due_date): HTTP 400.
 *
 * Note: updated_at is refreshed automatically by MySQL ON UPDATE CURRENT_TIMESTAMP.
 */
function updateAssignment(PDO $db, array $data): void
{
    // TODO: Validate that $data['id'] is present.
    // If not, sendResponse HTTP 400.
    if (!isset($data['id']) || empty($data['id'])){
        sendResponse(['success'=>false,'message'=>'Missing id'],400);
        return;
    }
    // TODO: Check that an assignment with this id exists.
    // If not, sendResponse HTTP 404.
    $stmt=$db->prepare("SELECT id FROM assignments WHERE id=?");
    $stmt->execute([$data['id']]);
    if(!$stmt->fetch(PDO::FETCH_ASSOC)){
        sendResponse(['success'=>false,'message'=>'Assignment not found'],404);
        return;
    }
    // TODO: Dynamically build the SET clause for whichever of
    // title, description, due_date, files are present in $data.
    // - If due_date is included, validate its format.
    // - If files is included, json_encode it.
    $fields=[];
    $params=[];
    if(isset($data['title'])){
        $fields[]="title=?";
        $params[]=trim($data['title']);
    }
    if(isset($data['description'])){
        $fields[]="description=?";
        $params[]=trim($data['description']);
    }
    if(isset($data['due_date'])){
        $due_date=trim($data['due_date']);
        $date=DateTime::createFromFormat('Y-m-d',$due_date);
        if(!$date||$date->format('Y-m-d')!==$due_date){
            sendResponse(['success'=>false,'message'=>'Invalid due date format'],400);
            return;
        }
        $fields[]="due_date=?";
        $params[]=$due_date;
    }
    if(isset($data['files'])){
        $files = json_encode($data['files']);
        $fields[]="files=?";
        $params[]=$files;
    }
    // TODO: If no updatable fields are present, sendResponse HTTP 400.
    if(empty($fields)){
        sendResponse(['success'=>false,'message'=>'No fields to update'],400);
        return;
    }
    // TODO: updated_at is refreshed automatically by MySQL
    //       (ON UPDATE CURRENT_TIMESTAMP) — no need to set it manually.

    // TODO: Build: UPDATE assignments SET {clauses} WHERE id = ?
    // Prepare, bind all SET values, then bind id, and execute.
    $sql="UPDATE assignments SET ".implode(',',$fields)." WHERE id=?";
    $stmt=$db->prepare($sql);
    $params[]=$data['id'];
    $result=$stmt->execute($params);
    // TODO: sendResponse HTTP 200 on success, HTTP 500 on failure.
    if($result){
        sendResponse(['success'=>true],200);
    }
    else{
        sendResponse(['success'=>false,'message'=>'Update failed'],500);
    }
}


/**
 * Delete an assignment by integer id.
 * Method: DELETE with ?id={id}.
 *
 * The ON DELETE CASCADE constraint on comments_assignment.assignment_id
 * automatically removes all comments for this assignment — no manual
 * deletion of comments is needed.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteAssignment(PDO $db, $id): void
{
    // TODO: Validate that $id is provided and numeric.
    // If not, sendResponse HTTP 400.
    if(empty($id)||!is_numeric($id)){
        sendResponse(['success'=>false,'message'=>'Invalid id'],400);
    }
    // TODO: Check that an assignment with this id exists.
    // If not, sendResponse HTTP 404.
    $stmt=$db->prepare("SELECT id FROM assignments WHERE id=?");
    $stmt->execute([$id]);
    if(!$stmt->fetch(PDO::FETCH_ASSOC)){
        sendResponse(['success'=>false,'message'=>'Assignment not found'],404);
    }
    // TODO: DELETE FROM assignments WHERE id = ?
    // (comments_assignment rows are removed automatically by ON DELETE CASCADE.)
    $stmt=$db->prepare("DELETE FROM assignments WHERE id=?");
    $stmt->execute([$id]);
    // TODO: If rowCount() > 0, sendResponse HTTP 200.
    // Otherwise sendResponse HTTP 500.
    if($stmt->rowCount()>0){
        sendResponse(['success'=>true],200);
    }
    else{
        sendResponse(['success'=>false,'message'=>'Delete failed'],500);
    }
}


// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

/**
 * Get all comments for a specific assignment.
 * Method: GET with ?action=comments&assignment_id={id}.
 *
 * Reads from the comments_assignment table.
 * Returns an empty data array if no comments exist — not an error.
 *
 * Each comment object: { id, assignment_id, author, text, created_at }
 */
function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    // TODO: Validate that $assignmentId is provided and numeric.
    // If not, sendResponse HTTP 400.
    if(empty($assignmentId)||!is_numeric($assignmentId)){
        sendResponse(['success'=>false,'message'=>'Invalid assignment_id'],400);
    }
    // TODO: SELECT id, assignment_id, author, text, created_at
    //       FROM comments_assignment
    //       WHERE assignment_id = ?
    //       ORDER BY created_at ASC
    $sql="SELECT id,assignment_id,author,text,created_at FROM comments_assignment WHERE assignment_id=? ORDER BY created_at ASC";
    $stmt=$db->prepare($sql);
    $stmt->execute([$assignmentId]);
    // TODO: Fetch all rows. Return sendResponse with the array
    //       (empty array is valid).
    $comments=$stmt->fetchAll(PDO::FETCH_ASSOC);
    sendResponse(['success'=>true,'data'=>$comments]);
}


/**
 * Create a new comment.
 * Method: POST with ?action=comment.
 *
 * Required JSON body:
 *   assignment_id — integer FK into assignments.id (required)
 *   author        — string (required)
 *   text          — string (required, must be non-empty after trim)
 *
 * Response (success): HTTP 201 — { success, message, id, data: comment }
 * Response (assignment not found): HTTP 404.
 * Response (missing fields): HTTP 400.
 */
function createComment(PDO $db, array $data): void
{
    // TODO: Validate that assignment_id, author, and text are all present
    // and non-empty after trimming. If any are missing, sendResponse HTTP 400.
    $assignment_id=trim($data['assignment_id']??'');
    $author=trim($data['author']??'');
    $text=trim($data['text']??'');
    if(empty($assignment_id)||empty($author)||empty($text)){
        sendResponse(['success'=>false,'message'=>'Missing required fields'],400);
    }
    // TODO: Validate that assignment_id is numeric.
    if(!is_numeric($assignment_id)){
        sendResponse(['success'=>false,'message'=>'Invalid assignment_id'],400);
    }
    // TODO: Check that an assignment with this id exists in the assignments
    // table. If not, sendResponse HTTP 404.
    $stmt=$db->prepare("SELECT id FROM assignments WHERE id=?");
    $stmt->execute([$assignment_id]);
    if(!$stmt->fetch(PDO::FETCH_ASSOC)){
        sendResponse(['success'=>false,'message'=>'Assignment not found'],404);
    }
    // TODO: INSERT INTO comments_assignment (assignment_id, author, text)
    //       VALUES (?, ?, ?)
    $sql="INSERT INTO comments_assignment(assignment_id,author,text)VALUES(?,?,?)";
    $stmt=$db->prepare($sql);
    $stmt->execute([$assignment_id,$author,$text]);
    // TODO: If rowCount() > 0, sendResponse HTTP 201 with the new id
    //       and the full new comment object.
    // Otherwise sendResponse HTTP 500.
    if($stmt->rowCount()>0){
        $id=$db->lastInsertId();
        sendResponse(['success'=>true,'data'=>['id'=>$id,'assignment_id'=>$assignment_id,'author'=>$author,'text'=>$text]],201);
    }
    else{
        sendResponse(['success'=>false,'message'=>'Insert failed'],500);
    }
}


/**
 * Delete a single comment.
 * Method: DELETE with ?action=delete_comment&comment_id={id}.
 *
 * Response (success): HTTP 200.
 * Response (not found): HTTP 404.
 */
function deleteComment(PDO $db, $commentId): void
{
    // TODO: Validate that $commentId is provided and numeric.
    // If not, sendResponse HTTP 400.
    if(empty($commentId)||!is_numeric($commentId)){
        sendResponse(['success'=>false,'message'=>'Invalid comment_id'],400);
        return;
    }
    // TODO: Check that the comment exists in comments_assignment.
    // If not, sendResponse HTTP 404.
    $stmt=$db->prepare("SELECT id FROM comments_assignment WHERE id=?");
    $stmt->execute([$commentId]);
    if(!$stmt->fetch(PDO::FETCH_ASSOC)){
        sendResponse(['success'=>false,'message'=>'Comment not found'],404);
        return;
    }
    // TODO: DELETE FROM comments_assignment WHERE id = ?
    $stmt=$db->prepare("DELETE FROM comments_assignment WHERE id=?");
    $stmt->execute([(int)$commentId]);
    // TODO: If rowCount() > 0, sendResponse HTTP 200.
    // Otherwise sendResponse HTTP 500.
    if($stmt->rowCount()>0){
        sendResponse(['success'=>true],200);
        return;
    }
    else{
        sendResponse(['success'=>false,'message'=>'Delete failed'],500);
        return;
    }
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        // ?action=comments&assignment_id={id} → list comments for an assignment
        // TODO: if $action === 'comments', call getCommentsByAssignment($db, $assignmentId)
        if($action==='comments'){
            getCommentsByAssignment($db,$assignmentId);
        }
        // ?id={id} → single assignment
        // TODO: elseif $id is set, call getAssignmentById($db, $id)
        elseif($id){
            getAssignmentById($db,$id);
        }
        // no parameters → all assignments (supports ?search, ?sort, ?order)
        // TODO: else call getAllAssignments($db)
        else{
            getAllAssignments($db);
        }
    } elseif ($method === 'POST') {

        // ?action=comment → create a comment in comments_assignment
        // TODO: if $action === 'comment', call createComment($db, $data)
        if($action==='comment'){
            createComment($db,$data);
        }
        // no action → create a new assignment
        // TODO: else call createAssignment($db, $data)
        else{
            createAssignment($db,$data);
        }
    } elseif ($method === 'PUT') {// TODO: Validate that $assignmentId is provided and numeric.
    // If not, sendResponse HTTP 400.
        if(empty($assignmentId)||!is_numeric($assignmentId)){
            sendResponse(['success'=>false,'message'=>'Invalid assignment_id'],400);
        }
        // Update an assignment; id comes from the JSON body
        // TODO: call updateAssignment($db, $data)
        updateAssignment($db,$data);
    } elseif ($method === 'DELETE') {

        // ?action=delete_comment&comment_id={id} → delete one comment
        // TODO: if $action === 'delete_comment', call deleteComment($db, $commentId)
        if($action==='delete_comment'){
            deleteComment($db,$commentId);
        }
        // ?id={id} → delete an assignment (and its comments via CASCADE)
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
    // Return a generic HTTP 500 — do NOT expose $e->getMessage() to clients.
    error_log($e);
    sendResponse(['success'=>false,'message'=>'Internal Server Error'],500);
} catch (Exception $e) {
    // TODO: Log the error with error_log().
    // Return HTTP 500 using sendResponse().
    error_log($e);
    sendResponse(['success'=>false,'message'=>'Internal Server Error'],500);
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Send a JSON response and stop execution.
 *
 * @param array $data        Must include a 'success' key.
 * @param int   $statusCode  HTTP status code (default 200).
 */
function sendResponse(array $data, int $statusCode = 200): void
{
    // TODO: http_response_code($statusCode);
    http_response_code($statusCode);
    // TODO: echo json_encode($data, JSON_PRETTY_PRINT);
    echo json_encode($data, JSON_PRETTY_PRINT);
    // TODO: exit;
    exit;
}


/**
 * Validate a date string against the "YYYY-MM-DD" format.
 *
 * @param  string $date
 * @return bool  True if valid, false otherwise.
 */
function validateDate(string $date): bool
{
    // TODO: $d = DateTime::createFromFormat('Y-m-d', $date);
    $d=DateTime::createFromFormat('Y-m-d', $date);
    // TODO: return $d && $d->format('Y-m-d') === $date;
    return $d && $d->format('Y-m-d') === $date;
}


/**
 * Sanitize a string input.
 *
 * @param  string $data
 * @return string  Trimmed, tag-stripped, HTML-encoded string.
 */
function sanitizeInput(string $data): string
{
    // TODO: return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
