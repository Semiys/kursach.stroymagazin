<?php
session_start();
require_once '../config.php';

// Проверяем, залогинен ли пользователь
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Начинаем транзакцию
    $pdo->beginTransaction();
    
    // Получаем несколько случайных товаров
    $products_stmt = $pdo->query("SELECT id, title, price, discount FROM goods WHERE stock_quantity > 0 ORDER BY RAND() LIMIT 3");
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        throw new Exception("Не найдены товары для создания тестового заказа");
    }
    
    // Случайные значения для заказа
    $shipping_address = "Тестовый адрес для доставки, дом 123";
    $payment_method = rand(0, 1) ? 'card' : 'cash';
    $status = 'В обработке';
    $promo_code = null;
    $discount_amount = 0;
    
    // Рассчитываем общую сумму
    $total_amount = 0;
    $order_items = [];
    
    foreach ($products as $product) {
        $quantity = rand(1, 3);
        $price = $product['price'];
        $discount_percentage = $product['discount'] ?? 0;
        
        // Рассчитываем цену с учетом скидки
        $discounted_price = $price * (1 - ($discount_percentage / 100));
        $item_total = $discounted_price * $quantity;
        $total_amount += $item_total;
        
        $order_items[] = [
            'product_id' => $product['id'],
            'quantity' => $quantity,
            'price' => $price,
            'discount_percentage' => $discount_percentage
        ];
    }
    
    // Создаем заказ
    $order_stmt = $pdo->prepare("
        INSERT INTO orders (
            user_id, total_amount, status, created_at, shipping_address, 
            payment_method, promo_code, discount_amount
        ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
    ");
    
    $order_stmt->execute([
        $user_id,
        $total_amount,
        $status,
        $shipping_address,
        $payment_method,
        $promo_code,
        $discount_amount
    ]);
    
    $order_id = $pdo->lastInsertId();
    
    // Добавляем товары в заказ
    $item_stmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id, product_id, quantity, price, discount_percentage
        ) VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($order_items as $item) {
        $item_stmt->execute([
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['discount_percentage']
        ]);
    }
    
    // Завершаем транзакцию
    $pdo->commit();
    
    // Устанавливаем флэш-сообщение
    $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => "Тестовый заказ #{$order_id} успешно создан!"
    ];
    
} catch (Exception $e) {
    // Откатываем транзакцию в случае ошибки
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Устанавливаем флэш-сообщение с ошибкой
    $_SESSION['flash_message'] = [
        'type' => 'danger',
        'text' => "Ошибка при создании тестового заказа: " . $e->getMessage()
    ];
    
    error_log("Ошибка при создании тестового заказа: " . $e->getMessage());
}

// Перенаправляем на страницу профиля
header('Location: profile.php');
exit();
?> 