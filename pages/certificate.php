<?php
session_start();
$pageTitle = 'NFT 증명서';
include 'includes/header.php';


// 사용자 정보를 가져오는 로직 (예시)
$userInfo = [
    'name' => '강 광 민',
    'serialNumber' => 'BHG 3362153-017',
    'title' => 'The Baptism of Jesus,1626 (NFT.2024)',
    'blockchain' => 'BEP721 (Binance Smart Chain)',
    'tokenId' => '23',
    'issueDate' => '2024.07.07'
];

$currentDate = date("Y.m.d");
?>


<style>
    .header {
        opacity: 0;
        pointer-events: none;
    }
    .certificate-content {
        position: fixed;
        top: 50px;  /* 헤더 높이 */
        bottom: 80px;  /* 푸터 높이 */
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: black;
    }
    #certificate-container {
        width: 95%;
        height: 95%;
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    #certificate-bg {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    #overlay {
        position: absolute;
        pointer-events: none;
    }
    .overlay-text {
        position: absolute;
        color: #e0dbbf;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        white-space: nowrap;
    }
    #view-nft {
        position: absolute;
        background-color: rgba(128, 104, 59, 0.5);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.35);
        cursor: pointer;
        font-family: 'Noto Serif KR', serif;
        transition: all 0.3s ease;
        border-radius: 20px;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #view-nft:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
</style>

<div class="certificate-content">
    <div id="certificate-container">
        <img id="certificate-bg" src="assets/certificate_background.png" alt="증명서 배경">
        <div id="overlay">
            <div id="owner-name" class="overlay-text"><?php echo htmlspecialchars($userInfo['name']); ?></div>
            <div id="registration-code" class="overlay-text"><?php echo htmlspecialchars($userInfo['serialNumber']); ?></div>
            <div id="token-id" class="overlay-text"><?php echo htmlspecialchars($userInfo['tokenId']); ?></div>
            <div id="registration-date" class="overlay-text"><?php echo htmlspecialchars($userInfo['issueDate']); ?></div>
            <div id="current-date" class="overlay-text text-center"><?php echo $currentDate; ?></div>
        </div>
        <a id="view-nft" href="/nftmovie" class="outline-button">NFT 보기</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const certificateContainer = document.getElementById('certificate-container');
    const certificateBg = document.getElementById('certificate-bg');
    const overlay = document.getElementById('overlay');
    const viewNftBtn = document.getElementById('view-nft');

    function adjustCertificate() {
        const containerWidth = certificateContainer.clientWidth;
        const containerHeight = certificateContainer.clientHeight;
        const imgAspectRatio = certificateBg.naturalWidth / certificateBg.naturalHeight;
        const containerAspectRatio = containerWidth / containerHeight;

        let imgWidth, imgHeight, imgLeft, imgTop;
        if (containerAspectRatio > imgAspectRatio) {
            imgHeight = containerHeight;
            imgWidth = imgHeight * imgAspectRatio;
            imgLeft = (containerWidth - imgWidth) / 2;
            imgTop = 0;
        } else {
            imgWidth = containerWidth;
            imgHeight = imgWidth / imgAspectRatio;
            imgLeft = 0;
            imgTop = (containerHeight - imgHeight) / 2;
        }

        certificateBg.style.width = `${imgWidth}px`;
        certificateBg.style.height = `${imgHeight}px`;
        certificateBg.style.position = 'absolute';
        certificateBg.style.left = `${imgLeft}px`;
        certificateBg.style.top = `${imgTop}px`;

        overlay.style.width = `${imgWidth}px`;
        overlay.style.height = `${imgHeight}px`;
        overlay.style.left = `${imgLeft}px`;
        overlay.style.top = `${imgTop}px`;

        const elements = {
            'owner-name': { top: 38, left: 32 },
            'registration-code': { top: 40.5, left: 32 },
            'token-id': { top: 47.5, left: 32 },
            'registration-date': { top: 50, left: 32 },
            'current-date': { top: 64, left: 50 }
        };

        for (let [id, pos] of Object.entries(elements)) {
            const el = document.getElementById(id);
            el.style.top = `${imgHeight * (pos.top / 100)}px`;
            el.style.left = id === 'current-date' ? '50%' : `${imgWidth * (pos.left / 100)}px`;
            el.style.transform = id === 'current-date' ? 'translateX(-50%)' : 'none';
            el.style.fontSize = `${imgWidth * 0.025}px`;
        }

        const btnBottomPercent = 10;
        const btnRightPercent = 35;
        viewNftBtn.style.fontSize = `${imgWidth * 0.018}px`;
        viewNftBtn.style.padding = `${imgWidth * 0.01}px ${imgWidth * 0.02}px`;
        viewNftBtn.style.bottom = `${imgHeight * (btnBottomPercent / 100)}px`;
        viewNftBtn.style.right = `${imgWidth * (btnRightPercent / 100)}px`;
    }

    certificateBg.addEventListener('load', adjustCertificate);
    window.addEventListener('resize', adjustCertificate);

    if (certificateBg.complete) {
        adjustCertificate();
    } else {
        certificateBg.onload = adjustCertificate;
    }
});
</script>

<?php include 'includes/footer.php'; ?>