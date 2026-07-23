<?php
/**
 * GET /stats       — Platform-wide dashboard statistics
 * GET /stats?dashboard=summary
 */

function handleRequest(mysqli $conn, string $method, ?string $id, ?string $sub): void
{
    if ($method !== 'GET') {
        jsonResponse(405, ['error' => 'Method not allowed.']);
    }

    getStats($conn);
}

function getStats(mysqli $conn): void
{
    $stats = [];

    $stats['students'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM students) AS total,
            (SELECT COUNT(*) FROM students WHERE status = 'active') AS active,
            (SELECT COUNT(*) FROM students WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS new_this_month"
    );

    $stats['courses'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM courses) AS total,
            (SELECT COUNT(*) FROM courses WHERE status = 'active') AS active,
            (SELECT COUNT(*) FROM course_modules) AS total_modules"
    );

    $stats['enrollments'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM student_courses) AS total,
            (SELECT COUNT(*) FROM student_courses WHERE status = 'enrolled') AS active,
            (SELECT COUNT(*) FROM student_courses WHERE status = 'completed') AS completed"
    );

    $stats['tutors'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM tutors) AS total,
            (SELECT COUNT(*) FROM tutors WHERE status = 'active') AS active"
    );

    $stats['payments'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM payments) AS total,
            (SELECT COUNT(*) FROM payments WHERE status = 'confirmed') AS confirmed,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'confirmed') AS total_revenue,
            (SELECT COUNT(*) FROM payments WHERE status = 'pending') AS pending"
    );

    $stats['applications'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM applications) AS total,
            (SELECT COUNT(*) FROM applications WHERE status = 'pending') AS pending,
            (SELECT COUNT(*) FROM applications WHERE status = 'approved') AS approved,
            (SELECT COUNT(*) FROM applications WHERE status = 'rejected') AS rejected"
    );

    $stats['assignments'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM assignments) AS total,
            (SELECT COUNT(*) FROM assignment_submissions) AS total_submissions,
            (SELECT COUNT(*) FROM assignment_submissions WHERE score IS NOT NULL) AS graded"
    );

    $stats['certificates'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM certificates) AS total,
            (SELECT COUNT(*) FROM certificates WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS issued_this_month"
    );

    $stats['notifications'] = fetchOne($conn,
        "SELECT
            (SELECT COUNT(*) FROM notifications) AS total,
            (SELECT COUNT(*) FROM notifications WHERE is_read = 0) AS unread"
    );

    jsonResponse(200, ['data' => $stats]);
}
