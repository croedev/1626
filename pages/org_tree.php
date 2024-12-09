<?php
// 오류 보고 설정
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/organization_functions.php';

// 사용자 인증 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=org_tree");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = db_connect();

if (!$conn) {
    die("데이터베이스 연결에 실패했습니다.");
}

// 사용자 정보 가져오기
$user = getUserInfo($conn, $user_id);
if ($user === null) {
    error_log("사용자 정보를 가져오는데 실패했습니다. User ID: " . $user_id);
    die("사용자 정보를 가져오는데 실패했습니다. 관리자에게 문의하세요.");
}

// 조직도 데이터 가져오기
$organizationData = getOrganizationData($conn, $user_id);

if (empty($organizationData)) {
    $organizationData = [
        [
            'id' => $user_id,
            'name' => $user['name'],
            'rank' => $user['rank'],
            'myQuantity' => $user['myQuantity'] ?? $user['direct_volume'] ?? 0,
            'myAmount' => $user['myAmount'] ?? 0,
            'myTotal_quantity' => $user['myTotal_quantity'] ?? $user['ref_total_volume'] ?? 0,
            'myTotal_Amount' => $user['myTotal_Amount'] ?? 0,
            'commission_total' => $user['commission_total'] ?? 0,
            'nft_token' => $user['nft_token'] ?? 0,
            'myAgent' => $user['myAgent'] ?? 0,
            'myAgent_referral' => $user['myAgent_referral'] ?? 0,
            'phone' => $user['phone'] ?? '',
            'total_distributor_count' => $user['total_distributor_count'] ?? 0,
            'special_distributor_count' => $user['special_distributor_count'] ?? 0,
            'direct_referrals_count' => $user['direct_referrals_count'] ?? 0,
            'total_referrals_count' => $user['total_referrals_count'] ?? 0,
            'referred_by' => null,
            'depth' => 1
        ]
    ];
}

// 트리 구조로 데이터 변환
$treeData = convertToTreeData($organizationData);

$pageTitle = '추천인 트리 조직도';
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* 트리 스타일 */
    ul.tree, ul.tree ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    ul.tree ul {
        margin-left: 20px;
        padding-left: 20px;
        position: relative;
    }
    ul.tree ul:before {
        content: '';
        position: absolute;
        top: 0;
        left: 10px;
        bottom: 0;
        width: 1px;
        background: #ccc;
    }
    ul.tree li {
        margin: 5px 0;
        position: relative;
    }
    ul.tree li:before {
        content: '';
        position: absolute;
        top: 12px;
        left: -10px;
        width: 20px;
        height: 1px;
        background: #ccc;
    }
    ul.tree li .member-name {
        color: #000;
        font-size: 0.9em;
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 5px 10px;
        display: inline-flex;
        align-items: center;
        background-color: #f9f9f9;
        cursor: pointer;
        font-family: 'Noto Sans KR', sans-serif;
    }
    ul.tree li .member-name:hover {
        background-color: #e0e0e0;
    }
    /* 하위 노드 숨김 */
    .hidden {
        display: none;
    }
    /* 토글 아이콘 */
    .toggle-icon {
        font-size: 16px;
        margin-right: 5px;
        color: #2196F3;
        cursor: pointer;
        display: inline-block;
        width: 16px;
        text-align: center;
    }
    .toggle-icon::before {
        content: '\25B6'; /* ▶ */
        display: inline-block;
    }
    .toggle-icon.open::before {
        content: '\25BC'; /* ▼ */
    }
    
   
    .rank-label {
        font-size: 0.7em;
        padding: 2px 4px;
        border-radius: 3px;
        margin-left: 5px;
        color: #fff;
    }



       .special-distributor .member-name .rank-label {
       border: 1px solid #ccc;
       color: #fff;
       background-color: #FF5722;
    }

   

    .distributor .member-name .rank-label {
        background-color: #2196F3;
    }
 

     .member .member-name .rank-label {
        background-color: #fff;
        color: #000;
    }

    #member-info {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background-color: #333;
        color: black;
        font-size: 0.9em;
        line-height: 1.4;
        padding: 15px;
        box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        font-family: 'Noto Sans KR', sans-serif;
    }
    #member-info h4 {
        font-size: 1.1em;
        margin-bottom: 10px;
        color: blue;
    }
    #member-info p {
        margin: 5px 0;
    }
    .tree {
        margin-bottom: 200px; /* 하단 여백 추가 */
    }
    /* 모바일 화면에서의 스타일 조정 */
    @media (max-width: 768px) {
        #member-info {
            padding: 10px;
            font-size: 0.8em;
        }
    }
</style>

