<?php
// digifinex API 클래스

class digifinex {
    protected $baseUrl = "https://openapi.digifinex.com/v3";    
    protected $appKey;
    protected $appSecret;

    public function __construct($data) {
        $this->appKey = $data['appKey'];
        $this->appSecret = $data['appSecret'];
    }

    public function do_request($method, $path, $data = [], $needSign=false) {
        $curl = curl_init();
        $query = http_build_query($data, '', '&');
        if ($method == "POST") {
            curl_setopt($curl, CURLOPT_URL, $this->baseUrl . $path);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
        } else {
            $url = $this->baseUrl . $path;
            if(!empty($query)){
                $url .= '?' . $query;
            }
            curl_setopt($curl, CURLOPT_URL, $url);
        }

        if($needSign){
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'ACCESS-KEY: ' . $this->appKey,
                'ACCESS-TIMESTAMP: ' . time(),
                'ACCESS-SIGN: ' . $this->calc_sign($data),
            ]);
        }

        curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);

        $content = curl_exec($curl);
        curl_close($curl);
        return $content;
    }

    private function calc_sign($data = []) {
        $query = http_build_query($data, '', '&');
        $sign = hash_hmac("sha256", $query, $this->appSecret);
        return $sign;
    }
}

// 특정 코인의 usdt 가격 가져오기
function get_price_in_usdt($coin, $symbol) {
    $response = $coin->do_request('GET', '/ticker', ['symbol' => $symbol], false);
    $data = json_decode($response, true);
    return isset($data['ticker'][0]['last']) ? floatval($data['ticker'][0]['last']) : null;
}

// USDT→KRW 환율 가져오기
function get_usdt_to_krw_rate() {
    $ch = curl_init("https://api.coingecko.com/api/v3/simple/price?ids=tether&vs_currencies=krw");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($res, true);
    if (isset($json['tether']['krw'])) {
        return floatval($json['tether']['krw']);
    }
    return null;
}

/**
 * digiPrice 함수
 * @param string $symbol 코인 심볼 (예: 'btc', 'eth', 'sere')
 * @param string $currency 표시 통화 ('usdt' 또는 'krw')
 * 
 * 예:
 * digiPrice('btc', 'usdt') => "10000.00 USDT"
 * digiPrice('sere', 'krw') => "5781.23 KRW"
 */
function digiPrice($symbol, $currency = 'usdt') {
    $symbol = strtolower($symbol);   // 심볼 소문자 처리
    $currency = strtolower($currency);

    $coin = new digifinex([
        'appKey' => 'ebfccbe9a5a1b1',
        'appSecret' => '7cc706476172250e08a48040a0fe1b6e55c666c6fc',
    ]);

    // usdt시세 조회를 위해 "[코인]_usdt" 형태로 symbol생성
    $marketSymbol = $symbol . '_usdt';
    $usdt_price = get_price_in_usdt($coin, $marketSymbol);

    if ($usdt_price === null) {
        return strtoupper($symbol) . " 가격 정보를 가져올 수 없습니다.";
    }

    if ($currency === 'usdt') {
        // USDT가격 바로 리턴
        return sprintf("%.2f USDT", $usdt_price);
    } else if ($currency === 'krw') {
        // KRW환율 가져와서 변환
        $usdt_krw_rate = get_usdt_to_krw_rate();
        if ($usdt_krw_rate === null) {
            return sprintf("%.2f USDT (KRW 환율 없음)", $usdt_price);
        }
        $krw_price = $usdt_price * $usdt_krw_rate;
        return sprintf("%.2f KRW", $krw_price); // 소수점 없이 출력
    } else {
        // 지원하지 않는 통화일 경우
        return "지원하지 않는 통화입니다. (usdt 또는 krw)";
    }
}


?>