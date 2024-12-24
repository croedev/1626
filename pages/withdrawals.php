<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 사용자 인증 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=withdrawals");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = db_connect();

// 사용자 정보 가져오기
$stmt = $conn->prepare("SELECT cash_points FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$error = '';
$success = '';

// 출금 신청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $withdrawal_amount = filter_input(INPUT_POST, 'withdrawal_amount', FILTER_VALIDATE_FLOAT);
    $bank_name = filter_input(INPUT_POST, 'bank_name', FILTER_SANITIZE_STRING);
    $account_number = filter_input(INPUT_POST, 'account_number', FILTER_SANITIZE_STRING);
    $account_holder = filter_input(INPUT_POST, 'account_holder', FILTER_SANITIZE_STRING);
    $jumin = filter_input(INPUT_POST, 'jumin', FILTER_SANITIZE_STRING);

    // 입력값 검증
    if (!$withdrawal_amount || !$bank_name || !$account_number || !$account_holder || !$jumin) {
        $error = "모든 필드를 입력해주세요.";
    } elseif (!preg_match('/^\d{6}-\d{7}$/', $jumin)) {
        $error = "올바른 주민등록번호 형식이 아닙니다.";
    } elseif ($withdrawal_amount < MIN_WITHDRAWAL_AMOUNT) {
        $error = "최소 출금 금액은 " . number_format(MIN_WITHDRAWAL_AMOUNT) . "원 입니다.";
    } elseif ($withdrawal_amount > $user['cash_points']) {
        $error = "출금 가능한 금액을 초과했습니다.";
    } else {
        try {
            $conn->begin_transaction();

            // 출금 수수료 계산 (3.3%)
            $fee = round($withdrawal_amount * WITHDRAWAL_FEE_RATE);
            
            // 출금 신청 기록
            $stmt = $conn->prepare("
                INSERT INTO withdrawals (
                    user_id, withdrawal_amount, fee, bank_name, 
                    account_number, account_holder, jumin, status, 
                    request_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");

            if (!$stmt) {
                throw new Exception("쿼리 준비 실패: " . $conn->error);
            }

            $stmt->bind_param("iddssss", 
                $user_id, $withdrawal_amount, $fee, $bank_name, 
                $account_number, $account_holder, $jumin
            );

            if (!$stmt->execute()) {
                throw new Exception("쿼리 실행 실패: " . $stmt->error);
            }

            // 사용자의 캐시 포인트 차감
            $stmt = $conn->prepare("
                UPDATE users 
                SET cash_points = cash_points - ? 
                WHERE id = ? AND cash_points >= ?
            ");
            $stmt->bind_param("ddd", $withdrawal_amount, $user_id, $withdrawal_amount);
            
            if (!$stmt->execute()) {
                throw new Exception("포인트 차감 실패: " . $stmt->error);
            }

            if ($stmt->affected_rows === 0) {
                throw new Exception("잔액이 부족합니다.");
            }

            $conn->commit();
            $success = "출금 신청이 완료되었습니다.";

            // 관리자에게 이메일 알림
            $admin_email = ADMIN_EMAIL;
            $subject = "[케이팬덤] 새로운 출금 신청";
            $message = "새로운 출금 신청이 있습니다.\n";
            $message .= "신청자: {$account_holder}\n";
            $message .= "금액: " . number_format($withdrawal_amount) . "원\n";
            $message .= "원천징수(세금): " . number_format($fee) . "원\n";
            $message .= "실수령액: " . number_format($withdrawal_amount - $fee) . "원\n";
            $message .= "은행: {$bank_name}\n";
            $message .= "계좌번호: {$account_number}\n";
            
            send_email($admin_email, $subject, $message);

        } catch (Exception $e) {
            $conn->rollback();
            error_log("출금 신청 오류: " . $e->getMessage());
            $error = "출금 신청 중 오류가 발생했습니다: " . $e->getMessage();
        }
    }
}

// 이전 출금 내역 조회
$stmt = $conn->prepare("
    SELECT * FROM withdrawals 
    WHERE user_id = ? 
    ORDER BY request_date DESC 
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$withdrawals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = '출금 신청';
include __DIR__ . '/../includes/header.php';
?>




<style>
        .withdrawal-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            color: #ffffff;
        }

        .balance-info {
            background-color: #2a2a2a;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .withdrawal-form {
            background-color: #2a2a2a;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            color: #d4af37;
        }

        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #555;
            background-color: #333;
            color: #fff;
            border-radius: 4px;
        }

        .jumin-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .jumin-part {
            flex: 1;
            text-align: center;
            letter-spacing: 2px;
        }

        .jumin-separator {
            color: #d4af37;
            font-weight: bold;
            padding: 0 4px;
        }

        .text-muted {
            color: #888 !important;
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

        .withdrawal-history {
            background-color: #2a2a2a;
            padding: 20px;
            border-radius: 5px;
        }

        .error-message {
            color: #ff6b6b;
            margin-bottom: 10px;
        }

        .success-message {
            color: #4cd137;
            margin-bottom: 10px;
        }

        .withdrawal-cards {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 20px 0;
        }

        .withdrawal-card {
            background: linear-gradient(145deg, #2a2a2a, #333333);
            border: 1px solid #444;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-body {
            padding: 10px 20px;
        }

        .amount-row, .fee-row, .process-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .label {
            color: #888;
            font-size: 0.9rem;
        }

        .value {
            color: #fff;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* style 태그 내에 추가 */
        .jumin-part {
            background-color: #333 !important;  /* 다른 입력 필드와 동일한 배경색 */
            color: #fff !important;
        }

        .jumin-part:-webkit-autofill,
        .jumin-part:-webkit-autofill:hover,
        .jumin-part:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 30px #333 inset !important;
            -webkit-text-fill-color: #fff !important;
        }

</style>

<div class="withdrawal-container">
    <div class="balance-info">
        <h4>출금 가능 금액</h4>
        <p class="text-warning fs-18"><?php echo number_format($user['cash_points']); ?>원</p>
        <p class="text-white mt10- fs-14">*최소 출금금액: <?php echo number_format(MIN_WITHDRAWAL_AMOUNT); ?>원</p>
        <p class="text-white mt20- fs-14">*원천징수(세금): <?php echo WITHDRAWAL_FEE_RATE * 100; ?>%</p>
        <p class="text-red5 fs-14 fw-500 notosans">* 출금신청은 <span class="fw-700 text-warning">매주(수)요일까지</span> 신청접수를 받아, <span class="fw-700 text-warning">매주(금)요일</span> 일괄지급됩니다. </p>
    </div>

    <?php if ($error): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="post" action="" class="withdrawal-form">
        <!-- 출금 금액 입력 -->
        <div class="form-group">
            <label for="withdrawal_amount" class="form-label">출금 신청 금액</label>
            <input type="number" id="withdrawal_amount" name="withdrawal_amount" class="form-control" 
                   min="<?php echo MIN_WITHDRAWAL_AMOUNT; ?>" 
                   max="<?php echo $user['cash_points']; ?>" required>
            <div id="fee_info" class="text-warning mt-2"></div>
        </div>

        <!-- 은행 선택 -->
        <div class="form-group">
            <label for="bank_name" class="form-label">은행명</label>
            <select id="bank_name" name="bank_name" class="form-control" required>
                <option value="">선택하세요</option>
                <option value="KB국민은행">KB국민은행</option>
                <option value="신한은행">신한은행</option>
                <option value="우리은행">우리은행</option>
                <option value="하나은행">하나은행</option>
                <option value="NH농협은행">NH농협은행</option>
                <option value="IBK기업은행">IBK기업은행</option>
                <option value="SC제일은행">SC제일은행</option>
                <option value="씨티은행">씨티은행</option>
                <option value="KDB산업은행">KDB산업은행</option>
                <option value="수협은행">수협은행</option>
                <option value="DGB대구은행">DGB대구은행</option>
                <option value="BNK부산은행">BNK부산은행</option>
                <option value="광주은행">광주은행</option>
                <option value="제주은행">제주은행</option>
                <option value="전북은행">전북은행</option>
                <option value="경남은행">경남은행</option>
                <option value="새마을금고">새마을금고</option>
                <option value="신협">신협</option>
                <option value="우체국">우체국</option>
                <option value="카카오뱅크">카카오뱅크</option>
                <option value="케이뱅크">케이뱅크</option>
                <option value="토스뱅크">토스뱅크</option>
            </select>
        </div>


        <!-- 계좌번호 입력 -->
        <div class="form-group">
            <label for="account_number" class="form-label">계좌번호</label>
            <input type="text" id="account_number" name="account_number" class="form-control" required>
        </div>

        <!-- 예금주 입력 -->
        <div class="form-group">
            <label for="account_holder" class="form-label">예금주</label>
            <input type="text" id="account_holder" name="account_holder" class="form-control" required>
        </div>

        <!-- 주민등록번호 입력 -->
        <div class="form-group">
            <label for="jumin" class="form-label">주민등록번호</label>

<div class="jumin-input-group">
    <input type="text" id="jumin1" maxlength="6" class="form-control jumin-part" required autocomplete="off" value="">
    <span class="jumin-separator">-</span>
    <input type="password" id="jumin2" maxlength="7" class="form-control jumin-part" required autocomplete="off" value="">
    <input type="hidden" id="jumin" name="jumin">
</div>
            <p class="text-gray fs-12 mt-2">* 원천징수 신고시 주민등록번호가 필요합니다. 개인정보 보호원칙에 따라 보호됩니다.</p>
        </div>

        <button type="submit" class="btn-gold">출금 신청</button>
    </form>





<!-- 출금 내역 표시 부분 -->
    <div class="withdrawal-history mb100">
        <h5 class="notosans">최근 출금 신청내역</h5>
        <div class="withdrawal-cards">
            <?php if (empty($withdrawals)): ?>
                <div class="no-history text-center">출금 내역이 없습니다.</div>
            <?php else: ?>
                <?php foreach ($withdrawals as $withdrawal): ?>
                    <div class="withdrawal-card ">
                        <div class="card-header flex-x-between px-20 pt10 pb10 bg-gray90">
                            <div class="request-date"><?php echo date('Y-m-d', strtotime($withdrawal['request_date'])); ?></div>
                            <?php
                            switch($withdrawal['status']) {
                                case 'pending':
                                    echo '<div class="status text-warning">처리중</div>';
                                    break;
                                case 'completed':
                                    echo '<div class="status text-success">완료</div>';
                                    break;
                                case 'rejected':
                                    echo '<div class="status text-danger">거절됨</div>';
                                    break;
                                default:
                                    echo '<div class="status">' . $withdrawal['status'] . '</div>';
                            }
                            ?>
                        </div>
                        <div class="card-body">
                            <div class="amount-row">
                                <span class="label">신청금액</span>
                                <span class="value"><?php echo number_format($withdrawal['withdrawal_amount']); ?>원</span>
                            </div>
                            <div class="fee-row">
                                <span class="label">수수료(3.3%)</span>
                                <span class="value"><?php echo number_format($withdrawal['fee']); ?>원</span>
                            </div>
                                                        <div class="fee-row">
                                <span class="label">은행계좌</span>
                                <span class="value fs-12"><?=$withdrawal['bank_name']?> <?=$withdrawal['account_number']?> <?=$withdrawal['account_holder']?></span>
                            </div>
                            <div class="process-row">
                                <span class="label">처리일</span>
                                <span class="value"><?php echo $withdrawal['processed_date'] ?: '-'; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 주민등록번호 입력 필드 초기화
    document.getElementById('jumin1').value = '';
    document.getElementById('jumin2').value = '';
    document.getElementById('jumin').value = '';
    
    // 출금 금액 입력 시 수수료 계산
    document.getElementById('withdrawal_amount').addEventListener('input', function(e) {
        const amount = parseFloat(e.target.value) || 0;
        const fee = Math.round(amount * <?php echo WITHDRAWAL_FEE_RATE; ?>);
        const actualAmount = amount - fee;
        
        if (amount > 0) {
            document.getElementById('fee_info').innerHTML = 
                `수수료: ${fee.toLocaleString()}원<br>` +
                `실수령액: ${actualAmount.toLocaleString()}원`;
        } else {
            document.getElementById('fee_info').innerHTML = '';
        }
    });

    // 계좌번호 형식 정리
    document.getElementById('account_number').addEventListener('input', function(e) {
        let value = e.target.value.replace(/[^0-9]/g, '');
        let parts = [];
        
        while (value.length > 4) {
            parts.push(value.slice(-4));
            value = value.slice(0, -4);
        }
        if (value.length > 0) {
            parts.push(value);
        }
        
        e.target.value = parts.reverse().join('-');
    });

    // 주민등록번호 앞자리 입력 처리
    document.getElementById('jumin1').addEventListener('input', function(e) {
        let value = e.target.value.replace(/[^0-9]/g, '');
        e.target.value = value;
        
        if (value.length === 6) {
            document.getElementById('jumin2').focus();
        }
    });

    // 주민등록번호 뒷자리 입력 처리
    document.getElementById('jumin2').addEventListener('input', function(e) {
        let value = e.target.value.replace(/[^0-9]/g, '');
        e.target.value = value;
    });

    // 폼 제출 처리
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const jumin1 = document.getElementById('jumin1').value;
        const jumin2 = document.getElementById('jumin2').value;
        
        if (jumin1.length !== 6 || jumin2.length !== 7) {
            alert('올바른 주민등록번호를 입력해주세요.');
            return;
        }

        // 주민번호 유효성 검사
        const jumin = jumin1 + '-' + jumin2;
        if (!isValidJumin(jumin1, jumin2)) {
            alert('올바른 주민등록번호가 아닙니다.');
            return;
        }

        // 주민번호를 hidden 필드에 설정
        document.getElementById('jumin').value = jumin;
        
        // 폼 제출
        this.submit();
    });

    // 주민번호 유효성 검사 함수
    function isValidJumin(jumin1, jumin2) {
        const pattern = /^(?:[0-9]{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[1,2][0-9]|3[0,1]))-[1-4][0-9]{6}$/;
        const jumin = jumin1 + '-' + jumin2;
        return pattern.test(jumin);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>