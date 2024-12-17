remove();

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