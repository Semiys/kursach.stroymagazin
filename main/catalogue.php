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
    error_log("AJAX request to catalogue.php: " . print_r($_GET, true));
    
    $product_id_action = filter_var($_GET['id_to_cart'], FILTER_VALIDATE_INT);
    $action = $_GET['action'];
    
    $response_data = [
        'success' => false,
        'message' => '',
        'new_quantity' => 0,
        'product_id' => $product_id_action,
        'total_cart_items' => 0, // Уникальные товары
        'total_cart_quantity' => 0 // Все единицы
    ];

    if ($product_id_action) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        // Опциональная проверка существования товара в БД (ВАЖНО для AJAX, чтобы не добавить несуществующий товар)
        $stmt_check_product = $pdo->prepare("SELECT id, stock_quantity FROM goods WHERE id = ?");
        $stmt_check_product->execute([$product_id_action]);
        $product_data_from_db = $stmt_check_product->fetch();

        $response_data['stock_quantity'] = $product_data_from_db ? (int)$product_data_from_db['stock_quantity'] : 0;

        if (!$product_data_from_db && ($action == 'add_to_cart' || $action == 'update_quantity' || $action == 'decrease_quantity')) {
            $response_data['message'] = 'Ошибка: Товар не найден в базе данных.';
        } else {
            $current_stock = $product_data_from_db ? (int)$product_data_from_db['stock_quantity'] : 0;
            $response_data['success'] = true; 

            if ($action == 'add_to_cart') {
                $current_in_cart = isset($_SESSION['cart'][$product_id_action]) ? $_SESSION['cart'][$product_id_action] : 0;
                if ($current_in_cart + 1 <= $current_stock) {
                    $_SESSION['cart'][$product_id_action] = $current_in_cart + 1;
                    $response_data['message'] = 'Товар добавлен в корзину!';
                } else {
                    $response_data['message'] = 'Недостаточно товара на складе. Доступно: ' . $current_stock . ', в корзине уже: ' . $current_in_cart;
                    $response_data['success'] = false;
                }
            } elseif ($action == 'decrease_quantity') {
                if (isset($_SESSION['cart'][$product_id_action])) {
                    $_SESSION['cart'][$product_id_action]--;
                    if ($_SESSION['cart'][$product_id_action] <= 0) {
                        unset($_SESSION['cart'][$product_id_action]);
                        $response_data['message'] = 'Товар удален из корзины.';
                    } else {
                        $response_data['message'] = 'Количество товара уменьшено.';
                    }
                } else {
                    $response_data['message'] = 'Товара не было в корзине.'; // Или success = false тут?
                    $response_data['success'] = false; // Логично, если пытаемся уменьшить то, чего нет
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
                $new_qty = isset($_GET['qty']) ? filter_var($_GET['qty'], FILTER_VALIDATE_INT) : false;
                if ($new_qty !== false && $new_qty > 0) {
                    if ($new_qty <= $current_stock) {
                        $_SESSION['cart'][$product_id_action] = $new_qty;
                        $response_data['message'] = 'Количество товара обновлено.';
                    } else {
                        $response_data['message'] = 'Невозможно установить количество: ' . $new_qty . '. На складе: ' . $current_stock . '.';
                        $response_data['success'] = false;
                        // Не меняем количество в корзине, если запрошенное превышает остаток
                    }
                } elseif ($new_qty !== false && $new_qty <= 0) {
                    unset($_SESSION['cart'][$product_id_action]);
                    $response_data['message'] = 'Товар удален из корзины (количество 0 или меньше).';
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

    // Обновляем общие данные корзины для ответа
    if (isset($_SESSION['cart'])) {
        $response_data['total_cart_items'] = count($_SESSION['cart']);
        $response_data['total_cart_quantity'] = array_sum($_SESSION['cart']);
    }

    // Логируем данные ответа для отладки
    error_log("AJAX response from catalogue.php: " . print_r($response_data, true));

    if ($is_ajax_request) {
        // Добавим правильные HTTP-заголовки
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        // Вывод JSON с корректной кодировкой
        echo json_encode($response_data, JSON_UNESCAPED_UNICODE);
        exit();
    } else {
        // Для не-AJAX запросов, сохраняем сообщение во flash и редиректим
        if (!empty($response_data['message'])) {
            $_SESSION['flash_message'] = ['type' => $response_data['success'] ? 'success' : 'danger', 'text' => $response_data['message']];
        }
        $return_url = isset($_GET['return_url']) ? urldecode($_GET['return_url']) : 'catalogue.php';
        header("Location: " . $return_url);
        exit();
    }
}

// Если это не AJAX-запрос или ни один из условий выше не вызвал exit(), 
// тогда включаем header.php для обычного отображения страницы
include_once "../template/header.php";

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

// Получаем выбранные категории из GET-параметра
$selected_categories_from_url = isset($_GET['categories']) && is_array($_GET['categories']) ? $_GET['categories'] : [];
// Получаем поисковый запрос из GET-параметра
$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';
// Получаем минимальную и максимальную цену из GET-параметров
$price_min = isset($_GET['price_min']) && is_numeric($_GET['price_min']) ? (float)$_GET['price_min'] : '';
$price_max = isset($_GET['price_max']) && is_numeric($_GET['price_max']) ? (float)$_GET['price_max'] : '';
// Получаем параметр сортировки из GET
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'newest'; // По умолчанию 'newest'
// Получаем фильтр по рейтингу из GET
$rating_filter = isset($_GET['rating_filter']) && is_numeric($_GET['rating_filter']) ? (int)$_GET['rating_filter'] : null;

// Пагинация
$products_per_page = 12; // Количество товаров на странице
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $products_per_page;

// Формируем SQL-запрос для товаров
$sql_products = "SELECT id, title, price, img, category, discr, rating, article, stock_quantity FROM goods";
$params_products = []; // Массив для параметров подготовленного выражения
$where_clauses = []; // Массив для условий WHERE

if (!empty($selected_categories_from_url)) {
    // Создаем плейсхолдеры для IN clauses: (?, ?, ?)
    $placeholders = implode(',', array_fill(0, count($selected_categories_from_url), '?'));
    $where_clauses[] = "category IN (" . $placeholders . ")";
    // Добавляем выбранные категории в массив параметров
    // array_values используется для переиндексации массива, если ключи не числовые (хотя в данном случае они должны быть)
    // и для обеспечения правильного порядка параметров для execute
    foreach ($selected_categories_from_url as $category_value) {
        $params_products[] = $category_value;
    }
}

if (!empty($search_query)) {
    $search_term_like = '%' . $search_query . '%';
    $where_clauses[] = "(title LIKE ? OR category LIKE ? OR discr LIKE ?)";
    $params_products[] = $search_term_like;
    $params_products[] = $search_term_like;
    $params_products[] = $search_term_like;
}

if ($price_min !== '') {
    $where_clauses[] = "price >= ?";
    $params_products[] = $price_min;
}

if ($price_max !== '') {
    $where_clauses[] = "price <= ?";
    $params_products[] = $price_max;
}

if ($rating_filter !== null && ($rating_filter == 3 || $rating_filter == 4)) {
    $where_clauses[] = "rating >= ?";
    $params_products[] = $rating_filter;
}

if (!empty($where_clauses)) {
    $sql_products .= " WHERE " . implode(' AND ', $where_clauses);
}

// Сначала получаем общее количество товаров (для пагинации) с учетом фильтров
$sql_total_products = "SELECT COUNT(*) FROM goods";
if (!empty($where_clauses)) {
    $sql_total_products .= " WHERE " . implode(' AND ', $where_clauses);
}

try {
    $stmt_total = $pdo->prepare($sql_total_products);
    $stmt_total->execute($params_products); // Используем те же параметры, что и для основного запроса товаров (кроме LIMIT/OFFSET)
    $total_products = (int)$stmt_total->fetchColumn();
} catch (PDOException $e) {
    error_log("Ошибка подсчета товаров: " . $e->getMessage());
    $total_products = 0;
}

$total_pages = ceil($total_products / $products_per_page);
if ($current_page > $total_pages && $total_products > 0) { // Если текущая страница больше максимальной (и есть товары)
    // Перенаправляем на последнюю страницу с сохранением всех GET параметров
    $redirect_params = $_GET;
    $redirect_params['page'] = $total_pages;
    header('Location: catalogue.php?' . http_build_query($redirect_params));
    exit;
} elseif ($current_page > 1 && $total_products == 0) { // Если товаров нет, а мы не на первой странице
    $redirect_params = $_GET;
    unset($redirect_params['page']); // Убираем page, чтобы перейти на первую по сути (без товаров)
    header('Location: catalogue.php?' . http_build_query($redirect_params));
    exit;
}

// Определяем часть ORDER BY на основе параметра sort_by
switch ($sort_by) {
    case 'price_asc':
        $sql_products .= " ORDER BY price ASC";
        break;
    case 'price_desc':
        $sql_products .= " ORDER BY price DESC";
        break;
    case 'name_asc':
        $sql_products .= " ORDER BY title ASC";
        break;
    case 'name_desc':
        $sql_products .= " ORDER BY title DESC";
        break;
    case 'newest':
    default:
        $sql_products .= " ORDER BY id DESC";
        break;
}

$sql_products .= " LIMIT ? OFFSET ?"; // Используем позиционные плейсхолдеры

// Пытаемся получить товары из базы данных с учетом фильтра
try {
    $stmt_products = $pdo->prepare($sql_products);

    // Формируем единый массив параметров для execute, включая параметры для WHERE, LIMIT и OFFSET
    $executable_params = $params_products; // Это параметры для WHERE части (уже содержат ?)
    $executable_params[] = (int)$products_per_page; // Добавляем параметр для LIMIT
    $executable_params[] = (int)$offset;             // Добавляем параметр для OFFSET

    // Явное приведение к (int) здесь для $products_per_page и $offset важно, 
    // так как PDO::execute будет обрабатывать все элементы массива как строки по умолчанию,
    // а LIMIT/OFFSET требуют числовых значений.
    // Хотя $products_per_page у нас задана как int, и $offset вычисляется из int,
    // для полной уверенности и предотвращения проблем с типами данных лучше так.

    // $stmt_products->bindParam(':limit', $products_per_page, PDO::PARAM_INT); // Больше не нужно
    // $stmt_products->bindParam(':offset', $offset, PDO::PARAM_INT); // Больше не нужно
    
    // $stmt_products->execute($params_products); // Старый вариант, где были только параметры WHERE
    $stmt_products->execute($executable_params); // Передаем полный набор позиционных параметров
    
    $products = $stmt_products->fetchAll();
} catch (PDOException $e) {
    error_log("Ошибка загрузки товаров: " . $e->getMessage());
    $products = [];
}

// Пытаемся получить уникальные категории для отображения в фильтре (этот запрос не меняется)
$all_filter_categories = [];
try {
    $stmt_filter_categories = $pdo->query("SELECT DISTINCT category FROM goods WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $all_filter_categories = $stmt_filter_categories->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (PDOException $e) {
    error_log("Ошибка загрузки категорий для фильтра: " . $e->getMessage());
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="../template/css/main.css">
</head>

<body>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <?php echo $flash_message_html; // Выводим flash сообщение здесь ?>
    <div class="container py-5">
        <!-- Top Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Каталог</h4>
            <div class="d-flex gap-2 align-items-center">
                <span class="text-muted">Сортировка:</span>
                <div class="dropdown">
                    <button class="sort-btn dropdown-toggle" type="button" id="dropdownSortButton" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php
                        // Отображаем текущий выбор сортировки
                        switch ($sort_by) {
                            case 'price_asc': echo 'Цена: по возрастанию'; break;
                            case 'price_desc': echo 'Цена: по убыванию'; break;
                            case 'name_asc': echo 'Название: А-Я'; break;
                            case 'name_desc': echo 'Название: Я-А'; break;
                            default: echo 'Новинки'; break;
                        }
                        ?>
                </button>
                    <ul class="dropdown-menu" aria-labelledby="dropdownSortButton">
                        <li><a class="dropdown-item <?php if ($sort_by == 'newest') echo 'active'; ?>" href="<?php echo getCurrentUrlWithSort('newest'); ?>">Новинки</a></li>
                        <li><a class="dropdown-item <?php if ($sort_by == 'price_asc') echo 'active'; ?>" href="<?php echo getCurrentUrlWithSort('price_asc'); ?>">Цена: по возрастанию</a></li>
                        <li><a class="dropdown-item <?php if ($sort_by == 'price_desc') echo 'active'; ?>" href="<?php echo getCurrentUrlWithSort('price_desc'); ?>">Цена: по убыванию</a></li>
                        <li><a class="dropdown-item <?php if ($sort_by == 'name_asc') echo 'active'; ?>" href="<?php echo getCurrentUrlWithSort('name_asc'); ?>">Название: А-Я</a></li>
                        <li><a class="dropdown-item <?php if ($sort_by == 'name_desc') echo 'active'; ?>" href="<?php echo getCurrentUrlWithSort('name_desc'); ?>">Название: Я-А</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-12 mb-4">
            <form class="d-flex" action="catalogue.php" method="GET">
                <div class="input-group">
                    <input class="form-control form-control-lg" type="search" name="search_query" placeholder="Поиск по товарам или категориям" aria-label="Search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <button class="btn btn-primary px-4" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <?php // Скрытые поля для сохранения состояния фильтров категорий и цен при поиске
                if (!empty($selected_categories_from_url)) {
                    foreach ($selected_categories_from_url as $cat) {
                        echo '<input type="hidden" name="categories[]" value="' . htmlspecialchars($cat) . '">';
                    }
                }
                if ($price_min !== '') {
                    echo '<input type="hidden" name="price_min" value="' . htmlspecialchars($price_min) . '">';
                }
                if ($price_max !== '') {
                    echo '<input type="hidden" name="price_max" value="' . htmlspecialchars($price_max) . '">';
                }
                if ($sort_by !== 'newest') { // Добавляем sort_by если он не по умолчанию
                    echo '<input type="hidden" name="sort_by" value="' . htmlspecialchars($sort_by) . '">';
                }
                if ($rating_filter !== null) { // Добавляем rating_filter если он выбран
                     echo '<input type="hidden" name="rating_filter" value="' . htmlspecialchars($rating_filter) . '">';
                }
                ?>
            </form>
        </div>

        <div class="row g-4">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 filters-column">
                <div class="filter-sidebar-wrapper">
                    <form action="catalogue.php" method="GET">
                <div class="filter-sidebar p-4 shadow-sm">
                    <div class="filter-group">
                        <h6 class="mb-3">Категории</h6>
                                <?php if (!empty($all_filter_categories)): ?>
                                    <?php
                                    // $selected_categories_from_url уже определена выше и используется для отметки чекбоксов
                                    ?>
                                    <?php foreach ($all_filter_categories as $category_item): ?>
                                        <?php $category_id_safe = htmlspecialchars(str_replace(' ', '-', $category_item)); ?>
                        <div class="form-check mb-2">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="categories[]"
                                                   value="<?php echo htmlspecialchars($category_item); ?>"
                                                   id="category-<?php echo $category_id_safe; ?>"
                                                   <?php if (in_array($category_item, $selected_categories_from_url)) echo 'checked'; ?>
                                                   >
                                            <label class="form-check-label" for="category-<?php echo $category_id_safe; ?>">
                                                <?php echo htmlspecialchars($category_item); ?>
                            </label>
                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted small">Категории не найдены.</p>
                                <?php endif; ?>
                    </div>

                    <div class="filter-group">
                        <h6 class="mb-3">Диапазон цены</h6>
                                <div class="row g-2">
                                    <div class="col">
                                        <input type="number" class="form-control" name="price_min" placeholder="От" value="<?php echo htmlspecialchars($price_min); ?>" min="0" step="any">
                                    </div>
                                    <div class="col">
                                        <input type="number" class="form-control" name="price_max" placeholder="До" value="<?php echo htmlspecialchars($price_max); ?>" min="0" step="any">
                                    </div>
                        </div>
                    </div>

                    <!-- <div class="filter-group">
                        <h6 class="mb-3">Цвета</h6>
                        <div class="d-flex gap-2">
                            <div class="color-option selected" style="background: #000000;"></div>
                            <div class="color-option" style="background: #dc2626;"></div>
                            <div class="color-option" style="background: #2563eb;"></div>
                            <div class="color-option" style="background: #16a34a;"></div>
                        </div>
                    </div> -->

                    <div class="filter-group">
                        <h6 class="mb-3">Рейтинг</h6>
                        <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="rating_filter" id="rating4" value="4" <?php if ($rating_filter == 4) echo 'checked'; ?>>
                            <label class="form-check-label" for="rating4">
                                <i class="bi bi-star-fill text-warning"></i> 4 и выше
                            </label>
                        </div>
                        <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="rating_filter" id="rating3" value="3" <?php if ($rating_filter == 3) echo 'checked'; ?>>
                            <label class="form-check-label" for="rating3">
                                <i class="bi bi-star-fill text-warning"></i> 3 и выше
                            </label>
                        </div>
                                 <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="rating_filter" id="rating_any" value="" <?php if ($rating_filter === null) echo 'checked'; ?>>
                                    <label class="form-check-label" for="rating_any">
                                        Любой рейтинг
                                    </label>
                    </div>
                </div>
                            <!-- Кнопка и скрытые поля перемещены сюда, ВНУТРЬ блока .filter-sidebar.p-4.shadow-sm -->
                            <button type="submit" class="btn btn-primary w-100 mt-3">Применить фильтры</button>
                            <?php
                            if (!empty($search_query)): ?>
                                <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                            <?php endif;
                            if ($sort_by !== 'newest') : ?>
                                <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
                            <?php endif;
                            // Скрытое поле для rating_filter не нужно здесь, так как он передается через видимые radio кнопки формы.
                            ?>
                        </div> <!-- End of filter-sidebar p-4 shadow-sm -->
                    </form>
                </div> <!-- End of filter-sidebar-wrapper -->
            </div>

            <!-- Product Grid -->
            <div class="col-lg-9">
                <div class="row g-4">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                    <div class="col-md-4">
                                <div class="product-card shadow-sm h-100">
                                    <div class="position-relative product-image-container">
                                        <?php
                                        // Если $product['img'] пусто или файл не существует
                                        // показываем заглушку.
                                        $defaultImagePath = '../template/assets/500x500.png';
                                        $imagePath = $defaultImagePath; // По умолчанию ставим заглушку

                                        if (!empty($product['img'])) {
                                            $potentialImagePath = '../template/assets/' . htmlspecialchars($product['img']);
                                            $absolutePotentialImagePath = __DIR__ . '/../template/assets/' . basename(htmlspecialchars($product['img'])); 
                                            
                                            if (file_exists($absolutePotentialImagePath)) {
                                                $imagePath = $potentialImagePath; 
                                            }
                                        }
                                        ?>
                                        <a href="product.php?id=<?php echo $product['id']; ?>">
                                            <img src="<?php echo $imagePath; ?>" class="product-image w-100" alt="<?php echo htmlspecialchars($product['title']); ?>">
                                        </a>
                                </div>
                                    <div class="card-body-custom d-flex flex-column">
                                        <div>
                                            <p class="product-code text-muted small mb-1">Код: <?php echo htmlspecialchars($product['article'] ?? 'N/A'); ?></p>
                                            <p class="product-category text-muted small mb-1">Категория: <?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?></p>
                                            <h6 class="product-title mb-2">
                                                <a href="product.php?id=<?php echo $product['id']; ?>" title="<?php echo htmlspecialchars($product['title']); ?>">
                                                    <?php echo htmlspecialchars($product['title']); ?>
                                                </a>
                                            </h6>
                                <div class="rating-stars mb-2">
                                                <?php 
                                                $rating_value = isset($product['rating']) ? (float)$product['rating'] : 0;
                                                $full_stars = floor($rating_value);
                                                $half_star = ($rating_value - $full_stars) >= 0.5 ? 1 : 0; 
                                                $empty_stars = 5 - $full_stars - $half_star;

                                                for ($s = 0; $s < $full_stars; $s++): ?>
                                                    <i class="bi bi-star-fill text-warning"></i>
                                                <?php endfor; 

                                                if ($half_star): ?>
                                                    <i class="bi bi-star-half text-warning"></i>
                                                <?php endif; 

                                                for ($es = 0; $es < $empty_stars; $es++): ?>
                                                    <i class="bi bi-star text-warning"></i>
                                                <?php endfor; ?>
                                                <span class="text-muted ms-1">(<?php echo number_format($rating_value, 1); ?>)</span>
                                </div>
                                            <p class="product-availability mb-2">
                                                <?php if (isset($product['stock_quantity']) && $product['stock_quantity'] > 0): ?>
                                                    <i class="bi bi-check-circle-fill text-success"></i> В наличии (<?php echo $product['stock_quantity']; ?> шт.)
                                                <?php else: ?>
                                                    <i class="bi bi-x-circle-fill text-danger"></i> Нет в наличии
                                                <?php endif; ?>
                                            </p>
                                </div>
                                        <div class="mt-auto">
                                            <p class="price fw-bold fs-5 mb-2" style="color: #2563eb !important;"><?php echo number_format($product['price'], 0, '.', ' '); ?> ₽/шт</p>
                                            <?php 
                                                $product_id_for_template = $product['id'];
                                                $product_stock_quantity = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : 0;
                                                $product_in_cart_quantity = isset($_SESSION['cart'][$product_id_for_template]) ? $_SESSION['cart'][$product_id_for_template] : 0;
                                            ?>
                                            <div class="cart-controls" data-product-id="<?php echo $product_id_for_template; ?>" data-stock="<?php echo $product_stock_quantity; ?>">
                                                <?php if ($product_stock_quantity > 0): ?>
                                                    <?php if ($product_in_cart_quantity > 0): ?>
                                                        <div class="input-group quantity-control-group ajax-quantity-control" style="max-width: 160px; margin-left: auto; margin-right: auto;">
                                                            <a href="#" class="btn btn-outline-secondary btn-sm cart-action-btn" data-action="decrease_quantity">-</a>
                                                            <input type="number" class="form-control form-control-sm text-center product-quantity-input" value="<?php echo $product_in_cart_quantity; ?>" min="0" max="<?php echo $product_stock_quantity; ?>" data-action="update_quantity">
                                                            <a href="#" class="btn btn-outline-secondary btn-sm cart-action-btn" data-action="add_to_cart">+</a>
                                                        </div>
                                                    <?php else: ?>
                                                        <a href="#" class="btn btn-primary w-100 cart-action-btn" data-action="add_to_cart" title="Добавить в корзину">
                                                            В корзину
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: // Товара нет на складе ?>
                                                    <button class="btn btn-secondary w-100" disabled>Нет в наличии</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col">
                            <p class="text-center">Товары не найдены или не удалось загрузить.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php // Вывод пагинации над списком товаров, если страниц больше одной
                if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4 mb-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>">
                                <a class="page-link" href="<?php echo getCurrentUrlWithPage($i); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>

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

</body>

</html>

<?php
// Вспомогательная функция для генерации URL с новым параметром сортировки, сохраняя остальные GET-параметры
function getCurrentUrlWithSort($new_sort_value) {
    $current_params = $_GET;
    $current_params['sort_by'] = $new_sort_value;
    // При смене сортировки всегда сбрасываем на первую страницу
    unset($current_params['page']);
    // Не сбрасываем rating_filter при смене сортировки
    return strtok($_SERVER["REQUEST_URI"], '?') . '?' . http_build_query($current_params);
}

// Вспомогательная функция для генерации URL с новым номером страницы, сохраняя остальные GET-параметры
function getCurrentUrlWithPage($new_page_number) {
    $current_params = $_GET;
    $current_params['page'] = $new_page_number;
    // Не сбрасываем rating_filter при смене страницы
    return strtok($_SERVER["REQUEST_URI"], '?') . '?' . http_build_query($current_params);
}
?>