<?php

// Настройки подключения к базе данных
define('DB_SERVER', 'your_db_host');
define('DB_PORT', 'your_db_port'); // например, 3306 для MySQL/MariaDB
define('DB_USERNAME', 'your_db_username');
define('DB_PASSWORD', 'YOUR_DB_PASSWORD_HERE');
define('DB_NAME', 'your_db_name');
define('DB_CHARSET', 'utf8mb4');

// Настройки для PHPMailer (например, для Gmail SMTP)
define('MAIL_SMTP_HOST', 'smtp.gmail.com');
define('MAIL_SMTP_USERNAME', 'your_gmail_username@gmail.com');
define('MAIL_SMTP_PASSWORD', 'YOUR_GMAIL_APP_PASSWORD_HERE'); // 16-значный пароль приложения
define('MAIL_SMTP_SECURE', 'tls'); // 'tls' (для PHPMailer::ENCRYPTION_STARTTLS) или 'ssl' (для PHPMailer::ENCRYPTION_SMTPS)
define('MAIL_SMTP_PORT', 587);       // 587 для TLS, 465 для SSL
define('MAIL_FROM_ADDRESS', 'your_gmail_username@gmail.com'); // С какого email отправлять
define('MAIL_FROM_NAME', 'Название Вашего Сайта'); // Имя отправителя

// Базовый URL вашего сайта (для формирования ссылок в письмах и т.д.)
define('APP_URL', 'http://yourwebsite.com');

// DSN и опции PDO (остаются как есть, так как не содержат прямых кредов)
$dsn = "mysql:host=" . DB_SERVER . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Переменная $pdo должна быть создана в реальном config.php после подключения к БД
// В этом файле-примере мы не устанавливаем соединение, чтобы не вызывать ошибку
// если реальные креды не заданы.
// Пример того, как это будет в реальном config.php:
/*
try {
     $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
} catch (\PDOException $e) {
     die("Ошибка подключения к БД: " . $e->getMessage());
}
*/

?> 