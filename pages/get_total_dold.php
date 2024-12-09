<?php
require_once 'includes/config.php';

$conn = db_connect();
$total_sold = getTotalSoldQuantity($conn);

header('Content-Type: application/json');
echo json_encode(['total_sold' => $total_sold]);
?>
