');
    const increaseQuantity = document.getElementById('increase-quantity');
    const quantityInput = document.getElementById('quantity');
    const totalAmount = document.getElementById('total-amount');
    const depositAmount = document.getElementById('deposit-amount');
    const paypalAmount = document.getElementById('paypal-amount');
    const bankTab = document.getElementById('bankTab');
    const pointTab = document.getElementById('pointTab');
    const paypalTab = document.getElementById('paypalTab');
    const bankTransferInfo = document.getElementById('bankTransferInfo');
    const pointInfo = document.getElementById('pointInfo');
    const paypalInfo = document.getElementById('paypalInfo');
    const useCashPoint = document.getElementById('useCashPoint');
    const useMileagePoint = document.getElementById('useMileagePoint');
    const pointPayAmount = document.getElementById('pointPayAmount');
    const totalPointAmount = document.getElementById('totalPointAmount');
    const pointErrorMessage = document.getElementById('pointErrorMessage');

    // 수량 관련 함수 유지
    function updateTotal() {
        let quantity = parseInt(quantityInput.value);
        let amount = quantity * <?php echo $price; ?>;
        totalAmount.textContent = amount.toLocaleString() + '원';
        depositAmount.textContent = amount.toLocaleString() + '원';
        paypalAmount.textContent = amount.toLocaleString() + '원';
        pointPayAmount.textContent = amount.toLocaleString() + '원';
        
        // 포인트 입력값 초기화 및 재계산
        useCashPoint.value = '0';
        useMileagePoint.value = '0';
        updatePointTotal();
    }

    // 수량 버튼 이벤트 리스너 유지
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

    // 탭 전환 이벤트 유지
    bankTab.addEventListener('click', function() {
        bankTab.classList.add('active');
        pointTab.classList.remove('active');
        if (paypalTab) paypalTab.classList.remove('active');
        bankTransferInfo.style.display = 'block';
        pointInfo.style.display = 'none';
        paypalInfo.style.display = 'none';
    });

    pointTab.addEventListener('click', function() {
        pointTab.classList.add('active');
        bankTab.classList.remove('active');
        if (paypalTab) paypalTab.classList.remove('active');
        pointInfo.style.display = 'block';
        bankTransferInfo.style.display = 'none';
        paypalInfo.style.display = 'none';
        updatePointTotal();
    });

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

    // 포인트 입력 처리 - 수정된 부분
    useCashPoint.addEventListener('input', function() {
        if (this.value === '') {
            this.value = '0';
        }
        
        let totalPrice = <?php echo $price; ?> * parseInt(quantityInput.value);
        let maxCashPoints = <?php echo $user_cash_points; ?>;
        let cashPoint = Math.min(parseInt(this.value) || 0, maxCashPoints, totalPrice);
        
        this.value = cashPoint;
        
        if (cashPoint > 0) {
            useMileagePoint.value = '0';
            useMileagePoint.disabled = true;
        } else {
            useMileagePoint.disabled = false;
        }
        
        updatePointTotal();
    });

    useMileagePoint.addEventListener('input', function() {
        if (this.value === '') {
            this.value = '0';
        }
        
        let totalPrice = <?php echo $price; ?> * parseInt(quantityInput.value);
        let maxMileagePoints = <?php echo $user_mileage; ?>;
        let mileagePoint = Math.min(parseInt(this.value) || 0, maxMileagePoints, totalPrice);
        
        this.value = mileagePoint;
        
        if (mileagePoint > 0) {
            useCashPoint.value = '0';
            useCashPoint.disabled = true;
        } else {
            useCashPoint.disabled = false;
        }
        
        updatePointTotal();
    });

    // 포인트 합계 계산 함수 - 수정된 부분
    function updatePointTotal() {
        let totalPrice = <?php echo $price; ?> * parseInt(quantityInput.value);
        let cashPoint = parseInt(useCashPoint.value) || 0;
        let mileagePoint = parseInt(useMileagePoint.value) || 0;
        let isValid = true;
        
        if (cashPoint > 0 && mileagePoint > 0) {
            pointErrorMessage.textContent = '캐시포인트와 마일리지포인트 중 하나만 선택하여 결제가 가능합니다.';
            pointErrorMessage.style.display = 'block';
            isValid = false;
        } else {
            let selectedPoint = cashPoint + mileagePoint;
            totalPointAmount.textContent = selectedPoint.toLocaleString() + '원';

            if (selectedPoint === 0) {
                pointErrorMessage.textContent = '사용할 포인트를 입력해주세요.';
                pointErrorMessage.style.display = 'block';
                isValid = false;
            } else if (selectedPoint !== totalPrice) {
                pointErrorMessage.textContent = '포인트 결제 금액이 결제할 금액과 일치하지 않습니다.';
                pointErrorMessage.style.display = 'block';
                isValid = false;
            } else {
                pointErrorMessage.style.display = 'none';
            }
        }
        
        return isValid;
    }

    // 주문 처리 함수 - 수정된 부분
    function processOrder(paymentMethod) {
        const quantity = parseInt(quantityInput.value);
        const productId = <?php echo $productId; ?>;
        let isValid = true;

        if (quantity < 1) {
            alert('구매 수량을 입력해주세요.');
            return;
        }

        const orderData = {
            quantity: quantity,
            price: <?php echo $price; ?>,
            paymentMethod: paymentMethod,
            productId: productId
        };

        if (paymentMethod === 'bank') {
            const depositorName = document.getElementById('depositorName').value.trim();
            if (!depositorName) {
                document.getElementById('depositorNameError').textContent = '입금자명을 입력해주세요.';
                return;
            }
            orderData.depositorName = depositorName;
        } else if (paymentMethod === 'point') {
            if (!updatePointTotal()) {
                return;
            }
            
            orderData.useCashPoint = parseFloat(useCashPoint.value) || 0;
            orderData.useMileagePoint = parseFloat(useMileagePoint.value) || 0;
        }

        // 로딩 표시
        document.getElementById('loadingOverlay').style.display = 'block';

        // API 호출
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
            document.getElementById('loadingOverlay').style.display = 'none';
            if (data.success) {
                window.location.href = '/order_complete?order_id=' + data.order_id;
            } else {
                alert(data.message || '주문 처리 중 오류가 발생했습니다.');
            }
        })
        .catch(error => {
            document.getElementById('loadingOverlay').style.display = 'none';
            console.error('Error:', error);
            alert('주문 처리 중 오류가 발생했습니다: ' + error.message);
        });
    }

    // 주문 버튼 이벤트 리스너
    document.getElementById('bank-order-button').addEventListener('click', () => processOrder('bank'));
    document.getElementById('point-order-button').addEventListener('click', () => processOrder('point'));

    // 클립보드 기능
    const clipboard = new ClipboardJS('.copy-btn');
    clipboard.on('success', function(e) {
        e.trigger.textContent = '복사됨!';
        setTimeout(() => e.trigger.textContent = '복사', 2000);
        e.clearSelection();
    });
    
    clipboard.on('error', function(e) {
        console.error('복사 실패:', e.action);
    });

    // 초기화
    updateTotal();
});
</script>


  <?php include 'includes/footer.php'; ?>