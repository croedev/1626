<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';
require __DIR__ . '/../vendor/autoload.php';

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

/**
 * BSCClient: BNB 네트워크(RPC)와 상호작용하는 클래스
 */
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

    /**
     * 지갑 주소의 SERE 토큰 잔액을 10^18 단위(wei)로 반환
     */
    public function getTokenBalance($address) {
        $methodID = '0x70a08231'; // balanceOf
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

    /**
     * 지갑 주소의 BNB 잔액(wei 단위)
     */
    public function getBNBBalance($address) {
        $response = $this->rpcCall('eth_getBalance', [$address, 'latest']);
        return $this->hexToDecimal($response['result']);
    }

    /**
     * DB에 저장된 개인키(암호화)를 복호화
     */
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

    /**
     * 트랜잭션 생성/서명/전송
     */
    public function sendTransaction($from, $to, $amount, $encryptedPrivateKey, $isToken = false) {
        $privateKey = $this->decryptPrivateKey($encryptedPrivateKey);
        $nonce = $this->getNonce($from);
        $gasPrice = $this->getGasPrice();

        if ($isToken) {
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

    /**
     * BSCScan API를 이용한 입출금 히스토리 조회(참고용)
     */
    public function getTransactionHistoryFromBlockchain($address, $page = 1, $limit = 10) {
        $apiKey = BSCSCAN_API_KEY;

        // BNB 내역
        $bnbParams = [
            'module' => 'account',
            'action' => 'txlist',
            'address' => $address,
            'page' => $page,
            'offset' => $limit,
            'sort' => 'desc',
            'apikey' => $apiKey
        ];
        // 토큰(SERE) 내역
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
        // 최신순 정렬
        usort($allTxs, function($a, $b) {
            return $b['timeStamp'] - $a['timeStamp'];
        });
        return $allTxs;
    }

    /**
     * bscscan API 호출
     */
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

    private function getNonce($address) {
        $response = $this->rpcCall('eth_getTransactionCount', [$address, 'latest']);
        return $response['result'];
    }

    private function getGasPrice() {
        $response = $this->rpcCall('eth_gasPrice');
        return $response['result'];
    }

    /**
     * 실제로 ERC-20 transfer() 함수를 호출하기 위해 data 필드를 인코딩
     */
    private function encodeTokenTransfer($to, $amount) {
        $weiAmount = $this->decimalToWei($amount, 18);
        $methodID = '0xa9059cbb'; // transfer
        $paddedAddress = str_pad(substr($to, 2), 64, '0', STR_PAD_LEFT);
        $hexAmount = $this->decimalToHex($weiAmount);
        $paddedAmount = str_pad(substr($hexAmount, 2), 64, '0', STR_PAD_LEFT);
        return $methodID . $paddedAddress . $paddedAmount;
    }

    private function decimalToWei($amount, $decimals=18) {
        $factor = bcpow('10', (string)$decimals, 0);
        return bcmul($amount, $factor, 0);
    }

    private function signTransaction($tx, $privateKey) {
        $transaction = new EthereumTx([
            'nonce' => $tx['nonce'],
            'gasPrice' => $tx['gasPrice'],
            'gasLimit' => $tx['gas'],
            'to' => $tx['to'],
            'value' => $tx['value'],
            'data' => $tx['data'],
            'chainId' => $tx['chainId']
        ]);
        $privateKey = str_replace('0x', '', $privateKey);
        $signed = '0x' . $transaction->sign($privateKey);
        return $signed;
    }

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

// ----------------------
// AJAX 요청 처리 시작
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        if (!isset($_POST['action'])) {
            throw new Exception('액션이 지정되지 않았습니다.');
        }

        ob_start();

        $bscClient = new BSCClient(BSC_RPC_URL, SERE_CONTRACT, $SERE_ABI);
        $response = ['success' => false];

        switch ($_POST['action']) {

            /**
             * 토큰/BNB 전송
             * $_POST['to'] : 받는 주소
             * $_POST['amount'] : 전송 희망 수량
             * $_POST['type'] : 'SERE' or 'BNB'
             * $_POST['fee_type'] : 'BNB' or 'SERE' (SERE일 때만 유효)
             */
            case 'send_transaction':
                if (!isset($_POST['to'], $_POST['amount'], $_POST['type'])) {
                    throw new Exception('필수 파라미터가 누락되었습니다.');
                }
                $to = $_POST['to'];
                $amount = floatval($_POST['amount']);
                $typeReq = $_POST['type'];
                $feeType = isset($_POST['fee_type']) ? $_POST['fee_type'] : 'BNB'; // 기본은 BNB

                // 주소 유효성 검사
                if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $to)) {
                    throw new Exception('유효하지 않은 주소 형식입니다.');
                }
                if ($amount <= 0) {
                    throw new Exception('유효하지 않은 금액입니다.');
                }

                // SERE 전송 여부
                $isToken = ($typeReq === 'SERE');

                // ----------------------------
                // (1) 예시: SERE 전송 한도 제한 로직
                // ----------------------------
                if ($isToken) {
                    $SERE_LIMIT = 1000;  
                    // wallet_history에서 지금까지 보낸 SERE 총합을 조회
                    $sql_sum = "SELECT COALESCE(SUM(amount), 0) as total_sent 
                                FROM wallet_history
                                WHERE user_id = ?
                                AND symbol = 'SERE'
                                AND from_address = ?";
                    $stmt_sum = $conn->prepare($sql_sum);
                    $stmt_sum->bind_param("is", $user_id, $user['erc_address']);
                    $stmt_sum->execute();
                    $result_sum = $stmt_sum->get_result();
                    $row_sum = $result_sum->fetch_assoc();
                    $already_sent = floatval($row_sum['total_sent']);

                    if (($already_sent + $amount) > $SERE_LIMIT) {
                        throw new Exception(
                            'SERE 전송 한도가 초과되었습니다. '
                            . '현재까지 전송한 수량: ' . $already_sent
                            . ' SERE, (최대 전송 한도: ' . $SERE_LIMIT . ' SERE)'
                        );
                    }
                }

                /**
                 * --------------------------------------------
                 * (2) 수수료 로직
                 * --------------------------------------------
                 * 1) BNB를 수수료로 지불 => 기존 로직 그대로
                 * 2) SERE를 수수료로 지불 => 회사지갑이 대신 BNB 가스 납부 + 사용자 SERE 5% 차감
                 */
                // 기본 값
                $finalAmount = $amount;   // 실제 전송될 최종수량
                $fee = 0.0;              // wallet_history에 기록할 수수료값
                $actualTxHash = '';      // 실제 트랜잭션해시

                if (!$isToken) {
                    // (2-1) 사용자가 BNB를 보낼 때는 fee_type 고려 없이, 
                    //       사용자가 직접 BNB가스로 전송(원래 로직 그대로).
                    
                    // 실제 트랜잭션 생성
                    $txHash = $bscClient->sendTransaction(
                        $user['erc_address'],
                        $to,
                        $finalAmount,
                        $user['private_key'],
                        false  // BNB 전송
                    );
                    $actualTxHash = $txHash;
                    $feeType = 'BNB'; // BNB를 보냈으므로 굳이 fee_type 선택 X

                } else {
                    // SERE 전송하는 경우
                    if ($feeType === 'BNB') {
                        // (2-2) BNB로 수수료를 낼 경우: 기존 로직과 동일
                        //       사용자가 직접 BNB를 가스비로 내어 SERE를 전송
                        $txHash = $bscClient->sendTransaction(
                            $user['erc_address'],
                            $to,
                            $finalAmount,
                            $user['private_key'],
                            true  // isToken = true
                        );
                        $actualTxHash = $txHash;
                        // fee는 실제 on-chain 상으로는 gas를 BNB로 소모했지만,
                        // 대략적인 예시값(estimate)으로 넣거나, 별도의 gasUsed 조회해서 업데이트
                        $fee = 0.001; // 예시

                    } else {
                        // (2-3) SERE 토큰으로 수수료를 낼 경우
                        //   - 회사가 BNB 가스를 대신 지불(메타 트랜잭션 가정)
                        //   - 사용자 SERE에서 5% 차감 (단, 최소 5 SERE)
                        
                        $sereFee = $amount * 0.05; // 5%
                        if ($sereFee < 5) {
                            $sereFee = 5;  // 최소 5개
                        }
                        if ($sereFee >= $amount) {
                            throw new Exception('보낼 금액보다 수수료가 많아 전송할 수 없습니다.');
                        }
                        $finalAmount = $amount - $sereFee;
                        $fee = $sereFee;

                        // 2-3-1. 먼저 사용자 -> 회사 지갑으로 수수료(sereFee) 전송  
                        //        (회사 지갑이 BNB 가스를 대신 낸다고 가정)
                        //        실제로는 회사 privateKey로 transferFrom() 등을 써야 하지만,
                        //        여기서는 단순 예시로 "사용자 -> 회사" 트랜잭션을 생성
                        $txHash_fee = $bscClient->sendTransaction(
                            $user['erc_address'],
                            COMPANY_SERE_ADDRESS,  // config.php의 회사 지갑 상수
                            $sereFee,
                            $user['private_key'],
                            true
                        );

                        // 2-3-2. 그리고 사용자 -> 최종 수신자(to) 로 (amount - fee) 전송
                        //        가스는 여전히 회사에서 낸다고 가정(코드 상은 동일)
                        $txHash_main = $bscClient->sendTransaction(
                            COMPANY_SERE_ADDRESS,  // 트랜잭션 from
                            $to,                   // to
                            $finalAmount,          
                            COMPANY_PRIVATE_KEY,   // 서명에 회사 키 사용
                            true                   // isToken = true
                        );
                        
                        // 여기서는 “최종 트랜잭션 해시”라고 한다면, 보통 실제 ‘받는 사람에게 전송’한 해시를 기록
                        // 필요하다면 wallet_history에 2개 레코드를 넣거나, 
                        // 혹은 다음처럼 tx_hash 컬럼에는 두 해시를 합쳐서 보관할 수도 있음(예시)
                        $actualTxHash = $txHash_main . ' | feeTx=' . $txHash_fee;
                    }
                }

                // ----------------------
                // (3) DB 기록 (wallet_history)
                // ----------------------
                $symbol = $isToken ? 'SERE' : 'BNB';
                $type = '전송';
                $from_address = $user['erc_address'];
                $to_address = $to;

                // 중복 tx_hash 체크(중복 insert 방지)
                $checkSql = "SELECT id FROM wallet_history WHERE tx_hash = ? LIMIT 1";
                $stmt_check = $conn->prepare($checkSql);
                $stmt_check->bind_param("s", $actualTxHash);
                $stmt_check->execute();
                $stmt_check->store_result();

                if ($stmt_check->num_rows == 0) {
                    $stmt_check->close();
                    $insertSql = "INSERT INTO wallet_history 
                        (user_id, symbol, type, amount, from_address, to_address, tx_hash, fee_type, fee, processed_at, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), '처리완료')";
                    $stmt_insert = $conn->prepare($insertSql);
                    $stmt_insert->bind_param(
                        "issdssssd",
                        $user_id,
                        $symbol,
                        $type,
                        $amount,       // 사용자가 입력한 원래 금액
                        $from_address,
                        $to_address,
                        $actualTxHash,
                        $feeType,
                        $fee
                    );
                    $stmt_insert->execute();
                    $stmt_insert->close();
                } else {
                    $stmt_check->close();
                }

                $response['success'] = true;
                $response['txHash'] = $actualTxHash;
                break;


            /**
             * 지갑 잔액(SERE, BNB) 조회
             */
            case 'get_balances':
                $response['success'] = true;
                $response['sere'] = $bscClient->getTokenBalance($user['erc_address']);
                $response['bnb'] = $bscClient->getBNBBalance($user['erc_address']);
                break;


            /**
             * DB에 기록된 트랜잭션 조회
             */
            case 'get_db_transactions':
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $limit = 10;
                $offset = ($page - 1) * $limit;
                $dbSql = "SELECT * FROM wallet_history WHERE user_id = ? ORDER BY processed_at DESC LIMIT ? OFFSET ?";
                $stmt_tx = $conn->prepare($dbSql);
                $stmt_tx->bind_param("iii", $user_id, $limit, $offset);
                $stmt_tx->execute();
                $res = $stmt_tx->get_result();

                $history = [];
                while($row = $res->fetch_assoc()) {
                    $history[] = $row;
                }
                $stmt_tx->close();

                $response['success'] = true;
                $response['transactions'] = $history;
                break;


            /**
             * 실시간 블록체인 조회(참고용)
             */
            case 'get_blockchain_transactions':
                $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
                $transactions = $bscClient->getTransactionHistoryFromBlockchain($user['erc_address'], $page, 10);
                $response['success'] = true;
                $response['transactions'] = $transactions;
                break;


            /**
             * 개인키 내보내기 전에 비밀번호 확인
             */
            case 'verify_password':
                if (!isset($_POST['password'])) {
                    throw new Exception('비밀번호가 제공되지 않았습니다.');
                }
                if (!password_verify($_POST['password'], $user['password'])) {
                    throw new Exception('비밀번호가 일치하지 않습니다.');
                }

                // 복호화 시도
                $bscClientTemp = new BSCClient(BSC_RPC_URL, SERE_CONTRACT, $SERE_ABI);
                try {
                    $privateKey = $bscClientTemp->decryptPrivateKey($user['private_key']);
                } catch (Exception $e) {
                    // decrypt_key가 있으면 fallback
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

                // 키 접근 로그 기록(선택)
                $log_sql = "INSERT INTO key_access_logs (user_id, access_type, access_time, ip_address) 
                            VALUES (?, 'private_key_export', NOW(), ?)";
                $stmt_log = $conn->prepare($log_sql);
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt_log->bind_param("is", $user_id, $ip);
                $stmt_log->execute();

                $response['success'] = true;
                $response['privateKey'] = $privateKey;
                break;


            default:
                throw new Exception('유효하지 않은 요청입니다.');
        }

        ob_end_clean();
        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        ob_end_clean();
        error_log("API Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}
// digifinex, get_price_in_usdt 등은 동일하므로 생략 가능

// 이하 HTML/CSS/JS 레이아웃은 기존 코드 유지
$pageTitle = 'SERE Wallet';
include __DIR__ . '/../includes/header.php';
?>


<?php
        class digifinex {
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
                    $url = $this->baseUrl . $path;
                    if(!empty($query)){
                        $url .= '?' . $query;
                    }
                    curl_setopt($curl, CURLOPT_URL, $url);
                }

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

        // 시세 조회
        $coin = new digifinex([
            'appKey' => 'ebfccbe9a5a1b1',
            'appSecret' => '7cc706476172250e08a48040a0fe1b6e55c666c6fc',
        ]);
        function get_price_in_usdt($coin, $symbol) {
            $response = $coin->do_request('GET', '/ticker', ['symbol' => $symbol], false);
            $data = json_decode($response, true);
            return isset($data['ticker'][0]['last']) ? floatval($data['ticker'][0]['last']) : null;
        }

        $btc_usdt = get_price_in_usdt($coin, 'btc_usdt');
        $eth_usdt = get_price_in_usdt($coin, 'eth_usdt');
        $bnb_usdt = get_price_in_usdt($coin, 'bnb_usdt');
        $sere_usdt = get_price_in_usdt($coin, 'sere_usdt');

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
        if ($usdt_krw_rate !== null) {
            $btc_krw = $btc_usdt * $usdt_krw_rate;
            $eth_krw = $eth_usdt * $usdt_krw_rate;
            $bnb_krw = $bnb_usdt * $usdt_krw_rate;
            $sere_krw = $sere_usdt * $usdt_krw_rate;
        }

?>

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
            border-bottom: 0px solid var(--text-gray);
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
            border: 1px solid var(--text-gray);
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
            background-color: rgba(255, 68, 68, 0.1);
            display: none;
        }

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
            color: var(--text-gray);
        }

        .copy-address:hover {
            text-decoration: underline;
        }

        .toggle-buttons {
            text-align: center;
            margin-bottom: 10px;
        }

        .toggle-buttons button {
            margin: 0 5px;
        }
