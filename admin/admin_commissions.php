<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/commission_functions.php';


$conn = db_connect();

// 검색 조건 처리
$search_member = $_GET['search'] ?? '';
$search_type = $_GET['type'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$page = $_GET['page'] ?? 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// 검색 조건에 따른 WHERE 절 생성
$where_clauses = [];

if ($search_member !== '') {
    $search_member = '%' . $conn->real_escape_string($search_member) . '%';
    $where_clauses[] = "(u.name LIKE '$search_member' OR u.phone LIKE '$search_member')";
}

if ($search_type !== '') {
    $search_type = $conn->real_escape_string($search_type);
    $where_clauses[] = "c.commission_type = '$search_type'";
}

if ($start_date !== '' && $end_date !== '') {
    $where_clauses[] = "DATE(o.payment_date) BETWEEN '$start_date' AND '$end_date'";
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// 총 수수료 개수 조회
$total_sql = "
    SELECT COUNT(*) as total
    FROM commissions c
    JOIN users u ON c.user_id = u.id
    JOIN orders o ON c.order_id = o.id
    $where_sql
";
$total_result = $conn->query($total_sql);
$total_commissions = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_commissions / $per_page);

// 수수료 내역 조회
$sql = "
    SELECT c.*, 
           u.name as user_name, 
           u.phone as user_phone, 
           u.rank as user_rank, 
           su.name as source_name, 
           su.id as source_id, 
           o.payment_date as order_completed_at, 
           o.total_amount as order_amount
    FROM commissions c
    JOIN users u ON c.user_id = u.id
    JOIN orders o ON c.order_id = o.id
    LEFT JOIN users su ON c.source_user_id = su.id
    $where_sql
    ORDER BY o.payment_date DESC
    LIMIT $offset, $per_page
";
$result = $conn->query($sql);
if ($result === false) {
    die('Query failed: ' . $conn->error);
}

$commissions = $result->fetch_all(MYSQLI_ASSOC);


$pageTitle = '수수료 관리';
include 'admin_header.php';
?>

<style>
    /* 스타일링 코드 */
    .admin-container {
        padding: 10px;
        background-color: #000;
        color: #d4af37;
        font-family: 'Noto Sans KR', sans-serif;
        font-size: 0.8em;
    }
    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .admin-table th, .admin-table td {
        border: 1px solid #333;
        padding: 5px;
        text-align: left;
        font-size: 0.9em;
        font-family: 'Noto Sans KR', sans-serif;
        color:#fff;
        font-weight: 300;
    }
    .admin-table th {
        background-color: #333;
    }
    .pagination {
        margin-top: 20px;
        text-align: center;
    }
    .pagination a {
        color: #d4af37;
        padding: 5px 10px;
        margin: 0 2px;
        text-decoration: none;
        border: 1px solid #d4af37;
    }
    .pagination a.active {
        background-color: #d4af37;
        color: #000;
    }
    .search-form input, .search-form select, .search-form button {
        padding: 5px 10px;
        margin-right: 5px;
    }
</style>

<div class="admin-container">
    <h2>수수료 관리</h2>

    <!-- 검색 폼 -->
    <form action="" method="GET" class="search-form">
        <label for="search">회원 검색:</label>
        <input type="text" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">

        <label for="type">수수료 종류:</label>
        <select name="type">
            <option value="">전체</option>
            <option value="direct_sales" <?php if ($search_type == 'direct_sales') echo 'selected'; ?>>직판 수수료</option>
            <option value="distributor" <?php if ($search_type == 'distributor') echo 'selected'; ?>>총판 수수료</option>
            <option value="special" <?php if ($search_type == 'special') echo 'selected'; ?>>특판 수수료</option>
        </select>

        <label for="start_date">시작 날짜:</label>
        <input type="date" name="start_date" value="<?php echo $start_date; ?>">

        <label for="end_date">종료 날짜:</label>
        <input type="date" name="end_date" value="<?php echo $end_date; ?>">

        <button type="submit">검색</button>
    </form>

    <!-- 수수료 내역 테이블 -->
    <table class="admin-table">
        <thead>
            <tr>
                <th>날짜</th>
                <th>회원 이름</th>
                <th>전화번호</th>
                <th>직급</th>
                <th>수수료 종류</th>
                <th>금액</th>
                <th>발생 원인</th>
                <th>캐시 포인트</th>
                <th>마일리지 포인트</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($commissions)): ?>
                <tr>
                    <td colspan="9">수수료 내역이 없습니다.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($commissions as $commission): ?>
                    <tr>
                        <td><?php echo $commission['order_completed_at']; ?></td>
                        <td><?php echo htmlspecialchars($commission['user_name']); ?></td>
                        <td><?php echo htmlspecialchars($commission['user_phone']); ?></td>
                        <td><?php echo getRankName($commission['user_rank']); ?></td>
                        <td><?php echo getCommissionTypeName($commission['commission_type']); ?></td>
                        <td><?php echo number_format($commission['amount']); ?>원</td>
                        <td class="fs-10 notoserif">
                            <?php
                            echo htmlspecialchars($commission['source_name']) . ' (ID: ' . $commission['source_id'] . ')<br>';
                            echo '발생 매출액: ' . number_format($commission['order_amount']) . '원';
                            ?>
                        </td>
                        <td><?php echo number_format($commission['cash_point_amount']); ?> CP</td>
                        <td><?php echo number_format($commission['mileage_point_amount']); ?> MP</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- 페이징 -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">이전</a>
        <?php endif; ?>
        <span><?php echo $page; ?> / <?php echo $total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">다음</a>
        <?php endif; ?>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
