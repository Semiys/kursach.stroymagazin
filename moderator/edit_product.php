<?php
session_start();
require_once __DIR__ . '/../config.php'; // Corrected path and assuming $pdo is used as in manage_products.php

// Check if user is logged in and is a moderator
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'moder') {
    header("Location: /index.php");
    exit();
}

$page_title = "Управление товаром";
$is_editing = false;
$product = [
    'title' => '',
    'article' => '',
    'category' => '',
    'price' => '',
    'stock_quantity' => '',
    'discount' => 0,
    'short_description' => '',
    'discr' => '',
    'img' => '',
    'gallery_images' => '' // Store as JSON string or handle as array
];
$product_id = null;
$errors = []; // For storing validation errors

if (isset($_GET['id'])) {
    $is_editing = true;
    $product_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($product_id === false) {
        header("Location: manage_products.php?error=invalid_id");
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM goods WHERE id = ?");
        $stmt->execute([$product_id]);
        $fetched_product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fetched_product) {
            header("Location: manage_products.php?error=notfound");
            exit();
        }
        // Merge fetched product data with defaults to ensure all keys exist
        $product = array_merge($product, $fetched_product);
        $page_title = "Редактировать товар: " . htmlspecialchars($product['title']);
    } catch (PDOException $e) {
        // Log error or display a message
        $errors[] = "Ошибка загрузки данных товара: " . $e->getMessage();
        $page_title = "Ошибка редактирования товара";
    }
} else {
    $page_title = "Добавить новый товар";
}

