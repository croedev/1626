<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/admin_functions.php';



$conn = db_connect();

// 회원 삭제 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id']);
    try {
        $result = deleteOrDeactivateUser($conn, $userId);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 페이지네이션 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 30;
$start = ($page - 1) * $perPage;

// 검색 조건
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = $search ? "WHERE name LIKE '%$search%' OR email LIKE '%$search%'" : '';

// 회원 목록 조회
$query = "SELECT * FROM users $searchCondition ORDER BY created_at DESC LIMIT $start, $perPage";
$result = $conn->query($query);
$members = $result->fetch_all(MYSQLI_ASSOC);

// 전체 회원 수 조회
$countQuery = "SELECT COUNT(*) as total FROM users $searchCondition";
$countResult = $conn->query($countQuery);
$totalMembers = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalMembers / $perPage);

require_once __DIR__ . '/admin_header.php';
?>

<h4>회원 관리</h4>

<form action="" method="GET" class="mb-3">
    <div class="input-group">
        <input type="text" name="search" class="form-control mr10" placeholder="이름, 이메일 또는 전화번호로 검색" value="<?php echo htmlspecialchars($search); ?>">
        <input type="date" name="start_date" class="form-control" placeholder="시작 날짜" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
        <input type="date" name="end_date" class="form-control" placeholder="종료 날짜" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
        <div class="input-group-append">
            <button type="submit" class="btn btn-primary">검색</button>
        </div>
    </div>
</form>

<table class="table table-striped">
    <thead class="thead-dark">
        <tr>
            <th>ID</th>
            <th>이름</th>
            <!-- <th>이메일</th> -->
             <th>전화번호</th>
            <th>직급</th>
            <th>추천인</th>
            <th>실적</th>
            <!-- <th>하위구매실적</th>
            <th>포인트</th>
            <th>마일리지</th>
            <th>하위총판수</th> -->
            <th>가입일</th>
            <th>액션</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($members as $member): ?>
        <tr>
            <td><?php echo $member['id']; ?></td>
            <td><?php echo htmlspecialchars($member['name']); ?></td>
            <!-- <td><?php echo htmlspecialchars($member['email']); ?></td> -->            
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
                echo $referrer_name ? htmlspecialchars($referrer_name) . '(' . $referred_by_id . ')' : '없음';
            ?></td>
            <td class="fs-12">구매수량:<?php echo htmlspecialchars($member['myQuantity']); ?><br>
        구매금액:<?php echo htmlspecialchars($member['myAmount']); ?> <br>
        NFT수량:<?php echo htmlspecialchars($member['nft_token']); ?>
        </td>
            <!-- <td><?php echo htmlspecialchars($member['ref_total_volume']); ?></td>
            <td><?php echo htmlspecialchars($member['cash_points']); ?></td>
            <td><?php echo htmlspecialchars($member['mileage_points']); ?></td>
            <td><?php echo htmlspecialchars($member['total_distributor_count']); ?></td> -->
            <td><?php echo date('Y-m-d', strtotime($member['created_at'])); ?></td>
            <td>
                <a href="member_edit.php?id=<?php echo $member['id']; ?>" class="btn btn-sm btn-primary">수정</a>
                <a href="#" class="btn btn-xs btn-danger" onclick="deleteUser(<?php echo $member['id']; ?>)">삭제</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<nav>
    <ul class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>

<?php
// admin_functions.php에 다음 함수를 추가합니다:
// function deleteUser($conn, $userId) {
//     $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
//     $stmt->bind_param("i", $userId);
//     return $stmt->execute();
// }

// 현재 파일 상단에 다음 코드를 추가합니다:
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
//     $userId = intval($_POST['user_id']);
//     if (deleteUser($conn, $userId)) {
//         echo json_encode(['success' => true]);
//     } else {
//         echo json_encode(['success' => false, 'message' => '사용자 삭제 중 오류가 발생했습니다.']);
//     }
//     exit;
// }
?>

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
                location.reload(); // 페이지 새로고침
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

<?php include __DIR__ . '/admin_footer.php'; ?>