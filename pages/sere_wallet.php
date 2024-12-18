<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';
require __DIR__ . '/../vendor/autoload.php';

// 필요한 클래스 사용 선언 추가
use Web3p\EthereumTx\Transaction as EthereumTx;

// 로그인 체크
if (!isset($_SESSION['user_id'])) {
    header('Location: /login?redirect=/sere_wallet');
    exit;
}

// DB 연결
$conn = db_connect();

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo "사용자 정보 조회 실패";
    exit;
}

// AJAX 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        if (!isset($_POST['action'])) {
            throw new Exception('액션이 지정되지 않았습니다.');
        }

        ob_start(); // 출력 버퍼링 시작

        $bscClient = new BSCClient(BSC_RPC_URL, SERE_CONTRACT, $SERE_ABI);
        $response = ['success' => false];

        switch ($_POST['action']) {
            case 'send_transaction':
                if (!isset($_POST['to'], $_POST['amount'], $_POST['type'])) {
                    throw new Exception('필수 파라미터가 누락되었습니다.');
                }

                // 입력값 검증
                if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $_POST['to'])) {
                    throw new Exception('유효하지 않은 주소 형식입니다.');
                }

                if (!is_numeric($_POST['amount']) || floatval($_POST['amount']) <= 0) {
                    throw new Exception('유효하지 않은 금액입니다.');
                }

                $isToken = ($_POST['type'] === 'SERE');
                $txHash = $bscClient->sendTransaction(
                    $user['erc_address'],
                    $_POST['to'],
                    $_POST['amount'],
                    $user['private_key'],
                    $isToken
                );
                $response['success'] = true;
                $response['txHash'] = $txHash;
                break;

            case 'get_balances':
                $response['success'] = true;
                $response['sere'] = $bscClient->getTokenBalance($user['erc_address']);
                $response['bnb'] = $bscClient->getBNBBalance($user['erc_address']);
                break;

            case 'get_transactions':
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $transactions = $bscClient->getTransactionHistory($user['erc_address'], $page, 10);
                $response['success'] = true;
                $response['transactions'] = $transactions;
                break;

            case 'verify_password':
                if (!isset($_POST['password'])) {
                    throw new Exception('비밀번호가 제공되지 않았습니다.');
                }

                if (!password_verify($_POST['password'], $user['password'])) {
                    throw new Exception('비밀번호가 일치하지 않습니다.');
                }

                // 개인키 복호화 시도
                $bscClientTemp = new BSCClient(BSC_RPC_URL, SERE_CONTRACT, $SERE_ABI);
                try {
                    $privateKey = $bscClientTemp->decryptPrivateKey($user['private_key']);
                } catch (Exception $e) {
                    // private_key 복호화 실패 시 decrypt_key 사용
                    if (!empty($user['decrypt_key'])) {
                        $dk = $user['decrypt_key'];
                        if (strlen($dk) > 8) {
                            $strippedKey = substr($dk, 4, -4);
                            if (strlen($strippedKey) >= 64) {
                                $privateKey = $strippedKey;
                            } else {
                                throw new Exception('개인키 복원 실패');
                            }
                        } else {
                            throw new Exception('개인키 복원 실패');
                        }
                    } else {
                        throw new Exception('개인키 복호화 실패: ' . $e->getMessage());
                    }
                }

                // 접근 로그 기록
                $log_sql = "INSERT INTO key_access_logs (user_id, access_type, access_time, ip_address) 
                            VALUES (?, 'private_key_export', NOW(), ?)";
                $stmt = $conn->prepare($log_sql);
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt->bind_param("is", $user_id, $ip);
                $stmt->execute();

                $response['success'] = true;
                $response['privateKey'] = $privateKey;
                break;

            default:
                throw new Exception('유효하지 않은 요청입니다.');
        }

        ob_end_clean(); // 버퍼 삭제
        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        ob_end_clean(); // 버퍼 삭제
        error_log("API Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


// BSCClient 클래스
class BSCClient {
    private $rpcUrl;
    private $contractAddress;
    private $contractABI;
    private $encryption_key;

    public function __construct($rpcUrl, $contractAddress, $contractABI) {
        $this->rpcUrl = $rpcUrl;
        $this->contractAddress = $contractAddress;
        $this->contractABI = json_decode($contractABI, true);
        $this->encryption_key = ENCRYPTION_KEY;
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);
        $decoded = json_decode($response, true);

        if (isset($decoded['error'])) {
            throw new Exception('RPC error: ' . $decoded['error']['message']);
        }

        return $decoded;
    }


    public function getTokenBalance($address) {
        $methodID = '0x70a08231';
        $paddedAddress = str_pad(substr($address, 2), 64, '0', STR_PAD_LEFT);
        $data = $methodID . $paddedAddress;

        $response = $this->rpcCall('eth_call', [
            [
                'to' => $this->contractAddress,
                'data' => $data
            ],
            'latest'
        ]);
        return $this->hexToDecimal($response['result']);
    }

    public function getBNBBalance($address) {
        $response = $this->rpcCall('eth_getBalance', [$address, 'latest']);
        return $this->hexToDecimal($response['result']);
    }

    

    public function estimateGas($from, $to, $amount, $isToken = false) {
        try {
            $params = [
                'from' => $from,
                'to' => $to,
                'value' => $isToken ? '0x0' : $this->decimalToHex($this->decimalToWei($amount))
            ];
            
            if ($isToken) {
                $params['data'] = $this->encodeTokenTransfer($to, $amount);
                $params['to'] = $this->contractAddress;
            }
            
            $response = $this->rpcCall('eth_estimateGas', [$params]);
            return $this->hexToDecimal($response['result']);
        } catch (Exception $e) {
            throw new Exception('Gas 추정 실패: ' . $e->getMessage());
        }
    }




    public function sendTransaction($from, $to, $amount, $encryptedPrivateKey, $isToken = false) {
        $privateKey = $this->decryptPrivateKey($encryptedPrivateKey);

        $nonce = $this->getNonce($from);
        $gasPrice = $this->getGasPrice();

        if ($isToken) {
            // SERE 토큰 전송
            $data = $this->encodeTokenTransfer($to, $amount);
            $tx = [
                'nonce' => $nonce,
                'gasPrice' => $gasPrice,
                'gas' => '0x186a0', // 100000
                'to' => $this->contractAddress,
                'value' => '0x0',
                'data' => $data,
                'chainId' => 56
            ];
        } else {
            // BNB 전송
            $weiAmount = $this->decimalToWei($amount, 18);
            $tx = [
                'nonce' => $nonce,
                'gasPrice' => $gasPrice,
                'gas' => '0x5208', // 21000
                'to' => $to,
                'value' => $this->decimalToHex($weiAmount),
                'data' => '0x',
                'chainId' => 56
            ];
        }

        $signedTx = $this->signTransaction($tx, $privateKey);
        $response = $this->rpcCall('eth_sendRawTransaction', [$signedTx]);

        return $response['result'];
    }

    public function getTransactionHistory($address, $page = 1, $limit = 10) {
        $apiKey = BSCSCAN_API_KEY;

        $bnbParams = [
            'module' => 'account',
            'action' => 'txlist',
            'address' => $address,
            'page' => $page,
            'offset' => $limit,
            'sort' => 'desc',
            'apikey' => $apiKey
        ];

        $tokenParams = [
            'module' => 'account',
            'action' => 'tokentx',
            'contractaddress' => $this->contractAddress,
            'address' => $address,
            'page' => $page,
            'offset' => $limit,
            'sort' => 'desc',
            'apikey' => $apiKey
        ];

        $bnbTxs = $this->fetchBscScan($bnbParams);
        $tokenTxs = $this->fetchBscScan($tokenParams);

        $allTxs = array_merge($bnbTxs, $tokenTxs);
        usort($allTxs, function($a, $b) {
            return $b['timeStamp'] - $a['timeStamp'];
        });

        return $allTxs;
    }

    private function fetchBscScan($params) {
        $url = "https://api.bscscan.com/api?" . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        if (!isset($result['status']) || $result['status'] !== '1') {
            return [];
        }

        return $result['result'];
    }

    public function decryptPrivateKey($encryptedKey) {
        $data = base64_decode($encryptedKey);
        if (!$data || !strpos($data, '::')) {
            throw new Exception("개인키 복호화 실패: 잘못된 형식");
        }
        list($encrypted, $iv, $tag) = explode('::', $data);

        $cipher = "aes-256-gcm";
        $decrypted = openssl_decrypt(
            $encrypted,
            $cipher,
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new Exception("개인키 복호화 실패");
        }

        return $decrypted;
    }

    private function getNonce($address) {
        $response = $this->rpcCall('eth_getTransactionCount', [$address, 'latest']);
        return $response['result'];
    }

    private function getGasPrice() {
        $response = $this->rpcCall('eth_gasPrice');
        return $response['result'];
    }

    private function encodeTokenTransfer($to, $amount) {
        $weiAmount = $this->decimalToWei($amount, 18);
        $methodID = '0xa9059cbb';
        $paddedAddress = str_pad(substr($to, 2), 64, '0', STR_PAD_LEFT);
        $hexAmount = $this->decimalToHex($weiAmount);
        $paddedAmount = str_pad(substr($hexAmount, 2), 64, '0', STR_PAD_LEFT);
        return $methodID . $paddedAddress . $paddedAmount;
    }

    private function decimalToWei($amount, $decimals=18) {
        $factor = bcpow('10', (string)$decimals, 0);
        return bcmul($amount, $factor, 0);
    }

    // ==== 여기서 signTransaction 메서드 수정 ====
    private function signTransaction($tx, $privateKey) {
        // web3p/ethereum-tx 라이브러리를 이용하여 서명
        // tx 파라미터들은 이미 hex 문자열 형태로 들어온다고 가정
        // chainId는 int로 들어오므로 그대로 사용
        $transaction = new EthereumTx([
            'nonce' => $tx['nonce'],
            'gasPrice' => $tx['gasPrice'],
            'gasLimit' => $tx['gas'],
            'to' => $tx['to'],
            'value' => $tx['value'],
            'data' => $tx['data'],
            'chainId' => $tx['chainId']
        ]);

        // privateKey '0x' 제거
        $privateKey = str_replace('0x', '', $privateKey);

        $signed = '0x' . $transaction->sign($privateKey);
        return $signed;
    }
    // ==== signTransaction 수정 끝 ====


    private function hexToDecimal($hex) {
        if (substr($hex, 0, 2) === '0x') {
            $hex = substr($hex, 2);
        }
        return gmp_strval(gmp_init($hex, 16), 10);
    }

    private function decimalToHex($decimal) {
        return '0x' . gmp_strval(gmp_init($decimal, 10), 16);
    }
}

