<?php
session_start();
require_once '../config.php';
require_once '../vendor/phpmailer/phpmailer/src/Exception.php';
require_once '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once '../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Проверяем, залогинен ли пользователь
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Необходима авторизация'
    ]);
    exit;
}

// Получаем ID заказа из запроса
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Неверный ID заказа'
    ]);
    exit;
}

try {
    // Проверяем, принадлежит ли заказ текущему пользователю
    $check_stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
    $check_stmt->execute([$order_id, $_SESSION['user_id']]);
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Заказ не найден или не принадлежит вам'
        ]);
        exit;
    }
    
    // Получаем информацию о заказе
    $order_stmt = $pdo->prepare("
        SELECT o.*, u.name, u.email 
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode([
            'success' => false,
            'message' => 'Заказ не найден в базе данных'
        ]);
        exit;
    }
    
    // Получаем товары в заказе
    $items_stmt = $pdo->prepare("
        SELECT oi.product_id, oi.quantity, oi.price, oi.discount_percentage, g.title
        FROM order_items oi
        LEFT JOIN goods g ON oi.product_id = g.id
        WHERE oi.order_id = ?
    ");
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Проверяем наличие товаров и заполняем заголовки, если какие-то товары были удалены
    $order_items = [];
    $total_cart_value = 0;
    
    foreach ($items as $item) {
        $product_title = !empty($item['title']) ? $item['title'] : 'Товар #' . $item['product_id'] . ' (удален)';
        $price = (float)$item['price'];
        $quantity = (int)$item['quantity'];
        $discount_percentage = (int)$item['discount_percentage'];
        
        $discounted_price = $price * (1 - $discount_percentage / 100);
        $item_total = $discounted_price * $quantity;
        $total_cart_value += $item_total;
        
        $order_items[] = [
            'title' => $product_title,
            'price' => $price,
            'quantity_in_cart' => $quantity,
            'discount' => $discount_percentage,
            'item_total_price' => $item_total
        ];
    }
    
    // Отправляем email с информацией о заказе
    $mail_sent = sendOrderDetailsEmail(
        $order['email'],
        $order['name'],
        $order_id,
        $order['created_at'],
        $order_items,
        $total_cart_value,
        10.00, // Стандартная стоимость доставки
        (float)$order['discount_amount'],
        (float)$order['total_amount'],
        $order['shipping_address'],
        '', // Телефон (может отсутствовать в базе)
        $order['payment_method'],
        $order['promo_code']
    );
    
    if ($mail_sent) {
        echo json_encode([
            'success' => true,
            'message' => 'Информация о заказе отправлена на ваш email'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Не удалось отправить письмо. Попробуйте позже.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Ошибка при отправке информации о заказе: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Произошла ошибка при обработке запроса'
    ]);
    exit;
}

/**
 * Отправляет email с информацией о заказе
 */
function sendOrderDetailsEmail($email, $name, $order_id, $order_date, $order_items, $order_total, $shipping, $discount, $grand_total, $address, $phone, $payment_method, $promo_code = null) {
    // Создаем экземпляр PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Настройки сервера из config.php
        $mail->isSMTP();
        $mail->Host = MAIL_SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_SMTP_USERNAME;
        $mail->Password = MAIL_SMTP_PASSWORD;
        $mail->SMTPSecure = MAIL_SMTP_SECURE == 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = MAIL_SMTP_PORT;
        
        // Получатели
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($email, $name);
        
        // Контент
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "Информация о вашем заказе #{$order_id}";
        
        // Формируем HTML-тело письма
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; }
                .header { text-align: center; padding: 20px; background-color: #f8f9fa; }
                .content { padding: 20px; }
                .order-details { margin-bottom: 30px; }
                .order-summary { margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f8f9fa; }
                .total-row td { font-weight: bold; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #777; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Информация о заказе #{$order_id}</h2>
                </div>
                
                <div class='content'>
                    <div class='order-details'>
                        <h3>Основная информация:</h3>
                        <p><strong>Дата заказа:</strong> " . date('d.m.Y H:i', strtotime($order_date)) . "</p>
                        <p><strong>Статус заказа:</strong> В обработке</p>
                        <p><strong>Способ оплаты:</strong> " . ($payment_method == 'card' ? 'Банковская карта' : 'Наличными при получении') . "</p>
                        " . ($promo_code ? "<p><strong>Применен промокод:</strong> {$promo_code}</p>" : "") . "
                        <p><strong>Адрес доставки:</strong> {$address}</p>
                    </div>
                    
                    <div class='order-summary'>
                        <h3>Товары в заказе:</h3>
                        <table>
                            <tr>
                                <th>Товар</th>
                                <th>Цена</th>
                                <th>Кол-во</th>
                                <th>Сумма</th>
                            </tr>";
        
        // Добавляем товары
        foreach ($order_items as $item) {
            $message .= "
                            <tr>
                                <td>{$item['title']}</td>
                                <td>" . number_format($item['price'], 0, '.', ' ') . "₽</td>
                                <td>{$item['quantity_in_cart']}</td>
                                <td>" . number_format($item['item_total_price'], 0, '.', ' ') . "₽</td>
                            </tr>";
        }
        
        // Добавляем итоги
        $message .= "
                            <tr>
                                <td colspan='3' style='text-align:right;'>Стоимость товаров:</td>
                                <td>" . number_format($order_total, 0, '.', ' ') . "₽</td>
                            </tr>
                            <tr>
                                <td colspan='3' style='text-align:right;'>Доставка:</td>
                                <td>" . number_format($shipping, 0, '.', ' ') . "₽</td>
                            </tr>";
        
        if ($discount > 0) {
            $message .= "
                            <tr>
                                <td colspan='3' style='text-align:right;'>Скидка:</td>
                                <td>-" . number_format($discount, 0, '.', ' ') . "₽</td>
                            </tr>";
        }
        
        $message .= "
                            <tr class='total-row'>
                                <td colspan='3' style='text-align:right;'>Итого к оплате:</td>
                                <td>" . number_format($grand_total, 0, '.', ' ') . "₽</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>Если у вас возникли вопросы, свяжитесь с нашей службой поддержки.</p>
                    <p>&copy; " . date('Y') . " " . MAIL_FROM_NAME . ". Все права защищены.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->Body = $message;
        $mail->AltBody = "Информация о заказе #{$order_id}";
        
        // Сохраняем копию HTML письма в файл для отладки
        $email_debug_dir = __DIR__ . '/../logs';
        if (!is_dir($email_debug_dir)) {
            mkdir($email_debug_dir, 0755, true);
        }
        file_put_contents($email_debug_dir . "/order_email_details_{$order_id}.html", $message);
        
        // Отправляем письмо
        $mail->send();
        error_log("Письмо с информацией о заказе успешно отправлено на адрес {$email}");
        return true;
    } catch (Exception $e) {
        error_log("Ошибка при отправке письма с информацией о заказе: " . $e->getMessage());
        if (isset($mail)) {
            error_log("Детали ошибки SMTP: " . $mail->ErrorInfo);
        }
        return false;
    }
} 