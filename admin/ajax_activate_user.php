<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/audit_logger.php';
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

$user_id_to_activate = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

if (empty($user_id_to_activate)) {
    $response['message'] = 'ID пользователя не указан или некорректен.';
    echo json_encode($response);
    exit;
}

// Администратор не может активировать сам себя, т.к. он и так должен быть активен
// Эта проверка скорее для консистентности, т.к. кнопка и не должна появляться для самого себя.
if ($user_id_to_activate == $_SESSION['user_id']) {
    $response['message'] = 'Вы не можете активировать свой собственный аккаунт таким образом.';
    echo json_encode($response);
    exit;
}

try {
    $checkStmt = $pdo->prepare("SELECT is_active, login FROM users WHERE id = ?");
    $checkStmt->execute([$user_id_to_activate]);
    $user = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $response['message'] = 'Пользователь (ID: ' . $user_id_to_activate . ') не найден.';
        echo json_encode($response);
        exit;
    }

    if ($user['is_active'] == 1) {
        $response['message'] = 'Пользователь \'' . htmlspecialchars($user['login']) . '\' уже активен.';
        echo json_encode($response);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
    if ($stmt->execute([$user_id_to_activate])) {
        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Пользователь \'' . htmlspecialchars($user['login']) . '\' (ID: ' . $user_id_to_activate . ') успешно активирован.';
            
            log_audit_action(
                action: 'USER_ACTIVATED',
                user_id: $_SESSION['user_id'],
                target_type: 'user',
                target_id: $user_id_to_activate,
                details: ['activated_user_login' => $user['login']]
            );
        } else {
            $response['message'] = 'Не удалось активировать пользователя (возможно, он был изменен параллельно).';
        }
    } else {
        $response['message'] = 'Ошибка выполнения запроса к базе данных.';
    }
} catch (PDOException $e) {
    error_log("Error activating user: " . $e->getMessage());
    $response['message'] = 'Ошибка базы данных: ' . $e->getMessage();
}

echo json_encode($response);
?> 