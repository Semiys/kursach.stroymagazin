<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/audit_logger.php'; // Подключаем логгер

// Проверка, залогинен ли пользователь и является ли он администратором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

$product_id = $_GET['id'] ?? null;
$is_editing = $product_id !== null;
$page_title = $is_editing ? "Редактирование товара" : "Добавление нового товара";

include_once 'header.php';

$product = [
    'id' => null,
    'title' => '',
    'discr' => '', // Для поля description в БД
    'short_description' => '', // Для краткого описания
    'price' => '',
    'category' => '',
    'category_custom' => '',
    'stock_quantity' => '',
    'img' => '', // Для поля img в БД
    'article' => '', // Для поля article в БД (SKU)
    'discount' => 0,
    'is_hidden' => 0,
    'gallery_images' => '' // Для изображений галереи
];

$errors = [];
$success_message = '';

// Получение существующих категорий для выпадающего списка
$categories = [];
$text_categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM goods WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt_text_cat = $pdo->query("SELECT category_name FROM hidden_categories ORDER BY category_name ASC");
    $text_categories = $stmt_text_cat->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $errors[] = "Ошибка загрузки категорий: " . $e->getMessage();
}

// Сначала загружаем данные товара, если редактируем
if ($is_editing) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM goods WHERE id = :id");
        $stmt->execute([':id' => $product_id]);
        $loaded_product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($loaded_product) {
            foreach ($product as $key => $defaultValue) {
                if (isset($loaded_product[$key])) {
                    $product[$key] = $loaded_product[$key];
                }
            }
            // Убедимся, что gallery_images всегда строка, даже если в БД NULL
            $product['gallery_images'] = $loaded_product['gallery_images'] ?? '';
        } else {
            $errors[] = "Товар с ID " . htmlspecialchars($product_id) . " не найден.";
            $is_editing = false; // Сбрасываем, если товар не найден
        }
    } catch (PDOException $e) {
        $errors[] = "Ошибка загрузки товара: " . $e->getMessage();
    }
}

