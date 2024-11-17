<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$name = '%' . $_POST['name'] . '%';

$conn = db_connect();
$stmt = $conn->prepare("SELECT name, phone, referral_code FROM users WHERE name LIKE ? LIMIT 10");
$stmt->bind_param("s", $name);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = [
        'name' => $row['name'],
        'phone' => $row['phone'],
        'referral_code' => $row['referral_code']
    ];
}

echo json_encode($users);
?>