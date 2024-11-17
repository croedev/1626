<?php /*
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../error.log');
*/
?>

<?php

date_default_timezone_set('Asia/Seoul');
// 데이터베이스 설정
define('DB_HOST', 'localhost');
define('DB_USER', 'lidyahkc_0');
define('DB_PASS', 'lidya2016$');
define('DB_NAME', 'lidyahkc_1626');

// 사이트 설정
define('SITE_NAME', '예수 세례주화 NFT 프로젝트');
define('SITE_URL', 'https://1626.lidyahk.com');

// 카카오 API 키
define('KAKAO_API_KEY', 'c9e708d6ad0e4ead5dc265350b6d4d89');


/**
 * 데이터베이스 연결 함수
 * 
 * @return mysqli 데이터베이스 연결 객체
 */
function db_connect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
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



// SMTP 설정
define('SMTP_HOST', 'mail.lidyahk.com');
define('SMTP_PORT', 465); // SSL 사용 시
define('SMTP_USERNAME', 'jesus@lidyahk.com');
define('SMTP_PASSWORD', 'lidya2016$');
define('SMTP_FROM', 'jesus@lidyahk.com');
define('SMTP_FROM_NAME', '예수 세례주화 NFT 프로젝트');



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
