<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = '회원가입';
include __DIR__ . '/../includes/header.php';

$conn = db_connect();

// 추천인 코드 처리
$referral_code = isset($_GET['ref']) ? $_GET['ref'] : '';
$referrer_info = '';
if ($referral_code) {
    $stmt = $conn->prepare("SELECT name, referral_code FROM users WHERE referral_code = ?");
    $stmt->bind_param("s", $referral_code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $referrer_info = $row['name'] . ' (' . $row['referral_code'] . ')';
    }
}

// 소속단체 목록 가져오기
$org_query = "SELECT * FROM organizations";
$org_result = $conn->query($org_query);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    //$email = trim($_POST['email']);
    $email = 'temp_' . time() . '@kfandom.com';

    $phone = preg_replace("/[^0-9]/", "", $_POST['phone']);
    $phone = substr($phone, 0, 3) . '-' . substr($phone, 3, 4) . '-' . substr($phone, 7);
    
    //$organization = $_POST['organization'] === 'other' ? trim($_POST['other_organization']) : $_POST['organization'];
    $organization = 'temp_org';

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $referrer_code = trim($_POST['referrer_code']);
    $agree = isset($_POST['agree']) ? $_POST['agree'] : '';

    try {
        if ($agree !== 'Y') {
            throw new Exception("개인정보 수집 및 이용에 동의해주세요.");
        }

        $conn->begin_transaction();

        // 중복 체크
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR phone = ?");
        $stmt->bind_param("ss", $email, $phone);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            throw new Exception("이미 사용 중인 이메일 또는 전화번호입니다.");
        }

        // 추천인 코드 확인
        if (empty($referrer_code)) {
            $referrer_code = 'kdca'; // 기본 회사 코드
        }

        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $referrer_code);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows == 0) {
            throw new Exception("유효하지 않은 추천인 코드입니다.");
        }
        $stmt->bind_result($referred_by);
        $stmt->fetch();
        $stmt->close();

        // 사용자 정보 저장
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, organization, password, referred_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $name, $email, $phone, $organization, $password, $referred_by);
        
        if (!$stmt->execute()) {
            throw new Exception("사용자 정보 저장 중 오류가 발생했습니다: " . $stmt->error);
        }

        $user_id = $conn->insert_id;
        $new_referral_code = generateReferralCode($user_id);
        $new_referral_link = SITE_URL . "/join?ref=" . $new_referral_code;
        $qr_code = generateQRCode($new_referral_link);
        
        $stmt = $conn->prepare("UPDATE users SET referral_code = ?, referral_link = ?, qr_code = ? WHERE id = ?");
        $stmt->bind_param("sssi", $new_referral_code, $new_referral_link, $qr_code, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("추천 코드 및 QR 코드 업데이트 중 오류가 발생했습니다: " . $stmt->error);
        }

        $conn->commit();

        $success = "회원등록이 완료되었습니다.";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "회원가입 중 오류가 발생했습니다: " . $e->getMessage();
        error_log("Join error: " . $e->getMessage());
    }
}
?>

<style>
.join-container {
    max-width: 600px;
    margin: 0 auto;
    padding:0px 30px;
    overflow-y: auto;
    height: calc(100vh - 130px);
}

.form-group {
    margin-bottom: 15px;
}

.form-label {
    display: block;
    color: #d4af37;
    margin-bottom: 5px;
    font-size: 0.9rem;
    font-family: noto sans kr, sans-serif;
}

.form-control {
    width: 100%;
    padding: 8px;
    border: 1px solid gray;
    background-color: #333;
    color: #fff;
    border-radius: 4px;
}

.btn-gold {
    background: linear-gradient(to right, #d1a14b, #593a03);
    border: none;
    color: #000;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    width: 100%;
    display: block;
    margin: 30px auto;
}

.error {
    color: #ff6b6b;
    margin-bottom: 10px;
}

.success {
    color: #4CAF50;
    margin-bottom: 20px;
    text-align: center;
    font-size: 1.1rem;
}

.check-button {
    background-color: gray;
    color: white;
    padding: 5px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin-left: 10px;
    font-size: 0.8rem;
    width: 25%;
}

.password-group {
    display: flex;
    justify-content: space-between;
}

.password-group .form-group {
    width: 48%;
}

#email,
#phone {
    width: 75%;
}

.validation-message {
    color: #4CAF50;
    margin-top: 5px;
    font-size: 0.8rem;
}

.referral-group {
    margin-bottom: 20px;
}

.referral-input {
    margin-bottom: 10px;
}

.referral-search {
    display: flex;
    margin-bottom: 10px;
}

.referral-search input {
    flex-grow: 1;
}

.referral-search button {
    width: auto;
    margin: 0;
}

.referral-results {
    background-color: transparent;
    border: 0px solid #555;
    text-align: right;
    border-radius: 4px;
    max-height: 150px;
    overflow-y: auto;
    font-size: 0.9rem;
    color: gray;
}

