아래의 사람들은 admin 권한으로 수량을 입고해 주었고, 무료로 입고해주었으나, 시스템은 제품구매로 인식해서 1개당 2000원의 매출로 인정하였고, 이로인해 수당이 발생하였다. 직판수당, 추천수당 기타수당이 발생하였다.
따라서 이 매출로 인해 발생한 수당을 찾아 삭감을 해야 하는데,,

commissions 테이블에는 user_id 와 order_id 가 있어서 수수료가 누구에게 어떤 매출에 의해서 발생했는지 찾아낼수가 있다.
orders 테이블에는 order_id 와 product_id 가 있어서 어떤 제품이 팔렸는지 찾아낼수가 있다.

1. 먼저 수당이 발생한 사람들을 찾아야 한다.
아래의 <이름과 연락처>를 활용하여 users테이블에서 user_id를 찾아낸다.

2. 그 사람 user_id를 활용하여 orders테이블에서 그사람이 구매한 제품을 찾는데, 
3. orders테이블에는 ueser_id가 있어 찾아낸 user_id가 구매한 주문건을 찾아낸다, 그다음 찾아낸 그 사람의 payment_method가 admin 또는 admin1으로 입력된게 관리자가 무료로 지급하여 발생한 주문건이다. 왜냐하면 모든 유저는 payment_method가 bank 또는 point 이기 때문이다.
4.따라서 payment_method가 admin 또는 admin1 인 주문건을 찾아내고 특히 수량이 아래 정해진 수량인 것을 찾아낸다.
이 찾아낸 주문건은 관리자가 무료로 지급하여 발생한 주문건이므로 orders테이블에서 해당 order_id를 찾아 commissions 테이블에서 그 주문건에 의해 발생한 수수료를 찾아낸다.

5. 그 수수료를 commissions 테이블에서 찾아내고 그 수수료를 삭감한다.
6.삭감방법은 레코드는 그대로 두고, 그 발생한 수수료 만큼 새로 마이너스 수수료를 발생시키는 것이다.  따라서 commissions 테이블에 동일한 레코드를 복사하여 생성하도록 하고, 컬럼값중 수수료와 관련된 
amount값은 -amount 값으로, cash_point_amount 값은 마이너스 - cash_point_amount 컬럼값을 생성하고, mileage_point_amount컬럼값도 마이너스(-)mileage_point_amount로 생성해서 저장한다.
이후 받아간 사람의 user_id를 찾아내고 그 users테이블에서 그 user_id에 대한 수수료를 삭감한다.
users테이블에서 그 user_id를 찾아낸다. users테이블에 그 user_id의 commission_total 컬럼에서 그 수수료를 삭감한다. 마찬가지로 cash_points 컬럼과 mileage_points 컬럼에서 그 수수료를 삭감한다.
문제는 무료로 지급된 주문건으로 발생한 수수료는 cash_point_amount 와 mileage_point_amount 두개의 포인트로 나누어 50%씩 나누어 저장되고, 똑같이 users테이블에서도 cash_points 와 mileage_points 두개의 포인트로 나누어 50%씩 나누어 저장되는데, 이미 발생한 cash_points 와 mileage_points 포인트들을 사용한 경우, 그 포인트로 인해 또다시 수당이 발생한다는 것이다.
그것까지 추적하기 어려우니, 수당을 받은 사람의 수수료를 삭감하고, 그 수수료에 대한 포인트들도 삭감하는 것으로 한다.
(이 부분에 대한 당신의 아이디어가 있는가?)

[삭감처리의 예시단게]

즉 만약 관리자가 김미선에게 6000개를 무료로 지급했다면, 
주문테이블에서 매출이 개당 2000원이므로 * 60000개= 1200만원의 매출이 발생한 것으로 기록되어 있을것이고, payment_method가 admin 또는 admin1일 것이다. 즉 bank가 아니면 된다. 그에 맞는 order_id를 찾는다.
그 매출 1200만원을 가지고 수수료계산 함수에 의거 수수료가 발생한 사람들이 있을것이다. 
직판수수료 또는 추천수수료가 발생했을 것이다. 모든 수수료는 발생하면 commissions테이블 모든 수수료가 저장된다. 어디매출로 발생했냐는 order_id를 통해서, 그 수수료를 누가 받아갔냐는 user_id에 기록된다. 따라서 그 order_id로 발생한 수수료가 commissions 테이블에 추천수수료든, 직판수수료든 저장이 되었을 것이다. 따라서 이 수수료를 받아가는 사람이 있을 것이다. 즉 직판수수료가 그 주문으로 발생했다면 user_id가 김미선 자신이고, 추천수수료는 자신을 추천한 사람이 user_id로 기록되어 수수료가 발생했을 것이다.

따라서 그 수수료가 amount=200만원으로 발생했다면, cash_point_amount=100만원, mileage_point_amount=100만원이 생성되었을 것이다. 따라서 새로운 주문은 이 수수료 레코드와 동일한 레코드를 복사하여 생성하되, amount 값을  amount=-200만원, cash_point_amount 값을 -100만원, mileage_point_amount 값을 -100만원으로 생성해서 저장하면 된다. 날짜는 created_at은 추가된 오늘날자로 하면된다.
이때 commissions테이블에 저장된 user_id는 수수료를 받아가는 사람의 user_id이다. 따라서 그 user_id를 찾아내고 그 user_id에 대한 수수료를 삭감한다. users테이블에서 그 user_id를 찾아낸다. users테이블에 그 user_id의 commission_total 컬럼에서 그 수수료를 200만원을 삭감한다. 마찬가지로 cash_points=cash_points-100만원, mileage_points=mileage_points-100만원
만약 그 order_id로 발생한 수수료가 2가지 종류가 발생했다면 order_id가 모두 들어있을 것이고, 각각에는 받아간 user_id가 있을것이나 각각 삭감을 반복해야 한다. 즉, 두건이면 두번 진행한다. 즉, 첫번째는 직판수수료 받은사람거 삭감하고, 두번째는 추천수수료 받은사람거 삭감한다.

이렇게 하면 김미선에게 지급한 6000개에 대한 수수료가 삭감된다.
그다음 사람으로 넘어간다. 이렇게 반복해야 하는데, 이것을 할수 있는 스크립트를 작성하라.


[ 처리과정 표시 예시]
한명씩 처리할때 마다 처리과정을 단계별로 화면에 출력한다.

이름과 연락처에 맞는 유저를 찾음 .. user_id= 93, 김미선이다
이 유저아이디로 orders 테이블에서 주문건을 찾고, 그중에서 payment_method가 admin 또는 admin1인 주문건을 찾고, 그중에서 수량이 6000개인 주문건을 찾는다. 찾아낸 주문건의 order_id를 찾는다. ...order_id=12.
이 order_id를 통해 commissions 테이블에서 그 주문건에 의해 발생한 수수료를 찾아낸다. ..commission_id=12
이 commission_id를 통해 commissions 테이블에서 그 수수료를 찾아낸다. ...amount=200, cash_point_amount=100, mileage_point_amount=100
이 수수료를 삭감한다. ...amount=-200, cash_point_amount=-100, mileage_point_amount=-100
추천수수료가 발생했다면 추천수수료를 받은 사람의 user_id를 찾아낸다. ...user_id=10
이 user_id를 통해 users테이블에서 그 사람을 찾아낸다. ...name=김미선, phone=010-8770-7388, commission_total=200만원, cash_points=100만원, mileage_points=100만원 발생한 수수료를 삭감한후 업데이트한다.
이 수수료를 삭감한다. ...commission_total=-200만원, cash_points=-100만원, mileage_points=-100만원
김미선에 대한 무료지급 6000개에 대한 수수료삭감이 완료되었다.

