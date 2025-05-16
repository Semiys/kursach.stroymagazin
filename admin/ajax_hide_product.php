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

$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

if (empty($product_id)) {
    $response['error'] = 'ID товара не указан или некорректен.';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE goods SET is_hidden = 1 WHERE id = ?");
    if ($stmt->execute([$product_id])) {
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Товар (ID: ' . $product_id . ') успешно скрыт.';
            unset($response['error']);

            // Получаем название товара для лога
            $product_title = 'Неизвестный товар';
            $title_stmt = $pdo->prepare("SELECT title FROM goods WHERE id = ?");
            if ($title_stmt->execute([$product_id])) {
                $product_data = $title_stmt->fetch(PDO::FETCH_ASSOC);
                if ($product_data && !empty($product_data['title'])) {
                    $product_title = $product_data['title'];
                }
            }

            log_audit_action(
                action: 'PRODUCT_HIDDEN',
                user_id: $_SESSION['user_id'],
                target_type: 'product',
                target_id: $product_id,
                details: ['product_title' => $product_title]
            );

        } else {
            $response['error'] = 'Товар (ID: ' . $product_id . ') не найден или уже был скрыт.';
        }
    } else {
        $response['error'] = 'Не удалось скрыть товар в базе данных.';
    }
} catch (PDOException $e) {
    error_log("Error hiding product: " . $e->getMessage());
    $response['error'] = 'Ошибка базы данных при скрытии товара: ' . $e->getMessage();
}

echo json_encode($response);
?> 