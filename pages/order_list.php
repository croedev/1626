<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=order_list");
    exit();
}

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// 페이지네이션 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 20;
$offset = ($page - 1) * $items_per_page;

// 정렬 처리
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$where_clause = "WHERE o.user_id = ?";
$params = [$user_id];
$param_types = "i";

switch ($sort) {
    case 'bank':
        $where_clause .= " AND o.payment_method = 'bank'";
        break;
    case 'point':
        $where_clause .= " AND o.payment_method = 'point'";
        break;
    case 'nft':
        // NFT 관련 데이터는 별도 쿼리로 처리
        break;
}

// 통계 데이터 조회 (캐시 키 생성)
$cache_key = "user_stats_{$user_id}";
$stats = null;

// 캐시된 통계가 없는 경우에만 DB 조회
if (!$stats) {
    // 주문 통계 쿼리
    $stats_query = "
        SELECT 
            SUM(quantity) as total_quantity,
            SUM(total_amount) as total_amount,
            SUM(CASE WHEN payment_method = 'bank' THEN 1 ELSE 0 END) as bank_count,
            SUM(CASE WHEN payment_method = 'bank' THEN total_amount ELSE 0 END) as bank_amount,
            SUM(CASE WHEN payment_method = 'point' THEN 1 ELSE 0 END) as point_count,
            SUM(cash_point_used) as cash_point_amount,
            SUM(mileage_point_used) as mileage_point_amount
        FROM orders
        WHERE user_id = ?
    ";
    
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
}

