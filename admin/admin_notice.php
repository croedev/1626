<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// 관리자 권한 체크
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], [1, 2])) {
//     header("Location: /login"); 
//     exit();
// }

$conn = db_connect();
$message = '';
$error = '';

// 파일 업로드 처리
function handleFileUpload($file) {
    $target_dir = "../assets/uploads/notices/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // 이미지 파일 검증
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_extension, $allowed_types)) {
        throw new Exception("허용되지 않는 파일 형식입니다.");
    }
    
    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        throw new Exception("파일 업로드에 실패했습니다.");
    }
    
    return '/assets/uploads/notices/' . $new_filename;
}

// AJAX 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'upload_image') {
            if (!isset($_FILES['image'])) {
                throw new Exception("업로드된 파일이 없습니다.");
            }
            
            $file_path = handleFileUpload($_FILES['image']);
            echo json_encode(['success' => true, 'location' => $file_path]);
            exit;
        }
        
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $notice_id = intval($_POST['notice_id'] ?? 0);
        $is_important = isset($_POST['is_important']) ? 1 : 0;
        $category = trim($_POST['category'] ?? '');
        $youtube_url = trim($_POST['youtube_url'] ?? '');
        
        // YouTube URL을 iframe으로 변환
        if (!empty($youtube_url)) {
            preg_match('/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $matches);
            if (!empty($matches[1])) {
                $youtube_embed = '<iframe width="560" height="315" src="https://www.youtube.com/embed/' . $matches[1] . '" frameborder="0" allowfullscreen></iframe>';
                $content .= "\n\n" . $youtube_embed;
            }
        }

        if ($action === 'add' || $action === 'edit') {
            if (empty($title) || empty($content)) {
                throw new Exception("제목과 내용을 입력하세요.");
            }
            
            $metadata = json_encode([
                'youtube_url' => $youtube_url,
                'has_image' => strpos($content, '<img') !== false
            ]);
            
            if ($action === 'add') {
                $stmt = $conn->prepare("
                    INSERT INTO notices (
                        title, content, author, is_important, 
                        category, metadata, status
                    ) VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $author = $_SESSION['user_name'] ?? 'admin';
                $stmt->bind_param("ssssss", $title, $content, $author, $is_important, $category, $metadata);
            } else {
                $stmt = $conn->prepare("
                    UPDATE notices 
                    SET title = ?, content = ?, is_important = ?,
                        category = ?, metadata = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("sssssi", $title, $content, $is_important, $category, $metadata, $notice_id);
            }
            
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            
            echo json_encode(['success' => true, 'message' => '저장되었습니다']);
            exit;
        }
        
        if ($action === 'delete') {
            $stmt = $conn->prepare("UPDATE notices SET status = 'inactive' WHERE id = ?");
            $stmt->bind_param("i", $notice_id);
            
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            
            echo json_encode(['success' => true, 'message' => '삭제되었습니다']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 페이지네이션 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// 검색 조건 처리
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = "WHERE status = 'active'";
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $whereClause .= " AND (title LIKE '%$search%' OR content LIKE '%$search%')";
}

$total = $conn->query("SELECT COUNT(*) as count FROM notices $whereClause")->fetch_assoc()['count'];
$totalPages = ceil($total / $perPage);

// 공지사항 목록 조회
$notices = $conn->query("
    SELECT *, 
           CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 3 DAY) THEN 1 ELSE 0 END as is_new
    FROM notices 
    $whereClause
    ORDER BY is_important DESC, created_at DESC 
    LIMIT $offset, $perPage
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = "공지사항 관리";
include __DIR__ . '/admin_header.php';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>공지사항 관리</title>
     <script src="https://cdn.tiny.cloud/1/7jeq6ekuqb75dcxojwb1ggrlplziwnojpsc89ni4d0u57ic4/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

 <style>
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .notice-form { 
            background: #222; 
            padding: 20px; 
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .notice-list { 
            background: #222;
            border-radius: 8px;
            padding: 20px;
        }
        .notice-item { 
            padding: 15px;
            border-bottom: 1px solid #333;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .notice-item:hover { background: #2a2a2a; }
        .form-group { margin-bottom: 15px; }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #444;
            background: #333;
            color: #fff;
            border-radius: 4px;
        }
        .btn {
            padding: 8px 15px;
            margin: 5px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s;
        }
        .btn-primary { 
            background: #d4af37; 
            color: #000; 
        }
        .btn-secondary { 
            background: #444; 
            color: #fff; 
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }
        .upload-preview {
            max-width: 200px;
            margin: 10px 0;
        }
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .badge-important {
            background: #d4af37;
            color: #000;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
        }
        .badge-new {
            background: #4CAF50;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
        }
        .tinymce-wrap {
            margin-bottom: 20px;
        }
</style>
</head>
<body>
    <div class="container">
        <div class="search-box">
            <input type="text" id="searchInput" class="form-control w-80" placeholder="검색어 입력" 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button onclick="searchNotices()" class="btn btn-primary w-20">검색</button>
        </div>

        <form id="noticeForm" class="notice-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="notice_id" value="">
            
            <div class="form-group">
                <label>제목</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="is_important"> 중요 공지
                </label>
            </div>
            
            <div class="form-group">
                <label>카테고리</label>
                <select name="category" class="form-control">
                    <option value="">선택</option>
                    <option value="notice">공지</option>
                    <option value="event">이벤트</option>
                    <option value="update">업데이트</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>YouTube URL</label>
                <input type="text" name="youtube_url" class="form-control" 
                       placeholder="YouTube 영상 URL을 입력하세요">
            </div>
            
            <div class="form-group">
                <label>이미지 업로드</label>
                <input type="file" id="imageUpload" accept="image/*" class="form-control">
                <div id="imagePreview" class="upload-preview"></div>
            </div>
            
            <div class="tinymce-wrap">
                <textarea id="content" name="content"></textarea>
            </div>
            
            <div>
                <button type="submit" class="btn btn-primary">저장</button>
                <button type="button" class="btn btn-secondary" onclick="resetForm()">새글</button>
            </div>
        </form>

        <div class="notice-list">
            <?php foreach ($notices as $notice): ?>
            <div class="notice-item" onclick='loadNotice(<?php echo json_encode($notice); ?>)'>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h5>
                        <?php if ($notice['is_important']): ?>
                            <span class="badge-important">중요</span>
                        <?php endif; ?>
                        <?php if ($notice['is_new']): ?>
                            <span class="badge-new">NEW</span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($notice['title']); ?>
                    </h5>
                    <button class="btn btn-secondary" 
                            onclick="deleteNotice(event, <?php echo $notice['id']; ?>)">삭제</button>
                </div>
                <div style="font-size: 0.9em; color: #888;">
                    <?php echo $notice['category'] ? "[{$notice['category']}] " : ''; ?>
                    작성일: <?php echo date('Y-m-d H:i', strtotime($notice['created_at'])); ?> |
                    작성자: <?php echo htmlspecialchars($notice['author']); ?> |
                    조회수: <?php echo number_format($notice['views']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 페이지네이션 -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: 20px; text-align: center;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        let editor;
        
        // TinyMCE 초기화
        tinymce.init({
            selector: '#content',
            plugins: 'advlist autolink lists link image charmap preview anchor pagebreak code',
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | image link | bullist numlist',
            height: 500,
            images_upload_url: '',
            images_upload_handler: handleImageUpload,
            setup: function(ed) {
                editor = ed;
            }
        });

        // 이미지 업로드 처리
        async function handleImageUpload(blobInfo) {
            const formData = new FormData();
            formData.append('action', 'upload_image');
            formData.append('image', blobInfo.blob(), blobInfo.filename());
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message);
            }
            
            return data.location;
        }

// 이미지 미리보기 처리
        document.getElementById('imageUpload').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            if (!file.type.startsWith('image/')) {
                alert('이미지 파일만 업로드할 수 있습니다.');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'upload_image');
                formData.append('image', file);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (!data.success) throw new Error(data.message);
                
                // 에디터에 이미지 삽입
                editor.insertContent(`<img src="${data.location}" alt="Uploaded image" />`);
                
                // 미리보기 표시
                const preview = document.getElementById('imagePreview');
                preview.innerHTML = `<img src="${data.location}" style="max-width: 100%;" />`;
            } catch (error) {
                alert('이미지 업로드 중 오류가 발생했습니다: ' + error.message);
            }
            
            // 파일 입력 초기화
            e.target.value = '';
        });

        // 공지사항 불러오기
        function loadNotice(notice) {
            document.querySelector('[name="action"]').value = 'edit';
            document.querySelector('[name="notice_id"]').value = notice.id;
            document.querySelector('[name="title"]').value = notice.title;
            document.querySelector('[name="is_important"]').checked = notice.is_important == 1;
            document.querySelector('[name="category"]').value = notice.category || '';
            
            // metadata 파싱
            let metadata = {};
            try {
                metadata = JSON.parse(notice.metadata || '{}');
            } catch (e) {
                console.error('Metadata parsing error:', e);
            }
            
            document.querySelector('[name="youtube_url"]').value = metadata.youtube_url || '';
            editor.setContent(notice.content);
            
            // 스크롤을 폼으로 이동
            document.querySelector('.notice-form').scrollIntoView({ behavior: 'smooth' });
        }

        // 폼 초기화
        function resetForm() {
            document.querySelector('[name="action"]').value = 'add';
            document.querySelector('[name="notice_id"]').value = '';
            document.querySelector('[name="title"]').value = '';
            document.querySelector('[name="is_important"]').checked = false;
            document.querySelector('[name="category"]').value = '';
            document.querySelector('[name="youtube_url"]').value = '';
            editor.setContent('');
            document.getElementById('imagePreview').innerHTML = '';
        }

        // 공지사항 삭제
        async function deleteNotice(event, noticeId) {
            event.stopPropagation();
            
            if (!confirm('정말 이 공지사항을 삭제하시겠습니까?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('notice_id', noticeId);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                alert('삭제 중 오류가 발생했습니다: ' + error.message);
            }
        }

        // 검색 기능
        function searchNotices() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('search', searchTerm);
            currentUrl.searchParams.set('page', '1');
            window.location.href = currentUrl.toString();
        }

        // 폼 제출
        document.getElementById('noticeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('content', editor.getContent());
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('저장 중 오류가 발생했습니다: ' + error.message);
            }
        });

        // 검색창 엔터 키 이벤트
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchNotices();
            }
        });
    </script>
</body>
</html>

<?php include __DIR__ . '/../includes/footer.php'; ?>