</style>

<body>
    <!-- 상단 네비 -->
    <div class="nav-container">
        <div style="display: flex; align-items: center; gap: 10px;">
            <img src="https://jesus1626.com/sere_logo.png" alt="SERE" height="40">
            <span style="font-size: 0.9em; font-weight: bold; line-height: 0.9em;">Blockchain Wallet </span>
        </div>
        <span class="rem-08 notoserif text-orange">
            <?php echo htmlspecialchars($user['name']).'('.$user['id'].')'; ?>
        </span>
    </div>

    <div class="main-container">
        <!-- 지갑 카드 -->
        <div class="wallet-card">
            <div class="qr-section">
                <h6 class="text-gray text-blue5 mb20 notoserif">
                    <?=$user['name']?>'s 지갑주소
                </h6>
                <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($user['erc_address']); ?>&size=150x150" alt="QR Code">
                <div class="address-container">
                    <span class="fs-10" id="wallet-address"><?php echo $user['erc_address']; ?></span>
                    <button class="btn-9" id="copy-address-btn">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
            <div class="balance-grid">
                <div class="balance-card">
                    <h6>
                        <img class="symbol-img" src="https://jesus1626.com/sere_logo.png" alt="SERE">SERE
                    </h6>
                    <div id="sere-balance" class="rem-09 text-orange">Loading...</div>
                    <div id="sere-price" class="text-yellow5 fs-9">Price: Coming soon</div>
                </div>
                <div class="balance-card">
                    <h6>
                        <img class="symbol-img" src="https://cryptologos.cc/logos/binance-coin-bnb-logo.png" alt="BNB">BNB
                    </h6>
                    <div id="bnb-balance" class="rem-09">Loading...</div>
                    <div id="bnb-price" class="text-gray1 fs-9">Price: Coming soon</div>
                </div>
            </div>
            <div class="action-buttons" style="text-align:center;">
                <button class="btn-gold" id="send-sere-btn">
                    <i class="fas fa-paper-plane"></i> SERE전송
                </button>
                <button class="btn-gold" id="send-bnb-btn">
                    <i class="fas fa-paper-plane"></i> BNB전송
                </button>
                <i class="fas fa-key fs-12 float-right" onclick="showExportPrivateKeyModal()" 
                   style="color: #d4af37; cursor: pointer; font-size: 1.0em;"></i> 
            </div>
        </div>

        <!-- 트랜잭션 히스토리 -->
        <div class="transaction-history mb100">
            <h5 class="notoserif mb-10">Transaction History</h5>
            <hr>
            <div class="toggle-buttons" style="text-align: right; margin-bottom: 10px;">
                <span style="color: #888; margin-right: 10px;">View Mode : </span>
                <button class="icon-btn" onclick="showDbTransactions()" title="리스트 보기">
                    <i class="fas fa-list"></i>
                </button>
                <button class="icon-btn" onclick="showBlockchainTransactions()" title="블록체인 보기">
                    <i class="fas fa-cube"></i>
                </button>
            </div>
            <div id="transaction-list"></div>
        </div>
    </div>

    <!-- 전송 모달(수수료 선택 추가) -->
    <div id="sendModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="notoserif text-orange">
                    <span id="token-type">SERE</span> 전송
                </h4>
                <button class="modal-close">&times;</button>
            </div>
            <div id="send-error-message" class="error-message" style="display:none;"></div>
            <div class="form-group">
                <label class="fs-12">받는 주소</label>
                <input type="text" id="recipient-address" class="fs-10 p10" placeholder="0x...">
            </div>
            <div class="form-group">
                <label class="fs-12">전송 수량</label>
                <input type="text" id="send-amount">
            </div>

            <!--  (수수료 방법 선택) : BNB vs SERE -->
            <div class="form-group" id="fee-type-group" style="display:none;">
                <label class="fs-12">수수료 지급방법</label>
                <div style="display:flex; gap:20px; margin-top:5px;">
                    <label style="cursor:pointer; font-size:12px;">
                        <input type="radio" name="fee_type" value="BNB" checked> BNB로 지불
                    </label>
                    <label style="cursor:pointer; font-size:12px;">
                        <input type="radio" name="fee_type" value="SERE"> SERE(5%)로 지불
                    </label>
                </div>
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
                    <button class="btn-gold" onclick="copyPrivateKey()">
                        <i class="fas fa-copy"></i> 복사
                    </button>
                    <button class="btn-cancel" onclick="closePrivateKeySection()">닫기</button>
                </div>
            </div>
        </div>
    </div>

    <div id="loading-overlay">Loading...</div>
    <?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
