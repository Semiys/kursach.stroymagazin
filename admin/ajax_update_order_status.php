<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/audit_logger.php'; // Подключаем логгер

header('Content-Type: application/json');

// Проверка прав администратора
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Доступ запрещен.', 'success' => false]);
    exit;
}

// Получаем данные из POST запроса
$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$new_status_key = trim(filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_SPECIAL_CHARS));

// Валидация данных
if (!$order_id) {
    echo json_encode(['error' => 'Неверный ID заказа.', 'success' => false]);
    exit;
}

// Используем тот же массив статусов, что и в manage_orders.php для консистентности
$defined_statuses = [
    'pending' => 'Ожидает обработки',
    'processing' => 'В обработке',
    'shipped' => 'Отправлен',
    'completed' => 'Выполнен',
    'cancelled' => 'Отменен',
    'refunded' => 'Возвращен'
    // Добавьте сюда другие статусы, если они есть в manage_orders.php и в БД
];

if (empty($new_status_key) || !array_key_exists($new_status_key, $defined_statuses)) {
    echo json_encode(['error' => 'Недопустимый ключ статуса заказа.', 'success' => false]);
    exit;
}

$new_status_for_db = $new_status_key; // В БД мы храним ключ статуса, например, 'pending'

try {
    // Получаем старый статус для логирования
    $old_status_stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $old_status_stmt->execute([$order_id]);
    $old_status_row = $old_status_stmt->fetch(PDO::FETCH_ASSOC);
    $old_status_key = $old_status_row ? $old_status_row['status'] : null;

    // Обновляем статус заказа
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status_for_db, $order_id]);

    if ($stmt->rowCount() > 0) {
        log_audit_action(
            action: 'ORDER_STATUS_UPDATED',
            user_id: $_SESSION['user_id'],
            target_type: 'order',
            target_id: $order_id,
            details: [
                'old_status' => $old_status_key,      // Логируем ключ старого статуса
                'new_status' => $new_status_for_db  // Логируем ключ нового статуса
            ]
        );
        echo json_encode(['success' => true, 'message' => 'Статус заказа успешно обновлен.']);
    } else {
        // Возможно, заказ с таким ID не найден или статус уже такой же
        $checkStmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $checkStmt->execute([$order_id]);
        $currentOrder = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentOrder) {
             echo json_encode(['error' => 'Заказ не найден.', 'success' => false]);
        } elseif ($currentOrder['status'] === $new_status_for_db) {
            echo json_encode(['success' => true, 'message' => 'Статус заказа не изменен (уже установлен).']);
        } else {
            echo json_encode(['error' => 'Не удалось обновить статус заказа (затронуто 0 строк).', 'success' => false]);
        }
    }

} catch (PDOException $e) {
    error_log("Ошибка при обновлении статуса заказа ID: {$order_id} на статус '{$new_status_for_db}' - " . $e->getMessage());
    echo json_encode(['error' => 'Ошибка базы данных при обновлении статуса.', 'success' => false]);
    exit;
} 