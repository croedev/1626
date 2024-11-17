<?php
// 데이터베이스 연결 설정
$servername = "localhost";
$username = "lidyahkc_0";
$password = "lidya2016$";
$database = "lidyahkc_1626";


// 사용자가 확인 버튼을 눌렀는지 확인
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute']) && $_POST['execute'] === 'yes') {
    $conn = new mysqli($servername, $username, $password, $database);

    // 연결 확인
    if ($conn->connect_error) {
        die("데이터베이스 연결 실패: " . $conn->connect_error);
    }

    echo "데이터베이스 연결 성공<br>";

    // 주문 번호 리스트
    $order_ids = [20]; // 테스트를 위해 order_id = 20만 처리


// 주문 번호 리스트
// $order_ids = [50, 271, 31, 36, 49, 34, 38, 115, 75, 35, 112, 40, 113, 47, 59, 48, 
//             52, 114, 41, 107, 110, 60, 37, 105, 67, 43, 71, 18, 32, 58, 109, 97, 33, 
//             104, 147, 57, 70, 46, 80, 111, 146, 338, 359, 360, 362, 363, 364, 366, 
//             368, 372, 373, 381, 384, 390, 391, 392, 422, 426, 465, 466, 449, 451, 
//             474, 475, 476, 477, 497, 481, 503, 528, 567, 585, 580, 586, 587, 624, 
//             629, 630, 631, 634, 609, 615, 663, 684, 686, 687, 688, 689, 746, 670, 
//             751, 752, 753, 695, 698, 701, 703, 704, 707, 706, 708, 710, 711, 718, 
//             722, 724, 725, 728, 729, 730, 731, 735, 736, 738, 739, 778, 813, 819, 
//             820, 821, 822, 824, 839, 851, 873, 885, 913, 915, 928, 929, 1155, 1169, 
//             1198, 1199, 1200, 1201, 1214, 1216, 1232, 1233, 1234, 1235, 1236, 1237, 
//             1238, 1273, 1278, 1279, 1335, 1340, 1360, 1362, 1390, 1407, 1439, 1453, 
//             1457, 1458, 1459, 1460, 1461, 1463, 1498, 1505, 1516, 1517, 1518, 1529, 
//             1530, 1573, 1574, 1579, 1580, 1581, 1582, 1584, 1587, 1591, 1603, 1604, 
//             1641, 1666, 1668];



foreach ($order_ids as $order_id) {
    echo "주문 번호 처리 중: $order_id\n";

    // commissions 테이블에서 해당 order_id에 대한 레코드 가져오기
    $sql = "SELECT id, user_id, amount, cash_point_amount, mileage_point_amount 
            FROM commissions 
            WHERE order_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "commissions 테이블에서 수수료 데이터 검색 성공: $order_id\n";
        while ($row = $result->fetch_assoc()) {
            $commission_id = $row['id'];
            $user_id = $row['user_id'];
            $amount = $row['amount'];
            $cash_point = $row['cash_point_amount'];
            $mileage_point = $row['mileage_point_amount'];

            echo "수수료 처리 중: commission ID = $commission_id, 사용자 ID = $user_id\n";

            // commissions 테이블에 음수 레코드 삽입
            $insert_sql = "INSERT INTO commissions (user_id, commission_type, amount, cash_point_amount, mileage_point_amount, 
                            commission_rate, order_id, minus_from, source_user_id, source_amount, created_at) 
                           SELECT user_id, commission_type, -amount, -cash_point_amount, -mileage_point_amount, 
                                  commission_rate, order_id, id, source_user_id, source_amount, NOW()
                           FROM commissions 
                           WHERE id = ?";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("i", $commission_id);
            if ($insert_stmt->execute()) {
                echo "음수 수수료 레코드 삽입 완료: commission ID = $commission_id\n";
            } else {
                echo "음수 수수료 레코드 삽입 실패: commission ID = $commission_id. 오류: " . $conn->error . "\n";
            }

            // users 테이블 업데이트
            $update_sql = "UPDATE users 
                           SET commission_total = commission_total - ?, 
                               cash_points = cash_points - ?, 
                               mileage_points = mileage_points - ? 
                           WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("dddi", $amount, $cash_point, $mileage_point, $user_id);
            if ($update_stmt->execute()) {
                echo "사용자 업데이트 완료: 사용자 ID = $user_id\n";
            } else {
                echo "사용자 업데이트 실패: 사용자 ID = $user_id. 오류: " . $conn->error . "\n";
            }
        }
    } else {
        echo "commissions 테이블에서 해당 주문 번호에 대한 수수료 데이터 없음: $order_id\n";
    }

    // 종료
    $stmt->close();
}


 $conn->close();
    echo "모든 처리가 완료되었습니다.<br>";
} else {
    // 실행 여부를 묻는 폼 출력
    ?>
    <form method="POST" action="">
        <p>스크립트를 실행하시겠습니까?</p>
        <button type="submit" name="execute" value="yes">확인</button>
        <button type="submit" name="execute" value="no">취소</button>
    </form>
    <?php
}
?>