<?php
session_start();
require_once '../config.php';

$product_id = null;
$product = null;
$error_message = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT id, title, price, img, category, discr, rating, article, short_description, rating_count FROM goods WHERE id = ?");
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

// Логику для галереи изображений (если есть дополнительные) можно будет добавить здесь
// Например, если в $product['gallery_images'] есть строка с именами файлов через запятую:
// $gallery_images = [];
// if ($product && !empty($product['gallery_images'])) {
//    $gallery_image_names = explode(',', $product['gallery_images']);
//    foreach ($gallery_image_names as $img_name) {
//        $gallery_images[] = '../template/assets/' . htmlspecialchars(trim($img_name));
//    }
// }

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
                <?php /* Блок с миниатюрами пока закомментируем 
                <div class="d-flex justify-content-between">
                    <img src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w0NzEyNjZ8MHwxfHNlYXJjaHwxfHxoZWFkcGhvbmV8ZW58MHwwfHx8MTcyMTMwMzY5MHww&ixlib=rb-4.0.3&q=80&w=1080"
                        alt="Thumbnail 1" class="thumbnail rounded active" onclick="changeImage(event, this.src)">
                    <img src="https://images.unsplash.com/photo-1528148343865-51218c4a13e6?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w0NzEyNjZ8MHwxfHNlYXJjaHwzfHxoZWFkcGhvbmV8ZW58MHwwfHx8MTcyMTMwMzY5MHww&ixlib=rb-4.0.3&q=80&w=1080"
                        alt="Thumbnail 2" class="thumbnail rounded" onclick="changeImage(event, this.src)">
                    <img src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w0NzEyNjZ8MHwxfHNlYXJjaHwxfHxoZWFkcGhvbmV8ZW58MHwwfHx8MTcyMTMwMzY5MHww&ixlib=rb-4.0.3&q=80&w=1080"
                        alt="Thumbnail 3" class="thumbnail rounded" onclick="changeImage(event, this.src)">
                    <img src="https://images.unsplash.com/photo-1528148343865-51218c4a13e6?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w0NzEyNjZ8MHwxfHNlYXJjaHwzfHxoZWFkcGhvbmV8ZW58MHwwfHx8MTcyMTMwMzY5MHww&ixlib=rb-4.0.3&q=80&w=1080"
                        alt="Thumbnail 4" class="thumbnail rounded" onclick="changeImage(event, this.src)">
                </div>
                */ ?>
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
                <button class="btn btn-outline-secondary btn-lg mb-3">
                    <i class="bi bi-heart"></i> Оценить товар
                </button>
                <div class="mt-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-truck text-primary me-2"></i>
                        <span>Беслплатная доставка от 5000 рублей</span>
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
            document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
            event.target.classList.add('active');
        }
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