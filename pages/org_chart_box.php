<?php
// 오류 보고 설정
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
// 실제 경로로 변경하세요.
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/organization_functions.php';

// 사용자 인증 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=org_chart_box");
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

// D3.js에서 사용하기 위한 데이터 변환
function buildOrgChartData($organizationData) {
    $dataMap = [];
    foreach ($organizationData as $user) {
        $dataMap[$user['id']] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'parentid' => $user['referred_by'],
            'rank' => $user['rank'],
            'phone' => $user['phone'],
            'myQuantity' => $user['myQuantity'],
            'myTotal_quantity' => $user['myTotal_quantity'],
            'myAmount' => $user['myAmount'],
            'myTotal_Amount' => $user['myTotal_Amount'],
            'commission_total' => $user['commission_total'],
            'nft_token' => $user['nft_token'],
            'myAgent' => $user['myAgent'],
            'myAgent_referral' => $user['myAgent_referral'],
            // 기타 필요한 데이터 추가
        ];
    }

    // 트리 구조로 변환
    $treeData = [];
    foreach ($dataMap as $id => &$node) {
        if ($node['parentid'] === null || !isset($dataMap[$node['parentid']])) {
            $treeData[] = &$node;
        } else {
            $parent = &$dataMap[$node['parentid']];
            $parent['children'][] = &$node;
        }
    }

    return $treeData;
}

$orgChartData = buildOrgChartData($organizationData);

// D3.js에 사용할 수 있도록 JSON 인코딩
$orgChartDataJson = json_encode($orgChartData[0], JSON_UNESCAPED_UNICODE);

$pageTitle = '박스형 조직도';
include __DIR__ . '/../includes/header.php';
?>

<style>
/* 조직도 스타일 정의 */
.node {
    cursor: pointer;
}

.node rect {
    stroke: #999;
    stroke-width: 1;
}

.node text {
    font-family: 'Noto Sans KR', sans-serif;
    font-size: 12px;
}

.link {
    fill: none;
    stroke: #999;
    stroke-width: 1px;
}

#member-info {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: #f9f9f9;
    color: #000;
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

/* 모바일 화면에서의 스타일 조정 */
@media (max-width: 768px) {
    #member-info {
        padding: 10px;
        font-size: 0.8em;
    }
}

/* 디자인 요구사항에 따른 추가 스타일 */
.node rect {
    stroke-width: 2;
    stroke: #999;
    rx: 0;
    /* 사각형 */
    ry: 0;
    /* 사각형 */
}

.node text.name {
    font-size: 14px;
    font-weight: bold;
}

.node text.details {
    font-size: 12px;
}

.node text {
    font-family: 'Noto Sans KR', sans-serif;
}

.link {
    stroke: #999;
    stroke-width: 1;
}

/* 메인 컨테이너 스타일 수정 */
.container {
    margin-top: 50px !important;
    /* 상단 여백 조정 */
    padding-bottom: 60px;
    /* 하단 여백 조정 */
    max-width: 100%;
    overflow: hidden;
}

/* 조직도 차트 컨테이너 */
#chart {
    width: 100%;
    height: calc(100vh - 180px);
    /* 상하단 여백 제외한 높이 */
    overflow: hidden;
    position: relative;
}

/* 컨트롤 패널 스타일 */
.control-panel {
    position: fixed;
    top: 50px;
    left: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.8);
    padding: 10px;
    z-index: 100;
}

/* 모바일 대응 */
@media (max-width: 768px) {
    .control-panel {
        padding: 5px;
    }

    .btn-control {
        padding: 5px 10px;
        font-size: 12px;
    }

    #chart {
        height: calc(100vh - 160px);
    }
}

/* 버튼 스타일 통일 */
.btn-control {
    background: linear-gradient(to right, #d4af37, #b8960c);
    border: none;
    color: #000;
    padding: 5px 15px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
}

/* 로그인한 사용자의 노드 강조 스타일 */
.node.current-user rect {
    stroke: #d4af37;
    stroke-width: 3px;
    filter: drop-shadow(0 0 3px rgba(212, 175, 55, 0.5));
}


/* 검색 결과 네비게이션 스타일 */
#search-navigation {
    margin-top: 10px;
    font-size: 14px;
}