const currentAddress = "<?php echo $user['erc_address']; ?>";

document.addEventListener('DOMContentLoaded', () => {
    updateBalances();
    loadDbTransactionHistory();
    setInterval(updateBalances, 20000);
});

// 잔액 갱신
async function updateBalances() {
    try {
        const data = await makeRequest('get_balances');
        if (data.success) {
            const sere = formatEther(data.sere);
            const bnb = formatEther(data.bnb);

            const sereElement = document.getElementById('sere-balance');
            sereElement.setAttribute('data-balance', sere);
            const splitted = sere.split('.');
            sereElement.innerHTML = splitted[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',')
                + (splitted[1] ? `<span style="font-size:0.8em;">.${splitted[1]}</span>` : '')
                + ` <span class="fs-9">SERE</span>`;

            const bnbElement = document.getElementById('bnb-balance');
            bnbElement.setAttribute('data-balance', bnb);
            bnbElement.innerHTML = bnb + ` <span class="fs-9">BNB</span>`;

            // 시세 반영(예: 디지파이넥스)
            const sere_usdt = <?php echo json_encode($sere_usdt); ?>;
            const bnb_usdt = <?php echo json_encode($bnb_usdt); ?>;
            if (sere_usdt && !isNaN(sere_usdt)) {
                const sere_price = sere_usdt * parseFloat(sere);
                document.getElementById('sere-price').innerHTML = `$${sere_price.toFixed(2)} USD`;
            }
            if (bnb_usdt && !isNaN(bnb_usdt)) {
                const bnb_price = bnb_usdt * parseFloat(bnb);
                document.getElementById('bnb-price').innerHTML = `$${bnb_price.toFixed(2)} USD`;
            }
        }
    } catch (e) {
        console.error('Balance update error:', e);
    }
}
function formatEther(value) {
    const ether = parseFloat(value) / 1e18;
    return ether.toFixed(4);
}

