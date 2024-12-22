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

// 페이지네이션 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 200;
$start = ($page - 1) * $perPage;

// 검색 조건 처리
$conditions = [];
$params = [];
$types = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['order_id'])) {
        $conditions[] = 'o.id = ?';
        $params[] = $_GET['order_id'];
        $types .= 'i';
    }

    if (!empty($_GET['user_name'])) {
        $conditions[] = 'u.name LIKE ?';
        $params[] = '%' . $_GET['user_name'] . '%';
        $types .= 's';
    }

    if (!empty($_GET['phone'])) {
        $conditions[] = 'u.phone LIKE ?';
        $params[] = '%' . $_GET['phone'] . '%';
        $types .= 's';
    }

    if (!empty($_GET['min_amount'])) {
        $conditions[] = 'o.total_amount >= ?';
        $params[] = $_GET['min_amount'];
        $types .= 'd';
    }

    if (!empty($_GET['min_quantity'])) {
        $conditions[] = 'o.quantity >= ?';
        $params[] = $_GET['min_quantity'];
        $types .= 'i';
    }

    if (!empty($_GET['payment_method'])) {
        $conditions[] = 'o.payment_method = ?';
        $params[] = $_GET['payment_method'];
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

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// 통계 정보 쿼리
try {
    $statsQuery = "SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(o.quantity), 0) as total_quantity,
        COALESCE(SUM(o.total_amount), 0) as total_amount,
        COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as completed_orders,
        COUNT(CASE WHEN o.status = 'pending' THEN 1 END) as pending_orders,
        COALESCE(AVG(o.total_amount), 0) as avg_amount
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        $whereClause";

    $stmt = $conn->prepare($statsQuery);
    if ($stmt === false) {
        throw new Exception("통계 쿼리 준비 실패: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("통계 쿼리 실행 실패: " . $stmt->error);
    }
} catch (Exception $e) {
    error_log("통계 쿼리 오류: " . $e->getMessage());
    // 기본값 설정
    $stats = [
        'total_orders' => 0,
        'total_quantity' => 0,
        'total_amount' => 0,
        'completed_orders' => 0,
        'pending_orders' => 0,
        'avg_amount' => 0
    ];
    $_SESSION['error_message'] = "통계 정보를 불러오는 중 오류가 발생했습니다.";
}
$stats = $stmt->get_result()->fetch_assoc();

// 전체 주문 수 조회
$countQuery = "SELECT COUNT(*) as total FROM orders o LEFT JOIN users u ON o.user_id = u.id $whereClause";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalOrders = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalOrders / $perPage);

// 현재 페이지 그룹 계산
$pageGroup = ceil($page/10);
$startPage = ($pageGroup - 1) * 10 + 1;
$endPage = min($startPage + 9, $totalPages);

// 주문 목록 쿼리
$query = "SELECT o.*, p.name AS product_name, u.name AS user_name, u.phone AS user_phone
          FROM orders o
          LEFT JOIN products p ON o.product_id = p.id
          LEFT JOIN users u ON o.user_id = u.id
          $whereClause
          ORDER BY o.created_at DESC
          LIMIT ?, ?";

$stmt = $conn->prepare($query);
$stmt->bind_param($types . "ii", ...[...$params, $start, $perPage]);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'admin_header.php';
?>

<style>
.dark-mode {
    background-color: #1a1a1a;
    color: #e0e0e0;
}
.dark-mode .table {
    color: #e0e0e0;
    background-color: #2d2d2d;
}
.dark-mode .table-striped tbody tr:nth-of-type(odd) {
    background-color: #333333;
}
.dark-mode .form-control {
    background-color: #2d2d2d;
    color: #000000;
    border-color: #404040;
}
.dark-mode .input-group-text {
    background-color: #404040;
    color: #000000;
    border-color: #505050;
}
.dark-mode .btn-primary {
    background-color: #2b5797;
    border-color: #1f407a;
}
.dark-mode .btn-secondary {
    background-color: #4a4a4a;
    border-color: #3a3a3a;
    color: #e0e0e0;
}
.dark-mode .btn-secondary:hover {
    background-color: #5a5a5a;
    border-color: #4a4a4a;
    color: #ffffff;
}
.dark-mode .alert {
    background-color: #2d2d2d;
    border-color: #404040;
}
.table td, .table th {
    padding: 0.3rem;
    font-size: 0.75rem;
    line-height: 1.1;
}
.btn-xs {
    padding: 0.1rem 0.3rem;
    font-size: 0.7rem;
}
.search-form .form-control {
    font-size: 0.8rem;
    height: calc(1.5em + 0.5rem + 2px);
    padding: 0.25rem 0.5rem;
    background-color: #fff;
    color: #000000;
}
.stats-box {
    background-color: #2d2d2d;
    padding: 0.75rem;
    border-radius: 0.25rem;
    margin-bottom: 1rem;
    font-size: 0.8rem;
}
.pagination {
    margin-bottom: 1rem;
}
.pagination .page-link {
    padding: 0.3rem 0.6rem;
    font-size: 0.8rem;
    background-color: #2d2d2d;
    border-color: #404040;
    color: #e0e0e0;
}
.pagination .active .page-link {
    background-color: #2b5797;
    border-color: #1f407a;
}
@media (max-width: 768px) {
    .container-fluid {
        padding: 0.5rem;
    }
    .table-responsive {
        font-size: 0.7rem;
    }
    .search-form .form-control {
        margin-bottom: 0.3rem;
    }
    .stats-box .col-6 {
        padding: 0.3rem;
    }
}
</style>

<div class="dark-mode">
    <div class="container-fluid px-2">
        <h4 class="mb-3">주문 관리</h4>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success py-2 px-3 mb-2 fs-12"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger py-2 px-3 mb-2 fs-12"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <!-- 통계 정보 -->
        <div class="stats-box mb-3">
            <div class="row g-1">
                <div class="col-md-2 col-6 notosans">총 주문:<span class="text-orange"><?php echo number_format($stats['total_orders']); ?>건</span></div>
                <div class="col-md-2 col-6 notosans">총 수량:<span class="text-orange"><?php echo number_format($stats['total_quantity']); ?>개</span></div>
                <div class="col-md-3 col-6 notosans">총 금액:<span class="text-orange"><?php echo number_format($stats['total_amount']); ?>원</span></div>
                <div class="col-md-2 col-6 notosans">평균:<span class="text-orange"><?php echo number_format($stats['avg_amount']); ?>원</span></div>
                <div class="col-md-2 col-6 notosans">완료:<span class="text-orange"><?php echo number_format($stats['completed_orders']); ?>건</span></div>
                <div class="col-md-1 col-6 notosans">대기:<span class="text-orange"><?php echo number_format($stats['pending_orders']); ?>건</span></div>
            </div>
        </div>

        <!-- 검색 폼 -->
        <form method="GET" class="search-form mb-3">
            <div class="row g-2">
                <div class="col-md-12 d-flex flex-wrap gap-2">
                    <input type="text" name="order_id" class="form-control" style="width: 100px;" placeholder="주문번호" value="<?php echo htmlspecialchars($_GET['order_id'] ?? ''); ?>">
                    <input type="text" name="user_name" class="form-control" style="width: 120px;" placeholder="사용자명" value="<?php echo htmlspecialchars($_GET['user_name'] ?? ''); ?>">
                    <input type="text" name="phone" class="form-control" style="width: 120px;" placeholder="전화번호" value="<?php echo htmlspecialchars($_GET['phone'] ?? ''); ?>">
                    <input type="number" name="min_amount" class="form-control" style="width: 120px;" placeholder="최소금액" value="<?php echo htmlspecialchars($_GET['min_amount'] ?? ''); ?>">
                    <input type="number" name="min_quantity" class="form-control" style="width: 100px;" placeholder="최소수량" value="<?php echo htmlspecialchars($_GET['min_quantity'] ?? ''); ?>">
                    <select name="payment_method" class="form-control" style="width: 100px;">
                        <option value="">결제방법</option>
                        <option value="card" <?php echo ($_GET['payment_method'] ?? '') === 'card' ? 'selected' : ''; ?>>카드</option>
                        <option value="bank" <?php echo ($_GET['payment_method'] ?? '') === 'bank' ? 'selected' : ''; ?>>계좌이체</option>
                    </select>
                    <select name="status" class="form-control" style="width: 100px;">
                        <option value="">상태</option>
                        <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>대기</option>
                        <option value="completed" <?php echo ($_GET['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>완료</option>
                        <option value="cancelled" <?php echo ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>취소</option>
                    </select>
                    <input type="date" name="start_date" class="form-control" style="width: 130px;" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                    <input type="date" name="end_date" class="form-control" style="width: 130px;" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                    <button type="submit" class="btn btn-primary btn-sm">검색</button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary btn-sm">초기화</a>
                </div>
            </div>
        </form>

        <!-- 주문 목록 테이블 -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>회원정보</th>
                        <th>수량/금액</th>
                        <th>결제정보</th>
                        <th>상태/시간</th>
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
                        $hours_passed += $interval->i / 60;
                    ?>
                    <tr>
                        <td class="fs-11"><?php echo $order['id']; ?></td>
                        <td class="fs-11">
                            <?php echo htmlspecialchars($order['user_name']); ?><br>
                            ID: <?php echo $order['user_id']; ?><br>
                            TEL: <?php echo htmlspecialchars($order['user_phone']); ?>
                        </td>
                        <td class="fs-11">
                            <?php echo number_format($order['quantity']); ?>개<br>
                            <?php echo number_format($order['total_amount']); ?>원<br>
                            NFT: <?php echo number_format($order['nft_token']); ?>개
                        </td>
                        <td class="fs-11">
                            <?php echo $order['payment_method']; ?><br>
                            <?php if($order['depositor_name']): ?>
                                입금자: <?php echo htmlspecialchars($order['depositor_name']); ?><br>
                            <?php endif; ?>
                            <?php if($order['payment_date']): ?>
                                결제: <?php echo date('m-d H:i', strtotime($order['payment_date'])); ?>
                            <?php endif; ?>
                        </td>
                        <td class="fs-11 <?php echo $order['status'] === 'completed' ? 'text-success' : ($order['status'] === 'cancelled' ? 'text-danger' : 'text-warning'); ?>">
                            <?php echo $order['status']; ?><br>
                            <?php echo $order_time->format('m-d H:i'); ?><br>
                            <?php if($order['status'] === 'pending'): ?>
                                <span class="<?php echo $hours_passed > 4 ? 'text-danger' : 'text-info'; ?>">
                                    (<?php echo number_format($hours_passed, 1); ?>시간)
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="fs-11">
                            <?php if ($order['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="confirm_payment" class="btn btn-primary btn-xs mb-1" onclick="return confirm('결제를 확인하시겠습니까?');">입금확인</button>
                                    <button type="submit" name="delete_order" class="btn btn-danger btn-xs" onclick="return confirm('이 주문을 삭제하시겠습니까?');">삭제</button>
                                </form>
                            <?php elseif ($order['status'] === 'completed' && $order['paid_status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="settle_order" class="btn btn-warning btn-xs" onclick="return confirm('정산을 진행하시겠습니까?');">정산</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">처리완료</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 페이지네이션 -->
        <nav>
            <ul class="pagination justify-content-center">
                <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">처음</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo ($page-1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">이전</a>
                    </li>
                <?php endif; ?>
                
                <?php for($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo ($page+1); ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">다음</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $totalPages; ?>&<?php echo http_build_query(array_filter($_GET, function($key) { return $key != 'page'; }, ARRAY_FILTER_USE_KEY)); ?>">마지막</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

<?php include '../includes/footer.php'; ?>