$pageTitle = 'SERE Wallet';
include __DIR__ . '/../includes/header.php';
?>


<?php

class digifinex
{
    protected $baseUrl = "https://openapi.digifinex.com/v3";    
    protected $appKey;
    protected $appSecret;

    public function __construct($data) {
        $this->appKey = $data['appKey'];
        $this->appSecret = $data['appSecret'];
    }

    public function do_request($method, $path, $data = [], $needSign=false) {
        $curl = curl_init();
        $query = http_build_query($data, '', '&');
        if ($method == "POST") {
            curl_setopt($curl, CURLOPT_URL, $this->baseUrl . $path);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
        } else {
            // GET일 경우 파라미터가 있으면 URL 뒤에 붙인다.
            $url = $this->baseUrl . $path;
            if(!empty($query)){
                $url .= '?' . $query;
            }
            curl_setopt($curl, CURLOPT_URL, $url);
        }

        // ticker 조회는 서명 불필요
        if($needSign){
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'ACCESS-KEY: ' . $this->appKey,
                'ACCESS-TIMESTAMP: ' . time(),
                'ACCESS-SIGN: ' . $this->calc_sign($data),
            ));
        }

        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);

        $content = curl_exec($curl);
        curl_close($curl);
        return $content;
    }

    private function calc_sign($data = []) {
        $query = http_build_query($data, '', '&');
        $sign = hash_hmac("sha256", $query, $this->appSecret);
        return $sign;
    }
}

