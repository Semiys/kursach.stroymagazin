<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/audit_logger.php'; // Подключаем логгер
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Неизвестная ошибка.'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $response['message'] = 'Доступ запрещен.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Неверный метод запроса.';
    echo json_encode($response);
    exit;
}

$category_name_to_show = $_POST['category_name'] ?? null;

if (empty($category_name_to_show)) {
    $response['message'] = 'Название категории не указано (ожидался параметр category_name).';
    echo json_encode($response);
    exit;
}

try {
    // Проверяем, скрыта ли категория
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM hidden_categories WHERE category_name = ?");
    $stmt_check->execute([$category_name_to_show]);
    if ($stmt_check->fetchColumn() == 0) {
        $response['message'] = 'Эта категория не была скрыта (или уже отображается).';
        echo json_encode($response);
        exit;
    }

    // Удаляем из скрытых
    $stmt_delete = $pdo->prepare("DELETE FROM hidden_categories WHERE category_name = ?");
    if ($stmt_delete->execute([$category_name_to_show])) {
        $response['success'] = true;
        $response['message'] = 'Категория "' . htmlspecialchars($category_name_to_show) . '" успешно снова отображена.';

        log_audit_action(
            action: 'CATEGORY_SHOWN',
            user_id: $_SESSION['user_id'],
            target_type: 'category',
            details: ['category_name' => $category_name_to_show]
        );

    } else {
        $response['message'] = 'Не удалось отобразить категорию в базе данных.';
    }

} catch (PDOException $e) {
    error_log("Error showing category: " . $e->getMessage());
    $response['message'] = 'Ошибка базы данных при отображении категории.';
}

echo json_encode($response);
?> 