// Fetch distinct categories for a datalist or suggestions
$categories = [];
try {
    $categories_stmt = $pdo->query("SELECT DISTINCT category FROM goods WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Log error or display a message
    $errors[] = "Ошибка загрузки категорий: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Populate product array from POST data for repopulating form on error
    $product['title'] = trim($_POST['title'] ?? '');
    $product['article'] = trim($_POST['article'] ?? '');
    $product['category'] = trim($_POST['category'] ?? '');
    $product['price'] = trim($_POST['price'] ?? '');
    $product['stock_quantity'] = trim($_POST['stock_quantity'] ?? '');
    $product['discount'] = trim($_POST['discount'] ?? 0);
    $product['short_description'] = trim($_POST['short_description'] ?? '');
    $product['discr'] = trim($_POST['discr'] ?? '');
    // Keep existing image paths if no new files are uploaded or errors occur before processing them
    // They will be overwritten later if new files are successfully processed.

    // Validation
    if (empty($product['title'])) {
        $errors[] = "Название товара обязательно для заполнения.";
    }
    if (empty($product['category'])) {
        $errors[] = "Категория обязательна для заполнения.";
    }
    if (!is_numeric($product['price']) || $product['price'] < 0) {
        $errors[] = "Цена должна быть положительным числом.";
    }
     if ($product['price'] === '' || $product['price'] === null) { // Check if price is empty
        $errors[] = "Цена обязательна для заполнения.";
    }
    if (!is_numeric($product['stock_quantity']) || intval($product['stock_quantity']) < 0) {
        $errors[] = "Количество на складе должно быть целым положительным числом.";
    }
    if ($product['stock_quantity'] === '' || $product['stock_quantity'] === null) {
        $errors[] = "Количество на складе обязательно для заполнения.";
    }
    if (!is_numeric($product['discount']) || $product['discount'] < 0 || $product['discount'] > 100) {
        $errors[] = "Скидка должна быть числом от 0 до 100.";
    }
    
    $upload_dir_relative_to_script = __DIR__ . '/../uploads/products/';
    $upload_dir_for_db = 'uploads/products/';

    if (!is_dir($upload_dir_relative_to_script)) {
        if (!mkdir($upload_dir_relative_to_script, 0777, true)) {
            $errors[] = "Не удалось создать директорию для загрузки изображений: " . $upload_dir_relative_to_script;
        }
    }
    if (!is_writable($upload_dir_relative_to_script)) {
         $errors[] = "Директория для загрузки изображений недоступна для записи: " . $upload_dir_relative_to_script;
    }

    // Handle main image ('img')
    $new_main_image_path = $product['img']; // Keep old if no new one is uploaded
    if (isset($_FILES['img']) && $_FILES['img']['error'] === UPLOAD_ERR_OK && empty($errors)) {
        $img_tmp_name = $_FILES['img']['tmp_name'];
        $img_name = basename($_FILES['img']['name']);
        $img_ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($img_ext, $allowed_ext)) {
            if ($_FILES['img']['size'] <= 5 * 1024 * 1024) { // Max 5MB
                $new_img_filename = uniqid('prod_', true) . '.' . $img_ext;
                $target_main_file = $upload_dir_relative_to_script . $new_img_filename;
                if (move_uploaded_file($img_tmp_name, $target_main_file)) {
                    // TODO: Optionally delete old image if $is_editing and $product['img'] had a value
                    $new_main_image_path = $upload_dir_for_db . $new_img_filename;
                } else {
                    $errors[] = "Не удалось загрузить основное изображение.";
                }
            } else {
                $errors[] = "Основное изображение слишком велико (макс. 5MB).";
            }
        } else {
            $errors[] = "Недопустимый формат основного изображения. Разрешены: " . implode(', ', $allowed_ext);
        }
    } elseif (isset($_FILES['img']) && $_FILES['img']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['img']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Ошибка при загрузке основного изображения: код " . $_FILES['img']['error'];
    }

    // Handle gallery images ('gallery_images')
    $new_gallery_images_string = $product['gallery_images']; // Keep old if no new ones are uploaded
    if (isset($_FILES['gallery_images']) && empty($errors)) {
        $gallery_files = $_FILES['gallery_images'];
        $uploaded_gallery_paths = [];
        $has_gallery_uploads = false;
        $allowed_gallery_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp']; // Определение разрешенных расширений для галереи

        foreach ($gallery_files['name'] as $key => $name) {
            if ($gallery_files['error'][$key] === UPLOAD_ERR_OK) {
                $has_gallery_uploads = true; // Mark that new gallery files are being processed
                $g_tmp_name = $gallery_files['tmp_name'][$key];
                $g_name = basename($name);
                $g_ext = strtolower(pathinfo($g_name, PATHINFO_EXTENSION));

                if (in_array($g_ext, $allowed_gallery_extensions)) { // Используем новую переменную
                    if ($gallery_files['size'][$key] <= 2 * 1024 * 1024) { // Max 2MB per image
                        $new_g_filename = uniqid('gallery_', true) . '.' . $g_ext;
                        $target_gallery_file = $upload_dir_relative_to_script . $new_g_filename;
                        if (move_uploaded_file($g_tmp_name, $target_gallery_file)) {
                            $uploaded_gallery_paths[] = $upload_dir_for_db . $new_g_filename;
                        } else {
                            $errors[] = "Не удалось загрузить файл галереи: " . htmlspecialchars($g_name);
                        }
                    } else {
                        $errors[] = "Файл галереи '" . htmlspecialchars($g_name) . "' слишком велик (макс. 2MB).";
                    }
                } else {
                    $errors[] = "Недопустимый формат файла галереи '" . htmlspecialchars($g_name) . "'.";
                }
            } elseif ($gallery_files['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                 $errors[] = "Ошибка при загрузке файла галереи '" . htmlspecialchars($name) . "': код " . $gallery_files['error'][$key];
            }
        }
        
        if ($has_gallery_uploads && !empty($uploaded_gallery_paths)) {
             // TODO: Optionally delete old gallery images if $is_editing and $product['gallery_images'] had values
            // $new_gallery_images_json = json_encode($uploaded_gallery_paths); // Старый вариант с JSON
            $new_gallery_images_string = implode(',', $uploaded_gallery_paths); // Новый вариант: строка через запятую
        } elseif ($has_gallery_uploads && empty($uploaded_gallery_paths) && empty($errors)) {
            // This case means files were selected for gallery, but all failed to upload for some reason
            // If we want to clear gallery on failed new uploads, set to empty string.
            // $new_gallery_images_string = ''; // Clears gallery
            // Current logic: only replace if new files are *successfully* uploaded.
            // Если оставить как есть, то $new_gallery_images_string не будет определена здесь, 
            // и ниже $product['gallery_images'] = $new_gallery_images_string; вызовет ошибку, если это единственный путь обновления.
            // Поэтому, если файлы были выбраны, но ни один не загрузился, очистим галерею или оставим старую.
            // Пока оставим старую, присвоив $new_gallery_images_string текущее значение.
            $new_gallery_images_string = $product['gallery_images'];
        } else {
            // Если новых загрузок не было или были ошибки до обработки галереи,
            // $new_gallery_images_string должна остаться равной текущему значению из $product['gallery_images']
            $new_gallery_images_string = $product['gallery_images'];
        }
    } else {
        // Если поле gallery_images вообще не было отправлено (например, нет input type=file multiple)
        // или были ошибки до этапа обработки галереи, сохраняем текущее значение.
        $new_gallery_images_string = $product['gallery_images'];
    }


    if (empty($errors)) {
        try {
            if ($is_editing) {
                // UPDATE existing product
                $sql = "UPDATE goods SET title = :title, article = :article, category = :category, price = :price, 
                        stock_quantity = :stock_quantity, discount = :discount, short_description = :short_description, 
                        discr = :discr, img = :img, gallery_images = :gallery_images 
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
            } else {
                // INSERT new product
                $sql = "INSERT INTO goods (title, article, category, price, stock_quantity, discount, short_description, discr, img, gallery_images, rating, rating_count) 
                        VALUES (:title, :article, :category, :price, :stock_quantity, :discount, :short_description, :discr, :img, :gallery_images, 0, 0)"; // Assuming rating starts at 0
                $stmt = $pdo->prepare($sql);
            }

            $stmt->bindParam(':title', $product['title']);
            $stmt->bindParam(':article', $product['article']);
            $stmt->bindParam(':category', $product['category']);
            $stmt->bindParam(':price', $product['price']); // No need to cast, PDO handles it with type hint or column type
            $stmt->bindParam(':stock_quantity', $product['stock_quantity'], PDO::PARAM_INT);
            $stmt->bindParam(':discount', $product['discount'], PDO::PARAM_INT); // Assuming discount is integer %
            $stmt->bindParam(':short_description', $product['short_description']);
            $stmt->bindParam(':discr', $product['discr']);
            $stmt->bindParam(':img', $new_main_image_path); // Use the potentially updated path
            // $stmt->bindParam(':gallery_images', $new_gallery_images_json); // Старый вариант с JSON
            $stmt->bindParam(':gallery_images', $new_gallery_images_string); // Новый вариант: строка через запятую

            $stmt->execute();

            $success_message = $is_editing ? "Товар успешно обновлен!" : "Товар успешно добавлен!";
            header("Location: manage_products.php?success=" . urlencode($success_message));
            exit();

        } catch (PDOException $e) {
            $errors[] = "Ошибка базы данных: " . $e->getMessage();
        }
    }
    // If there were errors, the script continues and will re-display the form with errors and entered values.
    // Update $product array with processed image paths if they were changed, so form shows correct state
    $product['img'] = $new_main_image_path; 
    // $product['gallery_images'] = $new_gallery_images_json; // Старый вариант
    $product['gallery_images'] = $new_gallery_images_string; // Новый вариант

} // End of POST request handling

// Placeholder for POST request handling (form submission)
// if ($_SERVER['REQUEST_METHOD'] === 'POST') { // This comment is now obsolete
    // ... Form processing logic will go here ...
// } // This comment is now obsolete

include 'header.php';
?>

<div class="container mt-4">
    <h2><?php echo $page_title; ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="edit_product.php<?php echo $is_editing ? '?id=' . htmlspecialchars($product_id) : ''; ?>" method="POST" enctype="multipart/form-data">
        
        <div class="row">
            <div class="col-md-8">
                <!-- Card 1: Основная информация -->
                <div class="card mb-4">
                    <div class="card-header">
                        Основная информация
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Название товара <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($product['title']); ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="article" class="form-label">Артикул</label>
                                <input type="text" class="form-control" id="article" name="article" value="<?php echo htmlspecialchars($product['article']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Категория <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="category" name="category" list="categories_list" value="<?php echo htmlspecialchars($product['category']); ?>" required>
                                <datalist id="categories_list">
                                    <?php foreach ($categories as $cat_name): ?>
                                        <option value="<?php echo htmlspecialchars($cat_name); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Описания -->
                <div class="card mb-4">
                    <div class="card-header">
                        Описания
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="short_description" class="form-label">Краткое описание</label>
                            <textarea class="form-control" id="short_description" name="short_description" rows="3"><?php echo htmlspecialchars($product['short_description']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="discr" class="form-label">Полное описание</label>
                            <textarea class="form-control" id="discr" name="discr" rows="6"><?php echo htmlspecialchars($product['discr']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Ценообразование и наличие -->
                <div class="card mb-4">
                    <div class="card-header">
                        Ценообразование и наличие
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="price" class="form-label">Цена <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="stock_quantity" class="form-label">На складе <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="discount" class="form-label">Скидка (%)</label>
                                <input type="number" class="form-control" id="discount" name="discount" value="<?php echo htmlspecialchars($product['discount'] ?? 0); ?>" min="0" max="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Card 4: Изображения -->
                <div class="card mb-4">
                    <div class="card-header">
                        Изображения
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="img" class="form-label">Основное изображение</label>
                            <input type="file" class="form-control" id="img" name="img" accept="image/*">
                            <?php if ($is_editing && !empty($product['img'])): ?>
                                <div class="mt-2">
                                    <img src="<?php echo APP_URL . '/' . htmlspecialchars(ltrim($product['img'], '/')); ?>" alt="Текущее изображение" style="max-width: 100%; height: auto; max-height: 200px;">
                                    <p class="text-muted"><small>Текущее: <?php echo htmlspecialchars($product['img']); ?></small></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="gallery_images" class="form-label">Галерея изображений</label>
                            <input type="file" class="form-control" id="gallery_images" name="gallery_images[]" multiple accept="image/*">
                            <?php 
                            if ($is_editing && !empty($product['gallery_images'])):
                                // $gallery_paths = json_decode($product['gallery_images'], true); // Старый вариант с JSON
                                // Новый вариант: если это строка, разделенная запятыми
                                $gallery_paths_str = trim($product['gallery_images']);
                                $gallery_paths = [];
                                if (!empty($gallery_paths_str)) {
                                    $gallery_paths = explode(',', $gallery_paths_str);
                                }

                                if (is_array($gallery_paths) && !empty($gallery_paths)):
                            ?>
                                <div class="mt-2 d-flex flex-wrap">
                                    <?php foreach ($gallery_paths as $g_img_path): ?>
                                        <div class="me-2 mb-2">
                                            <img src="<?php echo APP_URL . '/' . htmlspecialchars(ltrim($g_img_path, '/')); ?>" alt="Изображение галереи" style="width: 80px; height: 80px; object-fit: cover;">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <small class="form-text text-muted">Загрузка новых файлов галереи заменит существующие.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">
        <div class="d-flex justify-content-end mb-4">
            <a href="manage_products.php" class="btn btn-secondary me-2">Отмена</a>
            <button type="submit" class="btn btn-primary-orange"><?php echo $is_editing ? 'Сохранить изменения' : 'Добавить товар'; ?></button>
        </div>
    </form>

</div>

<?php include 'footer.php'; ?> 