// DB 기반 트랜잭션
async function loadDbTransactionHistory(page = 1) {
    try {
        const data = await makeRequest('get_db_transactions', { page });
        const list = document.getElementById('transaction-list');
        if (data.success) {
            const txs = data.transactions;
            if (txs.length === 0) {
                list.innerHTML = '<div style="text-align:center;">No transactions found.</div>';
                return;
            }
            list.innerHTML = txs.map(tx => createDbTransactionHTML(tx)).join('');
        } else {
            list.innerHTML = '<div style="text-align:center;">No transactions found.</div>';
        }
    } catch (e) {
        console.error('DB Transaction history error:', e);
    }
}

// 블록체인 기반 트랜잭션
async function loadBlockchainTransactionHistory(page = 1) {
    try {
        const data = await makeRequest('get_blockchain_transactions', { page });
        const list = document.getElementById('transaction-list');
        if (data.success) {
            const txs = data.transactions;
            if (txs.length === 0) {
                list.innerHTML = '<div style="text-align:center;">No transactions found.</div>';
                return;
            }
            list.innerHTML = txs.map(tx => createBlockchainTransactionHTML(tx)).join('');
        } else {
            list.innerHTML = '<div style="text-align:center;">No transactions found.</div>';
        }
    } catch (e) {
        console.error('Blockchain Transaction history error:', e);
    }
}