#reset-button {
    margin-left: 10px;
}
</style>
<div class="container mt-2 mb-3 p10 my-0 mx-0 bg-gray30"
    style="position: fixed; top: 0; left: 0; right: 0; z-index: 1000; background-color: #f8f9fa;">
    <!-- <h6 class="fs-12"><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?> (ID:<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars($user['phone'], ENT_QUOTES, 'UTF-8'); ?>)</h6>
    <hr> -->

    <div class="row mb-1">
        <div class="col-md-12 text-right">
            <a href="/org_tree"
                class="notosans btn btn-sm fs-11 <?php echo ($_SERVER['REQUEST_URI'] == '/org_tree') ? 'btn-primary' : 'btn-secondary'; ?>">
                트리형 조직도
            </a>
            <a href="/org_chart"
                class="notosans btn btn-sm fs-11 <?php echo ($_SERVER['REQUEST_URI'] == '/org_chart') ? 'btn-primary' : 'btn-secondary'; ?> ml-1">
                박스형 조직도
            </a>
            <button type="button" class="btn btn-sm btn-warning w80 ml-1 fs-11" id="reset-button">원래위치</button>
        </div>
    </div>

    <!-- 검색 폼 -->
    <form id="search-form" class="mb-1" style="max-width: 100%px;">
        <div class="form-group mb-1 flex-x-end">
            <label for="max-depth" class="mr-1 fs-11">레벨:</label>
            <div class="input-group" style="width:40%; display: inline-flex;">
                <button type="button" class="btn btn-sm  btn-secondary fs-15" id="decrease-depth">-</button>
                <input type="number" class="form-control form-control-sm text-center fs-11" id="max-depth"
                    name="max_depth" min="1" value="5" style="width: 30px;">
                <button type="button" class="btn btn-sm btn-secondary fs-15" id="increase-depth">+</button>
            </div>
            <label for="search-name" class="ml-2 mr-1 fs-11"></label>
            <input type="text" class="form-control-sm w100 d-inline-block fs-11" id="search-name" name="search_name"
                placeholder="회원명 입력" style="width: 100px;">
            <button type="submit" class="btn btn-sm btn-primary w80 fs-11">검색하기</button>
        </div>
        <div class="form-group d-flex justify-content-end">


            <span id="result-count" class="ml-1 fs-11"></span>
        </div>
    </form>

    <!-- 검색 결과 네비게이션 -->
    <div id="search-navigation" style="display: none;">
        <button id="prev-result" class="btn btn-sm btn-secondary">&lt;</button>
        <span id="result-count"></span>
        <button id="next-result" class="btn btn-sm btn-secondary">&gt;</button>
    </div>
</div>

<div class="container mt-4 mb-5">
    <!-- 조직도 컨테이너 -->
    <div id="chart"></div>
    <div id="member-info" style="display: none;" class="bg-yellow-b p-3 mt-3"></div>
</div>

<!-- D3.js 및 필요한 스크립트 -->
<script src="https://d3js.org/d3.v5.min.js"></script>

<script>
var orgChartData = <?php echo $orgChartDataJson; ?>;

// 초기 변수 설정
var margin = {
        top: 20,
        right: 20,
        bottom: 20,
        left: 20
    },
    width = 2000 - margin.right - margin.left, // 고정된 너비
    height = 2000 - margin.top - margin.bottom; // 고정된 높이

var i = 0,
    duration = 750,
    root;

// 노드 크기 설정
var nodeWidth = 120;
var nodeHeight = 60;
var horizontalSpacing = 20;
var verticalSpacing = 50;

var treemap = d3.tree()
    .nodeSize([nodeWidth + horizontalSpacing, (nodeHeight + verticalSpacing) / 1]) // 세로 간격을 1/4로 줄임
    .separation(function(a, b) {
        return a.parent == b.parent ? 1 : 1.5;
    });