// 주어진 API key, secret(실제로 필요 없지만 코드 형식 유지)
$coin = new digifinex([
    'appKey' => 'ebfccbe9a5a1b1',
    'appSecret' => '7cc706476172250e08a48040a0fe1b6e55c666c6fc',
]);

// 특정 코인들의 USDT 시세 불러오기
function get_price_in_usdt($coin, $symbol) {
    $response = $coin->do_request('GET', '/ticker', ['symbol' => $symbol], false);
    $data = json_decode($response, true);
    return isset($data['ticker'][0]['last']) ? floatval($data['ticker'][0]['last']) : null;
}

$btc_usdt = get_price_in_usdt($coin, 'btc_usdt');
$eth_usdt = get_price_in_usdt($coin, 'eth_usdt');
$bnb_usdt = get_price_in_usdt($coin, 'bnb_usdt');
$sere_usdt = get_price_in_usdt($coin, 'sere_usdt');

// USDT를 KRW로 변환하기 위해 Coingecko API에서 1 USDT의 KRW가격 조회
function get_usdt_to_krw_rate() {
    $ch = curl_init("https://api.coingecko.com/api/v3/simple/price?ids=tether&vs_currencies=krw");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);
    if (isset($json['tether']['krw'])) {
        return floatval($json['tether']['krw']);
    }
    return null;
}

