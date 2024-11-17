<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/commission_functions.php';

$conn = db_connect();

$last_calculation = getLastCalculationTime($conn);

// NFT 구매 내역만 삭제하는 함수 수정
function deleteNFTHistoryByOrderId($conn, $order_id) {
    try {
        $stmt = $conn->prepare("
            DELETE FROM nft_history 
            WHERE order_id = ? 
            AND transaction_type = '구매'
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        handleException($e, "NFT 구매 내역 삭제 오류");
    }
}

// NFT 최종 잔고 계산 함수
function updateNFTTokens($conn) {
    try {
        // 사용자별 NFT 토큰 수량 초기화하지 않음

        // nft_history 테이블 기반으로 최종 잔고 계산
        $stmt = $conn->prepare("
            UPDATE users u
            JOIN (
                SELECT 
                    to_user_id,
                    SUM(CASE 
                        WHEN transaction_type = '구매' THEN amount
                        WHEN transaction_type IN ('선물하기', '전송') THEN 
                            CASE 
                                WHEN from_user_id = to_user_id THEN -amount
                                ELSE amount
                            END
                        ELSE 0
                    END) as final_amount
                FROM nft_history
                GROUP BY to_user_id
            ) calc ON u.id = calc.to_user_id
            SET u.nft_token = GREATEST(COALESCE(calc.final_amount, 0), 0)
        ");
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        handleException($e, "NFT 토큰 잔고 업데이트 오류");
        throw $e;
    }
}

// 수수료 계산 실행
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['calculate'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    $conn->begin_transaction();

    try {
        // 1. 초기화 단계
        // 1.1 사용자 데이터 초기화
        $stmt = $conn->prepare("
            UPDATE users
            SET myQuantity = 0,
                myTotal_quantity = 0,
                myAmount = 0,
                myTotal_Amount = 0,
                commission_total = 0,
                cash_points = 0,
                mileage_points = 0,
                myAgent = 0,
                myAgent_referral = 0,
                rank = '회원',
                rank_update_date = NULL
        ");
        $stmt->execute();

        // 1.2 pricing_tiers 초기화
        $stmt = $conn->prepare("
            UPDATE pricing_tiers 
            SET sold_quantity = 0,
                remaining_quantity = total_quantity
            WHERE id = (SELECT MAX(id) FROM pricing_tiers)
        ");
        $stmt->execute();

        // 1.3 구매 관련 NFT 히스토리만 삭제
        $stmt = $conn->prepare("
            DELETE FROM nft_history 
            WHERE transaction_type = '구매' 
            AND order_id IN (
                SELECT id FROM orders 
                WHERE created_at BETWEEN ? AND ?
            )
        ");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();

        // 2. 해당 기간의 주문 가져오기 (시간순)
        $stmt = $conn->prepare("
            SELECT id
            FROM orders
            WHERE status = 'completed'
            AND created_at BETWEEN ? AND ?
            ORDER BY created_at ASC
        ");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $order_ids = [];
        while ($row = $result->fetch_assoc()) {
            $order_ids[] = $row['id'];
        }
        $stmt->close();

        if (!empty($order_ids)) {
            $order_ids_placeholder = implode(',', array_fill(0, count($order_ids), '?'));
            $types = str_repeat('i', count($order_ids));

            // 2.1 수수료 내역 삭제
            $stmt = $conn->prepare("DELETE FROM commissions WHERE order_id IN ($order_ids_placeholder)");
            $stmt->bind_param($types, ...$order_ids);
            $stmt->execute();
            $stmt->close();

            // 2.2 직급 이력 삭제
            $stmt = $conn->prepare("
                DELETE FROM rank_history 
                WHERE user_id IN (
                    SELECT user_id FROM orders WHERE id IN ($order_ids_placeholder)
                )
            ");
            $stmt->bind_param($types, ...$order_ids);
            $stmt->execute();
            $stmt->close();
        }

        // 3. 주문별 재계산
        $processed_orders = 0;
        $failed_orders = 0;

        foreach ($order_ids as $order_id) {
            try {
                // 기존 NFT 구매 내역 삭제
                deleteNFTHistoryByOrderId($conn, $order_id);

                // 주문 정보 가져오기
                $order = getOrderById($conn, $order_id);
                if (!$order) {
                    throw new Exception("주문 정보를 찾을 수 없습니다. 주문 ID: {$order_id}");
                }

                // 수수료 계산 및 새로운 NFT 구매 내역 생성
                calculateAndProcessCommissions($conn, $order_id);

                // 직급 계산
                updateUserRank($conn, $order['user_id']);

                $processed_orders++;
            } catch (Exception $e) {
                $failed_orders++;
                error_log("주문 처리 실패 (Order ID: $order_id): " . $e->getMessage());
            }
        }

        // 4. 최종 NFT 잔고 계산 (구매 + 이동 내역 반영)
        updateNFTTokens($conn);

        // 5. 계산 이력 저장
        $stmt = $conn->prepare("
            INSERT INTO commission_calculations 
            (calculation_time, start_date, end_date, processed_orders, failed_orders)
            VALUES (NOW(), ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssii", $start_date, $end_date, $processed_orders, $failed_orders);
        $stmt->execute();

        $conn->commit();
        $message = "수수료 재계산이 완료되었습니다. 처리된 주문: $processed_orders, 실패한 주문: $failed_orders";

    } catch (Exception $e) {
        $conn->rollback();
        $error = "수수료 재계산 중 오류가 발생했습니다: " . $e->getMessage();
        error_log($error);
    }
}

// 수수료 계산 이력 가져오기
$calculation_history = getCalculationHistory($conn);

$pageTitle = '수수료 계산 관리';

require_once __DIR__ . '/admin_header.php';
?>

<!-- 여기서부터 HTML 부분 -->
<style>
    .admin-container {
        padding: 20px;
        background-color: #000;
        color: #d4af37;
        font-family: 'Noto Sans KR', sans-serif;
    }
    .admin-card {
        background-color: #222;
        border-radius: 5px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .admin-form {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    .admin-form input[type="date"], .admin-form button {
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #d4af37;
    }
    .admin-form button {
        background-color: #d4af37;
        color: #000;
        cursor: pointer;
    }
    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }
    .admin-table th, .admin-table td {
        border: 1px solid #333;
        padding: 10px;
        text-align: left;
    }
    .admin-table th {
        background-color: #333;
    }
</style>

<div class="admin-container">
    <h3>수수료 계산 관리</h3>

    <div class="admin-card">
        <h2>수수료 계산 실행</h2>
        <form class="admin-form" method="POST">
            <input type="date" name="start_date" required>
            <input type="date" name="end_date" required>
            <button type="submit" name="calculate">수수료 계산 실행</button>
        </form>
        <?php if (isset($message)): ?>
            <p class="success"><?php echo $message; ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <p>마지막 계산 일시: <?php echo $last_calculation; ?></p>
    </div>

    <div class="admin-card">
        <h2>수수료 계산 이력</h2>
        <?php if (!empty($calculation_history)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>계산 일시</th>
                        <th>시작 날짜</th>
                        <th>종료 날짜</th>
                        <th>처리된 주문</th>
                        <th>실패한 주문</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calculation_history as $history): ?>
                    <tr>
                        <td><?php echo $history['calculation_time']; ?></td>
                        <td><?php echo $history['start_date']; ?></td>
                        <td><?php echo $history['end_date']; ?></td>
                        <td><?php echo $history['processed_orders']; ?></td>
                        <td><?php echo $history['failed_orders']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>수수료 계산 이력이 없습니다.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
