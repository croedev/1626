<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/admin_functions.php';

$conn = db_connect();

// 스왑 처리 완료
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_swap'])) {
    $swap_id = intval($_POST['swap_id']);
    $tx_hash = trim($_POST['tx_hash']);
    
    try {
        $stmt = $conn->prepare("UPDATE sere_swap SET status = 'completed', process_date = NOW(), tx_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $tx_hash, $swap_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "스왑 #$swap_id 처리가 완료되었습니다.";
        } else {
            throw new Exception("처리 실패");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "오류 발생: " . $e->getMessage();
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
    if (!empty($_GET['swap_id'])) {
        $conditions[] = 'ss.id = ?';
        $params[] = $_GET['swap_id'];
        $types .= 'i';
    }

    if (!empty($_GET['user_name'])) {
        $conditions[] = 'u.name LIKE ?';
        $params[] = '%' . $_GET['user_name'] . '%';
        $types .= 's';
    }

    if (!empty($_GET['sere_address'])) {
        $conditions[] = 'ss.sere_address LIKE ?';
        $params[] = '%' . $_GET['sere_address'] . '%';
        $types .= 's';
    }

    if (!empty($_GET['min_amount'])) {
        $conditions[] = 'ss.request_amount >= ?';
        $params[] = $_GET['min_amount'];
        $types .= 'i';
    }

    if (!empty($_GET['status'])) {
        $conditions[] = 'ss.status = ?';
        $params[] = $_GET['status'];
        $types .= 's';
    }

    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $conditions[] = 'ss.request_date BETWEEN ? AND ?';
        $params[] = $_GET['start_date'] . ' 00:00:00';
        $params[] = $_GET['end_date'] . ' 23:59:59';
        $types .= 'ss';
    }
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// 통계 정보 쿼리
try {
    $statsQuery = "SELECT 
        COUNT(*) as total_requests,
        COALESCE(SUM(ss.request_amount), 0) as total_nft_amount,
        COALESCE(SUM(ss.sere_amount), 0) as total_sere_amount,
        COUNT(CASE WHEN ss.status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN ss.status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN ss.status = 'failed' THEN 1 END) as failed_count,
        COALESCE(AVG(ss.request_amount), 0) as avg_nft_amount
        FROM sere_swap ss
        LEFT JOIN users u ON ss.user_id = u.id
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
    
    $stats = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    error_log("통계 쿼리 오류: " . $e->getMessage());
    $stats = [
        'total_requests' => 0,
        'total_nft_amount' => 0,
        'total_sere_amount' => 0,
        'completed_count' => 0,
        'pending_count' => 0,
        'failed_count' => 0,
        'avg_nft_amount' => 0
    ];
}

// 전체 레코드 수 조회
$countQuery = "SELECT COUNT(*) as total FROM sere_swap ss LEFT JOIN users u ON ss.user_id = u.id $whereClause";
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalSwaps = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalSwaps / $perPage);

// 현재 페이지 그룹 계산
$pageGroup = ceil($page/10);
$startPage = ($pageGroup - 1) * 10 + 1;
$endPage = min($startPage + 9, $totalPages);

// 스왑 목록 쿼리
$query = "SELECT ss.*, u.name as user_name, u.email as user_email
          FROM sere_swap ss
          LEFT JOIN users u ON ss.user_id = u.id
          $whereClause
          ORDER BY ss.request_date DESC
          LIMIT ?, ?";

$stmt = $conn->prepare($query);
$stmt->bind_param($types . "ii", ...[...$params, $start, $perPage]);
$stmt->execute();
$swaps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    color: #ffffff;
}
.dark-mode .alert {
    background-color: #2d2d2d;
    border-color: #ffffff;
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
}
.stats-box {
    background-color: #2d2d2d;
    padding: 0.75rem;
    border-radius: 0.25rem;
    margin-bottom: 1rem;
    font-size: 0.8rem;
}
.hash-text {
    font-family: monospace;
    font-size: 0.7rem;
}
</style>

