<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? $pageTitle : '관리자페이지'; ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;700&family=Noto+Serif+KR:wght@400;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://yammi.link/css/croe.2.5.0.css">
    <style>
    body {
        padding-top: 60px;
        padding-bottom: 60px;
        /* 하단 여백 추가 */
        overflow-y: auto;
        /* 세로 스크롤 허용 */
    }

    .navbar {
        margin-bottom: 10px;
        /* 상단 여백 줄임 */
    }

    .container {
        min-height: calc(100vh - 120px);
        /* 상단 패딩과 하단 패딩을 고려한 높이 */
        display: flex;
        flex-direction: column;
    }

    .table {
        font-size: 0.9em;
        /* 테이블 폰트 크기 줄임 */
    }

    .table td,
    .table th {
        padding: 0.5rem;
        /* 테이블 셀 패딩 줄임 */
    }

    .card {
        margin-bottom: 10px;
        /* 카드 간 간격 줄임 */
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        margin-bottom: 0.5rem;
        /* 제목 아래 여백 줄임 */
    }

    .row {
        margin-bottom: 10px;
        /* 행 간 간격 줄임 */
    }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand notosans" href="/admin/index.php">(주)케이팬덤 관리페이지 </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="/admin/admin_members.php">회원 관리</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/admin_orders.php">주문 관리</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/admin_commissions.php">수수료 관리</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/commission_calculate.php">수수료정산</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/admin_withdrawals.php">출금신청처리</a></li>
                    <li class="nav-item"><a class="nav-link" href="/org_chart">추천조직도</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/admin_notice.php">공지사항</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/admin_prize_apply.php">경품응모</a></li>
                       <li class="nav-item"><a class="nav-link" href="/admin/admin_nft.php">NFT조회</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">