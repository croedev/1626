<?php
session_start();
require_once 'includes/config.php';
$pageTitle = '공지사항';

$conn = db_connect();

// 페이지네이션 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// 선택된 공지사항 또는 최신 공지사항 가져오기
$featured_notice = null;
if (isset($_GET['id'])) {
    $notice_id = (int)$_GET['id'];
    
    // 조회수 증가
    $stmt = $conn->prepare("UPDATE notices SET views = views + 1 WHERE id = ?");
    $stmt->bind_param("i", $notice_id);
    $stmt->execute();
    
    // 선택된 공지사항 가져오기
    $stmt = $conn->prepare("
        SELECT n.*, 
               JSON_UNQUOTE(JSON_EXTRACT(n.metadata, '$.youtube_url')) as youtube_url,
               JSON_EXTRACT(n.metadata, '$.has_image') as has_image
        FROM notices n 
        WHERE n.id = ? AND n.status = 'active'
    ");
    $stmt->bind_param("i", $notice_id);
    $stmt->execute();
    $featured_notice = $stmt->get_result()->fetch_assoc();
} else {
    // 최신 중요 공지사항 가져오기
    $featured_notice = $conn->query("
        SELECT n.*, 
               JSON_UNQUOTE(JSON_EXTRACT(n.metadata, '$.youtube_url')) as youtube_url,
               JSON_EXTRACT(n.metadata, '$.has_image') as has_image
        FROM notices n 
        WHERE n.status = 'active' 
        ORDER BY n.is_important DESC, n.created_at DESC 
        LIMIT 1
    ")->fetch_assoc();
}

// 중요 공지사항 목록
$important_notices = $conn->query("
    SELECT *, 
           CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 1 ELSE 0 END as is_new,
           JSON_EXTRACT(metadata, '$.has_image') as has_image,
           CASE WHEN metadata LIKE '%youtube%' THEN 1 ELSE 0 END as has_video
    FROM notices 
    WHERE status = 'active' AND is_important = 1 
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// 일반 공지사항 목록
$stmt = $conn->prepare("
    SELECT *, 
           CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 1 ELSE 0 END as is_new,
           JSON_EXTRACT(metadata, '$.has_image') as has_image,
           CASE WHEN metadata LIKE '%youtube%' THEN 1 ELSE 0 END as has_video
    FROM notices 
    WHERE status = 'active' AND is_important = 0
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$notices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 전체 페이지 수 계산
$total_notices = $conn->query("SELECT COUNT(*) as count FROM notices WHERE status = 'active' AND is_important = 0")->fetch_assoc()['count'];
$total_pages = ceil($total_notices / $perPage);

include 'includes/header.php';
?>

<style>
    .notice-page {
        padding: 60px 0;
        background-color: #000;
        min-height: calc(100vh - 120px);
    }

    .notice-title {
        font-size: 1rem;
        margin-bottom: 8px;
        color: #fff;
    }



    .featured-notice {
        background: #181818;
        border-radius: 15px;
        margin: 20px;
        overflow: hidden;
        border: 1px solid #333;
    }

    .featured-notice-header {
        background: #222;
        padding: 20px;
        border-bottom: 1px solid #333;
    }

    .featured-notice-title {
        font-size: 1.2rem;
        color: #d4af37;
        margin-bottom: 10px;
        font-family: 'Noto Serif KR', serif;
    }

    .featured-notice-meta {
        color: #888;
        font-size: 0.85rem;
        display: flex;
        gap: 15px;
    }

    .featured-notice-content {
        padding: 20px;
        color: #fff;
        line-height: 1.6;
        font-family: 'Noto Sans KR', sans-serif;
    }

    .featured-notice-content img {
        width: 100%;
        height: auto;
        max-width: 100%;
        display: block;
        margin: 15px 0;
        border-radius: 8px;
    }

    .video-container {
        position: relative;
        padding-bottom: 56.25%;
        height: 0;
        overflow: hidden;
        margin: 15px 0;
        border-radius: 8px;
        background: #000;
    }

    .video-container iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }

    .notice-list-section {
        background: #181818;
        margin: 20px;
        border-radius: 15px;
        padding: 20px;
        border: 1px solid #333;
    }

    .notice-list-item {
        padding: 15px;
        border: 1px solid #333;
        border-radius: 8px;
        margin-bottom: 10px;
        background: rgba(34, 34, 34, 0.5);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .notice-list-item:hover {
        transform: translateY(-2px);
        background: rgba(34, 34, 34, 0.8);
        box-shadow: 0 4px 12px rgba(212, 175, 55, 0.1);
    }

    .notice-list-badges {
        display: flex;
        gap: 5px;
        align-items: center;
    }

    .badge {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        white-space: nowrap;
    }

    .badge-new {
        background: #d4af37;
        color: #000;
    }

    .badge-important {
        background: #a83232;
        color: #fff;
    }

    .category-badge {
        padding: 0px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        white-space: nowrap;
        background: #83817a;
        color: #000;
    }


    .notice-list-meta {
        display: flex;
        align-items: center;
        gap: 15px;
        color: #888;
        font-size: 0.8rem;
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 5px;
    }

    .notice-list-meta span {
        white-space: nowrap;
    }

    .notice-list-meta i {
        width: 16px;
        text-align: center;
        color: #d4af37;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
    }

    .pagination a {
        padding: 8px 12px;
        background: #222;
        color: #d4af37;
        text-decoration: none;
        border-radius: 4px;
        transition: all 0.3s ease;
        border: 1px solid #333;
    }

    .pagination a:hover,
    .pagination a.active {
        background: #d4af37;
        color: #000;
    }

    @media (max-width: 768px) {
        .notice-list-item {
            grid-template-columns: 1fr;
        }
        
        .notice-list-meta {
            gap: 10px;
            font-size: 0.75rem;
        }
        
        .featured-notice-meta {
            flex-wrap: wrap;
        }
        
        .video-container {
            margin: 10px 0;
        }
    }
</style>

<div class="notice-page">
    <?php if ($featured_notice): ?>
    <div class="featured-notice">
        <div class="featured-notice-header">
            <h1 class="featured-notice-title">
                <?php if ($featured_notice['is_important']): ?>
                    <span class="badge badge-important">중요</span>
                <?php endif; ?>
                <?php if ($featured_notice['category']): ?>
                    <span class="category-badge"><?php echo htmlspecialchars($featured_notice['category']); ?></span>
                <?php endif; ?>
                <?php echo htmlspecialchars($featured_notice['title']); ?>
            </h1>
            <div class="featured-notice-meta">
                <span><i class="far fa-clock"></i> <?php echo date('Y.m.d H:i', strtotime($featured_notice['created_at'])); ?></span>
                <span><i class="far fa-eye"></i> 조회 <?php echo number_format($featured_notice['views']); ?></span>
                <span><i class="far fa-user"></i> <?php echo htmlspecialchars($featured_notice['author']); ?></span>
                <?php if ($featured_notice['youtube_url']): ?>
                    <span><i class="fab fa-youtube"></i> 동영상</span>
                <?php endif; ?>
                <?php if ($featured_notice['has_image']): ?>
                    <span><i class="far fa-image"></i> 이미지</span>
                <?php endif; ?>
            </div>
        </div>


        <div class="featured-notice-content">
            <?php
                $content = $featured_notice['content'];
                
              // YouTube URL을 iframe으로 변환
if ($featured_notice['youtube_url']) {
    $youtube_pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
    preg_match($youtube_pattern, $featured_notice['youtube_url'], $matches);
 
}
                
                echo $content;
            ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="notice-list-section">
        <!-- 중요 공지사항 -->
        <?php foreach ($important_notices as $notice): ?>
            <div class="notice-list-item" onclick="location.href='/notice?id=<?php echo $notice['id']; ?>'">
                <div class="notice-list-badges">
                    <span class="badge badge-important">중요</span>
                    <?php if ($notice['is_new']): ?>
                        <span class="badge badge-new">NEW</span>
                    <?php endif; ?>
                    <?php if ($notice['category']): ?>
                        <span class="category-badge"><?php echo htmlspecialchars($notice['category']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="notice-list-content">
                    <div class="notice-title"><?php echo htmlspecialchars($notice['title']); ?></div>
                    <div class="notice-list-meta">
                        <span><i class="far fa-clock"></i> <?php echo date('Y.m.d', strtotime($notice['created_at'])); ?></span>
                        <span><i class="far fa-eye"></i> <?php echo number_format($notice['views']); ?></span>
                        <?php if ($notice['has_video']): ?>
                            <span><i class="fab fa-youtube"></i> 동영상</span>
                        <?php endif; ?>
                        <?php if ($notice['has_image']): ?>
                            <span><i class="far fa-image"></i> 이미지</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- 일반 공지사항 -->
        <?php foreach ($notices as $notice): ?>
            <div class="notice-list-item" onclick="location.href='/notice?id=<?php echo $notice['id']; ?>'">
                <div class="notice-list-badges">
                    <?php if ($notice['is_new']): ?>
                        <span class="badge badge-new">NEW</span>
                    <?php endif; ?>
                    <?php if ($notice['category']): ?>
                        <span class="category-badge"><?php echo htmlspecialchars($notice['category']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="notice-list-content">
                    <div class="notice-title"><?php echo htmlspecialchars($notice['title']); ?></div>
                    <div class="notice-list-meta">
                        <span><i class="far fa-clock"></i> <?php echo date('Y.m.d', strtotime($notice['created_at'])); ?></span>
                        <span><i class="far fa-eye"></i> <?php echo number_format($notice['views']); ?></span>
                        <?php if ($notice['has_video']): ?>
                            <span><i class="fab fa-youtube"></i> 동영상</span>
                        <?php endif; ?>
                        <?php if ($notice['has_image']): ?>
                            <span><i class="far fa-image"></i> 이미지</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- 페이지네이션 -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" 
                       class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>