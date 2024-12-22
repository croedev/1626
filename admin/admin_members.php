<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/admin_functions.php';

$conn = db_connect();

// 페이지네이션 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 100; // 한 페이지당 200건으로 수정
$start = ($page - 1) * $perPage;

// 검색 조건 처리
$conditions = [];
$params = [];
$types = '';

if (!empty($_GET['search'])) {
    $search = $_GET['search'];
    $conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}

if (!empty($_GET['rank'])) {
    $conditions[] = "rank = ?";
    $params[] = $_GET['rank'];
    $types .= 's';
}

if (!empty($_GET['min_quantity'])) {
    $conditions[] = "myQuantity >= ?";
    $params[] = $_GET['min_quantity'];
    $types .= 'i';
}

if (!empty($_GET['min_amount'])) {
    $conditions[] = "myAmount >= ?";
    $params[] = $_GET['min_amount'];
    $types .= 'd';
}

if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $conditions[] = "created_at BETWEEN ? AND ?";
    $params[] = $_GET['start_date'] . ' 00:00:00';
    $params[] = $_GET['end_date'] . ' 23:59:59';
    $types .= 'ss';
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// 통계 쿼리
$statsQuery = "SELECT 
    COUNT(*) as total_count,
    SUM(myQuantity) as total_quantity,
    AVG(myQuantity) as avg_quantity,
    SUM(myAmount) as total_amount,
    AVG(myAmount) as avg_amount,
    COUNT(CASE WHEN rank = '총판' THEN 1 END) as distributor_count
    FROM users $whereClause";

$stmt = $conn->prepare($statsQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// 회원 목록 쿼리
$query = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param($types . 'ii', ...[...$params, $start, $perPage]);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 전체 회원 수 조회
$countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalMembers = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalMembers / $perPage);

// 현재 페이지 그룹 계산
$pageGroup = ceil($page/10);
$startPage = ($pageGroup - 1) * 10 + 1;
$endPage = min($startPage + 9, $totalPages);

require_once __DIR__ . '/admin_header.php';
?>

<style>
.dark-mode {
    background-color: #1a1a1a;
    color: #ffffff;
}
.dark-mode .table {
    color: #000000;
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
.table td, .table th {
    padding: 0.5rem;
    font-size: 0.85rem;
    line-height: 1.2;
}
.search-form .form-control {
    font-size: 0.85rem;
    height: calc(1.5em + 0.5rem + 2px);
    padding: 0.25rem 0.5rem;
   background-color: #fff;
   color: #000000;
}
.stats-box {
    background-color: #2d2d2d;
    padding: 1rem;
    border-radius: 0.25rem;
    margin-bottom: 1rem;
}
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .table {
        font-size: 0.75rem;
    }
    
    .table td, .table th {
        padding: 0.3rem;
        white-space: nowrap;
    }
    
    .btn-10 {
        padding: 0.2rem 0.4rem;
        font-size: 0.75rem;
    }
    
    .stats-box .row {
        flex-direction: column;
    }
    
    .stats-box .col-md-2 {
        width: 100%;
        margin-bottom: 0.5rem;
        text-align: left;
    }
}
</style>

