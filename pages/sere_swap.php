<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php'; 
require __DIR__ . '/../vendor/autoload.php';

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header('Location: /login?redirect=/sere_swap');
    exit;
}

// DB 연결
$conn = db_connect();

// 사용자 정보 조회
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "사용자 정보 조회 실패";
    exit;
}

// 사용자별 누적 스왑수량 (표시용)
$swapSumSql = "SELECT IFNULL(SUM(request_amount), 0) as total_swap 
               FROM sere_swap
               WHERE user_id = ?";
$stmt = $conn->prepare($swapSumSql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sumRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$myTotalSwap = $sumRow ? intval($sumRow['total_swap']) : 0;



// sere_swap.php 파일 상단에 추가할 제한 체크 함수
function getUserSwapRestriction($conn, $user_id, $request_amount) {
    // 1. 먼저 전체 회원 대상 누적 스왑 제한 체크 (1000개)
    $query = "SELECT COALESCE(SUM(request_amount), 0) as total_swap 
              FROM sere_swap 
              WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $totalSwap = $stmt->get_result()->fetch_assoc()['total_swap'];
    
    // 전체 회원 기본 제한 (1000개) 체크
    if (($totalSwap + $request_amount) > 1000) {
        return [
            'restricted' => true,
            'message' => sprintf(
                "스왑 한도 초과입니다.\n현재까지 스왑한 수량: %d개\n추가 스왑 가능 수량: %d개",
                $totalSwap,
                max(0, 1000 - $totalSwap)
            )
        ];
    }

    // 2. 개별 사용자 그룹 제한 체크

    $case1_users = [85,1118,764,27726,2254,
                    351,583,643,1106,620,585,654,623,891,625,641,643,
                    329,25067,26201,24258,1757,2277
                    ];               // 완전 전송 불가
    $case2_users = [2719];              // 특정기간 내 100개 제한
    $case3_users = [12567123, 3509323]; // 특정기간 내 1000개 제한


    // 제한 기간 설정
    $start_date = '2024-12-22 00:00:00';
    $end_date = '2025-12-31 23:59:59';

    // case1: 완전 스왑 제한
    if (in_array($user_id, $case1_users)) {
        return [
            'restricted' => true,
            'message' => '이 계정은 현재 스왑이 제한되어 있습니다.'
        ];
    }

    // case2, case3 체크
    $is_case2 = in_array($user_id, $case2_users);
    $is_case3 = in_array($user_id, $case3_users);

    if (!$is_case2 && !$is_case3) {
        return ['restricted' => false, 'message' => ''];
    }

    // 기간 내 스왑한 총량 확인
    $sql = "SELECT COALESCE(SUM(request_amount), 0) as total_swapped 
            FROM sere_swap
            WHERE user_id = ? 
              AND request_date BETWEEN ? AND ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $already_swapped = $result->fetch_assoc()['total_swapped'];

    // 케이스별 한도 체크
    $limit_amount = $is_case2 ? 100 : 1000;
    $total_after = $already_swapped + $request_amount;

    if ($total_after > $limit_amount) {
        return [
            'restricted' => true,
            'message' => sprintf(
                "※개별 스왑 제한 안내 ※
                %s ~ %s 기간 중 최대 %d개까지 스왑 가능합니다.
                (현재까지 스왑: %d개 / 추가 %d개 스왑 불가)",
                 
                date('Y-m-d', strtotime($start_date)),
                date('Y-m-d', strtotime($end_date)),
                $limit_amount,
                $already_swapped,
                $request_amount
            )
        ];
    }

    return ['restricted' => false, 'message' => ''];
}



// ==================================
// BSCClient 클래스
// ==================================
use kornrunner\Ethereum\Transaction as KornrunnerTx;

class BSCClient {
    private $rpcUrl;
    private $contractAddress;
    private $contractABI;

    public function __construct($rpcUrl, $contractAddress, $contractABI) {
        $this->rpcUrl = $rpcUrl;
        $this->contractAddress = strtolower($contractAddress);
        $this->contractABI = $contractABI;
    }

    private function rpcCall($method, $params = []) {
        $data = [
            'jsonrpc' => '2.0',
            'id' => time(),
            'method' => $method,
            'params' => $params
        ];

        $ch = curl_init($this->rpcUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception('RPC 요청 실패: ' . curl_error($ch));
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['error'])) {
            throw new \Exception('RPC 오류: ' . $result['error']['message']);
        }

        return $result;
    }

    public function getTokenBalance($address) {
        $data = $this->encodeTokenBalanceOf($address);
        $params = [
            [
                'to' => $this->contractAddress,
                'data' => $data
            ],
            'latest'
        ];
        $response = $this->rpcCall('eth_call', $params);
        $hexValue = $response['result'];
        if (strpos($hexValue, '0x') === 0) {
            $hexValue = substr($hexValue, 2);
        }
        if ($hexValue === '') {
            $hexValue = '0';
        }
        $decValue = gmp_strval(gmp_init($hexValue, 16), 10);
        return $decValue;
    }

    public function getBNBBalance($address) {
        $response = $this->rpcCall('eth_getBalance', [$address, 'latest']);
        $hexValue = $response['result'];
        if (strpos($hexValue, '0x') === 0) {
            $hexValue = substr($hexValue, 2);
        }
        if ($hexValue === '') {
            $hexValue = '0';
        }
        $decValue = gmp_strval(gmp_init($hexValue, 16), 10);
        return $decValue;
    }

    private function encodeTokenBalanceOf($address) {
        $methodID = '0x70a08231'; // balanceOf(address)
        $paddedAddress = str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT);
        return $methodID . $paddedAddress;
    }

    private function encodeTokenTransfer($to, $amount) {
        $methodID = '0xa9059cbb'; // transfer(address,uint256)
        $paddedAddress = str_pad(substr($to, 2), 64, '0', STR_PAD_LEFT);
        $weiAmount = $this->decimalToWei($amount);
        $hexAmount = gmp_strval(gmp_init($weiAmount, 10), 16);
        $paddedAmount = str_pad($hexAmount, 64, '0', STR_PAD_LEFT);
        return $methodID . $paddedAddress . $paddedAmount;
    }

    public function getGasPrice() {
        $response = $this->rpcCall('eth_gasPrice');
        return hexdec($response['result']);
    }

    public function getNonce($address) {
        $response = $this->rpcCall('eth_getTransactionCount', [$address, 'latest']);
        return hexdec($response['result']);
    }

    private function decimalToHex($decimal) {
        return '0x' . dechex($decimal);
    }

    private function decimalToWei($amount, $decimals = 18) {
        $amount = strval($amount);
        return bcmul($amount, bcpow('10', $decimals, 0), 0);
    }

    public function estimateGas($from, $to, $amount, $isToken = false) {
        if ($isToken) {
            $data = $this->encodeTokenTransfer($to, $amount);
            $params = [[
                'from' => $from,
                'to' => $this->contractAddress,
                'data' => $data
            ], 'latest'];
        } else {
            $value = $this->decimalToHex($this->decimalToWei($amount));
            $params = [[
                'from' => $from,
                'to' => $to,
                'value' => $value
            ], 'latest'];
        }

        $response = $this->rpcCall('eth_estimateGas', $params);
        return hexdec($response['result']);
    }

    public function sendTransaction($from, $to, $amount, $privateKey, $isToken = false) {
        // 개인키 형식 검증
        $privateKey = preg_replace('/^0x/i', '', trim($privateKey));
        if (!preg_match('/^[a-f0-9]{64}$/i', $privateKey)) {
            throw new \Exception('Private key must be a valid 64-character hex string');
        }
        
        $from = strtolower($from);
        $to = strtolower($to);

        $nonce = $this->getNonce($from);
        $gasPrice = $this->getGasPrice();
        $gasLimit = $isToken ? 200000 : 21000;
        $chainId = 56; // BSC Mainnet

        $value = $isToken ? '0x0' : '0x' . dechex($this->decimalToWei($amount));
        $data = $isToken ? $this->encodeTokenTransfer($to, $amount) : '0x';

        $nonceHex = '0x' . dechex($nonce);
        $gasPriceHex = '0x' . dechex($gasPrice);
        $gasLimitHex = '0x' . dechex($gasLimit);

        $tx = new KornrunnerTx($nonceHex, $gasPriceHex, $gasLimitHex, $isToken ? $this->contractAddress : $to, $value, $data);
        $signedTx = $tx->getRaw($privateKey, $chainId);

        $response = $this->rpcCall('eth_sendRawTransaction', ['0x' . $signedTx]);
        if (!isset($response['result'])) {
            throw new \Exception('Transaction failed: No transaction hash returned');
        }
        return $response['result'];
    }
}


