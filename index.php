<?php
/**
 * IDAT Academy — REST API Router
 *
 * All requests are routed here via .htaccess.
 * Validates API key, sets CORS headers, then dispatches to the correct endpoint.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Bootstrap ---
$apiConfig = require __DIR__ . '/config/api_key.php';
require_once __DIR__ . '/helpers.php';

// --- API Key Validation ---
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';

if (!hash_equals($apiConfig['key'], $apiKey)) {
    jsonResponse(401, ['error' => 'Invalid or missing API key. Send it via X-API-Key header.']);
}

// --- Rate Limiting (simple file-based) ---
rateLimit($apiConfig['rate_limit'] ?? 60);

// --- Database Connection ---
$dbConfig = require __DIR__ . '/config/database.php';
$conn = connectDB($dbConfig);

// --- Route Parsing ---
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$apiPos = strrpos($uri, '/api/');
$uri = $apiPos !== false ? substr($uri, $apiPos + 5) : $uri;
$uri = rtrim($uri, '/');

$segments = array_values(array_filter(explode('/', $uri)));
$resource = $segments[0] ?? '';
$id       = $segments[1] ?? null;
$sub      = $segments[2] ?? null;

// --- Dispatch ---
$endpoints = [
    'courses'          => 'courses.php',
    'students'         => 'students.php',
    'enrollments'      => 'enrollments.php',
    'lessons'          => 'lessons.php',
    'assignments'      => 'assignments.php',
    'submissions'      => 'submissions.php',
    'payments'         => 'payments.php',
    'certificates'     => 'certificates.php',
    'notifications'    => 'notifications.php',
    'tutors'           => 'tutors.php',
    'applications'     => 'applications.php',
    'stats'            => 'stats.php',
    'settings'         => 'settings.php',
    'testimonials'     => 'testimonials.php',
    'gallery'          => 'gallery.php',
    'announcements'    => 'announcements.php',
    'contact_messages' => 'contact_messages.php',
];

if ($resource === '' || $resource === 'health') {
    jsonResponse(200, [
        'status'  => 'ok',
        'version' => '1.0.0',
        'time'    => date('c'),
    ]);
}

if (!isset($endpoints[$resource])) {
    jsonResponse(404, ['error' => "Resource '{$resource}' not found."]);
}

$handlerFile = __DIR__ . '/endpoints/' . $endpoints[$resource];
require_once $handlerFile;

// The endpoint file must define and call handleRequest()
handleRequest($conn, $method, $id, $sub);
