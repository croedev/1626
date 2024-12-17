<?php
// Set timezone
date_default_timezone_set('Asia/Seoul');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'lidyahkc_0'); // Replace with your database username
define('DB_PASS', 'lidya2016$'); // Replace with your database password
define('DB_NAME', 'lidyahkc_1626'); // Replace with your database name

// Connect to the database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// List of users who received free products
$freeUsers = [
    ['name' => '김미선', 'phone' => '010-8770-7388', 'quantity' => 6000],
    ['name' => '유향숙', 'phone' => '010-2011-0200', 'quantity' => 3000],
    ['name' => '이상호', 'phone' => '010-2577-3900', 'quantity' => 2000],
    // ... (Add all other users here)
    ['name' => '백설희', 'phone' => '010-9688-8718', 'quantity' => 1000],
];

// Start processing each user
foreach ($freeUsers as $userInfo) {
    $name = $userInfo['name'];
    $phone = $userInfo['phone'];
    $quantity = $userInfo['quantity'];

    echo "Processing user: Name = $name, Phone = $phone\n";

    // Step 1: Find the user_id
    $stmt = $conn->prepare("SELECT id FROM users WHERE name = ? AND phone = ?");
    $stmt->bind_param("ss", $name, $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "User not found: $name ($phone)\n";
        continue; // Proceed to next user
    }

    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    echo "Found user_id: $user_id for $name\n";

    // Step 2 & 3: Find orders with payment_method not 'bank' or 'point' and matching quantity
    $stmt = $conn->prepare("
        SELECT id FROM orders 
        WHERE user_id = ? 
        AND payment_method NOT IN ('bank', 'point') 
        AND quantity = ?
    ");
    $stmt->bind_param("ii", $user_id, $quantity);
    $stmt->execute();
    $ordersResult = $stmt->get_result();

    if ($ordersResult->num_rows === 0) {
        echo "No matching orders found for user_id: $user_id with quantity: $quantity\n";
        continue; // Proceed to next user
    }

    // Process each order
    while ($order = $ordersResult->fetch_assoc()) {
        $order_id = $order['id'];
        echo "Found order_id: $order_id for user_id: $user_id\n";

        // Step 4: Find commissions associated with this order
        $stmt = $conn->prepare("
            SELECT * FROM commissions 
            WHERE order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $commissionsResult = $stmt->get_result();

        if ($commissionsResult->num_rows === 0) {
            echo "No commissions found for order_id: $order_id\n";
            continue; // Proceed to next order
        }

        // Process each commission
        while ($commission = $commissionsResult->fetch_assoc()) {
            $commission_id = $commission['id'];
            $commission_user_id = $commission['user_id'];
            $amount = $commission['amount'];
            $cash_point_amount = $commission['cash_point_amount'];
            $mileage_point_amount = $commission['mileage_point_amount'];
            $commission_type = $commission['commission_type'];

            echo "Processing commission_id: $commission_id, Type: $commission_type, Amount: $amount for user_id: $commission_user_id\n";

            // Step 5: Insert negative commission to reverse it
            $stmt = $conn->prepare("
                INSERT INTO commissions 
                (user_id, commission_type, amount, cash_point_amount, mileage_point_amount, commission_rate, order_id, source_user_id, source_amount, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $negative_amount = -$amount;
            $negative_cash_point = -$cash_point_amount;
            $negative_mileage_point = -$mileage_point_amount;
            $commission_rate = $commission['commission_rate'];
            $source_user_id = $commission['source_user_id'];
            $source_amount = $commission['source_amount'];

            $stmt->bind_param(
                "isdddddid",
                $commission_user_id,
                $commission_type,
                $negative_amount,
                $negative_cash_point,
                $negative_mileage_point,
                $commission_rate,
                $order_id,
                $source_user_id,
                $source_amount
            );

            if ($stmt->execute()) {
                echo "Inserted negative commission for commission_id: $commission_id\n";
            } else {
                echo "Failed to insert negative commission for commission_id: $commission_id\n";
                continue; // Proceed to next commission
            }

            // Step 6: Update user's commission totals
            $stmt = $conn->prepare("
                UPDATE users 
                SET commission_total = commission_total + ?,
                    cash_points = cash_points + ?,
                    mileage_points = mileage_points + ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "dddi",
                $negative_amount,
                $negative_cash_point,
                $negative_mileage_point,
                $commission_user_id
            );

            if ($stmt->execute()) {
                echo "Updated user_id: $commission_user_id's commission totals.\n";
            } else {
                echo "Failed to update user_id: $commission_user_id's commission totals.\n";
            }
        }
    }

    echo "Completed processing for $name\n\n";
}

// Close the database connection
$conn->close();
?>
