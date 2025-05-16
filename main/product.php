<?php
// Удаляем session_start() так как он вызывается в header.php
// session_start();
// Сначала подключаем config.php для доступа к $pdo
require_once '../config.php';
// НЕ включаем header.php здесь! Переносим его вниз

// --- НАЧАЛО БЛОКА ОБРАБОТКИ КОРЗИНЫ ---
// Определяем, это AJAX запрос или обычный, проверяя GET-параметр ajax=1
$is_ajax_request = isset($_GET['ajax']) && $_GET['ajax'] === '1';

if (isset($_GET['action']) && isset($_GET['id_to_cart'])) {
    // Если это AJAX-запрос, НЕ включаем header.php и footer.php
    if ($is_ajax_request) {
        // Нам нужны сессии, но не нужен header.php
        session_start();
    }

    // Логируем все входящие данные для отладки
    error_log("AJAX request to product.php: " . print_r($_GET, true));
    
    $product_id_action = filter_var($_GET['id_to_cart'], FILTER_VALIDATE_INT);
    $action = $_GET['action'];
    
    $response_data = [
        'success' => false,
        'message' => '',
        'new_quantity' => 0,
        'product_id' => $product_id_action,
        'total_cart_items' => 0,
        'total_cart_quantity' => 0,
        'stock_quantity' => 0
    ];

    if ($product_id_action) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // На странице товара $product_id (ID текущего товара) уже должен быть определен ниже,
        // если товар существует. Мы можем это использовать для проверки.
        // Для AJAX важно убедиться, что товар, с которым работаем, существует.
        $stmt_check_product_page = $pdo->prepare("SELECT id, stock_quantity FROM goods WHERE id = ?");
        $stmt_check_product_page->execute([$product_id_action]);
        $product_data_from_db_page = $stmt_check_product_page->fetch();

        $response_data['stock_quantity'] = $product_data_from_db_page ? (int)$product_data_from_db_page['stock_quantity'] : 0;

        if (!$product_data_from_db_page && ($action == 'add_to_cart' || $action == 'update_quantity' || $action == 'decrease_quantity')) {
            $response_data['message'] = 'Ошибка: Товар не найден в базе данных.';
        } else {
            $current_stock_page = $product_data_from_db_page ? (int)$product_data_from_db_page['stock_quantity'] : 0;
            $response_data['success'] = true;

            if ($action == 'add_to_cart') {
                $current_in_cart_page = isset($_SESSION['cart'][$product_id_action]) ? $_SESSION['cart'][$product_id_action] : 0;
                if ($current_in_cart_page + 1 <= $current_stock_page) {
                    $_SESSION['cart'][$product_id_action] = $current_in_cart_page + 1;
                    $response_data['message'] = 'Товар добавлен в корзину!';
                } else {
                    $response_data['message'] = 'Недостаточно товара на складе. Доступно: ' . $current_stock_page . ', в корзине уже: ' . $current_in_cart_page;
                    $response_data['success'] = false;
                }
            } elseif ($action == 'decrease_quantity') {
                if (isset($_SESSION['cart'][$product_id_action])) {
                    $_SESSION['cart'][$product_id_action]--;
                    if ($_SESSION['cart'][$product_action] <= 0) {
                        unset($_SESSION['cart'][$product_id_action]);
                        $response_data['message'] = 'Товар удален из корзины.';
                    } else {
                        $response_data['message'] = 'Количество товара уменьшено.';
                    }
                } else {
                    $response_data['message'] = 'Товара не было в корзине.';
                    $response_data['success'] = false;
                }
            } elseif ($action == 'remove_from_cart') {
                if (isset($_SESSION['cart'][$product_id_action])) {
                    unset($_SESSION['cart'][$product_id_action]);
                    $response_data['message'] = 'Товар полностью удален из корзины.';
                } else {
                    $response_data['message'] = 'Товара не было в корзине для удаления.';
                    $response_data['success'] = false;
                }
            } elseif ($action == 'update_quantity') {
                $new_qty_page = isset($_GET['qty']) ? filter_var($_GET['qty'], FILTER_VALIDATE_INT) : false;
                if ($new_qty_page !== false && $new_qty_page > 0) {
                    if ($new_qty_page <= $current_stock_page) {
                        $_SESSION['cart'][$product_id_action] = $new_qty_page;
                        $response_data['message'] = 'Количество товара обновлено.';
                    } else {
                        $response_data['message'] = 'Невозможно установить количество: ' . $new_qty_page . '. На складе: ' . $current_stock_page . '.';
                        $response_data['success'] = false;
                    }
                } elseif ($new_qty_page !== false && $new_qty_page <= 0) {
                    unset($_SESSION['cart'][$product_id_action]);
                    $response_data['message'] = 'Товар удален (количество 0 или меньше).';
                } else {
                    $response_data['message'] = 'Неверное количество для обновления.';
                    $response_data['success'] = false;
                }
            }
            $response_data['new_quantity'] = isset($_SESSION['cart'][$product_id_action]) ? $_SESSION['cart'][$product_id_action] : 0;
        }
    } else {
        $response_data['message'] = 'Ошибка: Неверный ID товара для действия с корзиной.';
    }

    if (isset($_SESSION['cart'])) {
        $response_data['total_cart_items'] = count($_SESSION['cart']);
        $response_data['total_cart_quantity'] = array_sum($_SESSION['cart']);
    }

    // Логируем данные ответа для отладки
    error_log("AJAX response from product.php: " . print_r($response_data, true));

    if ($is_ajax_request) {
        // Добавим правильные HTTP-заголовки
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        // Вывод JSON с корректной кодировкой
        echo json_encode($response_data, JSON_UNESCAPED_UNICODE);
        exit();
    } else {
        if (!empty($response_data['message'])) {
            $_SESSION['flash_message'] = ['type' => $response_data['success'] ? 'success' : 'danger', 'text' => $response_data['message']];
        }
        // Для product.php $product_id должен быть определен ниже, если мы на странице конкретного товара
        // Этот $product_id используется для формирования return_url по умолчанию, если он не передан
        // Важно: $product_id здесь - это ID товара, отображаемого на странице, а не $product_id_action
        global $product_id; // Делаем $product_id (который определяется ниже) доступным
        $return_url = isset($_GET['return_url']) ? urldecode($_GET['return_url']) : (isset($product_id) ? 'product.php?id='.$product_id : 'catalogue.php');
        header("Location: " . $return_url);
        exit();
    }
}

