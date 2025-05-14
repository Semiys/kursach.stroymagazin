<div id="carouselExampleDark" class="carousel carousel-dark slide">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#carouselExampleDark" data-bs-slide-to="0" class="active"
            aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#carouselExampleDark" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#carouselExampleDark" data-bs-slide-to="2" aria-label="Slide 3"></button>
    </div>
    <div class="carousel-inner" style="height: 400px; overflow: hidden;">
        <div class="carousel-item active" data-bs-interval="10000">
            <img src="template/assets/1.jpg?v=<?php echo time(); ?>" class="d-block w-100" alt="Строительные материалы" style="object-fit: cover; height: 400px;">
            <div class="carousel-caption d-none d-md-block">
                <h1 style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.7);">Качественные строительные материалы</h1>
                <p style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.7);">Широкий ассортимент материалов для строительства и ремонта.</p>
            </div>
        </div>
        <div class="carousel-item" data-bs-interval="2000">
            <img src="template/assets/2.jpg?v=<?php echo time(); ?>" class="d-block w-100" alt="Инструменты" style="object-fit: cover; height: 400px;">
            <div class="carousel-caption d-none d-md-block">
                <h1 style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.7);">Профессиональные инструменты</h1>
                <p style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.7);">Инструменты и оборудование от ведущих производителей.</p>
            </div>
        </div>
        <div class="carousel-item">
            <img src="template/assets/3.jpg?v=<?php echo time(); ?>" class="d-block w-100" alt="Отделочные материалы" style="object-fit: cover; height: 400px;">
            <div class="carousel-caption d-none d-md-block">
                <h1 style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.7);">Отделочные материалы</h1>
                <p style="color: white; text-shadow: 2px 2px 4px rgba(0,0,0,0.7);">Всё для внутренней и внешней отделки вашего дома.</p>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleDark" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleDark" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>

<?php
// Подключение к базе данных
require_once 'config.php';

// Запрос для получения 4 самых популярных товаров 
// (товары с наибольшим количеством оценок)
try {
    $popular_products_sql = "
        SELECT g.*, COUNT(pr.id) as rating_count
        FROM goods g
        JOIN product_ratings pr ON g.id = pr.product_id
        WHERE g.stock_quantity > 0  -- Только товары в наличии
        GROUP BY g.id
        ORDER BY rating_count DESC
        LIMIT 4
    ";
    
    $stmt = $pdo->prepare($popular_products_sql);
    $stmt->execute();
    $popular_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка при загрузке популярных товаров: " . $e->getMessage());
    $popular_products = [];
}
?>

<div class="d-flex justify-content-center" style="margin-top: 50px;">
    <h1>Популярные товары</h1>
</div>

