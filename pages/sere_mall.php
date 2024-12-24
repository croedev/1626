<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$conn = db_connect();

// 상품 목록 가져오기
$stmt = $conn->prepare("SELECT id, name, description, cash_price, sere_price, discount_rate, image_url FROM products WHERE status = 'active'");
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'SERE MALL';
include __DIR__ . '/../includes/header.php';
?>

<style>
    body {
        background-color: #f5f5f5;
        color: #333;
    }

    .product-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        padding: 20px;
    }

    @media (max-width: 768px) {
        .product-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 10px;
        }
    }

    .product-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        overflow: hidden;
        transition: transform 0.2s;
    }

    .product-card:hover {
        transform: translateY(-5px);
    }

    .product-image {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .product-info {
        padding: 15px;
    }

    .product-title {
        font-size: 1rem;
        font-weight: bold;
        margin-bottom: 10px;
        height: 2.4em;
        overflow: hidden;
    }

    .price-info {
        font-size: 0.9rem;
        color: #666;
    }

    .original-price {
        text-decoration: line-through;
        color: #999;
    }

    .sere-price {
        color: #e44d26;
        font-size: 1.1rem;
        font-weight: bold;
    }

    .discount-badge {
        background: #e44d26;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.8rem;
        margin-left: 5px;
    }

    .order-button {
        width: 100%;
        background: #4CAF50;
        color: white;
        border: none;
        padding: 8px;
        border-radius: 5px;
        margin-top: 10px;
        cursor: pointer;
    }

    .order-button:hover {
        background: #45a049;
    }
</style>


<div class="container">
    <h2 class="text-center my-4">SERE MALL</h2>
    
    <div class="product-grid">
        <?php foreach ($products as $product): ?>
            <div class="product-card">
                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                     class="product-image">
                
                <div class="product-info">
                    <div class="product-title">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </div>
                    
                    <div class="price-info">
                        <div class="original-price">
                            현금가격: ￦<?php echo number_format($product['cash_price']); ?>
                        </div>
                        <div class="sere-price">
                            SERE 가격: <?php echo number_format($product['sere_price']); ?> SERE
                            <span class="discount-badge">
                                <?php echo $product['discount_rate']; ?>% OFF
                            </span>
                        </div>
                    </div>
                    
                    <button onclick="location.href='/sere_detail.php?id=<?php echo $product['id']; ?>'" 
                            class="order-button">
                        상세보기
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>