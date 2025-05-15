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

$old_category_name = trim($_POST['old_category_name'] ?? '');
$new_category_name = trim($_POST['new_category_name'] ?? '');

if (empty($old_category_name)) {
    echo json_encode(['success' => false, 'error' => 'Старое название категории не указано.']);
    exit;
}

if (empty($new_category_name)) {
    echo json_encode(['success' => false, 'error' => 'Новое название категории не может быть пустым.']);
    exit;
}

if ($old_category_name === $new_category_name) {
    echo json_encode(['success' => false, 'error' => 'Новое название совпадает со старым. Изменений не требуется.']);
    exit;
}

try {
    // Проверим, сколько товаров будет затронуто
    $stmt_count = $pdo->prepare("SELECT COUNT(*) as count FROM goods WHERE category = :old_category_name");
    $stmt_count->bindParam(':old_category_name', $old_category_name);
    $stmt_count->execute();
    $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $products_affected = $count_result ? $count_result['count'] : 0;

    if ($products_affected === 0) {
        echo json_encode(['success' => true, 'message' => 'Не найдено товаров с категорией \'' . htmlspecialchars($old_category_name) . '\'. Обновление не требуется.', 'affected_rows' => 0]);
        exit;
    }

    $pdo->beginTransaction();

    $stmt_update = $pdo->prepare("UPDATE goods SET category = :new_category_name WHERE category = :old_category_name");
    $stmt_update->bindParam(':new_category_name', $new_category_name);
    $stmt_update->bindParam(':old_category_name', $old_category_name);
    
    $stmt_update->execute();
    $affected_rows = $stmt_update->rowCount();

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Категория \'' . htmlspecialchars($old_category_name) . '\' успешно переименована в \'' . htmlspecialchars($new_category_name) . '\' для ' . $affected_rows . ' товар(а/ов).', 'affected_rows' => $affected_rows]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error renaming category: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных при переименовании категории: ' . $e->getMessage()]);
}
?> 