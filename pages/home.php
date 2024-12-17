<?php
session_start();
$pageTitle = '예수 세례주화 NFT';
$hideHeader = true;
include __DIR__ . '/../includes/header.php';
?>

<?php
$pageTitle = '예수세례주화 1626 by KAFANDOM';
include 'includes/header.php';
?>


<!-- 오디오 엘리먼트 추가 (carousel-container 바로 위에 배치) -->
<!-- <audio id="background-music" loop>
    <source src="assets/lovebit.mp3" type="audio/mpeg">
    Your browser does not support the audio element.
</audio> -->



<div class="carousel-container">
    <div class="carousel">
        <img src="assets/images/baptism_01.png" alt="Baptism 1">
        <img src="assets/images/baptism_02.png" alt="Baptism 2">
        <img src="assets/images/baptism_03.png" alt="Baptism 3">
        <img src="assets/images/baptism_04.png" alt="Baptism 4">
        <img src="assets/images/baptism_05.png" alt="Baptism 5">
        <img src="assets/images/baptism_06.png" alt="Baptism 6">
        <img src="assets/images/baptism_07.png" alt="Baptism 7">
        <img src="assets/images/baptism_08.png" alt="Baptism 8">
        <img src="assets/images/baptism_09.png" alt="Baptism 9">
    </div>
    <button class="carousel-button prev">&#10094;</button>
    <button class="carousel-button next">&#10095;</button>
</div>


<!-- 음악 제어 버튼 (company-info div 위에 배치) -->
<!-- <div style="position: fixed; bottom: 120px; left: 50%; transform: translateX(-50%); z-index: 1000;">
    <button id="music-toggle" class="btn-outline fs-12" style="background-color: transparent; color: white; border: 1px solid rgba(255, 255, 255, 0.5); padding: 8px 16px; border-radius: 20px; cursor: pointer;">찬양 OFF</button>
</div> -->


<div class="company-info notosans border-t-1 border-white"
    style="position: fixed; bottom: 55px; left: 0; right: 0; background-color: transparent; padding: 15px; text-align: center; z-index: 1000;">
    <h3 style="font-size: 1.2em; margin-bottom: 5px; color:#d4af37; line-height:0.7;" class="fw-700">(주)케이팬덤 <span
            class="fs-16 fw-400">T.1533-3790</span></h3>

    <div style="font-size: 0.7em; margin-top: 5px; color: white; font-weight:100" class="notosans">
        계좌: KB국민은행 771301-01-847437 예금주: (주)케이팬덤
        <span class="fs-13 fw-400" id="copyBtn" aria-label="계좌번호 복사">
            <i class="fas fa-copy"></i>
        </span>
        <!-- Toast 알림 -->
        <div id="toast" class="toast"
            style="background-color: #ffa500; color: #000; font-size: 12px;  border-radius: 50px; ">계좌번호가 복사되었습니다.</div>

        <script>
        document.getElementById('copyBtn').addEventListener('click', function() {
            const textToCopy = 'KB국민은행 771301-01-847437 예금주: (주)케이팬덤';
            // 클립보드에 텍스트 복사
            navigator.clipboard.writeText(textToCopy).then(function() {
                showToast('계좌번호가 복사되었습니다.');
            }).catch(function(err) {
                alert('계좌번호 복사에 실패했습니다. 다시 시도해주세요.');
                console.error('복사 실패:', err);
            });
        });

        // Toast 알림 표시 함수
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast show';
            setTimeout(function() {
                toast.className = toast.className.replace('show', '');
            }, 2000); // 3초 후에 사라짐
        }
        </script>


    </div>
</div>
</div>


<script>
var clipboard = new ClipboardJS('.copy-btn');
clipboard.on('success', function(e) {
    alert('계좌번호 복사됨');
    e.clearSelection();
});
clipboard.on('error', function(e) {
    console.error('복사 실패:', e.action);
});
</script>

<head>
    <link rel="manifest" href="/manifest.json">
    <meta property="og:url" content="https://1626.lidyahk.com" />
    <meta property="og:title" content="예수 세례주화 NFT 프로젝트" />
    <meta property="og:description" content="소중한 예수 세례주화를 소유하세요!" />
    <meta property="og:image" content="https://1626.lidyahk.com/assets/images/baptism_01.png" />
</head>
<!-- 회사명 고정 표시 -->
<div style="position: fixed; top: 10px; left: 10px; z-index: 9999; font-family: 'Noto Sans KR', sans-serif;">
    <span style="font-size: 18px; font-weight: bold; color: #ffffff;">(주)케이팬덤</span>
</div>

