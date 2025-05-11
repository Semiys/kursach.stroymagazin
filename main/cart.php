<?php
include '../template/header.php'; // This should start the session
require_once '../config.php'; // Подключаем конфигурацию для доступа к $pdo
// Теперь у нас есть доступ к переменной $pdo для работы с базой данных

// Инициализация переменных для работы с корзиной
$cart_products = [];
$total_cart_value = 0;
$cart_is_empty = true;
$flash_message_html = ''; // Для flash сообщений (если они будут на этой странице)

// Загрузка данных корзины для отображения
if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $product_ids_sanitized = array_map('intval', $product_ids); // Sanitize IDs

    if (!empty($product_ids_sanitized)) {
        // Создаем плейсхолдеры для подготовленного запроса
        $placeholders = implode(',', array_fill(0, count($product_ids_sanitized), '?'));
        $sql = "SELECT id, title, price, img, category, stock_quantity FROM goods WHERE id IN ($placeholders)";
        
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
                    $item_total_price = $product['price'] * $quantity_in_cart;
                    $total_cart_value += $item_total_price;
                    
                    $cart_products[] = [
                        'id' => $product['id'],
                        'title' => $product['title'],
                        'price' => $product['price'],
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
$discount_amount = 0; // Пока скидок нет
$grand_total = $total_cart_value + $shipping_cost - $discount_amount;
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Корзина</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="../template/css/main.css">
</head>

<body>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
                                        // Если $item['img'] пусто или файл не существует
                                        // показываем заглушку.
                                        $defaultImagePath = '../template/assets/500x500.png';
                                        $imagePath = $defaultImagePath; // По умолчанию ставим заглушку

                                        if (!empty($item['img'])) {
                                            $potentialImagePath = '../template/assets/' . htmlspecialchars($item['img']);
                                            $absolutePotentialImagePath = __DIR__ . '/../template/assets/' . basename(htmlspecialchars($item['img'])); 
                                            
                                            if (file_exists($absolutePotentialImagePath)) {
                                                $imagePath = $potentialImagePath; 
                                            }
                                        }
                                        ?>
                                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="img-fluid rounded">
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
                                            <?php // Контролы количества будут добавлены сюда позже, когда будем подключать JS для них
                                                  // Пока что просто выводим текущее количество
                                            ?>
                                            <div class="input-group quantity-control-group ajax-quantity-control" style="max-width: 140px;">
                                                <a href="#" class="btn btn-outline-secondary btn-sm cart-action-btn" data-action="decrease_quantity">-</a>
                                                <input type="number" class="form-control form-control-sm text-center product-quantity-input" 
                                                       value="<?php echo $item['quantity_in_cart']; ?>" 
                                                       min="0" <?php // min="0" чтобы можно было удалить через ввод 0, но JS должен это обработать для remove_from_cart ?>
                                                       max="<?php echo $item['stock_quantity']; ?>" 
                                                       data-action="update_quantity" 
                                                       aria-label="Количество">
                                                <a href="#" class="btn btn-outline-secondary btn-sm cart-action-btn <?php if ($item['quantity_in_cart'] >= $item['stock_quantity']) echo 'disabled'; ?>" 
                                                   data-action="add_to_cart"
                                                   <?php if ($item['quantity_in_cart'] >= $item['stock_quantity']) echo 'aria-disabled="true"'; ?>>+</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 col-sm-4 mt-2 mt-md-0 text-end">
                                        <p class="fw-bold mb-1 price-per-item-<?php echo $item['id']; ?>"><?php echo number_format($item['price'], 2, '.', ' '); ?>₽/шт</p>
                                        <p class="fw-bold item-total-price-<?php echo $item['id']; ?>"><?php echo number_format($item['item_total_price'], 2, '.', ' '); ?>₽</p>
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
                </div>
            </div>
            <div class="col-lg-4">
                <!-- Cart Summary -->
                <div class="card cart-summary">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Сумма заказа</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Стоимость товаров</span>
                            <span id="cart-subtotal"><?php echo number_format($total_cart_value, 2, '.', ' '); ?>₽</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Доставка</span>
                            <span id="cart-shipping"><?php echo number_format($shipping_cost, 2, '.', ' '); ?>₽</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Скидка</span>
                            <span id="cart-discount" class="text-success">-<?php echo number_format($discount_amount, 2, '.', ' '); ?>₽</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <strong>Итого</strong>
                            <strong id="cart-grand-total"><?php echo number_format($grand_total, 2, '.', ' '); ?>₽</strong>
                        </div>
                        <button class="btn btn-primary w-100 <?php if ($cart_is_empty) echo 'disabled'; ?>" <?php if ($cart_is_empty) echo 'disabled'; ?>>Перейти к оплате</button>
                    </div>
                </div>
                <!-- Promo Code -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Активировать промокод</h5>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" placeholder="Введите сюда промо-слово" <?php if ($cart_is_empty) echo 'disabled'; ?>>
                            <button class="btn btn-outline-secondary" type="button" <?php if ($cart_is_empty) echo 'disabled'; ?>>Применить</button>
                        </div>
                        <!-- Здесь можно выводить сообщения о промокоде -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include_once "../template/footer.php" ?>
    <!-- Скрипты Bootstrap и Popper.js уже были в вашем исходном файле, оставляем их -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js"
        integrity="sha384-VQqxDN0EQCkWoxt/0vsQvZswzTHUVOImccYmSyhJTp7kGtPed0Qcx8rK9h9YEgx+"
        crossorigin="anonymous"></script>
    <!-- cart_ajax.js уже подключается в footer.php, так что здесь он не нужен повторно, если footer.php его содержит -->
    <?php // Если cart_ajax.js не подключен в footer.php, его нужно будет подключить здесь или убедиться, что он есть в footer ?>
</body>

</html>