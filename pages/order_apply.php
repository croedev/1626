<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/lidyahkc/dir/1626.lidyahk.com/pages/error.log');
?>

<?php
session_start();

require_once 'includes/config.php';
$conn=db_connect();

if (!isset($_SESSION['user_id'])) {
    $showModal = true;
    $user = null;
    $user_mileage = 0;
    $user_cash_points = 0;
} else {
    $showModal = false;
    $conn = db_connect();
    $user_id = $_SESSION['user_id'];

    // 사용자 정보 가져오기
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        header("Location: /login?redirect=order_apply");
        exit();
    }

    $user_mileage = $user['mileage_points'];
    $user_cash_points = $user['cash_points'];
}


// 현재 가격 구간 정보 가져오기
$current_tier = getCurrentPricingTier($conn);
if (!$current_tier) {
    $price = 0;
    $tier_error = "현재 구매 가능한 가격이 없습니다.";
} else {
    $price = $current_tier['price'];
    $tier_error = "";
}

// 상품 ID를 동적으로 가져오는 코드 추가
$stmt = $conn->prepare("SELECT id FROM products LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$productId = $product['id'];


$pageTitle = 'NFT 구매신청';
include 'includes/header.php';


?>

<!-- 모달 창 HTML 구조 -->
<?php if ($showModal): ?>
<div id="loginModal" class="modal">
    <div class="modal-content">
        <div class="logout-container">
            <div class="logout-message">회원전용공간입니다. <br>로그인 후 이용하세요.</div>
            <a href="/login" class="home-link">로그인 이동</a>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
        .card {
            background-color: #111;
            /*border: 1px solid #333; */
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .card-body {
            padding: 15px;
        }

        h1,
        h2,
        h3,
        h4,
        h5 {
            color: #d4af37;
            font-family: 'Noto Serif KR', serif;
            margin-bottom: 10px;
        }

        p,
        label {
            font-size: 0.9rem;
            color: #cccccc;
            margin-bottom: 5px;
        }

        .form-control {
            background-color: #333;
            border-color: #555;
            color: #fff;
            padding: 8px;
            border-radius: 5px;
            width: 100%;
            box-sizing: border-box;
        }

        .form-control:focus {
            background-color: #444;
            border-color: #d4af37;
            outline: none;
        }

        .btn-gold {
            background: linear-gradient(to right, #d4af37, #f2d06b);
            border: none;
            color: #000;
            font-weight: bold;
            padding: 10px;
            border-radius: 5px;
            width: 100%;
            cursor: pointer;
        }

        .btn-gold:hover {
            opacity: 0.9;
        }

        .quantity-control {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #333;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .quantity-btn {
            background-color: #555;
            color: #fff;
            border: none;
            padding: 8px 12px;
            font-size: 16px;
            cursor: pointer;
        }

        #quantity {
            background-color: #333;
            color: #fff;
            border: none;
            text-align: center;
            font-size: 16px;
            width: 40px;
        }

        .payment-tabs {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .payment-tab {
            flex: 1;
            text-align: center;
            padding: 10px;
            background-color: #333;
            color: #d4af37;
            cursor: pointer;
            border: 1px solid #d4af37;
        }

        .payment-tab.active {
            background-color: #d4af37;
            color: #000;
        }

        #paypalInfo {
            display: none;
        }

        .error-message {
            color: #ff6b6b;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        /* 모달 배경 */
        .modal {
            display: none;
            /* 기본적으로 숨김 */
            position: fixed;
            /* 화면에 고정 */
            z-index: 1000;
            /* 다른 요소보다 위에 표시 */
            left: 0;
            top: 0;
            width: 100%;
            /* 전체 화면 너비 */
            height: 100%;
            /* 전체 화면 높이 */
            overflow: auto;
            /* 내용이 넘칠 경우 스크롤 */
            background-color: rgba(0, 0, 0, 0.5);
            /* 반투명한 검은색 배경 */
        }

        /* 모달 콘텐츠 */
        .modal-content {
            position: relative;
            margin: 15% auto;
            /* 화면 중앙에 위치 */
            padding: 0;
            border: none;
            width: 80%;
            max-width: 400px;
            /* 최대 너비 */
        }

        /* 기존 logout-container 스타일 */
        .logout-container {
            padding: 20px;
            text-align: center;
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 10px;
        }

        .logout-message {
            color: #d4af37;
            font-size: 1.2em;
            margin-bottom: 20px;
        }

        .home-link {
            display: inline-block;
            background: linear-gradient(to right, #d4af37, #f2d06b);
            color: #000;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: opacity 0.3s;
        }

        .home-link:hover {
            opacity: 0.8;
        }
</style>

<div class="container p15 mb100 pb100">
    <?php if (!empty($tier_error)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $tier_error; ?>
    </div>
    <?php else: ?>

    <div class="px-20">

        <p class="mt20 fs-16 text-orange">명칭: The Baptism of Jesus, 1626 NFT(Limited)</p>
        <p>단가: <span class="rem-12 text-warning"><?php echo number_format($price); ?>원</span> (부가세 포함)</p>
        <p class="flex-start">구매수량:
        <div class="quantity-control ">
            <button class="quantity-btn" id="decrease-quantity">-</button>
            <input type="number" id="quantity" value="1" min="1" max="100" class="form-control text-center w-100">
            <button class="quantity-btn" id="increase-quantity">+</button>
        </div>
        </p>
        <p class="rem-12">총 구매금액: <span class="rem-14 text-warning"
                id="total-amount"><?php echo number_format($price); ?>원</span></p>
    </div>

    <div class="p15">
        <div class="">
            <h5 class="notosans fs-16">결제방식 선택</h5>
            <div class="payment-tabs mb0">
                <div class="payment-tab flex-center active" id="bankTab">계좌입금</div>
                <div class="payment-tab flex-center" id="pointTab">포인트결제</div>
                <!-- 간편결제 탭만 주석 처리 -->
                <!-- <div class="payment-tab" id="paypalTab">간편결제</div> -->
            </div>

            <div id="bankTransferInfo" class="border-1 mt0 p20" style="background-color:#101;">

                <div class="card-body card">
                    <div class="form-group">
                        <label for="depositorName" style="margin-right: 10px; width:100px;">입금자명 :</label>
                        <input type="text" class="form-control" id="depositorName"
                            style="width:50%;color:white; flex-grow: 1;">
                        <div id="depositorNameError" class="error-message"></div>
                    </div>

                    <button class="btn-gold" id="bank-order-button" style="margin-top: 15px;">
                        <i class="fas fa-shopping-cart"></i> 구매 신청하기
                    </button>
                </div>

                <div class="card-body card mt20">
                    <p class="rem-09 fw-700">
                        KB국민은행 771301-01-847437 <br>예금주: (주)케이팬덤
                        <button class="copy-btn" data-clipboard-text="KB국민은행 : 771301-01-847437 (주)케이팬덤"
                            style="font-size: 10px; color:white; padding: 2px 5px; margin-left: 5px; background:#95031a;">복사</button>
                    </p>
                    <p class="text-orange">입금액: <span class="text-blue3 rem-10" id="deposit-amount"
                            <?php echo number_format($price); ?>원</span></p>
                    <p class="text-orange rem-08">*위 은행계좌로 4시간 이내 입금해 주세요.</p>
                </div>


            </div>


            <!-- 포인트 결제 탭 수정 -->
            <div id="pointInfo" class="p20 border-1 mt0" style="background-color:#101; display: none;">
                <p class="text-orange rem-12">결제할 금액: <span
                        id="pointPayAmount"><?php echo number_format($price); ?>원</span></p>
                <hr>
                <div class="form-group">
                    <label for="useCashPoint">현금포인트(CP) 사용</label>
                    <div style="display: flex; align-items: center;">
                        <input type="number" id="useCashPoint" name="useCashPoint" value="0" min="0"
                            max="<?php echo $user_cash_points; ?>" style="width: 120px; color:white;"
                            class="form-control">
                        <span style="margin-left: 10px; font-size:12px;"><br>현금잔고 <span
                                style="color: white;"><?php echo number_format($user_cash_points); ?></span>원</span>
                    </div>
                </div>
                <div class="form-group mt10">
                    <label for="useMileagePoint">마일리지(MP) 사용</label>
                    <div style="display: flex; align-items: center;">
                        <input type="number" id="useMileagePoint" name="useMileagePoint" value="0" min="0"
                            max="<?php echo $user_mileage; ?>" style="width: 120px; color:white;" class="form-control">
                        <span style="margin-left: 10px;font-size:12px;"><br>마일리지잔고 <span
                                style="color: white;"><?php echo number_format($user_mileage); ?></span>원</span>
                    </div>
                </div>
                <hr>
                <p class="text-blue3 rem-11">포인트결제 합계: <span id="totalPointAmount">0원</span></p>
                <p id="pointErrorMessage" style="color: red; display: none;"></p>

                    <button class="btn-gold" id="point-order-button" style="margin-top: 15px;">
                        <i class="fas fa-shopping-cart"></i> 구매 신청하기
                    </button>
            </div>




            <div id="paypalInfo" class="p20 border-0" style="display:none;">
                <p class="rem-13 mt-4 mb-4">간편결제(예정)</p>
                <p>결제 금액: <span id="paypal-amount" class="rem-12"><?php echo number_format($price); ?>원</span> (부가세
                    포함)</p>
                <p>

                    <button class="copy-btn" data-clipboard-text="lidyaholdings@gmail.com"
                        style="font-size: 10px; padding: 2px 5px; margin-left: 5px; background:#a8956c;">복사</button>
                </p>
                <p class="text-orange">위 계정으로 결제를 진행해 주세요.</p>

                <button class="btn-gold" id="order-button" style="margin-top: 15px;">구매 신청하기</button>
            </div>




            <!-- 로딩 메시지를 위한 오버레이 추가 -->
            <div id="loadingOverlay"
                style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999;">
                <div id="loadingMessage"
                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: #fff; padding: 20px; border-radius: 5px; text-align: center;">
                    <p style="color: #d4af37; margin: 0;">주문을 처리하고 있습니다. 잠시만 기다려주세요...</p>
                </div>
            </div>

        </div>
    </div>
    <?php endif; ?>





    <!-- 모달 창 표시를 위한 JavaScript -->
    <?php if ($showModal): ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var modal = document.getElementById("loginModal");
        modal.style.display = "block"; // 모달 표시



        // 모달 외부를 클릭하면 모달 닫기

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    });
    </script>
    <?php endif; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 수량 변경 이벤트
        const decreaseQuantity = document.getElementById('decrease-quantity');
        const increaseQuantity = document.getElementById('increase-quantity');
        const quantityInput = document.getElementById('quantity');
        const totalAmount = document.getElementById('total-amount');
        const depositAmount = document.getElementById('deposit-amount');
        const paypalAmount = document.getElementById('paypal-amount');

        // 포인트 결제 관련 변수
        const pointTab = document.getElementById('pointTab');
        const pointInfo = document.getElementById('pointInfo');
        const useCashPoint = document.getElementById('useCashPoint');
        const useMileagePoint = document.getElementById('useMileagePoint');
        const pointPayAmount = document.getElementById('pointPayAmount');
        const totalPointAmount = document.getElementById('totalPointAmount');
        const pointErrorMessage = document.getElementById('pointErrorMessage');

        function updateTotal() {
            let quantity = parseInt(quantityInput.value);
            let amount = quantity * <?php echo $price; ?>;
            totalAmount.textContent = amount.toLocaleString() + '원';
            depositAmount.textContent = amount.toLocaleString() + '원';
            paypalAmount.textContent = amount.toLocaleString() + '원';
            pointPayAmount.textContent = amount.toLocaleString() + '원';
            updatePointTotal();
        }

        decreaseQuantity.addEventListener('click', function() {
            let quantity = parseInt(quantityInput.value);
            if (quantity > 1) {
                quantityInput.value = --quantity;
                updateTotal();
            }
        });

        increaseQuantity.addEventListener('click', function() {
            let quantity = parseInt(quantityInput.value);
            quantityInput.value = ++quantity;
            updateTotal();
        });

        quantityInput.addEventListener('change', function() {
            updateTotal();
        });

        // 결제 방식 탭 클릭 이벤트
        const bankTab = document.getElementById('bankTab');
        const paypalTab = document.getElementById('paypalTab');
        const bankTransferInfo = document.getElementById('bankTransferInfo');
        const paypalInfo = document.getElementById('paypalInfo');

        bankTab.addEventListener('click', function() {
            bankTab.classList.add('active');
            pointTab.classList.remove('active');
            if (paypalTab) paypalTab.classList.remove('active');
            bankTransferInfo.style.display = 'block';
            pointInfo.style.display = 'none';
            paypalInfo.style.display = 'none';
        });

        // 포인트 결제 탭 클릭 이벤트
        pointTab.addEventListener('click', function() {
            pointTab.classList.add('active');
            bankTab.classList.remove('active');
            if (paypalTab) paypalTab.classList.remove('active');
            pointInfo.style.display = 'block';
            bankTransferInfo.style.display = 'none';
            paypalInfo.style.display = 'none';
            updatePointTotal();
        });

        // 간편결제 탭 클릭 이벤트는 그대로 둡니다
        if (paypalTab) {
            paypalTab.addEventListener('click', function() {
                paypalTab.classList.add('active');
                bankTab.classList.remove('active');
                pointTab.classList.remove('active');
                paypalInfo.style.display = 'block';
                bankTransferInfo.style.display = 'none';
                pointInfo.style.display = 'none';
            });
        }

        // 캐시 포인트 입력 이벤트
        useCashPoint.addEventListener('input', function() {
            let cashPoint = parseInt(this.value) || 0;
            let totalPrice = <?php echo $price; ?> * parseInt(quantityInput.value);

            if (cashPoint > <?php echo $user_cash_points; ?>) {
                cashPoint = <?php echo $user_cash_points; ?>;
            } else if (cashPoint > totalPrice) {
                cashPoint = totalPrice;
            }

            let remainingAmount = totalPrice - cashPoint;
            useMileagePoint.value = Math.min(remainingAmount, <?php echo $user_mileage; ?>);

            this.value = cashPoint;
            updatePointTotal();
        });

        // 마일리지 포인트 입력 이벤트
        useMileagePoint.addEventListener('input', function() {
            let mileagePoint = parseInt(this.value) || 0;
            let totalPrice = <?php echo $price; ?> * parseInt(quantityInput.value);
            let cashPoint = parseInt(useCashPoint.value) || 0;

            if (mileagePoint > totalPrice - cashPoint) {
                mileagePoint = totalPrice - cashPoint;
            } else if (mileagePoint > <?php echo $user_mileage; ?>) {
                mileagePoint = <?php echo $user_mileage; ?>;
            }

            this.value = mileagePoint;
            updatePointTotal();
        });

        // 포인트 합계 계산
        function updatePointTotal() {
            let totalPrice = <?php echo $price; ?> * parseInt(quantityInput.value);
            let cashPoint = parseInt(useCashPoint.value) || 0;
            let mileagePoint = parseInt(useMileagePoint.value) || 0;
            let totalPoint = cashPoint + mileagePoint;

            document.getElementById('totalPointAmount').textContent = totalPoint.toLocaleString() + '원';

            if (totalPoint !== totalPrice) {
                document.getElementById('pointErrorMessage').textContent = '포인트 결제 합계가 결제 금액과 일치하지 않습니다.';
                document.getElementById('pointErrorMessage').style.display = 'block';
            } else {
                document.getElementById('pointErrorMessage').textContent = '';
                document.getElementById('pointErrorMessage').style.display = 'none';
            }
        }

        // 초기 포인트 합계 계산
        updatePointTotal();

        // 주문하기 버튼 클릭 이벤트
        const bankOrderButton = document.getElementById('bank-order-button');
        const pointOrderButton = document.getElementById('point-order-button');

        bankOrderButton.addEventListener('click', function() {
            processOrder('bank');
        });

        pointOrderButton.addEventListener('click', function() {
            processOrder('point');
        });

        function processOrder(paymentMethod) {
            const quantity = quantityInput.value;
            const productId = <?php echo $productId; ?>;
            let isValid = true;

            const orderData = {
                quantity: parseInt(quantity),
                price: <?php echo $price; ?>,
                paymentMethod: paymentMethod,
                productId: productId
            };

            if (paymentMethod === 'bank') {
                const depositorName = document.getElementById('depositorName');
                if (!depositorName.value.trim()) {
                    document.getElementById('depositorNameError').textContent = '입금자명을 입력해주세요.';
                    isValid = false;
                } else {
                    document.getElementById('depositorNameError').textContent = '';
                    orderData.depositorName = depositorName.value;
                }
            } else if (paymentMethod === 'point') {
                const useCashPoint = parseFloat(document.getElementById('useCashPoint').value) || 0;
                const useMileagePoint = parseFloat(document.getElementById('useMileagePoint').value) || 0;
                
                if (useCashPoint + useMileagePoint === 0) {
                    alert('사용할 포인트를 입력해주세요.');
                    isValid = false;
                } else {
                    orderData.useCashPoint = useCashPoint;
                    orderData.useMileagePoint = useMileagePoint;
                }
            }

            if (isValid) {
                // 로딩 오버레이 표시
                document.getElementById('loadingOverlay').style.display = 'block';

                fetch('/order_process', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify(orderData)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('서버 응답 오류: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    // 로딩 오버레이 숨기기
                    document.getElementById('loadingOverlay').style.display = 'none';
                    if (data.success) {
                        window.location.href = '/order_complete?order_id=' + data.order_id;
                    } else {
                        alert('주문 처리 중 오류가 발생했습니다: ' + (data.message || '알 수 없는 오류'));
                        console.error('주문 처리 중 오류:', data);
                    }
                })
                .catch((error) => {
                    // 로딩 오버레이 숨기기
                    document.getElementById('loadingOverlay').style.display = 'none';
                    console.error('Error:', error);
                    alert('주문 처리 중 예기치 않은 오류가 발생했습니다: ' + error.message);
                });
            }
        }

        var clipboard = new ClipboardJS('.copy-btn');
        clipboard.on('success', function(e) {
            e.trigger.textContent = '복사됨!';
            setTimeout(function() {
                e.trigger.textContent = '복사';
            }, 2000);
            e.clearSelection();
        });
        clipboard.on('error', function(e) {
            console.error('복사 실패:', e.action);
        });

        // 초기 가격 계산
        updateTotal();
    });
    </script>
    <?php include 'includes/footer.php'; ?>