// =======================
// AJAX 요청 처리
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    try {
        if (!isset($_POST['action'])) {
            throw new \Exception('액션이 지정되지 않았습니다.');
        }

        $bscClient = new BSCClient(BSC_RPC_URL, SERE_CONTRACT, $SERE_ABI);
        $response = ['success' => false];

        switch ($_POST['action']) {
            // -------------------------
            // 스왑 요청 (request_swap)
            // -------------------------
            case 'request_swap':
                if (!isset($_POST['amount']) || !is_numeric($_POST['amount'])) {
                    throw new \Exception('유효하지 않은 수량입니다.');
                }
                $amount = intval($_POST['amount']);


                  // 제한 체크 (신규 추가)
                    $restriction = getUserSwapRestriction($conn, $user_id, $amount);
                    if ($restriction['restricted']) {
                        throw new \Exception($restriction['message']);
                    }
                    

                // 개인별 누적 스왑 한도 체크
                $query = "SELECT IFNULL(SUM(request_amount), 0) as total_swap 
                          FROM sere_swap 
                          WHERE user_id = ?";
                $stm = $conn->prepare($query);
                $stm->bind_param("i", $user_id);
                $stm->execute();
                $swapSumResult = $stm->get_result()->fetch_assoc();
                $stm->close();

                $myTotalSwapTemp = $swapSumResult['total_swap'] ?? 0;
                if (($myTotalSwapTemp + $amount) > 1000) {
                    throw new \Exception('개인별 스왑한도는 누적1,000개까지입니다.');
                }

                // 보유 NFT Token 체크
                if ($amount > $user['nft_token']) {
                    throw new \Exception('보유한 NFT Token 수량이 부족합니다.');
                }
                if ($amount <= 0) {
                    throw new \Exception('0보다 큰 수량을 입력하세요.');
                }

                // 스왑 수수료 계산
                $feeAmount = ($amount * SWAP_FEE_PERCENTAGE) / 100;
                $sereAmount = $amount - $feeAmount;

                // 회사 지갑 SERE 잔액 체크
                $companyBalance = $bscClient->getTokenBalance(COMPANY_SERE_ADDRESS);
                $requiredAmount = bcmul((string)$sereAmount, "1000000000000000000");
                if (bccomp($companyBalance, $requiredAmount) < 0) {
                    throw new \Exception('회사 지갑의 SERE 토큰 잔액이 부족합니다.');
                }

                // 회사 지갑 BNB(가스비) 잔액 체크
                $estimatedGas = $bscClient->estimateGas(COMPANY_SERE_ADDRESS, $user['erc_address'], $sereAmount, true);
                $gasPrice = $bscClient->getGasPrice();
                $requiredBnb = bcmul((string)$estimatedGas, (string)$gasPrice);
                $companyBnbBalance = $bscClient->getBNBBalance(COMPANY_SERE_ADDRESS);
                if (bccomp((string)$companyBnbBalance, (string)$requiredBnb) < 0) {
                    throw new \Exception('회사 지갑의 BNB(가스비) 잔액이 부족합니다.');
                }

                // 실제 전송
                $txHash = $bscClient->sendTransaction(
                    COMPANY_SERE_ADDRESS,
                    $user['erc_address'],
                    $sereAmount,
                    getCompanyPrivateKey(), // COMPANY_PRIVATE_KEY 대신 함수 사용
                    true
                );
                if (!$txHash) {
                    throw new \Exception('토큰 전송 실패');
                }

                // DB 처리
                $conn->begin_transaction();
                try {
                    // users 테이블: NFT 차감
                    $updateSql = "UPDATE users
                                  SET nft_token = nft_token - ?
                                  WHERE id = ? AND nft_token >= ?";
                    $stmt = $conn->prepare($updateSql);
                    $stmt->bind_param("iii", $amount, $user_id, $amount);
                    if (!$stmt->execute()) {
                        throw new \Exception('NFT 토큰 차감 실패');
                    }
                    $stmt->close();

                    // nft_history 기록
                    $historySql = "INSERT INTO nft_history
                                   (from_user_id, amount, transaction_date, transaction_type, to_address)
                                   VALUES (?, ?, NOW(), ?, ?)";
                    $transactionType = "토큰스왑";
                    $stmt = $conn->prepare($historySql);
                    $stmt->bind_param("iiss", $user_id, $amount, $transactionType, $user['erc_address']);
                    if (!$stmt->execute()) {
                        throw new \Exception('거래 내역 기록 실패');
                    }
                    $stmt->close();

                    // sere_swap 기록
                    $feePercentage = SWAP_FEE_PERCENTAGE;
                    $swapSql = "INSERT INTO sere_swap
                                (user_id, request_amount, fee_percentage, sere_amount, sere_address, tx_hash, status, process_date)
                                VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())";
                    $stmt = $conn->prepare($swapSql);
                    $stmt->bind_param("iiddss", $user_id, $amount, $feeAmount, $sereAmount, $user['erc_address'], $txHash);
                    if (!$stmt->execute()) {
                        throw new \Exception('스왑 내역 기록 실패');
                    }
                    $stmt->close();

                    $conn->commit();
                    $response['success'] = true;
                    $response['txHash'] = $txHash;
                    $response['message'] = '스왑이 성공적으로 처리되었습니다.';
                } catch (\Exception $e) {
                    $conn->rollback();
                    error_log("SERE Token Transfer Error: " . $e->getMessage());
                    throw new \Exception('토큰 전송 중 오류: ' . $e->getMessage());
                }
                break; // end request_swap

            // -------------------------
            // 스왑 이력 (get_swap_history)
            // -------------------------
            case 'get_swap_history':
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $limit = 10;
                $offset = ($page - 1) * $limit;

                $sql = "SELECT s.*, u.name as user_name 
                        FROM sere_swap s
                        JOIN users u ON s.user_id = u.id
                        WHERE s.user_id = ?
                        ORDER BY s.request_date DESC
                        LIMIT ? OFFSET ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $user_id, $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result();

                $history = [];
                while ($row = $result->fetch_assoc()) {
                    $history[] = $row;
                }
                $stmt->close();

                $response['success'] = true;
                $response['history'] = $history;
                break;

            default:
                throw new \Exception('유효하지 않은 요청입니다.');
        }

        echo json_encode($response);
        exit;

    } catch (\Exception $e) {
        error_log("Swap API Error: " . $e->getMessage());
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// =========================
// HTML 렌더 부분
// =========================
$pageTitle = 'NFT 세례토큰 스왑';
include __DIR__ . '/../includes/header.php';
?>


<!-- HTML 구조 -->
<style>
        :root {
            --primary-gold: #d4af37;
            --primary-gold-hover: #f2d06b;
            --background-dark: #1a1a1a;
            --card-bg: #2a2a2a;
            --text-light: #ffffff;
            --text-gray: #b0b0b0;
            --danger-red: #ff4444;
        }

        body {
            background-color: var(--background-dark);
            color: var(--text-light);
            font-family: 'Noto Sans KR', sans-serif;
            margin: 0;
            padding: 0;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 20px 0 20px;
        }

        .main-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .swap-card {
            background: var(--card-bg);
            border: 1px solid var(--primary-gold);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .swap-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        
        }

        .info-item {
            background: rgba(0, 0, 0, 0.2);
            padding: 8px 15px;
            border-radius: 8px;
        }

        .info-label {
            color: var(--text-gray);
            font-size: 0.9em;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .info-value {
            font-size: 0.9em;
            color: var(--primary-gold);
        }

        .swap-input {
            width: 100%;
            padding: 5px 12px;
            background: #333;
            border: 1px solid var(--primary-gold);
            border-radius: 8px;
            color: #fff;
            margin-bottom: 10px;
        }

        .swap-button {
            background: linear-gradient(to right, var(--primary-gold), var(--primary-gold-hover));
            color: #000;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
        }

        .swap-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        }

        #loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            color: #fff;
        }

        .error-message {
            color: var(--danger-red);
            background: rgba(255, 68, 68, 0.1);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: none;
            font-size: 14px;
            color: red;
            font-family: 'Noto Serif KR', serif;
        }

        @media (max-width: 768px) {
            .swap-info {
                grid-template-columns: 1fr;
            }
        }

        .swap-history-card {
            background: linear-gradient(145deg, rgba(42, 42, 42, 0.9), rgba(30, 30, 30, 0.9));
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .swap-history-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.15);
            border-color: rgba(212, 175, 55, 0.4);
        }

        .swap-history-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-gold), transparent);
            opacity: 0.5;
        }

        .swap-label {
            color: var(--text-gray);
            font-size: 0.8em;
            font-weight: 400;
            font-family: 'Noto Serif KR', serif;
        }

        @media (max-width: 768px) {
            .swap-history-card {
                padding: 15px;
            }
        }