// 전체 주문 수 조회
$count_query = "SELECT COUNT(*) as total FROM orders o " . $where_clause;
$stmt = $conn->prepare($count_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$total_items = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// 주문 데이터 조회 (필요한 컬럼만 선택)
$orders_query = "
    SELECT 
        o.id, o.created_at, o.quantity, o.total_amount, o.payment_method,
        o.status, o.cash_point_used, o.mileage_point_used,
        p.name AS product_name, u.name AS user_name
    FROM orders o
    LEFT JOIN products p ON o.product_id = p.id
    LEFT JOIN users u ON o.user_id = u.id
    {$where_clause}
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($orders_query);
$stmt->bind_param($param_types . "ii", ...[...$params, $items_per_page, $offset]);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// NFT 데이터 조회 (필요한 경우에만)
$nft_data = [];
if ($sort === 'nft' || $sort === 'latest') {
    $nft_query = "
        SELECT 
            nh.id as nft_id, nh.amount as nft_amount, 
            nh.transaction_date, nh.transaction_type,
            nh.order_id,
            COALESCE(u_from.name, '시스템') as from_user_name,
            u_to.name as to_user_name
        FROM nft_history nh
        LEFT JOIN users u_from ON nh.from_user_id = u_from.id
        LEFT JOIN users u_to ON nh.to_user_id = u_to.id
        WHERE nh.to_user_id = ?
        ORDER BY nh.transaction_date DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $conn->prepare($nft_query);
    $stmt->bind_param("iii", $user_id, $items_per_page, $offset);
    $stmt->execute();
    $nft_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // NFT 데이터를 order_id로 인덱싱
    foreach ($nft_results as $nft) {
        if ($nft['order_id']) {
            $nft_data[$nft['order_id']] = $nft;
        }
    }
}

// 현재 보유 NFT 수량 조회
$stmt = $conn->prepare("SELECT nft_token FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_nft = $stmt->get_result()->fetch_assoc()['nft_token'];

// 사용자 정보 조회
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();

$pageTitle = '구매내역';
include 'includes/header.php';
?>

<style>
        /* 전체 페이지를 위한 컨테이너 */
        .page-container {
            position: fixed;
            top: 60px;
            /* 헤더의 높이로 변경 */
            bottom: 35px;
            /* 하단바의 높이 */
            left: 0;
            right: 0;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .page-container::-webkit-scrollbar {
            display: none;
        }

        .order-list-container {
            padding: 20px;
            background-color: #111;
            color: #fff;
            font-family: 'Noto Sans KR', sans-serif;
            font-size: 14px;
        }

        .order-item {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #222;
            border-radius: 5px;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #d4af37;
        }

        .order-details p {
            margin: 5px 0;
            font-family: 'Noto serif kr', serif;
        }

        .order-details span {
            color: #d4af37;
        }

        .order-footer {
            margin-top: 15px;
            text-align: left;
            color: #d4af37;
        }

        .order-summary {
            background-color: #222;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .order-summary h2 {
            font-size: 1rem;
            margin-bottom: 10px;
            color: #d4af37;
        }

        .sort-buttons {
            margin-bottom: 20px;
            text-align: center;
        }

        .sort-buttons a {
            display: inline-block;
            padding: 5px 10px;
            margin: 0 5px;
            background-color: #333;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
        }

        .sort-buttons a.active {
            background-color: #d4af37;
            color: #000;
        }

        p span {
            font-family: 'Noto Sans KR', sans-serif;
        }

        .button-group {
            display: flex;
            justify-content: end;
            margin-top: 0px;
            padding-top: 0px;
        }
        .btn-outline {
            background-color: transparent;
            border: 1px solid #d4af37;
            color: #d4af37;
            font-size: 0.8em;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 2px;
        }
        .btn-outline:hover {
            background-color: #d4af37;
            color: #000000;
        }
        .nft-panel {
            background-color: #2e4047;
            padding: 10px;
            margin-top: 10px;
            border-radius: 5px;
        }
        .nft-panel p {
            margin: 5px 0;
            font-size: 0.9em;
        }

.pagination {
    display: flex;
    justify-content: center;
    margin: 20px 0;
}

.pagination a {
    color: #fff;
    padding: 8px 16px;
    text-decoration: none;
    background-color: #333;
    margin: 0 4px;
    border-radius: 3px;
}

.pagination a.active {
    background-color: #d4af37;
    color: #000;
}
</style>

<div class="page-container">
    <div class="order-list-container">
        <div class="button-group">
            <button class="btn-outline" onclick="location.href='/order'">구매하기</button>
            <button class="btn-outline" onclick="location.href='/nft_transfer'">NFT선물하기</button>
            <button class="btn-outline" onclick="location.href='/commission'">수수료조회</button>
        </div>

        <hr>

        <div class="order-summary rem-10">
            <div class="notosans">성명: <span class="notoserif text-red3">
                <?php echo $user_info ? htmlspecialchars($user_info['name']) . 
                    '(' . htmlspecialchars($user_info['email']) . ')' : '로그인이 필요합니다'; ?>
            </span></div>
            <div class="notosans">전체 구매수량: <span class="notoserif text-red3">
                <?php echo number_format($stats['total_quantity'] ?? 0); ?></span></div>
            <div class="notosans">전체 구매금액: <span class="notoserif text-red3">
                <?php echo number_format($stats['total_amount'] ?? 0); ?>원</span></div>
            <div class="notosans">나의 현재보유 NFT: <span class="notoserif text-red3">
                <?php echo number_format($user_nft); ?>개</span></div>
        </div>

        <div class="sort-buttons">
            <a href="?sort=latest" <?php echo $sort === 'latest' ? 'class="active"' : ''; ?>>최신순</a>
            <a href="?sort=bank" <?php echo $sort === 'bank' ? 'class="active"' : ''; ?>>계좌입금</a>
            <a href="?sort=point" <?php echo $sort === 'point' ? 'class="active"' : ''; ?>>포인트</a>
            <a href="?sort=nft" <?php echo $sort === 'nft' ? 'class="active"' : ''; ?>>NFT</a>
        </div>

        <?php foreach ($orders as $item): ?>
        <div class="order-item">
            <div class="order-header">
                <div class="fs-14">주문번호: <?php echo $item['id']; ?></div>
                <div class="fs-14">주문일시: <?php echo date('Y-m-d H:i', strtotime($item['created_at'])); ?></div>
            </div>
            <hr>
            <div class="order-details fs-13 lh-11">
                <p><span>구매자명:</span> <?php echo htmlspecialchars($item['user_name']); ?></p>
                <p><span>상품명:</span> <?php echo htmlspecialchars($item['product_name']); ?></p>
                <p><span>수량:</span> <?php echo number_format($item['quantity']); ?></p>
                <p><span>결제방식:</span>
                    <?php
                    switch($item['payment_method']) {
                        case 'bank': echo '무통장 입금'; break;
                        case 'point': echo '포인트 결제'; break;
                        case 'paypal': echo 'PayPal 결제'; break;
                        default: echo '케이팬덤 지급';
                    }
                    ?>
                </p>

                <?php if ($item['payment_method'] === 'point'): ?>
                <div>
                    <p><span class="fs-12 text-gray5">- 캐시 포인트:</span> 
                        <?php echo number_format($item['cash_point_used']); ?>원</p>
                    <p><span class="fs-12 text-gray5">- 마일리지 포인트:</span> 
                        <?php echo number_format($item['mileage_point_used']); ?>원</p>
                </div>
                <?php endif; ?>

                <div class="rem-07 notoserif bg-gray70 p5">
                    <p><i class="fas fa-won-sign"></i> <span>총 구매금액:</span> 
                        <?php echo number_format($item['total_amount']); ?>원</p>
                    <p>
                        <i class="fas fa-info-circle"></i> 상태: 
                        <span class="text-red2 notoserif">
                            <?php 
                            switch ($item['status']) {
                                case 'pending': echo '입금대기'; break;
                                case 'paid': echo '결제완료'; break;
                                case 'completed': echo '결제 및 NFT발행 완료'; break;
                                case 'cancelled': echo '취소'; break;
                                default: echo '알 수 없음';
                            }
                            ?>
                        </span>
                    </p>
                </div>
            </div>

            <?php if (isset($nft_data[$item['id']])): ?>
            <div class="nft-panel">
                <?php $nft = $nft_data[$item['id']]; ?>
                <p>NFT Token id: <?php echo $nft['nft_id']; ?>
                    <span class="float-right">생성일: 
                        <?php echo date('Y-m-d H:i', strtotime($nft['transaction_date'])); ?></span>
                </p>
                <hr>
                <p>NFT수량: <?php echo number_format($nft['nft_amount']); ?>개</p>
                <p>보낸사람: <?php echo htmlspecialchars($nft['from_user_name']); ?></p>
                <p>받은사람: <?php echo htmlspecialchars($nft['to_user_name']); ?></p>
                <p>유형: <?php echo htmlspecialchars($nft['transaction_type']); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- 페이지네이션 추가 -->
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>" 
                   class="<?php echo $page === $i ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>