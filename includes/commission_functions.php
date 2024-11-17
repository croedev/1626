<?php
require_once 'config.php';

// 데이터베이스 연결 설정
$conn = db_connect();

// 1. 사용자 정보 및 직급 관련 함수


/**
 * 구매 발생 시 사용자 정보와 누적 변수 업데이트
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 구매를 발생시킨 사용자 ID
 * @param int $quantity 구매 수량
 * @param float $amount 구매 금액
 * @return void
 */

function updateUserPurchaseInfo($conn, $user_id, $quantity, $amount, $order_id, $order_date = null) {
    try {
        $transaction_date = $order_date ?? date('Y-m-d H:i:s');

        // 본인 누적 구매 수량과 금액 업데이트 //users에 nft_token업데이트
        $stmt = $conn->prepare("
            UPDATE users
            SET myQuantity = myQuantity + ?,
                myTotal_quantity = myTotal_quantity + ?,
                myAmount = myAmount + ?,
                myTotal_Amount = myTotal_Amount + ?,
                nft_token = nft_token + ?, 
                last_purchase_date = ?,
                total_purchases = total_purchases + 1,
                direct_volume = direct_volume + ?
            WHERE id = ?
        ");
        $stmt->bind_param("iidididi", $quantity, $quantity, $amount, $amount, $quantity, $transaction_date, $amount, $user_id);
        $stmt->execute();

        // 로그 추가
        // error_log("User ID {$user_id} updated: myQuantity={$quantity}, myTotal_quantity={$quantity}, myAmount={$amount}, myTotal_Amount={$amount}, nft_token={$quantity}, direct_volume={$amount}, last_purchase_date={$transaction_date}");

        // NFT 히스토리 기록
        $stmt = $conn->prepare("
            INSERT INTO nft_history (
                from_user_id, to_user_id, amount, 
                transaction_date, transaction_type, order_id
            ) VALUES (NULL, ?, ?, ?, '구매', ?)
        ");
        $stmt->bind_param("iisi", $user_id, $quantity, $transaction_date, $order_id);
        $stmt->execute();

    } catch (Exception $e) {
        handleException($e, "사용자 구매 정보 업데이트 오류");
    }
}



/**
 * 직급 이름을 반환하는 함수
 *
 * @param string $rank 직급 코드
 * @return string 직급 이름
 */
function getRankName($rank) {
    $rank_names = [
        '회원' => '회원',
        '총판' => '총판',
        '특판' => '특판',
        '특판A' => '특판A'
    ];
    return $rank_names[$rank] ?? '알 수 음';
}

/**
 * 사용자의 직급을 결정하고 업데이트하는 함수
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 사용자 ID
 * @return void
 */
function updateUserRank($conn, $user_id, $order_date = null) {
    try {
        // 사용자 정보 가져오기
        $stmt = $conn->prepare("
            SELECT rank, myTotal_quantity, myAgent, myAgent_referral, referred_by
            FROM users WHERE id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        $current_rank = $user['rank'];
        $new_rank = $current_rank;

        // 총판 승급 조건 확인
        if ($user['myTotal_quantity'] >= 1000 && $current_rank == '회원') {
            $new_rank = '총판';
        }

        // 특판 승급 조건 확인
        if ($user['myAgent'] >= 10 && ($current_rank == '총판' || $new_rank == '총판')) {
            $new_rank = '특판';
        }

        // 특판A 승급 조건 확인
        if ($user['myAgent_referral'] >= 5 && $new_rank == '특판') {
            $new_rank = '특판A';
        }

        if ($new_rank != $current_rank) {
            // order_date가 없으면 현재 시간 사용
            $change_date = $order_date ?? date('Y-m-d H:i:s');

            // 직급 업데이트
            $stmt = $conn->prepare("
                UPDATE users
                SET rank = ?, rank_update_date = ?, rank_change_reason = '자동 승급'
                WHERE id = ?
            ");
            $stmt->bind_param("ssi", $new_rank, $change_date, $user_id);
            $stmt->execute();

            // 직급 변경 이력 저장
            $stmt = $conn->prepare("
                INSERT INTO rank_history (user_id, previous_rank, new_rank, change_date, change_reason)
                VALUES (?, ?, ?, ?, '자동 승급')
            ");
            $stmt->bind_param("isss", $user_id, $current_rank, $new_rank, $change_date);
            $stmt->execute();

            // '총판'으로 승급한 경우 상위 추천인의 myAgent, myAgent_referral 업데이트
            if ($new_rank == '총판') {
                updateUplineAgentCounts($conn, $user_id);
            }
        }

    } catch (Exception $e) {
        handleException($e, "사용자 직급 업데이트 오류");
    }
}



/**
 * 추천인 라인을 따라가며 누적 변수 업데이트
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 구매를 발생시킨 사용자 ID
 * @param int $quantity 구매 수량
 * @param float $amount 구매 금액
 * @return void
 */
function updateReferralLine($conn, $user_id, $quantity, $amount) {
    try {
        // 구매자의 추천인을 가져옵니다.
        $stmt = $conn->prepare("
            SELECT referred_by
            FROM users
            WHERE id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // 구매자의 추천인이 없으면 함수 종료
        if (!$user || !$user['referred_by']) {
            return;
        }

        $referrer_id = $user['referred_by'];
        $current_referrer_id = $referrer_id;

        $is_first_level = true;

        // 상위 추천인들을 따라가며 누적 변수 업데이트
        while ($current_referrer_id) {
            // 사용자 정보 가져오기
            $stmt = $conn->prepare("
                SELECT id, referred_by, rank
                FROM users
                WHERE id = ?
            ");
            $stmt->bind_param("i", $current_referrer_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                break;
            }

            // 산하 누적 구매 수량과 금액 업데이트
            $stmt = $conn->prepare("
                UPDATE users
                SET myTotal_quantity = myTotal_quantity + ?,
                    myTotal_Amount = myTotal_Amount + ?,
                    ref_total_volume = ref_total_volume + ?
                WHERE id = ?
            ");
            $stmt->bind_param("idii", $quantity, $amount, $amount, $current_referrer_id);
            $stmt->execute();

            // 직접 추천인에 대해 referrals_volume 업데이트
            if ($is_first_level) {
                $stmt = $conn->prepare("
                    UPDATE users
                    SET referrals_volume = referrals_volume + ?
                    WHERE id = ?
                ");
                $stmt->bind_param("di", $amount, $current_referrer_id);
                $stmt->execute();
                $is_first_level = false;
            }

            // 사용자 직급 업데이트
            updateUserRank($conn, $current_referrer_id);

            // 다음 상위 추천인으로 이동
            $current_referrer_id = $user['referred_by'];
        }
    } catch (Exception $e) {
        handleException($e, "추천인 라인 업데이트 오류");
    }
}



// 2. 수수료 계산 관련 함수

/**
 * 직판 수수료를 계산하는 함수
 *
 * @param string $rank 사용자 직급
 * @param float $orderAmount 주문 금액
 * @return float 직판 수수료 금액
 */
function calculateDirectCommission($rank, $orderAmount) {
    $rates = [
        '총판' => 0.30,
        '특판' => 0.40,
        '특판A' => 0.40
    ];
    $rate = $rates[$rank] ?? 0;
    return $orderAmount * $rate;
}

/**
 * 총판 수수료를 계산하고 지급 대상자를 찾는 함수
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 구매를 발생시킨 사용자 ID
 * @param float $orderAmount 주문 금액
 * @return array ['user_id' => 수수료 받을 사용자 ID, 'commission' => 수수료 금액]
 */
function calculateDistributorCommission($conn, $user_id, $orderAmount) {
    try {
        $upline_user_id = null;
        $commission_amount = 0;

        // 추천인 라인을 따라가며 첫 번째 총판을 찾음
        while ($user_id) {
            $stmt = $conn->prepare("
                SELECT id, referred_by, rank
                FROM users
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                break;
            }

            if ($user['rank'] == '총판') {
                $upline_user_id = $user['id'];
                $commission_amount = $orderAmount * 0.30;
                break;
            } elseif ($user['rank'] == '특판' || $user['rank'] == '특판A') {
                // 총판 이상 직급자가 있으면 종료
                break;
            }

            $user_id = $user['referred_by'];
        }

        return [
            'user_id' => $upline_user_id,
            'commission' => $commission_amount
        ];

    } catch (Exception $e) {
        handleException($e, "총판 수수료 계산 오류");
        return ['user_id' => null, 'commission' => 0];
    }
}

/**
 * 특판 수수료를 계산하고 지급 대상자를 찾는 함수
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 구매를 발생시킨 사용자 ID
 * @param float $orderAmount 주문 금액
 * @param bool $has_distributor 총판 수수료 지급 여부
 * @return array ['user_id' => 수수료 받을 사용자 ID, 'commission' => 수수료 금액]
 */
function calculateSpecialCommission($conn, $user_id, $orderAmount, $has_distributor) {
    try {
        $upline_user_id = null;
        $commission_amount = 0;
        $original_user_rank = '';

        // 원래 사용자의 직급 확인
        $stmt = $conn->prepare("SELECT rank FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $original_user_rank = $user['rank'];

        // 추천인 라인을 따라가며 첫 번째 특판 또는 특판A를 찾음
        while ($user_id) {
            $stmt = $conn->prepare("
                SELECT id, referred_by, rank
                FROM users
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                break;
            }

            if ($user['rank'] == '특판' || $user['rank'] == '특판A') {
                $upline_user_id = $user['id'];
                if ($original_user_rank == '회원') {
                    if ($has_distributor) {
                        $commission_amount = $orderAmount * 0.10; // 총판이 있는 경우 10%
                    } else {
                        $commission_amount = $orderAmount * 0.40; // 총판이 없는 경우 40%
                    }
                } elseif ($original_user_rank == '총판') {
                    $commission_amount = $orderAmount * 0.10; // 총판 위의 특판은 항상 10%
                }
                break;
            }

            $user_id = $user['referred_by'];
        }

        return [
            'user_id' => $upline_user_id,
            'commission' => $commission_amount
        ];

    } catch (Exception $e) {
        handleException($e, "특판 수수료 계산 오류");
        return ['user_id' => null, 'commission' => 0];
    }
}

/**
 * 수수료를 처리하고 커미션 테이블에 저장하는 함수
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 수수료를 받을 사용자 ID
 * @param float $amount 수수료 금액
 * @param string $type 수수료 유형
 * @param int $source_user_id 매출을 발생시킨 사용자 ID
 * @param int $order_id 주문 ID
 * @param float $commission_rate 수수료율
 * @return void
 */
function processCommission($conn, $user_id, $amount, $type, $source_user_id, $order_id, $commission_rate, $order_date = null) {
    try {
        // 트랜잭션 시작
        $conn->begin_transaction();

        $created_at = $order_date ?? date('Y-m-d H:i:s');
        
        // 소수점 처리를 위해 반올림
        $amount = round($amount, 2);
        $cash_point = round($amount * 0.5, 2);
        $mileage_point = round($amount * 0.5, 2);

        // 1. commissions 테이블에 기록
        $stmt = $conn->prepare("
            INSERT INTO commissions (
                user_id, amount, commission_type, source_user_id, 
                cash_point_amount, mileage_point_amount, order_id, 
                commission_rate, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "idsiiddis", 
            $user_id, $amount, $type, $source_user_id, 
            $cash_point, $mileage_point, $order_id, 
            $commission_rate, $created_at
        );
        
        if (!$stmt->execute()) {
            throw new Exception("커미션 기록 실패: " . $stmt->error);
        }

        // 2. users 테이블 포인트 업데이트
        $stmt = $conn->prepare("
            UPDATE users 
            SET cash_points = cash_points + ?,
                mileage_points = mileage_points + ?,
                commission_total = commission_total + ?
            WHERE id = ?
        ");
        
        $stmt->bind_param(
            "dddi",
            $cash_point,
            $mileage_point, 
            $amount,
            $user_id
        );

        if (!$stmt->execute()) {
            throw new Exception("사용자 포인트 업데이트 실패: " . $stmt->error);
        }

        // 트랜잭션 커밋
        $conn->commit();

        // 성공 로그
        error_log(sprintf(
            "Commission processed - User: %d, Amount: %.2f, Cash: %.2f, Mileage: %.2f",
            $user_id, $amount, $cash_point, $mileage_point
        ));

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Commission processing error: " . $e->getMessage());
        throw $e;
    }
}


/**
 * 수수료를 계산하고 처리하는 메인 함수
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $order_id 주문 ID
 * @return void
 */
function calculateAndProcessCommissions($conn, $order_id) {
    try {
        // 트랜잭션 시작
        $conn->begin_transaction();

        // 주문 정보를 가져오기
        // $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND status = 'completed'");
        // $stmt->bind_param("i", $order_id);
        // $stmt->execute();
        // $result = $stmt->get_result();

        // if ($result->num_rows === 0) {
        //     throw new Exception("유효하지 않은 주문이거나 이미 처리된 주문입니다.");
        // }

        // $order = $result->fetch_assoc();
        // $order_date = $order['created_at'];

        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND status = 'completed'");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if (!$order) {
            throw new Exception("유효하지 않은 주문입니다.");
        }

        // 포인트 결제는 수수료 계산 제외
        if ($order['payment_method'] === 'point') {
            $conn->commit();
            return;
        }

        $user_id = $order['user_id'];
        $orderAmount = $order['total_amount'];
        $order_date = $order['created_at'];



        // 주문이 이미 수수료 처리가 완료되었는지 확인
        $stmt = $conn->prepare("SELECT COUNT(*) as commission_count FROM commissions WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $commission_count = $row['commission_count'];
        $stmt->close();

        if ($commission_count > 0) {
            // 이미 수수료가 처리된 주문이므로 재처리하지 않음
            $conn->rollback();
            return;
        }

        $user_id = $order['user_id'];
        $orderAmount = $order['total_amount'];
        $quantity = $order['quantity'];

        // 사용자의 현재 직급 가져오기 (업데이트 전 직급)
        $stmt = $conn->prepare("SELECT rank FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_info = $result->fetch_assoc();
        $user_rank = $user_info['rank'];

        // 직판 수수료 계산 및 지급
        $direct_commission = calculateDirectCommission($user_rank, $orderAmount);
        if ($direct_commission > 0) {
            processCommission($conn, $user_id, $direct_commission, 'direct_sales', $user_id, $order_id, ($direct_commission / $orderAmount) * 100, $order_date);
        }

        $has_distributor = false;

        // 총판 수수료 계산 및 지급
        if ($user_rank == '회원') {
            $distributor_commission_info = calculateDistributorCommission($conn, $user_id, $orderAmount);
            if ($distributor_commission_info['commission'] > 0) {
                processCommission($conn, $distributor_commission_info['user_id'], $distributor_commission_info['commission'], 'distributor', $user_id, $order_id, 30, $order_date);
                $has_distributor = true;
            }
        }

        // 특판 수수료 계산 및 지급
        if ($user_rank == '회원' || $user_rank == '총판') {
            $special_commission_info = calculateSpecialCommission($conn, $user_id, $orderAmount, $has_distributor);
            if ($special_commission_info['commission'] > 0) {
                $commission_rate = $has_distributor ? 40 : 10;
                processCommission($conn, $special_commission_info['user_id'], $special_commission_info['commission'], 'special', $user_id, $order_id, $commission_rate, $order_date);
            }
        }

        // 사용자 구매 정보 업데이트 //nft users테이블에 업데이트
        updateUserPurchaseInfo($conn, $user_id, $quantity, $orderAmount, $order_id, $order_date);

        // 추천인 라인을 따라가며 누적 변수 업데이트
        updateReferralLine($conn, $user_id, $quantity, $orderAmount);

        // pricing_tiers 테이블 업데이트
        $stmt = $conn->prepare("
            UPDATE pricing_tiers 
            SET sold_quantity = sold_quantity + ?, 
                remaining_quantity = total_quantity - (sold_quantity + ?) 
            WHERE id = (SELECT MAX(id) FROM pricing_tiers)
        ");
        $stmt->bind_param("ii", $quantity, $quantity);
        $stmt->execute();
        $stmt->close();

        // 주문의 paid_status를 'completed'로 업데이트
        $stmt = $conn->prepare("UPDATE orders SET paid_status = 'completed' WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $stmt->close();

        // 사용자 직급 업데이트
        updateUserRank($conn, $user_id, $order_date);

        // 트랜잭션 커밋
        $conn->commit();

    } catch (Exception $e) {
        // 오류 발생 시 롤백
        $conn->rollback();
        handleException($e, "수수료 계산 및 처리 오류");
        throw $e;
    }
}



// NFT 토큰 및 직급 처리를 위한 새로운 함수(포인트결제시 수수료 제외)
function processNFTAndRank($conn, $order_id) {
    try {
        $conn->begin_transaction();

        // 주문 정보 조회
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND status = 'completed'");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        if (!$order) {
            throw new Exception("유효하지 않은 주문입니다.");
        }

        $user_id = $order['user_id'];
        $quantity = $order['quantity'];
        $orderAmount = $order['total_amount'];
        $order_date = $order['created_at'];

        // NFT 토큰 업데이트
        updateUserPurchaseInfo($conn, $user_id, $quantity, $orderAmount, $order_id, $order_date);
        
        // 추천인 라인 업데이트 (NFT 관련 통계)
        updateReferralLine($conn, $user_id, $quantity, $orderAmount);
        
        // 직급 업데이트
        updateUserRank($conn, $user_id, $order_date);

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        handleException($e, "NFT 및 직급 처리 오류");
        throw $e;
    }
}










// 3. 수수료 내역 및 통계 함수

/**
 * 특정 기간 동안의 수수료 내역을 가져오는 함수
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $user_id 사용자 ID
 * @param string $start_date 시작 날짜
 * @param string $end_date 종료 날���
 * @return array 수수료 내역 배열
 */
function getCommissions($conn, $user_id, $start_date, $end_date, $date_type = 'order_date') {
    try {
        $date_column = $date_type === 'order_date' ? 'o.payment_date' : 'c.created_at';

        $stmt = $conn->prepare("
            SELECT c.*, 
                   u.name as source_name, 
                   u.id as source_id, 
                   o.quantity as order_count, 
                   o.total_amount as order_amount, 
                   o.payment_date as order_completed_at, 
                   u.rank as source_rank
            FROM commissions c
            JOIN users u ON c.source_user_id = u.id
            JOIN orders o ON c.order_id = o.id
            WHERE c.user_id = ? AND DATE($date_column) BETWEEN ? AND ?
            ORDER BY $date_column DESC
        ");

        if ($stmt === false) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    } catch (Exception $e) {
        handleException($e, "수수료 내역 조회 오류");
        return [];
    }
}


/**
 * 수수료 통계를 계산하는 함수
 *
 * @param array $commissions 수수료 내역 배열
 * @return array 수수료 통계 배열
 */
function calculateCommissionStats($commissions) {
    $stats = [
        'total' => 0,
        'direct' => 0,
        'distributor' => 0,
        'special' => 0
    ];

    foreach ($commissions as $commission) {
        $stats['total'] += $commission['amount'];
        switch ($commission['commission_type']) {
            case 'direct_sales':
                $stats['direct'] += $commission['amount'];
                break;
            case 'distributor':
                $stats['distributor'] += $commission['amount'];
                break;
            case 'special':
                $stats['special'] += $commission['amount'];
                break;
        }
    }

    return $stats;
}

/**
 * 수수료 타입의 이름을 반환하는 함수
 *
 * @param string $type 수수료 타입 코드
 * @return string 수수료 타입 이름
 */
function getCommissionTypeName($type) {
    $type_names = [
        'direct_sales' => '직판 수수료',
        'distributor' => '총판 수수료',
        'special' => '특판 수수료'
    ];
    return $type_names[$type] ?? '알 수 없음';
}

// 4. 공통 예외 처리 함수

/**
 * 예외를 처리하고 로그에 기록하는 함수
 *
 * @param Exception $e 예외 객체
 * @param string $errorMessage 에러 메시지
 * @return void
 */
function handleException($e, $errorMessage) {
    error_log($errorMessage . ": " . $e->getMessage());
    // 필요에 따라 사용자에게 에러 메시지를 표시하거나 처리할 수 있음
}

/**
 * 마지막 수수료 계산 일시를 가져오는 함수
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @return string|null 마지막 계산 일시 또는 null
 */
function getLastCalculationTime($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT MAX(calculation_time) as last_calculation_time
            FROM commission_calculations
        ");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result['last_calculation_time'] ?? null;
    } catch (Exception $e) {
        handleException($e, "마지막 계산 일시 가져오기 오류");
        return null;
    }
}

/**
 * 수수료 계산 이력을 가져오는 함수
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @return array 산 이력 배열
 */
function getCalculationHistory($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT *
            FROM commission_calculations
            ORDER BY calculation_time DESC
            LIMIT 50
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        handleException($e, "수료 계산 이력 가져오기 오류");
        return [];
    }
}

/**
 * 수수료 계산 실행 함수
 *
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param string $start_date 시작 날짜
 * @param string $end_date 종료 날짜
 * @return array 결과 배열
 */
function runCommissionCalculation($conn, $start_date, $end_date) {
    try {
        $processed_orders = 0;
        $failed_orders = 0;

        // 해당 기간의 주문 가져오기
        $stmt = $conn->prepare("
            SELECT id
            FROM orders
            WHERE status = 'completed'
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($orders as $order) {
            try {
                calculateAndProcessCommissions($conn, $order['id']);
                $processed_orders++;
            } catch (Exception $e) {
                $failed_orders++;
                handleException($e, "주문 ID {$order['id']} 수수료 계산 오류");
            }
        }

        // 수수료 계산 이력 저장
        $stmt = $conn->prepare("
            INSERT INTO commission_calculations (calculation_time, start_date, end_date, processed_orders, failed_orders)
            VALUES (NOW(), ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssii", $start_date, $end_date, $processed_orders, $failed_orders);
        $stmt->execute();

        return [
            'success' => true,
            'processed_orders' => $processed_orders,
            'failed_orders' => $failed_orders
        ];
    } catch (Exception $e) {
        handleException($e, "수수료 계산 실행 오류");
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}



// 수수료 계산 이력 저장 함수 정의
function saveCalculationHistory($conn, $start_date, $end_date, $processed_orders, $failed_orders) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO commission_calculations (calculation_time, start_date, end_date, processed_orders, failed_orders)
            VALUES (NOW(), ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssii", $start_date, $end_date, $processed_orders, $failed_orders);
        $stmt->execute();
    } catch (Exception $e) {
        handleException($e, "수수료 계산 이력 저장 오류");
    }
}

function updateUplineAgentCounts($conn, $user_id) {
    try {
        // 구매자의 추천인을 가져옵니다.
        $stmt = $conn->prepare("
            SELECT referred_by
            FROM users
            WHERE id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // 추천인이 없으면 함수 종료
        if (!$user || !$user['referred_by']) {
            return;
        }

        $referrer_id = $user['referred_by'];
        $user_id = $referrer_id;

        $is_first_level = true;

        // 상위 추천인들을 따라가며 myAgent, myAgent_referral 업데이트
        while ($user_id) {
            // 사용자 정보 가져오기
            $stmt = $conn->prepare("
                SELECT id, referred_by
                FROM users
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                break;
            }

            // myAgent 증가
            $stmt = $conn->prepare("
                UPDATE users
                SET myAgent = myAgent + 1
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            // 직접 추천인에 대해 myAgent_referral 증가
            if ($is_first_level) {
                $stmt = $conn->prepare("
                    UPDATE users
                    SET myAgent_referral = myAgent_referral + 1
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $is_first_level = false;
            }

            // 다음 상위 추천인으로 이동
            $user_id = $user['referred_by'];
        }
    } catch (Exception $e) {
        handleException($e, "상위 추천인의 총판 수 업데이트 오류");
    }
}



/**
 * 주문 ID로 주문 정보를 가져오는 함수
 * @param mysqli $conn 데이터베이스 연결 객체
 * @param int $order_id 주문 ID
 * @return array|null 주문 정보 또는 null
 */
function getOrderById($conn, $order_id) {
    try {
        $stmt = $conn->prepare("
            SELECT o.*, u.id as user_id, u.name as user_name, p.name as product_name
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN products p ON o.product_id = p.id
            WHERE o.id = ?
        ");
        
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $order_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        
        $stmt->close();
        return $order;
        
    } catch (Exception $e) {
        error_log("Error in getOrderById: " . $e->getMessage());
        handleException($e, "주문 정보 조회 오류");
        return null;
    }
}

?>
