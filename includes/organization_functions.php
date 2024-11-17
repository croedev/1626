<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');
?>

<?php
require_once __DIR__ . '/../includes/config.php';

/**
 * 조직도 데이터를 가져오는 함수
 */
function getOrganizationData($conn, $user_id) {
    $organizationData = array();
    getOrganizationDataRecursive($conn, $user_id, $organizationData, 0, $user_id);
    return $organizationData;
}

/**
 * 재귀적으로 조직도 데이터를 가져오는 함수
 */
function getOrganizationDataRecursive($conn, $user_id, &$organizationData, $depth, $root_id) {
    // 이미 방문한 노드를 추적하여 무한 루프 방지
    static $visited = array();
    if (in_array($user_id, $visited)) {
        return;
    }
    $visited[] = $user_id;

    $user = getUserInfo($conn, $user_id);
    if ($user === null) {
        error_log("User not found: " . $user_id);
        return;
    }

    // 루트 노드의 referred_by를 null로 설정
    $referred_by = ($user_id === $root_id) ? null : $user['referred_by'];

    // 사용자의 정보에 nft_token 및 rank 추가
    $organizationData[] = array(
        'id' => $user['id'],
        'name' => $user['name'],
        'rank' => $user['rank'],  // 직급 추가
        'myQuantity' => $user['myQuantity'] ?? 0,
        'myTotal_quantity' => $user['myTotal_quantity'] ?? 0,
        'myAmount' => $user['myAmount'] ?? 0,
        'myTotal_Amount' => $user['myTotal_Amount'] ?? 0,
        'commission_total' => $user['commission_total'] ?? 0,
        'nft_token' => $user['nft_token'] ?? 0, // NFT 추가
        'myAgent' => $user['myAgent'] ?? 0,
        'myAgent_referral' => $user['myAgent_referral'] ?? 0,
        'phone' => $user['phone'] ?? '', // 전화번호가 없을 경우 빈 문자열로 설정
        'total_distributor_count' => $user['total_distributor_count'] ?? 0,
        'special_distributor_count' => $user['special_distributor_count'] ?? 0,
        'direct_referrals_count' => $user['direct_referrals_count'] ?? 0,
        'total_referrals_count' => $user['total_referrals_count'] ?? 0,
        'referred_by' => $referred_by,
        'depth' => $depth
    );

    // 직접 추천한 회원들 가져오기
    $stmt = $conn->prepare("SELECT id FROM users WHERE referred_by = ?");
    if ($stmt === false) {
        error_log("getOrganizationDataRecursive: prepare failed: " . $conn->error);
        return;
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("getOrganizationDataRecursive: execute failed: " . $stmt->error);
        return;
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        getOrganizationDataRecursive($conn, $row['id'], $organizationData, $depth + 1, $root_id);
    }
    $stmt->close();
}

/**
 * jsTree용 데이터 변환 함수
 */
function convertToJsTreeData($organizationData) {
    $nodes = array();
    foreach ($organizationData as $user) {
        $nodes[] = array(
            'id' => (string)$user['id'],
            'parent' => $user['referred_by'] ? (string)$user['referred_by'] : '#',
            'text' => $user['name'] . ' (' . $user['rank'] . ')',
            'rank' => $user['rank'],
            'myQuantity' => $user['myQuantity'],
            'myTotal_quantity' => $user['myTotal_quantity'],
            'nft_token' => $user['nft_token'], // NFT 추가
            'phone' => $user['phone'] // 전화번호 추가
        );
    }
    return $nodes;
}
/**
 * OrgChart.js용 데이터 변환 함수
 */
function convertToOrgChartData($organizationData) {
    $nodes = array();
    foreach ($organizationData as $user) {
        $nodes[] = array(
            'id' => $user['id'],
            'pid' => $user['referred_by'] ? $user['referred_by'] : null,
            'name' => $user['name'],
            'rank' => $user['rank'],  // 직급 추가
            'myQuantity' => $user['myQuantity'],
            'myTotal_quantity' => $user['myTotal_quantity'],
            'myAmount' => $user['myAmount'],  // 누적 금액 추가
            'myTotal_Amount' => $user['myTotal_Amount'],  // 하위 누적 금액 추가
            'commission_total' => $user['commission_total'],  // 수수료 총액 추가
            'nft_token' => $user['nft_token'], // NFT 수량 추가
            'phone' => $user['phone'], // 전화번호 추가
            'myAgent' => $user['myAgent'] ?? 0,
            'myAgent_referral' => $user['myAgent_referral'] ?? 0,
            'total_distributor_count' => $user['total_distributor_count'] ?? 0,
            'special_distributor_count' => $user['special_distributor_count'] ?? 0,
            'direct_referrals_count' => $user['direct_referrals_count'] ?? 0,
            'total_referrals_count' => $user['total_referrals_count'] ?? 0
        );
    }
    return $nodes;
}




/**
 * 트리 형태로 데이터 변환 함수
 */
function convertToTreeData($organizationData) {
    $treeData = array();

    // 노드 ID와 노드의 매핑 생성
    $nodeMap = array();
    foreach ($organizationData as $user) {
        $nodeMap[$user['id']] = $user;
        $nodeMap[$user['id']]['children'] = array();  // 'children' 키를 여기서 초기화
    }

    // 트리 구조 생성
    foreach ($organizationData as $user) {
        if ($user['referred_by'] === null) {
            // 루트 노드
            $treeData[] = &$nodeMap[$user['id']];
        } else {
            // 부모 노드에 자식 추가
            if (isset($nodeMap[$user['referred_by']])) {
                $nodeMap[$user['referred_by']]['children'][] = &$nodeMap[$user['id']];
            } else {
                // 부모 노드가 없는 경우 에러 로깅
                error_log("Parent node not found for user ID: " . $user['id']);
            }
        }
    }

    return $treeData;
}




?>