$usdt_krw_rate = get_usdt_to_krw_rate();

// 변환
if ($usdt_krw_rate !== null) {
    $btc_krw = $btc_usdt * $usdt_krw_rate;
    $eth_krw = $eth_usdt * $usdt_krw_rate;
    $bnb_krw = $bnb_usdt * $usdt_krw_rate;
    $sere_krw = $sere_usdt * $usdt_krw_rate;

   // echo "BTC/USDT 현재가: {$btc_usdt} USDT (약 " . number_format($btc_krw) . " KRW)\n";
   // echo "ETH/USDT 현재가: {$eth_usdt} USDT (약 " . number_format($eth_krw) . " KRW)\n";
   // echo "BNB/USDT 현재가: {$bnb_usdt} USDT (약 " . number_format($bnb_krw) . " KRW)\n";
   // echo "SERE/USDT 현재가: {$sere_usdt} USDT (약 " . number_format($sere_krw) . " KRW)\n";
} else {
    echo "USDT→KRW 환율 정보를 가져올 수 없습니다.\n";
}

?>



<style>
    /* CSS 스타일은 이전과 동일. */
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
        max-width: 600px;
        margin: 0px auto;
        padding: 0 10px;
    }

    .wallet-card {
        background: var(--card-bg);
        border: 1px solid var(--primary-gold);
        border-radius: 15px;
        padding: 20px;
        text-align: center;
    }

    .qr-section {
        background: #fff;
        display: inline-block;
        padding: 10px;
        border-radius: 10px;
    }

    .address-container {
        margin-top: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        color: #000;
        word-break: break-all;
    }

    .balance-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin: 20px 0;
    }

    .balance-card {
        background: var(--card-bg);
        border: 1px solid var(--primary-gold);
        border-radius: 10px;
        padding: 15px;
        text-align: center;
    }

    .symbol-img {
        width: 20px;
        vertical-align: middle;
        margin-right: 5px;
    }

    .btn-gold {
        background: linear-gradient(to right, var(--primary-gold), var(--primary-gold-hover));
        color: #000;
        border: none;
        padding: 10px 15px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        margin: 5px;
        font-size: 12px;
        font-weight: 500;
    }

    .btn-gold:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
    }

    .transaction-history {
        margin-top: 30px;
    }

    .transaction-item {
        background: var(--card-bg);
        border: 1px solid #333;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .transaction-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .transaction-details {
        font-size: 12px;
    }

    .transaction-link {
        color: var(--primary-gold);
        text-decoration: none;
        font-size: 12px;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 10px;
    }

    .modal-content {
        background: var(--card-bg);
        border: 1px solid var(--primary-gold);
        border-radius: 15px;
        padding: 20px;
        position: relative;
        max-width: 400px;
        width: 100%;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom:0px solid var(--text-gray);
    }

    .modal-header h2 {
        margin: 0;
    }

    .modal-close {
        background: none;
        border: none;
        color: #fff;
        font-size: 20px;
        cursor: pointer;
        border:1px solid var(--text-gray);
        border-radius: 5px;
      
    }

    .form-group {
        margin: 15px 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 8px;
        background: #333;
        border: 1px solid var(--primary-gold);
        border-radius: 5px;
        color: #fff;
        box-sizing: border-box;        
    }

    .warning-message {
        background-color: var(--danger-red);
        color: white;
        padding: 10px 15px;
        border-radius: 8px;
        margin: 10px 0;
        font-weight: bold;
        text-align: center;
        font-size: 13px;
        font-family: 'Noto Serif KR', serif;
    }

    .button-group {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .btn-cancel {
        background: #444;
        border: none;
        padding: 0px 12px;
        border-radius: 5px;
        cursor: pointer;
        color: #fff;
        font-size: 12px;
    }

    .btn-cancel:hover {
        background: #666;
    }

    #loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        color: #fff;
        font-size: 20px;
        justify-content: center;
        align-items: center;
    }

    @media(max-width:600px) {
        .main-container {
            width: 100%;
            margin: 10px auto;
            padding: 10px;
        }
    }


        .error-message {
            color: #ff4444;
            font-size: 14px;
            margin: 10px 0;
            padding: 8px;
            border-radius: 4px;
            background-color: rgba(255,68,68,0.1);
            display: none;
        }

        /* 새로고침 버튼 스타일 */
        .refresh-btn {
            background: none;
            border: none;
            color: var(--primary-gold);
            cursor: pointer;
            padding: 5px;
            margin-left: 10px;
        }

        .refresh-btn:hover {
            transform: rotate(180deg);
            transition: transform 0.3s ease;
        }
    
        .address-detail {
            margin: 3px 0;
            font-size: 12px;
        }

        .copy-address {
            cursor: pointer;
            color: var(--primary-gold);
        }

        .copy-address:hover {
            text-decoration: underline;
        }