// Теперь, когда $product определен (либо пустой, либо загружен), определяем $selected_category_type
$selected_category_type = 'existing'; // Значение по умолчанию
if ($is_editing && !empty($product['category'])) { // Если редактируем и категория у товара есть
    if (!empty($text_categories) && in_array($product['category'], $text_categories)) {
        $selected_category_type = 'existing_text';
    } elseif (!empty($categories) && in_array($product['category'], $categories)) {
        $selected_category_type = 'existing';
    } else { // Категория товара не найдена в существующих списках
        if (!empty($product['category'])) { // Убедимся, что категория не пуста
            $selected_category_type = 'new';
            $product['category_custom'] = $product['category']; // Показываем текущую категорию как новую
        }
    }
} elseif (!$is_editing) {
    // Для нового товара тип категории по умолчанию 'existing'
    $selected_category_type = 'existing';
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product['title'] = trim($_POST['title'] ?? '');
    $product['discr'] = trim($_POST['description'] ?? '');
    $product['short_description'] = trim($_POST['short_description'] ?? ''); // Считываем краткое описание
    $product['price'] = trim($_POST['price'] ?? '');
    $product['stock_quantity'] = trim($_POST['stock_quantity'] ?? '');
    $product['article'] = trim($_POST['sku'] ?? '');
    $product['discount'] = trim($_POST['discount'] ?? 0);
    $product['is_hidden'] = isset($_POST['is_hidden']) ? 1 : 0;
    
    // Обрабатываем текущие изображения галереи и возможные удаления
    $current_gallery_images = !empty($product['gallery_images']) ? explode(',', $product['gallery_images']) : [];
    $final_gallery_images = [];

    if (isset($_POST['delete_gallery_image']) && is_array($_POST['delete_gallery_image'])) {
        $images_to_delete_from_server = [];
        foreach ($current_gallery_images as $img_path) {
            if (!in_array($img_path, $_POST['delete_gallery_image'])) {
                $final_gallery_images[] = $img_path;
            } else {
                // Собираем пути для физического удаления файлов
                $images_to_delete_from_server[] = $img_path;
            }
        }
        // Физическое удаление файлов
        foreach ($images_to_delete_from_server as $file_to_delete_path) {
            $full_path_to_delete = __DIR__ . '/../' . $file_to_delete_path;
            if (file_exists($full_path_to_delete) && is_writable($full_path_to_delete)) {
                unlink($full_path_to_delete);
            }
        }
    } else {
        $final_gallery_images = $current_gallery_images;
    }
    
    $selected_category_type = $_POST['category_type'] ?? 'existing';
    
    if ($selected_category_type === 'existing_text') {
        $product['category'] = trim($_POST['category_text_select'] ?? '');
    } elseif ($selected_category_type === 'new') {
        $product['category_custom'] = trim($_POST['category_custom'] ?? '');
        $product['category'] = $product['category_custom'];
    } else {
        $product['category'] = trim($_POST['category'] ?? '');
    }

    // Сохраняем текущий путь к изображению, если он есть (для режима редактирования)
    $existing_image_path = $is_editing ? $product['img'] : '';

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/product_images/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
                 $errors[] = 'Не удалось создать директорию для загрузки: ' . $upload_dir;
            }
        }

        if (empty($errors)) { // Продолжаем только если директория создана или уже существует
            $file_tmp_name = $_FILES['product_image']['tmp_name'];
            $file_name = $_FILES['product_image']['name'];
            $file_size = $_FILES['product_image']['size'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $max_file_size = 5 * 1024 * 1024; // 5 MB

            if (!in_array($file_extension, $allowed_extensions)) {
                $errors[] = "Недопустимый формат файла. Разрешены: " . implode(', ', $allowed_extensions);
            } elseif ($file_size > $max_file_size) {
                $errors[] = "Файл слишком большой. Максимальный размер: 5MB.";
            } else {
                $new_filename = uniqid('prod_', true) . '.' . $file_extension;
                $new_filepath_on_server = $upload_dir . $new_filename;
                if (move_uploaded_file($file_tmp_name, $new_filepath_on_server)) {
                    $product['img'] = 'uploads/product_images/' . $new_filename; // Относительный путь для БД
                    // Опционально: удаление старого файла, если он был и это не placeholder
                    // if (!empty($existing_image_path) && file_exists(__DIR__ . '/../' . $existing_image_path) && strpos($existing_image_path, 'placeholder') === false) {
                    //     unlink(__DIR__ . '/../' . $existing_image_path);
                    // }
                } else {
                    $errors[] = "Ошибка при перемещении загруженного файла.";
                }
            }
        }
    } else {
        // Если новый файл не загружен, оставляем старый путь (если редактирование)
        $product['img'] = $existing_image_path;
    }

    // Обработка загрузки файлов галереи
    if (isset($_FILES['gallery_images'])) {
        $gallery_upload_dir = __DIR__ . '/../uploads/gallery_images/';
        if (!is_dir($gallery_upload_dir)) {
            if (!mkdir($gallery_upload_dir, 0777, true) && !is_dir($gallery_upload_dir)) {
                $errors[] = 'Не удалось создать директорию для загрузки изображений галереи: ' . $gallery_upload_dir;
            }
        }

        if (empty($errors)) { // Продолжаем только если директория создана или уже существует
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $max_file_size = 5 * 1024 * 1024; // 5 MB

            foreach ($_FILES['gallery_images']['name'] as $key => $file_name) {
                if ($_FILES['gallery_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_tmp_name = $_FILES['gallery_images']['tmp_name'][$key];
                    $file_size = $_FILES['gallery_images']['size'][$key];
                    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (!in_array($file_extension, $allowed_extensions)) {
                        $errors[] = "Недопустимый формат файла галереи ({$file_name}). Разрешены: " . implode(', ', $allowed_extensions);
                        continue;
                    }
                    if ($file_size > $max_file_size) {
                        $errors[] = "Файл галереи ({$file_name}) слишком большой. Максимальный размер: 5MB.";
                        continue;
                    }

                    $new_gallery_filename = 'gallery_' . uniqid('', true) . '.' . $file_extension;
                    $new_gallery_filepath_on_server = $gallery_upload_dir . $new_gallery_filename;

                    if (move_uploaded_file($file_tmp_name, $new_gallery_filepath_on_server)) {
                        $final_gallery_images[] = 'uploads/gallery_images/' . $new_gallery_filename; // Относительный путь для БД
                    } else {
                        $errors[] = "Ошибка при перемещении загруженного файла галереи ({$file_name}).";
                    }
                } elseif ($_FILES['gallery_images']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                    // Сообщаем об ошибке, если это не просто отсутствие файла
                    $errors[] = "Ошибка загрузки файла галереи ({$file_name}): код " . $_FILES['gallery_images']['error'][$key];
                }
            }
        }
    }

    // Обновляем поле $product['gallery_images'] перед валидацией и сохранением
    $product['gallery_images'] = implode(',', array_filter(array_unique($final_gallery_images))); // Удаляем пустые и дубликаты

    // Валидация
    if (empty($product['title'])) $errors[] = "Название товара обязательно.";
    if (empty($product['discr'])) $errors[] = "Описание товара обязательно.";
    if (empty($product['short_description'])) $errors[] = "Краткое описание обязательно.";
    if (mb_strlen($product['short_description']) > 500) { // Пример: не более 500 символов
        $errors[] = "Краткое описание не должно превышать 500 символов.";
    }
    if (!is_numeric($product['price']) || floatval($product['price']) < 0) $errors[] = "Цена должна быть неотрицательным числом.";
    if (empty($product['category'])) $errors[] = "Категория товара обязательна.";
    if (!is_numeric($product['stock_quantity']) || intval($product['stock_quantity']) < 0) $errors[] = "Количество на складе должно быть неотрицательным числом.";
    if (!is_numeric($product['discount']) || intval($product['discount']) < 0 || intval($product['discount']) > 100) $errors[] = "Скидка должна быть числом от 0 до 100.";

    if (empty($errors)) {
        try {
            $params = [
                ':title' => $product['title'], ':discr' => $product['discr'], ':short_description' => $product['short_description'], 
                ':price' => $product['price'],
                ':category' => $product['category'], ':stock_quantity' => $product['stock_quantity'],
                ':img' => $product['img'], ':article' => $product['article'], ':discount' => $product['discount'],
                ':is_hidden' => $product['is_hidden'],
                ':gallery_images' => $product['gallery_images'] // Добавлено
            ];
            if ($is_editing) {
                $sql = "UPDATE goods SET title = :title, discr = :discr, short_description = :short_description, price = :price, category = :category, stock_quantity = :stock_quantity, img = :img, article = :article, discount = :discount, is_hidden = :is_hidden, gallery_images = :gallery_images WHERE id = :id";
                $params[':id'] = $product_id;
            } else {
                $sql = "INSERT INTO goods (title, discr, short_description, price, category, stock_quantity, img, article, discount, is_hidden, gallery_images) VALUES (:title, :discr, :short_description, :price, :category, :stock_quantity, :img, :article, :discount, :is_hidden, :gallery_images)";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success_message = $is_editing ? "Товар успешно обновлен!" : "Товар успешно добавлен!";
            $target_id_log = $is_editing ? $product_id : $pdo->lastInsertId();
            $action_log = $is_editing ? 'PRODUCT_UPDATED' : 'PRODUCT_CREATED';
            log_audit_action($action_log, $_SESSION['user_id'], 'product', $target_id_log, ['title' => $product['title']]);
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => $success_message . ($is_editing ? '' : " (ID: {$target_id_log})")];
            header("Location: manage_products.php"); exit;
        } catch (PDOException $e) {
            $errors[] = "Ошибка сохранения товара: " . $e->getMessage();
        }
    }
}

