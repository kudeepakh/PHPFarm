<?php

// Simple health check endpoint that bypasses the framework for performance
// This should respond in milliseconds instead of 15 seconds

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only respond to GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Quick health check response
$response = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'version' => 'v1',
    'server' => 'PHPFrarm'
];

http_response_code(200);
echo json_encode($response);
exit;