<div class="dark-mode">
    <div class="container-fluid px-2">
        <h4 class="mb-3">SERE 토큰 스왑 관리</h4>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success py-2 px-3 mb-2 fs-12"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger py-2 px-3 mb-2 fs-12"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <!-- 통계 정보 -->
        <div class="stats-box mb-3">
            <div class="row g-2">
                <div class="col-md-2 col-6">총 요청: <span class="text-orange"><?php echo number_format($stats['total_requests']); ?>건</span></div>
                <div class="col-md-2 col-6">NFT수량: <span class="text-orange"><?php echo number_format($stats['total_nft_amount']); ?>개</span></div>
                <div class="col-md-2 col-6">SERE수량: <span class="text-orange"><?php echo number_format($stats['total_sere_amount'], 4); ?></span></div>
                <div class="col-md-2 col-6">평균수량: <span class="text-orange"><?php echo number_format($stats['avg_nft_amount'], 1); ?>개</span></div>
                <div class="col-md-2 col-6">완료: <span class="text-orange"><?php echo number_format($stats['completed_count']); ?>건</span></div>
                <div class="col-md-1 col-6">대기: <span class="text-orange"><?php echo number_format($stats['pending_count']); ?>건</span></div>
                <div class="col-md-1 col-6">실패: <span class="text-orange"><?php echo number_format($stats['failed_count']); ?>건</span></div>
            </div>
        </div>

        <!-- 검색 폼 -->
        <form method="GET" class="search-form mb-3">
            <div class="row g-2">
                <div class="col-md-12 d-flex flex-wrap gap-2">
                    <!-- <input type="text" name="swap_id" class="form-control" style="width: 100px;" placeholder="스왑ID" value="<?php echo htmlspecialchars($_GET['swap_id'] ?? ''); ?>"> -->
                    <input type="text" name="user_name" class="form-control" style="width: 120px;" placeholder="사용자명" value="<?php echo htmlspecialchars($_GET['user_name'] ?? ''); ?>">
                    <input type="text" name="sere_address" class="form-control" style="width: 200px;" placeholder="SERE 주소" value="<?php echo htmlspecialchars($_GET['sere_address'] ?? ''); ?>">
                    <input type="number" name="min_amount" class="form-control" style="width: 120px;" placeholder="최소수량" value="<?php echo htmlspecialchars($_GET['min_amount'] ?? ''); ?>">
                    <select name="status" class="form-control" style="width: 100px;">
                        <option value="">상태</option>
                        <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>대기</option>
                        <option value="completed" <?php echo ($_GET['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>완료</option>
                        <option value="failed" <?php echo ($_GET['status'] ?? '') === 'failed' ? 'selected' : ''; ?>>실패</option>
                    </select>
                    <input type="date" name="start_date" class="form-control" style="width: 130px;" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
                    <input type="date" name="end_date" class="form-control" style="width: 130px;" value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">
                    <button type="submit" class="btn btn-primary btn-sm">검색</button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary btn-sm">초기화</a>
                </div>
            </div>
        </form>

        <!-- 스왑 목록 테이블 -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>회원정보</th>
                        <th>수량정보</th>
                        <th>SERE 주소</th>
                        <th>요청일시</th>
                        <th>처리정보</th>
                        <th>액션</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($swaps as $swap): ?>
                    <tr>
                        <td class="fs-11"><?php echo $swap['id']; ?></td>
                        <td class="fs-11">
                            <?php echo htmlspecialchars($swap['user_name']); ?><br>
                            ID: <?php echo $swap['user_id']; ?>
                        </td>
                        <td class="fs-11">
                            NFT: <?php echo number_format($swap['request_amount']); ?>개<br>
                            SERE: <?php echo number_format($swap['sere_amount'], 8); ?><br>
                            수수료: <?php echo $swap['fee_percentage']; ?>개
                        </td>
                        <td class="fs-11 hash-text">
                            <?php echo substr($swap['sere_address'], 0, 20); ?>...<br>
                            <?php if($swap['tx_hash']): ?>
                                TX: <?php echo substr($swap['tx_hash'], 0, 20); ?>...
                            <?php endif; ?>
                        </td>
                        <td class="fs-11">
                            <?php echo date('Y-m-d H:i', strtotime($swap['request_date'])); ?><br>
                            <?php if($swap['process_date']): ?>
                                처리: <?php echo date('m-d H:i', strtotime($swap['process_date'])); ?>
                            <?php endif; ?>
                        </td>
                        <td class="fs-11 <?php echo $swap['status'] === 'completed' ? 'text-success' : ($swap['status'] === 'failed' ? 'text-danger' : 'text-warning'); ?>">
                            <?php echo $swap['status']; ?>
                        </td>
                        <td class="fs-11">
                            <?php if ($swap['status'] === 'pending'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="swap_id" value="<?php echo $swap['id']; ?>">
                                    <div class="input-group input-group-sm mb-1">
                                        <input type="text" name="tx_hash" class="form-control form-control-sm hash-text" style="width: 120px; font-size: 0.7rem;" placeholder="트랜잭션 해시" required>
                                        <button type="submit" name="complete_swap" class="btn btn-primary btn-xs" onclick="return confirm('스왑 처리를 완료하시겠습니까?');">완료</button>
                                    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 긴 해시값에 대한 클릭 시 전체 텍스트 복사 기능
    document.querySelectorAll('.hash-text').forEach(element => {
        element.style.cursor = 'pointer';
        element.title = '클릭하여 전체 텍스트 복사';
        element.addEventListener('click', function() {
            const fullText = this.innerText;
            navigator.clipboard.writeText(fullText).then(() => {
                alert('클립보드에 복사되었습니다.');
            }).catch(err => {
                console.error('복사 실패:', err);
            });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>