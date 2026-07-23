<?php
/**
 * GET /submissions                              — List submissions (filterable)
 * GET /submissions/{id}                         — Single submission
 * GET /submissions?student_id=X                 — Submissions for a student
 * GET /submissions?assignment_id=X              — Submissions for an assignment
 * POST /submissions                             — Create a submission
 * PUT /submissions/{id}                         — Grade/update a submission
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    switch ($method) {
        case 'GET':
            $id !== null ? getSubmission($conn, $id) : listSubmissions($conn);
            break;
        case 'POST':
            createSubmission($conn);
            break;
        case 'PUT':
            if (!$id) jsonResponse(400, ['error' => 'Submission ID required.']);
            updateSubmission($conn, $id);
            break;
        default:
            jsonResponse(405, ['error' => 'Method not allowed.']);
    }
}

function listSubmissions(mysqli $conn): void
{
    $page         = max(1, (int) ($_GET['page'] ?? 1));
    $perPage      = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));
    $studentId    = $_GET['student_id'] ?? null;
    $assignmentId = $_GET['assignment_id'] ?? null;
    $graded       = $_GET['graded'] ?? null;

    $sql    = "SELECT asub.*, a.title AS assignment_title, a.max_score, a.due_date,
                      c.title AS course_title, c.slug AS course_slug,
                      s.first_name, s.last_name, s.email
               FROM assignment_submissions asub
               JOIN assignments a ON asub.assignment_id = a.id
               JOIN courses c ON a.course_id = c.id
               JOIN students s ON asub.student_id = s.id
               WHERE 1=1";
    $params = [];
    $types  = '';

    if ($studentId) {
        $sql .= " AND asub.student_id = ?";
        $params[] = (int) $studentId;
        $types   .= 'i';
    }
    if ($assignmentId) {
        $sql .= " AND asub.assignment_id = ?";
        $params[] = (int) $assignmentId;
        $types   .= 'i';
    }
    if ($graded !== null) {
        if ($graded === '1' || strtolower($graded) === 'true') {
            $sql .= " AND asub.score IS NOT NULL";
        } else {
            $sql .= " AND asub.score IS NULL";
        }
    }

    $sql .= " ORDER BY asub.submitted_at DESC";
    $pagination = applyPagination($conn, $sql, $params, $types, $page, $perPage);
    $rows = fetchAll($conn, $sql, $params, $types);

    jsonResponse(200, ['data' => $rows, 'pagination' => $pagination]);
}

function getSubmission(mysqli $conn, string $id): void
{
    $row = fetchOne($conn,
        "SELECT asub.*, a.title AS assignment_title, a.max_score, a.instructions AS assignment_instructions,
                c.title AS course_title,
                s.first_name, s.last_name, s.email
         FROM assignment_submissions asub
         JOIN assignments a ON asub.assignment_id = a.id
         JOIN courses c ON a.course_id = c.id
         JOIN students s ON asub.student_id = s.id
         WHERE asub.id = ? LIMIT 1",
        [(int) $id], 'i'
    );

    if (!$row) {
        jsonResponse(404, ['error' => 'Submission not found.']);
    }
    jsonResponse(200, ['data' => $row]);
}

function createSubmission(mysqli $conn): void
{
    $input = getInput();
    $assignmentId = $input['assignment_id'] ?? null;
    $studentId    = $input['student_id'] ?? null;

    if (!$assignmentId || !$studentId) {
        jsonResponse(422, ['error' => 'assignment_id and student_id are required.']);
    }

    $existing = fetchOne($conn,
        "SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?",
        [$assignmentId, $studentId], 'ii'
    );
    if ($existing) {
        jsonResponse(409, ['error' => 'Submission already exists for this assignment. Use PUT to update.']);
    }

    $filePath      = $input['file_path'] ?? null;
    $typedResponse = $input['typed_response'] ?? null;

    $result = execute($conn,
        "INSERT INTO assignment_submissions (assignment_id, student_id, file_path, typed_response) VALUES (?, ?, ?, ?)",
        [$assignmentId, $studentId, $filePath, $typedResponse], 'iiss'
    );

    jsonResponse(201, ['message' => 'Submission created.', 'id' => $result['insert_id']]);
}

function updateSubmission(mysqli $conn, string $id): void
{
    $input = getInput();

    $submission = fetchOne($conn, "SELECT * FROM assignment_submissions WHERE id = ?", [(int) $id], 'i');
    if (!$submission) {
        jsonResponse(404, ['error' => 'Submission not found.']);
    }

    $score    = $input['score'] ?? $submission['score'];
    $feedback = $input['feedback'] ?? $submission['feedback'];

    execute($conn,
        "UPDATE assignment_submissions SET score = ?, feedback = ?, graded_by = ?, graded_at = NOW() WHERE id = ?",
        [$score, $feedback, $input['graded_by'] ?? null, (int) $id], 'dsii'
    );

    jsonResponse(200, ['message' => 'Submission updated.']);
}
