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

// 추가 함수들은 필요에 따라 여기에 구현합니다.