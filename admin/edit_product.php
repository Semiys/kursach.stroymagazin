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
    'description' => '',
    'price' => '',
    'category' => '',
    'category_custom' => '', // Для новой категории
    'stock_quantity' => '',
    'image_url' => '', // Предполагаем, что это URL или путь к файлу
    'sku' => '', // Артикул
    'discount' => 0,
    'is_hidden' => 0,
    'characteristics' => '[]' // JSON строка характеристик
];

$errors = [];
$success_message = '';

// Получение существующих категорий для выпадающего списка
$categories = [];
$text_categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM goods WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt_text_cat = $pdo->query("SELECT name FROM text_categories ORDER BY name ASC");
    $text_categories = $stmt_text_cat->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    $errors[] = "Ошибка загрузки категорий: " . $e->getMessage();
}

// Если редактируем, загружаем данные товара
if ($is_editing) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM goods WHERE id = ?");
        $stmt->execute([$product_id]);
        $loaded_product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($loaded_product) {
            $product = array_merge($product, $loaded_product);
            // Если категория товара не является текстовой, она будет выбрана в select
            // Если она текстовая, нужно будет обработать это отдельно или добавить механизм
        } else {
            $errors[] = "Товар с ID {$product_id} не найден.";
            $is_editing = false; // Сбрасываем режим редактирования, если товар не найден
        }
    } catch (PDOException $e) {
        $errors[] = "Ошибка загрузки товара: " . $e->getMessage();
    }
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получение и очистка данных из формы
    $product['title'] = trim($_POST['title'] ?? '');
    $product['description'] = trim($_POST['description'] ?? '');
    $product['price'] = trim($_POST['price'] ?? '');
    $product['stock_quantity'] = trim($_POST['stock_quantity'] ?? '');
    $product['image_url'] = trim($_POST['image_url'] ?? '');
    $product['sku'] = trim($_POST['sku'] ?? '');
    $product['discount'] = trim($_POST['discount'] ?? 0);
    $product['is_hidden'] = isset($_POST['is_hidden']) ? 1 : 0;
    
    $selected_category_type = $_POST['category_type'] ?? 'existing';
    
    if ($selected_category_type === 'existing_text') {
        $product['category'] = trim($_POST['category_text_select'] ?? '');
    } elseif ($selected_category_type === 'new') {
        $product['category_custom'] = trim($_POST['category_custom'] ?? '');
        $product['category'] = $product['category_custom']; // Устанавливаем новую категорию
    } else { // existing or default
        $product['category'] = trim($_POST['category'] ?? '');
    }

    // Характеристики
    $characteristics = [];
    if (isset($_POST['char_name']) && is_array($_POST['char_name'])) {
        foreach ($_POST['char_name'] as $key => $name) {
            $name = trim($name);
            $value = trim($_POST['char_value'][$key] ?? '');
            if (!empty($name) && !empty($value)) {
                $characteristics[] = ['name' => $name, 'value' => $value];
            }
        }
    }
    $product['characteristics'] = json_encode($characteristics, JSON_UNESCAPED_UNICODE);

    // Валидация
    if (empty($product['title'])) $errors[] = "Название товара обязательно.";
    if (empty($product['description'])) $errors[] = "Описание товара обязательно.";
    if (!is_numeric($product['price']) || $product['price'] < 0) $errors[] = "Цена должна быть положительным числом.";
    if (empty($product['category'])) $errors[] = "Категория товара обязательна.";
    if (!is_numeric($product['stock_quantity']) || $product['stock_quantity'] < 0) $errors[] = "Количество на складе должно быть неотрицательным числом.";
    if (!empty($product['image_url']) && !filter_var($product['image_url'], FILTER_VALIDATE_URL) && !file_exists(__DIR__ . '/../' . $product['image_url'])) {
        // Проверяем как URL и как локальный путь относительно корня сайта
        // $errors[] = "URL изображения некорректен или файл не найден.";
        // Пока что не будем жестко проверять URL, т.к. может быть относительный путь
    }
    if (!is_numeric($product['discount']) || $product['discount'] < 0 || $product['discount'] > 100) $errors[] = "Скидка должна быть числом от 0 до 100.";

    // Если нет ошибок, сохраняем в базу
    if (empty($errors)) {
        try {
            if ($is_editing) {
                $sql = "UPDATE goods SET title = ?, description = ?, price = ?, category = ?, stock_quantity = ?, image_url = ?, sku = ?, discount = ?, is_hidden = ?, characteristics = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $product['title'], $product['description'], $product['price'], $product['category'], 
                    $product['stock_quantity'], $product['image_url'], $product['sku'], 
                    $product['discount'], $product['is_hidden'], $product['characteristics'],
                    $product_id
                ]);
                $success_message = "Товар успешно обновлен!";
                
                log_audit_action(
                    action: 'PRODUCT_UPDATED', 
                    user_id: $_SESSION['user_id'], 
                    target_type: 'product', 
                    target_id: $product_id, 
                    details: ['title' => $product['title']]
                );
                
                 $_SESSION['flash_message'] = ['type' => 'success', 'text' => $success_message];
                 header("Location: manage_products.php"); exit;

            } else {
                $sql = "INSERT INTO goods (title, description, price, category, stock_quantity, image_url, sku, discount, is_hidden, characteristics, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $product['title'], $product['description'], $product['price'], $product['category'], 
                    $product['stock_quantity'], $product['image_url'], $product['sku'], 
                    $product['discount'], $product['is_hidden'], $product['characteristics']
                ]);
                $new_product_id = $pdo->lastInsertId();
                $success_message = "Товар успешно добавлен!";

                log_audit_action(
                    action: 'PRODUCT_CREATED', 
                    user_id: $_SESSION['user_id'], 
                    target_type: 'product', 
                    target_id: $new_product_id, 
                    details: ['title' => $product['title']]
                );

                $_SESSION['flash_message'] = ['type' => 'success', 'text' => $success_message . " (ID: {$new_product_id})"];
                header("Location: manage_products.php"); exit;
            }
            // После успешного добавления/обновления можно перенаправить пользователя
            // header("Location: manage_products.php"); exit;
        } catch (PDOException $e) {
            $errors[] = "Ошибка сохранения товара: " . $e->getMessage();
        }
    }
}

