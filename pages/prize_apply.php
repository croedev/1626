<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

require_once 'includes/config.php';

// AJAX 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $conn = db_connect();

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // rate 액션은 로그인 체크를 하지 않음
    if ($action === 'rate') {
        $res = $conn->query("SELECT COUNT(*) as count FROM prize_apply WHERE apply_status != 'cancelled'");
        $total_entries = $res->fetch_assoc()['count'];
        echo json_encode([
            'success' => true,
            'total_entries' => $total_entries
        ]);
        exit;
    }

    // 그 외 액션은 로그인 체크
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.']);
        exit;
    }

    try {
        if ($action === 'apply') {
            // 응모 기간 체크
            $today_full = date('Y-m-d');
            if ($today_full < '2024-12-10' || $today_full > '2025-01-10') {
                echo json_encode(['success' => false, 'message' => '현재 응모 기간이 아닙니다.']);
                exit;
            }

            $conn->begin_transaction();
            $stmt = $conn->prepare("SELECT myAmount, myPrize_used, myPrize_count FROM users WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                throw new Exception('사용자 정보를 찾을 수 없습니다.');
            }

            // 개인 최대 5회 제한
            if ($user['myPrize_count'] >= 5) {
                throw new Exception('더 이상 응모할 수 없습니다. (최대 5회)');
            }

            // 40만원 이상 가능해야 응모
            $available_amount = $user['myAmount'] - $user['myPrize_used'];
            if ($available_amount < 400000) {
                throw new Exception('응모를 위한 최소 금액(40만원)이 부족합니다.');
            }

            // prize_no 생성
            $today = date('ymd');
            $stmt = $conn->prepare("SELECT MAX(prize_no) as max_no FROM prize_apply WHERE prize_no LIKE CONCAT(?, '%')");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $max_row = $stmt->get_result()->fetch_assoc();

            $new_seq = 1;
            if ($max_row && $max_row['max_no']) {
                // max_no 형식: yymmdd-xxxx
                $parts = explode('-', $max_row['max_no']);
                if (isset($parts[1])) {
                    $current_num = (int)$parts[1];
                    $new_seq = $current_num + 1;
                }
            }

            $prize_no = $today . '-' . str_pad($new_seq, 4, '0', STR_PAD_LEFT);

            $new_count = $user['myPrize_count'] + 1;
            $ip_address = $_SERVER['REMOTE_ADDR'];

            // 응모 데이터 삽입
            $stmt = $conn->prepare("
                INSERT INTO prize_apply (
                    user_id, apply_amount, apply_count, apply_status, win_status, ip_address, prize_no
                ) VALUES (?, 400000, ?, 'pending', 'pending', ?, ?)
            ");
            $stmt->bind_param("iiss", $user_id, $new_count, $ip_address, $prize_no);
            if (!$stmt->execute()) {
                throw new Exception('응모 처리 중 오류가 발생했습니다.');
            }

            // 사용자 업데이트
            $stmt = $conn->prepare("
                UPDATE users 
                SET myPrize_used = myPrize_used + 400000, 
                    myPrize_count = myPrize_count + 1 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                throw new Exception('사용자 정보 업데이트 중 오류가 발생했습니다.');
            }

            $conn->commit();

            echo json_encode(['success' => true, 'message' => '응모가 완료되었습니다.']);
            exit;

        } elseif ($action === 'rate') {
            // 실시간 경쟁률 요청
            $res = $conn->query("SELECT COUNT(*) as count FROM prize_apply WHERE apply_status != 'cancelled'");
            $total_entries = $res->fetch_assoc()['count'];
            // total_prizes는 고정 50
            echo json_encode([
                'success' => true,
                'total_entries' => $total_entries
            ]);
            exit;

        } else {
            echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
            exit;
        }

    } catch (Exception $e) {
        if ($conn) {
            $conn->rollback();
        }
        error_log("Prize Apply Error - User: $user_id - " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    } finally {
        if ($conn) {
            $conn->close();
        }
    }
}

// GET 요청 시 HTML 출력
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=prize_apply");
    exit();
}

