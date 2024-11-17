<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/organization_functions.php';

// 사용자 인증 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=organization");
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

// 추천인 조직도 데이터 가져오기
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
            'depth' => 0
        ]
    ];
}

$jsTreeData = convertToJsTreeData($organizationData);
$orgChartData = convertToOrgChartData($organizationData);

// 로그로 조직도 데이터 확인
//error_log("Organization Data: " . print_r($orgChartData, true));

$pageTitle = '추천인 조직도';
include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.11/themes/default/style.min.css" />
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.11/jstree.min.js"></script>
<style>
    .orgchart-container {
        overflow: auto;
        margin-bottom: 100px;
    }
    
    #google-orgchart {
        width: 100%;
        margin-bottom: 100px;
    }

    .google-visualization-orgchart-node {
        border: 2px solid #b5d9ea;
        border-radius: 5px;
        box-shadow: rgba(0, 0, 0, 0.5) 3px 3px 3px;
        font-family: 'Noto Serif KR', serif;
        font-size: 0.92em;
        font-weight: bold;
        color: #000;
        padding: 5px;
        text-align: center;
        line-height: 1.2;
    }

    .google-visualization-orgchart-node-selected {
        background-color: blue !important;
        border: 3px solid red !important;
        color: red !important;
        z-index: 99000;
    }

    #member-info {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        max-height: 150px;
        overflow-y: auto;
        background-color: rgba(255, 255, 255, 0.95);
        border-top: 1px solid #ccc;
        padding: 10px;
        color: #333;
        box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
        font-size: 13px;
        line-height: 0.9;
        z-index: 1000;
    }

    #member-info h4 {
        margin-top: 0;
        font-weight: bold;
        color: blue;
        font-size: 14px;
    }

    #member-info p {
        margin: 5px 0;
    }
</style>

<div class="container mt-4">
    <h5><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>)</h5>
    <hr>
    
       <div class="row">
        <div class="col-md-12 mb-3 ">
            <a href="/org_chart" class="btn btn-sm <?php echo ($_SERVER['REQUEST_URI'] == '/organization') ? 'btn-primary' : 'btn-secondary'; ?> ml-2">
                <i class="fas fa-th-large"></i> 박스형 조직도보기
            </a>
            <a href="/org_tree" class="btn btn-sm <?php echo ($_SERVER['REQUEST_URI'] == '/org_tree') ? 'btn-primary' : 'btn-secondary'; ?> ml-2">
                <i class="fas fa-sitemap"></i> 트리형 조직도보기
            </a>
        </div>
    </div>

    <div id="googlechart-container">
        <div id="google-orgchart"></div>
    </div>
    
    <div id="member-info" style="display: none;" class="bg-yellow-b"></div>
</div>

