<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once '../includes/config.php';
require_once '../includes/commission_functions.php';



$conn = db_connect();

// 입금 확인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    $order_id = $_POST['order_id'];
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("UPDATE orders SET status = 'completed', payment_date = NOW(), nft_token = quantity WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();

        $stmt = $conn->prepare("SELECT user_id, quantity FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();

        // NFT 토큰 업데이트
        // $stmt = $conn->prepare("UPDATE users SET nft_token = nft_token + ? WHERE id = ?");
        // $stmt->bind_param("ii", $order['quantity'], $order['user_id']);
        // $stmt->execute();

        // pricing_tiers 테이블 업데이트
        // $stmt = $conn->prepare("UPDATE pricing_tiers SET sold_quantity = sold_quantity + ?, remaining_quantity = total_quantity - (sold_quantity + ?) WHERE id = (SELECT MAX(id) FROM pricing_tiers)");
        // $stmt->bind_param("ii", $order['quantity'], $order['quantity']);
        // $stmt->execute();

        // 수수료 계산 및 직급 계산

        calculateAndProcessCommissions($conn, $order_id);
        updateUserRank($conn, $order['user_id']);

        $conn->commit();
        $_SESSION['success_message'] = "주문 #$order_id 결제가 확인되었습니다.";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "오류가 발생했습니다: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 주문 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $order_id = $_POST['order_id'];
    
    $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $_SESSION['success_message'] = "주문 #$order_id 가 삭제되었습니다.";
    } else {
        $_SESSION['error_message'] = "주문 삭제에 실패했습니다.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// 검색 조건 처리
$conditions = [];
$params = [];
$types = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['user_name'])) {
        $conditions[] = 'u.name LIKE ?';
        $params[] = '%' . $_GET['user_name'] . '%';
        $types .= 's';
    }

    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $conditions[] = 'o.created_at BETWEEN ? AND ?';
        $params[] = $_GET['start_date'] . ' 00:00:00';
        $params[] = $_GET['end_date'] . ' 23:59:59';
        $types .= 'ss';
    }

    if (!empty($_GET['status'])) {
        $conditions[] = 'o.status = ?';
        $params[] = $_GET['status'];
        $types .= 's';
    }
}

$whereClause = '';
if (!empty($conditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

// 주문 목록 가져오기 쿼리 수정
$query = "SELECT o.*, p.name AS product_name, u.name AS user_name 
          FROM orders o
          LEFT JOIN products p ON o.product_id = p.id
          LEFT JOIN users u ON o.user_id = u.id
          $whereClause
          ORDER BY o.created_at DESC";

$stmt = $conn->prepare($query);

if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
    die("데이터베이스 쿼리 준비 중 오류가 발생했습니다.");
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("데이터베이스 쿼리 실행 중 오류가 발생했습니다.");
}

$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);

// 정산 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_order'])) {
    $order_id = $_POST['order_id'];
    
    $conn->begin_transaction();
    
    try {
        // 수수료 및 직급 계산
        calculateAndProcessCommissions($conn, $order_id);

        // 사용자의 직급 업데이트
        $stmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();

        if ($order) {
            updateUserRank($conn, $order['user_id']);
        }

        $conn->commit();
        $_SESSION['success_message'] = "주문 #$order_id 정산이 완료되었습니다.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "정산 중 오류가 발생했습니다: " . $e->getMessage();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

include 'admin_header.php';
?>

<div class="container" style="padding:10px 0px;">
    <h4>주문 관리</h4>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <!-- 검색 폼 수정 -->
    <form method="GET" class="form-inline mb-3">

        <div class="input-group mr-2">
            <div class="input-group-prepend">
                <span class="input-group-text bg-gray30 fs-14">사용자명</span>
            </div>
            <input type="text" name="user_name" id="user_name" class="form-control" value="<?php echo htmlspecialchars($_GET['user_name'] ?? ''); ?>">
        </div>
        <div class="input-group mr-2">
            <div class="input-group-prepend">
                <span class="input-group-text bg-gray30 fs-14">시작 날짜</span>
            </div>
            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">

            <div class="input-group-prepend">
                <span class="input-group-text bg-gray30 fs-14">종료 날짜</span>
            </div>
            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
        </div>
        <div class="input-group mr-2">
            <div class="input-group-prepend">
                <span class="input-group-text bg-gray30">상태</span>
            </div>
            <select name="status" id="status" class="form-control">
                <option value="">전체</option>
                <option value="pending" <?php if (isset($_GET['status']) && $_GET['status'] == 'pending') echo 'selected'; ?>>pending</option>
                <option value="completed" <?php if (isset($_GET['status']) && $_GET['status'] == 'completed') echo 'selected'; ?>>completed</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary fs-14">검색</button>
    </form>

    <!-- 기존 테이블 코드 유지 -->
    <div class="table-responsive"  style="overflow-x: auto;">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>주문 ID</th>
                    <th>사용자 ID (성명)</th>
                    <th>수량</th>
                    <th>총 금액</th>
                    <th>결제 방법</th>
                    <th>상태</th>
                    <th>생성일</th>
                    <th>액션</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $current_time = new DateTime();
                foreach ($orders as $order): 
                    $order_time = new DateTime($order['created_at']);
                    $interval = $current_time->diff($order_time);
                    $hours_passed = $interval->h + ($interval->days * 24);
                    $hours_passed += $interval->i / 60; // 분을 시간으로 변환하여 추가
                ?>
                <tr>
                    <td><?php echo $order['id']; ?></td>
                    <td><?php echo $order['user_id'] . ' (' . htmlspecialchars($order['user_name']) . ')'; ?></td>
                    <td class="fw-700"><?php echo $order['quantity']; ?></td>
                    <td><?php echo number_format($order['total_amount']); ?>원</td>
                    <td><?php echo $order['payment_method']; ?></td>
                    <td class="fs-10 <?php echo $order['status'] === 'completed' ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $order['status']; ?>
                    </td>
                    <td><?php echo $order_time->format('Y-m-d H:i'); ?></td>
                    <td class="fs-10 text-center">
                        <?php if ($order['status'] === 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" name="confirm_payment" class="btn btn-primary btn-xs" onclick="return confirm('결제를 확인하시겠습니까?');">입금 확인</button>
                                <button type="submit" name="delete_order" class="btn btn-danger btn-xs" onclick="return confirm('이 주문을 삭제하시겠습니까? 이 작업은 취소할 수 없습니다.');">삭제</button>
                            </form>
                            <br>
                            <span class="<?php echo $hours_passed > 4 ? 'text-danger' : 'text-primary'; ?>">
                                (<?php echo number_format($hours_passed, 1); ?>시간 경과)
                            </span>
                        <?php elseif ($order['status'] === 'completed' && $order['paid_status'] === 'pending'): ?>
                            <!-- 정산 버튼 추가 -->
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" name="settle_order" class="btn btn-warning btn-xs" onclick="return confirm('정산을 진행하시겠습니까?');">정산</button>
                            </form>
                        <?php else: ?>
                            처리완료
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>