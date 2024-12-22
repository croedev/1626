<?php

date_default_timezone_set('Asia/Seoul');

// Composer autoload 및 Dotenv 설정
require_once __DIR__ . '/../vendor/autoload.php';

// Dotenv 초기화 (.env 파일이 웹 루트 상위에 있음)
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();

    // 필수 환경변수 확인
    $dotenv->required([
        'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME',
        'COMPANY_PRIVATE_KEY', 'ENCRYPTION_KEY'
    ])->notEmpty();
} catch (Exception $e) {
    error_log('Environment configuration error: ' . $e->getMessage());
    die('Configuration Error: Please contact administrator');
}

// 안전한 환경변수 접근 함수
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// 데이터베이스 설정
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_USER', env('DB_USER'));
define('DB_PASS', env('DB_PASS'));
define('DB_NAME', env('DB_NAME'));

// 사이트 설정
define('SITE_NAME', env('SITE_NAME', '예수 세례주화 NFT 프로젝트'));
define('SITE_URL', env('SITE_URL', 'https://1626.lidyahk.com'));

// BSC 설정
define('BSC_RPC_URL', env('BSC_RPC_URL'));
define('BSC_CHAIN_ID', env('BSC_CHAIN_ID'));
define('SERE_CONTRACT', env('SERE_CONTRACT'));
define('BSCSCAN_API_KEY', env('BSCSCAN_API_KEY'));

// 개인키 암호화/복호화 함수
function encryptPrivateKey($privateKey, $encryptionKey) {
    $ivlen = openssl_cipher_iv_length($cipher = "AES-256-CBC");
    $iv = openssl_random_pseudo_bytes($ivlen);
    $encrypted = openssl_encrypt($privateKey, $cipher, $encryptionKey, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptPrivateKey($encryptedData, $encryptionKey) {
    try {
        $data = base64_decode($encryptedData);
        $cipher = "AES-256-CBC";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = substr($data, 0, $ivlen);
        $encrypted = substr($data, $ivlen);
        $decrypted = openssl_decrypt($encrypted, $cipher, $encryptionKey, 0, $iv);
        if ($decrypted === false) {
            throw new Exception("Decryption failed");
        }
        return $decrypted;
    } catch (Exception $e) {
        error_log('Decryption error: ' . $e->getMessage());
        return false;
    }
}

// 개인키 처리
$privateKey = env('COMPANY_PRIVATE_KEY');
$encryptionKey = env('ENCRYPTION_KEY');
define('COMPANY_PRIVATE_KEY_ENCRYPTED', encryptPrivateKey($privateKey, $encryptionKey));

// 개인키 사용 함수
function getCompanyPrivateKey() {
    $decrypted = decryptPrivateKey(COMPANY_PRIVATE_KEY_ENCRYPTED, env('ENCRYPTION_KEY'));
    // 16진수 문자열 검증 및 정규화
    $key = preg_replace('/^0x/', '', trim($decrypted));
    if (!preg_match('/^[a-f0-9]{64}$/i', $key)) {
        error_log('Invalid private key format');
        throw new Exception('Invalid private key format');
    }
    return $key;
}

// SERE 토큰 설정
define('COMPANY_SERE_ADDRESS', env('COMPANY_SERE_ADDRESS'));
define('SWAP_FEE_PERCENTAGE', 5);

// SMTP 설정
define('SMTP_HOST', env('SMTP_HOST'));
define('SMTP_PORT', env('SMTP_PORT'));
define('SMTP_USERNAME', env('SMTP_USERNAME'));
define('SMTP_PASSWORD', env('SMTP_PASSWORD'));
define('SMTP_FROM', env('SMTP_FROM'));
define('SMTP_FROM_NAME', env('SMTP_FROM_NAME'));



// 카카오 API 설정
define('KAKAO_API_KEY', env('KAKAO_API_KEY'));

// ABI 정의는 그대로 유지
$SERE_ABI = json_encode([
    [
        "constant" => true,
        "inputs" => [["name" => "_owner", "type" => "address"]],
        "name" => "balanceOf",
        "outputs" => [["name" => "balance", "type" => "uint256"]],
        "type" => "function"
    ],
    [
        "constant" => false,
        "inputs" => [
            ["name" => "_to", "type" => "address"],
            ["name" => "_value", "type" => "uint256"]
        ],
        "name" => "transfer",
        "outputs" => [["name" => "", "type" => "bool"]],
        "type" => "function"
    ]
]);

// 기존 함수들은 그대로 유지
function db_connect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Connection failed");
    }
    return $conn;
}



/**
 * 세션 시작 함수
 * 세션이 시작되지 않은 경우에만 세션을 시작합니다.
 */
