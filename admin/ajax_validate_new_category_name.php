<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса.']);
    exit;
}

$category_name = trim($_POST['category_name'] ?? '');

if (empty($category_name)) {
    echo json_encode(['success' => false, 'error' => 'Название категории не может быть пустым.']);
    exit;
}

try {
    $stmt_check = $pdo->prepare("SELECT COUNT(*) as count FROM goods WHERE category = :category_name");
    $stmt_check->bindParam(':category_name', $category_name);
    $stmt_check->execute();
    $result = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['count'] > 0) {
        echo json_encode(['success' => true, 'exists' => true, 'message' => 'Название категории \'' . htmlspecialchars($category_name) . '\' уже используется у ' . $result['count'] . ' товар(а/ов).']);
    } else {
        echo json_encode(['success' => true, 'exists' => false, 'message' => 'Название категории \'' . htmlspecialchars($category_name) . '\' новое и может быть использовано.']);
    }

} catch (PDOException $e) {
    error_log("Error validating category name: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных при проверке названия категории.']);
}
?> 