var zoom = d3.zoom().scaleExtent([0.1, 3]).on("zoom", zoomed);

var svg = d3.select("#chart").append("svg")
    .attr("width", width + margin.right + margin.left)
    .attr("height", height + margin.top + margin.bottom)
    .call(zoom)
    .append("g")
    .attr("transform", "translate(" + width / 2 + "," + margin.top + ")");

function zoomed() {
    svg.attr("transform", d3.event.transform);
}

root = d3.hierarchy(orgChartData, function(d) {
    return d.children;
});
root.x0 = 0;
root.y0 = 0;

// 초기 depth 설정 (검색 폼에서 가져올 수 있음)
var initialDepth = 5; // 기본값 5

// 초기 트리 설정
collapseToLevel(root, initialDepth);

update(root);
centerNode(root);

function update(source) {

    var treeData = treemap(root);

    var nodes = treeData.descendants(),
        links = treeData.links();

    // 노드 위치 조정
    nodes.forEach(function(d) {
        d.x = d.x;
        d.y = d.y;
    });

    var node = svg.selectAll('g.node')
        .data(nodes, function(d) {
            return d.id || (d.id = ++i);
        });

    var nodeEnter = node.enter().append('g')
        .attr('class', 'node')
        .attr("transform", function(d) {
            return "translate(" + source.x0 + "," + source.y0 + ")";
        })
        .on('click', click);

    // 노드 디자인 수정
    nodeEnter.append('rect')
        .attr('width', nodeWidth)
        .attr('height', nodeHeight)
        .attr('x', -nodeWidth / 2)
        .attr('y', -nodeHeight / 2)
        .attr('rx', 0)
        .attr('ry', 0)
        .style('fill', function(d) {
            if (d.searched) {
                return 'lightgreen';
            } else {
                return getRankColor(d.data.rank).bg;
            }
        })
        .style('stroke', function(d) {
            if (d.searched || d.selected) {
                return 'red';
            } else {
                return '#999';
            }
        })
        .style('stroke-width', function(d) {
            if (d.searched || d.selected) {
                return '3px';
            } else {
                return '1px';
            }
        });

    nodeEnter.append('text')
        .attr('class', 'name')
        .attr('dy', '-0.5em')
        .attr('text-anchor', 'middle')
        .text(function(d) {
            return d.data.name;
        })
        .style('fill', function(d) {
            return getRankColor(d.data.rank).text;
        });

    nodeEnter.append('text')
        .attr('class', 'details')
        .attr('dy', '0.8em')
        .attr('text-anchor', 'middle')
        .text(function(d) {
            return "ID: " + d.data.id + " (" + d.data.rank + ")";
        })
        .style('fill', function(d) {
            return getRankColor(d.data.rank).text;
        });

    var nodeUpdate = nodeEnter.merge(node);

    nodeUpdate.transition()
        .duration(duration)
        .attr("transform", function(d) {
            return "translate(" + d.x + "," + d.y + ")";
        });

    nodeUpdate.select('rect')
        .style('fill', function(d) {
            if (d.searched) {
                return 'lightgreen';
            } else {
                return getRankColor(d.data.rank).bg;
            }
        })
        .style('stroke', function(d) {
            if (d.searched || d.selected) {
                return 'red';
            } else {
                return '#999';
            }
        })
        .style('stroke-width', function(d) {
            if (d.searched || d.selected) {
                return '3px';
            } else {
                return '1px';
            }
        });

    var nodeExit = node.exit().transition()
        .duration(duration)
        .attr("transform", function(d) {
            return "translate(" + source.x + "," + source.y + ")";
        })
        .remove();

    // 링크 설정
    var link = svg.selectAll('path.link')
        .data(links, function(d) {
            return d.target.id;
        });

    // 연결선 디자인 수정
    var linkEnter = link.enter().insert('path', "g")
        .attr("class", "link")
        .attr('d', function(d) {
            var o = {
                x: source.x0,
                y: source.y0
            };
            return connectorLine(o, o);
        });

    var linkUpdate = linkEnter.merge(link);

    linkUpdate.transition()
        .duration(duration)
        .attr('d', function(d) {
            return connectorLine(d.source, d.target);
        });

    var linkExit = link.exit().transition()
        .duration(duration)
        .attr('d', function(d) {
            var o = {
                x: source.x,
                y: source.y
            };
            return connectorLine(o, o);
        })
        .remove();

    nodes.forEach(function(d) {
        d.x0 = d.x;
        d.y0 = d.y;
    });

    // 특정 노드를 클릭할 때 하단바에 정보 표시
    function click(d) {
        d3.event.stopPropagation(); // 클릭 이벤트 전파 방지

        var content = "<h4>" + d.data.name + " (ID: " + d.data.id + ")</h4>";
        content += "<p>직급: " + d.data.rank + "</p>";
        content += "<p>NFT 보유수량: " + numberWithCommas(d.data.nft_token || 0) + "</p>";
        content += "<p>개인구매: (수량: " + numberWithCommas(d.data.myQuantity || 0) + "개, 금액: " + numberWithCommas(d.data
            .myAmount || 0) + "원)</p>";
        content += "<p>본인하위전체: (수량: " + numberWithCommas(d.data.myTotal_quantity || 0) + "개, 금액: " + numberWithCommas(d
            .data.myTotal_Amount || 0) + "원)</p>";
        content += "<p>수수료 총액: " + numberWithCommas(d.data.commission_total || 0) + "원</p>";
        content += "<p>하위총판수: " + numberWithCommas(d.data.myAgent || 0) + "명, 직접추천한총판수: " + numberWithCommas(d.data
            .myAgent_referral || 0) + "명</p>";

        var infoDiv = document.getElementById('member-info');
        infoDiv.innerHTML = content;
        infoDiv.style.display = 'block';
    }

    // SVG 배경이나 하단바 자체를 클릭할 때 정보 창을 닫기
    d3.select("#chart svg").on("click", function() {
        closeInfoDiv();
    });

    document.getElementById('member-info').addEventListener('click', closeInfoDiv);

    function closeInfoDiv() {
        var infoDiv = document.getElementById('member-info');
        infoDiv.style.display = 'none';
    }

    // 숫자 콤마 추가 함수
    function numberWithCommas(x) {
        if (x == null) return '0';
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }



    // SVG 배경 클릭 시 정보창 닫기 및 선택 해제
    d3.select("#chart svg").on("click", function() {
        var infoDiv = document.getElementById('member-info');
        infoDiv.style.display = 'none';

        if (selectedNode) {
            selectedNode.selected = false;
            selectedNode = null;
            update(root);
        }
    });
}