<div class="container mt-4 mb100 pb100">

    <h5><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?> (ID:<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>)</h5>
    <hr>

       <div class="row">
        <div class="col-md-12 mb-3 text-right">

                    <a href="/org_tree" class="btn btn-sm <?php echo ($_SERVER['REQUEST_URI'] == '/org_tree') ? 'btn-primary' : 'btn-secondary'; ?> ml-2">
                <i class="fas fa-sitemap"></i> 트리형 조직도보기
            </a>
            <a href="/org_chart" class="btn btn-sm <?php echo ($_SERVER['REQUEST_URI'] == '/org_chart') ? 'btn-primary' : 'btn-secondary'; ?> ml-2">
                <i class="fas fa-th-large"></i> 박스형 조직도보기
            </a>

        </div>
    </div>


    <ul class="tree">
        <?php renderTree($treeData); ?>
    </ul>
    <div id="member-info" style="display: none;" class="bg-yellow-b p-3 mt-3"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 트리 토글 기능 추가
    var toggles = document.querySelectorAll('.toggle-icon');
    toggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(event) {
            event.stopPropagation(); // 이벤트 버블링 방지
            var childUl = this.parentNode.querySelector('ul');
            if (childUl) {
                if (childUl.classList.contains('hidden')) {
                    childUl.classList.remove('hidden');
                    this.classList.add('open');
                } else {
                    childUl.classList.add('hidden');
                    this.classList.remove('open');
                }
            }
        });
    });

    // 멤버 정보 표시
    var memberNames = document.querySelectorAll('.member-name');
    memberNames.forEach(function(nameElement) {
        nameElement.addEventListener('click', function() {
            var memberData = JSON.parse(this.getAttribute('data-member'));
            var myQuantity = numberWithCommas(memberData.myQuantity || 0);
            var myAmount = numberWithCommas(memberData.myAmount || 0);
            var myTotal_quantity = numberWithCommas(memberData.myTotal_quantity || 0);
            var myTotal_Amount = numberWithCommas(memberData.myTotal_Amount || 0);
            var commission_total = numberWithCommas(memberData.commission_total || 0);
            var nft_token = numberWithCommas(memberData.nft_token || 0);
            var myAgent = numberWithCommas(memberData.myAgent || 0);
            var myAgent_referral = numberWithCommas(memberData.myAgent_referral || 0);

            var content = "<h4 class='mb-3'>" + htmlspecialchars(memberData.name) +
                          " (ID: " + memberData.id + ", " + htmlspecialchars(memberData.phone) + ")</h4>" +
                          "<p class='mb-1'>• 직급: <strong>" +
                          htmlspecialchars(memberData.rank) + "</strong></p>" +
                    
                          "<p class='mb-1'>• NFT 보유수량: " + nft_token + "</p>" +
                          "<p class='mb-1'>• 개인구매 : (수량: <strong>" + myQuantity + "</strong>개, 금액: <strong>" + myAmount + "</strong>원)</p>" +
                          "<p class='mb-1'>• 본인하위전체 : (수량: <strong>" + myTotal_quantity + "</strong>개, 금액: <strong>" + myTotal_Amount + "</strong>원)</p>" +
                          "<p class='mb-1'>• 수수료 총액: " + commission_total + "원</p>" +
                          "<p class='mb-1'>• 하위총판수: " + myAgent + "명, 직접추천한총판수: " + myAgent_referral + "명</p>";

            var infoDiv = document.getElementById('member-info');
            infoDiv.innerHTML = content;
            infoDiv.style.display = 'block';
        });
    });
});

// 숫자 콤마 추가 함수
function numberWithCommas(x) {
    if (x == null) return '0';
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// 특수문자 이스케이프 함수
function htmlspecialchars(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * 트리 데이터를 HTML로 렌더링하는 함수
 */
function renderTree($nodes, $depth = 0) {
    foreach ($nodes as $node) {
        $hasChildren = isset($node['children']) && !empty($node['children']);
        $rankClass = '';
        switch ($node['rank']) {
            case '총판':
                $rankClass = 'distributor';
                break;
            case '특판':
            case '특판A':
                $rankClass = 'special-distributor';
                break;
            default:
                $rankClass = 'member';
                break;
        }
        ?>
        <li class="<?php echo $rankClass; ?> depth-<?php echo $depth; ?>">
            <?php if ($hasChildren): ?>
                <span class="toggle-icon <?php echo $depth < 2 ? 'open' : ''; ?>"></span>
            <?php else: ?>
                <span class="toggle-icon" style="visibility: hidden;"></span>
            <?php endif; ?>
            <span class="member-name" data-member='<?php echo json_encode($node, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                <?php echo htmlspecialchars($node['name'], ENT_QUOTES, 'UTF-8'); ?>
                <span class="rank-label"><?php echo htmlspecialchars($node['rank'], ENT_QUOTES, 'UTF-8'); ?></span>
            </span>
            <?php if ($hasChildren): ?>
                <ul class="<?php echo $depth < 2 ? '' : 'hidden'; ?>">
                    <?php renderTree($node['children'], $depth + 1); ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }
}
?>