<?php
session_start();
require_once __DIR__ . '/../config.php'; // Подключение к БД и конфигурации
require_once __DIR__ . '/includes/audit_logger.php'; // Подключение функции логирования

header('Content-Type: application/json');

// Проверка, что пользователь авторизован и является администратором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен. Требуются права администратора.']);
    exit;
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса.']);
    exit;
}

// Получение данных из POST-запроса
$user_id_to_change = $_POST['user_id'] ?? null;
$new_role = $_POST['new_role'] ?? null;
$current_admin_id = $_SESSION['user_id'];

// Валидация входных данных
if (empty($user_id_to_change) || !is_numeric($user_id_to_change)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный ID пользователя.']);
    exit;
}
$user_id_to_change = (int)$user_id_to_change;

// Определение допустимых ролей в зависимости от роли текущего пользователя (администратора)
$assignable_roles = [];
if ($_SESSION['user_role'] === 'admin') {
    $assignable_roles = ['user', 'moder', 'admin'];
} else {
    // Этот случай не должен произойти из-за проверки выше, но для безопасности:
    echo json_encode(['success' => false, 'message' => 'Недостаточно прав для назначения ролей.']);
    exit;
}

if (empty($new_role) || !in_array($new_role, $assignable_roles)) {
    echo json_encode(['success' => false, 'message' => 'Некорректная или недопустимая новая роль.']);
    exit;
}

// Запрет на изменение собственной роли
if ($user_id_to_change == $current_admin_id) {
    echo json_encode(['success' => false, 'message' => 'Вы не можете изменить свою собственную роль через этот интерфейс.']);
    exit;
}

// Дополнительная проверка: если пытаются назначить роль 'admin', убедимся, что текущий пользователь действительно админ
// (это уже проверено в $assignable_roles, но как двойная проверка безопасности)
if ($new_role === 'admin' && $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Только администратор может назначать роль администратора.']);
    exit;
}


try {
    // Опционально: Проверка, существует ли пользователь, которому меняют роль
    $stmt_check_user = $pdo->prepare("SELECT role FROM users WHERE id = :user_id");
    $stmt_check_user->bindParam(':user_id', $user_id_to_change, PDO::PARAM_INT);
    $stmt_check_user->execute();
    $targetUser = $stmt_check_user->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        echo json_encode(['success' => false, 'message' => 'Целевой пользователь не найден.']);
        exit;
    }
    $old_role = $targetUser['role']; // Получаем старую роль для логирования

    // Логика для предотвращения изменения роли "супер-администратора" (например, ID=1) другими администраторами
    // if ($user_id_to_change == 1 && $current_admin_id != 1) {
    //     echo json_encode(['success' => false, 'message' => 'Вы не можете изменить роль этого системного администратора.']);
    //     exit;
    // }

    // Обновление роли пользователя в базе данных
    $stmt = $pdo->prepare("UPDATE users SET role = :new_role WHERE id = :user_id");
    $stmt->bindParam(':new_role', $new_role, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $user_id_to_change, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            // Запись в журнал аудита
            $log_details = [
                'old_role' => $old_role,
                'new_role' => $new_role,
                'changed_user_id' => $user_id_to_change
            ];
            log_audit_action(
                action: 'USER_ROLE_CHANGED', 
                user_id: $current_admin_id, 
                target_type: 'user', 
                target_id: $user_id_to_change, 
                details: $log_details
            );
            echo json_encode(['success' => true, 'message' => 'Роль пользователя успешно обновлена.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Роль пользователя не была изменена (возможно, она уже такая).']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении роли в базе данных.']);
    }
} catch (PDOException $e) {
    // Log error $e->getMessage();
    error_log("PDOException in ajax_update_user_role.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных. Свяжитесь с администратором.']);
}

?> 