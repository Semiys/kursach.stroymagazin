<?php
// Скрипт для проверки ошибок PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Последние ошибки PHP</h2>";
echo "<pre>";

// Вывод последних 50 строк из лога ошибок PHP
$log_file = ini_get('error_log');
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $last_lines = array_slice($lines, -50);
    echo implode("\n", $last_lines);
} else {
    echo "Файл лога ошибок не найден: " . $log_file . "\n";
    
    // Альтернативный путь для OSPanel
    $ospanel_log = "D:/OSPanel/userdata/logs/PHP/php_errors.log";
    if (file_exists($ospanel_log)) {
        echo "Найден альтернативный лог ошибок: " . $ospanel_log . "\n\n";
        $log_content = file_get_contents($ospanel_log);
        $lines = explode("\n", $log_content);
        $last_lines = array_slice($lines, -50);
        echo implode("\n", $last_lines);
    } else {
        echo "Альтернативный файл лога также не найден.\n";
    }
}

echo "</pre>";
?> 