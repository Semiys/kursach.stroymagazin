<?php
// Запускаем сессию в самом начале, чтобы она была доступна везде
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Подключаем конфигурацию для доступа к $pdo и другим настройкам
// Это должно быть сделано до включения header.php, чтобы $pdo был доступен в нем
require_once '../config.php';

// НЕ включаем header.php сразу для AJAX-запросов
// Сначала проверяем, это AJAX-запрос или нет
$is_ajax_request = isset($_GET['ajax']) && $_GET['ajax'] === '1';

// Если это не AJAX-запрос, включаем header.php
if (!$is_ajax_request) {
    // Теперь $pdo должен быть доступен внутри header.php для обновления роли
    include '../template/header.php'; 
} 
// Для AJAX-запросов сессия уже запущена, и $pdo доступен, header не нужен.

// Теперь у нас есть доступ к переменной $pdo для работы с базой данных

// Обработка действия очистки корзины
if (isset($_GET['action']) && $_GET['action'] === 'clear_cart') {
    $_SESSION['cart'] = []; // Очищаем корзину
    
    if ($is_ajax_request) {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo json_encode([
            'success' => true,
            'message' => 'Корзина очищена',
            'total_cart_items' => 0,
            'total_cart_quantity' => 0
        ]);
        exit;
    } else {
        // Для не-AJAX запросов
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Корзина успешно очищена'];
        header("Location: cart.php");
        exit;
    }
}

// Обрабатываем действие удаления отдельного товара
if (isset($_GET['action']) && $_GET['action'] === 'remove_from_cart' && isset($_GET['id_to_cart'])) {
    $product_id = (int)$_GET['id_to_cart'];
    
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        
        if ($is_ajax_request) {
            header('Content-Type: application/json');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            
            // Считаем общее количество товаров в корзине
            $total_cart_items = count($_SESSION['cart']);
            $total_cart_quantity = array_sum($_SESSION['cart']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Товар удален из корзины',
                'product_id' => $product_id,
                'new_quantity' => 0,
                'total_cart_items' => $total_cart_items,
                'total_cart_quantity' => $total_cart_quantity
            ]);
            exit;
        } else {
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Товар удален из корзины'];
            header("Location: cart.php");
            exit;
        }
    }
}

// Инициализация переменных для работы с корзиной
$cart_products = [];
$total_cart_value = 0;
$cart_is_empty = true;
$flash_message_html = ''; // Для flash сообщений (если они будут на этой странице)

// Отображение flash-сообщений (если есть)
if (isset($_SESSION['flash_message'])) {
    $flash_type = $_SESSION['flash_message']['type'] ?? 'info';
    $flash_text = $_SESSION['flash_message']['text'] ?? '';
    $flash_message_html = "<div class='container mt-3'><div class='alert alert-{$flash_type} alert-dismissible fade show' role='alert'>
                            {$flash_text}
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                          </div></div>";
    unset($_SESSION['flash_message']);
}