$current_characteristics = json_decode($product['characteristics'], true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($current_characteristics)) {
    $current_characteristics = []; // Если JSON невалидный или не массив, инициализируем пустым массивом
}

?>

<div class="container pt-0 mt-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><?php echo $page_title; ?></h4>
        <a href="manage_products.php" class="btn btn-secondary">Назад к списку товаров</a>
    </div>

    <?php if (!empty($success_message)): ?>
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

    <form method="POST" action="edit_product.php<?php echo $is_editing ? '?id=' . $product_id : ''; ?>" enctype="multipart/form-data">
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
                            <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="image_url" class="form-label">URL изображения (или путь)</label>
                            <input type="text" class="form-control" id="image_url" name="image_url" value="<?php echo htmlspecialchars($product['image_url']); ?>">
                            <!-- TODO: Загрузка файла изображения -->
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Характеристики</label>
                            <div id="characteristics-container">
                                <?php if (empty($current_characteristics)): ?>
                                    <div class="row gx-2 mb-2 characteristic-entry">
                                        <div class="col">
                                            <input type="text" name="char_name[]" class="form-control form-control-sm" placeholder="Название характеристики">
                                        </div>
                                        <div class="col">
                                            <input type="text" name="char_value[]" class="form-control form-control-sm" placeholder="Значение">
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-sm btn-danger remove-char-btn" disabled><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($current_characteristics as $index => $char): ?>
                                        <div class="row gx-2 mb-2 characteristic-entry">
                                            <div class="col">
                                                <input type="text" name="char_name[]" class="form-control form-control-sm" placeholder="Название характеристики" value="<?php echo htmlspecialchars($char['name']); ?>">
                                            </div>
                                            <div class="col">
                                                <input type="text" name="char_value[]" class="form-control form-control-sm" placeholder="Значение" value="<?php echo htmlspecialchars($char['value']); ?>">
                                            </div>
                                            <div class="col-auto">
                                                <button type="button" class="btn btn-sm btn-danger remove-char-btn" <?php echo $index === 0 && count($current_characteristics) === 1 ? 'disabled' : ''; ?>><i class="bi bi-trash"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" id="add-char-btn" class="btn btn-sm btn-outline-secondary mt-2"><i class="bi bi-plus-circle"></i> Добавить характеристику</button>
                        </div>

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
                            <input type="number" step="0.1" min="0" max="100" class="form-control" id="discount" name="discount" value="<?php echo htmlspecialchars($product['discount']); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="sku" class="form-label">Артикул (SKU)</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Тип категории <span class="text-danger">*</span></label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="category_type" id="cat_type_existing" value="existing" checked>
                                    <label class="form-check-label" for="cat_type_existing">Существующая (из товаров)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="category_type" id="cat_type_existing_text" value="existing_text">
                                    <label class="form-check-label" for="cat_type_existing_text">Существующая (текстовая)</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="category_type" id="cat_type_new" value="new">
                                    <label class="form-check-label" for="cat_type_new">Новая</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3" id="category_select_div">
                            <label for="category" class="form-label">Категория (из товаров) <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category">
                                <option value="">-- Выберите категорию --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($product['category'] === $cat && $selected_category_type !== 'existing_text' && $selected_category_type !== 'new') ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="category_text_select_div" style="display: none;">
                            <label for="category_text_select" class="form-label">Категория (текстовая) <span class="text-danger">*</span></label>
                            <select class="form-select" id="category_text_select" name="category_text_select">
                                <option value="">-- Выберите текстовую категорию --</option>
                                <?php foreach ($text_categories as $txt_cat): ?>
                                    <option value="<?php echo htmlspecialchars($txt_cat); ?>" <?php echo ($product['category'] === $txt_cat && $selected_category_type === 'existing_text') ? 'selected' : ''; ?>><?php echo htmlspecialchars($txt_cat); ?></option>
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
    // Логика для переключения полей категории
    const categoryTypeRadios = document.querySelectorAll('input[name="category_type"]');
    const categorySelectDiv = document.getElementById('category_select_div');
    const categoryCustomDiv = document.getElementById('category_custom_div');
    const categoryTextSelectDiv = document.getElementById('category_text_select_div');

    const categorySelect = document.getElementById('category');
    const categoryTextSelect = document.getElementById('category_text_select');
    const categoryCustomInput = document.getElementById('category_custom');

    function toggleCategoryFields() {
        const selectedType = document.querySelector('input[name="category_type"]:checked').value;
        categorySelectDiv.style.display = (selectedType === 'existing') ? 'block' : 'none';
        categoryTextSelectDiv.style.display = (selectedType === 'existing_text') ? 'block' : 'none';
        categoryCustomDiv.style.display = (selectedType === 'new') ? 'block' : 'none';

        // Очищаем или устанавливаем required в зависимости от видимости
        categorySelect.required = (selectedType === 'existing');
        categoryTextSelect.required = (selectedType === 'existing_text');
        categoryCustomInput.required = (selectedType === 'new');
        
        // Сброс значений других полей при переключении, чтобы не отправлять лишнее
        if (selectedType !== 'existing') categorySelect.value = '';
        if (selectedType !== 'existing_text') categoryTextSelect.value = '';
        if (selectedType !== 'new') categoryCustomInput.value = '';
    }

    categoryTypeRadios.forEach(radio => radio.addEventListener('change', toggleCategoryFields));
    toggleCategoryFields(); // Инициализация при загрузке

    // Динамическое добавление/удаление характеристик
    const characteristicsContainer = document.getElementById('characteristics-container');
    const addCharBtn = document.getElementById('add-char-btn');

    function createCharacteristicEntry() {
        const entry = document.createElement('div');
        entry.className = 'row gx-2 mb-2 characteristic-entry';
        entry.innerHTML = `
            <div class="col">
                <input type="text" name="char_name[]" class="form-control form-control-sm" placeholder="Название характеристики">
            </div>
            <div class="col">
                <input type="text" name="char_value[]" class="form-control form-control-sm" placeholder="Значение">
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-danger remove-char-btn"><i class="bi bi-trash"></i></button>
            </div>
        `;
        return entry;
    }
    
    function updateRemoveButtonsState() {
        const entries = characteristicsContainer.querySelectorAll('.characteristic-entry');
        entries.forEach((entry, index) => {
            const removeBtn = entry.querySelector('.remove-char-btn');
            if (removeBtn) {
                removeBtn.disabled = (entries.length === 1);
            }
        });
    }

    addCharBtn.addEventListener('click', function() {
        characteristicsContainer.appendChild(createCharacteristicEntry());
        updateRemoveButtonsState();
    });

    characteristicsContainer.addEventListener('click', function(event) {
        if (event.target.closest('.remove-char-btn')) {
            const entry = event.target.closest('.characteristic-entry');
            if (characteristicsContainer.querySelectorAll('.characteristic-entry').length > 1) {
                entry.remove();
                updateRemoveButtonsState();
            } 
        }
    });
    updateRemoveButtonsState(); // Первичная установка состояния кнопок
});
</script>

<?php include_once 'footer.php'; ?> 