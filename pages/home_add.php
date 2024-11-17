<?php
require_once 'includes/config.php';
$pageTitle = '홈화면 추가 방법';
include 'includes/header.php';
?>

<style>
    body {
        background-color: #f8f9fa;
        color: #333;
    }
    .container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    h1, h2 {
        color: #007bff;
        font-family: 'Noto Serif KR', serif;
        margin-bottom: 20px;
    }
    .video-container {
        position: relative;
        padding-bottom: 56.25%;
        height: 0;
        overflow: hidden;
        max-width: 100%;
        margin-bottom: 30px;
    }
    .video-container iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    .instruction-box {
        background-color: #fff;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .instruction-title {
        font-size: 1.2rem;
        font-weight: bold;
        color: #007bff;
        margin-bottom: 15px;
    }
    .instruction-list {
        padding-left: 20px;
    }
    .instruction-list li {
        margin-bottom: 10px;
    }
    .btn-primary {
        background-color: #007bff;
        border-color: #007bff;
        font-weight: bold;
        padding: 10px 20px;
        font-size: 1rem;
    }
    .btn-primary:hover {
        background-color: #0056b3;
        border-color: #0056b3;
    }
</style>

<div class="container">
   
    
    <div class="video-container mt30">
        <iframe width="560" height="315" src="https://www.youtube.com/embed/lgdZRn0nvPo" title="예수세례주화 앱 홈화면 추가 방법" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="instruction-box">
                <h2 class="instruction-title">삼성 기종 홈화면 추가</h2>
                <ol class="instruction-list notosans">
                    <li>하단 홈화면으로 이동</li>
                    <li>우측 하단 점 3개(⋮) 선택</li>
                    <li>"다른 브라우저로 열기" 선택</li>
                    <li>"+ 현재페이지 추가" 선택</li>
                    <li>"홈화면" 선택</li>
                    <li>[추가] 버튼 클릭</li>
                </ol>
            </div>
        </div>
        <div class="col-md-6">
            <div class="instruction-box">
                <h2 class="instruction-title">아이폰 홈화면 추가</h2>
                <ol class="instruction-list notosans">
                    <li>하단 홈화면으로 이동</li>
                    <li><i class="fas fa-upload"></i> 하단 [공유하기] 아이콘 선택</li>
                    <li>선택 메뉴에서 "홈화면에 추가" 선택</li>
                    <li>상단 [추가] 선택</li>
                </ol>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