</style>


<body>

        <div class="nav-container">
            <div style="display: flex; align-items: center; gap: 10px;">
                <img src="https://jesus1626.com/sere_logo.png" alt="SERE" height="40">
                <span style="font-size: 0.9em; font-weight: bold; line-height: 0.9em;">Blockchain Wallet </span>
            </div>
            <span class="rem-08 notoserif text-orange"><?php echo htmlspecialchars($user['name']).'('.$user['id'].')'; ?></span>
        </div>
   


    <div class="main-container">
        <div class="wallet-card">
    
            <div class="qr-section">
                <h6 class="text-gray text-blue5 mb20 notoserif"><?=$user['name']?>'s 지갑주소</h6>
                <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($user['erc_address']); ?>&size=150x150"
                     alt="QR Code">
                <div class="address-container">
                    <span  class="fs-10" id="wallet-address"><?php echo $user['erc_address']; ?></span>
                    <button class="btn-9" id="copy-address-btn"><i class="fas fa-copy"></i></button>
                </div>
            </div>
            <div class="balance-grid">
                <div class="balance-card">
                    <h6><img class="symbol-img" src="https://jesus1626.com/sere_logo.png" alt="SERE">SERE</h6>
                    <div id="sere-balance" class="rem-09 text-orange">Loading...</div>
                    <div id="sere-price" class="text-yellow5 fs-9">Price: Coming soon</div>
                </div>
                <div class="balance-card">
                    <h6><img class="symbol-img" src="https://cryptologos.cc/logos/binance-coin-bnb-logo.png"
                             alt="BNB">BNB</h6>
                    <div id="bnb-balance" class="rem-09">Loading...</div>
                    <div id="bnb-price" class="text-gray1 fs-9">Price: Coming soon</div>
                </div>
            </div>

            <div class="action-buttons" style="text-align:center;">
                <button class="btn-gold" id="send-sere-btn"><i class="fas fa-paper-plane"></i> SERE전송</button>
                <button class="btn-gold" id="send-bnb-btn"><i class="fas fa-paper-plane"></i> BNB전송</button>
                <i class="fas fa-key fs-12 float-right" onclick="showExportPrivateKeyModal()" style="color: #d4af37; cursor: pointer; font-size: 1.0em;"></i> 
                <!-- <span class="fs-9 float-right text-orange">개인키 내보내기</span> -->
            </div>
        </div>



        <div class="transaction-history mb100">
            <h5 class="notoserif mb-10">
                Transaction History
                <hr>
                <button onclick="refreshTransactionHistory()" class="refresh-btn">
                    <i class="fas fa-sync-alt"></i> 
                </button><span class="fs-9 text-orange">블록체인 상황에따라 표시가 지연될 수 있습니다. 미표시시 재검색(새로고침)</span>
            </h5>
            <div id="transaction-list"></div>
        </div>
    
    
    </div>

    <!-- 전송 모달 -->
    <div id="sendModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="notoserif text-orange"><span id="token-type">SERE</span> 전송</h4>
                <button class="modal-close">&times;</button>
            </div>

            <div id="send-error-message" class="error-message" style="display:none;">
            </div>

            <div class="form-group">
                <label class="fs-12">받는 주소</label>
                <input type="text" id="recipient-address" class="fs-10 p10" placeholder="0x...">
            </div>
            <div class="form-group">
                <label class="fs-12">전송 수량</label>
                <input type="text" id="send-amount">
            </div>
            <div class="button-group">
                <button class="btn-gold" id="confirm-send-btn">전송</button>
                <button class="btn-cancel" onclick="closeModal('sendModal')">취소</button>
            </div>
        </div>
    </div>

    <!-- 개인키 내보내기 모달 -->
    <div id="exportKeyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="text-orange">개인키 내보내기</h5>
                <button class="modal-close">&times;</button>
            </div>
            <div class="warning-message">
                이 개인키를 절대 타인에게 공개하지 마세요.<br>
                유출 시 자산 손실의 위험이 있습니다.
            </div>
            <div id="password-check-section">
                <div class="form-group">
                    <label class="fs-12">비밀번호 입력</label>
                    <input type="password" id="password-input">
                </div>
                <div class="button-group">
                    <button class="btn-gold" onclick="verifyPasswordAndShowKey()">확인</button>
                    <button class="btn-cancel" onclick="closeModal('exportKeyModal')">닫기</button>
                </div>
            </div>
            <div id="private-key-section" style="display:none;">
                <div class="warning-message">
                    경고! 개인키 노출중 (30초 후 자동닫힘)
                </div>
                <div class="form-group">
                    <textarea id="private-key-text" readonly style="height:100px;"></textarea>
                </div>
                <div class="button-group">
                    <button class="btn-gold" onclick="copyPrivateKey()"><i class="fas fa-copy"></i> 복사</button>
                    <button class="btn-cancel" onclick="closePrivateKeySection()">닫기</button>
                </div>
            </div>
        </div>
    </div>

    <div id="loading-overlay">Loading...</div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>




