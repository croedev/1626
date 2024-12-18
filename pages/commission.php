<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once 'includes/config.php';
require_once 'includes/commission_functions.php';

// 사용자 인증 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=commission");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = db_connect();

// 사용자 정보 가져오기
$stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user === null) {
    error_log("사용자 정보를 가져오는데 실패했습니다. User ID: " . $user_id);
    die("사용자 정보를 가져오는데 실패했습니다. 관리자에게 문의하세요.");
}

// 기간별 검색 처리
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 month'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

try {
    // 수수료 내역 가져오기
    $commissions = getCommissions($conn, $user_id, $start_date, $end_date, 'order_date');
} catch (Exception $e) {
    error_log("수수료 내역 가져오기 실패: " . $e->getMessage());
    $commissions = [];
}


// 수수료 통계 가져오는 부분 수정
try {
    // 직접 SQL로 수수료 합계 계산
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN commission_type = 'direct_sales' THEN amount ELSE 0 END) as direct,
            SUM(CASE WHEN commission_type = 'distributor' THEN amount ELSE 0 END) as distributor, 
            SUM(CASE WHEN commission_type = 'special' THEN amount ELSE 0 END) as special,
            SUM(amount) as total
        FROM commissions 
        WHERE user_id = ?
    ");

    if (!$stmt) {
        throw new Exception("쿼리 준비 실패: " . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("쿼리 실행 실패: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $commission_stats = $result->fetch_assoc();

    // NULL 값 처리
    $commission_stats = array_map(function($value) {
        return $value ?? 0;
    }, $commission_stats);

    // 로그 추가
    // error_log("사용자 $user_id의 수수료 통계: " . print_r($commission_stats, true));

} catch (Exception $e) {
    error_log("수수료 통계 조회 실패: " . $e->getMessage());
    $commission_stats = [
        'direct' => 0,
        'distributor' => 0,
        'special' => 0,
        'total' => 0
    ];
}

// 데이터 검증을 위한 추가 쿼리
try {
    $verify_stmt = $conn->prepare("
        SELECT commission_total, cash_points, mileage_points 
        FROM users 
        WHERE id = ?
    ");
    
    if (!$verify_stmt) {
        throw new Exception("검증 쿼리 준비 실패: " . $conn->error);
    }

    $verify_stmt->bind_param("i", $user_id);
    
    if (!$verify_stmt->execute()) {
        throw new Exception("검증 쿼리 실행 실패: " . $verify_stmt->error);
    }

    $user_totals = $verify_stmt->get_result()->fetch_assoc();

    // 검증 로그 추가
    error_log(sprintf(
        "사용자 %d - 수수료 총액: %.2f, 포인트 총액: %.2f", 
        $user_id,
        $commission_stats['total'],
        ($user_totals['cash_points'] + $user_totals['mileage_points'])
    ));

    // 실제 commission_total과 cash_points + mileage_points의 합계가 일치하는지 확인
    if (abs($commission_stats['total'] - ($user_totals['cash_points'] + $user_totals['mileage_points'])) > 0.01) {
        error_log(sprintf(
            "사용자 %d의 불일치 감지 - 수수료 총액: %.2f, 포인트 총액: %.2f",
            $user_id,
            $commission_stats['total'],
            ($user_totals['cash_points'] + $user_totals['mileage_points'])
        ));
    }

} catch (Exception $e) {
    error_log("사용자 데이터 검증 실패: " . $e->getMessage());
}

$pageTitle = '수수료 내역';
include 'includes/header.php';
?>


<style>
.commission-container {
    padding: 20px;
    background-color: #000;
    color: #fff;
    font-size: 0.8em;
}

.commission-header {
    background-color: #d4af37;
    color: #000;
    padding: 10px;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.commission-header .back-btn {
    font-size: 24px;
    text-decoration: none;
    color: #000;
}



.user-info,
.commission-summary {
    background-color: #222;
    padding: 15px;
    margin-bottom: 0px;
    border-radius: 5px;
    line-height: 0.67;
    /* 기존 1.0에서 50% 줄임 */
}

.user-info h3,
.commission-summary h3 {
    margin-top: 0;
    color: #d4af37;
}

.commission-table {
    width: 100%;
    border-collapse: collapse;

}

.commission-table th,
.commission-table td {
    border: 1px solid #333;
    padding: 8px;
    text-align: left;
}

.commission-table th {
    background-color: #333;
}

.date-range {
    display: flex;
    justify-content: ;
    margin-bottom: 15px;
}

.date-range input[type="date"] {
    background-color: #333;
    border: 1px solid #d4af37;
    color: #fff;
    padding: 5px;
}

.date-range button {
    background-color: #3e3724;
    color: #fff;
    border: 1px solid #d4af37;
    padding: 5px 10px;
    cursor: pointer;
}

@media (max-width: 768px) {

    .commission-table,
    .commission-table thead,
    .commission-table tbody,
    .commission-table th,
    .commission-table td,
    .commission-table tr {
        display: block;
    }

    .commission-table thead tr {
        position: absolute;
        top: -9999px;
        left: -9999px;
    }

    .commission-table tr {
        margin-bottom: 15px;
    }

    .commission-table td {
        border: none;
        position: relative;
        padding-left: 50%;
    }

    .commission-table td:before {
        content: attr(data-label);
        position: absolute;
        left: 6px;
        width: 45%;
        padding-right: 10px;
        white-space: nowrap;
        font-weight: bold;
    }
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
    font-size: 0.9em;
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

.commission-card {
    background-color: #222;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
    color: #fff;
    transition: transform 0.2s;
}

.commission-card:hover {
    transform: scale(1.02);
}

.commission-card h4 {
    margin: 0 0 10px;
    color: #d4af37;
}

.commission-card p {
    margin: 5px 0;
}

.commission-card .status {
    font-weight: bold;
    color: #d4af37;
}

@media (min-width: 768px) {
    .commission-table {
        display: table;
        width: 100%;
        border-collapse: collapse;
    }

    .commission-table th,
    .commission-table td {
        border: 1px solid #333;
        padding: 8px;
        text-align: left;
    }

    .commission-table th {
        background-color: #333;
    }

    .commission-card {
        display: block;
        /* 모바일에서만 카드형으로 표시 */
    }
}

.table-dark {
    background-color: #222;
    color: #fff;
    font-size: 0.7rem;
}

.table-dark th {
    background-color: #333;
}

.pagination {
    margin-top: 20px;
    text-align: center;
}

.pagination a, .pagination span {
    display: inline-block;
    padding: 5px 10px;
    margin: 0 2px;
    border: 1px solid #d4af37;
    color: #d4af37;
    text-decoration: none;
}

.pagination .current-page {
    background-color: #d4af37;
    color: #000;
}
</style>


<div class="commission-container notoserif mb100 pb100">

    <div class="button-group mb20">
        <button class="btn-outline" onclick="location.href='/order'"><i class="fas fa-plus-circle"></i> 추가구매하기</button>
        <button class="btn-outline" onclick="location.href='/nft_transfer'">NFT선물하기</button>
        <button class="btn-outline" onclick="location.href='/withdrawals'">출금신청</button>
        <button class="btn-outline" onclick="location.href='/organization'">추천조직도</button>
    </div>

    <div class="user-info fw-300">
        <h5>나의 직급: <span class="notosans text-orange"><?php echo getRankName($user['rank']); ?></span></h5>
        <hr>
        <p class="notosans">1) 나의 구매수량: <span class="notoserif text-orange"><strong><?php echo number_format($user['myQuantity']); ?></strong></span></p>
        <p class="notosans">2) 나의 구매금액: <span class="notoserif text-orange"><strong><?php echo number_format($user['myAmount']); ?>원</strong></span></p>
        <p class="notosans">3) 나의 NFT 보유량: <span class="notoserif text-orange"><strong><?php echo number_format($user['nft_token']); ?></strong></span></p>
        <p class="notosans">4) 현재 현금 포인트: <span class="notoserif text-orange"><strong><?php echo number_format($user['cash_points']); ?> CP</strong></span>
        </p>
        <p class="notosans">5) 현재 마일리지 포인트: <span class="notoserif text-orange"><strong><?php echo number_format($user['mileage_points']); ?>
                MP</strong></span></p>
    </div>

    <div class="commission-summary mt20">
        <h5>나의 수수료(누적)</h5>
        <hr>
        <p>1) 직판 수수료: <span
                class="notosans text-blue3"><?php echo number_format($commission_stats['direct']); ?>원</span></p>
        <p>2) 총판 수수료: <span
                class="notosans text-blue3"><?php echo number_format($commission_stats['distributor']); ?>원</span></p>
        <p>3) 특판 수수료: <span
                class="notosans text-blue3"><?php echo number_format($commission_stats['special']); ?>원</span></p>
        <p>4) 발생 수수료 총합: <span
                class="notosans text-blue3"><?php echo number_format($commission_stats['total']); ?>원</span></p>
    </div>

    <div class="commission-details mt20">
        <h4>수수료 세부내역</h4>
        <hr>
        <div class="date-range">
            <form action="" method="GET">
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
                <span class="mx-20">~</span>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
                <button type="submit">검색</button>
            </form>
        </div>

        <!-- 수수료 내역 표시 -->
        <div class="commission-cards">
            <?php if (empty($commissions)): ?>
            <div class="commission-card">
                <p>현재 발생한 수수료가 없습니다.</p>
            </div>
            <?php else: ?>
            <?php
            // 페이징 설정
            $items_per_page = 10;
            $total_items = count($commissions);
            $total_pages = ceil($total_items / $items_per_page);
            $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $offset = ($current_page - 1) * $items_per_page;
            $paged_commissions = array_slice($commissions, $offset, $items_per_page);
            ?>

            <div class="table-responsive">
                <table class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th>발생일</th>
                            <th>매출내역</th>
                            <th>발생수수료</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paged_commissions as $commission): ?>
                        <tr>
                            <td><?php echo $commission['order_completed_at']; ?><br><br>
                            <?php echo getCommissionTypeName($commission['commission_type']); ?></td>
                            <td>
                            <?php echo htmlspecialchars($commission['source_name']) . ' (' . $commission['source_id'] . ')'; ?> <br>
                            수량: <span class="text-blue3"><?php echo $commission['order_count']; ?></span>개<br>
                            매출: <span class="text-blue3"><?php echo number_format($commission['order_amount']); ?></span>원<br>
                            적용률: <span class="text-blue3"><?php echo $commission['commission_rate']; ?></span>%</td>
                            <td>
                            합계: <span class="text-blue3"><?php echo number_format($commission['amount']); ?></span>원<br>
                            현금: <span class="text-blue3"><?php echo number_format($commission['cash_point_amount']); ?></span> CP<br>
                            마일리지: <span class="text-blue3"><?php echo number_format($commission['mileage_point_amount']); ?></span> MP</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 페이징 -->
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">이전</a>
                <?php endif; ?>

                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);

                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $current_page): ?>
                        <span class="current-page"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">다음</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