</style>


<body>
    <div class="nav-container">
        <div style="display: flex; align-items: center; gap: 10px;">
            <img src="https://jesus1626.com/sere_logo.png" alt="SERE" height="40">
            <span style="font-size: 1em; font-weight: bold;">NFT Token Swap</span>
        </div>
        <span class="rem-08 text-orange"><?php echo htmlspecialchars($user['name']).'('.$user['id'].')'; ?></span>
    </div>

    <div class="main-container">
        <div class="swap-card">
            <h5 class="notoserif rem-10 text-orange mb10" style="text-align: center;">NFT Token → SERE Token 스왑</h5>
            
            <!-- 보유 NFT Token 수량, 누적 스왑수량 표시 -->
            <div class="swap-info mb20" >

                <div class="info-item flex-x-end">
                    <div class="notoserif text-blue5">현재까지 스왑된 수량 :
                      <span class="text-orange"><i class="fas fa-exchange-alt"></i> <?php echo number_format($myTotalSwap); ?> 개</span>
                    </div>
                </div>

                <div class="info-item border-gray05">
                    <div class="info-label">보유 NFT Token수량</div>
                    <div class="info-value">
                        <?php echo number_format($user['nft_token']); ?> 개
                    </div>
                </div>
              
            </div>

            <div id="error-message" class="error-message"></div>

            <div class="info-item bg-black border-gray05">
                <div class="info-label mb10">스왑할 토큰수량(20개이상~1000개이하)</div>
                <input type="number" id="swap-amount" class="swap-input bg-white text-black fw-900 notoserif"
                    min="20" max="1000" placeholder="수량 입력(20~1000개)" style="font-size: 0.9em;"
                    oninput="validateAmount(this)">
            </div>
            
            <!-- 수수료, 실제 스왑 수량(예상) -->
            <div class="info-label mt20">
                <i class="fas fa-coins"></i> 블록체인 수수료: 
                <span id="feeAmount" class="fs-16 ml10 text-orange">0</span>개 (<?php echo SWAP_FEE_PERCENTAGE; ?>%)
            </div>
            <div class="info-item bg-red100 border-yellow05">
                <div class="info-label">
                    <img src="https://jesus1626.com/sere_logo.png" alt="SERE" style="width: 40px; height: 40px; vertical-align: middle; margin-right: 5px;">
                    실제스왑 수량(SERE): <span id="estimated-sere" class="fs-25 ml10 text-orange">0</span> SERE
                </div>
            </div>

            <!-- 지갑 주소 -->
            <div class="info-item mt20 mb10">
                <div class="info-label">나의 SERE 지갑 주소</div>
                <div class="info-value" style="font-size: 10px; display: flex; align-items: center; gap: 5px;">
                    <?php echo $user['erc_address']; ?>
                    <i class="fas fa-copy" 
                       onclick="copyToClipboard('<?php echo $user['erc_address']; ?>')" 
                       style="cursor: pointer; color: var(--primary-gold);" 
                       title="클립보드에 복사"></i>
                </div>
            </div>

            <button id="swap-button" class="swap-button">스왑하기</button>
        </div>
        <hr>

        <div class="float-right">
            <button class="btn-outline text-orange notoserif btn-14" onclick="location.href='/sere_wallet'">
                SERE월렛(지갑) 보기
            </button>
        </div>

        <div class="mt50 mb100">
            <h5 class="text-orange"><i class="fas fa-history"></i> 스왑 내역</h5>
            <div id="history-body"></div>
        </div>
    </div>

    <div id="loading-overlay">
        <div>처리중입니다...</div>
    </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>



