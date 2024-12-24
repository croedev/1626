<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');

session_start();
require_once '../includes/config.php';
require_once './fnc_address.php';

// 관리자 권한 체크
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [0, 1, 3, 20])) {
//     // 로그인되지 않은 경우 로그인 페이지로 리다이렉트
//     if (!isset($_SESSION['user_id'])) {
//         header("Location: /login?redirect=/admin");
//         exit();
//     }

//     // 관리자 권한이 없는 경우
//     echo "<script>
//         alert('관리자 권한이 없습니다.');
//         window.location.href = '/';
//     </script>";
//     exit();
// }


$conn = db_connect();

// 검색 파라미터
$search_name = $_GET['search_name'] ?? '';
$search_id = $_GET['search_id'] ?? '';
$has_address = $_GET['has_address'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// WHERE 조건 생성
$where_clauses = [];
$params = [];
$param_types = '';

if ($search_name) {
    $where_clauses[] = "name LIKE ?";
    $params[] = "%$search_name%";
    $param_types .= 's';
}

if ($search_id) {
    $where_clauses[] = "id = ?";
    $params[] = $search_id;
    $param_types .= 'i';
}

if ($has_address !== '') {
    if ($has_address === '1') {
        $where_clauses[] = "erc_address IS NOT NULL";
    } else {
        $where_clauses[] = "erc_address IS NULL";
    }
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// 총 레코드 수 조회
$count_sql = "SELECT COUNT(*) as cnt FROM users $where_sql";
if (!empty($params)) {
    $stmt = $conn->prepare($count_sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $total_count = $stmt->get_result()->fetch_assoc()['cnt'];
} else {
    $total_count = $conn->query($count_sql)->fetch_assoc()['cnt'];
}

$total_pages = ceil($total_count / $per_page);

// 선택된 ID들의 주소 생성 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $generator = new ErcAddressGenerator();
        $success_count = 0;
        $error_count = 0;

        if ($_POST['action'] === 'generate_selected' && isset($_POST['selected_ids'])) {
            foreach ($_POST['selected_ids'] as $userId) {
                $result = $generator->generateAndSaveAddress($userId);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        } elseif ($_POST['action'] === 'generate_all') {
            $null_address_sql = "SELECT id FROM users WHERE erc_address IS NULL";
            $null_results = $conn->query($null_address_sql);
            
            while ($user = $null_results->fetch_assoc()) {
                $result = $generator->generateAndSaveAddress($user['id']);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        $_SESSION['message'] = "처리완료: 성공 $success_count, 실패 $error_count";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// 데이터 조회
$sql = "
    SELECT 
        id, name, erc_address, private_key, decrypt_key,
        nft_token, created_at
    FROM users 
    $where_sql 
    ORDER BY id DESC 
    LIMIT ? OFFSET ?
";

$param_types .= 'ii';
$params[] = $per_page;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

include 'admin_header.php';
?>

<div class="container-fluid" style="padding:20px; background: #1a1a1a; color: #fff;">
    <h2 class="text-gold">ERC 주소 관리</h2>

    <!-- 검색 폼 -->
    <div class="search-form" style="background: #2d2d2d; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search_name" class="form-control bg-dark " style="color: #fff!important;" placeholder="이름 검색"
                    value="<?php echo htmlspecialchars($search_name); ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="search_id" class="form-control bg-dark text-light" placeholder="회원 ID"
                    value="<?php echo htmlspecialchars($search_id); ?>">
            </div>
            <div class="col-md-3">
                <select name="has_address" class="form-select bg-dark text-light">
                    <option value="">주소 생성 여부</option>
                    <option value="1" <?php echo $has_address === '1' ? 'selected' : ''; ?>>생성됨</option>
                    <option value="0" <?php echo $has_address === '0' ? 'selected' : ''; ?>>미생성</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-gold">검색</button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">초기화</a>
            </div>
        </form>
    </div>

    <!-- 통계 및 일괄 생성 버튼 -->
    <div class="stats-section" style="background: #2d2d2d; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <div class="row">
            <div class="col-md-8">
                <p class="text-gold">
                    총 회원수: <?php echo number_format($total_count); ?>명 |
                    주소 미생성: <span id="need-address-count"><?php 
            $need_address = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE erc_address IS NULL")->fetch_assoc()['cnt'];
            echo number_format($need_address);
        ?></span>명
                </p>
            </div>
            <div class="col-md-4 text-end">
                <form id="bulkActionForm" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="">
                    <input type="hidden" name="selected_ids[]" value="">
                    <button type="button" class="btn btn-gold" onclick="generateSelected()">선택 생성</button>
                    <button type="button" class="btn btn-gold" onclick="generateAll()">전체 생성</button>
                </form>
            </div>
        </div>

        <!-- 메시지 표시 -->
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-info">
            <?php 
                echo $_SESSION['message']; 
                unset($_SESSION['message']); 
            ?>
        </div>
        <?php endif; ?>

        <!-- 데이터 테이블 -->
        <div class="table-responsive">
            <table class="table table-dark table-striped">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th>
                        <th>번호</th>
                        <th>회원ID</th>
                        <th>성명</th>
                        <th>ERC 주소</th>
                        <th>개인키(암호화)</th>
                        <th>복호화상태</th>
                        <th>보유NFT</th>
                        <th>생성일</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php if (!$row['erc_address']): ?>
                            <input type="checkbox" class="user-checkbox" value="<?php echo $row['id']; ?>">
                            <?php endif; ?>
                        </td>
                        <td><?php echo $offset++; ?></td>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis;">
                            <?php echo $row['erc_address'] ?: '<span class="text-warning">미생성</span>'; ?>
                        </td>
                        <td style="max-width:150px; overflow:hidden; text-overflow:ellipsis;">
                            <?php echo $row['private_key'] ? '****' : '-'; ?>
                        </td>
                        <td>
                            <?php 
                        if ($row['decrypt_key']) {
                            echo '<span class="text-success">완료</span>';
                        } else {
                            echo $row['erc_address'] ? '<span class="text-warning">대기</span>' : '-';
                        }
                        ?>
                        </td>
                        <td><?php echo number_format($row['nft_token']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
<td>
    <?php if (!$row['erc_address']): ?>
        <button class="btn btn-sm btn-gold" onclick="generateAddress(<?php echo $row['id']; ?>)">
            주소생성
        </button>
    <?php elseif (empty($row['decrypt_key'])): ?>
        <button class="btn btn-sm btn-warning" onclick="regenerateDecryptKey(<?php echo $row['id']; ?>)">
            복호화키
        </button>
    <?php endif; ?>
</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- 페이징 -->
        <div class="pagination justify-content-center" style="margin-top:20px;">
            <?php
        $start_page = max(1, min($page - 4, $total_pages - 9));
        $end_page = min($total_pages, $start_page + 9);
        
        // 이전 버튼
        if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                class="btn btn-sm btn-dark mx-1">이전</a>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                class="btn btn-sm <?php echo $i == $page ? 'btn-gold' : 'btn-dark'; ?> mx-1">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                class="btn btn-sm btn-dark mx-1">다음</a>
            <?php endif; ?>
        </div>
    </div>



    <script>
    function toggleAll(source) {
        const checkboxes = document.getElementsByClassName('user-checkbox');
        for (let checkbox of checkboxes) {
            checkbox.checked = source.checked;
        }
    }

    function generateSelected() {
        const checkboxes = document.getElementsByClassName('user-checkbox');
        const selectedIds = [];

        for (let checkbox of checkboxes) {
            if (checkbox.checked) {
                selectedIds.push(checkbox.value);
            }
        }

        if (selectedIds.length === 0) {
            alert('생성할 주소를 선택해주세요.');
            return;
        }

        if (!confirm(`선택한 ${selectedIds.length}개의 주소를 생성하시겠습니까?`)) {
            return;
        }

        // fetch API로 변경
        fetch('../pages/erc_address.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_ids: selectedIds // 복수의 ID 전송
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('선택한 주소가 생성되었습니다.');
                    location.reload();
                } else {
                    alert('오류: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('처리 중 오류가 발생했습니다.');
            });
    }

function generateAll() {
    // Fetch를 통해 실제 DB의 미생성 주소 수를 체크
    fetch('../pages/erc_address.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'check_pending'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.count > 0) {
            if (confirm(`미생성된 주소 ${data.count}개를 일괄 생성하시겠습니까?\n시간이 다소 소요될 수 있습니다.`)) {
                return fetch('../pages/erc_address.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'generate_all'
                    })
                });
            }
        } else {
            alert('생성할 주소가 없습니다.');
            return null;
        }
    })
    .then(response => {
        if (response) return response.json();
    })
    .then(data => {
        if (data && data.success) {
            alert(data.message);
            location.reload();
        } else if (data) {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('처리 중 오류가 발생했습니다.');
    });
}

    function generateAddress(userId) {
        if (!confirm('해당 회원의 ERC 주소를 생성하시겠습니까?')) return;

        fetch('../pages/erc_address.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('주소가 생성되었습니다.');
                    location.reload();
                } else {
                    alert('오류: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('처리 중 오류가 발생했습니다.');
            });
    }
    </script>


<script>
function regenerateDecryptKey(userId) {
    if (!confirm('해당 회원의 복호화키를 생성하시겠습니까?')) return;
    
    fetch('../pages/erc_address.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'regenerate_decrypt_key',
            user_id: userId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('복호화키가 생성되었습니다.');
            location.reload();
        } else {
            alert('오류: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('처리 중 오류가 발생했습니다.');
    });
}
</script>

    <style>
    .btn-gold {
        background: linear-gradient(145deg, #d4af37, #aa8c2c);
        border: none;
        color: #000;
        margin: 0 5px;
    }

    .text-gold {
        color: #d4af37;
    }

    .form-control,
    .form-select {
        border: 1px solid #d4af37;
        color: #fff!important;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 5px;
    }

    .pagination a {
        padding: 5px 10px;
        text-decoration: none;
    }

    .user-checkbox {
        width: 18px;
        height: 18px;
    }
    </style>

    <?php 
$conn->close();
include '../includes/footer.php';