// Если это не AJAX-запрос или ни один из условий выше не вызвал exit(), 
// тогда включаем header.php для обычного отображения страницы
include_once "../template/header.php";

// Отображение flash-сообщений (если есть)
$flash_message_html = '';
if (isset($_SESSION['flash_message'])) {
    $flash_type = $_SESSION['flash_message']['type'] ?? 'info';
    $flash_text = $_SESSION['flash_message']['text'] ?? '';
    $flash_message_html = "<div class='container mt-3'><div class='alert alert-{$flash_type} alert-dismissible fade show' role='alert'>
                            {$flash_text}
                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                          </div></div>";
    unset($_SESSION['flash_message']);
}
// --- КОНЕЦ БЛОКА ОБРАБОТКИ КОРЗИНЫ ---

$product_id = null;
$product = null;
$error_message = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];

    try {
        // Включаем is_hidden в запрос
        $stmt = $pdo->prepare("SELECT id, title, price, img, category, discr, rating, article, short_description, rating_count, gallery_images, stock_quantity, discount, is_hidden FROM goods WHERE id = ?");
        $stmt->execute([$product_id]);
        $product_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product_data) {
            $error_message = 'Товар не найден.';
            $product = null; 
            // header("HTTP/1.0 404 Not Found"); 
        } elseif ($product_data['is_hidden'] == 1) {
            $error_message = 'Товар временно недоступен.'; // Или 'Товар не найден.'
            $product = null; 
            // header("HTTP/1.0 404 Not Found");
        } else {
            $product = $product_data; // Товар найден и не скрыт
        }
    } catch (PDOException $e) {
        error_log("Ошибка загрузки товара: " . $e->getMessage());
        $error_message = 'Произошла ошибка при загрузке товара.';
    }
} else {
    $error_message = 'ID товара не указан или некорректен.';
}