<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('클립보드에 복사되었습니다.');
        }).catch(err => {
            console.error('클립보드 복사 실패:', err);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // 페이지 로드 시 스왑 이력 불러오기
        loadSwapHistory();

        // 수량 입력 시 수수료 & 예상 스왑 수량 계산
        document.getElementById('swap-amount').addEventListener('input', function() {
            calculateSere();
        });

        // 스왑 버튼 클릭
        document.getElementById('swap-button').addEventListener('click', async function() {
            try {
                hideError();
                const amount = parseInt(document.getElementById('swap-amount').value);

                // 간단한 클라이언트단 검증
                if (!amount || amount < 20 || amount > 1000) {
                    showError('20개에서 1000개 사이의 수량을 입력해주세요.');
                    return;
                }

                if (!confirm(`${amount}개의 NFT Token을 SERE Token으로 스왑하시겠습니까?`)) {
                    return;
                }

                showLoading();
                const response = await makeRequest('request_swap', { amount });

                if (response.success) {
                    alert(response.message || '스왑이 완료되었습니다.');
                    window.location.reload();
                } else {
                    showError(response.message || '스왑 처리 중 오류가 발생했습니다.');
                }
            } catch (e) {
                showError(e.message || '처리 중 오류가 발생했습니다.');
            } finally {
                hideLoading();
            }
        });
    });

    async function loadSwapHistory() {
        try {
            showLoading();
            const response = await makeRequest('get_swap_history');
            if (response.success) {
                const historyBody = document.getElementById('history-body');
                const history = response.history;

                if (!history || history.length === 0) {
                    historyBody.innerHTML = `<div style="color:#aaa; margin-top:10px;">신청내역이 없습니다.</div>`;
                    return;
                }

                historyBody.innerHTML = history.map(item => createHistoryRow(item)).join('');
            }
        } catch (e) {
            console.error('History loading error:', e);
        } finally {
            hideLoading();
        }
    }

    function createHistoryRow(item) {
        const statusClass = {
            'pending': 'status-pending',
            'completed': 'status-completed',
            'failed': 'status-failed'
        }[item.status];

        const statusText = {
            'pending': '처리중',
            'completed': '완료',
            'failed': '실패'
        }[item.status];

        const txLink = item.tx_hash
            ? `<a href="https://bscscan.com/tx/${item.tx_hash}" target="_blank" class="tx-link">
                  ${item.tx_hash.substring(0, 28)}...
               </a>`
            : '-';

        return `
            <div class="swap-history-card mt10">
                <div class="swap-label">신청일시 : <span class="rem-08 text-orange">${formatDate(item.request_date)}</span></div>
                <div class="swap-label">신청수량 : <span class="rem-08 text-orange">${Number(item.request_amount).toLocaleString()} NFT</span></div>
                <div class="swap-label">수수료 : <span class="rem-08 text-orange">${Number(item.request_amount * item.fee_percentage / 100).toLocaleString()} (${item.fee_percentage}%)</span></div>
                <div class="swap-label">실제수량 : <span class="rem-08 text-orange">${Number(item.sere_amount).toLocaleString()} SERE</span></div>
                <div class="swap-label">상태 : <span class="badge outline ${statusClass}">${statusText}</span></div>
                <div class="swap-label">트랜잭션 : <span class="rem-08 text-orange">${txLink}</span></div>
            </div>
        `;
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleString('ko-KR', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // 서버에 POST
    async function makeRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        for (let k in data) {
            formData.append(k, data[k]);
        }

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        let jsonData = null;
        try {
            jsonData = await response.json();
        } catch (err) {
            throw new Error('JSON parse error');
        }

        if (!response.ok) {
            throw new Error(jsonData.message || '서버 에러가 발생했습니다.');
        }
        return jsonData;
    }

    // 수수료 계산 + 예상 스왑 수량
    function calculateSere() {
        const amount = parseInt(document.getElementById('swap-amount').value) || 0;
        const feePercentage = <?php echo SWAP_FEE_PERCENTAGE; ?>;
        const feeAmount = (amount * feePercentage) / 100;
        const estimatedSere = amount - feeAmount;

        document.getElementById('feeAmount').textContent = feeAmount.toLocaleString();
        document.getElementById('estimated-sere').textContent = estimatedSere.toLocaleString();
    }

    function showError(msg) {
        const errEl = document.getElementById('error-message');
        errEl.textContent = msg;
        errEl.style.display = 'block';
    }
    function hideError() {
        document.getElementById('error-message').style.display = 'none';
    }
    function showLoading() {
        document.getElementById('loading-overlay').style.display = 'flex';
    }
    function hideLoading() {
        document.getElementById('loading-overlay').style.display = 'none';
    }

    // 입력값 미니 검증
    function validateAmount(input) {
        const val = parseInt(input.value || '0');
        const errorDiv = document.getElementById('error-message');

        if (val < 20) {
            errorDiv.textContent = '최소 20개 이상 입력해주세요.';
            errorDiv.style.display = 'block';
            document.getElementById('swap-button').disabled = true;
        } else if (val > 1000) {
            errorDiv.textContent = '최대 1000개까지만 입력 가능합니다.';
            errorDiv.style.display = 'block';
            document.getElementById('swap-button').disabled = true;
        } else {
            errorDiv.style.display = 'none';
            document.getElementById('swap-button').disabled = false;
        }
        calculateSere();
    }
    </script>
</body>
</html>