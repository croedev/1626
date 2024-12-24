<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');


session_start();
require_once '../includes/config.php';

$conn = db_connect();

// Search parameters
$search_user = $_GET['search_user'] ?? '';
$wallet_address = $_GET['wallet_address'] ?? '';
$symbol = $_GET['symbol'] ?? '';
$type = $_GET['type'] ?? '';
$amount_min = $_GET['amount_min'] ?? '';
$amount_max = $_GET['amount_max'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$status = $_GET['status'] ?? '';
$fee_type = $_GET['fee_type'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$per_page = 100;
$offset = ($page - 1) * $per_page;

// Sort parameters
$valid_sort_columns = ['id', 'user_id', 'symbol', 'type', 'amount', 'from_address', 'to_address', 'fee', 'processed_at', 'status'];
$sort = $_GET['sort'] ?? 'processed_at';
$order = $_GET['order'] ?? 'DESC';

// Validate sort column/direction
if (!in_array($sort, $valid_sort_columns)) {
    $sort = 'processed_at';
}
$order = strtoupper($order);
if (!in_array($order, ['ASC', 'DESC'])) {
    $order = 'DESC';
}

// Build WHERE clauses
$where_clauses = [];
$params = [];

if ($search_user !== '') {
    $search_user_escaped = $conn->real_escape_string($search_user);
    $where_clauses[] = "(u.name LIKE '%$search_user_escaped%' OR u.id LIKE '%$search_user_escaped%')";
}

if ($wallet_address !== '') {
    $wallet_address_escaped = $conn->real_escape_string($wallet_address);
    $where_clauses[] = "(wh.from_address LIKE '%$wallet_address_escaped%' OR wh.to_address LIKE '%$wallet_address_escaped%')";
}

if ($symbol !== '') {
    $symbol_escaped = $conn->real_escape_string($symbol);
    $where_clauses[] = "wh.symbol = '$symbol_escaped'";
}

if ($type !== '') {
    $type_escaped = $conn->real_escape_string($type);
    $where_clauses[] = "wh.type = '$type_escaped'";
}

if ($amount_min !== '') {
    $amount_min_escaped = $conn->real_escape_string($amount_min);
    $where_clauses[] = "wh.amount >= '$amount_min_escaped'";
}

if ($amount_max !== '') {
    $amount_max_escaped = $conn->real_escape_string($amount_max);
    $where_clauses[] = "wh.amount <= '$amount_max_escaped'";
}

if ($start_date !== '' && $end_date !== '') {
    $start_escaped = $conn->real_escape_string($start_date);
    $end_escaped = $conn->real_escape_string($end_date);
    $where_clauses[] = "DATE(wh.processed_at) BETWEEN '$start_escaped' AND '$end_escaped'";
}

if ($status !== '') {
    $status_escaped = $conn->real_escape_string($status);
    $where_clauses[] = "wh.status = '$status_escaped'";
}

if ($fee_type !== '') {
    $fee_type_escaped = $conn->real_escape_string($fee_type);
    $where_clauses[] = "wh.fee_type = '$fee_type_escaped'";
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Count total records
$count_sql = "
    SELECT COUNT(*) as cnt
    FROM wallet_history wh
    LEFT JOIN users u ON wh.user_id = u.id
    $where_sql
";
$count_result = $conn->query($count_sql);
$total_count = $count_result->fetch_assoc()['cnt'];
$total_pages = ceil($total_count / $per_page);

// Calculate totals
$sum_sql = "
    SELECT 
        COUNT(*) as total_transactions,
        SUM(wh.amount) as total_amount,
        SUM(wh.fee) as total_fee,
        COUNT(CASE WHEN wh.status = '처리완료' THEN 1 END) as completed_count,
        COUNT(CASE WHEN wh.status != '처리완료' THEN 1 END) as failed_count
    FROM wallet_history wh
    LEFT JOIN users u ON wh.user_id = u.id
    $where_sql
";
$sum_result = $conn->query($sum_sql);
if (!$sum_result) {
    die('Sum Query Failed: ' . $conn->error);
}
$sum_row = $sum_result->fetch_assoc();

// Get current page data
$sql = "
    SELECT wh.*, u.name as user_name
    FROM wallet_history wh
    LEFT JOIN users u ON wh.user_id = u.id
    $where_sql
    ORDER BY $sort $order
    LIMIT $offset, $per_page
";
$result = $conn->query($sql);
if (!$result) {
    die('Query Failed: ' . $conn->error);
}

$wallet_history = $result->fetch_all(MYSQLI_ASSOC);

// Sort URL generator
function sortLink($column, $label, $current_sort, $current_order) {
    $params = $_GET;
    $params['sort'] = $column;
    $next_order = ($current_sort == $column && $current_order == 'DESC') ? 'ASC' : 'DESC';
    $params['order'] = $next_order;
    return "<a href=\"?" . http_build_query($params) . "\">$label</a>";
}

include 'admin_header.php';
?>

<div class="container-fluid" style="padding:10px 20px; font-size:12px; width:100%;">
    <h4>지갑 거래 내역 관리</h4>

    <!-- Search Form -->
    <form action="" method="GET" class="search-form mb-3">
        <div class="row g-2">
            <div class="col-md-3 col-sm-6">
                <label class="form-label">회원명/ID:</label>
                <input type="text" name="search_user" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search_user); ?>">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label">지갑주소:</label>
                <input type="text" name="wallet_address" class="form-control form-control-sm" value="<?php echo htmlspecialchars($wallet_address); ?>">
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label">토큰명:</label>
                <input type="text" name="symbol" class="form-control form-control-sm" value="<?php echo htmlspecialchars($symbol); ?>">
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label">거래유형:</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <option value="transfer" <?php echo $type === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                    <option value="deposit" <?php echo $type === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label">상태:</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>완료</option>
                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>실패</option>
                </select>
            </div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-2 col-sm-6">
                <label class="form-label">수량 범위:</label>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.000000000000000001" name="amount_min" class="form-control form-control-sm w50" placeholder="최소" value="<?php echo htmlspecialchars($amount_min); ?>">
                    <span class="input-group-text">~</span>
                    <input type="number" step="0.000000000000000001" name="amount_max" class="form-control form-control-sm w50" placeholder="최대" value="<?php echo htmlspecialchars($amount_max); ?>">
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <label class="form-label">기간:</label>
                <div class="input-group input-group-sm">
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
                    <span class="input-group-text">~</span>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label">수수료 유형:</label>
                <select name="fee_type" class="form-select form-select-sm">
                    <option value="">전체</option>
                    <option value="BNB" <?php echo $fee_type === 'BNB' ? 'selected' : ''; ?>>BNB</option>
                    <option value="SERE" <?php echo $fee_type === 'SERE' ? 'selected' : ''; ?>>SERE</option>
                </select>
            </div>
            <div class="col-md-4 col-sm-12 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-secondary me-2">검색</button>
                <a href="admin_wallet.php" class="btn btn-sm btn-outline-secondary">초기화</a>
            </div>
        </div>
    </form>

    <!-- Statistics -->
    <div class="alert alert-info py-2" style="font-size:12px;">
        <div class="row fs-16">
            <div class="col-md-4">
                <strong>검색 결과:</strong> 총<span class="badge bg-danger"> <?php echo number_format($sum_row['total_transactions']); ?>건</span>
                (완료: <?php echo number_format($sum_row['completed_count']); ?>건, 
                실패: <?php echo number_format($sum_row['failed_count']); ?>건)
            </div>
            <div class="col-md-4">
                <strong>총 거래량:</strong> <span class="badge bg-danger"><?php echo number_format($sum_row['total_amount'], 2); ?></span>
            </div>
            <div class="col-md-4">
                <strong>총 수수료:</strong> <span class="badge bg-danger"><?php echo number_format($sum_row['total_fee'], 6); ?></span>
            </div>
        </div>
    </div>

    <!-- Transaction History Table -->
    <div class="table-responsive">
        <table class="table table-striped table-sm" style="font-size:12px;">
            <thead>
                <tr>
                    <th><?php echo sortLink('id', '번호', $sort, $order); ?></th>
                    <th><?php echo sortLink('user_id', '회원', $sort, $order); ?></th>
                    <th><?php echo sortLink('symbol', '토큰명', $sort, $order); ?></th>
                    <th><?php echo sortLink('type', '유형', $sort, $order); ?></th>
                    <th><?php echo sortLink('amount', '수량', $sort, $order); ?></th>
                    <th>보낸이</th>
                    <th>받는이</th>
                    <th>트랜잭션해시</th>
                    <th><?php echo sortLink('fee', '수수료', $sort, $order); ?></th>
                    <th><?php echo sortLink('processed_at', '처리일', $sort, $order); ?></th>
                    <th><?php echo sortLink('status', '상태', $sort, $order); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wallet_history as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['user_name']) . '(' . $row['user_id'] . ')'; ?></td>
                    <td><?php echo htmlspecialchars($row['symbol']); ?></td>
                    <td><?php echo htmlspecialchars($row['type']); ?></td>
                    <td><?php echo number_format($row['amount'], 18); ?></td>
                    <td class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($row['from_address']); ?>">
                        <?php echo htmlspecialchars($row['from_address']); ?>
                    </td>
                    <td class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($row['to_address']); ?>">
                        <?php echo htmlspecialchars($row['to_address']); ?>
                    </td>
                    <td>
                        <a href="https://bscscan.com/tx/<?php echo htmlspecialchars($row['tx_hash']); ?>" target="_blank" 
                           class="text-truncate d-inline-block" style="max-width: 150px;" 
                           title="<?php echo htmlspecialchars($row['tx_hash']); ?>">
                            <?php echo htmlspecialchars($row['tx_hash']); ?>
                        </a>
                    </td>
                    <td><?php echo $row['fee_type'] . ' ' . number_format($row['fee'], 18); ?></td>
                    <td><?php echo $row['processed_at']; ?></td>
                    <td>
                        <span class="badge <?php echo $row['status'] === 'completed' ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($wallet_history)): ?>
                <tr>
                    <td colspan="11" class="text-center">거래 내역이 없습니다.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-3">
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            이전
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?' . 
                         http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor;

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?' . 
                         http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            다음
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>

<!-- Add mobile-specific styles -->
<style>
@media (max-width: 768px) {
    .container-fluid {
        padding: 5px !important;
    }
    
    .table {
        font-size: 11px !important;
    }
    
    .table td, .table th {
        padding: 0.3rem !important;
    }
    
    .text-truncate {
        max-width: 100px !important;
    }
    
    .form-label {
        font-size: 12px;
        margin-bottom: 0.2rem;
    }
    
    .input-group-text {
        padding: 0.25rem 0.5rem;
        font-size: 11px;
    }
    
    .btn {
        padding: 0.25rem 0.5rem;
        font-size: 11px;
    }
    
    .pagination {
        margin: 10px 0;
    }
    
    .page-link {
        padding: 0.25rem 0.5rem;
        font-size: 11px;
    }
    
    .alert {
        padding: 0.5rem;
        margin-bottom: 0.5rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?>