<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once 'includes/config.php';
require_once 'includes/commission_functions.php';
date_default_timezone_set('Asia/Seoul');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
    exit;
}

$requestBody = file_get_contents('php://input');
// error_log('Request Body: ' . $requestBody);

$input = json_decode($requestBody, true);

if ($input) {
    try {
        $conn = db_connect();
        $conn->begin_transaction();

        // 입력 데이터 유효성 검사 부분 위에 추가
        $useCashPoint = 0.00;
        $useMileagePoint = 0.00;

        // 입력 데이터 유효성 검사
        $quantity = filter_var($input['quantity'], FILTER_VALIDATE_INT);
        $price = filter_var($input['price'], FILTER_VALIDATE_FLOAT);
        $price = intval($price); // 정수로 변환
        $paymentMethod = filter_var($input['paymentMethod'], FILTER_SANITIZE_STRING);
        $depositorName = filter_var($input['depositorName'], FILTER_SANITIZE_STRING);
        $depositDate = isset($input['depositDate']) ? filter_var($input['depositDate'], FILTER_SANITIZE_STRING) : null;
        $productId = filter_var($input['productId'], FILTER_VALIDATE_INT);

        // 포인트 결제인 경우에만 포인트 관련 변수 처리
        if ($paymentMethod === 'point') {
            $useCashPoint = filter_var($input['useCashPoint'], FILTER_VALIDATE_FLOAT) ?? 0.00;
            $useMileagePoint = filter_var($input['useMileagePoint'], FILTER_VALIDATE_FLOAT) ?? 0.00;
        }

        if (!$quantity || !$price || !$paymentMethod || !$productId) {
            throw new Exception('잘못된 입력 데이터입니다.');
        }

        $totalAmount = $quantity * $price;

        // 공통 주문 정보 준비
        $nft_token = 0;
        $paymentDate = NULL;
        $status = 'pending';

        if ($paymentMethod === 'point') {
            $status = 'completed';
            $paymentDate = date('Y-m-d H:i:s');
            $nft_token = $quantity;

            // 사용자 정보 및 포인트 검증
            $stmt = $conn->prepare("SELECT name, cash_points, mileage_points FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $depositorName = $user['name'];

            if ($useCashPoint > floatval($user['cash_points']) || $useMileagePoint > floatval($user['mileage_points'])) {
                throw new Exception('사용 가능한 포인트를 초과했습니다.');
            }

            if (($useCashPoint + $useMileagePoint) != $totalAmount) {
                throw new Exception('포인트 사용 금액이 주문 총액과 일치하지 않습니다.');
            }

            // 사용자의 포인트 차감
            $stmt = $conn->prepare("UPDATE users SET cash_points = cash_points - ?, mileage_points = mileage_points - ? WHERE id = ?");
            $stmt->bind_param("ddi", $useCashPoint, $useMileagePoint, $_SESSION['user_id']);
            $stmt->execute();

            
        } elseif ($paymentMethod === 'bank') {
            // 계좌 입금의 경우 기본값 사용 (status = 'pending', paymentDate = NULL)
        } elseif ($paymentMethod === 'paypal') {
            $depositorName = NULL;
        } else {
            throw new Exception('잘못된 결제 방법입니다.');
        }

        // 주문 생성 (모든 결제 방식에 공통)
        $stmt = $conn->prepare("INSERT INTO orders (user_id, product_id, price_unit, quantity, nft_token, total_amount, payment_method, depositor_name, cash_point_used, mileage_point_used, status, payment_date, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("iiiidssddssss",
            $_SESSION['user_id'],
            $productId,
            $price,
            $quantity,
            $nft_token,
            $totalAmount,
            $paymentMethod,
            $depositorName,
            $useCashPoint,
            $useMileagePoint,
            $status,
            $paymentDate,
            $_SERVER['REMOTE_ADDR']
        );

        if (!$stmt->execute()) {
            throw new Exception("주문 생성 실패: " . $stmt->error);
        }
        $order_id = $conn->insert_id;




/*
        // 포인트 결제의 경우 수수료 계산 및 직급 업데이트
        if ($paymentMethod === 'point') {
            try {
               // calculateAndProcessCommissions($conn, $order_id);  수수료계산 제외
                //updateUserRank($conn, $_SESSION['user_id']);
                processNFTAndRank($conn, $order_id);  // 포인트 결제시 수수료 제외, NFT 토큰 및 직급 업데이트

            } catch (Exception $e) {
                error_log('수수료 계산 및 직급 업데이트 오류: ' . $e->getMessage());
            }
        }

*/


if ($paymentMethod === 'point') {
    $status = 'completed';
    $paymentDate = date('Y-m-d H:i:s');
    $nft_token = $quantity;

    // 사용자 정보 및 포인트 검증
    $stmt = $conn->prepare("SELECT name, cash_points, mileage_points FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $depositorName = $user['name'];

    // 포인트 타입 결정
    $point_type = $useCashPoint > 0 ? 'cash_point' : 'mileage_point';

    // 포인트 차감
    if ($point_type === 'cash_point') {
        if ($useCashPoint > floatval($user['cash_points'])) {
            throw new Exception('사용 가능한 캐시 포인트를 초과했습니다.');
        }
        $stmt = $conn->prepare("UPDATE users SET cash_points = cash_points - ? WHERE id = ?");
        $stmt->bind_param("di", $useCashPoint, $_SESSION['user_id']);
    } else {
        if ($useMileagePoint > floatval($user['mileage_points'])) {
            throw new Exception('사용 가능한 마일리지 포인트를 초과했습니다.');
        }
        $stmt = $conn->prepare("UPDATE users SET mileage_points = mileage_points - ? WHERE id = ?");
        $stmt->bind_param("di", $useMileagePoint, $_SESSION['user_id']);
    }
    $stmt->execute();

    // 주문 생성 후 포인트 타입에 따른 처리
    if ($point_type === 'cash_point') {
        // 캐시 포인트 결제: 수수료 계산 및 직급 업데이트
        calculateAndProcessCommissions($conn, $order_id);
    } else {
        // 마일리지 포인트 결제: NFT 토큰 및 직급만 업데이트
        processNFTAndRank($conn, $order_id);
    }
}





        $conn->commit();

        echo json_encode(['success' => true, 'order_id' => $order_id]);
        exit;

    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        error_log('주문 처리 중 오류 발생: ' . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => '주문 처리 중 오류가 발생했습니다: ' . $e->getMessage(), 
            'order_id' => isset($order_id) ? $order_id : null
        ]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}