// Загрузка данных корзины для отображения
if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $product_ids_sanitized = array_map('intval', $product_ids); // Sanitize IDs

    if (!empty($product_ids_sanitized)) {
        // Создаем плейсхолдеры для подготовленного запроса
        $placeholders = implode(',', array_fill(0, count($product_ids_sanitized), '?'));
        $sql = "SELECT id, title, price, img, category, stock_quantity, discount FROM goods WHERE id IN ($placeholders)";
        
        try {
            // Используем $pdo вместо $conn
            $stmt = $pdo->prepare($sql);
            
            // Выполняем запрос
            $stmt->execute($product_ids_sanitized);
            
            // Получаем данные
            $products_from_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Обрабатываем полученные товары
            foreach ($products_from_db as $product) {
                $quantity_in_cart = isset($_SESSION['cart'][$product['id']]) ? (int)$_SESSION['cart'][$product['id']] : 0;
                if ($quantity_in_cart > 0) { // Добавляем только если количество > 0
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
            
            // Если после фильтрации по количеству массив $cart_products остался пустым
            if (empty($cart_products)) {
                $cart_is_empty = true; 
            } else {
                $cart_is_empty = false;
            }
        } catch (PDOException $e) {
            error_log("cart.php: Database error - " . $e->getMessage());
            // echo "<div class='alert alert-danger'>Ошибка при загрузке данных корзины: " . htmlspecialchars($e->getMessage()) . "</div>";
            $cart_is_empty = true;
        }
    } else {
        $cart_is_empty = true; // Если после фильтрации ID не осталось
    }
} else {
    $cart_is_empty = true; // Если сессия 'cart' не установлена, не массив или пуста
}

// Расчет стоимости доставки и итоговой суммы
$shipping_cost = $cart_is_empty ? 0 : 10.00; // Пример: доставка 10, если корзина не пуста
$discount_amount = isset($_SESSION['promo_discount']) ? (float)$_SESSION['promo_discount'] : 0; // Скидка по промокоду
$grand_total = $total_cart_value + $shipping_cost - $discount_amount;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Корзина - СтройМаркет</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="../template/css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <?php echo $flash_message_html; // Выводим flash сообщение здесь ?>
    <div class="container py-5">
        <h4 class="mb-4">Корзина</h4>
        <div class="row">
            <div class="col-lg-8">
                <?php if ($cart_is_empty): ?>
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <h5 class="card-title">Ваша корзина пуста</h5>
                            <p class="card-text">Самое время добавить что-нибудь интересное!</p>
                            <a href="/main/catalogue.php" class="btn btn-primary">Перейти в каталог</a>
                        </div>
                    </div>
                <?php else: ?>
                <!-- Cart Items -->
                    <div class="card mb-4" id="cart-items-container">
                    <div class="card-body">
                            <?php foreach ($cart_products as $item_key => $item): ?>
                                <div class="row cart-item mb-3 align-items-center" id="cart-item-row-<?php echo $item['id']; ?>">
                                    <div class="col-md-2 col-sm-3">
                                        <?php
                                        $image_display_path = '../template/assets/500x500.png'; // Плейсхолдер по умолчанию
                                        $image_alt_text = htmlspecialchars($item['title']);

                                        if (!empty($item['img'])) {
                                            $item_image_db_path = htmlspecialchars($item['img']); // e.g., uploads/product_images/image.jpg
                                            // __DIR__ is /main, $item_image_db_path is root-relative (uploads/...)
                                            $image_filesystem_path = __DIR__ . '/../' . $item_image_db_path;
                                            
                                            if (file_exists($image_filesystem_path)) {
                                                // src path is also relative from /main
                                                $image_display_path = '../' . $item_image_db_path;
                                            } else {
                                                // error_log("Cart: Image not found for product ID {$item['id']}: {$image_filesystem_path}");
                                            }
                                        }
                                        ?>
                                        <img src="<?php echo $image_display_path; ?>" alt="<?php echo $image_alt_text; ?>" class="img-fluid rounded">
                                    </div>
                                    <div class="col-md-4 col-sm-9">
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($item['title']); ?></h6>
                                        <p class="text-muted small mb-1">Категория: <?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></p>
                                        <p class="text-muted small">Наличие: 
                                            <?php if ($item['stock_quantity'] > 0): ?>
                                                <span class="text-success"><?php echo $item['stock_quantity']; ?> шт.</span>
                                            <?php else: ?>
                                                <span class="text-danger">Нет на складе</span>
                                            <?php endif; ?>
                                        </p>
                            </div>
                                    <div class="col-md-3 col-sm-6 mt-2 mt-md-0">
                                        <div class="cart-controls" data-product-id="<?php echo $item['id']; ?>" data-stock="<?php echo $item['stock_quantity']; ?>">
                                            <div class="input-group quantity-control-group ajax-quantity-control" style="max-width: 140px;">
                                                <button type="button" class="btn btn-outline-warning btn-sm cart-action-btn" data-action="decrease_quantity" data-product-id="<?php echo $item['id']; ?>">-</button>
                                                <input type="number" class="form-control form-control-sm text-center product-quantity-input" 
                                                       value="<?php echo $item['quantity_in_cart']; ?>" 
                                                       min="0" max="<?php echo $item['stock_quantity']; ?>" 
                                                       data-action="update_quantity" 
                                                       name="quantity-<?php echo $item['id']; ?>"
                                                       id="quantity-<?php echo $item['id']; ?>"
                                                       autocomplete="off"
                                                       aria-label="Количество">
                                                <button type="button" class="btn btn-outline-warning btn-sm cart-action-btn <?php if ($item['quantity_in_cart'] >= $item['stock_quantity']) echo 'disabled'; ?>" 
                                                   data-action="add_to_cart"
                                                   data-product-id="<?php echo $item['id']; ?>"
                                                   <?php if ($item['quantity_in_cart'] >= $item['stock_quantity']) echo 'aria-disabled="true"'; ?>>+</button>
                            </div>
                                </div>
                            </div>
                                    <div class="col-md-2 col-sm-4 mt-2 mt-md-0 text-end">
                                        <div class="price-block">
                                            <?php if ($item['discount'] > 0): ?>
                                            <del class="text-muted" style="font-size: 0.9rem;"><?php echo number_format($item['price'], 2); ?>₽</del>
                                            <br>
                                            <strong><?php echo number_format($item['discounted_price'], 2); ?>₽</strong> <span class="badge text-bg-danger">-<?php echo $item['discount']; ?>%</span>
                                            <?php else: ?>
                                            <strong><?php echo number_format($item['price'], 2); ?>₽</strong>
                                            <?php endif; ?>
                                        </div>
                                        <p class="fw-bold item-total-price-<?php echo $item['id']; ?>"><?php echo number_format($item['item_total_price'], 0, '.', ' '); ?>₽</p>
                                    </div>
                                    <div class="col-md-1 col-sm-2 mt-2 mt-md-0 text-end">
                                        <button class="btn btn-sm btn-outline-danger cart-action-btn" data-action="remove_from_cart" data-product-id="<?php echo $item['id']; ?>" title="Удалить товар">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                                </div>
                                <?php if ($item_key < count($cart_products) - 1): // Добавляем <hr> если это не последний элемент ?>
                                    <hr class="my-3">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Continue Shopping Button -->
                <div class="text-start mb-4">
                    <a href="/main/catalogue.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>Продолжить покупки
                    </a>
                    <?php if (!$cart_is_empty): ?>
                    <button id="clear-cart-btn" class="btn btn-outline-danger ms-2">
                        <i class="bi bi-trash me-2"></i>Очистить корзину
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <!-- Cart Summary -->
                <div class="card cart-summary">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Сумма заказа</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Стоимость товаров</span>
                            <span id="cart-subtotal"><?php echo number_format($total_cart_value, 0, '.', ' '); ?>₽</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Доставка</span>
                            <span id="cart-shipping"><?php echo number_format($shipping_cost, 0, '.', ' '); ?>₽</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Скидка</span>
                            <span id="cart-discount" class="text-success">-<?php echo number_format($discount_amount, 0, '.', ' '); ?>₽</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <strong>Итого</strong>
                            <strong id="cart-grand-total"><?php echo number_format($grand_total, 0, '.', ' '); ?>₽</strong>
                        </div>
                        <form action="checkout.php" method="POST">
                            <input type="hidden" name="total_amount" value="<?php echo $grand_total; ?>">
                            <?php if (isset($_SESSION['applied_promo_code'])): ?>
                                <input type="hidden" name="promo_code" value="<?php echo htmlspecialchars($_SESSION['applied_promo_code']); ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary w-100 <?php if ($cart_is_empty) echo 'disabled'; ?>" <?php if ($cart_is_empty) echo 'disabled'; ?>>Перейти к оплате</button>
                        </form>
                    </div>
                </div>
                <!-- Promo Code -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Активировать промокод</h5>
                        <form id="promo-form" action="apply_promo.php" method="POST" class="mb-3">
                            <div class="input-group">
                                <input type="text" name="promo_code" id="promo_code" class="form-control" 
                                       placeholder="Введите сюда промо-слово" 
                                       autocomplete="off"
                                       <?php if ($cart_is_empty) echo 'disabled'; ?>>
                                <button class="btn btn-outline-warning" type="submit" <?php if ($cart_is_empty) echo 'disabled'; ?>>Применить</button>
                            </div>
                        </form>
                        <div id="promo-message">
                            <?php if (isset($_SESSION['promo_message'])): ?>
                                <div class="alert alert-<?php echo $_SESSION['promo_message']['type']; ?> small">
                                    <?php echo $_SESSION['promo_message']['text']; ?>
                                    <?php if (isset($_SESSION['applied_promo_code'])): ?>
                                        <a href="remove_promo.php" class="float-end text-decoration-none">Удалить</a>
                                    <?php endif; ?>
                                </div>
                                <?php unset($_SESSION['promo_message']); ?>
                            <?php endif; ?>
                        </div>
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
    
    <!-- Подключаем скрипт для работы с корзиной -->
    <script src="/template/js/cart_ajax.js"></script>
</body>

</html>