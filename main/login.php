<?php
session_start(); // Начинаем сессию в самом начале файла
require_once '../config.php'; // Подключаем конфигурацию БД

$error_message = ''; // Переменная для сообщений об ошибках

// Проверяем, была ли отправлена форма
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = trim($_POST['identifier']); // Будем использовать это поле для email или логина
    $password = $_POST['password'];

    // Простая валидация на клиенте уже есть (required), но серверная тоже не помешает
    if (empty($identifier) || empty($password)) {
        $error_message = "Пожалуйста, заполните все поля.";
    } else {
        try {
            // Ищем пользователя по email или логину
            $stmt = $pdo->prepare("SELECT id, email, login, password, role, accept, is_active FROM users WHERE email = ? OR login = ?");
            $stmt->execute([$identifier, $identifier]); // Передаем $identifier дважды для каждого плейсхолдера
            $user = $stmt->fetch();

            if ($user) {
                // Пользователь найден, проверяем статус подтверждения email
                if ($user['accept'] == 1) {
                    // Email подтвержден, проверяем активен ли пользователь
                    if ($user['is_active'] == 1) {
                        // Пользователь активен, проверяем пароль
                    if (password_verify($password, $user['password'])) {
                        // Пароль верный
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email']; // Можно добавить и другие данные, если нужны
                        $_SESSION['user_login'] = $user['login']; // Сохраним и логин в сессию
                        $_SESSION['user_role'] = $user['role'];
                        // Перенаправляем на главную страницу или в личный кабинет
                        header("Location: ../index.php"); // Предполагаем, что главная страница в корне
                        exit();
                    } else {
                        // Неверный пароль
                        $error_message = "Неверный email/логин или пароль.";
                        }
                    } else {
                        // Пользователь деактивирован
                        $error_message = "Ваш аккаунт деактивирован. Обратитесь к администратору.";
                    }
                } else {
                    // Email не подтвержден
                    $error_message = "Ваш email еще не подтвержден. Пожалуйста, проверьте свою почту и перейдите по ссылке для подтверждения.";
                }
            } else {
                // Пользователь с таким email не найден
                $error_message = "Неверный email/логин или пароль.";
            }
        } catch (PDOException $e) {
            // Ошибка базы данных (можно залогировать $e->getMessage())
            $error_message = "Произошла ошибка: " . $e->getMessage(); // Выводим ошибку для отладки
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../template/css/main.css">
</head>

<body class="d-flex justify-content-center align-items-center vh-100 bg-light">

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
                                <label for="identifier" class="form-label">Email или Логин</label>
                                <input type="text" name="identifier" id="identifier" class="form-control" value="<?php echo isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : ''; ?>" required>
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