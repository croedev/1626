<?php
session_start();
require_once 'includes/config.php';

// 알리고 API 설정 (실제 값으로 변경해야 함)
$api_key = 'm7873h00n5b9ublnzwgkflakw86dgabm'; // 알리고에서 발급받은 API 키
$aligo_user_id = 'kgm4679'; // 알리고 사용자 ID
$sender = '010-3603-4679'; // 알리고에 등록된 발신번호

if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;
$order = null;
$sms_sent = false;
$sms_error = '';

// SMS 전송 활성화 여부를 제어하는 변수
$sms_enabled = false; // SMS 전송을 비활성화하려면 false로 설정

try {
    $conn = db_connect();
    if ($order_id) {
        $stmt = $conn->prepare("SELECT o.*, p.name AS product_name, u.phone AS user_phone 
                               FROM orders o
                               JOIN products p ON o.product_id = p.id
                               JOIN users u ON o.user_id = u.id
                               WHERE o.id = ? AND o.user_id = ?");
        $stmt->bind_param("ii", $order_id, $user_id);
    } else {
        $stmt = $conn->prepare("SELECT o.*, p.name AS product_name, u.phone AS user_phone 
                               FROM orders o
                               JOIN products p ON o.product_id = p.id
                               JOIN users u ON o.user_id = u.id
                               WHERE o.user_id = ?
                               ORDER BY o.created_at DESC LIMIT 1");
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    // 결제 방식이 계좌이체 또는 포인트인 경우 SMS 발송
    if ($sms_enabled && $order && ($order['payment_method'] === 'bank' || $order['payment_method'] === 'point')) {
        if ($order['payment_method'] === 'bank') {
            // 계좌이체 결제 시 메시지
            $bank_account = "KB국민은행 : 771301-01-847437";
            $account_holder = "(주)케이팬덤";
            $amount = number_format($order['total_amount']);
            $sms_msg = "[세례주화 구매접수,입금요청]\n구매가 접수되었습니다. 아래의 계좌로 입금해 주세요.\n\n계좌번호: {$bank_account}\n예금주: {$account_holder}\n입금액: {$amount}원\n\n4시간 이내 입금하지 않으면 자동취소처리됩니다.\n케이팬덤 고객지원팀:1533-3790";
        } elseif ($order['payment_method'] === 'point') {
            // 포인트 결제 시 메시지
            $sms_msg = "[세례주화 구매완료]\n포인트 결제가 완료되어, NFT토큰이 지급되었습니다.\n구매해 주셔서 감사합니다.\n케이팬덤 고객지원팀:1533-3790";
        }

        // 수신자 전화번호 가져오기 (전화번호 정규화)
        $receiver_phone = preg_replace('/[^0-9]/', '', $order['user_phone']);

        // 전화번호가 없는 경우 처리
        if (empty($receiver_phone)) {
            $sms_error = '사용자 전화번호가 없습니다.';
        } else {
            // 알리고 API에 필요한 데이터 설정
            $sms_data = array(
                'key' => $api_key,
                'user_id' => $aligo_user_id,
                'sender' => $sender,
                'receiver' => $receiver_phone,
                'msg' => $sms_msg,
                'msg_type' => 'LMS', // 장문 메시지
                'title' => '[케이팬덤] 구매 안내', // 메시지 제목
                'testmode_yn' => 'N' // 실전 모드 ('Y'로 설정하면 테스트 모드)
            );

            // cURL을 사용하여 알리고 API 호출
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://apis.aligo.in/send/');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($sms_data));
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            // 응답 처리
            if ($response === false) {
                error_log('SMS 전송 실패: cURL 오류 - ' . $error);
                $sms_error = 'SMS 전송 실패: cURL 오류 - ' . $error;
            } else {
                error_log('알리고 API 응답: ' . $response);
                $result = json_decode($response, true);
                if ($result['result_code'] != '1') {
                    // SMS 전송 실패 시 로그 남기기
                    error_log('SMS 전송 실패: ' . $result['message']);
                    $sms_error = 'SMS 전송 실패: ' . $result['message'];
                } else {
                    $sms_sent = true;
                }
            }
        }
    } else {
        // SMS 전송이 비활성화된 경우 로그 남기기
        error_log('SMS 전송이 비활성화되어 있습니다.');
        $sms_error = 'SMS 전송이 일시적으로 중단되었습니다.';
    }

} catch (Exception $e) {
    error_log("Database error in order_complete.php: " . $e->getMessage());
}

$pageTitle = '주문 완료';
include 'includes/header.php';
?>



<style>
body,
html {
    background-color: #000;
    color: #d4af37;
    font-family: 'Noto Sans KR', sans-serif;
    margin: 0;
    padding: 0;
    font-size: 14px;
    line-height: 1.0;
}

.content {
    padding: 20px;
    max-width: 600px;
    margin: 0 auto;
    padding-bottom: 60px;
    max-height: calc(100vh - 120px);
    overflow-y: auto;
}

.top-bar {
    background-color: #d4af37;
    color: #000;
    padding: 10px;
    text-align: center;
    font-weight: bold;
    margin-bottom: 20px;
}

.card {
    background-color: #111;
    border: 1px solid #333;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    color: #fff;
}

p {
    font-family: noto serif kr, serif;
    font-size: 0.8rem;
}

p span {
    font-family: noto sans kr, sans-serif;
}


h2 {
    color: #d4af37;
    font-family: 'Noto Serif KR', serif;
    margin-bottom: 20px;
    text-align: center;
}

p {
    margin-bottom: 10px;
}

.btn-gold {
    background: linear-gradient(to right, #d4af37, #f2d06b);
    border: none;
    color: #000;
    font-weight: bold;
    padding: 10px 20px;
    border-radius: 5px;
    width: 100%;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    margin-top: 20px;
}

.btn-gold:hover {
    opacity: 0.9;
}

.flex-center {
    display: flex;
    justify-content: center;
    align-items: center;
}

.button-group {
    display: flex;
    justify-content: end;
    margin-top: 0px;
    padding-top: 0px;
}

.btn-outline {
    background-color: transparent;
    border: 1px solid #d4af37;
    color: #d4af37;
    font-size: 1.0em;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 2px;
}

.btn-outline:hover {
    background-color: #d4af37;
    color: #000000;
}
</style>


<div class="content mt30 flex-center vh-100">
    <div class="card w-100">
        <h2>주문 완료</h2>
        
        <?php if ($order): ?>
            <div>
                <p><span>주문 번호:</span> <span class="text-orange"><?php echo htmlspecialchars($order['id']); ?></span></p>
                <p><span>상품명:</span> <span class="text-orange"><?php echo htmlspecialchars($order['product_name']); ?></span></p>
                <p><span>수량:</span> <span class="text-orange"><?php echo htmlspecialchars($order['quantity']); ?></span></p>
                <p><span>총 금액:</span> <span class="text-orange"><?php echo number_format($order['total_amount']); ?>원</span></p>
                <hr>
                <?php if ($order['payment_method'] === 'bank'): ?>
                    <p><span>결제 방법:</span> 계좌이체</p>
                    <p><span>입금 계좌:</span> KB국민은행 : 771301-01-847437</p>
                    <p><span>예금주:</span> (주)케이팬덤</p>
                    <p><span>입금자명:</span> <?php echo htmlspecialchars($order['depositor_name']); ?></p>
                    <hr>
                    <div class="text-center text-orange rem-10">
                        <p>주문하신 내용이 정상적으로 접수되었습니다.</p>
                        <p>입금이 확인되면 NFT 증명서가 발행됩니다.</p>
                    </div>
                    <hr>
                    <!-- <p>안내문자 전송결과: <span class="text-orange">
                        <?php
                        if ($sms_sent) {
                            echo '<span style="color: green;">전송완료</span>';
                        } else {
                            echo '<span style="color: red;">전송실패</span>';
                            if ($sms_error != '') {
                                echo '<br><span style="color: red;">' . htmlspecialchars($sms_error) . '</span>';
                            }
                        }
                        ?>
                    </span>
                   </p> -->
                <?php elseif ($order['payment_method'] === 'point'): ?>
                    <p><span>결제 방법:</span> 포인트 결제</p>
                    <p><span>사용 캐시 포인트:</span> <?php echo number_format($order['cash_point_used']); ?>원</p>
                    <p><span>사용 마일리지 포인트:</span> <?php echo number_format($order['mileage_point_used']); ?>원</p>
                    <hr>
                    <div class="text-center text-orange rem-10">
                        <p>주문하신 내용이 정상적으로 처리되었습니다.</p>
                        <p>포인트 결제가 완료되어 NFT 증명서가 발행되었습니다.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>주문 정보를 조회할 수 없습니다.</p>
            <p class="error-message" style="font-size: 0.8em; color: #ff6b6b;">주문 오류로 데이터를 불러오지 못했습니다.</p>
        <?php endif; ?>

        <hr>
        <a href="/" class="btn-gold"><i class="fas fa-home"></i> 홈으로</a>

        <div class="button-group mt30">
            <button class="btn-outline" onclick="location.href='/order_apply'"><i class="fas fa-plus-circle"></i> 추가구매하기</button>
            <button class="btn-outline" onclick="location.href='/order_list'">구매내역</button>
            <button class="btn-outline" onclick="location.href='/commission'">수수료조회</button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
