
</div>  
<? //end content ?>

<div class="nav-menu">
        <a href="/" class="nav-item" data-page="home">
            <i class="fas fa-home"></i>
            <span>홈</span>
        </a>
        <a href="/profile" class="nav-item" data-page="profile">
            <i class="fas fa-user"></i>
            <span>회원</span>
        </a>
        <a href="/order" class="nav-item" data-page="order">
            <i class="fas fa-shopping-cart"></i>
            <span>구매</span>
        </a>
        <a href="/nftmovie" class="nav-item" data-page="nftmovie">
            <i class="fas fa-coins"></i>
            <span>NFT</span>
        </a>
        <a href="javascript:void(0);" onclick="shareKakao()" class="nav-item" data-page="share">
            <i class="fas fa-share-alt"></i>
            <span>공유</span>
        </a>
    </div>

    
<script src="https://developers.kakao.com/sdk/js/kakao.js"></script>
<script>
    // 카카오 SDK 초기화 함수
    function initKakao() {
        Kakao.init('c9e708d6ad0e4ead5dc265350b6d4d89');
        console.log(Kakao.isInitialized());
    }

    // 카카오톡 공유 함수
    function shareKakao() {
        Kakao.Link.sendDefault({
            objectType: 'feed',
            content: {
                title: '예수 세례주화 NFT 프로젝트',
                description: '소중한 예수 세례주화를 소유하세요!',
                imageUrl: 'https://1626.lidyahk.com/assets/images/logo.png', // 실제 이미지 URL로 교체해주세요
                link: {
                    mobileWebUrl: 'https://1626.lidyahk.com',
                    webUrl: 'https://1626.lidyahk.com'
                }
            },
            buttons: [
                {
                    title: '웹으로 보기',
                    link: {
                        mobileWebUrl: 'https://1626.lidyahk.com',
                        webUrl: 'https://1626.lidyahk.com'
                    }
                }
            ]
        });
    }

    // 현재 페이지 하이라이트 함수
    function highlightCurrentPage() {
        var currentPage = '<?php echo basename($_SERVER['PHP_SELF'], '.php'); ?>';
        var navItems = document.querySelectorAll('.nav-item');
        
        navItems.forEach(function(item) {
            if (item.getAttribute('data-page') === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }

    // 페이지 로드 완료 후 실행
    document.addEventListener('DOMContentLoaded', function() {
        initKakao();
        highlightCurrentPage();
    });

    // 카카오 SDK 로드 실패 시 대체 처리
    window.onload = function() {
        if (typeof Kakao === 'undefined') {
            console.error('카카오 SDK 로드 실패');
            // 대체 공유 방법 구현 또는 사용자에게 알림
            document.querySelector('a[onclick="shareKakao()"]').onclick = function() {
                alert('카카오톡 공유 기능을 사용할 수 없습니다.');
                return false;
            };
        }
    };
</script>

<style>
    .nav-menu {
        background: linear-gradient(90deg, #d4af37, #b18528);
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        display: flex;
        justify-content: space-around;
        padding: 13px 0 8px 0;
        border: 0;
        z-index: 11;
    }
    .nav-menu a {
        color: #000;
        text-decoration: none;
        text-align: center;
        flex: 1;
        transition: color 0.3s;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .nav-menu a:hover { color: #ffffff; }
    .nav-menu a.active {
        color: #000;
    }
    .nav-menu i {
        font-size: 24px;
        margin-bottom: 2px;
    }
    .nav-menu span {
        display: block;
        font-size: 10px;
    }
</style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.8.1/slick.min.js"></script>
</body>
</html>