// DB트랜잭션용 HTML
function createDbTransactionHTML(tx) {
    const isSent = tx.from_address.toLowerCase() === currentAddress.toLowerCase();
    const direction = isSent ? 'Sent' : 'Deposit';
    const hashLink = `https://bscscan.com/tx/${tx.tx_hash}`;
    const time = new Date(tx.processed_at).toLocaleString();
    const shortenAddress = (addr) => {
        if (!addr) return '';
        return addr.substring(0, 16) + '...' + addr.substring(addr.length - 4);
    };

    return `
    <div class="transaction-item">
        <div class="transaction-head">
            <div>
                <img src="${tx.symbol === 'SERE' 
                    ? 'https://jesus1626.com/sere_logo.png'
                    : 'https://cryptologos.cc/logos/binance-coin-bnb-logo.png'}"
                     alt="${tx.symbol}" 
                     style="width: 20px; height: 20px; vertical-align: middle; margin-right: 5px;">
                <strong>${direction}</strong> (${tx.symbol})
            </div>
            <a href="${hashLink}" target="_blank" class="transaction-link">View on BscScan</a>
        </div>
        <div class="transaction-details">
            <div class="address-detail">
                <span class="text-gray1">From: </span>
                <span title="Click to copy">
                    ${shortenAddress(tx.from_address)}
                </span>
                ${isSent ? '<span class="text-orange fs-9">(Me)</span>' : ''}
            </div>
            <div class="address-detail">
                <span class="text-gray1">To: </span>
                <span title="Click to copy">
                    ${shortenAddress(tx.to_address)}
                </span>
                ${!isSent ? '<span class="text-orange fs-9">(Me)</span>' : ''}
            </div>
            <div>Amount: <span class="text-orange">${tx.amount} ${tx.symbol}</span></div>
            ${
                tx.fee && parseFloat(tx.fee) > 0
                ? `<div class="text-gray1">Fee: ${tx.fee} ${tx.fee_type}</div>`
                : ''
            }
            <div class="text-gray1 fs-10">Date: ${time}</div>
            <div class="text-gray1 fs-10">TxHash: 
                <a href="${hashLink}" target="_blank" 
                   class="text-blue3 underline-none">${shortenHash(tx.tx_hash)}</a> 
            </div>
        </div>
    </div>
    `;
}

