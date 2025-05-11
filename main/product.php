<?php
session_start();
require_once '../config.php';

$product_id = null;
$product = null;
$error_message = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT id, title, price, img, category, discr, rating, article, short_description, rating_count, gallery_images FROM goods WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $error_message = 'Товар не найден.';
            // Можно установить HTTP-код ответа 404
            // header("HTTP/1.0 404 Not Found"); 
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
            $potential_path = '../template/assets/' . htmlspecialchars($trimmed_name);
            $absolute_potential_path = __DIR__ . '/../template/assets/' . basename(htmlspecialchars($trimmed_name));
            if (file_exists($absolute_potential_path)) {
                $gallery_image_paths[] = $potential_path;
            }
        }
    }
}
// Добавляем основное изображение в начало галереи, если оно есть и еще не там
if ($product && !empty($product['img'])) {
    $main_image_path_for_gallery = '../template/assets/' . htmlspecialchars($product['img']);
    $absolute_main_image_path = __DIR__ . '/../template/assets/' . basename(htmlspecialchars($product['img']));
    if (file_exists($absolute_main_image_path) && !in_array($main_image_path_for_gallery, $gallery_image_paths)) {
        array_unshift($gallery_image_paths, $main_image_path_for_gallery);
    } elseif (empty($gallery_image_paths) && file_exists($absolute_main_image_path)){
        // Если галерея пуста, но основное изображение есть, добавляем его
        $gallery_image_paths[] = $main_image_path_for_gallery;
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
    <?php include_once "../template/header.php" ?>

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
                $mainImagePath = '../template/assets/500x500.png'; // Заглушка по умолчанию
                if ($product && !empty($product['img'])) {
                    $potentialImagePath = '../template/assets/' . htmlspecialchars($product['img']);
                    $absolutePotentialImagePath = __DIR__ . '/../template/assets/' . basename(htmlspecialchars($product['img']));
                    if (file_exists($absolutePotentialImagePath)) {
                        $mainImagePath = $potentialImagePath;
                    }
                }
                ?>
                <img src="<?php echo $mainImagePath; ?>" alt="<?php echo htmlspecialchars($product['title'] ?? 'Изображение товара'); ?>" class="img-fluid rounded mb-3" id="mainImage">
                <?php if (!empty($gallery_image_paths) && count($gallery_image_paths) > 1): ?>
                <div class="row gx-2 gy-2 mt-2 thumbnail-gallery">
                    <?php foreach ($gallery_image_paths as $index => $thumb_path): ?>
                    <div class="col-3">
                        <img src="<?php echo $thumb_path; ?>"
                             alt="Thumbnail <?php echo $index + 1; ?>"
                             class="img-fluid rounded thumbnail <?php if ($thumb_path === $mainImagePath) echo 'active'; ?>"
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
                    <span class="h4 me-2"><?php 
                        $price_val = $product['price'] ?? 0;
                        $decimals = ($price_val == floor($price_val)) ? 0 : 2; // 0 знаков если целое, иначе 2
                        echo number_format($price_val, $decimals, '.', ' '); 
                    ?>₽</span>
                    <?php // <span class="text-muted"><s>499.99₽</s></span> // Старую цену пока убрали ?>
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
                <button class="btn btn-primary btn-lg mb-3 me-2">
                    <i class="bi bi-cart-plus"></i> Добавить в корзину
                </button>
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
</body>

</html>