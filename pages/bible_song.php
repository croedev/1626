<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// 로그인 체크
// if (!is_logged_in()) {
//     header("Location: /login");
//     exit;
// }

// Database connection
$conn = db_connect();

// Handle AJAX request for fetching lyrics
if (isset($_GET['action']) && $_GET['action'] === 'fetch_lyrics' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM hymns WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hymn = $result->fetch_assoc();

    // Set the Content-Type header to application/json
    header('Content-Type: application/json');
    echo json_encode($hymn);
    exit; // Terminate script after sending JSON response
}

// Handle AJAX request for searching hymns
if (isset($_GET['action']) && $_GET['action'] === 'search_hymns' && isset($_GET['term'])) {
    $term = $_GET['term'];
    $stmt = null;

    if (is_numeric($term)) {
        // 숫자로 검색할 경우 ID로 검색
        $stmt = $conn->prepare("SELECT id, title FROM hymns WHERE id = ? ORDER BY id ASC");
        $stmt->bind_param("i", $term);
    } else {
        // 문자열로 검색할 경우 제목으로 검색
        $term = '%' . $term . '%';
        $stmt = $conn->prepare("SELECT id, title FROM hymns WHERE title LIKE ? ORDER BY id ASC");
        $stmt->bind_param("s", $term);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $hymns = $result->fetch_all(MYSQLI_ASSOC);

    // Set the Content-Type header to application/json
    header('Content-Type: application/json');
    echo json_encode($hymns);
    exit; // Terminate script after sending JSON response
}

// Pagination settings
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Fetch hymns for the current page
$stmt = $conn->prepare("SELECT id, title FROM hymns ORDER BY id ASC LIMIT ?, ?");
$stmt->bind_param("ii", $offset, $limit);
$stmt->execute();
$result = $stmt->get_result();
$hymns = $result->fetch_all(MYSQLI_ASSOC);

// Fetch total hymns count for pagination
$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM hymns");
$total_stmt->execute();
$total_result = $total_stmt->get_result();
$total_hymns = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_hymns / $limit);
?>