?>

<div class="container pt-0 mt-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><?php echo htmlspecialchars($page_title); ?></h4>
        <a href="manage_products.php" class="btn btn-secondary">Назад к списку товаров</a>
    </div>

    <?php if (!empty($success_message) && empty($errors)): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert">
            <strong>Обнаружены ошибки:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit_product.php<?php echo $is_editing ? '?id=' . htmlspecialchars($product_id) : ''; ?>" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5>Основная информация</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Название товара <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($product['title']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Описание <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($product['discr']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="short_description" class="form-label">Краткое описание</label>
                            <textarea class="form-control" id="short_description" name="short_description" rows="3"><?php echo htmlspecialchars($product['short_description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="product_image" class="form-label">Изображение товара</label>
                            <input type="file" class="form-control" id="product_image" name="product_image">
                            <?php if ($is_editing && !empty($product['img'])): 
                                $current_image_display_path = "/" . htmlspecialchars($product['img']);
                                $current_image_alt_text = htmlspecialchars($product['title']);
                                $current_image_filename = htmlspecialchars($product['img']);
                            ?>
                                <div class="mt-2">
                                    <small>Текущее изображение:</small><br>
                                    <img src="<?php echo $current_image_display_path; ?>" alt="<?php echo $current_image_alt_text; ?>" style="max-width: 150px; max-height: 150px; margin-top: 5px;">
                                    <p><small><?php echo $current_image_filename; ?></small></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Изображения галереи -->
                        <div class="mb-3">
                            <label for="gallery_images" class="form-label">Изображения галереи</label>
                            <input type="file" class="form-control" id="gallery_images" name="gallery_images[]" multiple>
                            <small class="form-text text-muted">Можно выбрать несколько файлов. Разрешены: jpg, jpeg, png, gif. Макс. размер: 5MB каждый.</small>
                        </div>

                        <?php 
                        $current_gallery_paths = !empty($product['gallery_images']) ? explode(',', $product['gallery_images']) : [];
                        if (!empty($current_gallery_paths) && !(count($current_gallery_paths) == 1 && empty($current_gallery_paths[0])) ): // Проверка, что массив не пуст и не содержит только одну пустую строку
                        ?>
                        <div class="mb-3">
                            <p><strong>Текущие изображения галереи:</strong></p>
                            <div class="row">
                                <?php foreach ($current_gallery_paths as $gallery_img_path): 
                                    if (empty(trim($gallery_img_path))) continue; // Пропускаем пустые пути
                                    $gallery_image_display_path = "/" . htmlspecialchars(trim($gallery_img_path));
                                    $gallery_image_filename = basename(htmlspecialchars(trim($gallery_img_path)));
                                ?>
                                <div class="col-md-3 col-sm-4 col-6 mb-3 text-center">
                                    <img src="<?php echo $gallery_image_display_path; ?>" alt="Gallery image <?php echo $gallery_image_filename; ?>" class="img-thumbnail mb-2" style="max-width: 100px; max-height: 100px; object-fit: cover;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="delete_gallery_image[]" value="<?php echo htmlspecialchars(trim($gallery_img_path)); ?>" id="delete_gallery_<?php echo md5(trim($gallery_img_path)); // Уникальный ID для label ?>">
                                        <label class="form-check-label small" for="delete_gallery_<?php echo md5(trim($gallery_img_path)); ?>">Удалить</label>
                                    </div>
                                    <p class="small text-muted" style="word-wrap: break-word;"><?php echo $gallery_image_filename; ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <!-- Конец Изображения галереи -->

                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5>Ценообразование и категория</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="price" class="form-label">Цена (руб.) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="discount" class="form-label">Скидка (%)</label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control" id="discount" name="discount" value="<?php echo htmlspecialchars($product['discount']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="sku" class="form-label">Артикул (SKU)</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($product['article']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Тип категории <span class="text-danger">*</span></label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="category_type" id="cat_type_existing" value="existing" <?php if ($selected_category_type === 'existing') echo 'checked'; ?>>
                                    <label class="form-check-label" for="cat_type_existing">Существующая (из товаров)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="category_type" id="cat_type_existing_text" value="existing_text" <?php if ($selected_category_type === 'existing_text') echo 'checked'; ?>>
                                    <label class="form-check-label" for="cat_type_existing_text">Существующая (текстовая)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="category_type" id="cat_type_new" value="new" <?php if ($selected_category_type === 'new') echo 'checked'; ?>>
                                    <label class="form-check-label" for="cat_type_new">Новая</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3" id="category_select_div">
                            <label for="category" class="form-label">Категория (из товаров) <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category">
                                <option value="">-- Выберите категорию --</option>
                                <?php foreach ($categories as $cat_item): ?>
                                    <option value="<?php echo htmlspecialchars($cat_item); ?>" <?php if ($product['category'] === $cat_item && $selected_category_type === 'existing') echo 'selected'; ?>><?php echo htmlspecialchars($cat_item); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="category_text_select_div" style="display: none;">
                            <label for="category_text_select" class="form-label">Категория (текстовая) <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_text_select" name="category_text_select">
                                <option value="">-- Выберите текстовую категорию --</option>
                                <?php foreach ($text_categories as $txt_cat_item): ?>
                                    <option value="<?php echo htmlspecialchars($txt_cat_item); ?>" <?php if ($product['category'] === $txt_cat_item && $selected_category_type === 'existing_text') echo 'selected'; ?>><?php echo htmlspecialchars($txt_cat_item); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="category_custom_div" style="display: none;">
                            <label for="category_custom" class="form-label">Новая категория <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="category_custom" name="category_custom" value="<?php echo htmlspecialchars($product['category_custom']); ?>">
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5>Запасы и видимость</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="stock_quantity" class="form-label">Количество на складе <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" required>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_hidden" name="is_hidden" value="1" <?php echo $product['is_hidden'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_hidden">
                                Скрыть товар (не будет отображаться на сайте)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-0 mb-4">
            <button type="submit" class="btn btn-primary-blue btn-lg"><i class="bi bi-save-fill"></i> <?php echo $is_editing ? 'Сохранить изменения' : 'Добавить товар'; ?></button>
            <a href="manage_products.php" class="btn btn-secondary btn-lg">Отмена</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryTypeRadios = document.querySelectorAll('input[name="category_type"]');
    const categorySelectDiv = document.getElementById('category_select_div');
    const categoryCustomDiv = document.getElementById('category_custom_div');
    const categoryTextSelectDiv = document.getElementById('category_text_select_div');
    const categorySelect = document.getElementById('category');
    const categoryTextSelect = document.getElementById('category_text_select');
    const categoryCustomInput = document.getElementById('category_custom');

    function toggleCategoryFields() {
        let selectedType = null;
        const checkedRadio = document.querySelector('input[name="category_type"]:checked');
        if (checkedRadio) {
            selectedType = checkedRadio.value;
        }

        categorySelectDiv.style.display = 'none';
        categoryTextSelectDiv.style.display = 'none';
        categoryCustomDiv.style.display = 'none';
        categorySelect.required = false;
        categoryTextSelect.required = false;
        categoryCustomInput.required = false;

        if (selectedType === 'existing') {
            categorySelectDiv.style.display = 'block';
            categorySelect.required = true;
        } else if (selectedType === 'existing_text') {
            categoryTextSelectDiv.style.display = 'block';
            categoryTextSelect.required = true;
        } else if (selectedType === 'new') {
            categoryCustomDiv.style.display = 'block';
            categoryCustomInput.required = true;
        }
        
        if (selectedType !== 'existing') categorySelect.value = '';
        if (selectedType !== 'existing_text') categoryTextSelect.value = '';
        if (selectedType !== 'new') categoryCustomInput.value = '';
    }

    categoryTypeRadios.forEach(radio => {
        radio.addEventListener('change', toggleCategoryFields);
    });

    const initialCategoryTypeValue = <?php echo json_encode($selected_category_type); ?>;
    const isEditing = <?php echo json_encode($is_editing); ?>;
    let radioToSelect = document.querySelector('input[name="category_type"][value="' + initialCategoryTypeValue + '"]');
    
    if (!radioToSelect) {
        if (!isEditing) {
             radioToSelect = document.getElementById('cat_type_existing');
        } else {
            if (!initialCategoryTypeValue) {
                 radioToSelect = document.getElementById('cat_type_existing');
            }
        }
    }

    if (radioToSelect) {
        radioToSelect.checked = true;
    } else {
        const defaultRadio = document.getElementById('cat_type_existing');
        if (defaultRadio) defaultRadio.checked = true;
    }
    toggleCategoryFields(); 
});
</script>

<?php include_once 'footer.php'; ?> 