<!-- 모달 창 -->
<div id="addToHomeModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h4 class="fw-700">홈 화면에 추가하는 방법</h4>
        <h5 class="fw-700 notosans">삼성 기종:</h5>
        <ol>
            <li>하단 "홈화면" 선택</li>
            <li>우측 하단 점 3개(...) 선택</li>
            <li>"다른 브라우저로 열기" 선택</li>
            <li>"+" 현재 페이지 추가 선택</li>
            <li>"홈화면" 선택</li>
            <li>[추가] 버튼 선택</li>
        </ol>
        <h5 class="fw-700 notosans">아이폰:</h5>
        <ol>
            <li>하단 "홈화면" 선택</li>
            <li>하단 공유하기 아이콘 선택</li>
            <li>"홈화면에 추가" 선택</li>
            <li>상단 [추가] 선택</li>
        </ol>
        <div style="display: flex; justify-content: space-between;">
            <a href="/home_add" class="btn btn-primary" style="flex: 2; margin-right: 10px;">영상으로 보기</a>
            <button id="closeModal" class="btn-outline fs-13" style="flex: 1;">닫기</button>
        </div>
    </div>
</div>

<!-- 홈 화면 추가 버튼 -->
 <div style="position: fixed; top: 10px; right: 10px; z-index: 9999;">
    <a href="/notice" class="btn-outline fs-12 bg-yellow10 underline-none">공지,뉴스,이벤트</a>
    <a href="/home_add" class="btn-outline fs-12 underline-none">홈화면 추가</a>
</div>






<!--4. 화면시작시 찬양시작 -->

<script>
        document.addEventListener('DOMContentLoaded', function() {
            const audio = document.getElementById('background-music');
            const musicToggleBtn = document.getElementById('music-toggle');
            
            function updateMusicToggleButton() {
                musicToggleBtn.textContent = audio.paused ? '찬양 ON' : '찬양 OFF';
            }

            // 페이지 로드 즉시 음악 재생 시도
            const playPromise = audio.play();
            
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    // 자동 재생 성공
                    console.log('Audio autoplay successful');
                    musicToggleBtn.textContent = '찬양 OFF';
                }).catch(error => {
                    // 자동 재생 실패 시 수동 재생 필요
                    console.log('Audio autoplay failed:', error);
                    audio.pause();
                    musicToggleBtn.textContent = '찬양 ON';
                    
                    // 사용자 상호작용이 필요한 경우를 위한 클릭 이벤트 리스너
                    document.addEventListener('click', function initialPlay() {
                        audio.play().then(() => {
                            musicToggleBtn.textContent = '찬양 OFF';
                            // 초기 클릭 이벤트 리스너 제거
                            document.removeEventListener('click', initialPlay);
                        });
                    }, { once: true });
                });
            }

            // 음악 토글 버튼 클릭 이벤트
            musicToggleBtn.addEventListener('click', function() {
                if (audio.paused) {
                    audio.play().then(() => {
                        musicToggleBtn.textContent = '찬양 OFF';
                    });
                } else {
                    audio.pause();
                    musicToggleBtn.textContent = '찬양 ON';
                }
            });

            // 페이지 visibility 변경 시 음악 제어
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    if (!audio.paused) {
                        audio.pause();
                        // hidden 상태에서 일시정지된 것을 표시하기 위한 플래그
                        audio.dataset.wasPlaying = 'true';
                    }
                } else {
                    // 페이지가 다시 보일 때 이전에 재생 중이었다면 재생 재개
                    if (audio.dataset.wasPlaying === 'true') {
                        audio.play().then(() => {
                            musicToggleBtn.textContent = '찬양 OFF';
                        });
                        delete audio.dataset.wasPlaying;
                    }
                }
            });
        });
</script>








