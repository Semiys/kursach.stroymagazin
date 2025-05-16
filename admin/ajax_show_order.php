<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/audit_logger.php';
header('Content-Type: application/json');

$response = ['success' => false, 'error' => 'Неизвестная ошибка.'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $response['error'] = 'Доступ запрещен.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Неверный метод запроса.';
    echo json_encode($response);
    exit;
}

$order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

if (empty($order_id)) {
    $response['error'] = 'ID заказа не указан или некорректен.';
    echo json_encode($response);
    exit;
}

try {
    // Проверим, существует ли заказ и не показан ли он уже
    $checkStmt = $pdo->prepare("SELECT is_hidden FROM orders WHERE id = ?");
    $checkStmt->execute([$order_id]);
    $currentOrder = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentOrder) {
        $response['error'] = 'Заказ (ID: ' . $order_id . ') не найден.';
        echo json_encode($response);
        exit;
    }

    if ($currentOrder['is_hidden'] == 0) {
        $response['error'] = 'Заказ (ID: ' . $order_id . ') уже видим.';
        echo json_encode($response);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE orders SET is_hidden = 0 WHERE id = ?");
    if ($stmt->execute([$order_id])) {
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Заказ (ID: ' . $order_id . ') успешно сделан видимым.';
            unset($response['error']);

            log_audit_action(
                action: 'ORDER_SHOWN',
                user_id: $_SESSION['user_id'],
                target_type: 'order',
                target_id: $order_id,
                details: ['order_id' => $order_id]
            );
        } else {
            $response['error'] = 'Не удалось показать заказ (затронуто 0 строк, возможно, он был удален параллельно).';
        }
    } else {
        $response['error'] = 'Не удалось показать заказ в базе данных.';
    }
} catch (PDOException $e) {
    error_log("Error showing order: " . $e->getMessage());
    $response['error'] = 'Ошибка базы данных при показе заказа: ' . $e->getMessage();
}

echo json_encode($response);
?> 