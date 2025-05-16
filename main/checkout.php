<?php
// Подключаем необходимые файлы
include '../template/header.php'; // Это должно запустить сессию
require_once '../config.php'; 
// Добавляем PHPMailer
require_once '../vendor/phpmailer/phpmailer/src/Exception.php';
require_once '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once '../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Удаляем ссылку на DaData PHP библиотеку, так как будем использовать JS версию
// require_once '../vendor/autoload.php';

// Проверяем, авторизован ли пользователь
$user_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// Проверяем, есть ли товары в корзине
$cart_is_empty = !isset($_SESSION['cart']) || empty($_SESSION['cart']);

// Если корзина пуста, перенаправляем на страницу корзины
if ($cart_is_empty) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Ваша корзина пуста. Добавьте товары перед оформлением заказа.'];
    header('Location: cart.php');
    exit;
}

// Получаем информацию о товарах в корзине
$cart_products = [];
$total_cart_value = 0;
$product_ids = array_keys($_SESSION['cart']);

if (!empty($product_ids)) {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $sql = "SELECT id, title, price, img, category, stock_quantity, discount FROM goods WHERE id IN ($placeholders)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($product_ids);
        $products_from_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products_from_db as $product) {
            $quantity_in_cart = isset($_SESSION['cart'][$product['id']]) ? (int)$_SESSION['cart'][$product['id']] : 0;
            if ($quantity_in_cart > 0) {
                // Учитываем скидку
                $discount = isset($product['discount']) ? intval($product['discount']) : 0;
                $original_price = floatval($product['price']);
                $discounted_price = $original_price;
                
                if ($discount > 0) {
                    $discounted_price = $original_price * (1 - $discount / 100);
                }
                
                $item_total_price = $discounted_price * $quantity_in_cart;
                $total_cart_value += $item_total_price;
                
                $cart_products[] = [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'price' => $original_price,
                    'discount' => $discount,
                    'discounted_price' => $discounted_price,
                    'img' => $product['img'],
                    'category' => $product['category'],
                    'stock_quantity' => (int)$product['stock_quantity'],
                    'quantity_in_cart' => $quantity_in_cart,
                    'item_total_price' => $item_total_price
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("checkout.php: Database error - " . $e->getMessage());
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Ошибка при загрузке данных корзины. Пожалуйста, попробуйте позже.'];
        header('Location: cart.php');
        exit;
    }
}

// Получаем информацию о пользователе, если он авторизован
$user_data = [];
if ($user_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("checkout.php: Error getting user data - " . $e->getMessage());
    }
}

// Расчет стоимости доставки и итоговой суммы
$shipping_cost = 10.00; // Фиксированная стоимость доставки
$discount_amount = isset($_SESSION['promo_discount']) ? (float)$_SESSION['promo_discount'] : 0;
$grand_total = $total_cart_value + $shipping_cost - $discount_amount;