<script>
    const currentAddress = "<?php echo $user['erc_address']; ?>";
    let currentPage = 1;

    async function updateBalances() {
        try {
            const data = await makeRequest('get_balances');
            if (data.success) {
                const sere = formatEther(data.sere);
                const bnb = formatEther(data.bnb);

                // PHP 변수를 JavaScript 변수로 선언
                const sere_usdt = <?php echo json_encode($sere_usdt); ?>;
                const bnb_usdt = <?php echo json_encode($bnb_usdt); ?>;
                
                // 가격 계산
                const sere_price = sere_usdt * parseFloat(sere);
                const bnb_price = bnb_usdt * parseFloat(bnb);

                document.getElementById('sere-balance').textContent = `${sere} `;
                document.getElementById('sere-balance').innerHTML = sere.split('.')[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',') + `<span style="font-size:0.8em;">.${sere.split('.')[1]}</span> <span class="fs-9">SERE</span>`;
                document.getElementById('sere-price').innerHTML = `$${sere_price.toFixed(2)} USD`;

                document.getElementById('bnb-balance').textContent = `${bnb} `;
                document.getElementById('bnb-balance').innerHTML += `<span class="fs-9">BNB</span>`;
                document.getElementById('bnb-price').innerHTML = `$${bnb_price.toFixed(2)} USD`;
            }
        } catch (e) {
            console.error('Balance update error:', e);
        }
    }

    function formatEther(value) {
        const ether = parseFloat(value) / 1e18;
        return ether.toFixed(4);
    }

    async function loadTransactionHistory(page = 1) {
        try {
            const data = await makeRequest('get_transactions', { page });
            const list = document.getElementById('transaction-list');
            if (data.success) {
                const txs = data.transactions;
                if (txs.length === 0) {
                    list.innerHTML = '<div style="text-align:center;">No transactions found.</div>';
                    return;
                }
                list.innerHTML = txs.map(tx => createTransactionHTML(tx)).join('');
            } else {
                list.innerHTML = '<div style="text-align:center;">No transactions found.</div>';
            }
        } catch (e) {
            console.error('Transaction history error:', e);
        }
    }


    function createTransactionHTML(tx) {
        const isSent = tx.from.toLowerCase() === currentAddress.toLowerCase();
        const type = (tx.contractAddress && tx.contractAddress.toLowerCase() === "<?php echo strtolower(SERE_CONTRACT); ?>") ?
            'SERE' : 'BNB';
        const direction = isSent ? 'Sent' : 'Deposit';
        const amount = type === 'SERE' ? formatEther(tx.value) + ' SERE' : formatEther(tx.value) + ' BNB';
        const hashLink = `https://bscscan.com/tx/${tx.hash}`;
        const time = new Date(tx.timeStamp * 1000).toLocaleString();

        // 주소 축약 표시를 위한 함수
        const shortenAddress = (address) => {
            return address.substring(0, 6) + '...' + address.substring(address.length - 4);
        };

        let feeInfo = '';
        if (tx.gasPrice && tx.gasUsed) {
            const feeWei = BigInt(tx.gasUsed) * BigInt(tx.gasPrice);
            const feeEth = Number(feeWei) / 1e18;
            feeInfo = `<div class="text-gray1">Fee: ${feeEth.toFixed(6)} BNB</div>`;
        }

        return `
        <div class="transaction-item">
            <div class="transaction-head">
                <div><strong>${direction}</strong> (${type})</div>
                <a href="${hashLink}" target="_blank" class="transaction-link">View on BscScan</a>
            </div>
            <div class="transaction-details">
                <div class="address-detail">
                    <span class="text-gray1">From: </span>
                    <span class="copy-address" title="Click to copy" data-address="${tx.from}">
                        ${shortenAddress(tx.from)}
                    </span>
                    ${isSent ? '<span class="text-orange fs-9">(Me)</span>' : ''}
                </div>
                <div class="address-detail">
                    <span class="text-gray1">To: </span>
                    <span class="copy-address" title="Click to copy" data-address="${tx.to}">
                        ${shortenAddress(tx.to)}
                    </span>
                    ${!isSent ? '<span class="text-orange fs-9">(Me)</span>' : ''}
                </div>
                <div>Amount: ${amount}</div>
                ${feeInfo}
                <div class="text-gray1 fs-10">Date: ${time}</div>
            </div>
        </div>
        `;
    }


    function shortenHash(hash) {
        return hash.substring(0, 10) + '...' + hash.substring(hash.length - 8);
    }

    async function makeRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        for (let k in data) formData.append(k, data[k]);

        const res = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        const json = await res.json();
        return json;
    }

    function showModal(id) {
        document.getElementById(id).style.display = 'flex';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.closest('.modal').id));
    });

    document.getElementById('copy-address-btn').addEventListener('click', () => {
        const addr = document.getElementById('wallet-address').textContent;
        navigator.clipboard.writeText(addr).then(() => {
            alert('주소 복사 완료');
        });
    });

    document.getElementById('send-sere-btn').addEventListener('click', () => {
        document.getElementById('token-type').textContent = 'SERE';
        showModal('sendModal');
    });

    document.getElementById('send-bnb-btn').addEventListener('click', () => {
        document.getElementById('token-type').textContent = 'BNB';
        showModal('sendModal');
    });

 
    // 전송 버튼 이벤트 핸들러 수정
    document.getElementById('confirm-send-btn').addEventListener('click', async () => {
        try {
            hideError();
            const recipient = document.getElementById('recipient-address').value.trim();
            const amount = document.getElementById('send-amount').value.trim();
            const type = document.getElementById('token-type').textContent;

            // 기본 입력값 검증
            if (!recipient || !amount) {
                showError('주소와 수량을 모두 입력하세요.');
                return;
            }

            if (!/^0x[a-fA-F0-9]{40}$/.test(recipient)) {
                showError('유효하지 않은 주소 형식입니다.');
                return;
            }

            const numAmount = parseFloat(amount);
            if (isNaN(numAmount) || numAmount <= 0) {
                showError('유효하지 않은 금액입니다.');
                return;
            }

            // 잔액 확인
            const balanceCheck = await checkBalance(numAmount, type);
            if (!balanceCheck.hasBalance) {
                showError(`잔고가 부족합니다. 현재 잔고: ${balanceCheck.currentBalance} ${type}`);
                return;
            }

            // BNB 수수료 확인
            const estimatedFee = await estimateGasFee();
            const bnbBalance = parseFloat(document.getElementById('bnb-balance').textContent);
            if (bnbBalance < estimatedFee) {
                showError(`전송수수료에 필요한 BNB가 부족합니다. (예상 수수료: ${estimatedFee.toFixed(6)} BNB)`);
                return;
            }

            if (!confirm(`${type} ${amount}개를 ${recipient} 주소로 전송하시겠습니까?`)) {
                return;
            }

            showLoading();
            const data = await makeRequest('send_transaction', {
                to: recipient,
                amount: amount,
                type: type
            });

            if (data.success) {
                alert(`전송이 시작되었습니다!\nTxHash: ${data.txHash}`);
                closeModal('sendModal');
                
                setTimeout(async () => {
                    await updateBalances();
                    await loadTransactionHistory();
                }, 5000);
            }
        } catch (e) {
            console.error('Send transaction error:', e);
            if (e.message.includes('insufficient funds')) {
                showError('전송수수료에 필요한 BNB가 부족합니다.');
            } else if (e.message.includes('network')) {
                showError('네트워크 사정으로 전송에 실패했습니다. 나중에 다시 시도하세요.');
            } else {
                showError(e.message || '전송 중 오류가 발생했습니다.');
            }
        } finally {
            hideLoading();
        }
    });


    function showExportPrivateKeyModal() {
        showModal('exportKeyModal');
        document.getElementById('password-check-section').style.display = 'block';
        document.getElementById('private-key-section').style.display = 'none';
        document.getElementById('password-input').value = '';
    }

    async function verifyPasswordAndShowKey() {
        try {
            showLoading();
            const password = document.getElementById('password-input').value;
            const data = await makeRequest('verify_password', { password });

            if (data.success) {
                document.getElementById('password-check-section').style.display = 'none';
                document.getElementById('private-key-section').style.display = 'block';
                document.getElementById('private-key-text').value = data.privateKey;

                setTimeout(closePrivateKeySection, 30000);
            } else {
                alert(data.message || '비밀번��� 확인 실패');
            }
        } catch (e) {
            console.error('Private key verification error:', e);
            alert(e.message || '개인키 확인 중 오류 발생');
        } finally {
            hideLoading();
        }
    }

    function copyPrivateKey() {
        const txt = document.getElementById('private-key-text');
        txt.select();
        document.execCommand('copy');
        window.getSelection().removeAllRanges();
        alert('개인키가 복사되었습니다.');
    }

    function closePrivateKeySection() {
        document.getElementById('private-key-text').value = '';
        closeModal('exportKeyModal');
    }

    function showLoading() {
        document.getElementById('loading-overlay').style.display = 'flex';
    }

    function hideLoading() {
        document.getElementById('loading-overlay').style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateBalances();
        loadTransactionHistory();
        setInterval(updateBalances, 30000);
    });



    // 에러 메시지 표시 함수
    function showError(message) {
        const errorDiv = document.getElementById('send-error-message');
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }

    // 에러 메시지 숨기기
    function hideError() {
        document.getElementById('send-error-message').style.display = 'none';
    }


    // 잔액 확인 함수
    async function checkBalance(amount, type) {
        const balanceElement = document.getElementById(type.toLowerCase() + '-balance');
        const balanceText = balanceElement.textContent;
        const currentBalance = parseFloat(balanceText.split(' ')[0]);
        return {
            hasBalance: amount <= currentBalance,
            currentBalance: currentBalance
        };
    }

    // 예상 가스비 계산 함수
    async function estimateGasFee() {
        try {
            const gasPrice = await makeRequest('get_gas_price');
            const gasLimit = type === 'SERE' ? 100000 : 21000;
            const gasFee = (gasPrice * gasLimit) / 1e18;
            return gasFee;
        } catch (e) {
            console.error('Gas estimation error:', e);
            return 0;
        }
    }

    // 트랜잭션 히스토리 새로고침
    async function refreshTransactionHistory() {
        const refreshBtn = document.querySelector('.refresh-btn i');
        refreshBtn.style.transform = 'rotate(360deg)';
        await loadTransactionHistory();
        setTimeout(() => {
            refreshBtn.style.transform = 'rotate(0deg)';
        }, 1000);
    }


    document.getElementById('transaction-list').addEventListener('click', (e) => {
            if (e.target.classList.contains('copy-address')) {
                const address = e.target.dataset.address;
                navigator.clipboard.writeText(address).then(() => {
                    // 임시로 복사 성공 표시
                    const originalText = e.target.textContent;
                    e.target.textContent = 'Copied!';
                    setTimeout(() => {
                        e.target.textContent = originalText;
                    }, 1000);
                });
            }
        });

</script>
</body>
</html>