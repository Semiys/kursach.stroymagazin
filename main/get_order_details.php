<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Необходима авторизация'
    ]);
    exit;
}

// Получаем данные из POST запроса
$raw_data = file_get_contents('php://input');
error_log("Получены данные: " . $raw_data);

$data = json_decode($raw_data, true);
error_log("Декодированные данные: " . print_r($data, true));

// Проверка валидности JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Ошибка JSON: " . json_last_error_msg());
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при разборе JSON: ' . json_last_error_msg()
    ]);
    exit;
}

$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Неверный ID заказа'
    ]);
    exit;
}

try {
    // Проверяем, принадлежит ли заказ текущему пользователю
    $check_stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $check_stmt->execute([$order_id, $_SESSION['user_id']]);
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Заказ не найден или не принадлежит вам'
        ]);
        exit;
    }
    
    // Получаем информацию о заказе
    $order_stmt = $pdo->prepare("
        SELECT id, user_id, total_amount, status, created_at, 
               shipping_address, payment_method, promo_code, discount_amount
        FROM orders 
        WHERE id = ?
    ");
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode([
            'success' => false,
            'message' => 'Заказ не найден в базе данных'
        ]);
        exit;
    }
    
    // Получаем товары в заказе
    $items_stmt = $pdo->prepare("
        SELECT oi.product_id, oi.quantity, oi.price, oi.discount_percentage, g.title
        FROM order_items oi
        LEFT JOIN goods g ON oi.product_id = g.id
        WHERE oi.order_id = ?
    ");
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Проверяем наличие товаров и заполняем заголовки, если какие-то товары были удалены
    foreach ($items as $key => $item) {
        if (empty($item['title'])) {
            $items[$key]['title'] = 'Товар #' . $item['product_id'] . ' (удален)';
        }
    }
    
    // Форматируем дату для отображения
    $created_at = date('d.m.Y H:i', strtotime($order['created_at']));
    
    $response = [
        'success' => true,
        'order_id' => $order['id'],
        'user_id' => $order['user_id'],
        'total_amount' => (float)$order['total_amount'],
        'status' => $order['status'],
        'created_at' => $created_at,
        'shipping_address' => $order['shipping_address'],
        'payment_method' => $order['payment_method'],
        'promo_code' => $order['promo_code'],
        'discount_amount' => (float)$order['discount_amount'],
        'items' => $items
    ];
    
    error_log("Отправляемый ответ: " . print_r($response, true));
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Ошибка при получении данных заказа: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Произошла ошибка при получении данных заказа: ' . $e->getMessage()
    ]);
    exit;
} 