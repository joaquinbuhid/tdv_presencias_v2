<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';

requireAdminRealApi();

echo json_encode([]);