// Обработка отправки формы заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    // Валидация формы
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    
    $errors = [];
    
    if (empty($name)) {
        $errors['name'] = 'Укажите ваше имя';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strpos($email, '@') === false) {
        $errors['email'] = 'Укажите корректный email';
    }
    
    if (empty($phone)) {
        $errors['phone'] = 'Укажите ваш телефон';
    } else {
        // Очищаем номер телефона от всего, кроме цифр и символа "+"
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Ограничиваем длину телефона до 12 символов
        if (strlen($cleanPhone) > 12) {
            $errors['phone'] = 'Номер телефона должен содержать не более 12 символов';
        } else {
            $phone = $cleanPhone; // Используем очищенный номер телефона
        }
    }
    
    if (empty($address)) {
        $errors['address'] = 'Укажите адрес доставки';
    }
    // Удаляем серверную валидацию адреса через DaData, так как теперь используем JS
    
    if (empty($payment_method)) {
        $errors['payment_method'] = 'Выберите способ оплаты';
    }
    
    if (empty($errors)) {
        try {
            // Начинаем транзакцию
            $pdo->beginTransaction();
        
            // Получаем ID пользователя, если он авторизован, или устанавливаем null для гостя
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            // Проверяем, что есть ID пользователя (должен быть, т.к. мы проверяем авторизацию выше)
            if ($user_id === null) {
                throw new Exception('Пользователь не авторизован');
        }
        
        // Рассчитываем итоговую сумму
        $shipping_cost = 10.00; // Фиксированная стоимость доставки
        $discount_amount = isset($_SESSION['promo_discount']) ? (float)$_SESSION['promo_discount'] : 0;
            $grand_total = $total_cart_value + $shipping_cost - $discount_amount;
        
        // Получаем информацию о промокоде, если он был применен
        $promo_code = isset($_SESSION['applied_promo_code']) ? $_SESSION['applied_promo_code'] : null;
            
            // Создаем запись в таблице orders
            $order_stmt = $pdo->prepare("
                INSERT INTO orders (
                    user_id, total_amount, status, created_at, 
                    shipping_address, payment_method, promo_code, discount_amount
                ) VALUES (
                    ?, ?, 'В обработке', NOW(), ?, ?, ?, ?
                )
            ");
            
            $order_stmt->execute([
                $user_id,
                $grand_total,
                $address,
                $payment_method,
                $promo_code,
                $discount_amount
            ]);
            
            // Получаем ID только что созданного заказа
            $order_id = $pdo->lastInsertId();
            
            // Добавляем товары в заказ
            $item_stmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, quantity, price, discount_percentage
                ) VALUES (
                    ?, ?, ?, ?, ?
                )
            ");
            
            foreach ($cart_products as $product) {
                $item_stmt->execute([
                    $order_id,
                    $product['id'],
                    $product['quantity_in_cart'],
                    $product['price'],
                    $product['discount']
                ]);
                
                // Обновляем количество товара на складе
                $update_stock_stmt = $pdo->prepare("
                    UPDATE goods
                    SET stock_quantity = stock_quantity - ?
                    WHERE id = ? AND stock_quantity >= ?
                ");
                
                $update_stock_stmt->execute([
                    $product['quantity_in_cart'],
                    $product['id'],
                    $product['quantity_in_cart']
                ]);
            }
            
            // Завершаем транзакцию
            $pdo->commit();
        
        // Отправляем email-уведомление о заказе
        $mail_sent = sendOrderConfirmationEmail(
            $email, 
            $name, 
                $order_id, 
                date('Y-m-d H:i:s'), 
                $cart_products, 
                $total_cart_value, 
            $shipping_cost, 
            $discount_amount, 
            $grand_total, 
            $address, 
            $phone, 
            $payment_method,
            $promo_code
        );
        
        if (!$mail_sent) {
            // Логируем ошибку, но позволяем процессу продолжиться
            error_log("Failed to send order confirmation email to: $email");
        }
        
        // Очищаем корзину
        $_SESSION['cart'] = [];
        unset($_SESSION['applied_promo_code']);
        unset($_SESSION['promo_discount']);
        
        // Сохраняем номер заказа для отображения на странице подтверждения
            $_SESSION['last_order_number'] = $order_id;
        
        // Перенаправляем на страницу успешного заказа
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Ваш заказ #' . $order_id . ' успешно оформлен! Благодарим за покупку.'];
        header('Location: order_success.php');
        exit;
        } catch (Exception $e) {
            // В случае ошибки отменяем транзакцию
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            error_log("Ошибка при оформлении заказа: " . $e->getMessage());
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Произошла ошибка при оформлении заказа. Пожалуйста, попробуйте позже.'];
            header('Location: cart.php');
            exit;
        }
    }
}

/**
 * Отправляет email-уведомление о подтверждении заказа
 */
