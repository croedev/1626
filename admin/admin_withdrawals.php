<?php
session_start();
require_once '../includes/config.php';


session_start(); // 세션 시작



$conn = db_connect();

// 출금 상태 업데이트 처리 - POST 처리 로직을 먼저 배치
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $withdrawal_id = $_POST['withdrawal_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $admin_comment = $_POST['admin_comment'] ?? '';

    if ($withdrawal_id && $status) {
        $processed_date = ($status === 'completed' || $status === 'rejected') ? date('Y-m-d H:i:s') : null;
        
        $stmt = $conn->prepare("UPDATE withdrawals SET status = ?, processed_date = ?, admin_comment = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("sssi", $status, $processed_date, $admin_comment, $withdrawal_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "출금 요청 #$withdrawal_id 의 상태가 성공적으로 업데이트되었습니다.";
            } else {
                $_SESSION['error_message'] = "업데이트 중 오류가 발생했습니다.";
            }
            $stmt->close();
        }
    }
    
    header("Location: admin_withdrawals.php");
    exit();
}

// 검색 파라미터 처리
$search_member = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// WHERE 절 생성
$where_clauses = [];
$params = [];
$param_types = '';

if ($search_member) {
    $search_member = "%$search_member%";
    $where_clauses[] = "(u.name LIKE ? OR u.phone LIKE ?)";
    $params[] = $search_member;
    $params[] = $search_member;
    $param_types .= 'ss';
}

if ($start_date && $end_date) {
    $where_clauses[] = "DATE(w.request_date) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $param_types .= 'ss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// 출금 신청 내역 조회
$sql = "SELECT w.*, u.name AS user_name, u.phone
        FROM withdrawals w 
        JOIN users u ON w.user_id = u.id 
        $where_sql 
        ORDER BY w.request_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$withdrawals = $result->fetch_all(MYSQLI_ASSOC);

// 합계 계산
$total_amount = array_sum(array_column($withdrawals, 'withdrawal_amount'));
$total_fee = array_sum(array_column($withdrawals, 'fee'));

include 'admin_header.php';
?>

<div class="container-fluid" style="padding:10px 20px; font-size:13px;">
    <h4>출금 신청 관리</h4>

    <!-- 검색 폼 -->
    <form method="GET" class="mb-4">
        <div class="row align-items-end">
            <div class="col-auto">
                <label>회원 검색:</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search_member) ?>" class="form-control">
            </div>
            <div class="col-auto">
                <label>시작일:</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-control">
            </div>
            <div class="col-auto">
                <label>종료일:</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-control">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">검색</button>
                <a href="admin_withdrawals.php" class="btn btn-secondary">초기화</a>
            </div>
        </div>
    </form>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <!-- 합계 표시 -->
    <div class="mb-3">
        <strong>검색결과 합계:</strong> 
        요청금액: <?= number_format($total_amount) ?>원, 
        수수료: <?= number_format($total_fee) ?>원
    </div>

    <!-- 테이블 -->
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>사용자</th>
                    <th>요청금액</th>
                    <th>수수료</th>
                    <th>은행명</th>
                    <th>계좌번호</th>
                    <th>예금주</th>
                    <th>주민번호</th>
                    <th>요청일</th>
                    <th>처리일</th>
                    <th>상태</th>
                    <th>액션</th>
                    <th>메모</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($withdrawals as $withdrawal): ?>
                <tr>
                    <td><?= $withdrawal['id'] ?></td>
                    <td><?= htmlspecialchars($withdrawal['user_name']) ?></td>
                    <td><?= number_format($withdrawal['withdrawal_amount']) ?>원</td>
                    <td><?= number_format($withdrawal['fee']) ?>원</td>
                    <td><?= htmlspecialchars($withdrawal['bank_name']) ?></td>
                    <td><?= htmlspecialchars($withdrawal['account_number']) ?></td>
                    <td><?= htmlspecialchars($withdrawal['account_holder']) ?></td>
                    <td><?= htmlspecialchars($withdrawal['jumin']) ?></td>
                    <td><?= $withdrawal['request_date'] ?></td>
                    <td><?= $withdrawal['processed_date'] ?></td>
                    <td>
                        <form method="POST" action="admin_withdrawals.php">
                            <input type="hidden" name="withdrawal_id" value="<?= $withdrawal['id'] ?>">
                            <?php if ($withdrawal['status'] === 'completed'): ?>
                                <span class="text-success fw-bold">처리완료</span>
                            <?php else: ?>
                                <select name="status" class="form-select form-select-sm" style="width: auto;">
                                    <option value="pending" <?= $withdrawal['status'] === 'pending' ? 'selected' : '' ?>>처리중</option>
                                    <option value="completed" <?= $withdrawal['status'] === 'completed' ? 'selected' : '' ?>>처리완료</option>
                                    <option value="rejected" <?= $withdrawal['status'] === 'rejected' ? 'selected' : '' ?>>출금거절</option>
                                </select>
                            <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($withdrawal['status'] !== 'completed'): ?>
                            <button type="submit" name="update_status" class="btn btn-primary btn-sm">업데이트</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-sm" disabled>업데이트</button>
                        <?php endif; ?>
                    </td>
                    <td>
                        <input type="text" name="admin_comment" 
                               value="<?= htmlspecialchars($withdrawal['admin_comment']) ?>" 
                               class="form-control form-control-sm" 
                               <?= $withdrawal['status'] === 'completed' ? 'readonly' : '' ?>>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>