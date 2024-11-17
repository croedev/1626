<?php
session_start();
require_once 'includes/config.php';
$pageTitle = 'NFT 구매';

include 'includes/header.php';

$conn = db_connect();

// 현재 가격 구간 정보 가져오기
$current_tier = getCurrentPricingTier($conn);

// 전체 누적 판매 수량 가져오기 
$total_sold = getTotalSoldQuantity($conn);

?>

<!-- Odometer.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/odometer.js/0.4.8/themes/odometer-theme-default.min.css">

<style>
        .order-container {
            max-width: 500px;
            margin: 20px auto;
            margin-bottom: 250px;
            padding: 0 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }

        h2 {
            text-align: center;
            color: #d4af37;
            margin-bottom: 20px;
        }

        .meter-container {
            width: 100%;
            height: 100px;
            margin-bottom: 20px;
            border: 1px solid #d4af37;
        }

        .price-display {
              border: 1px solid #d4af37;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 10px;
       
        }

        .price-label {
            color: #d4af37;
        }

        .price-value {
            font-weight: bold;
            color: #fff;
        }

        .current-tier-info {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .current-tier-info img {
            width: 20px;
            height: 20px;
            margin-left: 5px;
        }


        .current-tier-info img {
            width: 30px;
            height: 30px;
            margin-left: 10px;
        }


        .chart-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .chart-container img {
            max-width: 100%;
            height: auto;
        }

        .btn-purchase {
            display: block;
            width: 95%;
            padding: 10px;
            border: none;
            border-radius: 5px;
            background: linear-gradient(to right, #d4af37, #f2d06b);
            color: #000;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-purchase:hover {
            transform: scale(1.02);
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
        }





    h2 {
        text-align: center;
        color: #d4af37;
        margin-bottom: 20px;
    }

    .odometer {
        font-size: 3em;
        color: #d4af37;
        margin-bottom: 20px;
        text-align: center;
    }

    .button-group {
    display: flex;
    justify-content: end;
    margin-top: 0px;
    padding-top: 0px;
}
.btn-outline {
    background-color: transparent;
    border: 1px solid #d4af37;
    color: #d4af37;
    font-size: 0.8em;
    padding: 5px 10px;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin: 2px;
}
.btn-outline:hover {
    background-color: #d4af37;
    color: #000000;
}


</style>


<div class="order-container">

<div class="button-group flex-x-end mb20 mt30">    
    <button class="btn-outline" onclick="location.href='/nft_transfer'">NFT선물하기</button>
    <button class="btn-outline" onclick="location.href='/order_list'">구매내역 보기</button>
    <button class="btn-outline" onclick="location.href='/commission'">수수료조회</button>
</div>


<div class="card bg-gray90 p20 mb30 flex-x-center outline">
  <div class="fs-13 notosans">현재 누적판매 수량</div>
    <hr>
  <div id="total-sold" class="odometer flex-x-start fs-30"> </div>    

  <div class="mt20- fs-15">전체발행량 : <span class="fs-20 text-orange">12,000,000</span> </div>
</div>



    <div class="price-display bg-gray90 card">
        <div class="price-item height50">
            <div class="price-label text-left w200">*상장(예정)가격</div>
            <div class="fs-18 flex-y-center mr20">
                3,000원
                <!-- <img src="assets/images/arrow_up.png" height="30px;" alt="상승 화살표"> -->
            </div>
        </div>

        <div class="price-item height80 bg-blue100 border-1">            
            <div class="price-label w150 text-left fs-18">*판매가격</div>
            <div class="price-value text-left fs-20 text-yellow5 mr20"><?php echo number_format($current_tier['price']); ?>원</div>                        
        </div>

      
        <div class="flex-x-end border-b1 height50 w-100 border-0 ">
           <div class="current-tier-info fs-16 w-100">
            <span class="fs-14 ml10 mr5">프리세일: <?php echo number_format($current_tier['total_quantity']); ?>개중
             <img src="assets/images/sand2.gif" alt="모래시계">
              <span class="text-orange " id="remainingQuantity"><?php echo number_format($current_tier['remaining_quantity']); ?>개 남음</span>  
                 
                                
            </div>
        </div>
     
</div>

<div class="w-100 flex-x-center mt30">
      <a href="/order_apply" class="btn-purchase text-center underline-none ">
        구매하기 <span class="fs-15">(1개당 <span class="fx-12 text-red8"><?php echo number_format($current_tier['price']); ?>원)</span></span>
    </a>
</div>



<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Odometer.js 스크립트 추가 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/odometer.js/0.4.8/odometer.min.js"></script>
<script>
$(document).ready(function() {
    var el = document.getElementById('total-sold');
    el.innerHTML = <?php echo $total_sold; ?>; // PHP에서 직접 값을 가져옴
});
</script>

<!-- 주문 폼 끝나는 부분 근처에 추가 -->
<div id="loadingMessage" style="display:none;">
    <p>현재 접수가 처리중입니다. 잠시만 기다려주세요...</p>
    <div class="spinner"></div>
</div>

<script>
document.getElementById('orderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // 로딩 메시지 표시
    document.getElementById('loadingMessage').style.display = 'block';
    
    // 폼 데이터 수집
    var formData = new FormData(this);
    
    // AJAX 요청
    fetch('order_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // 로딩 메시지 숨기기
        document.getElementById('loadingMessage').style.display = 'none';
        
        if (data.success) {
            // 성공 시 order_complete 페이지로 리다이렉트
            window.location.href = 'order_complete.php?order_id=' + data.order_id;
        } else {
            // 실패 시 에러 메시지 표시
            alert('주문 처리 중 오류가 발생했습니다: ' + data.message);
        }
    })
    .catch(error => {
        // 로딩 메시지 숨기기
        document.getElementById('loadingMessage').style.display = 'none';
        console.error('Error:', error);
        alert('주문 처리 중 오류가 발생했습니다. 다시 시도해주세요.');
    });
});
</script>

<style>
.spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 20px auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<?php include 'includes/footer.php'; ?>