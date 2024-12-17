<?php
session_start();
require_once '../includes/config.php';

$conn = db_connect();

// 검색 파라미터
$search_name = $_GET['search_name'] ?? '';
$transaction_type = $_GET['transaction_type'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$per_page = 100;
$offset = ($page - 1) * $per_page;

// 정렬 파라미터
$valid_sort_columns = ['id', 'from_user_name', 'to_user_name', 'amount', 'transaction_date', 'transaction_type', 'order_id'];
$sort = $_GET['sort'] ?? 'transaction_date';
$order = $_GET['order'] ?? 'DESC';

// 정렬 컬럼/방향 유효성 검사
if (!in_array($sort, $valid_sort_columns)) {
    $sort = 'transaction_date';
}
$order = strtoupper($order);
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'DESC';
}

// WHERE 조건 생성
$where_clauses = [];

if ($search_name !== '') {
    $search_name_escaped = $conn->real_escape_string($search_name);
    // 송신자명 또는 수신자명에 해당 이름이 포함
    $where_clauses[] = "(u_from.name LIKE '%$search_name_escaped%' OR u_to.name LIKE '%$search_name_escaped%')";
}

if ($transaction_type !== '') {
    $transaction_type_escaped = $conn->real_escape_string($transaction_type);
    $where_clauses[] = "nh.transaction_type = '$transaction_type_escaped'";
}

if ($start_date !== '' && $end_date !== '') {
    $start_escaped = $conn->real_escape_string($start_date);
    $end_escaped = $conn->real_escape_string($end_date);
    $where_clauses[] = "DATE(nh.transaction_date) BETWEEN '$start_escaped' AND '$end_escaped'";
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// 총 개수 조회
$count_sql = "
    SELECT COUNT(*) as cnt
    FROM nft_history nh
    LEFT JOIN users u_from ON nh.from_user_id = u_from.id
    LEFT JOIN users u_to ON nh.to_user_id = u_to.id
    $where_sql
";
$count_result = $conn->query($count_sql);
$total_count = $count_result->fetch_assoc()['cnt'];
$total_pages = ceil($total_count / $per_page);

// 전체 합계 조회 (페이징 없이)
$sum_sql = "
    SELECT 
        SUM(CASE WHEN nh.from_user_id IS NOT NULL THEN nh.amount ELSE 0 END) AS total_sent, 
        SUM(CASE WHEN nh.to_user_id IS NOT NULL THEN nh.amount ELSE 0 END) AS total_received
    FROM nft_history nh
    LEFT JOIN users u_from ON nh.from_user_id = u_from.id
    LEFT JOIN users u_to ON nh.to_user_id = u_to.id
    $where_sql
";
$sum_result = $conn->query($sum_sql);
if (!$sum_result) {
    die('Sum Query Failed: ' . $conn->error);
}
$sum_row = $sum_result->fetch_assoc();
$total_sent = $sum_row['total_sent'] ?? 0;
$total_received = $sum_row['total_received'] ?? 0;

// 현재 페이지 데이터 조회
$sql = "
    SELECT nh.*, 
           u_from.name AS from_user_name, 
           u_to.name AS to_user_name
    FROM nft_history nh
    LEFT JOIN users u_from ON nh.from_user_id = u_from.id
    LEFT JOIN users u_to ON nh.to_user_id = u_to.id
    $where_sql
    ORDER BY $sort $order
    LIMIT $offset, $per_page
";
$result = $conn->query($sql);
if (!$result) {
    die('Query Failed: ' . $conn->error);
}

$nft_history = $result->fetch_all(MYSQLI_ASSOC);

// 정렬용 URL 생성 함수
function sortLink($column, $label, $current_sort, $current_order) {
    $params = $_GET;
    $params['sort'] = $column;
    // 현재 컬럼으로 정렬 중이면 반대 방향, 아니면 DESC 기본
    $next_order = 'DESC';
    if ($current_sort == $column && $current_order == 'DESC') {
        $next_order = 'ASC';
    }
    $params['order'] = $next_order;
    $url = "?".http_build_query($params);
    return "<a href=\"$url\">$label</a>";
}

include 'admin_header.php';
?>

<div class="container-fluid" style="padding:10px 20px; font-size:13px; width:100%;">
    <h4>NFT 거래 히스토리 관리</h4>

    <!-- 검색 폼 시작 -->
    <form action="" method="GET" class="search-form" style="margin-bottom:20px;">
        <label for="search_name">이름:</label>
        <input type="text" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>" style="margin-right:10px;">

        <label for="transaction_type">거래유형:</label>
        <input type="text" name="transaction_type" value="<?php echo htmlspecialchars($transaction_type); ?>" style="margin-right:10px;">

        <label for="start_date">시작 날짜:</label>
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="margin-right:10px;">

        <label for="end_date">종료 날짜:</label>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="margin-right:10px;">

        <button type="submit" class="btn btn-sm btn-secondary" style="margin-right:10px;">검색</button>
        <a href="admin_nft.php" class="btn btn-sm btn-outline-secondary">초기화</a>
    </form>
    <!-- 검색 폼 종료 -->

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <!-- 전체 합계 표시 -->
    <div style="margin-bottom:20px;">
        <strong>검색결과 전체 합계:</strong><br>
        내가받은(수신) NFT수량 합계:<span class="text-orange"> <?php echo number_format($total_received); ?></span><br>
        내가보낸(송신) NFT수량 합계:<span class="text-orange"> <?php echo number_format($total_sent); ?></span>
       
    </div>

    <div class="table-responsive" style="width:100%;">
        <table class="table table-striped table-sm" style="width:100%;">
            <thead>
                <tr>
                    <th><?php echo sortLink('id','번호',$sort,$order); ?></th>
                    <th><?php echo sortLink('from_user_name','송신자명(id)',$sort,$order); ?></th>
                    <th><?php echo sortLink('to_user_name','수신자명(id)',$sort,$order); ?></th>
                    <th><?php echo sortLink('amount','거래수량',$sort,$order); ?></th>
                    <th><?php echo sortLink('transaction_date','거래일시',$sort,$order); ?></th>
                    <th><?php echo sortLink('transaction_type','거래유형',$sort,$order); ?></th>
                    <th><?php echo sortLink('order_id','관련주문id',$sort,$order); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nft_history as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td>
                        <?php 
                        if ($row['from_user_id'] && $row['from_user_name']) {
                            echo htmlspecialchars($row['from_user_name']) . '(' . $row['from_user_id'] . ')'; 
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($row['to_user_id'] && $row['to_user_name']) {
                            echo htmlspecialchars($row['to_user_name']) . '(' . $row['to_user_id'] . ')'; 
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><?php echo number_format($row['amount']); ?></td>
                    <td><?php echo $row['transaction_date']; ?></td>
                    <td><?php echo htmlspecialchars($row['transaction_type']); ?></td>
                    <td><?php echo $row['order_id'] ? $row['order_id'] : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($nft_history)): ?>
                <tr>
                    <td colspan="7" class="text-center">거래 내역이 없습니다.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 페이징 -->
    <div class="pagination" style="margin-top:20px; text-align:center;">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">이전</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">다음</a>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
