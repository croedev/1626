<?php
// 설정 파일 포함
require_once __DIR__ . '/includes/config.php';
date_default_timezone_set('Asia/Seoul');

// URL에서 경로 추출 (GET 파라미터 제거)
$request_uri = explode('?', $_SERVER['REQUEST_URI'], 2)[0];
$request_uri = rtrim($request_uri, '/');

// 라우팅 로직
switch ($request_uri) {
    case '':
    case '/':
        require __DIR__ . '/pages/home.php';
        break;
    case '/profile':
        require __DIR__ . '/pages/profile.php';
        break;
    case '/order':
        require __DIR__ . '/pages/order.php';
        break;
    case '/nftmovie':
        require __DIR__ . '/pages/nftmovie.php';
        break;
    case '/certificate':
        require __DIR__ . '/pages/certificate.php';
        break;
            case '/login_alert':
        require __DIR__ . '/pages/login_alert.php';
        break;
    case '/login':
        require __DIR__ . '/pages/login.php';
        break;
    case '/logout':
        require __DIR__ . '/pages/logout.php';
        break;
    case '/join':
        require __DIR__ . '/pages/join.php';
        break;
    case '/forgot_password':
        require __DIR__ . '/pages/forgot_password.php';
        break;
    case '/reset_password':
        require __DIR__ . '/pages/reset_password.php';
        break;
    case '/edit_profile':
        require __DIR__ . '/pages/edit_profile.php';
        break;

    case '/bible':
        require __DIR__ . '/pages/bible.php';
        break;
    case '/order_apply':
        require __DIR__ . '/pages/order_apply.php';
        break;
    case '/order_process':
        require __DIR__ . '/pages/order_process.php';
        break;
    case '/order_complete':
        require __DIR__ . '/pages/order_complete.php';
        break;
    case '/order_list':
        require __DIR__ . '/pages/order_list.php';
        break;

    case '/commission':
        require __DIR__ . '/pages/commission.php';
        break;

    case '/referral_chart':
        require __DIR__ . '/pages/referral_chart.php';
        break;
    case '/organization':
        require __DIR__ . '/pages/organization.php';
        break;

  
    case '/nft_transfer':
    require __DIR__ . '/pages/nft_transfer.php';
    break;

        case '/withdrawals':
    require __DIR__ . '/pages/withdrawals.php';
    break;

            case '/bible_song':
    require __DIR__ . '/pages/bible_song.php';
    break;


            case '/org_tree':
    require __DIR__ . '/pages/org_tree.php';
    break;

            case '/home_add':
    require __DIR__ . '/pages/home_add.php';
    break;

            case '/org_chart':
    require __DIR__ . '/pages/org_chart_box.php';
    break;

               case '/prize_apply':
    require __DIR__ . '/pages/prize_apply.php';
    break;

    case '/notice':
    require __DIR__ . '/pages/notice.php';
    break;

    case '/summary':
    require __DIR__ . '/pages/summary.php';
    break;

    
    case '/admin':
    require __DIR__ . '/admin/index.php';
    break;

    case '/admin_members':
    require __DIR__ . '/admin/admin_members.php';
    break;

    case '/admin_orders':
    require __DIR__ . '/admin/admin_orders.php';
    break;


    case '/sere_wallet':
    require __DIR__ . '/pages/sere_wallet.php';
    break;


    case '/sere_swap':
    require __DIR__ . '/pages/sere_swap.php';
    break;


    default:
        // 404 에러 페이지
        header("HTTP/1.0 404 Not Found");
        require __DIR__ . '/pages/404.php';
        break;
}

?>

<!-- //홈화면 추가 -->

<link rel="manifest" href="/manifest.json">
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js')
        .then((registration) => {
            console.log('Service Worker 등록 성공:', registration);
        })
        .catch((error) => {
            console.log('Service Worker 등록 실패:', error);
        });
}
</script>

<button id="add-to-home-btn" style="display: none;">홈화면에 추가</button>

<script>
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    // 기본 동작 방지 및 이벤트 저장
    e.preventDefault();
    deferredPrompt = e;

    // 버튼을 보이게 함
    const addToHomeBtn = document.getElementById('add-to-home-btn');
    addToHomeBtn.style.display = 'block';

    // 버튼 클릭 시 홈 화면 추가
    addToHomeBtn.addEventListener('click', () => {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('홈 화면에 추가되었습니다.');
            } else {
                console.log('홈 화면 추가가 취소되었습니다.');
            }
            deferredPrompt = null;
        });
    });
});

// 앱이 이미 설치된 경우 버튼 숨김
window.addEventListener('appinstalled', () => {
    console.log('앱이 이미 설치되었습니다.');
    document.getElementById('add-to-home-btn').style.display = 'none';
});
</script>