<div class="dark-mode">
    <h4>회원 관리</h4>

    <!-- 검색 폼 -->
    <form action="" method="GET" class="search-form mb-3">
        <div class="row g-2">
            <div class="col-md-12 d-flex flex-wrap gap-2">
                <input type="text" name="search" class="form-control" style="width: 150px;" placeholder="이름/이메일/전화번호" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <select name="rank" class="form-control" style="width: 100px;">
                    <option value="">전체 직급</option>
                    <option value="회원" <?php echo ($_GET['rank'] ?? '') === '회원' ? 'selected' : ''; ?>>회원</option>
                    <option value="총판" <?php echo ($_GET['rank'] ?? '') === '총판' ? 'selected' : ''; ?>>총판</option>
                    <option value="특판" <?php echo ($_GET['rank'] ?? '') === '특판' ? 'selected' : ''; ?>>특판</option>
                    <option value="특판A" <?php echo ($_GET['rank'] ?? '') === '특판A' ? 'selected' : ''; ?>>특판A</option>
                </select>
                <input type="number" name="min_quantity" class="form-control" style="width: 120px;" placeholder="최소구매량" value="<?php echo htmlspecialchars($_GET['min_quantity'] ?? ''); ?>"   >
                <input type="number" name="min_amount" class="form-control" style="width: 120px;" placeholder="최소구매액" value="<?php echo htmlspecialchars($_GET['min_amount'] ?? ''); ?>">
                <input type="date" name="start_date" class="form-control" style="width: 130px;" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>"> 
                <input type="date" name="end_date" class="form-control" style="width: 130px;">

                 <button type="submit" class="btn btn-primary btn-sm">검색</button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary btn-sm">초기화</a>
            </div>
        </div>
    </form>

    <!-- 통계 정보 -->
    <div class="stats-box">
        <div class="row">
            <div class="col-md-2 fs-14 notosans">총 회원수: <span class="text-orange"><?php echo number_format($stats['total_count']); ?>명</span></div>
            <div class="col-md-2 fs-14 notosans">총 구매수량: <span class="text-orange"><?php echo number_format($stats['total_quantity']); ?>개</span></div>
            <div class="col-md-2 fs-12 notosans">평균 구매수량: <span class="text-orange"><?php echo number_format($stats['avg_quantity'], 1); ?>개</span></div>
            <div class="col-md-2 fs-12 notosans">총 구매금액: <span class="text-orange"><?php echo number_format($stats['total_amount']); ?>원</span></div>
            <div class="col-md-2 fs-12 notosans">평균 구매금액: <span class="text-orange"><?php echo number_format($stats['avg_amount']); ?>원</span></div>
            <div class="col-md-2 fs-12 notosans">총판 회원수: <span class="text-orange"><?php echo number_format($stats['distributor_count']); ?>명</span></div>
        </div>
    </div>

    <!-- 회원 목록 테이블 -->
    <table class="table table-striped">
        <thead class="thead-dark">
            <tr>
                <th>ID</th>
                <th>이름</th>
                <th>전화번호</th>
                <th>직급</th>
                <th>추천인</th>
                <th>실적정보</th>
                <th>가입일</th>
                <th>액션</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $member): ?>
            <tr>
                <td><?php echo $member['id']; ?></td>
                <td><?php echo htmlspecialchars($member['name']); ?></td>
                <td><?php echo htmlspecialchars($member['phone']); ?></td>
                <td><?php echo htmlspecialchars($member['rank']); ?></td>
                <td><?php
                    $referred_by_id = $member['referred_by'];
                    $referrer_name = '';
                    if ($referred_by_id) {
                        $referrer_query = "SELECT name FROM users WHERE id = ?";
                        $stmt = $conn->prepare($referrer_query);
                        $stmt->bind_param("i", $referred_by_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $referrer_name = $row['name'];
                        }
                        $stmt->close();
                    }
                    echo $referrer_name ? htmlspecialchars($referrer_name) . '<br>(' . $referred_by_id . ')' : '없음';
                ?></td>
                <td class="fs-12">
                    구매수량:<?php echo number_format($member['myQuantity']); ?><br>
                    구매금액:<?php echo number_format($member['myAmount']); ?><br>
                    NFT수량:<?php echo number_format($member['nft_token']); ?>
                </td>
                <td><?php echo date('Y-m-d', strtotime($member['created_at'])); ?></td>
                <td>
                    <a href="member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-10 btn-primary">수정</a>
                    <button class="btn btn-10 btn-danger" onclick="deleteUser(<?php echo $member['id']; ?>)">삭제</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- 페이지네이션 -->
    <nav>
        <ul class="pagination justify-content-center">
            <?php if($page > 1): ?>
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
            <?php endif; ?>
        </ul>
    </nav>
</div>

<script>
function deleteUser(userId) {
    if (confirm('정말로 이 회원을 삭제하시겠습니까?')) {
        fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'delete_user=1&user_id=' + userId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('오류가 발생했습니다: ' + data.message);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            alert('오류가 발생했습니다.');
        });
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>