<?php
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

    // 입력값 검증
    if (!$withdrawal_amount || !$bank_name || !$account_number || !$account_holder) {
        $error = "모든 필드를 입력해주세요.";
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
                    account_number, account_holder, status, request_date
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->bind_param("iddsss", 
                $user_id, $withdrawal_amount, $fee, $bank_name, 
                $account_number, $account_holder
            );
            $stmt->execute();

            // 사용자의 캐시 포인트 차감
            $stmt = $conn->prepare("
                UPDATE users 
                SET cash_points = cash_points - ? 
                WHERE id = ? AND cash_points >= ?
            ");
            $stmt->bind_param("ddd", $withdrawal_amount, $user_id, $withdrawal_amount);
            $stmt->execute();

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

.history-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.history-table th,
.history-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #444;
}

.history-table th {
    color: #d4af37;
}

.error-message {
    color: #ff6b6b;
    margin-bottom: 10px;
}

.success-message {
    color: #4cd137;
    margin-bottom: 10px;
}
</style>

<div class="withdrawal-container">
    <div class="balance-info">
        <h4>출금 가능 금액</h4>
        <p class="text-warning fs-18"><?php echo number_format($user['cash_points']); ?>원</p>
        <p class="text-orange mt20- fs-16">*최소 출금 금액: <?php echo number_format(MIN_WITHDRAWAL_AMOUNT); ?>원</p>
        <p class="text-orange mt20- fs-16">*원천징수(세금): <?php echo WITHDRAWAL_FEE_RATE * 100; ?>%</p>
        <p class="text-red5 fs-14 fw-600 notosans">* 출금신청은 <span class="fw-700 text-warning">매주(수)요일까지</span> 신청접수를 받아, <span class="fw-700 text-warning">매주(금)요일</span> 일괄지급됩니다. </p>
    </div>

    <?php if ($error): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-message"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="post" action="" class="withdrawal-form">
        <div class="form-group">
            <label for="withdrawal_amount" class="form-label">출금 신청 금액</label>
            <input type="number" id="withdrawal_amount" name="withdrawal_amount" class="form-control" 
                   min="<?php echo MIN_WITHDRAWAL_AMOUNT; ?>" 
                   max="<?php echo $user['cash_points']; ?>" required>
            <div id="fee_info" class="text-warning mt-2"></div>
        </div>

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

        <div class="form-group">
            <label for="account_number" class="form-label">계좌번호</label>
            <input type="text" id="account_number" name="account_number" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="account_holder" class="form-label">예금주</label>
            <input type="text" id="account_holder" name="account_holder" class="form-control" required>
        </div>

        <button type="submit" class="btn-gold">출금 신청</button>
    </form>

    <div class="withdrawal-history mb100">
        <h4>최근 출금 신청내역</h4>
        <div class="withdrawal-cards">
            <?php if (empty($withdrawals)): ?>
                <div class="no-history text-center">출금 내역이 없습니다.</div>
            <?php else: ?>
                <?php foreach ($withdrawals as $withdrawal): ?>
                    <div class="withdrawal-card">
                        <div class="card-header">
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
                                <span class="label">수수료</span>
                                <span class="value"><?php echo number_format($withdrawal['fee']); ?>원</span>
                            </div>
                            <div class="process-row">
                                <span class="label">처리일</span>
                                <span class="value"><?php echo $withdrawal['processed_date']; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <style>
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

        .withdrawal-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            background: linear-gradient(90deg, #1a1a1a, #2d2d2d);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #444;
        }

        .request-date {
            color: #d4af37;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background-color: rgba(0, 0, 0, 0.2);
        }

        .card-body {
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.02);
        }

        .amount-row, .fee-row, .process-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .amount-row:last-child {
            border-bottom: none;
        }

        .label {
            color: #888;
            font-size: 0.9rem;
        }

        .value {
            color: #fff;
            font-weight: 500;
        }

        .no-history {
            padding: 30px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            color: #888;
            font-size: 0.9rem;
        }
        </style>
    </div>
</div>

<script>
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>