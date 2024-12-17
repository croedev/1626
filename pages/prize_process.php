<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');
?>

<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = db_connect();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('잘못된 요청입니다.');
    }

    if (!isset($input['action']) || $input['action'] !== 'apply') {
        throw new Exception('잘못된 요청입니다.');
    }

    $conn->begin_transaction();

    // 응모 가능 여부 확인
    $stmt = $conn->prepare("SELECT myAmount, myPrize_used FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        throw new Exception('사용자 정보를 찾을 수 없습니다.');
    }

    // 응모 가능 금액 계산
    $available_amount = min($user['myAmount'] - $user['myPrize_used'], 2000000);
    if ($available_amount < 400000) {
        throw new Exception('응모 가능 금액이 부족합니다.');
    }

    // 전체 응모 수 확인
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM prize_apply WHERE apply_status != 'cancelled'");
    $stmt->execute();
    $total_entries = $stmt->get_result()->fetch_assoc()['count'];

    if ($total_entries >= 50) {
        throw new Exception('응모가 마감되었습니다.');
    }

    // 현재 사용자의 응모 횟수 확인
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM prize_apply WHERE user_id = ? AND apply_status != 'cancelled'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_entries = $stmt->get_result()->fetch_assoc()['count'];

    if ($user_entries >= 5) {
        throw new Exception('최대 응모 횟수를 초과했습니다.');
    }

    // 응모 기록 추가
    $stmt = $conn->prepare("
        INSERT INTO prize_apply (
            user_id, 
            apply_amount, 
            apply_count,
            apply_status,
            win_status,
            ip_address
        ) VALUES (?, 400000, ?, 'pending', 'pending', ?)
    ");
    
    $entry_count = $user_entries + 1;
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("iis", $user_id, $entry_count, $ip_address);
    
    if (!$stmt->execute()) {
        throw new Exception('응모 처리 중 오류가 발생했습니다.');
    }

    // 사용자의 응모 금액과 횟수 업데이트
    $stmt = $conn->prepare("
        UPDATE users 
        SET myPrize_used = myPrize_used + 400000,
            myPrize_count = myPrize_count + 1 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('사용자 정보 업데이트 중 오류가 발생했습니다.');
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => '응모가 완료되었습니다.'
    ]);

} catch (Exception $e) {
    if ($conn) {
        $conn->rollback();
    }
    error_log("Prize Apply Error - User: $user_id - " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if ($conn) {
        $conn->close();
    }
}