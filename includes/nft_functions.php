<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');
?>

<?php
require_once 'config.php';

/**
 * NFT 거래 내역 가져오기
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 사용자 ID
 * @return array NFT 거래 내역
 */
function getNFTHistory($conn, $user_id) {
    $query = "SELECT nh.*, u1.name as from_user_name, u2.name as to_user_name 
              FROM nft_history nh
              LEFT JOIN users u1 ON nh.from_user_id = u1.id
              LEFT JOIN users u2 ON nh.to_user_id = u2.id
              WHERE nh.from_user_id = ? OR nh.to_user_id = ?
              ORDER BY nh.transaction_date DESC
              LIMIT 20";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    return $history;
}

/**
 * NFT 전송 함수
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $from_user_id 보내는 사용자 ID
 * @param int $to_user_id 받는 사용자 ID
 * @param int $amount 전송할 NFT 수량
 * @return bool 전송 성공 여부
 */
function transferNFT($conn, $from_user_id, $to_user_id, $amount) {
    $conn->begin_transaction();

    try {
        // 보내는 사용자의 NFT 차감 (정확히 전송하는 양만큼만 차감)
        $query = "UPDATE users SET nft_token = nft_token - ? WHERE id = ? AND nft_token >= ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $amount, $from_user_id, $amount);
        $stmt->execute();
        
        if ($stmt->affected_rows == 0) {
            throw new Exception("충분한 NFT가 없습니다.");
        }

        // 받는 사용자의 NFT 증가
        $query = "UPDATE users SET nft_token = nft_token + ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $amount, $to_user_id);
        $stmt->execute();

        // 거래 내역 기록
        $query = "INSERT INTO nft_history (from_user_id, to_user_id, amount, transaction_type) VALUES (?, ?, ?, '선물/전송')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $from_user_id, $to_user_id, $amount);
        $stmt->execute();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("NFT 전송 오류: " . $e->getMessage());
        return false;
    }
}

/**
 * 총 받은 NFT 수량 계산
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 사용자 ID
 * @return int 총 받은 NFT 수량
 */
function getTotalReceivedNFT($conn, $user_id) {
    $query = "SELECT SUM(amount) as total FROM nft_history WHERE to_user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

/**
 * 총 보낸 NFT 수량 계산
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 사용자 ID
 * @return int 총 보낸 NFT 수량
 */
function getTotalSentNFT($conn, $user_id) {
    $query = "SELECT SUM(amount) as total FROM nft_history WHERE from_user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

/**
 * 사용자 검색 함수
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param string $search_term 검색어
 * @return array 검색 결과
 */
function searchUsers($conn, $search_term) {
    $search_term = "%$search_term%";
    $query = "SELECT id, name, phone FROM users WHERE name LIKE ? OR phone LIKE ? LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $search_term, $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'phone' => substr($row['phone'], 0, -4) . '****'  // 전화번호 마스킹
        ];
    }
    
    return $users;
}

/**
 * SMS 발송 함수
 * @param string $phone 수신자 전화번호
 * @param int $amount 전송된 NFT 수량
 * @return bool 발송 성공 여부
 */
function sendSMS($phone, $amount) {
    // 알리고 API 설정
    $api_key = 'm7873h00n5b9ublnzwgkflakw86dgabm';
    $aligo_user_id = 'kgm4679';
    $sender = '010-3603-4679';

    $receiver_phone = preg_replace('/[^0-9]/', '', $phone);
    $sms_msg = "[세례주화 NFT 선물 알림]\n{$amount}개의 NFT를 선물 받았습니다. 마이페이지에서 확인해 주세요.\n케이팬덤 고객지원팀:1533-3790";

    $sms_data = array(
        'key' => $api_key,
        'user_id' => $aligo_user_id,
        'sender' => $sender,
        'receiver' => $receiver_phone,
        'msg' => $sms_msg,
        'msg_type' => 'SMS',
        'testmode_yn' => 'N'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://apis.aligo.in/send/');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($sms_data));
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('SMS 전송 실패: cURL 오류 - ' . $error);
        return false;
    } else {
        $result = json_decode($response, true);
        if ($result['result_code'] != '1') {
            error_log('SMS 전송 실패: ' . $result['message']);
            return false;
        }
        return true;
    }
}