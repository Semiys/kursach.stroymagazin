<?php
// Инициализируем сессию
session_start();

// Удаляем информацию о промокоде из сессии
unset($_SESSION['applied_promo_code']);
unset($_SESSION['promo_discount']);

// Создаем сообщение для пользователя
$_SESSION['promo_message'] = [
    'type' => 'info',
    'text' => 'Промокод удален'
];

// Перенаправляем обратно в корзину
header('Location: cart.php');
exit; 