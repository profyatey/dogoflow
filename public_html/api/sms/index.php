<?php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
http_response_code(501);
echo json_encode(['success' => false, 'message' => 'SMS service will be implemented in Phase 8/9.']);