$pageTitle = '경품 응모하기';
include 'includes/header.php';

$conn = db_connect();
$user_id = $_SESSION['user_id'];

// 사용자 정보 조회
$stmt = $conn->prepare("
    SELECT name, myAmount, myPrize_used, myPrize_count 
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$available_amount = $user['myAmount'] - $user['myPrize_used'];
// 40만원 단위로 응모 가능 횟수
$raw_possible_entries = floor($available_amount / 400000);
// 개인 최대 5회 제한
$remaining_chances = 5 - $user['myPrize_count'];
$possible_entries = max(0, min($raw_possible_entries, $remaining_chances));

// 전체 응모 현황
$total_entries_res = $conn->query("SELECT COUNT(*) as count FROM prize_apply WHERE apply_status != 'cancelled'");
$total_entries = $total_entries_res->fetch_assoc()['count'];
$total_prizes = 50; 

// 응모 내역
$stmt = $conn->prepare("
    SELECT apply_date, apply_amount, apply_status, prize_no, win_status
    FROM prize_apply 
    WHERE user_id = ? 
    ORDER BY apply_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$entries = $stmt->get_result();

// 응모 기간 체크
$today_full = date('Y-m-d');
$apply_enabled = ($today_full >= '2024-12-10' && $today_full <= '2025-01-10');
?>

<!-- Odometer.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/odometer.js/0.4.8/themes/odometer-theme-default.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/odometer.js/0.4.8/odometer.min.js"></script>


<style>
/* 전체 컨테이너 스타일 */
.prize-dashboard {
    max-width: 100%;
    margin: 0 auto;
    padding: 15px;
    background: linear-gradient(180deg, #000000, #111111);
    min-height: calc(100vh - 100px);
    font-family: 'Noto Serif KR', serif;
}

/* 실시간 응모 현황 섹션 */
.live-status-card {
    background: linear-gradient(145deg, #1a1a1a, #222);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3);
}

.competition-numbers {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin: 20px 0;
}

.numbers {
    font-family: 'Noto Sans KR', sans-serif;
    font-size: 3.5rem;
    font-weight: 700;
    color: #d4af37;
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
    line-height: 1;
}

.divider {
    font-size: 3.5rem;
    color: #d4af37;
    font-weight: 700;
}

/* 이벤트 안내 카드 */
.event-info-card {
    background: linear-gradient(145deg, #111, #1a1a1a);
    border: 1px solid rgba(212,175,55,0.2);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
}

.event-info-card h3 {
    font-family: 'Noto Sans KR', sans-serif;
    color: #d4af37;
    font-size: 1.2rem;
    margin-bottom: 10px;
    border-bottom: 1px solid rgba(212,175,55,0.2);
    padding-bottom: 8px;
}

.event-info-card p {
    font-family: 'Noto Serif KR', serif;
    font-size: 0.9rem;
    margin: 5px 0;
    line-height: 1.4;
}

/* 나의 응모 현황 카드 */
.my-status-card {
    background: linear-gradient(145deg, #1a1a1a, #222);
    border: 1px solid rgba(212,175,55,0.2);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 10px;
}

.status-item {
    background: rgba(0,0,0,0.3);
    padding: 10px;
    border-radius: 8px;
    text-align: center;
}

.status-label {
    font-family: 'Noto Sans KR', sans-serif;
    font-size: 0.8rem;
    color: #888;
}

.status-value {
    font-family: 'Noto Serif KR', serif;
    font-size: 1.1rem;
    color: #d4af37;
    margin-top: 5px;
}

/* 응모 버튼 */
.apply-button {
    background: linear-gradient(45deg, #d4af37, #f2d06b);
    border: none;
    border-radius: 25px;
    color: #000;
    font-family: 'Noto Sans KR', sans-serif;
    font-weight: 700;
    font-size: 1.1rem;
    padding: 12px 0;
    width: 100%;
    margin-top: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.apply-button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(212,175,55,0.4);
}

.apply-button:disabled {
    background: #444;
    cursor: not-allowed;
}

/* 응모 내역 리스트 */
.history-card {
    background: linear-gradient(145deg, #111, #1a1a1a);
    border: 1px solid rgba(212,175,55,0.2);
    border-radius: 12px;
    padding: 15px;
}

.history-item {
    border-bottom: 1px solid rgba(212,175,55,0.1);
    padding: 10px 0;
}

.history-item:last-child {
    border-bottom: none;
}

.status-title {
    color: #d4af37;
    text-align: center;
    font-size: 1.2rem;
    margin-bottom: 15px;
}

.competition-display {
    background: rgba(0,0,0,0.3);
    border-radius: 10px;
    padding: 20px;
    text-align: center;
}

.meter-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
}

.odometer-wrapper {
    background: linear-gradient(145deg, #2a2a2a, #333);
    border-radius: 10px;
    padding: 15px 25px;
    box-shadow: 0 0 20px rgba(212,175,55,0.2);
    position: relative;
    overflow: hidden;
}

.odometer-wrapper::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, #d4af37, transparent);
    animation: shine 2s infinite;
}

.odometer {
    font-family: 'Noto Sans KR', sans-serif;
    font-size: 2.0rem !important;
    color: #d4af37;
    font-weight: 700;
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
}

.fixed-number {
    font-family: 'Noto Sans KR', sans-serif;
    font-size: 3.0rem;
    color: #7fa3a0;
    font-weight: 700;
    background: linear-gradient(145deg, #2a2a2a, #333);
    border-radius: 10px;
    padding: 15px 25px;
    box-shadow: 0 0 20px rgba(212,175,55,0.2);
}

@keyframes shine {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

.odometer.odometer-auto-theme .odometer-digit,
.odometer.odometer-theme-default .odometer-digit {
    color: #d4af37;
    font-size: 1.50rem;
    font-family: 'Noto Serif KR', serif;
}

</style>

<div class="prize-dashboard mb100">
    <!-- 실시간 응모 현황 -->
    <div class="live-status-card">
        <h3 class="status-title notosans">실시간 응모 현황</h3>
        <div class="competition-display">
            <div class="meter-container">
                <div class="odometer-wrapper">
                    <div id="current-entries" class="odometer">0</div>
                </div>
                <div class="divider">:</div>
                <div class="fixed-number">50</div>
            </div>
        </div>
    </div>

    <!-- 이벤트 안내 -->
    <div class="event-info-card">
        <h3>경품 이벤트 안내</h3>
        <p>•<span class="notosans"> 응모자격</span>: <span class="text-orange"><span class="text-success notosans">40만원(200개) 이상 구매 고객</span></span></p>
        <p>•<span class="notosans"> 응모횟수</span>: <span class="text-orange">40만원(구매토큰200개당) 1회, <span class="text-success notosans">개인 최대 5회까지</span></span></p>
        <p>•<span class="notosans"> 경품내용</span>: <span class="text-orange">예수세례주화 레플리카 <br><br> <span class="btn14 border-green05 text-success notosans fw-700">순금10돈 및 1000만원이상의 레플리카를 200만원(1000개토큰)으로 구매가능</span></span></p>
        <p>•<span class="notosans"> 당첨인원</span>: <span class="text-orange"><span class="text-success notosans">50명 한정</span></span></p>
        <p>•<span class="notosans"> 응모기간</span>: <span class="text-orange">2024-12-10 ~ 2025-01-10</span></p>
        <p>•<span class="notosans"> 추첨일시</span>: <span class="text-orange">2025-01-23 저녁8시(줌으로방송)</span></p>
    </div>

    <!-- 나의 응모 현황 -->
    <div class="my-status-card">
        <h3 class="notosans text-orange">나의 응모 현황</h3>
        <div class="status-grid">
            <div class="status-item bg-gray100 border-gray05">
                <div class="status-label">누적 구매금액</div>
                <div class="status-value"><?php echo number_format($user['myAmount']); ?>원</div>
            </div>
            <div class="status-item bg-blue100 border-gray05">
                <div class="status-label">응모가능 금액</div>
                <div class="status-value"><?php echo number_format(max($available_amount, 0)); ?>원</div>
            </div>
                        <div class="status-item bg-red100 border-gray05">
                <div class="status-label">현재 응모횟수</div>
                <div class="status-value"><?php echo $user['myPrize_count']; ?>/5회중</div>
            </div>
            <div class="status-item bg-green100 border-gray05">
                <div class="status-label">잔여 응모가능 횟수</div>
                <div class="status-value"><?php echo $possible_entries; ?>회</div>
            </div>

        </div>
        
        <button class="apply-button" onclick="submitEntry()" 
                <?php echo (!$apply_enabled || $possible_entries <= 0) ? 'disabled' : ''; ?>>
            <?php
            if (!$apply_enabled) {
                echo '응모 기간이 아닙니다';
            } elseif ($user['myPrize_count'] >= 5) {
                echo '최대 응모횟수 초과';
            } elseif ($possible_entries <= 0) {
                echo '추가 구매 필요';
            } else {
                echo '무료 응모신청';
            }
            ?>
        </button>
    </div>

    <!-- 응모 내역 -->
    <div class="history-card">
        <h3 class="notosans text-orange">나의 응모 내역</h3>
        <?php if ($entries->num_rows > 0): ?>
            <?php while ($entry = $entries->fetch_assoc()): ?>
                <div class="history-item">
                    <p class="notoserif">
                        응모일시: <?php echo date('Y-m-d H:i', strtotime($entry['apply_date'])); ?><br>
                        추첨코드: <span class="text-orange"><?php echo htmlspecialchars($entry['prize_no']); ?></span><br>
                        상태: <span class="btn12 border-green05 text-success notosans fw-700"><?php echo ($entry['win_status'] === 'won') ? '당첨' : 
                                   (($entry['win_status'] === 'lost') ? '미당첨' : '추첨대기중'); ?></span>
                    </p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="notoserif text-center">아직 응모 내역이 없습니다.</p>
        <?php endif; ?>
    </div>
</div>



<script>
// 응모 신청 처리
function submitEntry() {
    if (!confirm('응모하시겠습니까?')) return;

    // querySelector로 버튼 선택 (ID 대신 클래스 사용)
    const entryButton = document.querySelector('.apply-button');
    if (!entryButton) return; // 버튼이 없는 경우 처리

    entryButton.disabled = true;
    entryButton.classList.add('loading');
    entryButton.textContent = '처리중...';

    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'apply'})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message || '응모 처리 중 오류가 발생했습니다. 다시 시도해주세요.');
            entryButton.disabled = false;
            entryButton.classList.remove('loading');
            entryButton.textContent = '무료 응모신청';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('응모 처리 중 오류가 발생했습니다. 다시 시도해주세요.');
        entryButton.disabled = false;
        entryButton.classList.remove('loading');
        entryButton.textContent = '무료 응모신청';
    });
}
// 실시간 경쟁률 업데이트 (10초 마다)
document.addEventListener('DOMContentLoaded', function() {
    // 오도미터 초기화 및 초기 애니메이션
    let entriesOd = new Odometer({
        el: document.getElementById('current-entries'),
        value: 0,
        format: 'd',
        duration: 1000,
        animation: 'count'
    });

    // 페이지 로드 시 바로 현재 값으로 애니메이션
    setTimeout(() => {
        entriesOd.update(<?php echo $total_entries; ?>);
    }, 500);

    // 10초마다 업데이트
    setInterval(function() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action:'rate'})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // 숫자 업데이트 시 애니메이션 효과
                const wrapper = document.querySelector('.odometer-wrapper');
                wrapper.style.animation = 'pulse 0.5s ease';
                entriesOd.update(data.total_entries);
                
                // 애니메이션 초기화
                setTimeout(() => {
                    wrapper.style.animation = '';
                }, 500);
            }
        })
        .catch(err => console.error('Error:', err));
    }, 10000);
});


</script>

<?php include 'includes/footer.php'; ?>
