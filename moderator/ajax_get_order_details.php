<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Проверка прав модератора
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'moder') {
    echo json_encode(['error' => 'Доступ запрещен.']);
    exit;
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$order_id) {
    echo json_encode(['error' => 'Неверный ID заказа.']);
    exit;
}

$response = [
    'order' => null,
    'items' => [],
    'user' => null
];

try {
    // 1. Получаем основную информацию о заказе
    $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmtOrder->execute([$order_id]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['error' => 'Заказ не найден.']);
        exit;
    }
    $response['order'] = $order;

    // 2. Получаем товары в заказе
    // Присоединяем goods для получения названия товара
    $stmtItems = $pdo->prepare("SELECT oi.*, g.title as product_title 
                                FROM order_items oi
                                JOIN goods g ON oi.product_id = g.id
                                WHERE oi.order_id = ?");
    $stmtItems->execute([$order_id]);
    $response['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Получаем информацию о пользователе
    if ($order['user_id']) {
        $stmtUser = $pdo->prepare("SELECT id, login, email, name FROM users WHERE id = ?");
        $stmtUser->execute([$order['user_id']]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $response['user'] = $user;
        }
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Ошибка при получении деталей заказа ID: " . $order_id . " - " . $e->getMessage()); // Желательно логировать
    echo json_encode(['error' => 'Ошибка базы данных при получении деталей заказа. Пожалуйста, проверьте логи сервера для получения дополнительной информации.']);
    exit;
} 