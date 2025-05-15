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

    // 1. Обновляем категорию в таблице goods
    $stmt_goods = $pdo->prepare("UPDATE goods SET category = ? WHERE category = ?");
    $stmt_goods->execute([$new_category_name, $old_category_name]);
    $affected_goods = $stmt_goods->rowCount();

    // 2. Проверяем, была ли старая категория скрыта
    $stmt_check_hidden = $pdo->prepare("SELECT COUNT(*) FROM hidden_categories WHERE category_name = ?");
    $stmt_check_hidden->execute([$old_category_name]);
    $is_hidden = $stmt_check_hidden->fetchColumn() > 0;

    if ($is_hidden) {
        // Если старая категория была скрыта, обновляем ее в hidden_categories
        // Сначала удаляем старую запись (если новое имя отличается), затем вставляем новую, чтобы избежать проблем с уникальностью, если имя не изменилось
        $stmt_delete_hidden = $pdo->prepare("DELETE FROM hidden_categories WHERE category_name = ?");
        $stmt_delete_hidden->execute([$old_category_name]);
        
        $stmt_insert_hidden = $pdo->prepare("INSERT INTO hidden_categories (category_name) VALUES (?)");
        $stmt_insert_hidden->execute([$new_category_name]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Категория \'' . htmlspecialchars($old_category_name) . '\' успешно переименована в \'' . htmlspecialchars($new_category_name) . '\' для ' . $affected_goods . ' товар(а/ов).', 'affected_rows' => $affected_goods]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error renaming category: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных при переименовании категории: ' . $e->getMessage()]);
}
?> 