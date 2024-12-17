<?php
session_start();
require_once '../includes/config.php';



$conn = db_connect();

// 검색 파라미터
$search_member = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// WHERE 절 생성
$where_clauses = [];

if ($search_member !== '') {
    $search_member_escaped = '%' . $conn->real_escape_string($search_member) . '%';
    // 회원 이름 또는 전화번호로 검색
    $where_clauses[] = "(u.name LIKE '$search_member_escaped' OR u.phone LIKE '$search_member_escaped')";
}

if ($start_date !== '' && $end_date !== '') {
    $start_escaped = $conn->real_escape_string($start_date);
    $end_escaped = $conn->real_escape_string($end_date);
    $where_clauses[] = "DATE(pa.apply_date) BETWEEN '$start_escaped' AND '$end_escaped'";
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// 데이터 조회
$sql = "
    SELECT pa.*, u.name as user_name, u.myAmount
    FROM prize_apply pa
    JOIN users u ON pa.user_id = u.id
    $where_sql
    ORDER BY pa.apply_date DESC
";
$result = $conn->query($sql);
if (!$result) {
    die('Query Failed: ' . $conn->error);
}

$prize_applies = $result->fetch_all(MYSQLI_ASSOC);

// 합계 계산
$sum_apply_amount = 0;
foreach ($prize_applies as $pa) {
    $sum_apply_amount += $pa['apply_amount'];
}

include 'admin_header.php';
?>

<div class="container-fluid" style="padding:10px 20px; font-size:13px; width:100%;">
    <h4>경품 응모 관리</h4>

    <!-- 검색 폼 시작 -->
    <form action="" method="GET" class="search-form" style="margin-bottom:20px;">
        <label for="search">회원 검색 (이름/전화번호):</label>
        <input type="text" name="search" value="<?php echo htmlspecialchars($search_member); ?>" placeholder="회원 이름 또는 전화번호" style="margin-right:10px;">

        <label for="start_date">시작 날짜:</label>
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="margin-right:10px;">

        <label for="end_date">종료 날짜:</label>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="margin-right:10px;">

        <button type="submit" class="btn btn-sm btn-secondary" style="margin-right:10px;">검색</button>
        <a href="admin_prize_apply.php" class="btn btn-sm btn-outline-secondary">초기화</a>
    </form>
    <!-- 검색 폼 종료 -->

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <!-- 합계 표시 -->
    <div style="margin-bottom:20px;">
        <strong>검색결과 합계:</strong><br>
        사용금액 합계: <?php echo number_format($sum_apply_amount, 2); ?>원
    </div>

    <div class="table-responsive" style="width:100%;">
        <table class="table table-striped table-sm" style="width:100%;">
            <thead>
                <tr>
                    <th style="min-width: 50px;">번호</th>
                    <th style="min-width: 150px;">신청회원</th>
                    <th style="min-width: 100px;">누적신청횟수</th>
                    <th style="min-width: 150px;">신청일</th>
                    <th style="min-width: 120px;">당첨번호</th>
                    <th style="min-width: 100px;">사용금액</th>
                    <th style="min-width: 100px;">보유금액</th>
                    <th style="min-width: 100px;">당첨상태</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prize_applies as $pa): ?>
                <tr>
                    <td><?php echo $pa['id']; ?></td>
                    <td><?php echo htmlspecialchars($pa['user_name']) . '(' . $pa['user_id'] . ')'; ?></td>
                    <td><?php echo number_format($pa['apply_count']); ?>회</td>
                    <td><?php echo $pa['apply_date']; ?></td>
                    <td><?php echo htmlspecialchars($pa['prize_no']); ?></td>
                    <td><?php echo number_format($pa['apply_amount'], 2); ?>원</td>
                    <td><?php echo number_format($pa['myAmount'], 2); ?>원</td>
                    <td>
                        <?php
                        // 당첨상태 표시
                        switch($pa['win_status']) {
                            case 'pending':
                                echo '<span style="color:blue;">대기중</span>';
                                break;
                            case 'won':
                                echo '<span style="color:green; font-weight:bold;">당첨</span>';
                                break;
                            case 'lost':
                                echo '<span style="color:red;">미당첨</span>';
                                break;
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($prize_applies)): ?>
                <tr>
                    <td colspan="8" class="text-center">응모 내역이 없습니다.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