다음은 유향숙에 대한 처리과정이다.

이런식으로 메세지를 출력하면서 처리한다.
만약 해당되는 유저나 주문건을 찾지 못했으면,, 못찾았다고 표시하고 다음단계로 넘어간다.



date_default_timezone_set('Asia/Seoul');
// 데이터베이스 설정
define('DB_HOST', 'localhost');
define('DB_USER', 'lidyahkc_0');
define('DB_PASS', 'lidya2016$');
define('DB_NAME', 'lidyahkc_1626');



$freeUsers = [
    ['name' => '김미선', 'phone' => '010-8770-7388', 'quantity' => 6000],
    ['name' => '유향숙', 'phone' => '010-2011-0200', 'quantity' => 3000],
    ['name' => '이상호', 'phone' => '010-2577-3900', 'quantity' => 2000],
    ['name' => '서예담', 'phone' => '010-9825-6887', 'quantity' => 4000],
    ['name' => '최애정', 'phone' => '010-4660-7696', 'quantity' => 2000],
    ['name' => '박윤숙', 'phone' => '010-3060-6067', 'quantity' => 2000],
    ['name' => '주용철', 'phone' => '010-3779-2577', 'quantity' => 2000],
    ['name' => '최우재', 'phone' => '010-3770-7209', 'quantity' => 2000],
    ['name' => '이경남', 'phone' => '010-4557-5549', 'quantity' => 2000],
    ['name' => '임은정', 'phone' => '010-2399-3427', 'quantity' => 2000],
    ['name' => '김삼성', 'phone' => '010-4222-3428', 'quantity' => 4000],
    ['name' => '윤주영', 'phone' => '010-2833-0094', 'quantity' => 10000],
    ['name' => '송성석', 'phone' => '010-5071-8904', 'quantity' => 4000],
    ['name' => '박영애', 'phone' => '010-5511-6593', 'quantity' => 6000],
    ['name' => '최순애', 'phone' => '010-3029-7854', 'quantity' => 4000],
    ['name' => '조현순', 'phone' => '010-3561-6926', 'quantity' => 6000],
    ['name' => '서정희', 'phone' => '010-6799-8811', 'quantity' => 2000],
    ['name' => '조해운', 'phone' => '010-6360-8536', 'quantity' => 2000],
    ['name' => '유재임', 'phone' => '010-5128-1678', 'quantity' => 2000],
    ['name' => '김은경', 'phone' => '010-4658-5598', 'quantity' => 2000],
    ['name' => '나상봉', 'phone' => '010-4606-1972', 'quantity' => 4000],
    ['name' => '권혜숙', 'phone' => '010-5116-7174', 'quantity' => 2000],
    ['name' => '이수미', 'phone' => '010-2540-8566', 'quantity' => 2000],
    ['name' => '최애정', 'phone' => '010-4660-7696', 'quantity' => 6000],
    ['name' => '박성진', 'phone' => '010-2324-8204', 'quantity' => 2000],
    ['name' => '권혁미', 'phone' => '010-4599-5035', 'quantity' => 2000],
    ['name' => '노예진', 'phone' => '010-7762-0181', 'quantity' => 4000],
    ['name' => '양동숙', 'phone' => '010-6611-0010', 'quantity' => 10000],
    ['name' => '이주표', 'phone' => '010-9966-0179', 'quantity' => 2000],
    ['name' => '양승덕', 'phone' => '010-3186-8095', 'quantity' => 2000],
    ['name' => '염훈자', 'phone' => '010-9916-5530', 'quantity' => 2000],
    ['name' => '이성구', 'phone' => '010-8865-5488', 'quantity' => 2000],
    ['name' => '배성림', 'phone' => '010-9312-9150', 'quantity' => 2000],
    ['name' => '김은자', 'phone' => '010-3815-4819', 'quantity' => 2000],
    ['name' => '진서연', 'phone' => '010-5647-2526', 'quantity' => 4000],
    ['name' => '이강걸', 'phone' => '010-5066-7030', 'quantity' => 2000],
    ['name' => '조해운', 'phone' => '010-6360-8536', 'quantity' => 2000],
    ['name' => '노숙경', 'phone' => '010-5039-1006', 'quantity' => 2000],
    ['name' => '손나경', 'phone' => '010-5511-1994', 'quantity' => 2000],
    ['name' => '김옥순', 'phone' => '010-5617-5718', 'quantity' => 12000],
    ['name' => '장선미', 'phone' => '010-9852-4958', 'quantity' => 2000],
    ['name' => '이해성', 'phone' => '010-4070-7888', 'quantity' => 1000],
    ['name' => '한윤근', 'phone' => '010-2687-3285', 'quantity' => 2000],
    ['name' => '박종복', 'phone' => '010-4641-4733', 'quantity' => 2000],
    ['name' => '한관명', 'phone' => '010-3602-1749', 'quantity' => 2000],
    ['name' => '배미홍', 'phone' => '010-2426-5038', 'quantity' => 11000],
    ['name' => '김순덕', 'phone' => '010-3947-8835', 'quantity' => 2000],
    ['name' => '오정미', 'phone' => '010-7724-3445', 'quantity' => 1000],
    ['name' => '백설희', 'phone' => '010-9688-8718', 'quantity' => 1000],
];



[무료로 지급한 명단]
이름	연락처	      지급수량
김미선	010-8770-7388	6,000
유향숙	010-2011-0200	3,000
이상호	010-2577-3900	2,000
서예담	010-9825-6887	4,000
최애정	010-4660-7696	2,000
박윤숙	010-3060-6067	2,000
주용철	010-3779-2577	2,000
최우재	010-3770-7209	2,000
이경남	010-4557-5549	2,000
임은정	010-2399-3427	2,000
김삼성	010-4222-3428	4,000
윤주영	010-2833-0094	10,000
송성석	010-5071-8904	4,000
박영애	010-5511-6593	6,000
최순애	010-3029-7854	4,000
조현순	010-3561-6926	6,000
서정희	010-6799-8811	2,000
조해운	010-6360-8536	2,000
유재임	010-5128-1678	2,000
김은경	010-4658-5598	2,000
나상봉	010-4606-1972	4,000
권혜숙	010-5116-7174	2,000
이수미	010-2540-8566	2,000
최애정	010-4660-7696	6,000
박성진	010-2324-8204	2,000
권혁미	010-4599-5035	2,000
노예진	010-7762-0181	4,000
양동숙	010-6611-0010	10,000
이주표	010-9966-0179	2,000
양승덕	010-3186-8095	2,000
염훈자	010-9916-5530	2,000
이성구	010-8865-5488	2,000
배성림	010-9312-9150	2,000
김은자	010-3815-4819	2,000
진서연	010-5647-2526	4,000
이강걸	010-5066-7030	2,000
조해운	010-6360-8536	2,000
노숙경	010-5039-1006	2,000
손나경	010-5511-1994	2,000
김옥순	010-5617-5718	12,000
장선미	010-9852-4958	2,000
이해성	010-4070-7888	1,000
한윤근	010-2687-3285	2,000
박종복	010-4641-4733	2,000
한관명	010-3602-1749	2,000
배미홍	010-2426-5038	11,000
김순덕	010-3947-8835	2,000
오정미	010-7724-3445	1,000
백설희	010-9688-8718	1,000






