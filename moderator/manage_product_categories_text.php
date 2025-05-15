<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'moder') {
    header("Location: /index.php");
    exit;
}

$page_title = "Управление категориями товаров (текст)";
include_once 'header.php'; // Предполагается, что header.php корректно подключит Bootstrap и общие стили

$text_categories = [];
$error_message = '';
$success_message = '';

if (isset($_SESSION['flash_message'])) {
    if ($_SESSION['flash_message']['type'] === 'success') {
        $success_message = $_SESSION['flash_message']['text'];
    } else {
        $error_message = $_SESSION['flash_message']['text'];
    }
    unset($_SESSION['flash_message']);
}

try {
    $stmt = $pdo->query("
        SELECT 
            g.category as category_name,
            COUNT(g.id) as product_count,
            hc.category_name IS NOT NULL as is_hidden
        FROM goods g
        LEFT JOIN hidden_categories hc ON g.category = hc.category_name
        WHERE g.category IS NOT NULL AND g.category != ''
        GROUP BY g.category
        ORDER BY g.category ASC
    ");
    $text_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Ошибка при загрузке названий категорий: " . $e->getMessage();
}

?>

<div class="container pt-0 mt-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><?php echo htmlspecialchars($page_title); ?></h4>
        <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#checkTextCategoryNameModal">
            <i class="bi bi-search"></i> Проверить название
        </button>
    </div>
    <p class="text-muted small">Категории генерируются на основе текстовых значений в поле 'category' ваших товаров.</p>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($text_categories) && empty($error_message)): ?>
        <div class="alert alert-info" role="alert">
            Используемых названий категорий пока нет. Они появятся здесь после того, как вы присвоите их товарам.
        </div>
    <?php elseif (!empty($text_categories)): ?>
        <div class="table-responsive" style="max-height: 75vh; overflow-y: auto;">
            <table class="table table-striped table-bordered">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>Название категории</th>
                        <th>Кол-во товаров</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($text_categories as $tc): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($tc['category_name']); ?>
                                <?php if ($tc['is_hidden']): ?>
                                    <span class="badge bg-secondary ms-2">Скрыта</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($tc['product_count']); ?></td>
                            <td>
                                <?php if ($tc['is_hidden']): ?>
                                    <span class="text-muted">Скрыта от пользователей</span>
                                <?php else: ?>
                                    <span class="text-success">Видна всем</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-warning btn-sm mb-1 rename-text-category-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#renameTextCategoryModal"
                                        data-old-category-name="<?php echo htmlspecialchars($tc['category_name']); ?>">
                                    <i class="bi bi-pencil-fill"></i> Переименовать
                                </button>
                                <?php if ($tc['is_hidden']): ?>
                                    <button type="button" class="btn btn-success btn-sm mb-1 show-text-category-btn"
                                            data-category-name-to-show="<?php echo htmlspecialchars($tc['category_name']); ?>">
                                        <i class="bi bi-eye-fill"></i> Показать для всех
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-sm mb-1 hide-text-category-btn"
                                            data-category-name-to-hide="<?php echo htmlspecialchars($tc['category_name']); ?>">
                                        <i class="bi bi-eye-slash-fill"></i> Скрыть от пользователей
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

<!-- Модальное окно: Проверить название категории -->
<div class="modal fade" id="checkTextCategoryNameModal" tabindex="-1" aria-labelledby="checkTextCategoryNameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="checkTextCategoryNameModalLabel">Проверить название категории</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="checkTextCategoryNameForm">
                    <div class="mb-3">
                        <label for="checkCategoryNameInput" class="form-label">Название категории <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="checkCategoryNameInput" name="category_name_to_check" required>
                    </div>
                    <div id="checkTextCategoryNameInfo" class="form-text mb-2"></div>
                    <div id="checkTextCategoryNameError" class="text-danger mb-2"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="submitCheckCategoryNameBtn">Проверить</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно: Переименовать категорию -->