function connectorLine(s, d) {
    return "M" + s.x + "," + s.y +
        "V" + (s.y + (d.y - s.y) / 2) +
        "H" + d.x +
        "V" + d.y;
}


// 랭크에 따른 색상 함수
function getRankColor(rank) {
    var colorMap = {
        '회원': {
            bg: '#ffff66',
            text: '#000'
        }, // 옅은 노란색 배경, 검은색 글자
        '총판': {
            bg: '#0a41c3',
            text: '#fff'
        }, // 금색 배경, 흰색 글자
        '특판': {
            bg: '#bf5d07',
            text: '#fff'
        }, // 주황색 배경, 흰색 글자
        '특판A': {
            bg: '#ff0000',
            text: '#fff'
        }, // 빨간색 배경, 흰색 글자
        'default': {
            bg: '#fff',
            text: '#000'
        } // 기본 흰색 배경, 검은색 글자
    };
    return colorMap[rank] || colorMap['default'];
}

// 검색 결과 관련 변수
var searchResults = [];
var currentResultIndex = 0;
var selectedNode = null;

// 검색 폼 제출 처리
document.getElementById('search-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var maxDepthInput = document.getElementById('max-depth').value;
    var searchName = document.getElementById('search-name').value.trim().toLowerCase();

    initialDepth = parseInt(maxDepthInput) || 5;

    // 레벨에 따라 트리 구성
    collapseToLevel(root, initialDepth);

    // 이전 검색 결과 초기화
    clearSearch(root);

    searchResults = [];

    function searchTree(d) {
        var nameMatch = d.data.name.toLowerCase() === searchName;
        var match = nameMatch;

        if (nameMatch) {
            searchResults.push(d);
            d.searched = true;
        }

        if (d.children)
            d.children.forEach(function(child) {
                if (searchTree(child)) match = true;
            });
        else if (d._children)
            d._children.forEach(function(child) {
                if (searchTree(child)) match = true;
            });

        if (match) {
            if (d._children) {
                d.children = d._children;
                d._children = null;
            }
        } else {
            if (d.children) {
                d._children = d.children;
                d.children = null;
            }
        }
        return match;
    }

    if (searchName !== "") {
        searchTree(root);
        if (searchResults.length > 0) {
            currentResultIndex = 0;
            expandToNode(searchResults[currentResultIndex]);
            centerNode(searchResults[currentResultIndex]);
            update(root);
            showSearchNavigation();
        } else {
            alert('검색 결과가 없습니다.');
            update(root);
            hideSearchNavigation();
        }
    } else {
        update(root);
        hideSearchNavigation();
    }
});

