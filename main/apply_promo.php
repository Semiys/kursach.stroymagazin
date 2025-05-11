<?php
// Подключаем необходимые файлы
session_start();
require_once '../config.php';

// Проверяем, отправлена ли форма и не пуста ли корзина
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promo_code']) && !empty($_SESSION['cart'])) {
    // Получаем введенный промокод и обрабатываем его
    $promo_code = trim($_POST['promo_code']);
    
    // Валидация промокода
    if (empty($promo_code)) {
        $_SESSION['promo_message'] = [
            'type' => 'danger',
            'text' => 'Введите промокод'
        ];
        header('Location: cart.php');
        exit;
    }
    
    // Рассчитываем общую сумму корзины для проверки минимальной суммы заказа
    $cart_total = 0;
    $product_ids = array_keys($_SESSION['cart']);
    
    if (!empty($product_ids)) {
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $sql = "SELECT id, price FROM goods WHERE id IN ($placeholders)";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($product_ids);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($products as $product) {
                $quantity_in_cart = (int)$_SESSION['cart'][$product['id']];
                $cart_total += $product['price'] * $quantity_in_cart;
            }
        } catch (PDOException $e) {
            error_log('Error calculating cart total: ' . $e->getMessage());
            $_SESSION['promo_message'] = [
                'type' => 'danger',
                'text' => 'Ошибка при проверке промокода. Пожалуйста, попробуйте позже.'
            ];
            header('Location: cart.php');
            exit;
        }
    }
    
    // Проверяем, существует ли такой промокод в базе данных
    try {
        $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1");
        $stmt->execute([$promo_code]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$promo) {
            $_SESSION['promo_message'] = [
                'type' => 'danger',
                'text' => 'Промокод не найден или не активен'
            ];
            header('Location: cart.php');
            exit;
        }
        
        // Проверяем срок действия промокода
        $now = new DateTime();
        
        if ($promo['starts_at'] && new DateTime($promo['starts_at']) > $now) {
            $_SESSION['promo_message'] = [
                'type' => 'danger',
                'text' => 'Промокод еще не активен'
            ];
            header('Location: cart.php');
            exit;
        }
        
        if ($promo['expires_at'] && new DateTime($promo['expires_at']) < $now) {
            $_SESSION['promo_message'] = [
                'type' => 'danger',
                'text' => 'Срок действия промокода истек'
            ];
            header('Location: cart.php');
            exit;
        }
        
        // Проверяем ограничение на использование
        if ($promo['usage_limit'] !== null && $promo['usage_count'] >= $promo['usage_limit']) {
            $_SESSION['promo_message'] = [
                'type' => 'danger',
                'text' => 'Лимит использования промокода исчерпан'
            ];
            header('Location: cart.php');
            exit;
        }
        
        // Проверяем минимальную сумму заказа
        if ($cart_total < $promo['min_order_amount']) {
            $_SESSION['promo_message'] = [
                'type' => 'danger',
                'text' => "Промокод действует при заказе от " . number_format($promo['min_order_amount'], 0, '.', ' ') . "₽"
            ];
            header('Location: cart.php');
            exit;
        }
        
        // Рассчитываем скидку
        $discount_amount = 0;
        if ($promo['discount_type'] === 'percentage') {
            $discount_amount = $cart_total * ($promo['discount_value'] / 100);
            $discount_text = $promo['discount_value'] . '%';
        } else { // fixed
            $discount_amount = min($cart_total, $promo['discount_value']); // Скидка не может быть больше суммы корзины
            $discount_text = number_format($promo['discount_value'], 0, '.', ' ') . '₽';
        }
        
        // Сохраняем информацию о промокоде в сессии
        $_SESSION['applied_promo_code'] = $promo_code;
        $_SESSION['promo_discount'] = $discount_amount;
        $_SESSION['promo_message'] = [
            'type' => 'success',
            'text' => "Промокод применен! Скидка: $discount_text"
        ];
        
        // Обновляем счетчик использования промокода
        // Примечание: в реальном приложении это лучше делать при оформлении заказа
        $stmt = $pdo->prepare("UPDATE promo_codes SET usage_count = usage_count + 1 WHERE code = ?");
        $stmt->execute([$promo_code]);
        
    } catch (PDOException $e) {
        error_log('Error processing promo code: ' . $e->getMessage());
        $_SESSION['promo_message'] = [
            'type' => 'danger',
            'text' => 'Ошибка при проверке промокода. Пожалуйста, попробуйте позже.'
        ];
    }
} else {
    $_SESSION['promo_message'] = [
        'type' => 'danger',
        'text' => 'Ошибка при применении промокода'
    ];
}

header('Location: cart.php');
exit; 