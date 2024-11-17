<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');
?>

<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/commission_functions.php';


$conn = db_connect();

if (!$conn) {
    die("데이터베이스 연결 실패: " . mysqli_connect_error());
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = $error_message = '';

// 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt === false) {
        die("SQL 준비 오류: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        die("SQL 실행 오류: " . $stmt->error);
    }
    $_SESSION['success_message'] = "회원이 성공적으로 삭제되었습니다.";
    header("Location: admin_members.php");
    exit();
}

// 회원 정보 수정 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $rank = sanitizeInput($_POST['rank']);
    $quantity = intval($_POST['quantity']);
    $status = sanitizeInput($_POST['status']);
    $referred_by = (int)sanitizeInput($_POST['referred_by']);

    // 회원 정보 업데이트
    $stmt = $conn->prepare("
        UPDATE users 
        SET name = ?, 
            email = ?, 
            phone = ?, 
            rank = ?, 
            status = ?, 
            referred_by = ? 
        WHERE id = ?
    ");
    $stmt->bind_param("sssssii", 
        $name, $email, $phone, $rank, $status, $referred_by, $user_id
    );
    $stmt->execute();


    
// 수량 지급 처리
if ($quantity > 0) {
    $conn->begin_transaction();

    try {
        // 1. orders 테이블에 구매 실적 등록
        $order_date = date('Y-m-d H:i:s');
        $total_amount = $quantity * 2000;
        
        $stmt = $conn->prepare("
            INSERT INTO orders (
                user_id, 
                product_id, 
                quantity, 
                nft_token,
                total_amount, 
                status, 
                payment_method, 
                created_at,
                payment_date
            ) VALUES (?, 1, ?, ?, ?, 'completed', 'admin', ?, ?)
        ");
        
        // i: integer (user_id)
        // i: integer (quantity)
        // i: integer (nft_token)
        // d: double (total_amount)
        // s: string (created_at)
        // s: string (payment_date)
        $stmt->bind_param("iiidss", 
            $user_id,      // integer
            $quantity,     // integer
            $quantity,     // integer (nft_token)
            $total_amount, // double
            $order_date,   // string
            $order_date    // string
        );

        if (!$stmt->execute()) {
            throw new Exception("주문 생성 실패: " . $stmt->error);
        }

            $order_id = $conn->insert_id;

            // 2. 수수료 계산 및 처리
            calculateAndProcessCommissions($conn, $order_id);
            
            // 3. 직급 업데이트
            updateUserRank($conn, $user_id);

            $conn->commit();
            $success_message = "회원 정보와 수량이 성공적으로 업데이트되었습니다.";

        } catch (Exception $e) {
            $conn->rollback();
            error_log("관리자 수량 지급 오류: " . $e->getMessage());
            $error_message = "오류 발생: " . $e->getMessage();
        }
    } else {
        $success_message = "회원 정보가 성공적으로 수정되었습니다.";
    }
}

// 사용자 정보 가져오기
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("사용자를 찾을 수 없습니다.");
}

// 추천인 정보 가져오기
$stmt = $conn->prepare("SELECT id, name FROM users WHERE id = ?");
$stmt->bind_param("i", $user['referred_by']);
$stmt->execute();
$result = $stmt->get_result();
$referrer = $result->fetch_assoc();

$pageTitle = '회원 정보 수정';
include __DIR__ . '/admin_header.php';
?>

<div class="container mt-4">
    <h2>회원 정보 수정</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label for="name" class="form-label">이름</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">이메일</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="phone" class="form-label">전화번호</label>
            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
        </div>


<? /*
        <div class="mb-3">
            <label for="cash_points" class="form-label">캐시 포인트</label>
            <input type="number" class="form-control" id="cash_points" name="cash_points" value="<?php echo $user['cash_points']; ?>" step="0.01">
        </div>
        <div class="mb-3">
            <label for="mileage_points" class="form-label">마일리지 포인트</label>
            <input type="number" class="form-control" id="mileage_points" name="mileage_points" value="<?php echo $user['mileage_points']; ?>" step="0.01">
        </div>
*/?>

        <div class="mb-3">
            <label for="rank" class="form-label">직급</label>
            <select class="form-control" id="rank" name="rank">
                <option value="회원" <?php echo $user['rank'] === '회원' ? 'selected' : ''; ?>>회원</option>
                <option value="총판" <?php echo $user['rank'] === '총판' ? 'selected' : ''; ?>>총판</option>
                <option value="특판" <?php echo $user['rank'] === '특판' ? 'selected' : ''; ?>>특판</option>
                <option value="특판A" <?php echo $user['rank'] === '특판A' ? 'selected' : ''; ?>>특판A</option>
            </select>
        </div>

        <? /*
        <div class="mb-3">
            <label for="rank_change_date" class="form-label">직급 승급일</label>
            <input type="date" class="form-control" id="rank_change_date" name="rank_change_date" value="<?php echo $user['rank_update_date']; ?>">
        </div>
        <div class="mb-3">
            <label for="rank_change_reason" class="form-label">승급 사유</label>
            <textarea class="form-control" id="rank_change_reason" name="rank_change_reason"><?php echo htmlspecialchars($user['rank_change_reason'] ?? ''); ?></textarea>
        </div>

        */?>
        <div class="mb-3">
            <label for="quantity" class="form-label">수량 지급</label>
            <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="0">
        </div>
        <div class="mb-3">
            <label for="referred_by" class="form-label">추천인</label>
            <select class="form-control" id="referred_by" name="referred_by">
                <option value="">없음</option>
                <?php
                $stmt = $conn->prepare("SELECT id, name FROM users WHERE id != ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $selected = ($row['id'] == $user['referred_by']) ? 'selected' : '';
                    echo "<option value='{$row['id']}' $selected>{$row['name']} ({$row['id']})</option>";
                }
                ?>
            </select>
        </div>

                <div class="mb-3">
            <label for="status" class="form-label">상태</label>
            <select class="form-control" id="status" name="status">
                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>활성</option>
                <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>비활성</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">정보 수정</button>
        <a href="/admin/admin_members.php" class="btn btn-secondary">돌아가기</a>
    </form>
</div>

<?php include __DIR__ . '/admin_footer.php'; ?>
