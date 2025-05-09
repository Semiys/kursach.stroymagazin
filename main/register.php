<?php
// Подключаем автозагрузчик Composer и классы PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Загружаем автозагрузчик Composer
require '../vendor/autoload.php'; // Путь к vendor относительно main/

session_start();
require_once '../config.php'; // Путь к config.php относительно main/

// Initialize variables for messages
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize input
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Пожалуйста, заполните все поля.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Пароли не совпадают.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Некорректный формат email.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error_message = "Пользователь с таким email уже существует.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // Generate a unique confirmation token
            $confirmation_token = bin2hex(random_bytes(32)); // Generates a 64-character hex token

            // Insert user into the database
            // Phone and address are optional for now based on the form
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, confirm_token) VALUES (?, ?, ?, ?, ?)");
                // Default role to 'user'.
                if ($stmt->execute([$name, $email, $hashed_password, 'user', $confirmation_token])) {

                    // === НАЧАЛО КОДА ОТПРАВКИ EMAIL ===
                    $mail = new PHPMailer(true);

                    try {
                        // Настройки сервера SMTP из config.php
                        $mail->isSMTP();
                        $mail->Host       = MAIL_SMTP_HOST;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = MAIL_SMTP_USERNAME;
                        $mail->Password   = MAIL_SMTP_PASSWORD;
                        $mail->SMTPSecure = MAIL_SMTP_SECURE;
                        $mail->Port       = MAIL_SMTP_PORT;
                        $mail->CharSet    = 'UTF-8';

                        // Получатели
                        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
                        $mail->addAddress($email, $name);     // Email и имя получателя

                        // Контент письма
                        $mail->isHTML(true);
                        $mail->Subject = 'Подтверждение регистрации на сайте СтройМагазин';

                        // Формируем URL для подтверждения
                        $confirmation_link = APP_URL . "/main/confirm_email.php?token=" . $confirmation_token;

                        $mail->Body    = "Здравствуйте, " . htmlspecialchars($name) . "!<br><br>" .
                                         "Спасибо за регистрацию на сайте СтройМагазин.<br>" .
                                         "Пожалуйста, перейдите по следующей ссылке для подтверждения вашего email:<br>" .
                                         "<a href='" . $confirmation_link . "'>" . $confirmation_link . "</a><br><br>" .
                                         "Если вы не регистрировались, просто проигнорируйте это письмо.";
                        $mail->AltBody = "Здравствуйте, " . htmlspecialchars($name) . "! " .
                                         "Спасибо за регистрацию на сайте СтройМагазин. " .
                                         "Пожалуйста, скопируйте и вставьте следующую ссылку в ваш браузер для подтверждения email: " .
                                         $confirmation_link . " " .
                                         "Если вы не регистрировались, просто проигнорируйте это письмо.";

                        $mail->send();
                        $success_message = 'Регистрация прошла успешно! Письмо для подтверждения отправлено на ваш email.';

                    } catch (Exception $e) {
                        // Ошибка отправки email. Можно залогировать $mail->ErrorInfo
                        // Важно: Не показывай $mail->ErrorInfo пользователю напрямую в продакшене
                        // Вместо сообщения об успехе, установим сообщение об ошибке, но регистрация уже произошла.
                        $error_message = "Регистрация прошла успешно, но не удалось отправить письмо для подтверждения. Ошибка: " . $mail->ErrorInfo; // Убрать ->ErrorInfo в продакшене
                        // Возможно, стоит удалить пользователя или пометить его как неподтвержденного,
                        // чтобы он не мог войти, пока email не подтвержден.
                        // Но пока оставим как есть - регистрация есть, но письмо не ушло.
                    }
                    // === КОНЕЦ КОДА ОТПРАВКИ EMAIL ===

                    // Пока не редиректим, чтобы видеть сообщения
                    // header("Location: login.php");
                    // exit();
                } else {
                    $error_message = "Ошибка при регистрации. Пожалуйста, попробуйте еще раз.";
                }
            } catch (PDOException $e) {
                // Log error $e->getMessage() for debugging
                $error_message = "Ошибка базы данных: " . $e->getMessage(); // Выводим реальную ошибку для отладки
            }
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

<body class="d-flex justify-content-center align-items-center vh-100 bg-light">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-body">
                        <h2 class="text-center mb-4">Регистрация</h2>
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>
                        <form action="" method="post">
                            <div class="mb-3">
                                <label for="name" class="form-label">Имя</label>
                                <input type="text" name="name" id="name" class="form-control" value="" required="">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control" value="" required="">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Пароль</label>
                                <input type="password" name="password" id="password" class="form-control" required="">
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Подтвердите пароль</label>
                                <input type="password" name="confirm_password" id="confirm_password"
                                    class="form-control" required="">
                            </div>
                            <button type="submit" class="btn btn-success w-100">Зарегистрироваться</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-outline-secondary w-100">Уже есть аккаунт? Войти</a>
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