<style>
        .carousel-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: calc(100% - 80px);
            overflow: hidden;
        }

        .carousel {
            display: flex;
            transition: transform 0.3s ease-out;
            height: 100%;
            touch-action: pan-y;
        }

        .carousel img {
            flex-shrink: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .carousel-button {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            z-index: 10;
        }

        .carousel-button.prev {
            left: 10px;
        }

        .carousel-button.next {
            right: 10px;
        }
</style>

<!-- 캐러셀 -->
<script>
        document.addEventListener('DOMContentLoaded', function() {
            const carousel = document.querySelector('.carousel');
            const slides = document.querySelectorAll('.carousel img');
            const prevButton = document.querySelector('.carousel-button.prev');
            const nextButton = document.querySelector('.carousel-button.next');

            const slideSequence = [0, 1, 2, 3, 4, 5, 6, 7, 8, 7, 6, 5, 4, 3, 2, 1, 0];
            let currentSequenceIndex = 0;

            let startX;
            let isDragging = false;
            let currentTranslate = 0;
            let prevTranslate = 0;

            function showSlide(index) {
                currentTranslate = -index * carousel.clientWidth;
                carousel.style.transform = `translateX(${currentTranslate}px)`;
            }

            function nextSlide() {
                currentSequenceIndex = (currentSequenceIndex + 1) % slideSequence.length;
                showSlide(slideSequence[currentSequenceIndex]);
            }

            function prevSlide() {
                currentSequenceIndex = (currentSequenceIndex - 1 + slideSequence.length) % slideSequence.length;
                showSlide(slideSequence[currentSequenceIndex]);
            }

            let interval = setInterval(nextSlide, 10000);

            prevButton.addEventListener('click', () => {
                clearInterval(interval);
                prevSlide();
                interval = setInterval(nextSlide, 10000);
            });

            nextButton.addEventListener('click', () => {
                clearInterval(interval);
                nextSlide();
                interval = setInterval(nextSlide, 10000);
            });

            carousel.addEventListener('mouseenter', () => clearInterval(interval));
            carousel.addEventListener('mouseleave', () => interval = setInterval(nextSlide, 10000));

            // 터치 이벤트 처리
            carousel.addEventListener('touchstart', touchStart);
            carousel.addEventListener('touchmove', touchMove);
            carousel.addEventListener('touchend', touchEnd);

            function touchStart(event) {
                startX = event.touches[0].clientX;
                isDragging = true;
                clearInterval(interval);
            }

            function touchMove(event) {
                if (!isDragging) return;
                const currentX = event.touches[0].clientX;
                const diff = startX - currentX;
                carousel.style.transform = `translateX(${currentTranslate - diff}px)`;
            }

            function touchEnd(event) {
                isDragging = false;
                const movedBy = startX - event.changedTouches[0].clientX;

                if (movedBy > 100) {
                    nextSlide();
                } else if (movedBy < -100) {
                    prevSlide();
                } else {
                    showSlide(slideSequence[currentSequenceIndex]);
                }

                interval = setInterval(nextSlide, 10000);
            }
        });
</script>





<!-- 5. 공지 모달 팝업 -->
<div id="updateModal" class="modal">
    <div class="modal-content bg-gray90 text-white">
         <!-- <button id="closeModal">닫기</button> -->
        <h3 class="text-orange">서버 업데이트 안내</h3>
        <p>주말동안 서버업데이트 중입니다.</p>
        <p>업데이트중에도 등록이 가능합니다.</p>
        <p>빠른시간에 완료하겠습니다.</p>
        <p>감사합니다.</p>
        <p class="notosans">케이팬덤 고객관리팀</p>
        <!-- <button id="closeModal">닫기</button> -->
    </div>
</div>



 <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 70%;
            max-width: 400px;
            text-align: center;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .modal-content h3 {
            color: #d4af37;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .modal-content p {
            margin-bottom: 5px;
            line-height: 1.3;
            font-size: 0.9em;
        }

        #closeModal {
            background-color: #d4af37;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
            font-size: 0.9em;
            width: 30%;  /* 버튼 크기를 50%로 조정 */
            margin:  0 auto;
        }

        #closeModal:hover {
            background-color: #c19b2e;
        }
</style>

<script> /*
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('updateModal');
    var closeBtn = document.getElementById('closeModal');

    // 페이지 로드 시 모달 표시
    modal.style.display = "block";

    // 닫기 버튼 클릭 시 모달 닫기
    closeBtn.addEventListener('click', function() {
        modal.style.display = "none";
    });

    // 모달 외부 클릭 시 모달 닫기
    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    });
});
*/</script>




<script>
document.addEventListener('DOMContentLoaded', function() {
    // Odometer 초기화
    let entriesOd = new Odometer({
        el: document.getElementById('current-entries'),
        value: 0,
        format: 'd',
        duration: 1500
    });

    // 모달 표시
    const prizeModal = document.getElementById('prizeModal');
    prizeModal.style.display = 'flex';

    // 실시간 데이터 업데이트 함수
    function updateEntries() {
        fetch('/prize_apply', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action:'rate'})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                entriesOd.update(data.total_entries);
            }
        })
        .catch(err => console.error('Error:', err));
    }

    // 초기 데이터 로드
    updateEntries();

    // 10초마다 데이터 업데이트
    setInterval(updateEntries, 10000);

    // 모달 외부 클릭시 닫기
    prizeModal.addEventListener('click', function(e) {
        if (e.target === prizeModal) {
            prizeModal.style.display = 'none';
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
