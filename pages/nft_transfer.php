<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';

// 사용자 인증 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=nft_transfer");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = db_connect();

// 함수 정의


function getNFTHistoryPaginated($conn, $user_id, $limit, $offset) {
    $sql = "SELECT nh.*, 
                   u1.name as from_user_name, 
                   u2.name as to_user_name
            FROM nft_history nh
            LEFT JOIN users u1 ON nh.from_user_id = u1.id
            LEFT JOIN users u2 ON nh.to_user_id = u2.id
            WHERE nh.from_user_id = ? OR nh.to_user_id = ?
            ORDER BY nh.transaction_date DESC
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $user_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getTotalNFTHistoryCount($conn, $user_id) {
    $sql = "SELECT COUNT(*) as total FROM nft_history 
            WHERE from_user_id = ? OR to_user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['total'];
}

function getTotalReceivedNFT($conn, $user_id) {
    $sql = "SELECT SUM(amount) as total FROM nft_history WHERE to_user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function getTotalSentNFT($conn, $user_id) {
    $sql = "SELECT SUM(amount) as total FROM nft_history WHERE from_user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function transferNFT($conn, $from_user_id, $to_user_id, $amount) {
    $conn->begin_transaction();

    try {
        // 보내는 사람의 NFT 차감
        $sql = "UPDATE users SET nft_token = nft_token - ? WHERE id = ? AND nft_token >= ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $amount, $from_user_id, $amount);
        $stmt->execute();

        if ($stmt->affected_rows == 0) {
            throw new Exception("NFT 차감 실패");
        }

        // 받는 사람의 NFT 증가
        $sql = "UPDATE users SET nft_token = nft_token + ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $amount, $to_user_id);
        $stmt->execute();

        // 거래 내역 기록
        $sql = "INSERT INTO nft_history (from_user_id, to_user_id, amount, transaction_type) VALUES (?, ?, ?, 'transfer')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $from_user_id, $to_user_id, $amount);
        $stmt->execute();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

// 사용자 정보 가져오기
$user = getUserInfo($conn, $user_id);

// 페이징 관련 변수 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// NFT 거래 내역 가져오기 (페이징 적용)
$nft_history = getNFTHistoryPaginated($conn, $user_id, $limit, $offset);

// 전체 거래 내역 수 가져오기
$total_records = getTotalNFTHistoryCount($conn, $user_id);
$total_pages = ceil($total_records / $limit);

$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// NFT 전송 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_id = $_POST['recipient_id'] ?? null;
    $amount = intval($_POST['amount'] ?? 0);

    if ($recipient_id && $amount > 0 && $amount <= $user['nft_token']) {
        $result = transferNFT($conn, $user_id, $recipient_id, $amount);
        if ($result) {
            $_SESSION['success_message'] = "NFT가 성공적으로 전송되었습니다.";
            // 받는 사람의 전화번호 가져오기
            $recipient = getUserInfo($conn, $recipient_id);
            // SMS 발송
            // sendSMS($recipient['phone'], $amount);
        } else {
            $_SESSION['error_message'] = "NFT 전송 중 오류가 발생했습니다.";
        }
    } else {
        $_SESSION['error_message'] = "유효하지 않은 입력입니다.";
    }
    
    // 사용자 정보 및 NFT 내역 갱신
    $user = getUserInfo($conn, $user_id);
    $nft_history = getNFTHistoryPaginated($conn, $user_id, $limit, $offset);

    // POST 후 리다이렉트 (절대 경로 사용)
    $redirect_url = '/nft_transfer';
    header("Location: " . $redirect_url);
    die("리다이렉션 중... 만약 자동으로 이동하지 않으면 <a href='".$redirect_url."'>여기</a>를 클릭하세요.");
}

$pageTitle = 'NFT 선물하기';
include __DIR__ . '/../includes/header.php';
?>



<style>
    .nft-transfer-container {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
    }
    .nft-info, .nft-transfer-form, .nft-history {
        background-color: #222;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 5px;
        border: 1px solid #444;
    }
    .form-group {
        margin-bottom: 15px;
    }
    .form-label {
        display: block;
        color: #d4af37;
        margin-bottom: 5px;
        font-size: 0.9rem;
        font-family: 'Noto Sans KR', sans-serif;
    }
    .form-control {
        width: 100%;
        padding: 8px;
        border: 1px solid #444;
        background-color: #8d8d8d;
        color: #000;
        border-radius: 4px;
        font-size: 1.1rem;
        font-weight: bold;
    }
    .btn-gold {
        background: linear-gradient(to right, #d4af37, #f2d06b);
        border: none;
        color: #000;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.9rem;
        font-weight: bold;
    }
    .nft-history-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
        font-weight: 200;
        font-family: 'Noto Sans KR', sans-serif;

    }
    .nft-history-table th, .nft-history-table td {
        border: 1px solid #444;
        padding: 5px;
        text-align: left;
        font-size: 0.8rem;
        font-weight: 200;
                border-left: 0px solid #444;
        border-right: 0px solid #444;
    }
    .nft-history-table th {
        background-color: #333;
    }
    .success-message, .error-message {
        margin-bottom: 10px;
        font-size: 1.3rem;
    }
    .success-message { color: #4CAF50; }
    .error-message { color: #ff6b6b; }
    .recipient-search {
        display: flex;
        margin-bottom: 10px;
    }
    .recipient-search input {
        flex-grow: 1;
        margin-right: 10px;
    }
    .recipient-results {
        background-color: #333;
        border: 1px solid #444;
        border-radius: 4px;
        max-height: 150px;
        overflow-y: auto;
        font-size: 0.9rem;
    }
    .recipient-result-item {
        padding: 8px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .recipient-result-item:hover {
        background-color: #444;
    }
    .pagination {
        text-align: center;
        margin-top: 20px;
    }
    .pagination a {
        color: #d4af37;
        padding: 4px 8px;
        text-decoration: none;
        transition: background-color .3s;
        border: 1px solid #444;
        margin: 0 4px;
        font-size: 0.8rem;
    }
    .pagination a.active {
        background-color: #d4af37;
        color: black;
        border: 1px solid #d4af37;
    }
    .pagination a:hover:not(.active) {background-color: #444;}
</style>

<div class="nft-transfer-container">
  
    <div class="nft-info mt30">
        <p>나의 NFT 보유량: <span id="current-nft" class="text-orange fs-18"><?php echo number_format($user['nft_token']); ?></span></p>
    </div>


    <form class="nft-transfer-form" method="post" action="">
        <div class="form-group">
            <label for="amount" class="form-label">상대방에게 보낼 수량</label>
            <input type="number" id="amount" name="amount" class="form-control" required min="1" max="<?php echo $user['nft_token']; ?>">
        </div>


       <div class="form-group">

            <label for="recipient-search" class="form-label">상대방 (검색후 선택)</label>
            
            <div style="display: flex;">
            <div class="recipient-search" style="width: 60%; margin-left: 0px;">
                <input type="text" id="recipient-search" class="form-control" placeholder="상대방 이름 검색"  style="width:35%;">
                <button type="button" onclick="searchRecipient()" class="btn-gold" style="width:30%;">검색</button>
             </div>
             
        <div class="referral-input" style="width:35%; margin-left: 10px;">
            <input type="text" id="selected-recipient" class="form-control" readonly              
                style="background-color: #000; border-color: #1c180f; color: #fff; pointer-events: none;"  placeholder="선택된 상대방">
            
            <input type="hidden" id="recipient_id" name="recipient_id">
        </div>
      </div>

    <div  class="mp-0 text-left text-orange border-orange"> <span class="text-orange fs-12"> 상대방 검색후 선택하여 등록하세요 ></span>
    </div>

        <div id="recipient-results" class="recipient-results"></div>
        <button type="submit" class="btn-gold mt30" style="width: 100%; margin-top: 15px;">보내기</button>
    </form>


</div>
</div>


 <div class="mb20 flex-center">

    <div class="mt-3 mb-3">
    <?php if ($success_message): ?>
        <p class="success-message"><?php echo $success_message; ?></p>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <p class="error-message"><?php echo $error_message; ?></p>
    <?php endif; ?>
     </div>
 </div>




    <div class="mx-20 mt20 mb100">
        <h4 class="text-orange">나의 NFT 내역</h4>
<hr>
        <div class="mt20 lh-08 fs-14">
        <p>● 현재 나의 NFT 보유량: <span style="color: yellow;"><?php echo number_format($user['nft_token']); ?></span></p>
        <p>● 총 받은 수량: <span style="color: yellow;"><?php echo number_format(getTotalReceivedNFT($conn, $user_id)); ?></span></p>
        <p>● 총 보낸 수량: <span style="color: yellow;"><?php echo number_format(getTotalSentNFT($conn, $user_id)); ?></span></p>
        </div>
        <table class="nft-history-table mb50">
            <thead>
                <tr>
                    <th>거래일시</th>
                    <th>보낸 사람</th>
                    <th>받은 사람</th>
                    <th>수량</th>
                    <th>거래 유형</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nft_history as $history): ?>
                <tr>
                    <td><?php echo $history['transaction_date']; ?></td>
                    <td><?php echo $history['from_user_name']; ?></td>
                    <td><?php echo $history['to_user_name']; ?></td>
                    <td class="text-right"><?php echo number_format($history['amount']); ?></td>
                    <td><?php echo $history['transaction_type']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- 페이징 네비게이션 추가 -->
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" <?php echo ($page == $i) ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>

</div>

<script>
function searchRecipient() {
    var searchTerm = document.getElementById('recipient-search').value;
    if (searchTerm.length < 2) {
        alert('검색어를 2글자 이상 입력해주세요.');
        return;
    }

    fetch('/pages/ajax/search_recipient.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'search=' + encodeURIComponent(searchTerm)
    })
    .then(response => response.json())
    .then(data => {
        var resultsDiv = document.getElementById('recipient-results');
        resultsDiv.innerHTML = '';
        if (data.length === 0) {
            resultsDiv.innerHTML = '<p>검색 결과가 없습니다.</p>';
        } else {
            data.forEach(user => {
                var userDiv = document.createElement('div');
                userDiv.className = 'recipient-result-item';
                userDiv.textContent = user.name + ' (' + user.phone + ')';
                userDiv.onclick = function() {
                    document.getElementById('selected-recipient').value = user.name;
                    document.getElementById('recipient_id').value = user.id;
                    resultsDiv.innerHTML = '';
                };
                resultsDiv.appendChild(userDiv);
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('검색 중 오류가 발생했습니다.');
    });
}

// 폼 제출 시 입력 필드 초기화 및 유효성 검사
document.querySelector('form.nft-transfer-form').addEventListener('submit', function(e) {
    var amount = document.getElementById('amount').value;
    var recipientId = document.getElementById('recipient_id').value;
    
    if (!amount || !recipientId) {
        e.preventDefault();
        alert('수량과 받는 사람을 모두 입력해주세요.');
        return;
    }
    
    // 폼 제출 후 입력 필드 초기화
    setTimeout(() => {
        document.getElementById('amount').value = '';
        document.getElementById('recipient-search').value = '';
        document.getElementById('selected-recipient').value = '';
        document.getElementById('recipient_id').value = '';
        document.getElementById('recipient-results').innerHTML = '';
    }, 100);
});

// 페이지 로드 시 폼 초기화
window.onload = function() {
    document.getElementById('amount').value = '';
    document.getElementById('recipient-search').value = '';
    document.getElementById('selected-recipient').value = '';
    document.getElementById('recipient_id').value = '';
    document.getElementById('recipient-results').innerHTML = '';
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>