.referral-result-item {
    padding: 8px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.referral-result-item:hover {
    background-color: #444;
}

.privacy-agreement {
    background-color: #838080;
    padding: 10px;
    margin-bottom: 20px;
    border-radius: 4px;
    height: 100px;
    overflow-y: scroll;
}

.privacy-agreement p {
    color: #fff;
    font-size: 0.75rem;
    margin-bottom: 10px;
    line-height: 1.3;
    padding: 0;
    margin: 10 0;
}

.agree-checkbox {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
}

.agree-checkbox input {
    margin-right: 10px;
}

.agree-checkbox label {
    color: #216705;
    font-size: 0.95rem;
}
</style>

<div class="join-container mt50 mb100 rem-07">


    <?php if ($error): ?>
    <p class="error"><?php echo $error; ?></p>
    <?php elseif ($success): ?>
    <p class="success"><?php echo $success; ?></p>
    <a href="/" class="btn-gold">홈으로</a>
    <?php else: ?>


    <form method="post" action="" id="joinForm">
        <div class="form-group">
            <label for="name" class="form-label">성명</label>
            <input type="text" id="name" name="name" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="phone" class="form-label">전화번호</label>
            <div style="display: flex;">
                <input type="tel" id="phone" name="phone" class="form-control" required>
                <button type="button" class="check-button" onclick="checkDuplicate('phone')">중복확인</button>
            </div>
            <div id="phone-validation" class="validation-message"></div>
        </div>


        <? /*
                <div class="form-group">
            <label for="email" class="form-label">이메일</label>
            <div style="display: flex;">
                <input type="email" id="email" name="email" class="form-control" required>
                <button type="button" class="check-button" onclick="checkDuplicate('email')">중복확인</button>
            </div>
            <div id="email-validation" class="validation-message"></div>
        </div>

        <div class="form-group">
            <label for="organization" class="form-label">소속단체</label>
            <select id="organization" name="organization" class="form-control" required>
                <?php while ($row = $org_result->fetch_assoc()): ?>
        <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
        <?php endwhile; ?>
        <option value="other">기타</option>
        </select>
        <input type="text" id="other_organization" name="other_organization" class="form-control"
            style="display: none; margin-top: 10px;" placeholder="기타 소속단체 입력">
</div>
*/ ?>
<div class="password-group">
    <div class="form-group">
        <label for="password" class="form-label">비밀번호</label>
        <input type="password" id="password" name="password" class="form-control" required>
    </div>
    <div class="form-group">
        <label for="confirm_password" class="form-label">비밀번호 확인</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
    </div>
</div>
<div id="password-validation" class="validation-message"></div>

<div class="form-group referral-group">
    <label for="referrer" class="form-label">추천인 (검색후선택)</label>

    <div style="display: flex;">
        <div class="referral-search" style="width: 55%; margin-left: 0px;">
            <input type="text" id="referralSearch" class="form-control"
                style="width: 55%; background-color: #fff; color: #000;" placeholder="추천인이름 검색"
                oninput="this.value = this.value.replace(/\s/g, '')">
            <button type="button" class="check-button" style="width: 30%;" onclick="searchReferral()">검색</button>
        </div>

        <div class="referral-input" style="width:40%; margin-left: 20px;">
            <input type="text" id="referrer" name="referrer" class="form-control"
                value="<?php echo htmlspecialchars($referrer_info); ?>" readonly
                style="background-color: #000; border-color: #1c180f; color: #fff; pointer-events: none;">
            <input type="hidden" id="referrer_code" name="referrer_code"
                value="<?php echo htmlspecialchars($referral_code); ?>">
        </div>

    </div>

    <div id="referralResults" class="referral-results text-left text-orange border-orange"> <span class="text-orange fs-12"> 추천인 검색후 선택하여 등록하세요 ></span>
    </div>

</div>


<div class="privacy-agreement  mt30">
    <p>* 이용자가 제공한 모든 정보는 다음의 목적을 위해 활용하며, 하기 목적 이외의 용도로는 사용되지 않습니다.</p>
    <p>1. 개인정보 수집 항목 및 수집·이용 목적</p>
    <p>① 수집 항목 (필수항목)<br>
        - 이름, 휴대전화번호 가입 신청서에 기재된 정보 또는 신청자가 제공한 정보<br>
        ② 수집 및 이용 목적<br>
        - 예수 세례주화 프로젝트 회원 확인 및 가입<br></p>
    <p> 2. 개인정보 보유 및 이용기간</p>
    <p>- 수집·이용 동의일로부터 개인정보의 수집·이용목적을 달성할 때까지</p>
    <p>3. 동의거부권리</p>
    <p> - 귀하께서는 본 안내에 따른 개인정보 수집, 이용에 대하여 동의를 거부하실 권리가 있습니다.<br>
        다만, 귀하가 개인정보의 수집/이용에 동의를 거부하시는 경우에는 가입을 할수 없음을 알려드립니다.</p>
</div>

<div class="agree-checkbox">
    <input type="checkbox" id="agree" name="agree" value="Y" required>
    <label for="agree">개인정보 수집/이용에 동의합니다.</label>
</div>

<button type="submit" class="btn-gold">가입하기</button>
</form>
<?php endif; ?>
</div>

<script>
document.getElementById('phone').addEventListener('input', function(e) {
    var x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,4})(\d{0,4})/);
    e.target.value = !x[2] ? x[1] : x[1] + '-' + x[2] + (x[3] ? '-' + x[3] : '');
});