<script type="text/javascript">
    var googleChartVisible = true;

    var orgchartData = <?php echo json_encode($orgChartData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>;

    // 페이지 로드 시 Google Charts 조직도 표시
    $(document).ready(function() {
        drawChart();
    });

    // Google Charts 초기화 함수
    function showGoogleChart() {
        googleChartVisible = !googleChartVisible;
        $('#googlechart-container').toggle(googleChartVisible);
        
        if (googleChartVisible) {
            drawChart();
        }
    }

    // Google Charts 조직도 그리기 함수
    function drawChart() {
        google.charts.load('current', {packages:["orgchart"]});
        google.charts.setOnLoadCallback(function() {
            var data = new google.visualization.DataTable();
            data.addColumn('string', 'Name');
            data.addColumn('string', 'Manager');
            data.addColumn('string', 'ToolTip');

            // 재귀적으로 데이터 추가
            function addNode(node, parent) {
                var nodeId = node.name;
                var parentId = parent ? parent.name : '';
                var tooltip = '직급: ' + node.rank + '\n' +
                              '개인구매: ' + node.myQuantity + '\n' +
                              '하위누적: ' + node.myTotal_quantity;

                var nodeClass = '';
                var rankDisplay = '';
                switch (node.rank) {
                    case '총판':
                        nodeClass = 'distributor';
                        rankDisplay = '<span class="btn btn-xs bg-blue" >총판</span> ';
                        break;
                    case '특판':
                    case '특판A':
                        nodeClass = 'special-distributor';
                        rankDisplay = '<span class="btn btn-xs bg-red" >특판</span> ';
                        break;
                    default:
                        nodeClass = 'member';
                        break;
                }

                var row = [
                    {
                        v: nodeId,
                        f: '<div class="' + nodeClass + '">' + rankDisplay + nodeId + '</div>'
                    },
                    parentId,
                    tooltip
                ];
                data.addRow(row);

                // 자식 노드 추가
                orgchartData.forEach(function(child) {
                    if (child.pid === node.id) {
                        addNode(child, node);
                    }
                });
            }

            // 루트 노드 찾기 및 추가
            var rootNode = orgchartData.find(node => node.pid === null);
            if (rootNode) {
                addNode(rootNode, null);
            }

            // 조직도 옵션 설정
            var options = {
                allowHtml: true,
                size: 'large',
                nodeClass: 'google-visualization-orgchart-node',
                selectedNodeClass: 'google-visualization-orgchart-node-selected'
            };

            // 조직도 그리기
            var chart = new google.visualization.OrgChart(document.getElementById('google-orgchart'));
            chart.draw(data, options);

            // 노드 스타일 적용
            setTimeout(function() {
                var container = document.getElementById('google-orgchart');
                var nodes = container.getElementsByTagName('div');
                for (var i = 0; i < nodes.length; i++) {
                    var node = nodes[i];
                    if (node.className.indexOf('google-visualization-orgchart-node') !== -1) {
                        var innerDiv = node.querySelector('div');
                        if (innerDiv) {
                            node.className += ' ' + innerDiv.className;
                            node.setAttribute('data-has-sales', innerDiv.getAttribute('data-has-sales'));
                            
                            // 직접 스타일 적용
                            if (innerDiv.className.indexOf('member') !== -1) {
                                node.style.setProperty('background-color', '#f9f9f9', 'important');
                                node.style.setProperty('color', '#000', 'important');
                            } else if (innerDiv.className.indexOf('distributor') !== -1) {
                                node.style.setProperty('background-color', '#007bff', 'important');
                                node.style.setProperty('color', 'white', 'important');
                            } else if (innerDiv.className.indexOf('special-distributor') !== -1) {
                                node.style.setProperty('background-color', '#8B0000', 'important');
                                node.style.setProperty('color', 'white', 'important');
                            }

                            if (innerDiv.getAttribute('data-has-sales') === 'true') {
                                node.style.setProperty('border', '3px solid blue', 'important');
                            } else {
                                node.style.setProperty('border', '1px solid black', 'important');
                            }
                        }
                    }
                }
            }, 100);

            // 노드 클릭 이벤트 처리
            google.visualization.events.addListener(chart, 'select', function() {
                var selectedItem = chart.getSelection()[0];
                if (selectedItem) {
                    var nodeName = data.getValue(selectedItem.row, 0);
                    var node = orgchartData.find(node => node.name === nodeName);
                    if (node) {
                        console.log("Selected node data:", node); // 디버깅을 위해 로그 추가
                        var myQuantity = node.myQuantity !== undefined ? numberWithCommas(node.myQuantity) : '0';
                        var myAmount = node.myAmount !== undefined ? numberWithCommas(node.myAmount) : '0';
                        var myTotal_quantity = node.myTotal_quantity !== undefined ? numberWithCommas(node.myTotal_quantity) : '0';
                        var myTotal_Amount = node.myTotal_Amount !== undefined ? numberWithCommas(node.myTotal_Amount) : '0';
                        var myAgent = node.myAgent !== undefined ? numberWithCommas(node.myAgent) : '0';
                        var myAgent_referral = node.myAgent_referral !== undefined ? numberWithCommas(node.myAgent_referral) : '0';
                        var commission_total = node.commission_total !== undefined ? numberWithCommas(node.commission_total) : '0';
                        var nft_token = node.nft_token !== undefined ? numberWithCommas(node.nft_token) : '0';

                        var content = "<h4 class='mb10 border-b1 pb5'>" + htmlspecialchars(node.name) + 
                                      " (ID:" + node.id + ", " + htmlspecialchars(node.phone) + ")</h4>" +
                                      "<p class='notosans mb10'>• 직급: <span class='btn-12 btn-outline text-black'>" + 
                                      htmlspecialchars(node.rank) + "</span></p>" +
                                      "<p class='notosans'>• NFT보유수량: <strong>" + nft_token + "</strong></p>" +
                                      "<p class='notosans'>• 개인구매 : (수량: <strong>" + myQuantity + "</strong>개,   " +
                                      " .  금액 : <strong>" + myAmount + "</strong>원)</p>" +
                                      "<p class='notosans'>• 본인하위전체 : (수량: <strong>" + myTotal_quantity + "</strong>개, 금액 : <strong>" + myTotal_Amount + "</strong>원)</p>" +
                                      "<p class='notosans'>• 수수료 총액: <strong>" + commission_total + "</strong>원</p>" +
                                      "<p class='notosans'>• 하위총판수: <strong>" + myAgent + "</strong>명, 직접추천한총판수: <strong>" + myAgent_referral + "</strong>명</p>";

                        $('#member-info').html(content).show();
                    }
                }
            });
        });
    }

    // 숫자에 세자리 쉼표를 추가하는 함수
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // PHP에서 전달된 데이터를 안전하게 처리하기 위한 함수 (옵션)
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