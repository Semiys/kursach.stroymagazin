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
$new_status = trim(filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_SPECIAL_CHARS));

// Валидация данных
if (!$order_id) {
    echo json_encode(['error' => 'Неверный ID заказа.', 'success' => false]);
    exit;
}

$allowed_statuses = [
    'Ожидает оплаты',
    'В обработке',
    'Оплачен',
    'Комплектуется',
    'Передан в доставку',
    'Доставлен',
    'Отменен',
    'Возврат'
];

if (empty($new_status) || !in_array($new_status, $allowed_statuses)) {
    echo json_encode(['error' => 'Недопустимый статус заказа.', 'success' => false]);
    exit;
}

try {
    // Получаем старый статус для логирования
    $old_status_stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $old_status_stmt->execute([$order_id]);
    $old_status_row = $old_status_stmt->fetch(PDO::FETCH_ASSOC);
    $old_status = $old_status_row ? $old_status_row['status'] : null;

    // Обновляем статус заказа
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);

    if ($stmt->rowCount() > 0) {
        log_audit_action(
            action: 'ORDER_STATUS_UPDATED',
            user_id: $_SESSION['user_id'],
            target_type: 'order',
            target_id: $order_id,
            details: [
                'old_status' => $old_status,
                'new_status' => $new_status
            ]
        );
        echo json_encode(['success' => true, 'message' => 'Статус заказа успешно обновлен.']);
    } else {
        // Возможно, заказ с таким ID не найден или статус уже такой же
        // Проверим, существует ли заказ и не является ли статус таким же
        $checkStmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
        $checkStmt->execute([$order_id]);
        $currentOrder = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentOrder) {
             echo json_encode(['error' => 'Заказ не найден.', 'success' => false]);
        } elseif ($currentOrder['status'] === $new_status) {
            echo json_encode(['success' => true, 'message' => 'Статус заказа не изменен (уже установлен).']);
        } else {
            echo json_encode(['error' => 'Не удалось обновить статус заказа (затронуто 0 строк).', 'success' => false]);
        }
    }

} catch (PDOException $e) {
    error_log("Ошибка при обновлении статуса заказа ID: {$order_id} на статус '{$new_status}' - " . $e->getMessage());
    echo json_encode(['error' => 'Ошибка базы данных при обновлении статуса.', 'success' => false]);
    exit;
} 