==========================================================

[주문번호별로 삭감하기]

아래는 orders 주문 테이블에서 조회한 주문번호이다.




WHERE order_id IN (
    20, 28, 50, 271, 31, 36, 49, 34, 38, 115, 75, 35, 112, 40, 113, 47, 59, 48, 52, 
    114, 41, 107, 110, 60, 37, 105, 67, 43, 71, 18, 32, 58, 109, 97, 33, 104, 147, 57, 
    70, 46, 80, 111, 146, 338, 359, 360, 362, 363, 364, 366, 368, 372, 373, 381, 384, 
    390, 391, 392, 422, 426, 465, 466, 449, 451, 474, 475, 476, 477, 497, 481, 503, 
    528, 567, 585, 580, 586, 587, 624, 629, 630, 631, 634, 609, 615, 663, 684, 686, 
    687, 688, 689, 746, 670, 751, 752, 753, 695, 698, 701, 703, 704, 707, 706, 708, 
    710, 711, 718, 722, 724, 725, 728, 729, 730, 731, 735, 736, 738, 739, 778, 813, 
    819, 820, 821, 822, 824, 839, 851, 873, 885, 913, 915, 928, 929, 1155, 1169, 1198, 
    1199, 1200, 1201, 1214, 1216, 1232, 1233, 1234, 1235, 1236, 1237, 1238, 1273, 
    1278, 1279, 1335, 1340, 1360, 1362, 1390, 1407, 1439, 1453, 1457, 1458, 1459, 1460, 
    1461, 1463, 1498, 1505, 1516, 1517, 1518, 1529, 1530, 1573, 1574, 1579, 1580, 1581, 
    1582, 1584, 1587, 1591, 1603, 1604, 1641, 1666, 1668, 1669, 1713, 1715, 1722, 1723, 
    1832, 1833, 1837, 1838, 1839, 1869, 1870, 1893, 1895, 1931, 1941, 2056, 2071, 2093, 
    2094, 2095, 2096, 2100, 2101, 2163, 2170, 2171, 2175, 2179, 2186, 2187, 2211, 2224, 
    2266, 2268, 2276, 2277, 2301, 2306, 2311, 2330, 2334, 2335, 2392, 2407, 2415, 2422, 
    2495, 2504, 2509, 2514, 2515, 2520, 2526, 2570, 2580, 2581, 2644, 2658, 2688, 2691, 
    2692, 2710, 2711, 2740, 2741, 2742, 2757, 2789, 2795, 2811, 2813, 2815, 2821, 2822, 
    2824, 2825, 2826, 2831, 2832, 2839, 2853, 2882, 2894, 2899, 2909, 2924, 2925, 2995, 
    3011, 3016, 3019, 3042
);



주어진 주문번호(order_id)는 
문제는 이 주문건은 관리자가 무료로 지급한 주문이라서 수수료가 발생하지 않았야 하는데도 불구하고 수수료가 발생한 것으로 기록되어 있습니다. 그래서 삭감을 해야 하는 스크립트를 작성해야 합니다.

해당 주문건의 order_id 즉, orders테이블의 주문번호(order_id)만을 우선 추출하여 이 주문번호로 발생된 수수료를 commissions 테이블에서 순차적으로 찾아내어 이 주문건으로 발생된 수수료를 삭감합니다.

삭감방법은 
1. 주번번호 1건당 발생된 수수료를 찾아 commissions테이블에서 해당 레코드와 같은데 값이 마이너스인 레코드 생성하고, users테이블에서 그 수수료를 삭감한다.

order_id 한건당, commissions 테이블에서 그 order_id로 발생한 수수료가 저장되어 있는데, 거기에는 어떤수당(commission_type)이 누구(user_id)에게 얼마(amount)가 발생했는지, 그리고 그 수당은 현금포인트(cash_point_amount)50%와 마일리지포인트(mileage_point_amount)50%로 분배되어 저장되어 있다.

2.삭감방법은 레코드는 그대로 두고, 그 발생한 수수료 만큼 새로 레코드를 발생시켜 마이너스 수수료를 발생시키는 것이다.  
새로생성된 레코드의 minus_from 컬럼에는 처음 발생했던 삭감대상의 commissions 테이블의 id값을 저장하여야 한다. 나중에 어디서 발생된 수당건으로 (-)수당이 발생했는지 알수있다. 

따라서 commissions 테이블에 동일한 레코드를 복사하여 생성하도록 하고, 컬럼값중 수수료와 관련된 minus_from 컬럼에는 처음 발생했던 삭감대상의 commissions 테이블의 id값을 저장하고, amount값은 -amount 값으로, cash_point_amount 값은 마이너스 - cash_point_amount 컬럼값을 생성하고, mileage_point_amount컬럼값도 마이너스(-)mileage_point_amount로 생성해서 저장한다. 수수료발생 날짜는 created_at은 추가된 오늘날자로 하면된다.

3.그다음에는 각 레코드에서 받아간 사람의 user_id를 찾아내고,  users테이블에서 그 user_id에 대한 수수료를 삭감한다.
users테이블에서 그 user_id로 발생했던 commission_total 컬럼값 = -(amount) , cash_points 컬럼값은 -(cash_point_amount)과 mileage_points 컬럼에서는 -(mileage_point_amount)를 각각 수수료를 삭감한다.


[삭감처리의 예시단게]

1.order_id를 읽는다. 예를 들어 order_id = 31153 번이라면, 이 order_id로 발생된 commissions테이블의 order_id를 찾아 해당레코드를 찾는다.
그 order_id로 인해 commissions테이블에는 수수료가 발생한 사람(user_id)와 직판수수료 또는 추천수수료가 발생했을 것이다. 
보통 order_id 주문건 1건당 발생된 commissions테이블의 레코드는 추천수수료 1건 또는 직판수수료 1건 등, 보통 2건의 레코드가 생성된다. 
모든 수수료는 발생하면 commissions테이블에 저장되는데, 무슨 매출로 발생했냐는 order_id를 통해서, 그 수수료를 누가 받아갔냐는 user_id에 기록된다. 따라서 그 order_id로 발생한 수수료가 commissions 테이블에 추천수수료든, 직판수수료든 저장이 되었을 것이다. 따라서 이 수수료를 받아가는 사람이 있을 것이다. 즉 직판수수료가 그 주문으로 발생했다면 user_id가 김미선 자신이고, 추천수수료는 자신을 추천한 사람이 user_id로 기록되어 수수료가 발생했을 것이다.

따라서 commissions테이블의  수수료가 amount=200만원으로 발생했다면, cash_point_amount=100만원, mileage_point_amount=100만원이 생성되었을 것이다. 따라서 새로운 주문은 이 수수료 레코드와 동일한 레코드를 복사하여 생성하되, amount 값을  amount=-200만원, cash_point_amount 값을 -100만원, mileage_point_amount 값을 -100만원으로 생성해서 minus_from컬럼에 삭감대상의 commissions테이블의 id값을 저장하면 된다. 날짜는 created_at은 추가된 오늘날자로 하면된다.