// 블록체인 내역용 HTML
function createBlockchainTransactionHTML(tx) {
    const isSent = tx.from.toLowerCase() === currentAddress.toLowerCase();
    const type = (tx.contractAddress && tx.contractAddress.toLowerCase() === "<?php echo strtolower(SERE_CONTRACT); ?>") 
        ? 'SERE' : 'BNB';
    const direction = isSent ? 'Sent' : 'Deposit';
    const amount = type === 'SERE' ? formatEther(tx.value) + ' SERE' : formatEther(tx.value) + ' BNB';
    const hashLink = `https://bscscan.com/tx/${tx.hash}`;
    const time = new Date(tx.timeStamp * 1000).toLocaleString();
    const shortenAddress = (addr) => {
        if (!addr) return '';
        return addr.substring(0, 16) + '...' + addr.substring(addr.length - 4);
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
            <div>
                <img src="${type === 'SERE' 
                  ? 'https://jesus1626.com/sere_logo.png'
                  : 'https://cryptologos.cc/logos/binance-coin-bnb-logo.png'}" 
                     alt="${type}" 
                     style="width: 20px; height: 20px; vertical-align: middle; margin-right: 5px;">
                <strong>${direction}</strong> (${type})
            </div>
            <a href="${hashLink}" target="_blank" class="transaction-link">View on BscScan</a>
        </div>
        <div class="transaction-details">
            <div class="address-detail">
                <span class="text-gray1">From: </span>
                <span title="Click to copy">
                    ${shortenAddress(tx.from)}
                </span>
                ${isSent ? '<span class="text-orange fs-9">(Me)</span>' : ''}
            </div>
            <div class="address-detail">
                <span class="text-gray1">To: </span>
                <span title="Click to copy">
                    ${shortenAddress(tx.to)}
                </span>
                ${!isSent ? '<span class="text-orange fs-9">(Me)</span>' : ''}
            </div>
            <div>Amount: <span class="text-orange">${amount}</span></div>
            ${feeInfo}
            <div class="text-gray1 fs-10">Date: ${time}</div>
            <div class="text-gray1 fs-10">TxHash: 
                <a href="${hashLink}" target="_blank" 
                   class="text-blue3 underline-none">${shortenHash(tx.hash)}</a> 
            </div>
        </div>
    </div>
    `;
}
function shortenHash(hash) {
    if (!hash) return '';
    return hash.substring(0, 28) + '...' + hash.substring(hash.length - 8);
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

// 주소복사
document.getElementById('copy-address-btn').addEventListener('click', () => {
    const addr = document.getElementById('wallet-address').textContent.trim();
    navigator.clipboard.writeText(addr).then(() => {
        alert('주소 복사 완료');
    });
});

// 전송 버튼
document.getElementById('send-sere-btn').addEventListener('click', () => {
    document.getElementById('token-type').textContent = 'SERE';
    document.getElementById('fee-type-group').style.display = 'block';  // 수수료 선택 보이기
    showModal('sendModal');
});
document.getElementById('send-bnb-btn').addEventListener('click', () => {
    document.getElementById('token-type').textContent = 'BNB';
    document.getElementById('fee-type-group').style.display = 'none';   // 수수료 선택 숨김
    showModal('sendModal');
});

// 실제 전송 로직
document.getElementById('confirm-send-btn').addEventListener('click', async () => {
    try {
        hideError();
        const recipient = document.getElementById('recipient-address').value.trim();
        const amount = document.getElementById('send-amount').value.trim();
        const type = document.getElementById('token-type').textContent.trim();

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
        
        // 수수료방법(라디오)
        let fee_type = 'BNB';
        const feeRadios = document.querySelectorAll('input[name="fee_type"]');
        feeRadios.forEach(r => { if (r.checked) fee_type = r.value; });

        // 잔액 부족 여부 체크(간단)
        const balElem = document.getElementById(type.toLowerCase() + '-balance');
        if (balElem) {
            const currentBalance = parseFloat(balElem.getAttribute('data-balance'));
            if (numAmount > currentBalance) {
                showError(`잔고 부족! 현재 ${type} 잔액: ${currentBalance}`);
                return;
            }
        }

        if (!confirm(`${type} ${numAmount}개를\n${recipient}에게 전송하시겠습니까?\n(수수료: ${fee_type})`)) {
            return;
        }

        showLoading();
        const data = await makeRequest('send_transaction', {
            to: recipient,
            amount: numAmount,
            type: type,
            fee_type: fee_type
        });
        if (!data.success) {
            throw new Error(data.message);
        }
        alert(`전송이 시작되었습니다.\nTxHash: ${data.txHash}`);
        closeModal('sendModal');
        setTimeout(async () => {
            await updateBalances();
            await loadDbTransactionHistory();
        }, 3000);

    } catch (e) {
        console.error('Send transaction error:', e);
        showError(e.message || '전송 중 오류가 발생했습니다.');
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
            setTimeout(closePrivateKeySection, 30000); // 30초후 자동 닫힘
        } else {
            alert(data.message || '비밀번호 확인 실패');
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

function showError(msg) {
    const err = document.getElementById('send-error-message');
    err.textContent = msg;
    err.style.display = 'block';
}
function hideError() {
    document.getElementById('send-error-message').style.display = 'none';
}
function showLoading() {
    document.getElementById('loading-overlay').style.display = 'flex';
}
function hideLoading() {
    document.getElementById('loading-overlay').style.display = 'none';
}
function showDbTransactions() {
    loadDbTransactionHistory();
}
function showBlockchainTransactions() {
    loadBlockchainTransactionHistory();
}

async function makeRequest(action, data = {}) {
    const formData = new FormData();
    formData.append('action', action);
    for (let k in data) {
        formData.append(k, data[k]);
    }
    const res = await fetch(window.location.href, {
        method: 'POST',
        body: formData
    });
    return await res.json();
}
function shortenHash(hash) {
    if (!hash) return '';
    return hash.substring(0, 28) + '...' + hash.substring(hash.length - 8);
}
</script>

</body>
</html>