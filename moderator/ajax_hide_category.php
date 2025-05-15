<?php
session_start();
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Неизвестная ошибка.'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'moder') {
    $response['message'] = 'Доступ запрещен.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Неверный метод запроса.';
    echo json_encode($response);
    exit;
}

$category_name_to_hide = $_POST['category_name'] ?? null;

if (empty($category_name_to_hide)) {
    $response['message'] = 'Название категории не указано (ожидался параметр category_name).';
    echo json_encode($response);
    exit;
}

try {
    // Проверяем, не скрыта ли уже категория
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM hidden_categories WHERE category_name = ?");
    $stmt_check->execute([$category_name_to_hide]);
    if ($stmt_check->fetchColumn() > 0) {
        $response['message'] = 'Эта категория уже скрыта.';
        echo json_encode($response);
        exit;
    }

    // Добавляем в скрытые
    $stmt_insert = $pdo->prepare("INSERT INTO hidden_categories (category_name) VALUES (?)");
    if ($stmt_insert->execute([$category_name_to_hide])) {
        $response['success'] = true;
        $response['message'] = 'Категория "' . htmlspecialchars($category_name_to_hide) . '" успешно скрыта.';
    } else {
        $response['message'] = 'Не удалось скрыть категорию в базе данных.';
    }

} catch (PDOException $e) {
    error_log("Error hiding category: " . $e->getMessage());
    $response['message'] = 'Ошибка базы данных при скрытии категории.'; 
}

echo json_encode($response);
?> 