// 레벨 증가/감소 버튼 처리
document.getElementById('increase-depth').addEventListener('click', function() {
    var depthInput = document.getElementById('max-depth');
    depthInput.value = parseInt(depthInput.value) + 1;
});

document.getElementById('decrease-depth').addEventListener('click', function() {
    var depthInput = document.getElementById('max-depth');
    if (parseInt(depthInput.value) > 1) {
        depthInput.value = parseInt(depthInput.value) - 1;
    }
});

// 검색 결과 네비게이션 버튼 처리
document.getElementById('prev-result').addEventListener('click', function() {
    if (currentResultIndex > 0) {
        currentResultIndex--;
        expandToNode(searchResults[currentResultIndex]);
        centerNode(searchResults[currentResultIndex]);
        update(root);
        updateSearchNavigation();
    }
});

document.getElementById('next-result').addEventListener('click', function() {
    if (currentResultIndex < searchResults.length - 1) {
        currentResultIndex++;
        expandToNode(searchResults[currentResultIndex]);
        centerNode(searchResults[currentResultIndex]);
        update(root);
        updateSearchNavigation();
    }
});

function showSearchNavigation() {
    document.getElementById('search-navigation').style.display = 'block';
    updateSearchNavigation();
}

function hideSearchNavigation() {
    document.getElementById('search-navigation').style.display = 'none';
}

function updateSearchNavigation() {
    document.getElementById('result-count').innerText = '총 ' + searchResults.length + '명 [' + (currentResultIndex + 1) +
        '/' + searchResults.length + ']';
}





// 로그인한 사용자 ID로 노드를 찾는 함수
function findNodeById(root, userId) {
    if (root.data.id === userId) return root;
    if (root.children) {
        for (let i = 0; i < root.children.length; i++) {
            const found = findNodeById(root.children[i], userId);
            if (found) return found;
        }
    }
    return null;
}

// 초기 사용자 노드 설정
const userNode = findNodeById(root, <?php echo json_encode($user_id); ?>);

if (!userNode) {
    console.error("사용자 노드를 찾을 수 없습니다.");
}

// 모바일 화면 크기를 감지하여 초기 줌 레벨과 위치 설정
function isMobileDevice() {
    return window.innerWidth <= 768;
}


// 로그인한 사용자의 ID로 노드를 찾는 함수
function findUserNode(root, userId) {
    if (root.data.id === userId) return root;
    if (root.children) {
        for (let child of root.children) {
            const found = findUserNode(child, userId);
            if (found) return found;
        }
    }
    if (root._children) {
        for (let child of root._children) {
            const found = findUserNode(child, userId);
            if (found) return found;
        }
    }
    return null;
}

