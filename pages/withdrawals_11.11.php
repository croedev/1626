<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// 사용자 인증 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: /login?redirect=withdrawals");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = db_connect();

$pageTitle = '출금 신청하기';
include __DIR__ . '/../includes/header.php';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>출금 신청하기</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: Arial, sans-serif;
        }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            max-width: 80%;
            width: 400px;
        }
        .modal-content h2 {
            color: #d4af37;
            margin-bottom: 15px;
        }
        .modal-content p {
            color: #fff;
            margin-bottom: 20px;
        }
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .modal-button {
            background-color: #d4af37;
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .modal-button:hover {
            background-color: #b8960c;
        }
    </style>
</head>
<body>
    <div class="modal-overlay notoserif">
        <div class="modal-content">
            <h3 class="text-orange">"서비스 정산중"</h3>
            <p class="text-orange">빠른시간에 완료하겠습니다.</p>
            <p class="rem-08">예정일 : 2024년 11월 11일 이후부터 <br>출금신청이 가능합니다.</p>
            <p class="notosans">케이팬덤 고객관리팀</p>
            <div class="modal-buttons">
                <button class="modal-button btn-16 " onclick="location.href='/'">홈으로</button>
                <button class="modal-button btn-16 " onclick="location.href='/commission'">수수료조회로</button>
            </div>
        </div>
    </div>


</body>
</html>

<?php include __DIR__ . '/../includes/footer.php'; ?>