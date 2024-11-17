<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($pageTitle) ? $pageTitle : '예수 세례주화 NFT 프로젝트'; ?></title>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100;200;300;400;500;600;700;800;900&family=Noto+Serif+KR:wght@100;200;300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://yammi.link/css/croe.2.5.0.css">
    <style>
    body {
        font-family: 'Noto Serif KR', serif;
        margin: 0;
        padding: 0;
        background-color: #000000;
        color: #ffffff;
    }

    .header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 50px;
        background: linear-gradient(to right, #846300, #f2d06b);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 15px;
        z-index: 1000;
    }

    .back-button {
        position: absolute;
        left: 15px;
        color: #ffffff;
        font-size: 28px;
        text-decoration: none;
        cursor: pointer;
    }

    .header h1 {
        font-family: 'Noto Serif KR', serif;
        color: #000000;
        font-size: 23px;
        font-weight: bold;
        margin: 0;
    }

    .header.transparent {
        background: transparent;
        color: transparent;
    }

    .header.transparent .back-button,
    .header.transparent h1 {
        opacity: 0;
    }

    .content {
        padding: 0;
        padding-top: 40px;
        padding-bottom: 40px;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
    }
    </style>
</head>

<body>
    <div class="header <?php echo isset($hideHeader) && $hideHeader ? 'hidden-header' : ''; ?>">
        <?php if (!isset($hideHeader) || !$hideHeader): ?>
        <span class="back-button" onclick="history.back();">&lt;</span>
        <h1><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : '예수 세례주화 NFT 프로젝트'; ?></h1>
        <?php endif; ?>
    </div>
    <div class="content">
