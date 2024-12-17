<?php
session_start();
$pageTitle = '케이팬덤,예수세례주화';
include 'includes/header.php';
?>

<style>
.iframe-content {
    position: relative;
    width: 100%;
    height: 100vh; /* 화면 전체 높이 */
    overflow: hidden;
}

.iframe-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.iframe-container iframe {
    width: 100%;
    height: 100%;
    border: none;
    overflow: auto;
    -webkit-overflow-scrolling: touch; /* iOS 스크롤 지원 */
}

/* 모바일 최적화 */
@media (max-width: 768px) {
    .iframe-content {
        height: calc(100vh - 60px); /* 모바일 헤더 높이 고려 */
    }
}
</style>

<div class="iframe-content">
    <div class="iframe-container">
        <iframe src="https://e-name.kr/6qWKAM6R" frameborder="0" allowfullscreen></iframe>
    </div>
</div>

<?php include 'includes/footer.php'; ?>