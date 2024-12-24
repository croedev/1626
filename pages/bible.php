<?php
session_start();
$pageTitle = '성경말씀 듣기';
include 'includes/header.php';
?>

<style>
    .bible-content {
        position: fixed;
        top: 50px; /* 상단바 높이 */
        bottom: 80px; /* 하단바 높이 */
        left: 0;
        right: 0;
        overflow-y: auto;
        background-color: #000000;
        color: #ffffff;
        font-family: 'Noto Sans KR', sans-serif;
    }
    .bible-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 10px;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    h1 {
        color: #d4af37;
        text-align: center;
        font-size: 1.5em;
        margin: 5px 0;
           font-family: 'Noto Serif KR', serif;
    }
    h2 {
        color: #d4af37;
        text-align: center;
        font-size: 1.2em;
        margin: 5px 0;   font-family: 'Noto Serif KR', serif;
    }
    .bible-sections {
        display: flex;
        flex: 1;
        gap: 10px;
    }
    .section {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .bible-list {
        list-style-type: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    .bible-list li {
        flex: 0 0 calc(50% - 5px);
    }
    .bible-list a {
        color: #ffffff;
        text-decoration: none;
        transition: color 0.3s ease;
        font-size: 0.8em;
        display: block;
        padding: 2px 5px;
    }
    .bible-list a:hover {
        color: #d4af37;
    }

    @media (max-width: 768px) {
        .bible-sections {
            flex-direction: column;
        }
        .bible-list li {
            flex: 0 0 calc(33.33% - 5px);
        }
        .bible-list a {
            font-size: 0.7em;
        }
    }

    @media (max-width: 480px) {
        .bible-list li {
            flex: 0 0 calc(50% - 5px);
        }
        .bible-list a {
            font-size: 0.6em;
        }
    }
</style>

<div class="bible-content">
    <div class="bible-container">
     

        <div class="bible-sections">
            <div class="section text-center">
                <h2>구약</h2>
                <ul class="bible-list mt-2">
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b01.htm" target="_blank">창세기</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b02.htm" target="_blank">출애굽기</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b03.htm" target="_blank">레위기</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b04.htm" target="_blank">민수기</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b05.htm" target="_blank">신명기</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b06.htm" target="_blank">여호수아</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b07.htm" target="_blank">사사기</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b08.htm" target="_blank">룻기</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b09.htm" target="_blank">사무엘상</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b10.htm" target="_blank">사무엘하</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b11.htm" target="_blank">열왕기상</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b12.htm" target="_blank">열왕기하</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b13.htm" target="_blank">역대상</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b14.htm" target="_blank">역대하</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b15.htm" target="_blank">에스라</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b16.htm" target="_blank">느헤미야</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b17.htm" target="_blank">에스더</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b18.htm" target="_blank">욥기</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b19.htm" target="_blank">시편</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b20.htm" target="_blank">잠언</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b21.htm" target="_blank">전도서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b22.htm" target="_blank">아가</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b23.htm" target="_blank">이사야</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b24.htm" target="_blank">예레미야</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b25.htm" target="_blank">예레미야애가</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b26.htm" target="_blank">에스겔</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b27.htm" target="_blank">다니엘</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b28.htm" target="_blank">호세아</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b29.htm" target="_blank">요엘</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b30.htm" target="_blank">아모스</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b31.htm" target="_blank">오바댜</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b32.htm" target="_blank">요나</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b33.htm" target="_blank">미가</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b34.htm" target="_blank">나훔</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b35.htm" target="_blank">하박국</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b36.htm" target="_blank">스바냐</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b37.htm" target="_blank">학개</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b38.htm" target="_blank">스가랴</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b39.htm" target="_blank">말라기</a></li>
                </ul>
            </div>
            <hr>
            <div class="section text-center">
                <h2>신약</h2>
                <ul class="bible-list mt-2">
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b40.htm" target="_blank">마태복음</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b41.htm" target="_blank">마가복음</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b42.htm" target="_blank">누가복음</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b43.htm" target="_blank">요한복음</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b44.htm" target="_blank">사도행전</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b45.htm" target="_blank">로마서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b46.htm" target="_blank">고린도전서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b47.htm" target="_blank">고린도후서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b48.htm" target="_blank">갈라디아서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b49.htm" target="_blank">에베소서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b50.htm" target="_blank">빌립보서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b51.htm" target="_blank">골로새서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b52.htm" target="_blank">데살로니가전서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b53.htm" target="_blank">데살로니가후서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b54.htm" target="_blank">디모데전서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b55.htm" target="_blank">디모데후서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b56.htm" target="_blank">디도서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b57.htm" target="_blank">빌레몬서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b58.htm" target="_blank">히브리서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b59.htm" target="_blank">야고보서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b60.htm" target="_blank">베드로전서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b61.htm" target="_blank">베드로후서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b62.htm" target="_blank">요한일서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b63.htm" target="_blank">요한이서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b64.htm" target="_blank">요한삼서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b65.htm" target="_blank">유다서</a></li>
                    <li><a href="https://www.wordproject.org/bibles/audio/11_korean/b66.htm" target="_blank">요한계시록</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>