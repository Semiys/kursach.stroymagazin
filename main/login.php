<?php
session_start(); // Начинаем сессию в самом начале файла
require_once '../config.php'; // Подключаем конфигурацию БД

$error_message = ''; // Переменная для сообщений об ошибках

// Проверяем, была ли отправлена форма
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Простая валидация на клиенте уже есть (required), но серверная тоже не помешает
    if (empty($email) || empty($password)) {
        $error_message = "Пожалуйста, заполните все поля.";
    } else {
        try {
            // Ищем пользователя по email
            $stmt = $pdo->prepare("SELECT id, email, password, role, accept FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Пользователь найден, проверяем статус подтверждения email
                if ($user['accept'] == 1) {
                    // Email подтвержден, проверяем пароль
                    if (password_verify($password, $user['password'])) {
                        // Пароль верный
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email']; // Можно добавить и другие данные, если нужны
                        $_SESSION['user_role'] = $user['role'];
                        // Перенаправляем на главную страницу или в личный кабинет
                        header("Location: ../index.php"); // Предполагаем, что главная страница в корне
                        exit();
                    } else {
                        // Неверный пароль
                        $error_message = "Неверный email или пароль.";
                    }
                } else {
                    // Email не подтвержден
                    $error_message = "Ваш email еще не подтвержден. Пожалуйста, проверьте свою почту и перейдите по ссылке для подтверждения.";
                }
            } else {
                // Пользователь с таким email не найден
                $error_message = "Неверный email или пароль.";
            }
        } catch (PDOException $e) {
            // Ошибка базы данных (можно залогировать $e->getMessage())
            $error_message = "Произошла ошибка. Пожалуйста, попробуйте еще раз."; // Общее сообщение для пользователя
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="template/css/main.css">
</head>

<body class="d-flex justify-content-center align-items-center vh-100">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow-lg">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Вход</h2>
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        <form action="" method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control" required="">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Пароль</label>
                                <input type="password" name="password" id="password" class="form-control" required="">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Войти</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="register.php" class="btn btn-outline-secondary w-100">Нет аккаунта?
                                Зарегистрироваться</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js"
        integrity="sha384-VQqxDN0EQCkWoxt/0vsQvZswzTHUVOImccYmSyhJTp7kGtPed0Qcx8rK9h9YEgx+"
        crossorigin="anonymous"></script>

</body>

</html>