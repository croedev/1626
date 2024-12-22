<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/config.php';

use kornrunner\Ethereum\Address;
use Web3\Web3;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;

class ErcAddressGenerator {
    private $conn;
    private $encryption_key;
    
    public function __construct() {
        $this->conn = db_connect();
        
        // 암호화 키 설정 - 실제 운영에서는 환경변수나 안전한 설정 파일에서 가져와야 함
        $this->encryption_key = "SERE_erc20_encryption_key_2024";
    }
    
    /**
     * 주소 생성 및 저장 메인 함수
     * @param int $userId 사용자 ID
     * @return array 처리 결과
     */
    public function generateAndSaveAddress($userId) {
        try {
            // 기존 주소 존재 여부 확인
            $stmt = $this->conn->prepare("SELECT erc_address FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if ($user && $user['erc_address']) {
                return [
                    'success' => false,
                    'message' => '이미 ERC 주소가 존재합니다.'
                ];
            }
            
            // 개인키 생성 (64자리 16진수)
            $privateKey = $this->generatePrivateKey();
            
            // 개인키로부터 주소 생성 (0x로 시작하는 42자리)
            $address = $this->generateAddress($privateKey);
            
            // 생성된 주소 유효성 검증
            if (!$this->validateAddress($address)) {
                throw new Exception("유효하지 않은 주소가 생성되었습니다: " . $address);
            }
            
            // 개인키 암호화
            $encryptedPrivateKey = $this->encryptPrivateKey($privateKey);
            
            // 복호화 키 생성 (원본 키에서 앞 2자리 제거 후 랜덤 4자리 추가)
            $decryptKey = $this->generateDecryptKey($privateKey);
            
            // DB 트랜잭션 시작
            $this->conn->begin_transaction();
            
            try {
                // 주소 정보 저장
                $stmt = $this->conn->prepare("
                    UPDATE users 
                    SET erc_address = ?,
                        private_key = ?,
                        decrypt_key = ?
                    WHERE id = ?
                ");
                
                $stmt->bind_param("sssi", 
                    $address,
                    $encryptedPrivateKey,
                    $decryptKey,
                    $userId
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("DB 저장 실패: " . $stmt->error);
                }
                
                $this->conn->commit();
                
                // 생성 성공 로그 기록
                error_log("Address generated successfully for user $userId: $address");
                
                return [
                    'success' => true,
                    'address' => $address,
                    'message' => 'ERC 주소가 성공적으로 생성되었습니다.'
                ];
                
            } catch (Exception $e) {
                $this->conn->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("ERC Address generation error for user $userId: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '주소 생성 중 오류가 발생했습니다: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 안전한 개인키 생성
     * @return string 64자리 16진수 개인키
     */
    private function generatePrivateKey() {
        // 32바이트(256비트) 랜덤 데이터 생성
        $bytes = random_bytes(32);
        // 16진수 문자열로 변환 (64자)
        $privateKey = bin2hex($bytes);
        
        // 키 길이 검증
        if (strlen($privateKey) !== 64) {
            throw new Exception("잘못된 개인키 길이: " . strlen($privateKey));
        }
        
        return $privateKey;
    }
    
    /**
     * 개인키로부터 이더리움 주소 생성
     * @param string $privateKey 64자리 16진수 개인키
     * @return string 0x로 시작하는 42자리 주소
     */
    private function generateAddress($privateKey) {
        try {
            $address = new Address($privateKey);
            // 0x 접두어 추가하여 반환
            return '0x' . $address->get();
        } catch (Exception $e) {
            error_log("Address generation error: " . $e->getMessage());
            throw new Exception("주소 생성 실패: " . $e->getMessage());
        }
    }
    
    /**
     * 이더리움 주소 유효성 검증
     * @param string $address 검증할 주소
     * @return bool 유효성 여부
     */
    private function validateAddress($address) {
        // 0x로 시작하는 42자리 16진수 형식 검증
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1;
    }
    
    /**
     * 개인키 암호화
     * @param string $privateKey 암호화할 개인키
     * @return string base64 인코딩된 암호화 데이터
     */
    private function encryptPrivateKey($privateKey) {
        $cipher = "aes-256-gcm";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $tag = "";
        
        // AES-256-GCM으로 암호화
        $encrypted = openssl_encrypt(
            $privateKey,
            $cipher,
            $this->encryption_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        if ($encrypted === false) {
            throw new Exception("개인키 암호화 실패");
        }
        
        // 암호화된 데이터, IV, 태그를 base64로 인코딩
        return base64_encode($encrypted . '::' . $iv . '::' . $tag);
    }
    

   

    private function generateDecryptKey($privateKey) {
    // 랜덤 4자리 16진수 생성 (앞)
    $prefixRandom = substr(str_shuffle('0123456789ABCDEF'), 0, 4);
    // 랜덤 4자리 16진수 생성 (뒤)
    $suffixRandom = substr(str_shuffle('0123456789ABCDEF'), 0, 4);
    
    // 복호화된 개인키 앞뒤에 랜덤값 추가
    return $prefixRandom . $privateKey . $suffixRandom;
}

// 복호화키 재생성 함수 추가
public function regenerateDecryptKey($userId) {
    try {
        // 사용자 정보 조회
        $stmt = $this->conn->prepare("SELECT private_key FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user || !$user['private_key']) {
            throw new Exception("개인키가 없는 사용자입니다.");
        }

        // 개인키 복호화
        $decryptedKey = $this->decryptPrivateKey($user['private_key']);
        // 새로운 복호화키 생성
        $newDecryptKey = $this->generateDecryptKey($decryptedKey);
        
        // DB 업데이트
        $stmt = $this->conn->prepare("UPDATE users SET decrypt_key = ? WHERE id = ?");
        $stmt->bind_param("si", $newDecryptKey, $userId);
        
        if (!$stmt->execute()) {
            throw new Exception("복호화키 저장 실패");
        }
        
        return [
            'success' => true,
            'message' => '복호화키가 재생성되었습니다.'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '복호화키 재생성 실패: ' . $e->getMessage()
        ];
    }
}



    
    /**
     * 개인키 복호화 (필요시 사용)
     * @param string $encryptedKey 암호화된 개인키
     * @return string 복호화된 개인키
     */
    public function decryptPrivateKey($encryptedKey) {
        try {
            // base64 디코딩
            $data = base64_decode($encryptedKey);
            list($encrypted, $iv, $tag) = explode('::', $data);
            
            $cipher = "aes-256-gcm";
            
            // 복호화
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
            
        } catch (Exception $e) {
            error_log("Private key decryption error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// API 엔드포인트로 사용될 경우의 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $conn = db_connect();

        // 미생성 주소 수 확인 요청
        if (isset($data['action']) && $data['action'] === 'check_pending') {
            $sql = "SELECT COUNT(*) as cnt FROM users WHERE erc_address IS NULL OR erc_address = ''";
            $result = $conn->query($sql);
            $count = $result->fetch_assoc()['cnt'];
            
            echo json_encode([
                'success' => true,
                'count' => (int)$count
            ]);
            exit;
        }
        
        // 전체 생성 요청
        if (isset($data['action']) && $data['action'] === 'generate_all') {
            $null_address_sql = "SELECT id FROM users WHERE erc_address IS NULL OR erc_address = ''";
            $result = $conn->query($null_address_sql);
            
            $success_count = 0;
            $error_count = 0;
            
            while ($row = $result->fetch_assoc()) {
                $generator = new ErcAddressGenerator();
                $genResult = $generator->generateAndSaveAddress($row['id']);
                if ($genResult['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "처리완료: 성공 $success_count, 실패 $error_count"
            ]);
            exit;
        }


        
        // 단일 ID 처리
        if (isset($data['user_id'])) {
            $generator = new ErcAddressGenerator();
            $result = $generator->generateAndSaveAddress($data['user_id']);
            echo json_encode($result);
            exit;
        }
        
        throw new Exception('필요한 파라미터가 누락되었습니다.');
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>