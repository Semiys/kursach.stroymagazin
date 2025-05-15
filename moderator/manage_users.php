<?php
session_start(); 

require_once __DIR__ . '/../config.php'; 

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'moder') {
    header("Location: /index.php"); 
    exit;
}

$page_title = "Управление пользователями";
include_once 'header.php';

$users = [];
$error_message = '';
$success_message = ''; // For success messages from role changes (though usually handled by AJAX + toast)

// Handle potential success/error messages passed via session (e.g., after a non-AJAX action if any)
if (isset($_SESSION['flash_message'])) {
    if ($_SESSION['flash_message']['type'] === 'success') {
        $success_message = $_SESSION['flash_message']['text'];
    } else {
        $error_message = $_SESSION['flash_message']['text'];
    }
    unset($_SESSION['flash_message']);
}

try {
    // TODO: Add pagination later
    $stmt = $pdo->query("SELECT id, login, email, name, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Ошибка при загрузке пользователей: " . $e->getMessage();
}

?>

<div class="container pt-0 mt-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><?php echo htmlspecialchars($page_title); ?></h4>
        <!-- No "Create User" button for now, users register themselves -->
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message) && empty($success_message)): // Avoid showing generic load error if a specific success/error is already shown ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($users) && empty($error_message)): ?>
        <div class="alert alert-info" role="alert">
            Пользователей пока нет.
        </div>
    <?php elseif (!empty($users)): ?>
        <div class="table-responsive" style="max-height: 75vh; overflow-y: auto;"> 
            <table class="table table-striped table-bordered">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>ID</th>
                        <th>Логин</th>
                        <th>Email</th>
                        <th>Имя</th>
                        <th>Роль</th>
                        <th>Дата регистрации</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['login']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['name'] ?? '-'); ?></td>
                            <td class="user-role-<?php echo htmlspecialchars($user['id']); ?>"><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo htmlspecialchars(date("d.m.Y H:i", strtotime($user['created_at']))); ?></td>
                            <td>
                                <button type="button" class="btn btn-warning btn-sm mb-1 change-user-role-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#changeUserRoleModal" 
                                        data-user-id="<?php echo $user['id']; ?>"
                                        data-current-role="<?php echo htmlspecialchars($user['role']); ?>"
                                        data-user-login="<?php echo htmlspecialchars($user['login']); ?>">
                                    Изменить роль
                                </button>
                                <!-- TODO: Add View Details button/modal -->
                                <!-- TODO: Add Delete User button (with caution) -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно для изменения роли пользователя -->
<div class="modal fade" id="changeUserRoleModal" tabindex="-1" aria-labelledby="changeUserRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeUserRoleModalLabel">Изменить роль пользователя <span id="changeRoleUserLogin"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="changeUserRoleForm">
                    <input type="hidden" id="formUserId" name="user_id">
                    <div class="mb-3">
                        <label for="newUserRole" class="form-label">Новая роль:</label>
                        <select class="form-select" id="newUserRole" name="new_role" required>
                            <option value="user">User</option>
                            <option value="moder">Moderator</option>
                            <!-- <option value="admin">Admin</option> -->
                        </select>
                    </div>
                    <div id="roleChangeError" class="text-danger mb-2"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveUserRoleBtn">Сохранить изменения</button>
            </div>
        </div>
    </div>
</div>

<!-- Контейнер для Toast уведомлений (можно использовать тот же, что и в manage_orders, если header.php его подключает глобально, или определить здесь) -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1056;"> <!-- z-index to be above modals -->
    <div id="userRoleToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Уведомление</strong>
            <small>Только что</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            Роль пользователя успешно обновлена!
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Код для модального окна изменения роли ---
    const changeUserRoleModalElement = document.getElementById('changeUserRoleModal');
    const changeUserRoleModal = new bootstrap.Modal(changeUserRoleModalElement);
    const changeRoleUserLoginSpan = document.getElementById('changeRoleUserLogin');
    const formUserIdInput = document.getElementById('formUserId');
    const newUserRoleSelect = document.getElementById('newUserRole');
    const saveUserRoleBtn = document.getElementById('saveUserRoleBtn');
    const roleChangeErrorDiv = document.getElementById('roleChangeError');

    // --- Инициализация Toast --- 
    const toastElement = document.getElementById('userRoleToast');
    const toastBody = toastElement.querySelector('.toast-body');
    const userRoleToast = new bootstrap.Toast(toastElement, { delay: 3000 });

    const changeRoleButtons = document.querySelectorAll('.change-user-role-btn');

    changeRoleButtons.forEach(button => {
        button.addEventListener('click', function () {
            const userId = this.dataset.userId;
            const currentRole = this.dataset.currentRole;
            const userLogin = this.dataset.userLogin;

            changeRoleUserLoginSpan.textContent = userLogin;
            formUserIdInput.value = userId;
            newUserRoleSelect.value = currentRole;
            roleChangeErrorDiv.textContent = ''; 
            changeUserRoleModal.show();
        });
    });

    saveUserRoleBtn.addEventListener('click', function() {
        const userId = formUserIdInput.value;
        const newRole = newUserRoleSelect.value;
        roleChangeErrorDiv.textContent = '';

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';

        fetch('ajax_update_user_role.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}&new_role=${encodeURIComponent(newRole)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                changeUserRoleModal.hide();
                // Обновить роль в таблице
                const roleCell = document.querySelector(`.user-role-${userId}`);
                if (roleCell) {
                    roleCell.textContent = newRole;
                }
                // Обновить data-current-role у кнопки
                const changeBtn = document.querySelector(`.change-user-role-btn[data-user-id="${userId}"]`);
                if(changeBtn) {
                    changeBtn.dataset.currentRole = newRole;
                }
                
                toastBody.textContent = data.message || 'Роль пользователя успешно обновлена!';
                userRoleToast.show(); 
            } else {
                roleChangeErrorDiv.textContent = data.error || 'Не удалось обновить роль.';
            }
        })
        .catch(error => {
            console.error('Ошибка при обновлении роли:', error);
            roleChangeErrorDiv.textContent = 'Произошла ошибка сети. Попробуйте снова.';
        })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = 'Сохранить изменения';
        });
    });
});
</script>

<?php
include_once 'footer.php';
?> 