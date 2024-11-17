<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/nft_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['search'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$search_term = $_POST['search'];
$conn = db_connect();

$users = searchUsers($conn, $search_term);

echo json_encode($users);