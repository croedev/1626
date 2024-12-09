<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$field = isset($_POST['email']) ? 'email' : (isset($_POST['phone']) ? 'phone' : '');
$value = $field === 'email' ? $_POST['email'] : (isset($_POST['phone']) ? $_POST['phone'] : '');

if (empty($field) || empty($value)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$conn = db_connect();
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE $field = ?");
$stmt->bind_param("s", $value);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

echo json_encode(['duplicate' => $count > 0]);
?>