function start_session_if_not_started() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * 로그인 체크 함수
 * 
 * @return bool 로그인 상태 여부
 */
function is_logged_in() {
    start_session_if_not_started();
    return isset($_SESSION['user_id']);
}



/**
 * 로그인 필요 페이지 체크 함수
 * 로그인되지 않은 경우 로그인 페이지로 리다이렉트합니다.
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: /login");
        exit;
    }
}





/**
 * 사용자 정보 조회 함수
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 사용자 ID
 * @return array 사용자 정보
 * @throws Exception 사용자 정보 조회 실패 시 예외 발생
 */
function getUserInfo($conn, $user_id) {
    try {
        $query = "SELECT * FROM users WHERE id = ?";
        /* id, name, email, rank, direct_referrals_count, total_distributor_count, 
                  special_distributor_count, rank_update_date, direct_volume, referrals_volume, 
                  ref_total_volume 
        */
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("사용자 정보를 찾을 수 없습니다.");
        }
        return $result->fetch_assoc();
    } catch (Exception $e) {
        error_log("사용자 정보 조회 오류: " . $e->getMessage());
        return null;
    } finally {
        if (isset($stmt) && $stmt !== false) {
            $stmt->close();
        }
    }
}



/**
 * 추천 코드 생성 함수
 * 
 * @param int $user_id 사용자 ID
 * @return string 생성된 추천 코드
 */
function generateReferralCode($user_id) {
    // 사용자 ID를 기반으로 고유한 추천 코드 생성
    $base = $user_id . time();
    return substr(md5($base), 0, 8);
}

/**
 * QR 코드 생성 함수
 * 
 * @param string $referral_code 추천 코드
 * @return string QR 코드 이미지 URL
 */
function generateQRCode($referral_code) {
    // Google Chart API를 사용하여 QR 코드 생성
    $qr_url = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode(SITE_URL . "/join?ref=" . $referral_code);
    return $qr_url;
}


/**
 * 이메일 중복 체크 함수
 * 
 * @param string $email 체크할 이메일 주소
 * @return bool 중복 여부
 */
function is_email_duplicate($email) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $duplicate = $result->num_rows > 0;
    $stmt->close();
    return $duplicate;
}

/**
 * 전화번호 중복 체크 함수
 * 
 * @param string $phone 체크할 전화번호
 * @return bool 중복 여부
 */
function is_phone_duplicate($phone) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    $duplicate = $result->num_rows > 0;
    $stmt->close();
    return $duplicate;
}




// PHPMailer 클래스 로드
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

/**
 * 이메일 전송 함수
 * 
 * @param string $to 수신자 이메일 주소
 * @param string $subject 이메일 제목
 * @param string $body 이메일 본문 (HTML 형식)
 * @return bool 전송 성공 여부
 */
function send_email($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // 서버 설정
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL 사용
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // 수신자 설정
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);

        // 내용 설정
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}



// 수수료 및 직급 관련 설정

// 직급 승급 조건
define('TOTAL_DISTRIBUTOR_THRESHOLD', 1000);  // 총판 승급 조건: 총 구매 수량 1000개
define('SPECIAL_DISTRIBUTOR_THRESHOLD', 10);  // 특판 승급 조건: 하위 총판 10명

// 수수료 지급 비율
define('DISTRIBUTOR_COMMISSION', 0.30);  // 총판의 하위 조직 수익 수수료: 30%
define('SPECIAL_DISTRIBUTOR_COMMISSION', 0.10);  // 특판의 하위 조직 수익 수수료: 10% (특판 제외)

// 할인율
define('DISTRIBUTOR_DISCOUNT', 0.30);  // 총판은 구매 시 30% 할인
define('SPECIAL_DISTRIBUTOR_DISCOUNT', 0.40);  // 특판은 구매 시 40% 할인

// 출금 관련 설정
define('MIN_WITHDRAWAL_AMOUNT', 50000);  // 최소 출금 금액: 5만원
define('WITHDRAWAL_FEE_RATE', 0.033);    // 출금 수수료율: 3.3%

// 기타 설정
define('PAGINATION_LIMIT', 20);  // 페이지네이션에서 한 페이지당 표시할 항목 수
define('ADMIN_EMAIL', 'kncalab@gmail.com');  // 관리자 이메일



//order
function getCurrentPricingTier($conn) {
    $query = "SELECT * FROM pricing_tiers ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    return $result->fetch_assoc();
}

function getTotalSoldQuantity($conn) {
    $query = "SELECT total_quantity, remaining_quantity FROM pricing_tiers ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    return $row['total_quantity'] - $row['remaining_quantity'];
}
