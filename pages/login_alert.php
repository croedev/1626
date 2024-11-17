<?php
// 세션 시작
session_start();

// 페이지 타이틀 설정
$pageTitle = '로그인하세요';

// 모든 세션 변수 제거
$_SESSION = array();

// 세션 쿠키 삭제
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 세션 파괴
session_destroy();

// 헤더 인클루드
require_once 'includes/config.php';
include 'includes/header.php';
?>

<style>
    .logout-container {
        max-width: 400px;
        margin: 50px auto;
        padding: 20px;
        text-align: center;
        background-color: rgba(0, 0, 0, 0.7);
        border-radius: 10px;
    }
    .logout-message {
        color: #d4af37;
        font-size: 1.2em;
        margin-bottom: 20px;
    }
    .home-link {
        display: inline-block;
        background: linear-gradient(to right, #d4af37, #f2d06b);
        color: #000;
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        transition: opacity 0.3s;
    }
    .home-link:hover {
        opacity: 0.8;
    }
</style>


<div class="logout-container">
    <img src="assets/images/goldkey.png" width="150" height="" alt="로고">
    <h2 class="fw-900"> </h2>
    <div class="logout-message">회원전용공간입니다. <br>로그인 후 이용하세요.</div>
    <a href="/login" class="home-link">로그인 이동</a>
</div>

<?php include 'includes/footer.php'; ?>