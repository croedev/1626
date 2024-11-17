<?php
require_once 'includes/config.php';
$pageTitle = '로그인';
include 'includes/header.php';

// 세션 시작
session_start();

// 로그인 폼 초기화
$_SESSION['login_attempt'] = null;

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email_or_phone = trim($_POST['email_or_phone']);
    $password = trim($_POST['password']);

    // 전화번호 형식 맞추기 (숫자만 입력된 경우)
    if (preg_match('/^\d+$/', $email_or_phone)) {
        $email_or_phone = preg_replace('/^(\d{3})(\d{3,4})(\d{4})$/', '$1-$2-$3', $email_or_phone);
    }

    // 입력 값 유효성 검사
    if (empty($email_or_phone)) {
        $error = "전화번호 또는 이메일을 입력하세요.";
    } elseif (empty($password)) {
        $error = "비밀번호를 입력하세요.";
    } else {
        // 데이터베이스 연결 시도
        $conn = db_connect();
        if ($conn->connect_error) {
            $error = "데이터베이스 연결에 실패했습니다: " . $conn->connect_error;
        } else {
            // 전화번호 형식 통일 (하이픈 제거)
            $email_or_phone_clean = preg_replace('/[^0-9a-zA-Z@.]/', '', $email_or_phone);
            
            // SQL 쿼리 준비 및 실행
            $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ? OR REPLACE(phone, '-', '') = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $email_or_phone, $email_or_phone_clean);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($user = $result->fetch_assoc()) {
                    // 비밀번호 확인
                    if (password_verify($password, $user['password'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        
                        // 관리자인 경우 관리자 페이지로 리다이렉트
                        if ($user['email'] === 'kncalab@gmail.com') {
                            header("Location: /admin/index.php");
                            exit();
                        } else {
                            header("Location: /"); // 일반 사용자 대시보드 또는 메인 페이지
                            exit();
                        }
                    } else {
                        $error = "잘못된 비밀번호입니다.";
                    }
                } else {
                    $error = "등록되지 않은 이메일 또는 전화번호입니다.";
                }
                $stmt->close();
            } else {
                $error = "SQL 쿼리 준비 중 오류가 발생했습니다: " . $conn->error;
            }
            $conn->close();
        }
    }
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
}

?>

<style>
    body {
        display: flex;
        justify-content: center;
        /* align-items: center; */
        margin: 0 auto;
        background-color: #000;
    }
    .login-container {
        max-width: 400px;
        padding: 20px;        
        border-radius: 10px;      
        text-align: center;
    }
    .form-group {
        margin-bottom: 15px;
        text-align: left;
    }
    .form-label {
        display: block;
        color: #d4af37;
        margin-bottom: 5px;
        font-family: 'Noto serif KR', serif;
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
        font-family: 'Noto Sans KR', sans-serif;
    }
    .error {
        color: #ff6b6b;
        margin-bottom: 10px;
    }
    .links {
        margin-top: 20px;
        text-align: center;
        font-size: 0.85rem;
    }
    .links a {
        color: #d4af37;
        text-decoration: none;
        margin: 0 10px;
    }
</style>

<div class="login-container">
    <img src="assets/images/logo.png" width="150" height="150" alt="로고" class="mp-0">
    <h2 class="mt10-">로그인</h2>
    <hr>
    <?php if ($error): ?>
        <p class="error"><?php echo $error; ?></p>
    <?php endif; ?>
    <form method="post" action="" id="login-form">
        <div class="form-group">
            <label for="email_or_phone" class="form-label"><i class="fas fa-phone"></i> 전화번호</label>
            <input type="text" id="email_or_phone" name="email_or_phone" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="password" class="form-label"><i class="fas fa-lock"></i> 비밀번호</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn-gold">로그인</button>
    </form>
    <div class="links">
        <a href="/join">> 회원가입</a>
        <a href="/forgot_password">> 비밀번호 찾기</a>
    </div>
</div>


<script>
    document.getElementById('email_or_phone').addEventListener('input', function (e) {
        var value = e.target.value.replace(/[^0-9]/g, '');
        if (value.length <= 11 && /^\d+$/.test(value)) {
            // 숫자만 입력된 경우 전화번호 형식으로 변환
            if (value.length > 3 && value.length <= 7) {
                e.target.value = value.slice(0, 3) + '-' + value.slice(3);
            } else if (value.length > 7) {
                e.target.value = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7);
            } else {
                e.target.value = value;
            }
        } else {
            // 이메일 입력의 경우 그대로 유지
            e.target.value = e.target.value.replace(/[^0-9a-zA-Z@.-]/g, '');
        }
    });

    // 폼 초기화
    window.addEventListener('load', function() {
        document.getElementById('login-form').reset();
    });

    // 로컬 스토리지의 자동 완성 비밀번호 제거
    if (localStorage.getItem('password')) {
        localStorage.removeItem('password');
    }
    document.getElementById('password').value = '';
</script>

<?php include 'includes/footer.php'; ?>