이때 commissions테이블에 저장된 user_id는 수수료를 받아가는 사람의 user_id이다. 따라서 그 user_id를 찾아내고 그 user_id에 대한 수수료를 삭감해야 하기때문에 users테이블에서 그 user_id를 찾아낸다. users테이블에서 수당을 받은 user_id의 수수료합계 컬럼인 commission_total컬럼=commission_total-200만원 으로  200만원을 삭감한다. 마찬가지로 cash_points=cash_points-100만원, mileage_points=mileage_points-100만원
만약 그 order_id로 발생한 수수료가 2가지 종류 수수료가 발생했다면, commissions테이블에는 2건의 레코드가 생성되어 있을 것이고, order_id가 모두 들어있을 것이고, 각각에는 order_id와 받아간 user_id가 있을것이니 각각 삭감을 반복해야 한다. 즉, 두건이면 두번 진행한다. 즉, 첫번째는 직판수수료 받은사람거 삭감하고, 두번째는 추천수수료 받은사람거 삭감한다.

이렇게 하면 한건의 order_id로 인해 발생된 commissions테이블에 지급된 수수료가 모두 삭감된다.
그다음 order_id로 넘어간다. 이렇게 반복해야 하는데, 이것을 할수 있는 스크립트를 작성하라.


[ 처리과정 표시 예시]
한건씩 처리할때 마다 처리과정을 단계별로 화면에 출력한다.

주문번호 order_id= 93, 
이 order_id를 통해 commissions 테이블에서 그 주문건에 의해 발생한 수수료를 찾아낸다. ..만약 commissions 테이블에서 해당되는 order_id 가 2개 레코드에서 발견되면  각각에 대해서 처리한다. 예를 들어 한건의 레코드에서 amount=200, cash_point_amount=100, mileage_point_amount=100
이 commissions 테이블에서 발생했다면, 레코드를 복사해서 수당건을 새로발생시키되 amount=-200, cash_point_amount=-100, mileage_point_amount=-100 으로 기록하여 *(-)수당을 발생시킨다.  minus_from 컬럼에는 삭감대상의 commissions 테이블의 id값을 저장한다.

새로운 레코드가 commissions테이블에 생성된다. amount=-200, cash_point_amount=-100, mileage_point_amount=-100, minus_from=1234
추천수수료가 발생했다면 추천수수료를 받은 사람의 user_id를 찾아낸다. ...user_id=10
이 user_id를 통해 users테이블에서 그 사람을 찾아낸다. ...name=김미선, phone=010-8770-7388, commission_total=commission_total-200만원, cash_points=cash_points-100만원, mileage_points=mileage_points-100만원 발생한 수수료를 삭감한후 업데이트한다.
이 수수료를 삭감한다. ...commission_total=commission_total-200만원, cash_points=cash_points-100만원, mileage_points=mileage_points-100만원

이렇게 해서 한건의 order_id에 대한 수수료를 삭감하는 과정을 마쳤다, 다음의 주문번호 order_id=100으로 넘어간다.


이런식으로 메세지를 출력하면서 처리한다.
만약 해당되는 유저나 주문건을 찾지 못했으면,, 못찾았다고 표시하고 다음단계로 넘어간다.




디비구조를 참조하여 완벽한 스크립트를 작성하라.


CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `rank` varchar(10) NOT NULL DEFAULT '회원' COMMENT '직급',
  `referred_by` int(11) DEFAULT NULL COMMENT '추천인',
  `commission_total` decimal(10,2) DEFAULT 0.00 COMMENT '수수료총액',
  `cash_points` decimal(10,2) DEFAULT 0.00 COMMENT '현금포인트',
  `mileage_points` decimal(10,2) DEFAULT 0.00 COMMENT '마일리지포인트',
  `nft_token` int(11) DEFAULT 0 COMMENT '보유한 NFT 수량',
  `myQuantity` int(11) DEFAULT 0 COMMENT '본인 누적 구매 수량',
  `myAmount` decimal(15,2) DEFAULT 0.00 COMMENT '본인 누적 구매 금액',
  `myAgent` int(11) DEFAULT 0 COMMENT '산하 총판 수',
  `myAgent_referral` int(11) DEFAULT 0 COMMENT '직접 추천한 총판 수',
  `password` varchar(255) NOT NULL,
  `referral_code` varchar(20) DEFAULT NULL COMMENT '추천인코드',
  `referral_link` varchar(255) DEFAULT NULL COMMENT '추천인링크',
  `qr_code` varchar(255) DEFAULT NULL COMMENT 'QR코드',
  `organization` varchar(100) NOT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active' COMMENT '활성화,비활성화',
  `is_admin` tinyint(1) DEFAULT 0 COMMENT '관리자인지 여부',
  `rank_change_date` date DEFAULT NULL COMMENT '승급일',
  `rank_change_reason` text DEFAULT NULL COMMENT '승급사유',
  `last_purchase_date` date DEFAULT NULL,
  `total_purchases` int(11) DEFAULT 0,
  `myTotal_quantity` int(11) DEFAULT 0 COMMENT '본인 및 산하 누적 구매 수량',
  `myTotal_Amount` decimal(15,2) DEFAULT 0.00 COMMENT '본인 및 산하 누적 구매 금액',
  `direct_volume` decimal(15,2) DEFAULT 0.00 COMMENT '직접 구매 실적',
  `referrals_volume` decimal(15,2) DEFAULT 0.00 COMMENT '직접 추천인 구매 실적',
  `ref_total_volume` decimal(15,2) DEFAULT 0.00 COMMENT '전체 하위라인 구매 실적',
  `rank_update_date` date DEFAULT NULL COMMENT '마지막 직급 업데이트 일자',
  `direct_referrals_count` int(11) DEFAULT 0 COMMENT '직접 추천한 회원 수',
  `total_distributor_count` int(11) DEFAULT 0 COMMENT '하위 총판 수',
  `special_distributor_count` int(11) DEFAULT 0 COMMENT '하위 특판 수',
  `total_referrals_count` int(11) DEFAULT 0 COMMENT '전체 추천 회원 수',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `phone` (`phone`),
  KEY `referred_by` (`referred_by`),
  KEY `idx_myTotal_quantity` (`myTotal_quantity`),
  KEY `idx_myAgent` (`myAgent`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci


CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `price_unit` decimal(10,0) DEFAULT NULL COMMENT '단위가격',
  `quantity` int(11) NOT NULL COMMENT '수량',
  `nft_token` int(11) DEFAULT 0 COMMENT '지급된 NFT 수량',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `depositor_name` varchar(100) DEFAULT NULL,
  `cash_point_used` decimal(10,2) DEFAULT 0.00,
  `mileage_point_used` decimal(10,2) DEFAULT 0.00,
  `payment_date` datetime DEFAULT NULL,
  `status` enum('pending','paid','completed','cancelled') DEFAULT 'pending',
  `paid_status` enum('pending','completed') DEFAULT 'pending',
  `currency` char(3) DEFAULT 'KRW',
  `vat_amount` decimal(10,2) DEFAULT 0.00,
  `ip_address` varchar(45) DEFAULT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `order_type` varchar(256) DEFAULT 'regular',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4237 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci




CREATE TABLE `commissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `commission_type` enum('direct_sales','distributor','special','total_distributor','special_distributor') NOT NULL COMMENT '수수료 유형',
  `amount` decimal(10,2) NOT NULL,
  `cash_point_amount` decimal(10,2) DEFAULT 0.00 COMMENT '캐시포인트로 지급된 금액',
  `mileage_point_amount` decimal(10,2) DEFAULT 0.00 COMMENT '마일리지포인트로 지급된 금액',
  `commission_rate` decimal(5,2) DEFAULT 0.00 COMMENT '적용된 수수료 비율',
  `order_id` int(11) NOT NULL COMMENT '주문 ID',
  `minus_from` int(10) DEFAULT NULL COMMENT '삭제된수당건',
  `source_user_id` int(11) NOT NULL,
  `source_amount` decimal(11,2) DEFAULT NULL COMMENT '발생매출',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `source_user_id` (`source_user_id`),
  KEY `order_id` (`order_id`),
  KEY `idx_commission_type` (`commission_type`),
  CONSTRAINT `commissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `commissions_ibfk_2` FOREIGN KEY (`source_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `commissions_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36479 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci






order_id	user_id	created_at	payment_method	name	product_id	quantity	total_amount	cash_point_used	mileage_point_used	
20	4	2024-09-27 19:41	admin	유향숙	1	1000	2000000	0	0	
28	4	2024-09-27 19:41	admin	유향숙	1	2000	4000000	0	0	
50	44	2024-09-27 19:41	admin1	박성진	1	2000	4000000	0	0	
271	189	2024-09-27 19:41	admin	임은정	1	2000	4000000	0	0	
31	5	2024-09-27 19:41	admin1	이수미	1	2000	4000000	0	0	
36	10	2024-09-27 19:41	admin1	이상호	1	2000	4000000	0	0	
49	43	2024-09-27 19:41	admin1	한윤근	1	2000	4000000	0	0	
34	8	2024-09-27 19:41	admin1	윤주영	1	10000	20000000	0	0	
38	13	2024-09-27 19:41	admin1	최순애	1	4000	8000000	0	0	
115	103	2024-09-27 19:41	admin	양승덕	1	2000	4000000	0	0	
75	74	2024-09-27 19:41	admin	조현순	1	6000	12000000	0	0	
35	9	2024-09-27 19:41	admin1	주용철	1	2000	4000000	0	0	
112	46	2024-09-27 19:41	admin	김은자	1	2000	4000000	0	0	
40	14	2024-09-27 19:41	admin1	김순덕	1	2000	4000000	0	0	
113	83	2024-09-27 19:41	admin	이해성	1	1000	2000000	0	0	
47	38	2024-09-27 19:41	admin1	김삼성	1	4000	8000000	0	0	
59	54	2024-09-27 19:41	admin1	이경남	1	2000	4000000	0	0	
48	39	2024-09-27 19:41	admin1	권혁미	1	2000	4000000	0	0	
52	15	2024-09-27 19:41	admin1	나상봉	1	4000	8000000	0	0	
114	52	2024-09-27 19:41	admin	박종복	1	2000	4000000	0	0	
41	18	2024-09-27 19:41	admin1	김은경	1	2000	4000000	0	0	
107	88	2024-09-27 19:41	admin	최애정	1	2000	4000000	0	0	
110	88	2024-09-27 19:41	admin	최애정	1	6000	12000000	0	0	
60	58	2024-09-27 19:41	admin1	노숙경	1	2000	4000000	0	0	
37	12	2024-09-27 19:41	admin1	송성석	1	4000	8000000	0	0	
105	2	2024-09-27 19:41	admin	권혜숙	1	2000	4000000	0	0	
67	64	2024-09-27 19:41	admin1	유재임	1	2000	4000000	0	0	
43	19	2024-09-27 19:41	admin1	손나경	1	2000	4000000	0	0	
71	71	2024-09-27 19:41	admin	박영애	1	6000	12000000	0	0	
18	6	2024-09-27 19:41	admin	김옥순	1	1000	2000000	0	0	
32	6	2024-09-27 19:41	admin	김옥순	1	2000	4000000	0	0	
58	56	2024-09-27 19:41	admin1	진서연	1	4000	8000000	0	0	
109	97	2024-09-27 19:41	admin	조해운	1	4000	8000000	0	0	
97	96	2024-09-27 19:41	admin	양동숙	1	10000	20000000	0	0	
33	7	2024-09-27 19:41	admin1	서정희	1	2000	4000000	0	0	
104	101	2024-09-27 19:41	admin	노예진	1	4000	8000000	0	0	
147	93	2024-09-27 19:41	admin	김미선	1	6000	12000000	0	0	
57	55	2024-09-27 19:41	admin1	이성구	1	2000	4000000	0	0	
70	70	2024-09-27 19:41	admin	배성림	1	2000	4000000	0	0	
46	37	2024-09-27 19:41	admin1	서예담	1	4000	8000000	0	0	
80	80	2024-09-27 19:41	admin	장선미	1	2000	4000000	0	0	
111	86	2024-09-27 19:41	admin	염훈자	1	2000	4000000	0	0	
146	120	2024-09-27 19:41	admin	이주표	1	2000	4000000	0	0	
338	97	2024-09-28 6:30	admin	조해운	1	2000	4000000	0	0	
359	14	2024-09-28 11:03	admin	김순덕	1	100	200000	0	0	
360	55	2024-09-28 11:04	admin	이성구	1	100	200000	0	0	
362	9	2024-09-28 11:11	admin	주용철	1	100	200000	0	0	
363	15	2024-09-28 11:11	admin	나상봉	1	100	200000	0	0	
364	88	2024-09-28 11:12	admin	최애정	1	100	200000	0	0	
366	6	2024-09-28 11:13	admin	김옥순	1	100	200000	0	0	
368	19	2024-09-28 11:15	admin	손나경	1	100	200000	0	0	
372	46	2024-09-28 11:18	admin	김은자	1	100	200000	0	0	
373	54	2024-09-28 11:19	admin	이경남	1	100	200000	0	0	
381	5	2024-09-28 11:26	admin	이수미	1	600	1200000	0	0	
384	4	2024-09-28 11:29	admin	유향숙	1	100	200000	0	0	
390	295	2024-09-28 18:06	admin	이강걸	1	2200	4400000	0	0	
391	297	2024-09-28 20:17	admin	오정미	1	1000	2000000	0	0	
392	296	2024-09-28 20:17	admin	백설희	1	1000	2000000	0	0	
422	97	2024-09-29 7:23	admin	조해운	1	1000	2000000	0	0	
426	4	2024-09-30 8:30	admin	유향숙	1	2000	4000000	0	0	
465	93	2024-09-30 15:53	point	김미선	1	7	14000	0	14000	
466	93	2024-09-30 15:59	point	김미선	1	8	16000	15000	1000	
449	110	2024-09-30 16:49	admin	한관명	1	2000	4000000	0	0	
451	351	2024-09-30 17:43	admin	배미홍	1	1000	2000000	0	0	
474	9	2024-10-01 6:55	point	주용철	1	82	164000	0	164000	
475	88	2024-10-01 7:07	point	최애정	1	1500	3000000	0	3000000	
476	88	2024-10-01 7:10	point	최애정	1	320	640000	0	640000	
477	88	2024-10-01 7:11	point	최애정	1	26	52000	0	52000	
497	15	2024-10-01 12:10	point	나상봉	1	25	50000	0	50000	
481	4	2024-10-01 14:21	admin	유향숙	1	2000	4000000	0	0	
503	4	2024-10-02 8:23	admin	유향숙	1	2000	4000000	0	0	
528	9	2024-10-02 8:44	point	주용철	1	41	82000	0	82000	
567	4	2024-10-03 10:30	admin	유향숙	1	2000	4000000	0	0	
585	88	2024-10-03 14:20	point	최애정	1	500	1000000	0	1000000	
580	97	2024-10-03 17:17	admin	조해운	1	1000	2000000	0	0	
586	351	2024-10-03 22:57	admin	배미홍	1	2000	4000000	0	0	
587	4	2024-10-04 7:53	admin	유향숙	1	2000	4000000	0	0	
624	9	2024-10-04 11:47	point	주용철	1	177	354000	0	354000	
629	93	2024-10-04 14:01	point	김미선	1	16	32000	15200	16800	
630	15	2024-10-04 14:50	point	나상봉	1	50	100000	0	100000	
631	15	2024-10-04 14:50	point	나상봉	1	50	100000	0	100000	
634	15	2024-10-04 14:59	point	나상봉	1	73	146000	0	146000	
609	351	2024-10-04 15:33	admin	배미홍	1	4000	8000000	0	0	
615	351	2024-10-04 16:04	admin	배미홍	1	4000	8000000	0	0	
663	88	2024-10-05 8:32	point	최애정	1	1000	2000000	2000000	0	
684	6	2024-10-05 13:53	point	김옥순	1	1	2000	0	2000	
686	14	2024-10-05 13:55	point	김순덕	1	1	2000	0	2000	
687	14	2024-10-05 14:05	point	김순덕	1	10	20000	0	20000	
688	6	2024-10-05 14:05	point	김옥순	1	650	1300000	0	1300000	
689	6	2024-10-05 14:12	point	김옥순	1	650	1300000	1300000	0	
746	9	2024-10-05 16:08	point	주용철	1	45	90000	0	90000	
670	4	2024-10-05 17:06	admin	유향숙	1	2000	4000000	0	0	
751	9	2024-10-05 17:22	point	주용철	1	15	30000	0	30000	
752	9	2024-10-05 17:28	point	주용철	1	60	120000	120000	0	
753	15	2024-10-05 17:30	point	나상봉	1	90	180000	90000	90000	
695	295	2024-10-05 21:45	admin	이강걸	1	100	200000	0	0	
698	19	2024-10-05 21:47	admin	손나경	1	100	200000	0	0	
701	54	2024-10-05 21:49	admin	이경남	1	100	200000	0	0	
703	10	2024-10-05 21:51	admin	이상호	1	100	200000	0	0	
704	5	2024-10-05 21:52	admin	이수미	1	100	200000	0	0	
707	110	2024-10-05 21:54	admin	한관명	1	100	200000	0	0	
706	52	2024-10-05 21:54	admin	박종복	1	100	200000	0	0	
708	15	2024-10-05 21:55	admin	나상봉	1	100	200000	0	0	
710	88	2024-10-05 21:56	admin	최애정	1	100	200000	0	0	
711	9	2024-10-05 21:58	admin	주용철	1	100	200000	0	0	
718	44	2024-10-05 22:02	admin	박성진	1	100	200000	0	0	
722	6	2024-10-05 22:05	admin	김옥순	1	1000	2000000	0	0	
724	13	2024-10-05 22:07	admin	최순애	1	100	200000	0	0	
725	39	2024-10-05 22:08	admin	권혁미	1	100	200000	0	0	
728	96	2024-10-05 22:10	admin	양동숙	1	100	200000	0	0	
729	64	2024-10-05 22:11	admin	유재임	1	100	200000	0	0	
730	43	2024-10-05 22:12	admin	한윤근	1	100	200000	0	0	
731	74	2024-10-05 22:12	admin	조현순	1	100	200000	0	0	
735	12	2024-10-05 22:14	admin	송성석	1	100	200000	0	0	
736	93	2024-10-05 22:15	admin	김미선	1	100	200000	0	0	
738	4	2024-10-05 22:16	admin	유향숙	1	100	200000	0	0	
739	14	2024-10-05 22:17	admin	김순덕	1	100	200000	0	0	
778	14	2024-10-06 4:24	point	김순덕	1	60	120000	120000	0	
813	9	2024-10-06 11:14	point	주용철	1	36	72000	0	72000	
819	110	2024-10-06 12:43	point	한관명	1	1	2000	30	1970	
820	110	2024-10-06 12:43	point	한관명	1	1	2000	30	1970	
821	110	2024-10-06 12:43	point	한관명	1	1	2000	33	1967	
822	110	2024-10-06 12:43	point	한관명	1	1	2000	33	1967	
824	6	2024-10-06 13:34	point	김옥순	1	840	1680000	0	1680000	
839	120	2024-10-07 3:00	point	이주표	1	300	600000	300000	300000	
851	9	2024-10-07 6:12	point	주용철	1	10	20000	0	20000	
873	10	2024-10-07 8:38	point	이상호	1	80	160000	80000	80000	
885	9	2024-10-07 9:57	point	주용철	1	11	22000	0	22000	
913	93	2024-10-07 13:44	point	김미선	1	81	162000	81000	81000	
915	9	2024-10-07 14:04	point	주용철	1	100	200000	150000	50000	
928	88	2024-10-07 15:24	point	최애정	1	300	600000	0	600000	
929	120	2024-10-07 15:25	point	이주표	1	250	500000	250000	250000	
1155	93	2024-10-10 7:18	point	김미선	1	33	66000	33000	33000	
1169	4	2024-10-10 8:48	point	유향숙	1	100	200000	100000	100000	
1198	13	2024-10-10 13:18	point	최순애	1	100	200000	0	200000	
1199	13	2024-10-10 13:20	point	최순애	1	100	200000	200000	0	
1200	13	2024-10-10 13:22	point	최순애	1	65	130000	0	130000	
1201	13	2024-10-10 13:23	point	최순애	1	65	130000	130000	0	
1214	93	2024-10-10 15:33	point	김미선	1	25	50000	24200	25800	
1216	93	2024-10-10 15:36	point	김미선	1	15	30000	15000	15000	
1232	93	2024-10-10 23:35	point	김미선	1	9	18000	9000	9000	
1233	93	2024-10-10 23:40	point	김미선	1	6	12000	7000	5000	
1234	93	2024-10-10 23:42	point	김미선	1	3	6000	2000	4000	
1235	93	2024-10-10 23:47	point	김미선	1	2	4000	3000	1000	
1236	93	2024-10-10 23:49	point	김미선	1	1	2000	0	2000	
1237	93	2024-10-10 23:51	point	김미선	1	1	2000	2000	0	
1238	93	2024-10-10 23:52	point	김미선	1	1	2000	800	1200	
1273	13	2024-10-11 5:38	point	최순애	1	80	160000	80000	80000	
1278	93	2024-10-11 6:20	point	김미선	1	3	6000	3000	3000	
1279	93	2024-10-11 6:22	point	김미선	1	1	2000	1000	1000	
1335	9	2024-10-11 11:07	point	주용철	1	150	300000	0	300000	
1340	9	2024-10-11 11:45	point	주용철	1	65	130000	0	130000	
1360	15	2024-10-11 16:13	point	나상봉	1	65	130000	0	130000	
1362	9	2024-10-11 16:31	point	주용철	1	16	32000	0	32000	
1390	9	2024-10-12 6:23	point	주용철	1	23	46000	0	46000	
1407	15	2024-10-12 7:37	point	나상봉	1	10	20000	0	20000	
1439	96	2024-10-12 12:02	point	양동숙	1	30	60000	0	60000	
1453	9	2024-10-12 14:14	point	주용철	1	150	300000	0	300000	
1457	15	2024-10-12 15:29	point	나상봉	1	10	20000	20000	0	
1458	15	2024-10-12 15:29	point	나상봉	1	10	20000	20000	0	
1459	15	2024-10-12 15:31	point	나상봉	1	10	20000	20000	0	
1460	15	2024-10-12 15:31	point	나상봉	1	10	20000	20000	0	
1461	39	2024-10-12 15:46	point	권혁미	1	280	560000	0	560000	
1463	9	2024-10-12 18:08	point	주용철	1	30	60000	0	60000	
1498	9	2024-10-13 8:57	point	주용철	1	6	12000	0	12000	
1505	9	2024-10-13 11:57	point	주용철	1	21	42000	0	42000	
1516	52	2024-10-13 17:16	point	박종복	1	30	60000	0	60000	
1517	52	2024-10-13 17:19	point	박종복	1	30	60000	60000	0	
1518	93	2024-10-13 23:33	point	김미선	1	30	60000	6700	53300	
1529	52	2024-10-14 5:06	point	박종복	1	9	18000	18000	0	
1530	52	2024-10-14 5:08	point	박종복	1	10	20000	0	20000	
1573	13	2024-10-14 13:41	point	최순애	1	28	56000	28000	28000	
1574	13	2024-10-14 13:43	point	최순애	1	8	16000	8000	8000	
1579	110	2024-10-14 14:23	point	한관명	1	37	74000	0	74000	
1580	110	2024-10-14 14:27	point	한관명	1	45	90000	90000	0	
1581	110	2024-10-14 14:31	point	한관명	1	8	16000	16000	0	
1582	110	2024-10-14 14:31	point	한관명	1	14	28000	0	28000	
1584	12	2024-10-14 15:03	point	송성석	1	30	60000	0	60000	
1587	93	2024-10-14 23:54	point	김미선	1	13	26000	500	25500	
1591	13	2024-10-15 2:16	point	최순애	1	4	8000	4000	4000	
1603	12	2024-10-15 5:44	point	송성석	1	1	2000	2000	0	
1604	12	2024-10-15 5:45	point	송성석	1	1	2000	2000	0	
1641	14	2024-10-15 11:11	point	김순덕	1	178	356000	0	356000	
1666	12	2024-10-15 15:23	point	송성석	1	10	20000	20000	0	
1668	15	2024-10-15 17:17	point	나상봉	1	20	40000	0	40000	
1669	93	2024-10-16 0:03	point	김미선	1	10	20000	16100	3900	
1713	10	2024-10-16 15:22	point	이상호	1	200	400000	200000	200000	
1715	12	2024-10-16 15:51	point	송성석	1	10	20000	20000	0	
1722	295	2024-10-16 21:40	point	이강걸	1	60	120000	60000	60000	
1723	93	2024-10-16 23:46	point	김미선	1	9	18000	8000	10000	
1832	37	2024-10-17 15:48	point	서예담	1	34	68000	34500	33500	
1833	37	2024-10-17 15:50	point	서예담	1	10	20000	10000	10000	
1837	13	2024-10-17 23:00	point	최순애	1	30	60000	30000	30000	
1838	13	2024-10-17 23:01	point	최순애	1	10	20000	10000	10000	
1839	93	2024-10-17 23:55	point	김미선	1	11	22000	17300	4700	
1869	9	2024-10-18 5:48	point	주용철	1	25	50000	0	50000	
1870	10	2024-10-18 5:49	point	이상호	1	100	200000	100000	100000	
1893	6	2024-10-18 7:46	point	김옥순	1	297	594000	0	594000	
1895	6	2024-10-18 7:49	point	김옥순	1	1182	2364000	2364000	0	
1931	9	2024-10-18 11:53	point	주용철	1	38	76000	0	76000	
1941	9	2024-10-18 17:41	point	주용철	1	8	16000	0	16000	
2056	9	2024-10-19 14:51	point	주용철	1	8	16000	0	16000	
2071	93	2024-10-20 0:12	point	김미선	1	10	20000	0	20000	
2093	14	2024-10-20 4:24	point	김순덕	1	153	306000	0	306000	
2094	55	2024-10-20 4:41	point	이성구	1	40	80000	0	80000	
2095	55	2024-10-20 4:45	point	이성구	1	46	92000	92000	0	
2096	55	2024-10-20 5:07	point	이성구	1	20	40000	14000	26000	
2100	9	2024-10-20 5:36	point	주용철	1	26	52000	0	52000	
2101	295	2024-10-20 5:49	point	이강걸	1	18	36000	18000	18000	
2163	14	2024-10-20 13:21	point	김순덕	1	23	46000	0	46000	
2170	9	2024-10-20 15:13	point	주용철	1	200	400000	0	400000	
2171	9	2024-10-20 15:16	point	주용철	1	40	80000	0	80000	
2175	9	2024-10-20 17:02	point	주용철	1	8	16000	0	16000	
2179	93	2024-10-21 0:12	point	김미선	1	10	20000	0	20000	
2186	9	2024-10-21 2:46	point	주용철	1	350	700000	0	700000	
2187	9	2024-10-21 2:48	point	주용철	1	72	144000	0	144000	
2211	9	2024-10-21 7:14	point	주용철	1	22	44000	0	44000	
2224	10	2024-10-21 7:48	point	이상호	1	100	200000	100000	100000	
2266	37	2024-10-21 12:01	point	서예담	1	36	72000	36000	36000	
2268	37	2024-10-21 12:02	point	서예담	1	11	22000	11000	11000	
2276	9	2024-10-21 13:15	point	주용철	1	16	32000	0	32000	
2277	18	2024-10-21 13:18	point	김은경	1	86	172000	0	172000	
2301	93	2024-10-21 23:29	point	김미선	1	10	20000	0	20000	
2306	12	2024-10-22 2:19	point	송성석	1	5	10000	0	10000	
2311	15	2024-10-22 4:20	point	나상봉	1	12	24000	0	24000	
2330	10	2024-10-22 7:22	point	이상호	1	100	200000	100000	100000	
2334	9	2024-10-22 8:03	point	주용철	1	108	216000	0	216000	
2335	9	2024-10-22 8:04	point	주용철	1	22	44000	0	44000	
2392	9	2024-10-22 14:35	point	주용철	1	10	20000	0	20000	
2407	93	2024-10-22 23:30	point	김미선	1	10	20000	0	20000	
2415	9	2024-10-23 1:11	point	주용철	1	12	24000	0	24000	
2422	14	2024-10-23 3:10	point	김순덕	1	34	68000	0	68000	
2495	12	2024-10-23 12:06	point	송성석	1	10	20000	20000	0	
2504	13	2024-10-23 14:00	point	최순애	1	24	48000	24000	24000	
2509	9	2024-10-23 14:13	point	주용철	1	26	52000	0	52000	
2514	6	2024-10-23 15:19	point	김옥순	1	228	456000	0	456000	
2515	37	2024-10-23 15:26	point	서예담	1	33	66000	33000	33000	
2520	9	2024-10-23 16:54	point	주용철	1	14	28000	0	28000	
2526	93	2024-10-23 23:22	point	김미선	1	10	20000	0	20000	
2570	13	2024-10-24 14:21	point	최순애	1	22	44000	22000	22000	
2580	12	2024-10-24 20:20	point	송성석	1	5	10000	0	10000	
2581	93	2024-10-24 23:35	point	김미선	1	10	20000	0	20000	
2644	39	2024-10-25 9:19	point	권혁미	1	75	150000	0	150000	
2658	110	2024-10-25 11:38	point	한관명	1	30	60000	0	60000	
2688	93	2024-10-26 0:06	point	김미선	1	10	20000	0	20000	
2691	52	2024-10-26 1:57	point	박종복	1	26	52000	52000	0	
2692	52	2024-10-26 1:57	point	박종복	1	30	60000	0	60000	
2710	14	2024-10-26 6:15	point	김순덕	1	500	1000000	0	1000000	
2711	14	2024-10-26 6:29	point	김순덕	1	500	1000000	0	1000000	
2740	13	2024-10-26 13:46	point	최순애	1	420	840000	420000	420000	
2741	13	2024-10-26 13:48	point	최순애	1	135	270000	135000	135000	
2742	13	2024-10-26 13:50	point	최순애	1	40	80000	40000	40000	
2757	93	2024-10-27 0:23	point	김미선	1	10	20000	0	20000	
2789	6	2024-10-27 4:05	point	김옥순	1	1260	2520000	0	2520000	
2795	10	2024-10-27 5:10	point	이상호	1	500	1000000	500000	500000	
2811	15	2024-10-27 8:50	point	나상봉	1	325	650000	0	650000	
2813	15	2024-10-27 8:56	point	나상봉	1	50	100000	0	100000	
2815	15	2024-10-27 8:59	point	나상봉	1	7	14000	0	14000	
2821	110	2024-10-27 10:23	point	한관명	1	73	146000	0	146000	
2822	110	2024-10-27 10:25	point	한관명	1	80	160000	160000	0	
2824	110	2024-10-27 10:27	point	한관명	1	15	30000	30000	0	
2825	110	2024-10-27 10:28	point	한관명	1	26	52000	0	52000	
2826	110	2024-10-27 10:32	point	한관명	1	12	24000	16000	8000	
2831	52	2024-10-27 11:35	point	박종복	1	34	68000	68000	0	
2832	52	2024-10-27 11:36	point	박종복	1	40	80000	0	80000	
2839	93	2024-10-27 22:49	point	김미선	1	10	20000	0	20000	
2853	10	2024-10-28 2:13	point	이상호	1	400	800000	400000	400000	
2882	52	2024-10-28 5:42	point	박종복	1	6	12000	0	12000	
2894	9	2024-10-28 6:20	point	주용철	1	50	100000	0	100000	
2899	9	2024-10-28 8:28	point	주용철	1	200	400000	0	400000	
2909	13	2024-10-28 9:48	point	최순애	1	15	30000	15000	15000	
2924	12	2024-10-28 21:21	point	송성석	1	10	20000	0	20000	
2925	93	2024-10-28 22:54	point	김미선	1	5	10000	0	10000	
2995	93	2024-10-29 11:58	point	김미선	1	5	10000	0	10000	
3011	96	2024-10-29 13:46	point	양동숙	1	10	20000	0	20000	
3016	39	2024-10-29 15:06	point	권혁미	1	1	2000	0	2000	
3019	96	2024-10-29 19:36	point	양동숙	1	5	10000	10000	0	
3042	13	2024-10-30 5:06	point	최순애	1	10	20000	10000	10000	




<?php
// 데이터베이스 연결 설정
$servername = "localhost";
$username = "root";
$password = "your_password";
$database = "your_database";

// 사용자가 확인 버튼을 눌렀는지 확인
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute']) && $_POST['execute'] === 'yes') {
    $conn = new mysqli($servername, $username, $password, $database);

    // 연결 확인
    if ($conn->connect_error) {
        die("데이터베이스 연결 실패: " . $conn->connect_error);
    }

    echo "데이터베이스 연결 성공<br>";

    // 주문 번호 리스트
    $order_ids = [20]; // 테스트를 위해 order_id = 20만 처리

    foreach ($order_ids as $order_id) {
        echo "주문 번호 처리 중: $order_id<br>";

        // commissions 테이블에서 해당 order_id에 대한 레코드 가져오기
        $sql = "SELECT id, user_id, amount, cash_point_amount, mileage_point_amount 
                FROM commissions 
                WHERE order_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo "commissions 테이블에서 수수료 데이터 검색 성공: $order_id<br>";
            while ($row = $result->fetch_assoc()) {
                $commission_id = $row['id'];
                $user_id = $row['user_id'];
                $amount = $row['amount'];
                $cash_point = $row['cash_point_amount'];
                $mileage_point = $row['mileage_point_amount'];

                echo "수수료 처리 중: commission ID = $commission_id, 사용자 ID = $user_id<br>";

                // commissions 테이블에 음수 레코드 삽입
                $insert_sql = "INSERT INTO commissions (user_id, commission_type, amount, cash_point_amount, mileage_point_amount, 
                                commission_rate, order_id, minus_from, source_user_id, source_amount, created_at) 
                               SELECT user_id, commission_type, -amount, -cash_point_amount, -mileage_point_amount, 
                                      commission_rate, order_id, id, source_user_id, source_amount, NOW()
                               FROM commissions 
                               WHERE id = ?";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("i", $commission_id);
                if ($insert_stmt->execute()) {
                    echo "음수 수수료 레코드 삽입 완료: commission ID = $commission_id<br>";
                } else {
                    echo "음수 수수료 레코드 삽입 실패: commission ID = $commission_id. 오류: " . $conn->error . "<br>";
                }

                // users 테이블 업데이트
                $update_sql = "UPDATE users 
                               SET commission_total = commission_total - ?, 
                                   cash_points = cash_points - ?, 
                                   mileage_points = mileage_points - ? 
                               WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("dddi", $amount, $cash_point, $mileage_point, $user_id);
                if ($update_stmt->execute()) {
                    echo "사용자 업데이트 완료: 사용자 ID = $user_id<br>";
                } else {
                    echo "사용자 업데이트 실패: 사용자 ID = $user_id. 오류: " . $conn->error . "<br>";
                }
            }
        } else {
            echo "commissions 테이블에서 해당 주문 번호에 대한 수수료 데이터 없음: $order_id<br>";
        }

        // 종료
        $stmt->close();
    }

    $conn->close();
    echo "모든 처리가 완료되었습니다.<br>";
} else {
    // 실행 여부를 묻는 폼 출력
    ?>
    <form method="POST" action="">
        <p>스크립트를 실행하시겠습니까?</p>
        <button type="submit" name="execute" value="yes">확인</button>
        <button type="submit" name="execute" value="no">취소</button>
    </form>
    <?php
}
?>
