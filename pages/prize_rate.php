

<?php
header('Content-Type: application/json');
require_once 'includes/config.php';

$conn = db_connect();

try {
    // 총 응모 건수 조회
    $total_entries_query = $conn->query("
        SELECT COUNT(*) as count 
        FROM prize_apply 
        WHERE apply_status != 'cancelled'
    ");
    $total_entries = $total_entries_query->fetch_assoc()['count'];
    
    // 총 상품 수
    $total_prizes = 50;
    
    // 남은 상품 수
    $remaining_prizes = max(0, $total_prizes - $total_entries);
    
    // 경쟁률 계산
    $competition_rate = $total_entries > 0 ? round($total_entries / $total_prizes, 1) : 0;

    // 응답 데이터
    $response = [
        'total_entries' => (int)$total_entries,
        'total_prizes' => $total_prizes,
        'remaining_prizes' => $remaining_prizes,
        'competition_rate' => $competition_rate
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Prize Rate Error: " . $e->getMessage());
    echo json_encode([
        'error' => true,
        'message' => '경쟁률 정보를 가져오는 중 오류가 발생했습니다.'
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
}