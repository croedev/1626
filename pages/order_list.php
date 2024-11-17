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

// 사용자의 주문내역과 NFT 히스토리 가져오기
$stmt = $conn->prepare("
    SELECT o.*, p.name AS product_name, u.name AS user_name, 
           nh.id AS nft_id, nh.amount AS nft_amount, nh.transaction_date, nh.transaction_type,
           COALESCE(u_from.name, '시스템') AS from_user_name, u_to.name AS to_user_name
    FROM orders o
    LEFT JOIN products p ON o.product_id = p.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN nft_history nh ON o.id = nh.order_id
    LEFT JOIN users u_from ON nh.from_user_id = u_from.id
    LEFT JOIN users u_to ON nh.to_user_id = u_to.id
    WHERE o.user_id = ? OR nh.to_user_id = ?
    ORDER BY COALESCE(nh.transaction_date, o.created_at) DESC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$combined_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 현재 보유 NFT 수량 가져오기
$stmt = $conn->prepare("SELECT nft_token FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_nft = $result->fetch_assoc()['nft_token'];

// 주문 통계 계산
$total_quantity = 0;
$total_amount = 0;
$bank_count = 0;
$bank_amount = 0;
$point_count = 0;
$cash_point_amount = 0;
$mileage_point_amount = 0;

// 포인트 결제 통계 계산을 위한 변수 추가
$mileage_point_count = 0; // 마일리지 포인트 건수
$cash_point_count = 0; // 캐시 포인트 건수

foreach ($combined_history as $item) {
    if (isset($item['id'])) {
        $total_quantity += $item['quantity'];
        $total_amount += $item['total_amount'];
        
        if ($item['payment_method'] === 'bank') {
            $bank_count++;
            $bank_amount += $item['total_amount'];
        } elseif ($item['payment_method'] === 'point') {
            $point_count++;
            $cash_point_amount += $item['cash_point_used'];
            $mileage_point_amount += $item['mileage_point_used'];
            
            // 마일리지 포인트 건수 증가
            if ($item['mileage_point_used'] > 0) {
                $mileage_point_count++;
            }
            
            // 캐시 포인트 건수 증가
            if ($item['cash_point_used'] > 0) {
                $cash_point_count++;
            }
        }
    }
}

// 정렬 처리
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$sortedHistory = $combined_history;

switch ($sort) {
    case 'bank':
        $sortedHistory = array_filter($combined_history, function($item) {
            return isset($item['payment_method']) && $item['payment_method'] === 'bank';
        });
        break;
    case 'point':
        $sortedHistory = array_filter($combined_history, function($item) {
            return isset($item['payment_method']) && $item['payment_method'] === 'point';
        });
        break;
    case 'nft':
        $sortedHistory = array_filter($combined_history, function($item) {
            return isset($item['nft_id']);
        });
        break;
    case 'latest':
    default:
        // 이미 날짜순으로 정렬되어 있으므로 추가 작업 불필요
        break;
}

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
                    <?php
        // 사용자 정보 가져오기
        $user_info = null;
        if (isset($_SESSION['user_id'])) {
            $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_info = $result->fetch_assoc();
        }
        
        if ($user_info) {
            echo htmlspecialchars($user_info['name']) . '(' . htmlspecialchars($user_info['email']) . ')';
        } else {
            echo '로그인이 필요합니다';
        }
        ?>
                </span></div>
            <div class="notosans">전체 구매수량: <span
                    class="notoserif text-red3"><?php echo number_format($total_quantity); ?></span></div>
            <div class="notosans">전체 구매금액: <span
                    class="notoserif text-red3"><?php echo number_format($total_amount); ?>원</span></div>
            <div class="notosans">나의 현재보유 NFT: <span class="notoserif text-red3"><?php echo number_format($user_nft); ?>개</span></div>
        </div>

        <div class="sort-buttons">
            <a href="?sort=latest" <?php echo $sort === 'latest' ? 'class="active"' : ''; ?>>최신순</a>
            <a href="?sort=bank" <?php echo $sort === 'bank' ? 'class="active"' : ''; ?>>계좌입금</a>
            <a href="?sort=point" <?php echo $sort === 'point' ? 'class="active"' : ''; ?>>포인트</a>
            <a href="?sort=nft" <?php echo $sort === 'nft' ? 'class="active"' : ''; ?>>NFT</a>
        </div>

        <?php foreach ($sortedHistory as $item) : ?>
        <div class="order-item">
            <?php if (isset($item['id'])) : // 주문 정보가 있는 경우 ?>
                <div class="order-header">
                    <div class="fs-14">주문번호: <?php echo $item['id']; ?></div>
                    <div class="fs-14">주문일시: <?php echo date('Y-m-d H:i', strtotime($item['created_at'])); ?></div>
                </div>
                <hr>
                <div class="order-details fs-13 lh-11">
                    <p><span>구매자명:</span> <?php echo $item['user_name']; ?></p>
                    <p><span>상품명:</span> <?php echo $item['product_name']; ?></p>
                    <p><span>수량:</span> <?php echo $item['quantity']; ?></p>
                    <p><span>결제방식:</span>
                        <?php
                            switch($item['payment_method']) {
                                case 'bank':
                                    echo '무통장 입금';
                                    break;
                                case 'point':
                                    echo '포인트 결제';
                                    break;
                                case 'paypal':
                                    echo 'PayPal 결제';
                                    break;
                                default:
                                    echo '케이팬덤 지급';
                            }
                        ?>
                    </p>

                    <div class="">
                        <?php if ($item['payment_method'] === 'point'): ?>
                        <p> <span class="fs-12 text-gray5">- 캐시 포인트:</span> <?php echo number_format($item['cash_point_used']); ?>원</p>
                        <p> <span class="fs-12 text-gray5" >- 마일리지 포인트:</span> <?php echo number_format($item['mileage_point_used']); ?>원</p>
                        <?php endif; ?>
                    </div>


                    <div class="rem-07 notoserif bg-gray70 p5">
                        <p><i class="fas fa-won-sign"></i> <span>총 구매금액:</span> <?php echo number_format($item['total_amount']); ?>원</p>

                        <p>
                            <i class="fas fa-info-circle"></i> 상태: <span class="text-red2 notoserif">
                                <?php 
                        switch ($item['status']) {
                            case 'pending':
                                echo '입금대기';
                                break;
                            case 'paid':
                                echo '결제완료';
                                break;
                            case 'completed':
                                echo '결제 및 NFT발행 완료';
                                break;
                            case 'cancelled':
                                echo '취소';
                                break;
                            default:
                                echo '알 수 없음';
                        }
                         ?>
                            </span>
                        </p>

                    </div>


                </div>
            <?php endif; ?>

            <?php if (isset($item['nft_id'])) : // NFT 정보가 있는 경우 ?>
                <div class="nft-panel">
                    <p>NFT Token id: <?php echo $item['nft_id']; ?>  <span class="float-right">생성일: <?php echo date('Y-m-d H:i', strtotime($item['transaction_date'])); ?></span></p>
                    <hr>
                    <p>NFT수량: <?php echo number_format($item['nft_amount']); ?>개</p>
                    <p>보낸사람: <?php echo htmlspecialchars($item['from_user_name']); ?></p>
                    <p>받은사람: <?php echo htmlspecialchars($item['to_user_name']); ?></p>
                    <p>유형: <?php echo $item['transaction_type']; ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

    </div>
</div>

<?php include 'includes/footer.php'; ?>