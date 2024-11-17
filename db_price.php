<?php
// 데이터베이스 연결
include 'includes/config.php';  // config.php 파일에 데이터베이스 연결 설정이 포함되어 있다고 가정합니다.

$initial_price = 1000;      // 첫 번째 구간 시작 가격
$second_tier_start = 10100; // 두 번째 구간 시작 가격
$total_tiers = 410;         // 구간 수
$first_tier_quantity = 100000;  // 첫 번째 구간 수량
$second_tier_quantity = 10000;  // 두 번째 구간 이후의 각 구간 수량
$price_increment = 1000;    // 첫 번째 구간은 1000원씩 가격 증가
$second_price_increment = 100;  // 두 번째 구간 이후에는 100원씩 증가

$db=db_connect();

// 데이터베이스에 구간 데이터를 삽입하는 함수
function insertPricingTier($db, $tier_level, $price, $total_quantity) {

    $remaining_quantity = $total_quantity;  // 초기 수량은 전체 수량과 동일
    $query = "INSERT INTO pricing_tiers (tier_level, price, total_quantity, remaining_quantity)
              VALUES ($tier_level, $price, $total_quantity, $remaining_quantity)";
    $db->query($query);
}

// 첫 번째 구간 설정
for ($tier = 1; $tier <= 10; $tier++) {
    $price = $initial_price + (($tier - 1) * $price_increment);  // 첫 번째 구간 가격은 1000원씩 증가
    insertPricingTier($db, $tier, $price, $first_tier_quantity);
}

// 두 번째 구간 이후 설정
for ($tier = 11; $tier <= $total_tiers; $tier++) {
    $price = $second_tier_start + (($tier - 11) * $second_price_increment);  // 두 번째 구간부터는 100원씩 증가
    insertPricingTier($db, $tier, $price, $second_tier_quantity);
}

echo "구간별 가격과 수량이 성공적으로 삽입되었습니다.";
?>
