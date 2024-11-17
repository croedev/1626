<?php
// includes/admin_functions.php

function isAdminLoggedIn() {
    return isset($_SESSION['user_email']) && $_SESSION['user_email'] === 'kncalab@gmail.com';
}

function getTotalMembers($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $row = $result->fetch_assoc();
    return $row['total'];
}

function getTotalOrders($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM orders");
    $row = $result->fetch_assoc();
    return $row['total'];
}

function getTotalSales($conn) {
    $result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function getTotalCommissions($conn) {
    $result = $conn->query("SELECT SUM(amount) as total FROM commissions");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

function paginateResults($conn, $query, $page, $perPage) {
    $start = ($page - 1) * $perPage;
    $result = $conn->query($query . " LIMIT $start, $perPage");
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getTotalPages($conn, $query, $perPage) {
    $result = $conn->query($query);
    $totalRows = $result->num_rows;
    return ceil($totalRows / $perPage);
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input));
}

function generateSearchCondition($searchFields, $searchTerm) {
    $conditions = [];
    foreach ($searchFields as $field) {
        $conditions[] = "$field LIKE '%$searchTerm%'";
    }
    return implode(" OR ", $conditions);
}

function generateFilterCondition($filters) {
    $conditions = [];
    foreach ($filters as $field => $value) {
        if ($value !== '') {
            $conditions[] = "$field = '$value'";
        }
    }
    return implode(" AND ", $conditions);
}



function deleteUser($conn, $userId) {
    // 추천인으로 이미 가입된 회원이 있는지 확인
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE referred_by = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $referredCount = $result->fetch_assoc()['count'];
    $stmt->close();

    if ($referredCount > 0) {
        throw new Exception("이 회원을 추천인으로 하는 하위 회원이 있어 삭제할 수 없습니다. 관리자에게 문의하세요.");
    }

    // 구매 주문이 있는지 확인
    $stmt = $conn->prepare("SELECT id FROM orders WHERE user_id = ? AND status = 'completed'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orderIds = [];
    while ($row = $result->fetch_assoc()) {
        $orderIds[] = $row['id'];
    }
    $stmt->close();

    if (!empty($orderIds)) {
        throw new Exception("이 회원은 구매 주문(주문번호: " . implode(", ", $orderIds) . ")이 있어 삭제할 수 없습니다.");
    }

    // 수수료와 직급 계산이 완료된 주문이 있는지 확인
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND paid_status = 'completed'");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $paidCount = $result->fetch_assoc()['count'];
    $stmt->close();

    if ($paidCount > 0) {
        // 비활성화로 상태 변경
        $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
        throw new Exception("이 회원은 수수료와 직급 계산이 완료된 주문이 있어 비활성화 처리되었습니다.");
    }

    // 회원 삭제
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    return $stmt->execute();
}


?>