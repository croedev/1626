<?php
session_start();
$pageTitle = 'NFT 비디오';
require_once __DIR__ . '/../includes/config.php';
include 'includes/header.php';

$conn = db_connect();
// 사용자 정보를 가져오는 로직
$serialNumber = 'NFT Serial';
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT referral_code, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $serialNumber = $row['referral_code'] . $row['phone'];
    }
    $stmt->close();
}
?>

<style>
    .header {
        opacity: 0;
        pointer-events: none;
    }
    .nft-content {
        position: fixed;
        top: 50px;  /* 헤더 높이 */
        bottom: 80px;  /* 푸터 높이 */
        left: 0;
        right: 0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        background-color: black;
    }
    #video-container {
        width: calc(100% - 40px);  /* 좌우 20px 패딩 */
        height: calc(100% - 60px);  /* 버튼 컨테이너 높이를 뺀 값 */
        position: relative;
        overflow: hidden;
        margin: 0 20px;  /* 좌우 20px 마진 */
    }
    #nft-video {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        max-width: 100%;
        max-height: 100%;
        width: auto;
        height: auto;
    }
    #overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
    }
    #serial-number {
        position: absolute;
        top: 45%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: gold;
        text-shadow: 0.1vw 0.1vw 0.2vw rgba(0,0,0,0.8);
        white-space: nowrap;
        text-align: center;
        font-size: 3vw;  /* 반응형 폰트 크기 */
    }
    #button-container {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 10px;
        width: 100%;
        max-width: 600px;  /* 최대 너비 설정 */
    }
    .outline-button {
        background-color: transparent;
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.5);
        background-color: rgba(97, 80, 56, 0.1);
        cursor: pointer;
        font-family: 'Noto Serif KR', serif;
        transition: all 0.3s ease;
        margin: 0 5px;
        font-size: 14px;  /* 고정 폰트 크기 */
        padding: 8px 16px;  /* 고정 패딩 */
        max-width: 120px;  /* 최대 너비 설정 */
        width: 30%;  /* 버튼 너비를 컨테이너의 30%로 설정 */
        border-radius: 20px;  /* 둥근 사각형 모양 */
    }
    .outline-button:hover {
        background-color: rgba(105, 79, 31, 0.1);
    }
    @media (max-width: 768px) {
        #serial-number {
            font-size: 5vw;
        }
        .outline-button {
            font-size: 12px;
            padding: 6px 12px;
        }
    }
</style>

<div class="nft-content">
    <div id="video-container">
        <video id="nft-video" autoplay loop playsinline>
            <source src="assets/nft.mov" type="video/quicktime">
            <source src="assets/nft.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>
        <div id="overlay">
            <div id="serial-number" class="notosans rem-09"><?php echo htmlspecialchars($serialNumber); ?></div>
        </div>
    </div>
    <div id="button-container">
        <button id="music-toggle" class="outline-button">찬양 ON</button>
        <!-- <button id="view-certificate" class="outline-button">증명서 확인</button> -->
        <a href="/bible_song" target="_self" class="outline-button text-center" style="text-decoration: none;">새찬송가</a>
           <a href="/bible" target="_blank" class="outline-button text-center" style="text-decoration: none;">성경말씀</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const video = document.getElementById('nft-video');
    const viewCertificateBtn = document.getElementById('view-certificate');
    const musicToggleBtn = document.getElementById('music-toggle');
    const serialNumber = document.getElementById('serial-number');
    const videoContainer = document.getElementById('video-container');
    const buttonContainer = document.getElementById('button-container');

    function adjustVideoAndElements() {
        const containerWidth = videoContainer.clientWidth;
        const containerHeight = videoContainer.clientHeight;
        const videoAspectRatio = video.videoWidth / video.videoHeight;
        const containerAspectRatio = containerWidth / containerHeight;

        if (containerAspectRatio > videoAspectRatio) {
            video.style.width = 'auto';
            video.style.height = '100%';
        } else {
            video.style.width = '100%';
            video.style.height = 'auto';
        }

        // 시리얼 번호 폰트 크기 조정
        const fontSizeVw = Math.min(containerWidth * 0.04, containerHeight * 0.05, 20);
        serialNumber.style.fontSize = `${fontSizeVw}px`;
    }

    function updateMusicToggleButton() {
        musicToggleBtn.textContent = video.muted ? '찬양 ON' : '찬양 OFF';
    }

    function playVideoWithSound() {
        video.muted = false;
        video.play().then(() => {
            console.log('Video playing with sound');
            updateMusicToggleButton();
        }).catch(error => {
            console.error('Autoplay with sound failed:', error);
            video.muted = true;
            video.play().then(() => {
                console.log('Video playing muted');
                updateMusicToggleButton();
            });
        });
    }

    musicToggleBtn.addEventListener('click', function () {
        video.muted = !video.muted;
        updateMusicToggleButton();
    });

    // 자동 재생 시도
    playVideoWithSound();

    // 비디오 반복 재생 확인
    video.addEventListener('ended', function () {
        video.play();
    });

    viewCertificateBtn.addEventListener('click', function () {
        window.location.href = '/certificate';
    });

    // 초기 조정 및 이벤트 리스너 설정
    video.addEventListener('loadedmetadata', adjustVideoAndElements);
    window.addEventListener('resize', adjustVideoAndElements);

    // 확대/축소 방지
    document.addEventListener('gesturestart', function (e) {
        e.preventDefault();
    });
});
</script>

<?php include 'includes/footer.php'; ?>