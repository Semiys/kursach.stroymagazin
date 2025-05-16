<?php
session_start(); // Начинаем или возобновляем сессию

// Подключаем файл конфигурации для доступа к $pdo
require_once __DIR__ . '/../config.php'; 

// Проверяем, залогинен ли пользователь и является ли он администратором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php"); // Перенаправляем на главную сайта
    exit;
}

// Если проверки пройдены, пользователь является администратором
// Подключаем заголовок
include_once 'header.php';

// Получаем товары из базы данных
$products = [];
$error_message = '';

try {
    // TODO: Добавить пагинацию позже
    $stmt = $pdo->query("SELECT id, title, category, price, stock_quantity, discount, is_hidden FROM goods ORDER BY id DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Ошибка при загрузке товаров: " . $e->getMessage();
    // Можно залогировать ошибку, если у вас есть система логирования
    // error_log($error_message);
}

?>

<div class="container pt-0 mt-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Управление товарами</h4>
        <a href="edit_product.php" class="btn btn-primary-blue">Добавить новый товар</a> <!-- edit_product.php будет и для создания, и для редактирования -->
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($products) && empty($error_message)): ?>
        <div class="alert alert-info" role="alert">
            Товары пока не добавлены.
        </div>
    <?php elseif (!empty($products)): ?>
        <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;"> 
            <table class="table table-striped table-bordered">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Категория</th>
                        <th>Цена</th>
                        <th>На складе</th>
                        <th>Скидка (%)</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr <?php echo $product['is_hidden'] ? 'class="table-secondary"' : ''; ?>>
                            <td><?php echo htmlspecialchars($product['id']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($product['title']); ?>
                                <?php if ($product['is_hidden']): ?>
                                    <span class="badge bg-secondary ms-1">Скрыт</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td><?php echo htmlspecialchars(number_format($product['price'], 2, '.', ' ')); ?>₽</td>
                            <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                            <td><?php echo htmlspecialchars($product['discount'] ?? 0); ?>%</td>
                            <td>
                                <?php if ($product['is_hidden']): ?>
                                    <span class="text-muted">Скрыт</span>
                                <?php else: ?>
                                    <span class="text-success">Виден</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm mb-1">Редактировать</a>
                                <?php if ($product['is_hidden']): ?>
                                    <button type="button" class="btn btn-success btn-sm mb-1 show-product-btn" data-product-id="<?php echo $product['id']; ?>" data-product-title="<?php echo htmlspecialchars($product['title']); ?>">
                                        <i class="bi bi-eye-fill"></i> Показать
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-sm mb-1 hide-product-btn" data-product-id="<?php echo $product['id']; ?>" data-product-title="<?php echo htmlspecialchars($product['title']); ?>">
                                        <i class="bi bi-eye-slash-fill"></i> Скрыть
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<!-- Модальное окно: Подтверждение действия Скрыть/Показать Товар -->
<div class="modal fade" id="confirmProductActionModal" tabindex="-1" aria-labelledby="confirmProductActionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmProductActionModalLabel">Подтвердите действие</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmProductActionModalText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="confirmProductActionModalButton">Подтвердить</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast уведомления (если еще не подключен глобально, можно взять из manage_product_categories_text.php или создать отдельный) -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1056;">
    <div id="productActionToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Уведомление</strong>
            <small>Только что</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"></div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const productActionToastElement = document.getElementById('productActionToast');
    const productActionToast = new bootstrap.Toast(productActionToastElement, { delay: 4000 });

    function showProductActionToast(message, type = 'success') {
        const toastBody = productActionToastElement.querySelector('.toast-body');
        toastBody.textContent = message;
        toastBody.classList.remove('text-danger', 'text-success');
        toastBody.classList.add(type === 'error' ? 'text-danger' : 'text-success');
        productActionToast.show();
    }

    const confirmModalElement = document.getElementById('confirmProductActionModal');
    const confirmModal = new bootstrap.Modal(confirmModalElement);
    const modalLabel = document.getElementById('confirmProductActionModalLabel');
    const modalText = document.getElementById('confirmProductActionModalText');
    const confirmButton = document.getElementById('confirmProductActionModalButton');

    let currentProductId = null;
    let currentActionType = null; // 'hide' or 'show'
    let currentButtonElement = null; // Store the button that was clicked

    document.querySelectorAll('.hide-product-btn, .show-product-btn').forEach(button => {
        button.addEventListener('click', function () {
            currentButtonElement = this; // Store the clicked button
            currentProductId = this.dataset.productId;
            const productTitle = this.dataset.productTitle;
            currentActionType = this.classList.contains('hide-product-btn') ? 'hide' : 'show';

            if (currentActionType === 'hide') {
                modalLabel.textContent = 'Скрыть товар?';
                modalText.textContent = `Вы уверены, что хотите скрыть товар "${productTitle}" (ID: ${currentProductId}) от пользователей?`;
                confirmButton.textContent = 'Да, скрыть';
                confirmButton.className = 'btn btn-danger'; // Or btn-warning
            } else {
                modalLabel.textContent = 'Показать товар?';
                modalText.textContent = `Вы уверены, что хотите снова показать товар "${productTitle}" (ID: ${currentProductId}) для всех пользователей?`;
                confirmButton.textContent = 'Да, показать';
                confirmButton.className = 'btn btn-success';
            }
            confirmModal.show();
        });
    });

    confirmButton.addEventListener('click', function () {
        if (!currentProductId || !currentActionType || !currentButtonElement) return;

        const originalButtonText = this.textContent; // Store original text for the modal button
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Выполнение...';

        const formData = new FormData();
        formData.append('product_id', currentProductId);

        const ajaxUrl = currentActionType === 'hide' ? 'ajax_hide_product.php' : 'ajax_show_product.php';

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showProductActionToast(data.message || 'Действие успешно выполнено!', 'success');
                confirmModal.hide();

                // --- DYNAMIC UI UPDATE ---
                const row = currentButtonElement.closest('tr');
                if (row) {
                    const titleCell = row.cells[1]; // ID, Title, Category, Price, Stock, Discount, Status, Actions
                    const statusCell = row.cells[6]; // Status cell

                    if (currentActionType === 'hide') {
                        row.classList.add('table-secondary');
                        // Add 'Скрыт' badge to title cell if not present
                        if (!titleCell.querySelector('.badge.bg-secondary')) {
                            titleCell.insertAdjacentHTML('beforeend', ' <span class="badge bg-secondary ms-1">Скрыт</span>');
                        }
                        statusCell.innerHTML = '<span class="text-muted">Скрыт</span>';

                        // Change button to "Show"
                        currentButtonElement.innerHTML = '<i class="bi bi-eye-fill"></i> Показать';
                        currentButtonElement.classList.remove('btn-secondary', 'hide-product-btn');
                        currentButtonElement.classList.add('btn-success', 'show-product-btn');
                        // currentActionType = 'show'; // Update for next potential click - this logic needs to be tied to the actual class change
                    } else { // Action was 'show'
                        row.classList.remove('table-secondary');
                        // Remove 'Скрыт' badge from title cell
                        const badge = titleCell.querySelector('.badge.bg-secondary');
                        if (badge) {
                            badge.remove();
                        }
                        statusCell.innerHTML = '<span class="text-success">Виден</span>';

                        // Change button to "Hide"
                        currentButtonElement.innerHTML = '<i class="bi bi-eye-slash-fill"></i> Скрыть';
                        currentButtonElement.classList.remove('btn-success', 'show-product-btn');
                        currentButtonElement.classList.add('btn-secondary', 'hide-product-btn');
                        // currentActionType = 'hide'; // Update for next potential click
                    }
                     // Update the data-product-action attribute or re-evaluate based on classes for the next click
                    if(currentButtonElement.classList.contains('show-product-btn')){
                        currentActionType = 'show';
                    } else {
                        currentActionType = 'hide';
                    }
                }
            } else {
                showProductActionToast(data.error || 'Ошибка выполнения действия.', 'error');
                confirmModal.hide(); // Hide modal on error too
            }
        })
        .catch(error => {
            console.error('Action error:', error);
            showProductActionToast('Сетевая ошибка или ошибка выполнения.', 'error');
            confirmModal.hide(); // Hide modal on catch too
        })
        .finally(() => {
            // Restore modal button state
            this.disabled = false;
            this.innerHTML = originalButtonText;
            // currentProductId = null; // Keep for potential sequential clicks if modal stays open
            // currentActionType = null;
            // currentButtonElement = null;
        });
    });
});
</script>

<?php 
// Подключаем подвал
include_once 'footer.php'; 
?> 