<div class="modal fade" id="renameTextCategoryModal" tabindex="-1" aria-labelledby="renameTextCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renameTextCategoryModalLabel">Переименовать категорию</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="renameTextCategoryForm">
                    <input type="hidden" id="renameOldCategoryName" name="old_category_name">
                    <div class="mb-3">
                        <label class="form-label">Текущее название:</label>
                        <p><strong id="currentCategoryNameDisplay"></strong></p>
                    </div>
                    <div class="mb-3">
                        <label for="renameNewCategoryNameInput" class="form-label">Новое название категории <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="renameNewCategoryNameInput" name="new_category_name" required>
                    </div>
                    <div id="renameTextCategoryError" class="text-danger mb-2"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveRenameTextCategoryBtn">Сохранить изменения</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно: Подтверждение действия Скрыть/Показать -->
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-labelledby="confirmActionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmActionModalLabel">Подтвердите действие</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmActionModalText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="confirmActionModalButton">Подтвердить</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast уведомления -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1056;">
    <div id="textCategoryToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
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
    const textCategoryToastElement = document.getElementById('textCategoryToast');
    const textCategoryToast = new bootstrap.Toast(textCategoryToastElement, { delay: 4000 });

    function showTextCategoryToast(message, type = 'success') {
        const toastBody = textCategoryToastElement.querySelector('.toast-body');
        toastBody.textContent = message;
        toastBody.classList.remove('text-danger', 'text-info', 'text-warning', 'text-success');
        if (type === 'error') {
            toastBody.classList.add('text-danger');
        } else if (type === 'info') {
            toastBody.classList.add('text-info');
        } else if (type === 'warning') {
            toastBody.classList.add('text-warning');
        } else {
            toastBody.classList.add('text-success');
        }
        textCategoryToast.show();
    }

    // --- CHECK CATEGORY NAME --- //
    const checkTextCategoryNameModalElement = document.getElementById('checkTextCategoryNameModal');
    const checkTextCategoryNameModal = new bootstrap.Modal(checkTextCategoryNameModalElement);
    const checkCategoryNameInput = document.getElementById('checkCategoryNameInput');
    const checkInfoDiv = document.getElementById('checkTextCategoryNameInfo');
    const checkErrorDiv = document.getElementById('checkTextCategoryNameError');
    const submitCheckBtn = document.getElementById('submitCheckCategoryNameBtn');

    submitCheckBtn.addEventListener('click', function() {
        const categoryName = checkCategoryNameInput.value.trim();
        checkErrorDiv.textContent = '';
        checkInfoDiv.textContent = '';
        checkInfoDiv.classList.remove('text-warning', 'text-success', 'text-info');

        if (!categoryName) {
            checkErrorDiv.textContent = 'Название категории не может быть пустым.';
            return;
        }

        this.disabled = true;
        const originalButtonText = this.textContent;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Проверка...';

        const formData = new FormData();
        formData.append('category_name', categoryName); // Changed from category_name_to_check to match AJAX script

        fetch('ajax_validate_new_category_name.php', { 
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                checkInfoDiv.textContent = data.message;
                if (data.exists) {
                    checkInfoDiv.classList.add('text-warning');
                } else {
                    checkInfoDiv.classList.add('text-success');
                }
            } else {
                checkErrorDiv.textContent = data.error || 'Не удалось проверить название.';
            }
        })
        .catch(error => {
            console.error('Error checking category name:', error);
            checkErrorDiv.textContent = 'Произошла сетевая ошибка.';
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = originalButtonText;
        });
    });
    
    checkTextCategoryNameModalElement.addEventListener('hidden.bs.modal', function () {
        checkErrorDiv.textContent = '';
        checkInfoDiv.textContent = '';
        checkCategoryNameInput.form.reset();
    });

    // --- RENAME CATEGORY --- //
    const renameTextCategoryModalElement = document.getElementById('renameTextCategoryModal');
    const renameTextCategoryModal = new bootstrap.Modal(renameTextCategoryModalElement);
    const saveRenameTextCategoryBtn = document.getElementById('saveRenameTextCategoryBtn');
    const renameOldCategoryNameInput = document.getElementById('renameOldCategoryName');
    const renameNewCategoryNameInputEl = document.getElementById('renameNewCategoryNameInput');
    const currentCategoryNameDisplay = document.getElementById('currentCategoryNameDisplay');
    const renameTextCategoryErrorDiv = document.getElementById('renameTextCategoryError');

    document.querySelectorAll('.rename-text-category-btn').forEach(button => {
        button.addEventListener('click', function() {
            const oldCategoryName = this.dataset.oldCategoryName;
            renameOldCategoryNameInput.value = oldCategoryName;
            currentCategoryNameDisplay.textContent = oldCategoryName; 
            renameNewCategoryNameInputEl.value = oldCategoryName; 
            renameTextCategoryErrorDiv.textContent = '';
        });
    });

    saveRenameTextCategoryBtn.addEventListener('click', function() {
        const oldName = renameOldCategoryNameInput.value;
        const newName = renameNewCategoryNameInputEl.value.trim();
        renameTextCategoryErrorDiv.textContent = '';

        if (!newName) {
            renameTextCategoryErrorDiv.textContent = 'Новое название категории не может быть пустым.';
            return;
        }
        if (newName === oldName) {
            renameTextCategoryErrorDiv.textContent = 'Новое название совпадает со старым.';
            return;
        }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Переименование...';

        const formData = new FormData();
        formData.append('old_category_name', oldName);
        formData.append('new_category_name', newName);

        fetch('ajax_rename_category_in_goods.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renameTextCategoryModal.hide();
                showTextCategoryToast(data.message || 'Категория успешно переименована!', 'success');
                setTimeout(() => location.reload(), 1500); 
            } else {
                renameTextCategoryErrorDiv.textContent = data.error || 'Не удалось переименовать категорию.';
            }
        })
        .catch(error => {
            console.error('Error renaming category:', error);
            renameTextCategoryErrorDiv.textContent = 'Произошла сетевая ошибка.';
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = 'Сохранить изменения';
        });
    });

    // --- HIDE/SHOW CATEGORY (using a single confirmation modal) --- //
    const confirmActionModalElement = document.getElementById('confirmActionModal');
    const confirmActionModal = new bootstrap.Modal(confirmActionModalElement);
    const confirmActionModalText = document.getElementById('confirmActionModalText');
    const confirmActionModalButton = document.getElementById('confirmActionModalButton');
    let currentCategoryNameForAction = null;
    let currentActionType = null; // 'hide' or 'show'
    let currentButtonElement = null; // Store the button that was clicked

    document.querySelectorAll('.hide-text-category-btn, .show-text-category-btn').forEach(button => {
        button.addEventListener('click', function() {
            currentButtonElement = this; // Store the clicked button
            currentCategoryNameForAction = this.dataset.categoryNameToHide || this.dataset.categoryNameToShow;
            currentActionType = this.dataset.categoryNameToHide ? 'hide' : 'show';
            
            if (currentActionType === 'hide') {
                confirmActionModalText.textContent = `Вы уверены, что хотите скрыть категорию "${currentCategoryNameForAction}" от пользователей? Товары этой категории останутся, но категория не будет видна в фильтрах и на карточках товаров.`;
                confirmActionModalButton.className = 'btn btn-danger'; // Or btn-warning
                confirmActionModalButton.textContent = 'Да, скрыть';
            } else {
                confirmActionModalText.textContent = `Вы уверены, что хотите снова показать категорию "${currentCategoryNameForAction}" для всех пользователей?`;
                confirmActionModalButton.className = 'btn btn-success';
                confirmActionModalButton.textContent = 'Да, показать';
            }
            confirmActionModal.show();
        });
    });

    confirmActionModalButton.addEventListener('click', function() {
        if (!currentCategoryNameForAction || !currentActionType || !currentButtonElement) return;

        const originalConfirmButtonText = this.textContent; // Store original text
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Выполнение...';

        const actionUrl = currentActionType === 'hide' ? 'ajax_hide_category.php' : 'ajax_show_category.php';
        const params = new URLSearchParams();
        params.append('category_name', currentCategoryNameForAction);

        fetch(actionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTextCategoryToast(data.message || 'Действие успешно выполнено.', 'success');
                
                // --- DYNAMIC UI UPDATE (from previous correct implementation) ---
                const row = currentButtonElement.closest('tr');
                if (row) {
                    const statusCell = row.cells[2]; 
                    const categoryNameCell = row.cells[0]; 
                    
                    if (currentActionType === 'hide') {
                        statusCell.innerHTML = '<span class="text-muted">Скрыта от пользователей</span>';
                        let badge = categoryNameCell.querySelector('.badge.bg-secondary');
                        if (!badge) {
                            categoryNameCell.insertAdjacentHTML('beforeend', ' <span class="badge bg-secondary ms-2">Скрыта</span>');
                        }
                        currentButtonElement.innerHTML = '<i class="bi bi-eye-fill"></i> Показать для всех';
                        currentButtonElement.classList.remove('btn-secondary', 'hide-text-category-btn');
                        currentButtonElement.classList.add('btn-success', 'show-text-category-btn');
                        currentButtonElement.dataset.categoryNameToShow = currentCategoryNameForAction;
                        delete currentButtonElement.dataset.categoryNameToHide;
                        // currentActionType = 'show'; // Not strictly needed to change here as modal re-evaluates on open
                    } else { 
                        statusCell.innerHTML = '<span class="text-success">Видна всем</span>';
                        let badge = categoryNameCell.querySelector('.badge.bg-secondary');
                        if (badge) {
                            badge.remove();
                        }
                        currentButtonElement.innerHTML = '<i class="bi bi-eye-slash-fill"></i> Скрыть от пользователей';
                        currentButtonElement.classList.remove('btn-success', 'show-text-category-btn');
                        currentButtonElement.classList.add('btn-secondary', 'hide-text-category-btn');
                        currentButtonElement.dataset.categoryNameToHide = currentCategoryNameForAction;
                        delete currentButtonElement.dataset.categoryNameToShow;
                        // currentActionType = 'hide'; // Not strictly needed
                    }
                }
                // Возвращаем фокус на кнопку, которая открыла модальное окно
                if (currentButtonElement) {
                    currentButtonElement.focus();
                }
                confirmActionModal.hide();
            } else {
                showTextCategoryToast(data.message || 'Произошла ошибка при выполнении действия.', 'error');
                confirmActionModal.hide();
            }
        })
        .catch(error => {
            showTextCategoryToast('Сетевая ошибка или ошибка сервера: ' + error, 'error');
            confirmActionModal.hide();
        })
        .finally(() => {
            // Restore modal button state
            this.disabled = false;
            this.innerHTML = originalConfirmButtonText;
        });
    });

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>'"/]/g, function (s) {
            const entityMap = {
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': '&quot;',
                "'": '&#39;',
                "/": '&#x2F;'
            };
            return entityMap[s];
        });
    }
});
</script>

<?php
include_once 'footer.php'; // Предполагается, что footer.php существует и корректно закрывает HTML
?> 
?> 