therField = document.getElementById('other_organization');
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