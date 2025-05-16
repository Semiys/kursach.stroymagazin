<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Проверка прав администратора
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен.']);
    exit;
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Неверный ID заказа.']);
    exit;
}

try {
    // 1. Получаем основную информацию о заказе
    $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmtOrder->execute([$order_id]);
    $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Заказ не найден.']);
        exit;
    }

    // 2. Получаем товары в заказе
    $stmtItems = $pdo->prepare("SELECT oi.*, g.title as product_title 
                                FROM order_items oi
                                JOIN goods g ON oi.product_id = g.id
                                WHERE oi.order_id = ?");
    $stmtItems->execute([$order_id]);
    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    // 3. Получаем информацию о пользователе (если есть)
    $user_info_html = '<p><strong>Клиент:</strong> Незарегистрированный пользователь</p>';
    if ($order['user_id']) {
        $stmtUser = $pdo->prepare("SELECT id, login, email, name FROM users WHERE id = ?");
        $stmtUser->execute([$order['user_id']]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $user_info_html = '<p><strong>Клиент:</strong> ' . htmlspecialchars($user['name'] ?: $user['login']) . ' (ID: ' . $user['id'] . ')</p>';
            $user_info_html .= '<p><strong>Email:</strong> ' . htmlspecialchars($user['email']) . '</p>';
        } else {
            $user_info_html = '<p><strong>Клиент:</strong> Зарегистрированный пользователь (ID: ' . $order['user_id'] . '), данные не найдены.</p>';
        }
    }
    // В таблице orders нет user_email, user_phone, user_name, поэтому берем из users или оставляем общую информацию
    // Если user_id нет, то это гостевой заказ, и эти поля могут быть в самой таблице orders, если они там предусмотрены для гостей
    // На данный момент, скриншот таблицы orders не показывает таких полей. Если они есть, нужно их добавить в SELECT для $stmtOrder

    // Статусы заказов (можно вынести в config или helper)
    $order_statuses = [
        'pending' => 'Ожидает обработки',
        'processing' => 'В обработке',
        'shipped' => 'Отправлен',
        'completed' => 'Выполнен',
        'cancelled' => 'Отменен',
        'refunded' => 'Возвращен'
    ];
    $status_display = htmlspecialchars($order_statuses[$order['status']] ?? $order['status']);

    // Начинаем формирование HTML
    $html = '<div class="container-fluid">';
    $html .= '<h4>Информация о заказе</h4>';
    $html .= '<div class="row"><div class="col-md-6">';
    $html .= '<p><strong>ID Заказа:</strong> ' . htmlspecialchars($order['id']) . '</p>';
    $html .= '<p><strong>Дата создания:</strong> ' . htmlspecialchars(date("d.m.Y H:i:s", strtotime($order['created_at']))) . '</p>';
    $html .= '<p><strong>Статус:</strong> ' . $status_display . '</p>';
    $html .= '<p><strong>Общая сумма:</strong> ' . htmlspecialchars(number_format($order['total_amount'], 2, '.', ' ')) . ' ₽</p>';
    
    // Информация, которая может быть в таблице orders, но на скриншоте её нет (пример)
    // $html .= '<p><strong>Адрес доставки:</strong> ' . htmlspecialchars($order['shipping_address'] ?? 'Не указан') . '</p>';
    // $html .= '<p><strong>Метод оплаты:</strong> ' . htmlspecialchars($order['payment_method'] ?? 'Не указан') . '</p>';
    // $html .= '<p><strong>Промокод:</strong> ' . htmlspecialchars($order['promo_code'] ?? 'Нет') . '</p>';
    // $html .= '<p><strong>Скидка по промокоду:</strong> ' . htmlspecialchars(number_format($order['discount_amount'] ?? 0, 2, '.', ' ')) . ' ₽</p>';
    
    $html .= '</div><div class="col-md-6">';
    $html .= $user_info_html;
    $html .= '</div></div>';

    $html .= '<hr><h4>Товары в заказе</h4>';
    if (count($items) > 0) {
        $html .= '<table class="table table-sm table-bordered">';
        $html .= '<thead><tr><th>Товар</th><th>Кол-во</th><th>Цена за ед.</th><th>Сумма</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($items as $item) {
            $item_total = $item['quantity'] * $item['price'];
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['product_title'] ?? 'ID товара: '.$item['product_id']) . ' (ID: ' . htmlspecialchars($item['product_id']) . ')</td>';
            $html .= '<td>' . htmlspecialchars($item['quantity']) . '</td>';
            $html .= '<td>' . htmlspecialchars(number_format($item['price'], 2, '.', ' ')) . ' ₽</td>';
            $html .= '<td>' . htmlspecialchars(number_format($item_total, 2, '.', ' ')) . ' ₽</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    } else {
        $html .= '<p>Нет товаров в этом заказе.</p>';
    }
    $html .= '</div>'; // Закрываем container-fluid

    echo json_encode(['success' => true, 'html' => $html]);

} catch (PDOException $e) {
    error_log("Ошибка при получении деталей заказа ID: " . $order_id . " - " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных при получении деталей заказа.']);
    exit;
} 