<div class="container" style="margin-top: 50px;">
    <div class="row">
        <?php if (empty($popular_products)): ?>
            <div class="col-12 text-center">
                <p>Нет популярных товаров для отображения.</p>
            </div>
        <?php else: ?>
            <?php foreach ($popular_products as $product): ?>
                <?php
                // Получаем скидку из базы данных
                $discount_percentage = isset($product['discount']) ? intval($product['discount']) : 0;
                
                $original_price = floatval($product['price']);
                $discounted_price = $original_price;
                
                if ($discount_percentage > 0) {
                    $discounted_price = $original_price * (1 - $discount_percentage / 100);
                }
                
                // Определяем, является ли товар хитом (если количество оценок больше 3)
                $is_hit = isset($product['rating_count']) && intval($product['rating_count']) > 3;
                
                // Проверяем, есть ли товар в корзине
                $quantity_in_cart = 0;
                if (isset($_SESSION['cart']) && isset($_SESSION['cart'][$product['id']])) {
                    $quantity_in_cart = (int)$_SESSION['cart'][$product['id']];
                }
                ?>
                <div class="col-md-3">
                    <div class="product-card shadow-sm">
                        <div class="position-relative">
                            <?php
                            // Определяем путь к изображению товара
                            $imagePath = 'template/assets/500x500.png'; // Картинка по умолчанию
                            
                            if (!empty($product['img'])) {
                                $potentialImagePath = 'template/assets/' . htmlspecialchars($product['img']);
                                $absolutePotentialImagePath = __DIR__ . '/../template/assets/' . basename(htmlspecialchars($product['img']));
                                
                                if (file_exists($absolutePotentialImagePath)) {
                                    $imagePath = $potentialImagePath;
                                }
                            }
                            ?>
                            <a href="/main/product.php?id=<?php echo $product['id']; ?>">
                                <img src="<?php echo $imagePath; ?>" class="product-image w-100" alt="<?php echo htmlspecialchars($product['title']); ?>">
                            </a>
                            <div class="position-absolute top-0 start-0" style="padding-left: 6px; padding-top: 2px;">
                                <?php if ($discount_percentage > 0): ?>
                                <span class="badge text-bg-danger discount-badge">СКИДКА <?php echo $discount_percentage; ?>%</span>
                                <?php endif; ?>
                                <?php if ($is_hit): ?>
                                <span class="badge text-bg-success discount-badge">ХИТ</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="p-3">
                            <span class="category-badge mb-2 d-inline-block"><?php echo htmlspecialchars($product['category']); ?></span>
                            <h6 class="mb-1"><?php echo htmlspecialchars($product['title']); ?></h6>
                            <div class="rating-stars mb-2">
                                <?php 
                                // Отображение звезд рейтинга
                                $rating_rounded = round($product['rating'], 0);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating_rounded) {
                                        echo '<i class="bi bi-star-fill"></i>';
                                    } else {
                                        echo '<i class="bi bi-star"></i>';
                                    }
                                }
                                ?>
                                <span class="text-muted ms-2">(<?php echo number_format($product['rating'], 1); ?>)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="price">
                                    <?php if ($discount_percentage > 0): ?>
                                    <del class="text-muted me-2"><?php echo number_format($original_price, 2); ?>₽</del>
                                    <?php echo number_format($discounted_price, 2); ?>₽
                                    <?php else: ?>
                                    <?php echo number_format($original_price, 2); ?>₽
                                    <?php endif; ?>
                                    <a style="color: gray;">шт.</a>
                                </span>
                                
                                <!-- Кнопки корзины: показываем разные UI в зависимости от наличия товара в корзине -->
                                <div class="quantity-controls" id="cart-controls-<?php echo $product['id']; ?>">
                                    <?php if ($quantity_in_cart > 0): ?>
                                    <!-- Если товар уже в корзине, показываем управление количеством -->
                                    <div class="quantity-control-group">
                                        <button class="btn btn-sm btn-outline-primary decrease-quantity-btn" data-product-id="<?php echo $product['id']; ?>">
                                            <i class="bi bi-dash"></i>
                                        </button>
                                        <span class="quantity-display"><?php echo $quantity_in_cart; ?></span>
                                        <button class="btn btn-sm btn-outline-primary increase-quantity-btn" data-product-id="<?php echo $product['id']; ?>">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <!-- Если товара нет в корзине, показываем соответствующую кнопку -->
                                    <?php if (isset($product['stock_quantity']) && $product['stock_quantity'] > 0): ?>
                                    <button class="btn cart-btn add-to-cart-btn" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="bi bi-cart-plus"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-secondary" disabled title="Нет в наличии">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-center" style="margin-top: 50px;">
        <h1>О проекте</h1>
    </div>
    <div class="row align-items-center" style="margin-top: 50px;">
        <div class="col-md-8">
            <p class="lead">
                Данный интернет-магазин был разработан в качестве курсовой работы по предмету <strong>"Базы
                    данных"</strong>
                в <strong>Институте авиационных технологий и управления</strong> студентом 2 курса <strong>Мишиным
                    Артуром
                    Вадимовичем</strong>.
            </p>
            <p class="lead">
                Руководителем проекта является <strong>Бажутин Михаил Михайлович</strong> — старший преподаватель
                кафедры
                "Информационные технологии и общенаучные дисциплины" Самолетостроительного факультета Института
                авиационных технологий и управления.
            </p>
        </div>
        <div class="col-md-4">
            <img src="https://avatars.mds.yandex.net/get-altay/4381564/2a00000182a135afbc2f24b8c7c67c78aaff/orig"
                class="img-fluid rounded shadow" alt="ИАТУ Ульяновск">
        </div>
    </div>
</div>

<!-- Добавляем CSS для кнопок корзины -->
<style>
.quantity-control-group {
    display: flex;
    align-items: center;
    border-radius: 4px;
}

.quantity-control-group .btn {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quantity-display {
    padding: 0 0.75rem;
    font-weight: 500;
}
</style>

<!-- Подключаем глобальные функции для корзины -->
<script src="/template/js/cart_ajax.js"></script>

<!-- Подключаем специальный файл для корзины на главной странице -->
<script src="/template/js/cart_main.js"></script>