<?
$pageTitle = '새찬송가';
include __DIR__ . '/../includes/header.php';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>새찬송가</title>
    <!-- Bootstrap CDN -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <!-- Custom Styles -->
 <style>
        body {
            background-color: #1a1a1a;
            color: #ffffff;
            font-family: 'Noto serif KR', serif;
        }
        .btn-gold, .btn-play-song, .controls button, .pagination .page-link, .select-buttons button {
            background-color: transparent;
            border: 1px solid #d4af37;
            color: #d4af37;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-gold:hover, .btn-play-song:hover, .controls button:hover, .pagination .page-link:hover, .select-buttons button:hover {
            background-color: #d4af37;
            color: #000;
        }
        .controls button, .select-buttons button {
            margin-right: 10px;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9em;
        }
        .lyrics, .player-container, .playlist {
            background-color: rgba(42, 42, 42, 0.8);
            border-radius: 10px;
            margin: 20px auto;
            max-width: 800px;
            padding: 20px;
        }
        .lyrics h6, .player-container h3 {
            color: #d4af37;
        }
        .lyrics p {
            font-size: 0.9em;
            margin-bottom: 15px;
            padding: 10px 20px;
        }
        .pagination {
            justify-content: center;
        }
        .playlist table {
            color: #ffffff;
            width: 100%;
        }
        .playlist table td, .playlist table th {
            border-bottom: 1px solid #555;
            padding: 8px;
            text-align: center;
            vertical-align: middle;
        }
        .playlist table td {
            font-family: 'Noto sans KR', sans-serif;
            font-size: 0.85em;
            font-weight: 300;
        }
        .playlist table th {
            background-color: #1a1a1a;
            border-top: 1px solid #fff555;
            font-family: 'Noto sans KR', sans-serif;
            font-size: 0.8em;
            font-weight: 400;
        }
        .playlist table tr:hover {
            background-color: rgba(212, 175, 55, 0.1);
        }
        .select-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-container {
            display: flex;
            flex-grow: 1;
            margin-right: 10px;
        }
        #search-input {
            background-color: rgba(145, 128, 128, 0.32);
            border: 1px solid #a39a7e;
            border-radius: 5px 0 0 5px;
            color: #ffffff;
            flex-grow: 1;
            font-size: 0.8em;
            padding: 5px 10px;
        }
        #btn-search {
            border-radius: 0 5px 5px 0;
        }
        #hymn-table {
            border-collapse: separate;
            border-spacing: 0 10px;
            width: 100%;
        }
        #hymn-table td {
            background-color: rgba(42, 42, 42, 0.7);
            padding: 2px;
        }
        #hymn-table th {
            background-color: #2a2a2a;
            color: #d4af37;
            padding: 12px;
            text-align: left;
        }
        .btn-play-song {
            font-size: 0.75em;
            padding: 3px 8px;
        }
        .playlist table td.title-cell {
            cursor: pointer;
            text-align: left;
        }
        .playlist table td.title-cell:hover {
            text-decoration: underline;
        }
        .selected {
            background-color: rgba(212, 175, 55, 0.2);
        }
        @media (max-width: 768px) {
            .select-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .search-container {
                margin: 0 auto 10px;
            }
            .btn-gold {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>




</head>
<body>

<div class="player-container bg-blue100">
    <h5 id="song-title" class="notosans text-orange">제1장 만복의 근원 하나님</h5>
    <audio id="audio-player" controls style="width: 100%;" data-current-id="1" class="mt-3">
        <source src="https://online.goodtv.co.kr/hymn/new/001.mp3" type="audio/mp3">
        Your browser does not support the audio element.
    </audio>
    <div class="controls mt-3 text-center">
        <button id="btn-play-selected" class="bg-blue90"><i class="fas fa-play-circle"></i> 선택재생</button>
        <button id="btn-play-all" class="bg-blue90"><i class="fas fa-music"></i> 연속재생</button>
        <button id="btn-show-lyrics" class="bg-red90"><i class="fas fa-file-alt"></i> 가사보기</button>
    </div>

      <div class="search-container w-90  mt-3">
            <input type="text" id="search-input" style="width:200px" placeholder="찬송가 번호 또는 제목 검색">
            <button id="btn-search" class="bg-gray80 fs-12 w80 outline">검색</button>
        </div>
</div>

<div class="playlist mb80" id="playlist-container">
    <div class="select-buttons mb-2">

        <div class="flex-x-end">
       <button id="btn-select-all" class="bg-gray80">전체선택</button>
        <button id="btn-deselect-all" class="bg-gray90">선택해제</button>
        </div>
    </div>

  


    <table id="hymn-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th><th>번호</th><th>제목</th><th>재생</th>
            </tr>
        </thead>
        <tbody class="fw-200">
            <?php foreach ($hymns as $hymn): ?>
                <tr data-id="<?php echo $hymn['id']; ?>" data-title="<?php echo htmlspecialchars($hymn['title']); ?>">
                    <td><input type="checkbox" class="song-checkbox"></td><td><?php echo str_pad($hymn['id'], 3, '0', STR_PAD_LEFT); ?></td><td class="title-cell"><?php echo htmlspecialchars($hymn['title']); ?></td><td><button class="btn-play-song">바로재생</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <nav aria-label="Page navigation mt3">
        <ul class="pagination mt-2 fs-12">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">이전</a>
                </li>
            <?php endif; ?>
            <?php
            // Limit the number of page links shown
            $max_pages_to_show = 5;
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $start_page + $max_pages_to_show - 1);

            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">다음</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>

<div class="lyrics mx-20 mb80" id="lyrics-container" style="display: none;">
    <div style="text-align:left;" class="mb-3">
        <button class="fs-12 bg-gray90 rounded-pill notosans p5" id="btn-back-to-playlist">
            <i class="fas fa-arrow-left"></i> 재생목록으로 돌아가기
        </button>
    </div>
    <h6 id="lyrics-title"></h6>
    <hr class="border-1">
    <div id="lyrics-content"></div>
</div>

<!-- Include jQuery and Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<script>
    let selectedSongs = [];
    let hymnsData = <?php
        // Fetch all hymns for play all functionality
        $all_hymns_stmt = $conn->prepare("SELECT id, title FROM hymns ORDER BY id ASC");
        $all_hymns_stmt->execute();
        $all_hymns_result = $all_hymns_stmt->get_result();
        $all_hymns = $all_hymns_result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($all_hymns);
    ?>;
    let isPlayingSelected = false;
    let isPlayingAll = false;
    let currentIndex = 0;

    $(document).ready(function () {
        // Set initial song ID
        $('#audio-player').data('current-id', 1);

        // Play selected songs
        $('#btn-play-selected').click(function () {
            selectedSongs = [];
            $('.song-checkbox:checked').each(function () {
                let row = $(this).closest('tr');
                let songId = row.data('id');
                let songTitle = row.data('title');
                selectedSongs.push({id: songId, title: songTitle});
            });

            if (selectedSongs.length === 0) {
                alert('재생할 찬송가를 선택하세요.');
                return;
            }

            isPlayingSelected = true;
            isPlayingAll = false;
            currentIndex = 0;
            playSong(selectedSongs[currentIndex]);
        });

        // Play all songs
        $('#btn-play-all').click(function () {
            selectedSongs = hymnsData.map(hymn => ({id: hymn.id, title: hymn.title}));
            isPlayingSelected = false;
            isPlayingAll = true;
            currentIndex = 0;
            playSong(selectedSongs[currentIndex]);
        });

        // 개별 곡 재생 - 제목 또는 바로재생 버튼 클릭 시 (이벤트 위임으로 수정)
        $(document).on('click', '.title-cell, .btn-play-song', function () {
            let row = $(this).closest('tr');
            let songId = row.data('id');
            let songTitle = row.data('title');

            // 체크박스 선택
            row.find('.song-checkbox').prop('checked', true);

            isPlayingSelected = false;
            isPlayingAll = false;
            playSong({id: songId, title: songTitle});
        });

        // Show lyrics
        $('#btn-show-lyrics').click(function () {
            let currentSongId = $('#audio-player').data('current-id');
            if (!currentSongId) {
                alert('재생 중인 찬송가가 없습니다.');
                return;
            }
            fetchLyrics(currentSongId);
        });

        // Back to playlist
        $('#btn-back-to-playlist').click(function () {
            $('#lyrics-container').hide();
            $('#playlist-container').show();
        });

        // Audio ended event
        $('#audio-player').on('ended', function () {
            if (isPlayingSelected || isPlayingAll) {
                currentIndex++;
                if (currentIndex < selectedSongs.length) {
                    playSong(selectedSongs[currentIndex]);
                } else {
                    isPlayingSelected = false;
                    isPlayingAll = false;
                }
            }
        });

        // Select all checkboxes
        $('#btn-select-all').click(function () {
            $('.song-checkbox').prop('checked', true);
        });

        // Deselect all checkboxes
        $('#btn-deselect-all').click(function () {
            $('.song-checkbox').prop('checked', false);
        });

        // Individual select all checkbox in table header
        $('#select-all').click(function () {
            $('.song-checkbox').prop('checked', this.checked);
        });

        // 검색 기능
        $('#btn-search').click(function() {
            var searchTerm = $('#search-input').val();
            $.ajax({
                url: '/bible_song',
                method: 'GET',
                data: { action: 'search_hymns', term: searchTerm },
                dataType: 'json',
                success: function(data) {
                    var tbody = $('#hymn-table tbody');
                    tbody.empty();
                    $.each(data, function(index, hymn) {
                        var row = `
                            <tr data-id="${hymn.id}" data-title="${hymn.title}">
                                <td><input type="checkbox" class="song-checkbox"></td>
                                <td>${String(hymn.id).padStart(3, '0')}</td>
                                <td class="title-cell">${hymn.title}</td>
                                <td><button class="btn-play-song">바로재생</button></td>
                            </tr>
                        `;
                        tbody.append(row);
                    });
                },
                error: function(xhr, status, error) {
                    console.error('검색 중 오류 발생:', error);
                    console.log('서버 응답:', xhr.responseText);
                    alert('검색 중 오류가 발생했습니다. 콘솔을 확인해주세요.');
                }
            });
        });

        // 엔터 키로 검색 실행
        $('#search-input').keypress(function(e) {
            if (e.which == 13) {
                $('#btn-search').click();
                return false;
            }
        });
    });

    function playSong(song) {
        let songIdPadded = String(song.id).padStart(3, '0');
        let songUrl = `https://online.goodtv.co.kr/hymn/new/${songIdPadded}.mp3`;
        $('#audio-player').attr('src', songUrl);
        $('#song-title').text(`제${song.id}장 ${song.title}`);
        $('#audio-player').data('current-id', song.id);
        $('#audio-player')[0].play();

        // Highlight the playing song
        $('tr').removeClass('selected');
        $(`tr[data-id='${song.id}']`).addClass('selected');
    }

    function fetchLyrics(songId) {
        $.ajax({
            url: '/bible_song', // URL matches the router
            method: 'GET',
            dataType: 'json', // Expecting JSON response
            data: { action: 'fetch_lyrics', id: songId },
            success: function (data) {
                if (data) {
                    $('#lyrics-title').text(`제${data.id}장 ${data.title}`);
                    let content = '';
                    for (let i = 1; i <= 6; i++) {
                        let verse = data[`verse${i}`];
                        if (verse && verse !== 'None') {
                            content += `<p><strong>[${i}절]</strong><br>${verse.replace(/-/g, '')}</p>`;
                        }
                    }
                    $('#lyrics-content').html(content);
                    $('#playlist-container').hide();
                    $('#lyrics-container').show();
                } else {
                    alert('가사를 불러올 수 없습니다.');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('가사를 불러오는 중 오류가 발생했습니다.');
            }
        });
    }
</script>


</body>
</html>

<?php include __DIR__ . '/../includes/footer.php'; ?>