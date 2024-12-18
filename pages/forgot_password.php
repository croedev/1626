<?php
// `forgot_password.php`

// 필수 설정 및 함수 포함
require_once 'includes/config.php';

// 페이지 설정
$pageTitle = '비밀번호 재설정';
date_default_timezone_set('Asia/Seoul');

// 메시지 변수 초기화
$message = '';
$error = '';

// 폼이 제출되었을 때 처리
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 입력된 전화번호 받기 및 트림
    $phone = trim($_POST['phone']);

    if (empty($phone)) {
        $error = "전화번호를 입력하세요.";
    } else {
        // 전화번호 정규화
        function normalize_phone_number($phone) {
            // 숫자만 추출
            $phone = preg_replace('/[^0-9]/', '', $phone);
            // 형식 적용: 010-1234-5678
            if (strlen($phone) == 11) {
                $phone = preg_replace('/(\d{3})(\d{4})(\d{4})/', '$1-$2-$3', $phone);
            }
            return $phone;
        }

        $phone = normalize_phone_number($phone);

        // 데이터베이스 연결
        $conn = db_connect();
        if ($conn->connect_error) {
            $error = "데이터베이스 연결에 실패했습니다: " . $conn->connect_error;
        } else {
            // 전화번호로 사용자 조회
            $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
            if ($stmt) {
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($user = $result->fetch_assoc()) {
                    // 비밀번호 재설정 토큰 생성
                    $token = bin2hex(random_bytes(50));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // 토큰 및 만료 시간 업데이트
                    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("ssi", $token, $expires, $user['id']);
                        $stmt->execute();

                        // 비밀번호 재설정 링크 생성
                        $reset_link = SITE_URL . "/pages/reset_password.php?token=" . $token;

                        
                     // 알리고 API를 사용하여 SMS 발송
                        $api_key = 'm7873h00n5b9ublnzwgkflakw86dgabm'; // 알리고에서 발급받은 API 키
                        $user_id = 'kgm4679'; // 알리고 사용자 ID
                        $sender = '010-3603-4679'; // 알리고에 등록된 발신번호

                        // SMS 내용 설정 (문자 길이를 고려하여 간략하게 작성)
                        $sms_msg = "[케이팬덤 세례주화 프로젝트]\n아래 링크를 통해 비밀번호를 재설정할 수 있습니다.\n{$reset_link}\n케이팬덤 고객지원팀";

                        // 알리고 API에 필요한 데이터 설정
                        $sms_data = array(
                            'key' => $api_key,
                            'user_id' => $user_id,
                            'sender' => $sender,
                            'receiver' => $phone,
                            'msg' => $sms_msg,
                            'msg_type' => 'LMS', // 장문 메시지
                            'title' => '[케이팬덤] 비밀번호 재설정' // 메시지 제목
                        );

                        // cURL을 사용하여 알리고 API 호출
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, 'https://apis.aligo.in/send/');
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($sms_data));
                        $response = curl_exec($ch);
                        curl_close($ch);

                        // 응답 처리
                        $result = json_decode($response, true);

                        if ($result['result_code'] == '1') {
                            $message = "비밀번호 재설정 링크를 SMS로 전송했습니다.";
                        } else {
                            $error = "SMS 전송 중 오류가 발생했습니다: " . $result['message'];
                        }
                    } else {
                        $error = "토큰 업데이트 중 오류가 발생했습니다: " . $conn->error;
                    }
                } else {
                    $error = "해당되는 전화번호가 없습니다. 다시 입력하세요.";
                }
                $stmt->close();
            } else {
                $error = "SQL 쿼리 준비 중 오류가 발생했습니다: " . $conn->error;
            }
            $conn->close();
        }
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?php echo $pageTitle; ?></title>
    <!-- CSS 스타일 내장 -->
    <style>
        body {
            background-color: #000;
            color: #d4af37;
            font-family: 'Noto serif KR', serif;
        }
        .forgot-password-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
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
        }
        .btn-gold[disabled] {
            background-color: #aaa;
            cursor: not-allowed;
        }
        .error, .message {
            margin-bottom: 10px;
        }
        .error {
            color: #ff6b6b;
        }
        .message {
            color: #4cd137;
        }
    </style>
</head>
<body>
    <div class="forgot-password-container">
        <img src="assets/images/goldkey.png" width="200" alt="로고">
        <h2 class="fw-900">비밀번호 재설정</h2>
        <hr>
        <?php if ($message): ?>
            <div class="message">
                <p><?php echo $message; ?></p>
                <p class="rem-085 text-warning">SMS를 확인 후 비밀번호를 재설정하시기 바랍니다.</p>
                <a href="/" class="btn btn-gold btn-md mt30">홈으로</a>
            </div>
        <?php elseif ($error): ?>
            <p class="error"><?php echo $error; ?></p>
            <!-- 폼을 다시 표시하여 사용자에게 재입력 기회 제공 -->
            <form method="post" action="">
                <div class="form-group">
                    <label for="phone" class="form-label notosans">전화번호</label>
                    <input type="text" id="phone" name="phone" class="form-control" required>
                </div>
                <p class="fs-12 text-left">
                    * 회원가입 시 입력한 전화번호를 입력해 주세요.<br>
                    * 전화번호가 일치하면 해당 번호로 비밀번호 재설정 링크가 전송됩니다.<br>
                    * 확인 후 비밀번호를 재설정해 주세요.
                </p>
                <button type="submit" class="mt30 btn-gold">비밀번호 재설정 링크 받기</button>
            </form>
        <?php else: ?>
            <p>회원가입 시 입력한 전화번호를 입력해 주세요.</p>
            <hr>
            <form method="post" action="">
                <div class="form-group">
                    <label for="phone" class="form-label notosans">전화번호</label>
                    <input type="text" id="phone" name="phone" class="form-control" required>
                </div>
                <p class="fs-12 text-left">
                    * 회원가입 시 입력한 전화번호를 입력해 주세요.<br>
                    * 전화번호가 일치하면 해당 번호로 비밀번호 재설정 링크가 전송됩니다.<br>
                    * 확인 후 비밀번호를 재설정해 주세요.
                </p>
                <button type="submit" class="mt30 btn-gold">비밀번호 재설정 링크 받기</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- JavaScript 코드 내장 -->
    <script>
        // 전화번호 입력 시 실시간 정규화 및 검증
        document.getElementById('phone').addEventListener('input', function() {
            const phoneInput = this;

            // 전화번호 정규화: 숫자만 추출
            let phoneNumber = phoneInput.value.replace(/[^0-9]/g, '');

            // 형식 적용: 010-1234-5678
            if (phoneNumber.length >= 11) {
                phoneNumber = phoneNumber.replace(/(\d{3})(\d{4})(\d{4})/, '$1-$2-$3');
            } else if (phoneNumber.length >= 7) {
                phoneNumber = phoneNumber.replace(/(\d{3})(\d{3,4})(\d{0,4})/, '$1-$2-$3');
            }

            phoneInput.value = phoneNumber;
        });
    </script>
</body>
</html>