// Готовим массив путей к изображениям галереи
$gallery_image_paths = [];
if ($product && !empty($product['gallery_images'])) {
    $gallery_image_names = explode(',', $product['gallery_images']);
    foreach ($gallery_image_names as $img_name) {
        $trimmed_name = trim($img_name);
        if (!empty($trimmed_name)) { // Убедимся, что имя файла не пустое
            // Путь из БД уже относительный от корня (например, uploads/gallery/image.jpg)
            $image_db_path = htmlspecialchars($trimmed_name);
            $absolute_filesystem_path = __DIR__ . '/../' . $image_db_path;
            if (file_exists($absolute_filesystem_path)) {
                $gallery_image_paths[] = '../' . $image_db_path; // Путь для src атрибута
            }
        }
    }
}

// Добавляем основное изображение в начало галереи, если оно есть и еще не там
if ($product && !empty($product['img'])) {
    $main_image_db_path = htmlspecialchars($product['img']);
    $main_image_src_path = '../' . $main_image_db_path;
    $absolute_main_image_filesystem_path = __DIR__ . '/../' . $main_image_db_path;

    if (file_exists($absolute_main_image_filesystem_path)) {
        // Проверяем, нет ли уже этого изображения в галерее (с учетом '../' префикса)
        $already_in_gallery = false;
        foreach ($gallery_image_paths as $gallery_path) {
            if ($gallery_path === $main_image_src_path) {
                $already_in_gallery = true;
                break;
            }
        }
        if (!$already_in_gallery) {
            array_unshift($gallery_image_paths, $main_image_src_path);
        }
    } elseif (empty($gallery_image_paths) && file_exists($absolute_main_image_filesystem_path)) {
         // Эта ветка кажется дублирующей логику выше, но оставим на случай если первое изображение должно быть добавлено даже если галерея пуста
         // и оно не прошло первую проверку file_exists (что маловероятно, но для безопасности)
        $gallery_image_paths[] = $main_image_src_path;
    }
}

// Узнаем, оценил ли текущий пользователь этот товар
$user_rating_for_this_product = 0;
$user_has_rated = false;
if (isset($_SESSION['user_id']) && $product) {
    try {
        $stmt_user_rating = $pdo->prepare("SELECT rating_value FROM product_ratings WHERE user_id = ? AND product_id = ?");
        $stmt_user_rating->execute([$_SESSION['user_id'], $product['id']]);
        $rating_row = $stmt_user_rating->fetch(PDO::FETCH_ASSOC);
        if ($rating_row) {
            $user_rating_for_this_product = (int)$rating_row['rating_value'];
            $user_has_rated = true;
        }
    } catch (PDOException $e) {
        error_log("Ошибка получения оценки пользователя: " . $e->getMessage());
        // Не прерываем выполнение, просто пользователь не увидит свою предыдущую оценку сразу
    }
}

// Логика для характеристик из поля discr
// $characteristics = [];
// if ($product && !empty($product['discr'])) {
//    $lines = explode("\n", $product['discr']); // Предполагаем, что каждая характеристика на новой строке
//    foreach ($lines as $line) {
//        if (strpos($line, ':') !== false) {
//            list($key, $value) = explode(':', $line, 2);
//            $characteristics[trim($key)] = trim($value);
//        }
//    }
// }

// Рассчитываем скидку
$discount = isset($product['discount']) ? intval($product['discount']) : 0;
$original_price = floatval($product['price']);
$discounted_price = $original_price;

