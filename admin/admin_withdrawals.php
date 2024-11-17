<?php
session_start();
require_once '../includes/config.php';

$conn = db_connect();

// 출금 상태 업데이트 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $withdrawal_id = $_POST['withdrawal_id'];
    $status = $_POST['status'];
    $admin_comment = $_POST['admin_comment'] ?? '';

    // 처리일자 설정
    if ($status === 'completed' || $status === 'rejected') {
        $processed_date = date('Y-m-d H:i:s');
    } else {
        $processed_date = null;
    }

    // 상태 업데이트
    $stmt = $conn->prepare("UPDATE withdrawals SET status = ?, processed_date = ?, admin_comment = ? WHERE id = ?");
    $stmt->bind_param("sssi", $status, $processed_date, $admin_comment, $withdrawal_id);
    $stmt->execute();

    // 사용자 정보 가져오기
    $stmt = $conn->prepare("SELECT w.*, u.phone, u.name FROM withdrawals w JOIN users u ON w.user_id = u.id WHERE w.id = ?");
    $stmt->bind_param("i", $withdrawal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $withdrawal = $result->fetch_assoc();

    // SMS 발송 (status가 completed인 경우)
    if ($status === 'completed') {
        $receiver_phone = preg_replace('/[^0-9]/', '', $withdrawal['phone']);
        $sms_msg = "[출금 완료 안내]\n출금 요청액: " . number_format($withdrawal['withdrawal_amount']) . "원\n수수료: " . number_format($withdrawal['fee']) . "원\n입금액: " . number_format($withdrawal['withdrawal_amount'] - $withdrawal['fee']) . "원\n" . $withdrawal['bank_name'] . " " . $withdrawal['account_number'] . " 계좌로 출금 처리되었습니다.\n(주)케이팬덤 고객관리팀(1533-3790)";

// 알리고 API 설정 (실제 값으로 변경해야 함)
$api_key = 'm7873h00n5b9ublnzwgkflakw86dgabm'; // 알리고에서 발급받은 API 키
$aligo_user_id = 'kgm4679'; // 알리고 사용자 ID
$sender = '010-3603-4679'; // 알리고에 등록된 발신번호

        // 알리고 API에 필요한 데이터 설정
        $sms_data = array(
            'key' => $api_key,
            'user_id' => $aligo_user_id,
            'sender' => $sender,
            'receiver' => $receiver_phone,
            'msg' => $sms_msg,
            'msg_type' => 'LMS',
            'title' => '[케이팬덤] 출금 완료 안내',
            'testmode_yn' => 'N'
        );

        // cURL을 사용하여 알리고 API 호출
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://apis.aligo.in/send/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($sms_data));
        $response = curl_exec($ch);
        curl_close($ch);

        // 응답 처리 (실제로는 응답을 확인하고 로그를 남겨야 함)
    }

    $_SESSION['success_message'] = "출금 요청 #$withdrawal_id 의 상태가 업데이트되었습니다.";
    header("Location: admin_withdrawals.php");
    exit();
}

// 출금 신청 내역 가져오기
$stmt = $conn->prepare("SELECT w.*, u.name AS user_name FROM withdrawals w JOIN users u ON w.user_id = u.id ORDER BY request_date DESC");
$stmt->execute();
$result = $stmt->get_result();
$withdrawals = $result->fetch_all(MYSQLI_ASSOC);

include 'admin_header.php';
?>

<div class="container" style="padding:10px 0px; font-size:13px;">
    <h4>출금 신청 관리</h4>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="table-responsive" style="overflow-x: auto;">
        <table class="table table-striped table-sm" style="min-width: 1200px;">
            <thead>
                <tr>
                    <th style="min-width: 50px;">ID</th>
                    <th style="min-width: 150px;">사용자 (ID)</th>
                    <th style="min-width: 100px;">요청 금액</th>
                    <th style="min-width: 80px;">수수료</th>
                    <th style="min-width: 100px;">은행명</th>
                    <th style="min-width: 150px;">계좌 번호</th>
                    <th style="min-width: 100px;">예금주</th>
                    <th style="min-width: 150px;">요청일</th>
                    <th style="min-width: 150px;">처리일</th>
                    <th style="min-width: 100px;">상태</th>                    
                    <th style="min-width: 100px;">액션</th>
                    <th style="min-width: 150px;">메모</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($withdrawals as $withdrawal): ?>
                <tr>
                    <td><?php echo $withdrawal['id']; ?></td>
                    <td><?php echo $withdrawal['user_id'] . ' (' . htmlspecialchars($withdrawal['user_name']) . ')'; ?></td>
                    <td><?php echo number_format($withdrawal['withdrawal_amount']); ?>원</td>
                    <td><?php echo number_format($withdrawal['fee']); ?>원</td>
                    <td><?php echo htmlspecialchars($withdrawal['bank_name']); ?></td>
                    <td><?php echo htmlspecialchars($withdrawal['account_number']); ?></td>
                    <td><?php echo htmlspecialchars($withdrawal['account_holder']); ?></td>
                    <td><?php echo $withdrawal['request_date']; ?></td>
                    <td><?php echo $withdrawal['processed_date']; ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['id']; ?>">
                            <?php if ($withdrawal['status'] == 'completed'): ?>
                                <span style="color: green; font-weight: bold;">처리완료</span>
                            <?php else: ?>
                                <select name="status" class="form-control" style="width:auto; display:inline; color:red; font-size:13px;">
                                    <option value="pending" <?php if ($withdrawal['status'] == 'pending') echo 'selected'; ?>>처리중</option>
                                    <option value="completed">처리완료</option>
                                    <option value="rejected" <?php if ($withdrawal['status'] == 'rejected') echo 'selected'; ?>>출금거절</option>
                                </select>
                            <?php endif; ?>
                    </td>
                      <td>
                        <?php if ($withdrawal['status'] != 'completed'): ?>
                            <button type="submit" name="update_status" class="btn btn-primary btn-sm">업데이트</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-sm" disabled>업데이트</button>
                        <?php endif; ?>
                        </form>
                    </td>
                    <td>
                        <input type="text" name="admin_comment" value="<?php echo htmlspecialchars($withdrawal['admin_comment']); ?>" class="form-control" style="width:150px;" <?php if ($withdrawal['status'] == 'completed') echo 'readonly'; ?>>
                    </td>
                  
                </tr>
                <?php endforeach; ?>
                <?php if (empty($withdrawals)): ?>
                <tr>
                    <td colspan="12" class="text-center">출금 신청 내역이 없습니다.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
