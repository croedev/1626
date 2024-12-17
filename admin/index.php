<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/commission_functions.php';





$conn = db_connect();


// 에러 핸들링을 위한 함수
function handleDatabaseError($conn, $query) {
    error_log("Query error: " . $conn->error . " in query: " . $query);
    die("데이터베이스 오류가 발생했습니다. 관리자에게 문의하세요.");
}

// 데이터 가져오기
try {
    $totalMembers = getTotalMembers($conn);
    $totalOrders = getTotalOrders($conn);
    $totalSales = getTotalSales($conn);
    $totalCommissions = getTotalCommissions($conn);

    // 최근 가입 회원 목록
    $query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 10";
    $result = $conn->query($query);
    if (!$result) handleDatabaseError($conn, $query);
    $recentMembers = $result->fetch_all(MYSQLI_ASSOC);

    // 최근 주문 목록
    $query = "SELECT o.id, o.total_amount, o.created_at, u.name as user_name, u.rank as user_rank 
              FROM orders o 
              LEFT JOIN users u ON o.user_id = u.id 
              ORDER BY o.created_at DESC LIMIT 10";
    $result = $conn->query($query);
    if (!$result) handleDatabaseError($conn, $query);
    $recentOrders = $result->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log("Error in admin/index.php: " . $e->getMessage());
    die("오류가 발생했습니다. 관리자에게 문의하세요.");
}

require_once __DIR__ . '/admin_header.php';
?>

<div class="admin-content">
    <h1>관리자 대시보드</h1>

    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">총 회원 수</h5>
                    <p class="card-text"><?php echo number_format($totalMembers); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">총 주문 수</h5>
                    <p class="card-text"><?php echo number_format($totalOrders); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">총 매출</h5>
                    <p class="card-text"><?php echo number_format($totalSales); ?>원</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">총 지급 수수료</h5>
                    <p class="card-text"><?php echo number_format($totalCommissions); ?>원</p>
                </div>
            </div>
        </div>
    </div>

    <h4>최근 가입 회원</h4>
    <a href="/admin/admin_members.php" class="btn btn-primary bttn-xs mb-3">회원 관리로 이동</a>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>이름</th>
                    <th>전화번호</th>
                    <th>직급</th>
                    <th>가입일</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentMembers as $member): ?>
                <tr>
                    <td><?php echo htmlspecialchars($member['name']); ?></td>
                    <td><?php echo htmlspecialchars($member['phone']); ?></td>
                    <td><?php echo htmlspecialchars($member['rank']); ?></td>
                    <td><?php echo date('Y-m-d', strtotime($member['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h4>최근 주문</h4>
    <a href="/admin/admin_orders.php" class="btn btn-primary bttn-xs mb-3">주문 관리로 이동</a>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>주문 번호</th>
                    <th>회원 이름</th>
                    <th>직급</th>
                    <th>금액</th>
                    <th>주문일</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td><?php echo $order['id']; ?></td>
                    <td><?php echo htmlspecialchars($order['user_name'] ?? '알 수 없음'); ?></td>
                    <td><?php echo htmlspecialchars($order['user_rank'] ?? '미지정'); ?></td>
                    <td><?php echo number_format($order['total_amount']); ?>원</td>
                    <td><?php echo date('Y-m-d', strtotime($order['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
body, html {
    height: 100%;
    margin: 0;
    padding: 0;
}

.admin-content {
    padding: 20px 5px;
    padding-bottom: 60px; /* 하단 여백 추가 */
}

.table-responsive {
    overflow-x: auto;
}

@media (max-width: 768px) {
    .row {
        flex-direction: column;
    }
    .col-md-3 {
        width: 100%;
        margin-bottom: 15px;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>