function checkDuplicate(field) {
    var value = document.getElementById(field).value;
    if (!value) {
        alert(field === 'email' ? "이메일을 입력해주세요." : "전화번호를 입력해주세요.");
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/pages/check_duplicate.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                var validationElement = document.getElementById(field + '-validation');
                if (response.duplicate) {
                    validationElement.textContent = field === 'email' ? "이미 사용 중인 이메일입니다. 다른 이메일을 사용하세요." :
                        "이미 사용 중인 전화번호입니다. 다른 번호를 사용하세요.";
                    validationElement.style.color = '#ff6b6b';
                } else {
                    validationElement.textContent = "사용 가능합니다.";
                    validationElement.style.color = '#4CAF50';
                }
            } catch (e) {
                console.error('JSON 파싱 오류:', e, 'Response:', xhr.responseText);
                alert('서버 응답을 처리하는 중 오류가 발생했습니다.');
            }
        } else {
            console.error('Server error:', xhr.status, xhr.statusText);
            alert('서버 오류가 발생했습니다. 다시 시도해주세요.');
        }
    };
    xhr.onerror = function() {
        console.error('Network error');
        alert('네트워크 오류가 발생했습니다. 다시 시도해주세요.');
    };
    xhr.send(field + '=' + encodeURIComponent(value));
}

document.getElementById('organization').addEventListener('change', function() {
    var otherField = document.getElementById('other_organization');
    if (this.value === 'other') {
        otherField.style.display = 'block';
        otherField.required = true;
    } else {
        otherField.style.display = 'none';
        otherField.required = false;
    }
});

document.getElementById('joinForm').addEventListener('submit', function(e) {
    var password = document.getElementById('password').value;
    var confirmPassword = document.getElementById('confirm_password').value;
    var validationElement = document.getElementById('password-validation');
    var agreeCheckbox = document.getElementById('agree');

    if (password !== confirmPassword) {
        e.preventDefault();
        validationElement.textContent = '비밀번호가 일치하지 않습니다. 다시 입력해주세요.';
        validationElement.style.color = '#ff6b6b';
    } else {
        validationElement.textContent = '';
    }

    if (!agreeCheckbox.checked) {
        e.preventDefault();
        alert('개인정보 수집 및 이용에 동의해주세요.');
    }
});

document.getElementById('confirm_password').addEventListener('input', function() {
    var password = document.getElementById('password').value;
    var confirmPassword = this.value;
    var validationElement = document.getElementById('password-validation');

    if (password === confirmPassword) {
        validationElement.textContent = '비밀번호가 일치합니다.';
        validationElement.style.color = '#4CAF50';
    } else {
        validationElement.textContent = '비밀번호가 일치하지 않습니다.';
        validationElement.style.color = '#ff6b6b';
    }
});

function searchReferral() {
    var name = document.getElementById('referralSearch').value;
    if (!name) {
        alert("추천인 이름을 입력해주세요.");
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/pages/search_referral.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            var results = JSON.parse(xhr.responseText);
            displayReferralResults(results);
        } else {
            alert('검색 중 오류가 발생했습니다.');
        }
    };
    xhr.send('name=' + encodeURIComponent(name));
}

function displayReferralResults(results) {
    var resultsDiv = document.getElementById('referralResults');
    resultsDiv.innerHTML = '';
    if (results.length === 0) {
        resultsDiv.innerHTML = '<p>검색 결과가 없습니다.</p>';
        return;
    }
    results.forEach(function(user) {
        var userDiv = document.createElement('div');
        userDiv.className = 'referral-result-item';
        var maskedPhone = maskPhoneNumber(user.phone);
        userDiv.innerHTML = user.name + ' (' + maskedPhone + ')';
        userDiv.onclick = function() {
            selectReferral(user.name, user.referral_code);
        };
        resultsDiv.appendChild(userDiv);
    });
}

function maskPhoneNumber(phone) {
    var parts = phone.split('-');
    if (parts.length === 3) {
        return parts[0] + '-****-' + parts[2];
    }
    return phone;
}

function selectReferral(name, code) {
    document.getElementById('referrer').value = name;
    document.getElementById('referrer_code').value = code;
    document.getElementById('referralResults').innerHTML = '';
    document.getElementById('referralSearch').value = '';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>