// 중앙 정렬 함수 수정
function centerNode(source) {
    // 로그인한 사용자의 노드 찾기
    const userNode = findUserNode(root, <?php echo $user_id; ?>);
    const nodeToCenter = userNode || source;

    const scale = isMobileDevice() ? 0.6 : 0.8;
    const containerWidth = document.getElementById('chart').offsetWidth;
    const containerHeight = document.getElementById('chart').offsetHeight;

    // 사용자 노드를 화면 중앙에 위치시키기
    const x = -nodeToCenter.x * scale + containerWidth / 2;
    const y = -nodeToCenter.y * scale + containerHeight / 3; // 상단에서 1/3 지점

    d3.select("#chart svg")
        .transition()
        .duration(750)
        .call(zoom.transform, d3.zoomIdentity
            .translate(x, y)
            .scale(scale));
}

// 원래 위치로 돌아가는 버튼 이벤트 수정
document.getElementById('reset-button').addEventListener('click', function() {
    const userNode = findUserNode(root, <?php echo $user_id; ?>);
    if (userNode) {
        centerNode(userNode);
    }
    collapseToLevel(root, initialDepth);
    update(root);
});

// 초기 로드 시 사용자 노드 중심으로 위치
document.addEventListener('DOMContentLoaded', function() {
    const userNode = findUserNode(root, <?php echo $user_id; ?>);
    if (userNode) {
        centerNode(userNode);
    }
});




function clearSearch(d) {
    if (d.searched) {
        d.searched = false;
    }
    if (d.children) {
        d.children.forEach(clearSearch);
    }
    if (d._children) {
        d._children.forEach(clearSearch);
    }
}

function expandToNode(d) {
    if (d.parent) {
        d.parent.children = d.parent.children || d.parent._children;
        d.parent._children = null;
        expandToNode(d.parent);
    }
}

function collapseToLevel(d, level) {
    if (d.depth >= level) {
        if (d.children) {
            d._children = d.children;
            d._children.forEach(function(child) {
                collapseToLevel(child, level);
            });
            d.children = null;
        }
    } else if (d.children) {
        d.children.forEach(function(child) {
            collapseToLevel(child, level);
        });
    } else if (d._children) {
        d._children.forEach(function(child) {
            collapseToLevel(child, level);
        });
    }
}

// 원래위치 버튼 클릭 시
document.getElementById('reset-button').addEventListener('click', function() {
    resetZoom();
    collapseToLevel(root, initialDepth);
    update(root);
});



// "원래 위치" 버튼 클릭 시 로그인한 사용자 노드를 화면 중앙 상단에 위치
function resetZoom() {
    if (userNode) {
        centerNode(userNode);
    } else {
        console.error("사용자 노드를 찾을 수 없습니다.");
    }
}



// 초기 로드시 로그인한 사용자 노드를 화면 중앙 상단에 위치시키기
document.addEventListener("DOMContentLoaded", function() {
    if (userNode) {
        centerNode(userNode);
    } else {
        console.error("사용자 노드를 찾을 수 없습니다.");
    }
});

// 검색 후 검색된 노드를 중앙 상단에 위치시키기
function searchAndCenterNode(searchNode) {
    if (searchNode) {
        centerNode(searchNode);
    } else {
        console.error("검색된 노드를 찾을 수 없습니다.");
    }
}

// 모바일 화면 크기를 감지하여 초기 줌 레벨과 위치 설정
function isMobileDevice() {
    return window.innerWidth <= 768;
}


// 초기 로드시 사용자의 노드를 화면 중심으로 설정
document.addEventListener("DOMContentLoaded", function() {
    centerNode(root); // 초기 로드 시 사용자의 노드를 검색 바 아래에 위치하도록 설정
});


nodeEnter.classed('current-user', d => d.data.id === <?php echo $user_id; ?>);

// SVG 배경 클릭 시 정보창 닫기
d3.select("#chart svg").on("click", function() {
    var infoDiv = document.getElementById('member-info');
    infoDiv.style.display = 'none';

    if (selectedNode) {
        selectedNode.selected = false;
        selectedNode = null;
        update(root);
    }
});



// 검색 폼 이벤트 리스너
const searchForm = document.getElementById('search-form');
searchForm.addEventListener('submit', handleSearchSubmit);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>