<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'moder') {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса.']);
    exit;
}

$category_name_to_delete = trim($_POST['category_name_to_delete'] ?? '');

if (empty($category_name_to_delete)) {
    echo json_encode(['success' => false, 'error' => 'Название категории для удаления не указано.']);
    exit;
}

try {
    // Проверим, сколько товаров будет затронуто
    $stmt_count = $pdo->prepare("SELECT COUNT(*) as count FROM goods WHERE category = :category_name");
    $stmt_count->bindParam(':category_name', $category_name_to_delete);
    $stmt_count->execute();
    $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $products_affected = $count_result ? $count_result['count'] : 0;

    if ($products_affected === 0) {
        echo json_encode(['success' => true, 'message' => 'Не найдено товаров с категорией \'' . htmlspecialchars($category_name_to_delete) . '\'. Действий не требуется.', 'affected_rows' => 0]);
        exit;
    }

    $pdo->beginTransaction();

    // Устанавливаем категорию в NULL. Можно также установить в пустую строку ('') если бизнес-логика это предполагает.
    $stmt_update = $pdo->prepare("UPDATE goods SET category = NULL WHERE category = :category_name_to_delete");
    $stmt_update->bindParam(':category_name_to_delete', $category_name_to_delete);
    
    $stmt_update->execute();
    $affected_rows = $stmt_update->rowCount();

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Название категории \'' . htmlspecialchars($category_name_to_delete) . '\' успешно очищено (установлено в NULL) для ' . $affected_rows . ' товар(а/ов).', 'affected_rows' => $affected_rows]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error deleting/clearing category name: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных при удалении/очистке названия категории: ' . $e->getMessage()]);
}
?> 