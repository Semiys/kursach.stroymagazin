<?php
session_start();
require_once __DIR__ . '/../config.php'; // Подключение к БД и конфигурации

header('Content-Type: application/json');

// Проверка, что пользователь авторизован и является модератором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'moder') {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен.']);
    exit;
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса.']);
    exit;
}

// Получение данных из POST-запроса
$user_id = $_POST['user_id'] ?? null;
$new_role = $_POST['new_role'] ?? null;

// Валидация входных данных
if (empty($user_id) || !is_numeric($user_id)) {
    echo json_encode(['success' => false, 'error' => 'Некорректный ID пользователя.']);
    exit;
}

$allowed_roles = ['user', 'moder']; // Допустимые роли
if (empty($new_role) || !in_array($new_role, $allowed_roles)) {
    echo json_encode(['success' => false, 'error' => 'Некорректная новая роль.']);
    exit;
}

// Не позволяем модератору изменять свою собственную роль через этот интерфейс
// Это для безопасности, чтобы случайно не лишить себя прав модератора.
// Изменение собственной роли или роли администратора (если таковой будет) должно происходить другим, более контролируемым способом.
if ($user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'Вы не можете изменить свою собственную роль.']);
    exit;
}

try {
    // Получаем текущую роль пользователя, чтобы убедиться, что мы не пытаемся изменить роль администратора (если такая логика будет добавлена)
    // $stmt_check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    // $stmt_check->execute([$user_id]);
    // $currentUser = $stmt_check->fetch(PDO::FETCH_ASSOC);
    // if ($currentUser && $currentUser['role'] === 'admin') {
    //     echo json_encode(['success' => false, 'error' => 'Нельзя изменять роль администратора.']);
    //     exit;
    // }

    // Обновление роли пользователя в базе данных
    $stmt = $pdo->prepare("UPDATE users SET role = :new_role WHERE id = :user_id");
    $stmt->bindParam(':new_role', $new_role, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Роль пользователя успешно обновлена.']);
        } else {
            // Это может произойти, если пользователь с таким ID не найден, или роль уже такая же
            echo json_encode(['success' => false, 'error' => 'Пользователь не найден или роль не была изменена.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при обновлении роли в базе данных.']);
    }
} catch (PDOException $e) {
    // Log error $e->getMessage();
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}

?> 