<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// 로그인 체크
if (!is_logged_in()) {
    header("Location: /login?redirect=/profile");
    exit;
}

$pageTitle = '프로필 정보';
include __DIR__ . '/../includes/header.php';

$conn = db_connect();
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT u.*, r.name as referrer_name, r.referral_code as referrer_code, o.name as organization_name
                        FROM users u 
                        LEFT JOIN users r ON u.referred_by = r.id
                        LEFT JOIN organizations o ON u.organization = o.id
                        WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "사용자 정보를 찾을 수 없습니다.";
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$name = $user['name'];
$phone = $user['phone'];
$email = $user['email'];
$organization = $user['organization_name'] ?? '미지정';
$referral_link = $user['referral_link'];
$referrer_info = $user['referrer_name'] ? $user['referrer_name'] . ' (' . $user['referrer_code'] . ')' : '없음';
?>

<style>
    hr{ border :1px solid orange;}
    .profile-container {
        width: 100%;
        max-width: 600px;
        margin: 20px auto;
        padding: 30px;
   
        background-color: rgba(42, 42, 42, 0.3);
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        */
    }
    .profile-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }
    .profile-info {
        flex: 1;
        margin-right: 10px;
    }
    .qr-code {
        width: 120px;
        height: 120px;
    }
    .qr-code img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        border: 2px solid #d4af37;
        padding: 2px;
        margin-top:25px;
        background-color: #ffffff;
    }
    .form-group {
        margin-bottom: 10px;
    }
    .form-label {
        display: block;
        font-family: 'Noto Sans KR', sans-serif;
        color: #d4af37;
        font-weight: bold;
        margin-bottom: 2px;
        font-size: 0.9em;
    }
    .form-control {
        width: 100%;
        padding: 5px;
        background-color: #333333;
        border: 1px solid #d4af37;
        color: #ffffff;
        border-radius: 5px;
        font-size: 0.9em;
    }
    .btn-gold {
        background: linear-gradient(to right, #d4af37, #f2d06b);
        border: none;
        color: #000000;
        font-weight: bold;
        padding: 5px 10px;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9em;
    }
    .btn-gold:hover {
        opacity: 0.9;
    }
    .input-group {
        display: flex;
    }
    .input-group .form-control {
        flex: 1;
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }
    .input-group .btn-gold {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    .button-group {
        display: flex;
        justify-content: end;
        margin-top: 0px;
        padding-top:0px;
    }
    .btn-outline {
        background-color: transparent;
        border: 1px solid #d4af37;
        color: #d4af37;
        font-size: 0.75em;
        padding: 5px 10px;
        border-radius: 3px;
        cursor: pointer;
        transition: all 0.3s ease;
        margin:2px;
    }
    .btn-outline:hover {
        background-color: #d4af37;
        color: #000000;
    }
    .profile-summary {
        margin-top: 20px;
        padding: 15px;
        background-color: rgba(42, 42, 42, 0.3);
        border-radius: 5px;
    }

    .profile-summary h4 {
        color: #d4af37;
        margin-bottom: 10px;
    }

    .profile-summary ul {
        list-style-type: none;
        padding: 0;
    }

    .profile-summary li {
        margin-bottom: 5px;
        font-family: 'Noto Sans KR', sans-serif;
        color: #ffffff;
    }

    .profile-summary span {
        color: #d4af37;
        font-weight: bold;
    }
</style>


<div class="profile-container pt30">


    <div class="profile-header">
        <div class="profile-info">
            <div class="form-group">
                <label for="name" class="form-label">이름</label>
                <input type="text" class="form-control" id="name" value="<?php echo htmlspecialchars($name); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="phone" class="form-label">전화번호</label>
                <input type="text" class="form-control" id="phone" value="<?php echo htmlspecialchars($phone); ?>" readonly>
            </div>
        </div>
        <div class="qr-code">
            <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($referral_link); ?>&size=100x100" alt="QR Code">
        </div>
    </div>

     <? /*
    <div class="form-group">
        <label for="email" class="form-label">이메일</label>
        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($email); ?>" readonly>
    </div>

   
    <div class="form-group">
        <label for="organization" class="form-label">소속 단체</label>
        <input type="text" class="form-control" id="organization" value="<?php echo htmlspecialchars($organization); ?>" readonly>
    </div>
*/?>
    <div class="form-group">
        <label for="referrer" class="form-label">추천인</label>
        <input type="text" class="form-control" id="referrer" value="<?php echo htmlspecialchars($referrer_info); ?>" readonly>
    </div>

    <div class="form-group">
        <label for="referral_link" class="form-label">추천 링크</label>
        <div class="input-group">
            <input type="text" class="form-control" id="referral_link" value="<?php echo htmlspecialchars($referral_link); ?>" readonly>
            <button class="btn-gold" type="button" onclick="copyReferralLink()">복사</button>
        </div>
    </div>

    <div class="profile-summary">
        <h5>나의 정보 요약</h5>
        <hr>
        <?php
        // 사용자 정보 가져오기
        $stmt = $conn->prepare("
            SELECT rank, myQuantity, nft_token, cash_points, mileage_points
            FROM users
            WHERE id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_summary = $result->fetch_assoc();

        // 직급 이름 가져오기
  
        ?>
        <ul class="fs-12">
            <li>나의 직급: <span><?php echo htmlspecialchars($user_summary['rank']); ?></span></li>
            <li>나의 구매수량: <span><?php echo number_format($user_summary['myQuantity']); ?> 개</span></li>
            <li>나의 보유NFT: <span><?php echo number_format($user_summary['nft_token']); ?> 개</span></li>
            <li>현금포인트: <span><?php echo number_format($user_summary['cash_points']); ?> 원</span></li>
            <li>마일리지포인트: <span><?php echo number_format($user_summary['mileage_points']); ?> 원</span></li>
        </ul>
    </div>

    </div>

    <div class="mx-30 fw-200 ">
        <div class=" ">
                <button class="btn-outline" onclick="location.href='/order_apply'">구매하기</button>
                <button class="btn-outline" onclick="location.href='/order_list'">구매내역</button>
                <button class="btn-outline" onclick="location.href='/org_tree'">추천조직도</button>

                <button class="btn-outline" onclick="location.href='/commission'">수수료조회</button>
                <button class="btn-outline" onclick="location.href='/nft_transfer'">NFT선물하기</button> 
                <button class="btn-outline" onclick="location.href='/withdrawals'">출금신청</button> 

                <button class="btn-outline bg-gray60" onclick="location.href='/sere_swap'">NFT토큰(스왑)</button>
                <button class="btn-outline bg-gray60" onclick="location.href='/sere_wallet'">SERE월렛(지갑)</button>

                <button class="btn-outline" onclick="location.href='/nftmovie'">NFT성경찬송</button>
                <button class="btn-outline" onclick="location.href='/forgot_password'">비밀번호변경</button>
                <button class="btn-outline" onclick="location.href='/logout'">로그아웃</button>
        </div>

    </div>

 

 <!-- <div class="qr-code-large">
     <img src="https://api.qrserver.com/v1/create-qr-code/?data=<?php echo urlencode($referral_link); ?>&size=200x200" alt="QR Code">
 </div> -->
 

 <h3 class="fs-20 mt50 text-center">The Baptism of Jesus, 1626</h3>
 <div class="coin-images mt20 flex-center mb100 pb100">
     <img src="assets/images/jesus_front_back.png" alt="세례주화 앞뒷면" class="w-50">
    
 </div>

<script>
    function copyReferralLink() {
        const copyText = document.getElementById("referral_link");
        copyText.select();
        copyText.setSelectionRange(0, 99999);
        document.execCommand("copy");
        alert("추천 링크가 복사되었습니다: " + copyText.value);
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>