if ($discount > 0) {
    $discounted_price = $original_price * (1 - $discount / 100);
}

// Определяем количество товара в корзине для отображения на кнопке
$quantity_in_cart = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id] : 0;
$stock_quantity = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : 0;

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($product['title'] ?? 'Страница товара'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="../template/css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php echo $flash_message_html; // Выводим flash сообщение здесь ?>

<?php if (!empty($error_message)): ?>
    <div class="container mt-5">
        <div class="alert alert-danger text-center" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <div class="text-center">
            <a href="catalogue.php" class="btn btn-primary">Вернуться в каталог</a>
        </div>
    </div>
    <?php 
    include_once "../template/footer.php"; 
    exit; // Прерываем выполнение скрипта, чтобы не выводить остальную часть страницы
    ?>
<?php endif; ?>

<?php // Если ошибок нет и товар найден ($product не null), продолжаем выводить страницу товара ?>

    <div class="container mt-5">
        <div class="row">
            <!-- Product Images -->
            <div class="col-md-6 mb-4">
                <?php
                $main_image_display_path = '../template/assets/500x500.png'; // Заглушка по умолчанию
                // Используем htmlspecialchars для title один раз, если он есть, или общее сообщение
                $main_image_alt_text = htmlspecialchars($product['title'] ?? 'Изображение товара');

                if ($product && !empty($product['img'])) {
                    $current_product_image_db_path = htmlspecialchars($product['img']);
                    // Путь из БД (например, uploads/product_images/image.jpg) уже относительный от корня.
                    // Для file_exists нужен путь от текущего файла (__DIR__) до корня сайта ('/../') и затем путь из БД.
                    $current_product_image_filesystem_path = __DIR__ . '/../' . $current_product_image_db_path;
                    // Для src атрибута нужен путь от текущей директории (main/) до корня сайта ('../') и затем путь из БД.
                    $current_product_image_src_path = '../' . $current_product_image_db_path;

                    if (file_exists($current_product_image_filesystem_path)) {
                        $main_image_display_path = $current_product_image_src_path;
                        // $main_image_alt_text уже установлен с названием товара, если оно есть
                    }
                }
                ?>
                <img src="<?php echo $main_image_display_path; ?>" alt="<?php echo $main_image_alt_text; ?>" class="img-fluid rounded mb-3" id="mainImage">
                <?php if (!empty($gallery_image_paths) && count($gallery_image_paths) > 1): ?>
                <div class="row gx-2 gy-2 mt-2 thumbnail-gallery">
                    <?php foreach ($gallery_image_paths as $index => $thumb_path): ?>
                    <div class="col-3">
                        <img src="<?php echo $thumb_path; ?>"
                             alt="Thumbnail <?php echo $index + 1; ?>"
                             class="img-fluid rounded thumbnail <?php if ($thumb_path === $main_image_display_path) echo 'active'; ?>"
                             onclick="changeImage(event, this.src)"
                             style="cursor: pointer; border: 2px solid transparent;">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Product Details -->
            <div class="col-md-6">
                <h2 class="mb-3"><?php echo htmlspecialchars($product['title'] ?? 'Название товара'); ?></h2>
                <p class="text-muted mb-4">Артикул: <?php echo htmlspecialchars($product['article'] ?? 'N/A'); ?></p>
                <div class="mb-3">
                    <?php if ($discount > 0): ?>
                    <span class="badge text-bg-danger mb-2">СКИДКА <?php echo $discount; ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <?php if ($discount > 0): ?>
                    <del class="text-muted me-2 fs-5"><?php echo number_format($original_price, 2); ?>₽</del>
                    <span class="price fs-3 fw-bold"><?php echo number_format($discounted_price, 2); ?>₽</span>
                    <?php else: ?>
                    <span class="price fs-3 fw-bold"><?php echo number_format($original_price, 2); ?>₽</span>
                    <?php endif; ?>
                    <span class="text-muted">за шт.</span>
                </div>
                <div class="mb-3">
                    <?php
                    $rating_value = isset($product['rating']) ? (float)$product['rating'] : 0;
                    $full_stars = floor($rating_value);
                    $half_star = ($rating_value - $full_stars) >= 0.5 ? 1 : 0;
                    $empty_stars = 5 - $full_stars - $half_star;

                    for ($s = 0; $s < $full_stars; $s++): echo '<i class="bi bi-star-fill text-warning"></i>'; endfor;
                    if ($half_star): echo '<i class="bi bi-star-half text-warning"></i>'; endif;
                    for ($es = 0; $es < $empty_stars; $es++): echo '<i class="bi bi-star text-warning"></i>'; endfor;
                    ?>
                    <span class="ms-2"><?php echo number_format($rating_value, 1); ?></span>
                    <?php if (isset($product['rating_count']) && $product['rating_count'] > 0): ?>
                        (<?php echo (int)$product['rating_count']; ?> оценок)
                    <?php else: ?>
                        (нет оценок)
                    <?php endif; ?>
                </div>
                <p class="mb-4"><?php echo htmlspecialchars($product['short_description'] ?? ($product['discr'] ?? 'Описание отсутствует.')); ?></p>
                <div class="mb-3 product-stock-info">
                    <?php if (isset($product['stock_quantity']) && $product['stock_quantity'] > 0): ?>
                        <p class="text-success"><i class="bi bi-check-circle-fill"></i> В наличии (<?php echo $product['stock_quantity']; ?> шт.)</p>
                    <?php else: ?>
                        <p class="text-danger"><i class="bi bi-x-circle-fill"></i> Нет в наличии</p>
                    <?php endif; ?>
                </div>
                <?php 
                    $product_id_for_template_prod_page = $product['id'];
                    $product_stock_quantity_prod_page = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : 0;
                    $product_in_cart_quantity_product_page = isset($_SESSION['cart'][$product_id_for_template_prod_page]) ? $_SESSION['cart'][$product_id_for_template_prod_page] : 0;
                ?>
                <div class="cart-controls mb-3" data-product-id="<?php echo $product_id_for_template_prod_page; ?>" data-stock="<?php echo $product_stock_quantity_prod_page; ?>">
                    <?php if ($product_stock_quantity_prod_page > 0): ?>
                        <?php if ($product_in_cart_quantity_product_page > 0): ?>
                            <div class="d-flex align-items-center">
                                <span class="me-3">Количество:</span>
                                <div class="input-group quantity-control-group ajax-quantity-control" style="max-width: 150px;">
                                    <button type="button" class="btn btn-outline-warning btn-lg cart-action-btn" data-action="decrease_quantity">-</button>
                                    <input type="number" class="form-control form-control-lg text-center product-quantity-input" value="<?php echo $product_in_cart_quantity_product_page; ?>" min="0" max="<?php echo $product_stock_quantity_prod_page; ?>" data-action="update_quantity">
                                    <button type="button" class="btn btn-outline-warning btn-lg cart-action-btn" data-action="add_to_cart">+</button>
                                </div>
                            </div>
                        <?php else: ?>
                            <button type="button" class="btn btn-primary btn-lg cart-action-btn" data-action="add_to_cart">
                    <i class="bi bi-cart-plus"></i> Добавить в корзину
                            </button>
                        <?php endif; ?>
                    <?php else: // Товара нет на складе ?>
                         <button class="btn btn-secondary btn-lg" disabled><i class="bi bi-cart-x"></i> Нет в наличии</button>
                    <?php endif; ?>
                </div>
                <div class="mb-3 rating-widget-container">
                    <h6>Оцените товар:</h6>
                    <div class="stars-rating mb-2 <?php if ($user_has_rated) echo 'rated'; ?>" 
                         data-product-id="<?php echo $product['id']; ?>" 
                         data-initial-rating="<?php echo $user_rating_for_this_product; ?>">
                        <i class="bi bi-star" data-value="1" title="Плохо"></i>
                        <i class="bi bi-star" data-value="2" title="Сойдет"></i>
                        <i class="bi bi-star" data-value="3" title="Хорошо"></i>
                        <i class="bi bi-star" data-value="4" title="Отлично"></i>
                        <i class="bi bi-star" data-value="5" title="Превосходно!"></i>
                    </div>
                    <small id="rating-message" class="form-text text-muted"></small>
                </div>
                <div class="mt-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-truck text-primary me-2"></i>
                        <span>Бесплатная доставка от 5000 рублей</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-undo text-primary me-2"></i>
                        <span>Политика возврата в течении 30 суток</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shield-alt text-primary me-2"></i>
                        <span>Гарантия на все товары 2 года</span>
                    </div>
                </div>
            </div>
            <div class="col-md-12 mt-3">
                <h4 class="mb-3">Характеристики товара:</h4>
                <?php
                $characteristics = [];
                if ($product && !empty($product['discr'])) {
                    $lines = explode("\n", trim($product['discr']));
                    foreach ($lines as $line) {
                        $trimmed_line = trim($line);
                        if (empty($trimmed_line)) continue; // Пропускаем пустые строки

                        if (strpos($trimmed_line, ':') !== false) {
                            list($key, $value) = explode(':', $trimmed_line, 2);
                            $characteristics[trim($key)] = trim($value);
                        } else { // Если нет двоеточия, считаем это просто пунктом характеристики
                            $characteristics[] = $trimmed_line;
                        }
                    }
                }
                ?>
                <?php if (!empty($characteristics)): ?>
                    <ul>
                        <?php foreach ($characteristics as $key => $value): ?>
                            <?php if (is_string($key)): // Формат "ключ:значение" ?>
                                <li><p><b><?php echo htmlspecialchars($key); ?>: </b><?php echo htmlspecialchars($value); ?></p></li>
                            <?php else: // Просто пункт списка ?>
                                <li><p><?php echo htmlspecialchars($value); ?></p></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                </ul>
                <?php else: ?>
                    <p>Характеристики не указаны.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function changeImage(event, src) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.thumbnail-gallery .thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
                thumb.style.borderColor = 'transparent';
            });
            if (event && event.target) {
            event.target.classList.add('active');
                event.target.style.borderColor = '#007bff'; // Bootstrap primary color, or your preferred highlight
            }
        }
        // Set initial active thumbnail border
        document.addEventListener('DOMContentLoaded', function() {
            const activeThumb = document.querySelector('.thumbnail-gallery .thumbnail.active');
            if (activeThumb) {
                activeThumb.style.borderColor = '#007bff';
            }

            const starsContainer = document.querySelector('.stars-rating');
            if (starsContainer) {
                const stars = starsContainer.querySelectorAll('.bi-star, .bi-star-fill');
                const productId = starsContainer.dataset.productId;
                const ratingMessage = document.getElementById('rating-message');
                // Считываем начальный рейтинг из data-атрибута
                let initialRating = parseInt(starsContainer.dataset.initialRating) || 0;
                // Если виджет уже помечен как 'rated', то currentRating равен initialRating
                if (starsContainer.classList.contains('rated')) {
                    starsContainer.dataset.currentRating = initialRating;
                }

                function highlightStars(rating) {
                    stars.forEach(star => {
                        if (parseInt(star.dataset.value) <= rating) {
                            star.classList.remove('bi-star');
                            star.classList.add('bi-star-fill', 'text-warning');
                        } else {
                            star.classList.remove('bi-star-fill', 'text-warning');
                            star.classList.add('bi-star');
                        }
                    });
                }

                stars.forEach(star => {
                    star.addEventListener('mouseover', function() {
                        if (!starsContainer.classList.contains('rated')) {
                            highlightStars(parseInt(this.dataset.value));
                        }
                    });

                    star.addEventListener('mouseout', function() {
                        if (!starsContainer.classList.contains('rated')) {
                            // Возвращаем к 0, если не было клика, или к initialRating, если он был установлен
                            const currentRating = parseInt(starsContainer.dataset.currentRating) || 0; // Это будет 0, если еще не кликнуто
                            highlightStars(currentRating);
                        }
                    });

                    star.addEventListener('click', function() {
                        if (starsContainer.classList.contains('rated')) {
                            ratingMessage.textContent = 'Вы уже оценили этот товар.';
                            ratingMessage.className = 'form-text text-info'; // Можно использовать text-info или text-muted
                            return;
                        }
                        const ratingValue = parseInt(this.dataset.value);
                        starsContainer.dataset.currentRating = ratingValue;
                        starsContainer.classList.add('rated');
                        highlightStars(ratingValue);

                        fetch('rate_product.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `product_id=${productId}&rating=${ratingValue}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                ratingMessage.textContent = data.message || 'Спасибо за вашу оценку!';
                                ratingMessage.className = 'form-text text-success';
                                if (data.new_average_rating !== undefined && data.new_rating_count !== undefined) {
                                    const averageRatingElement = document.querySelector('.product-details-rating .rating-value');
                                    const ratingCountElement = document.querySelector('.product-details-rating .rating-count');

                                    if (averageRatingElement) {
                                        averageRatingElement.textContent = parseFloat(data.new_average_rating).toFixed(1);
                                    }
                                    if (ratingCountElement) {
                                        ratingCountElement.textContent = `(${data.new_rating_count} оценок)`;
                                    }
                                    // Обновляем существующие звезды отображения рейтинга
                                    const displayedStarsContainer = document.querySelector('.product-details-rating .stars-display');
                                    if(displayedStarsContainer){
                                        const newRatingFloat = parseFloat(data.new_average_rating);
                                        const fullStars = Math.floor(newRatingFloat);
                                        const halfStar = (newRatingFloat - fullStars) >= 0.5 ? 1 : 0;
                                        const emptyStars = 5 - fullStars - halfStar;
                                        let starsHTML = '';
                                        for(let i=0; i < fullStars; i++) starsHTML += '<i class="bi bi-star-fill text-warning"></i>';
                                        if(halfStar) starsHTML += '<i class="bi bi-star-half text-warning"></i>';
                                        for(let i=0; i < emptyStars; i++) starsHTML += '<i class="bi bi-star text-warning"></i>';
                                        displayedStarsContainer.innerHTML = starsHTML;
                                    }
                                }
                            } else {
                                ratingMessage.textContent = data.message || 'Ошибка при сохранении оценки.';
                                ratingMessage.className = 'form-text text-danger';
                                starsContainer.classList.remove('rated');
                                starsContainer.dataset.currentRating = 0;
                                highlightStars(0);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            ratingMessage.textContent = 'Произошла сетевая ошибка.';
                            ratingMessage.className = 'form-text text-danger';
                            starsContainer.classList.remove('rated');
                            starsContainer.dataset.currentRating = 0;
                            highlightStars(0);
                        });
                    });
                });

                // Устанавливаем начальное состояние звезд на основе initialRating
                highlightStars(initialRating);
                if (initialRating > 0 && starsContainer.classList.contains('rated')){
                    ratingMessage.textContent = 'Вы уже оценили этот товар.';
                    ratingMessage.className = 'form-text text-info'; // Можно использовать text-info или text-muted
                }
            }
        });
    </script>

    <?php include_once "../template/footer.php" ?>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js"
        integrity="sha384-VQqxDN0EQCkWoxt/0vsQvZswzTHUVOImccYmSyhJTp7kGtPed0Qcx8rK9h9YEgx+"
        crossorigin="anonymous"></script>
    
    <!-- Добавляем скрипт для работы с корзиной -->
    <script src="/template/js/cart_ajax.js"></script>
</body>

</html>