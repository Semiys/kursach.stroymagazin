<?php
// Логика выхода из системы
session_start();
session_destroy();
header("Location: /index.php");
exit;
?> 