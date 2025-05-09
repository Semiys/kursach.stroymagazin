<?php
session_start();
require_once '../config.php'; // Путь к config.php относительно main/

$message = '';
$message_type = 'danger'; // Тип сообщения для Bootstrap (danger, success)

// 1. Получаем токен из URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // 2. Ищем пользователя с таким токеном
        $stmt = $pdo->prepare("SELECT id FROM users WHERE confirm_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // 3. Пользователь найден, обновляем его статус (очищаем токен и устанавливаем флаг accept)
            $update_stmt = $pdo->prepare("UPDATE users SET confirm_token = NULL, accept = 1 WHERE id = ?");
            if ($update_stmt->execute([$user['id']])) {
                $message = "Ваш email успешно подтвержден! Теперь вы можете войти в свой аккаунт.";
                $message_type = 'success';
            } else {
                $message = "Произошла ошибка при подтверждении вашего email. Пожалуйста, попробуйте еще раз.";
            }
        } else {
            // 4. Пользователь с таким токеном не найден (возможно, уже подтвержден или токен неверный)
            $message = "Неверный или устаревший токен подтверждения.";
        }
    } catch (PDOException $e) {
        // Ошибка базы данных
        // В реальном приложении здесь должно быть логирование ошибки $e->getMessage()
        $message = "Ошибка базы данных при проверке токена.";
    }

} else {
    // 5. Токен не был передан в URL
    $message = "Токен подтверждения не найден.";
}

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Подтверждение Email</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        .message-container {
            max-width: 600px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container message-container">
        <div class="alert alert-<?php echo $message_type; ?>" role="alert">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php if ($message_type === 'success'): ?>
            <a href="login.php" class="btn btn-primary">Перейти к входу</a>
        <?php endif; ?>
    </div>
</body>
</html> 