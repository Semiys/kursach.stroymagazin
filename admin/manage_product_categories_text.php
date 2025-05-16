<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
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
        submitCheckBtn.disabled = true;
        submitCheckBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Проверка...';

        fetch('ajax_validate_new_category_name.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'category_name_to_check=' + encodeURIComponent(categoryName)
        })
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                checkInfoDiv.innerHTML = 'Категория <strong>'+ escapeHtml(categoryName) +'</strong> уже используется. <br>Товаров в ней: ' + data.count;
                if (data.is_hidden) {
                    checkInfoDiv.innerHTML += '<br><span class="badge bg-secondary">Эта категория сейчас скрыта</span>';
                } else {
                     checkInfoDiv.innerHTML += '<br><span class="badge bg-success">Эта категория сейчас видима</span>';
                }
                if (data.similar_normalized && data.similar_normalized.length > 0) {
                    let similarHtml = '<p class="mt-2 mb-1">Возможно, вы имели в виду одну из этих похожих категорий (после нормализации):</p><ul>';
                    data.similar_normalized.forEach(cat => {
                        similarHtml += '<li>' + escapeHtml(cat.category_name) + ' (' + cat.product_count + ' товаров)</li>';
                    });
                    similarHtml += '</ul>';
                    checkInfoDiv.innerHTML += similarHtml;
                }

            } else {
                checkInfoDiv.innerHTML = 'Категория <strong>'+ escapeHtml(categoryName) +'</strong> свободна для использования.';
                if (data.similar_normalized && data.similar_normalized.length > 0) {
                     let similarHtml = '<p class="mt-2 mb-1">Похожие (по нормализации) категории, которые уже существуют:</p><ul>';
                    data.similar_normalized.forEach(cat => {
                        similarHtml += '<li>' + escapeHtml(cat.category_name) + ' (' + cat.product_count + ' товаров)</li>';
                    });
                    similarHtml += '</ul>';
                    checkInfoDiv.innerHTML += similarHtml;
                } else {
                     checkInfoDiv.innerHTML += '<br>Похожих по написанию категорий не найдено.';
                }
            }
        })
        .catch(error => {
            console.error('Error checking category name:', error);
            checkErrorDiv.textContent = 'Произошла ошибка при проверке имени категории.';
        })
        .finally(() => {
            submitCheckBtn.disabled = false;
            submitCheckBtn.innerHTML = 'Проверить';
        });
    });
    
    checkTextCategoryNameModalElement.addEventListener('hidden.bs.modal', function () {
        checkCategoryNameInput.value = '';
        checkErrorDiv.textContent = '';
        checkInfoDiv.textContent = '';
    });


    // --- RENAME CATEGORY --- //
    const renameModalElement = document.getElementById('renameTextCategoryModal');
    const renameModal = new bootstrap.Modal(renameModalElement);
    const renameOldCategoryNameInput = document.getElementById('renameOldCategoryName');
    const renameNewCategoryNameInput = document.getElementById('renameNewCategoryNameInput');
    const currentCategoryNameDisplay = document.getElementById('currentCategoryNameDisplay');
    const renameErrorDiv = document.getElementById('renameTextCategoryError');

    document.querySelectorAll('.rename-text-category-btn').forEach(button => {
        button.addEventListener('click', function () {
            const oldCategoryName = this.dataset.oldCategoryName;
            renameOldCategoryNameInput.value = oldCategoryName;
            currentCategoryNameDisplay.textContent = oldCategoryName;
            renameNewCategoryNameInput.value = oldCategoryName; // Предзаполняем новым именем
            renameErrorDiv.textContent = '';
        });
    });

    const saveRenameBtn = document.getElementById('saveRenameTextCategoryBtn');
    saveRenameBtn.addEventListener('click', function() {
        const oldName = renameOldCategoryNameInput.value;
        const newName = renameNewCategoryNameInput.value.trim();
        renameErrorDiv.textContent = '';

        if (!newName) {
            renameErrorDiv.textContent = 'Новое название категории не может быть пустым.';
            return;
        }
        if (newName === oldName) {
            renameErrorDiv.textContent = 'Новое название совпадает со старым.';
            return;
        }

        saveRenameBtn.disabled = true;
        saveRenameBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';

        fetch('ajax_rename_category_in_goods.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'old_category_name=' + encodeURIComponent(oldName) + '&new_category_name=' + encodeURIComponent(newName)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renameModal.hide();
                // Показываем сообщение и перезагружаем страницу для обновления таблицы
                showTextCategoryToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 2000); 
            } else {
                renameErrorDiv.textContent = data.message || 'Не удалось переименовать категорию.';
            }
        })
        .catch(error => {
            console.error('Error renaming category:', error);
            renameErrorDiv.textContent = 'Произошла ошибка при переименовании.';
        })
        .finally(() => {
            saveRenameBtn.disabled = false;
            saveRenameBtn.innerHTML = 'Сохранить изменения';
        });
    });

    renameModalElement.addEventListener('hidden.bs.modal', function () {
        renameNewCategoryNameInput.value = '';
        renameErrorDiv.textContent = '';
    });

    // --- HIDE/SHOW CATEGORY --- //
    const confirmActionModalElement = document.getElementById('confirmActionModal');
    const confirmActionModal = new bootstrap.Modal(confirmActionModalElement);
    const confirmActionModalText = document.getElementById('confirmActionModalText');
    const confirmActionModalButton = document.getElementById('confirmActionModalButton');
    let currentCategoryNameForAction = '';
    let currentActionType = ''; // 'hide' or 'show'

    document.querySelectorAll('.hide-text-category-btn').forEach(button => {
        button.addEventListener('click', function () {
            currentCategoryNameForAction = this.dataset.categoryNameToHide;
            currentActionType = 'hide';
            confirmActionModalText.textContent = `Вы уверены, что хотите скрыть категорию "${escapeHtml(currentCategoryNameForAction)}" от пользователей? Товары этой категории не исчезнут, но сама категория не будет отображаться в фильтрах и списках категорий.`;
            confirmActionModalButton.className = 'btn btn-danger';
            confirmActionModalButton.textContent = 'Да, скрыть';
            confirmActionModal.show();
        });
    });

    document.querySelectorAll('.show-text-category-btn').forEach(button => {
        button.addEventListener('click', function () {
            currentCategoryNameForAction = this.dataset.categoryNameToShow;
            currentActionType = 'show';
            confirmActionModalText.textContent = `Вы уверены, что хотите снова отображать категорию "${escapeHtml(currentCategoryNameForAction)}"?`;
            confirmActionModalButton.className = 'btn btn-success';
            confirmActionModalButton.textContent = 'Да, показать';
            confirmActionModal.show();
        });
    });

    confirmActionModalButton.addEventListener('click', function() {
        if (!currentCategoryNameForAction || !currentActionType) return;

        const endpoint = currentActionType === 'hide' ? 'ajax_hide_category.php' : 'ajax_show_category.php';
        const originalButtonText = confirmActionModalButton.textContent;
        confirmActionModalButton.disabled = true;
        confirmActionModalButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Обработка...';

        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'category_name=' + encodeURIComponent(currentCategoryNameForAction)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showTextCategoryToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showTextCategoryToast(data.message || 'Произошла ошибка', 'error');
            }
        })
        .catch(error => {
            console.error('Error with category visibility action:', error);
            showTextCategoryToast('Сетевая ошибка или ошибка сервера.', 'error');
        })
        .finally(() => {
            confirmActionModal.hide();
            confirmActionModalButton.disabled = false;
            confirmActionModalButton.textContent = originalButtonText;
            currentCategoryNameForAction = '';
            currentActionType = '';
        });
    });
    
    function escapeHtml(unsafe) {
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

});
</script>

<?php 
// Не забываем подключить footer.php, если он у вас есть и используется для закрытия HTML и подключения JS
include_once 'footer.php'; 
?> 