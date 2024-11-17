<?php
// 오류 로깅 강화
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 실행 시간 제한 늘리기 (필요한 경우)
set_time_limit(30);

// 메모리 제한 늘리기 (필요한 경우)
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../includes/config.php';

$pageTitle = '비밀번호 재설정';
include __DIR__ . '/../includes/header.php';

$error = '';
$success = '';

// GET 파라미터에서 토큰 추출
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    $error = "토큰이 제공되지 않았습니다.";
} else {
    try {
        $conn = db_connect();
        if (!$conn) {
            throw new Exception("데이터베이스 연결에 실패했습니다.");
        }
        
        $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
        if (!$stmt) {
            throw new Exception("SQL 쿼리 준비 중 오류가 발생했습니다: " . $conn->error);
        }
        
        $stmt->bind_param("s", $token);
        if (!$stmt->execute()) {
            throw new Exception("쿼리 실행 중 오류가 발생했습니다: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $error = "유효하지 않거나 만료된 토큰입니다.";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Reset password error: " . $e->getMessage());
        $error = "오류가 발생했습니다: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$error) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($password) || empty($confirm_password)) {
        $error = "모든 필드를 입력하세요.";
    } elseif ($password !== $confirm_password) {
        $error = "비밀번호가 일치하지 않습니다.";
    } elseif (strlen($password) < 4) {
        $error = "비밀번호는 최소 4자 이상이어야 합니다.";
    } else {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE reset_token = ?");
            if (!$stmt) {
                throw new Exception("SQL 쿼리 준비 중 오류가 발생했습니다: " . $conn->error);
            }
            
            $stmt->bind_param("ss", $hashed_password, $token);
            if (!$stmt->execute()) {
                throw new Exception("쿼리 실행 중 오류가 발생했습니다: " . $stmt->error);
            }

            if ($stmt->affected_rows > 0) {
                $success = "비밀번호가 성공적으로 변경되었습니다.";
            } else {
                $error = "비밀번호 변경에 실패했습니다.";
            }
            
            $stmt->close();
        } catch (Exception $e) {
            error_log("Reset password error: " . $e->getMessage());
            $error = "비밀번호 변경 중 오류가 발생했습니다: " . $e->getMessage();
        }
    }
}

if ($conn) {
    $conn->close();
}
?>



<style>
    body {
        background-color: #000;
        color: #d4af37;
   font-family: 'Noto serif KR', serif;
    }
    .reset-password-container {
        max-width: 400px;
        margin: 50px auto;
        padding: 20px;
        background-color: rgba(42, 42, 42, 0.9);
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        text-align: center;
    }
    .form-group {
        margin-bottom: 15px;
        text-align: left;
    }
    .form-label {
        display: block;
        margin-bottom: 5px;
    }
    .form-control {
        width: 100%;
        padding: 8px;
        border: 1px solid #d4af37;
        background-color: #333;
        color: #fff;
        border-radius: 4px;
    }
    .btn-gold {
        background: linear-gradient(to right, #d4af37, #f2d06b);
        border: none;
        color: #000;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
        font-weight: bold;
        margin-top: 10px;
    }
    .error, .success {
        margin-bottom: 10px;
    }
    .error {
        color: #ff6b6b;
    }
    .success {
        color: #4cd137;
    }
</style>

<div class="reset-password-container">
 <img src="../assets/images/goldkey.png" width="200" height="" alt="로고">
    <h2>비밀번호 재설정</h2>
    <hr>
    <?php if ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php elseif ($success): ?>
        <p class="success"><?php echo $success; ?></p>
        <a href="/login" class="btn-gold mt30 btn-md">로그인하기</a>
    <?php else: ?>
        <form method="post" action="">
            <div class="form-group">
                <label for="password" class="form-label">새 비밀번호</label>
                <input type="password" id="password" name="password" class="form-control" required minlength="4">
            </div>
            <div class="form-group">
                <label for="confirm_password" class="form-label">새 비밀번호 확인</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="4">
            </div>
            <button type="submit" class="btn-gold">비밀번호 변경</button>
        </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
