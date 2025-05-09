<?php
session_start(); // Запускаем сессию

// Уничтожаем все данные сессии
$_SESSION = array();

// Если нужно уничтожить куку сессии, это тоже можно сделать
// (обычно это делают, если используется session.use_cookies)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Наконец, уничтожаем сессию
session_destroy();

// Перенаправляем пользователя на главную страницу
header("Location: ../index.php");
exit();
?> 