function sendOrderConfirmationEmail($email, $name, $order_id, $order_date, $order_items, $order_total, $shipping, $discount, $grand_total, $address, $phone, $payment_method, $promo_code = null) {
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
        $mail->Subject = "Ваш заказ #{$order_id} подтвержден";
        
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
                    <h2>Заказ подтвержден</h2>
                    <p>Спасибо за ваш заказ!</p>
                </div>
                
                <div class='content'>
                    <div class='order-details'>
                        <h3>Информация о заказе:</h3>
                        <p><strong>Номер заказа:</strong> {$order_id}</p>
                        <p><strong>Дата заказа:</strong> " . date('d.m.Y H:i', strtotime($order_date)) . "</p>
                        <p><strong>Способ оплаты:</strong> " . ($payment_method == 'card' ? 'Банковская карта' : 'Наличными при получении') . "</p>
                        " . ($promo_code ? "<p><strong>Применен промокод:</strong> {$promo_code}</p>" : "") . "
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
                    
                    <div class='customer-info'>
                        <h3>Информация о получателе:</h3>
                        <p><strong>Имя:</strong> {$name}</p>
                        <p><strong>Email:</strong> {$email}</p>
                        <p><strong>Телефон:</strong> {$phone}</p>
                        <p><strong>Адрес доставки:</strong> {$address}</p>
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
        $mail->AltBody = "Заказ #{$order_id} подтвержден. Спасибо за покупку!";
        
        // Сохраняем содержимое письма в лог для отладки
        error_log("Email для отправки: " . $email);
        error_log("Тема письма: Ваш заказ #{$order_id} подтвержден");
        
        // Сохраняем копию HTML письма в файл для отладки
        $email_debug_dir = __DIR__ . '/../logs';
        if (!is_dir($email_debug_dir)) {
            mkdir($email_debug_dir, 0755, true);
        }
        file_put_contents($email_debug_dir . "/order_email_{$order_id}.html", $message);
        
        // Отправляем письмо
        $mail->send();
        error_log("Письмо успешно отправлено на адрес {$email}");
        return true;
    } catch (Exception $e) {
        error_log("Ошибка при отправке письма: " . $e->getMessage());
        if (isset($mail)) {
            error_log("Детали ошибки SMTP: " . $mail->ErrorInfo);
        }
        // Возвращаем true, чтобы не блокировать оформление заказа из-за проблем с отправкой почты
        return true;
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оформление заказа</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="../template/css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Подключаем DaData JS API -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="../vendor/dadata-js-master/src/index.js"></script>
</head>

<body>
    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="container mt-3">
        <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_message']['text']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php unset($_SESSION['flash_message']); endif; ?>

    <div class="container py-5">
        <h4 class="mb-4">Оформление заказа</h4>
        
        <div class="row">
            <!-- Форма оформления заказа -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="POST" action="checkout.php">
                            <!-- Контактная информация -->
                            <h5 class="mb-3">Контактная информация</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Имя *</label>
                                        <input type="text" class="form-control <?php if (isset($errors['name'])) echo 'is-invalid'; ?>" 
                                            id="name" name="name" value="<?php echo htmlspecialchars($name ?? ($user_data['name'] ?? '')); ?>" required>
                                        <?php if (isset($errors['name'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control <?php if (isset($errors['email'])) echo 'is-invalid'; ?>" 
                                            id="email" name="email" value="<?php echo htmlspecialchars($email ?? ($user_data['email'] ?? '')); ?>" required>
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Телефон *</label>
                                <input type="tel" class="form-control <?php if (isset($errors['phone'])) echo 'is-invalid'; ?>" 
                                    id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ($user_data['phone'] ?? '')); ?>" 
                                    required maxlength="12" pattern="^\+?[0-9]{10,11}$">
                                <small class="text-muted">Формат: +79996664212</small>
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Информация о доставке -->
                            <h5 class="mb-3 mt-4">Адрес доставки</h5>
                            <div class="mb-3">
                                <label for="address" class="form-label">Полный адрес *</label>
                                <input type="text" class="form-control <?php if (isset($errors['address'])) echo 'is-invalid'; ?>" 
                                    id="address" name="address" placeholder="Начните вводить адрес" 
                                    value="<?php echo htmlspecialchars($address ?? ($user_data['address'] ?? '')); ?>" required>
                                <?php if (isset($errors['address'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                                <?php endif; ?>
                                <small class="text-muted">Укажите полный адрес доставки, включая город, улицу, дом, квартиру и почтовый индекс</small>
                            </div>
                            
                            <!-- Способ оплаты -->
                            <h5 class="mb-3 mt-4">Способ оплаты</h5>
                            <div class="mb-4">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payment_card" value="card" 
                                        <?php if (isset($payment_method) && $payment_method === 'card') echo 'checked'; ?> required>
                                    <label class="form-check-label" for="payment_card">
                                        <i class="bi bi-credit-card me-2"></i>Банковская карта
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="payment_method" id="payment_cash" value="cash" 
                                        <?php if (isset($payment_method) && $payment_method === 'cash') echo 'checked'; ?>>
                                    <label class="form-check-label" for="payment_cash">
                                        <i class="bi bi-cash-coin me-2"></i>Наличными при получении
                                    </label>
                                </div>
                                <?php if (isset($errors['payment_method'])): ?>
                                    <div class="text-danger small"><?php echo $errors['payment_method']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="cart.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Вернуться в корзину
                                </a>
                                <button type="submit" name="submit_order" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Оформить заказ
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Сводка заказа -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Ваш заказ</h5>
                        
                        <div class="order-summary">
                            <?php foreach ($cart_products as $product): ?>
                            <div class="d-flex mb-2">
                                <div class="flex-shrink-0">
                                    <?php
                                    $image_display_path = '../template/assets/500x500.png'; // Плейсхолдер по умолчанию
                                    $image_alt_text = htmlspecialchars($product['title']);

                                    if (!empty($product['img'])) {
                                        $product_image_db_path = htmlspecialchars($product['img']); // e.g., uploads/product_images/image.jpg
                                        // __DIR__ is /main, $product_image_db_path is root-relative (uploads/...)
                                        $image_filesystem_path = __DIR__ . '/../' . $product_image_db_path;
                                        
                                        if (file_exists($image_filesystem_path)) {
                                            // src path is also relative from /main
                                            $image_display_path = '../' . $product_image_db_path;
                                        } else {
                                            // error_log("Checkout: Image not found for product ID {$product['id']}: {$image_filesystem_path}");
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo $image_display_path; ?>" alt="<?php echo $image_alt_text; ?>" width="50" class="rounded">
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="my-0"><?php echo htmlspecialchars($product['title']); ?></h6>
                                            <small class="text-muted"><?php echo $product['quantity_in_cart']; ?> шт × <?php echo number_format($product['price'], 0, '.', ' '); ?>₽</small>
                                        </div>
                                        <span class="text-muted"><?php echo number_format($product['item_total_price'], 0, '.', ' '); ?>₽</span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <hr class="my-3">
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Стоимость товаров</span>
                                <span><?php echo number_format($total_cart_value, 0, '.', ' '); ?>₽</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Доставка</span>
                                <span><?php echo number_format($shipping_cost, 0, '.', ' '); ?>₽</span>
                            </div>
                            <?php if ($discount_amount > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Скидка <?php if (isset($_SESSION['applied_promo_code'])) echo '(промокод ' . htmlspecialchars($_SESSION['applied_promo_code']) . ')'; ?></span>
                                <span class="text-success">-<?php echo number_format($discount_amount, 0, '.', ' '); ?>₽</span>
                            </div>
                            <?php endif; ?>
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Итого к оплате</strong>
                                <strong><?php echo number_format($grand_total, 0, '.', ' '); ?>₽</strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Информация о доставке</h5>
                        <p class="mb-1"><i class="bi bi-truck me-2"></i> Стандартная доставка: 1-3 рабочих дня</p>
                        <p class="mb-1"><i class="bi bi-shield-check me-2"></i> Гарантия качества на все товары</p>
                        <p class="mb-0"><i class="bi bi-arrow-counterclockwise me-2"></i> Возврат в течение 14 дней</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once "../template/footer.php" ?>
    
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js"
        integrity="sha384-VQqxDN0EQCkWoxt/0vsQvZswzTHUVOImccYmSyhJTp7kGtPed0Qcx8rK9h9YEgx+"
        crossorigin="anonymous"></script>
        
    <!-- Скрипт для интеграции с DaData -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Используем API DaData для подсказок по адресу
            const addressInput = document.getElementById('address');
            if (addressInput) {
                // Токен для DaData API
                const token = "14a0ebf128ce762f97197cbb7851f12bbac6d39e";
                
                // Инициализируем подсказки по адресу
                // Вместо использования библиотеки делаем прямой запрос к API
                // Это более универсальный подход, который будет работать без необходимости устанавливать библиотеку
                
                addressInput.addEventListener('input', function() {
                    const query = this.value;
                    
                    // Если введено менее 3 символов, не делаем запрос
                    if (query.length < 3) return;
                    
                    // Делаем запрос к API DaData
                    fetch("https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address", {
                        method: "POST",
                        mode: "cors",
                        headers: {
                            "Content-Type": "application/json",
                            "Accept": "application/json",
                            "Authorization": "Token " + token
                        },
                        body: JSON.stringify({ query: query })
                    })
                    .then(response => response.json())
                    .then(result => {
                        // Создаем выпадающий список с подсказками
                        const suggestionsContainer = document.getElementById('address-suggestions');
                        if (!suggestionsContainer) {
                            const container = document.createElement('div');
                            container.id = 'address-suggestions';
                            container.className = 'list-group position-absolute w-100';
                            container.style.zIndex = '1000';
                            addressInput.parentNode.appendChild(container);
                        }
                        
                        const container = document.getElementById('address-suggestions');
                        container.innerHTML = '';
                        
                        // Добавляем подсказки
                        result.suggestions.forEach(suggestion => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'list-group-item list-group-item-action';
                            item.textContent = suggestion.value;
                            item.addEventListener('click', function() {
                                addressInput.value = suggestion.value;
                                container.innerHTML = '';
                            });
                            container.appendChild(item);
                        });
                    })
                    .catch(error => console.error("Ошибка запроса к DaData:", error));
                });
                
                // Скрываем подсказки при клике вне поля ввода
                document.addEventListener('click', function(e) {
                    if (e.target !== addressInput) {
                        const container = document.getElementById('address-suggestions');
                        if (container) {
                            container.innerHTML = '';
                        }
                    }
                });
            }
        });
    </script>
</body>

</html> 