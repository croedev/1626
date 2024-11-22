<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/commission_functions.php';

// 출력 버퍼 초기화
ob_clean();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => '로그인이 필요합니다.']));
}

try {
    $conn = db_connect();
    $conn->begin_transaction();

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if ($input === null) {
        throw new Exception('잘못된 JSON 데이터입니다: ' . json_last_error_msg());
    }

    // 기본 데이터 검증
    $quantity = (int)($input['quantity'] ?? 0);
    $price = (int)($input['price'] ?? 0);
    $paymentMethod = $input['paymentMethod'] ?? '';
    $productId = (int)($input['productId'] ?? 0);
    $totalAmount = $quantity * $price;

    if (!$quantity || !$price || !$paymentMethod || !$productId) {
        throw new Exception('필수 입력 값이 누락되었습니다.');
    }

    // 기본값 설정
    $status = 'pending';
    $paymentDate = null;
    $nftToken = 0;
    $useCashPoint = 0;
    $useMileagePoint = 0;
    $depositorName = null;

    // 사용자 정보 조회
    $stmt = $conn->prepare("SELECT name, cash_points, mileage_points FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        throw new Exception('사용자 정보를 찾을 수 없습니다.');
    }

    if ($paymentMethod === 'point') {
        $useCashPoint = (float)($input['useCashPoint'] ?? 0);
        $useMileagePoint = (float)($input['useMileagePoint'] ?? 0);

        if ($useCashPoint > 0 && $useMileagePoint > 0) {
            throw new Exception('한 종류의 포인트만 사용할 수 있습니다.');
        }

        if ($useCashPoint > 0) {
            if ($useCashPoint > $user['cash_points']) {
                throw new Exception('캐시포인트가 부족합니다.');
            }
            if ($useCashPoint != $totalAmount) {
                throw new Exception('결제 금액이 일치하지 않습니다.');
            }
        } elseif ($useMileagePoint > 0) {
            if ($useMileagePoint > $user['mileage_points']) {
                throw new Exception('마일리지포인트가 부족합니다.');
            }
            if ($useMileagePoint != $totalAmount) {
                throw new Exception('결제 금액이 일치하지 않습니다.');
            }
        } else {
            throw new Exception('사용할 포인트를 선택해주세요.');
        }

        $status = 'completed';
        $paymentDate = date('Y-m-d H:i:s');
        $nftToken = $quantity;
        $depositorName = $user['name'];

    } elseif ($paymentMethod === 'bank') {
        $depositorName = trim($input['depositorName'] ?? '');
        if (empty($depositorName)) {
            throw new Exception('입금자명을 입력해주세요.');
        }
    } else {
        throw new Exception('유효하지 않은 결제 방식입니다.');
    }

    // 주문 생성
    $stmt = $conn->prepare("
        INSERT INTO orders (
            user_id, product_id, price_unit, quantity, nft_token,
            total_amount, payment_method, depositor_name,
            cash_point_used, mileage_point_used, status, payment_date,
            ip_address
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception("주문 쿼리 준비 실패: " . $conn->error);
    }

    $stmt->bind_param(
        "iiiidssddssss",
        $_SESSION['user_id'],
        $productId,
        $price,
        $quantity,
        $nftToken,
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

    $orderId = $conn->insert_id;

    if ($paymentMethod === 'point') {
        if ($useCashPoint > 0) {
            // 캐시포인트 차감
            $stmt = $conn->prepare("UPDATE users SET cash_points = cash_points - ? WHERE id = ?");
            $stmt->bind_param("di", $useCashPoint, $_SESSION['user_id']);
            if (!$stmt->execute()) {
                throw new Exception("캐시포인트 차감 실패");
            }
            
            calculateAndProcessCommissions($conn, $orderId);
        } else {
            // 마일리지포인트 차감
            $stmt = $conn->prepare("UPDATE users SET mileage_points = mileage_points - ? WHERE id = ?");
            $stmt->bind_param("di", $useMileagePoint, $_SESSION['user_id']);
            if (!$stmt->execute()) {
                throw new Exception("마일리지포인트 차감 실패");
            }

            if (!function_exists('processNFTAndRank')) {
                throw new Exception("NFT 처리 함수가 정의되지 않았습니다.");
            }
            processNFTAndRank($conn, $orderId);
        }
    }

    $conn->commit();
    die(json_encode(['success' => true, 'order_id' => $orderId]));

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    error_log('Order process error: ' . $e->getMessage());
    die(json_encode(['success' => false, 'message' => $e->getMessage()]));
}