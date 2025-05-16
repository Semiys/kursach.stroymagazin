<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

$page_title = "Управление пользователями";
include_once 'header.php';

$users = [];
$error_message = '';
$success_message = ''; // Для flash-сообщений

if (isset($_SESSION['flash_message'])) {
    if ($_SESSION['flash_message']['type'] === 'success') {
        $success_message = $_SESSION['flash_message']['text'];
    } else {
        $error_message = $_SESSION['flash_message']['text'];
    }
    unset($_SESSION['flash_message']);
}

// Параметры для пагинации
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$users_per_page = 15;
$offset = ($page - 1) * $users_per_page;

// Поиск и фильтрация
$search_query = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? '';
$filter_verified = $_GET['verified'] ?? '';
$filter_active_status = $_GET['active_status'] ?? 'all'; // 'all', 'active', 'inactive'

$where_clauses = [];
$params = [];

if ($filter_active_status === 'active') {
    $where_clauses[] = "users.is_active = 1";
} elseif ($filter_active_status === 'inactive') {
    $where_clauses[] = "users.is_active = 0";
}

if (!empty($search_query)) {
    $where_clauses[] = "(users.name LIKE :search OR users.email LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}

if (!empty($filter_role)) {
    $where_clauses[] = "users.role = :role";
    $params[':role'] = $filter_role;
}

if ($filter_verified !== '') {
    $where_clauses[] = "users.accept = :verified";
    $params[':verified'] = (int)$filter_verified;
}

$where_sql = count($where_clauses) > 0 ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

try {
    // Получаем общее количество пользователей для пагинации
    $total_stmt = $pdo->prepare("SELECT COUNT(*) FROM users" . $where_sql);
    $total_stmt->execute($params);
    $total_users = $total_stmt->fetchColumn();
    $total_pages = ceil($total_users / $users_per_page);

    // Получаем пользователей для текущей страницы
    $sql = "SELECT users.id, users.name, users.email, users.role, users.accept, users.is_active, users.created_at 
            FROM users" 
           . $where_sql 
           . " ORDER BY users.id DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $users_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val); // Тип будет определен автоматически для :search, :role, :verified
    }
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Ошибка при загрузке пользователей: " . $e->getMessage();
}

$all_roles = ['user' => 'Пользователь', 'moder' => 'Модератор', 'admin' => 'Администратор']; // Можно расширить

?>

<div class="container pt-0 mt-0">
    <h4 class="mb-3"><?php echo htmlspecialchars($page_title); ?></h4>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_message) && empty($success_message)): // Показываем ошибку только если нет success сообщения ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Форма поиска и фильтрации -->
    <form method="GET" action="manage_users.php" class="mb-4 p-3 border rounded bg-light">
        <div class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Поиск по ФИО, email" value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="">Все роли</option>
                    <?php foreach ($all_roles as $role_key => $role_name): ?>
                        <option value="<?php echo $role_key; ?>" <?php echo $filter_role === $role_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($role_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="verified" class="form-select">
                    <option value="">Верификация (все)</option>
                    <option value="1" <?php echo $filter_verified === '1' ? 'selected' : ''; ?>>Только верифицированные</option>
                    <option value="0" <?php echo $filter_verified === '0' ? 'selected' : ''; ?>>Только неверифицированные</option>
                </select>
            </div>
            <div class="col-md-2">
                 <select name="active_status" class="form-select">
                    <option value="all" <?php echo $filter_active_status === 'all' ? 'selected' : ''; ?>>Все статусы</option>
                    <option value="active" <?php echo $filter_active_status === 'active' ? 'selected' : ''; ?>>Только активные</option>
                    <option value="inactive" <?php echo $filter_active_status === 'inactive' ? 'selected' : ''; ?>>Только деактивированные</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary-blue w-100">Применить</button>
            </div>
        </div>
    </form>

    <?php if (empty($users) && empty($error_message)): ?>
        <div class="alert alert-info" role="alert">
            Пользователи не найдены или соответствуют вашему запросу.
        </div>
    <?php elseif (!empty($users)): ?>
        <p>Всего найдено: <?php echo $total_users; ?></p>
        <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
            <table class="table table-striped table-bordered">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Email</th>
                        <th>Роль</th>
                        <th>Верификация</th>
                        <th>Статус</th>
                        <th>Регистрация</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['name'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($all_roles[$user['role']] ?? $user['role']); ?> 
                                <?php if ($user['id'] != $_SESSION['user_id']): // Не даем менять свою роль ?>
                                <button class="btn btn-sm btn-outline-secondary ms-1 change-role-btn" 
                                        data-user-id="<?php echo $user['id']; ?>" 
                                        data-current-role="<?php echo $user['role']; ?>" 
                                        data-user-name="<?php echo htmlspecialchars($user['name'] ?: $user['email']); ?>">
                                    <i class="bi bi-person-badge"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['accept']): ?>
                                    <span class="badge bg-success">Пройдена</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Нет</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge bg-success">Активен</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Деактивирован</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(date("d.m.Y H:i", strtotime($user['created_at']))); ?></td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): // Нельзя деактивировать самого себя ?>
                                    <?php if ($user['is_active']): ?>
                                        <button class="btn btn-warning btn-sm mb-1 toggle-user-activation-btn"
                                                data-user-id="<?php echo $user['id']; ?>"
                                                data-action="deactivate">
                                            <i class="bi bi-person-dash-fill"></i> Деактивировать
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-success btn-sm mb-1 toggle-user-activation-btn"
                                                data-user-id="<?php echo $user['id']; ?>"
                                                data-action="activate">
                                            <i class="bi bi-person-check-fill"></i> Активировать
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small class="text-muted">Это вы</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Пагинация -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&role=<?php echo urlencode($filter_role); ?>&verified=<?php echo urlencode($filter_verified); ?>&active_status=<?php echo urlencode($filter_active_status); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Модальное окно для смены роли -->
<div class="modal fade" id="changeRoleModal" tabindex="-1" aria-labelledby="changeRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeRoleModalLabel">Изменить роль пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Пользователь: <strong id="userNameForRoleChange"></strong></p>
                <p>Текущая роль: <strong id="currentRoleDisplay"></strong></p>
                <form id="changeRoleForm">
                    <input type="hidden" id="userIdForRoleChange" name="user_id">
                    <div class="mb-3">
                        <label for="newRoleSelect" class="form-label">Новая роль:</label>
                        <select class="form-select" id="newRoleSelect" name="new_role">
                            <!-- Опции будут добавлены JS, исключая текущую роль и роль админа, если не админ меняет -->
                            <?php 
                            // Для модератора доступные роли user, moder.
                            // Для админа будут доступны все, включая admin.
                            // Здесь нужно будет адаптировать логику для админа
                            $roles_for_select = ['user' => 'Пользователь', 'moder' => 'Модератор']; // Модератор не может назначить админа
                            // Если текущий пользователь - админ, он может назначать админов.
                            if ($_SESSION['user_role'] === 'admin') { $roles_for_select['admin'] = 'Администратор'; }

                            foreach ($roles_for_select as $role_key => $role_name) {
                                echo '<option value="' . $role_key . '">' . htmlspecialchars($role_name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div id="roleChangeError" class="text-danger"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveRoleChangeBtn">Сохранить изменения</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast для уведомлений -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1056;">
    <div id="roleChangeToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Уведомление</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const changeRoleModal = new bootstrap.Modal(document.getElementById('changeRoleModal'));
    const changeRoleBtns = document.querySelectorAll('.change-role-btn');
    const userNameForRoleChange = document.getElementById('userNameForRoleChange');
    const currentRoleDisplay = document.getElementById('currentRoleDisplay');
    const userIdForRoleChangeInput = document.getElementById('userIdForRoleChange');
    const newRoleSelect = document.getElementById('newRoleSelect');
    const saveRoleChangeBtn = document.getElementById('saveRoleChangeBtn');
    const roleChangeError = document.getElementById('roleChangeError');
    const roleChangeToastEl = document.getElementById('roleChangeToast');
    const roleChangeToast = roleChangeToastEl ? new bootstrap.Toast(roleChangeToastEl) : null;
    const toastBody = roleChangeToastEl ? roleChangeToastEl.querySelector('.toast-body') : null;

    const allRoles = {
        'user': 'Пользователь',
        'moder': 'Модератор',
        'admin': 'Администратор'
    };

    // Роли, которые может назначать текущий пользователь
    // PHP сессия доступна здесь, если страница генерируется PHP.
    // Однако, для большей чистоты, лучше передать это через data-атрибут или JS переменную.
    // В данном случае, предположим, что админ может назначать все роли.
    // Модератор (если бы он мог попасть на эту страницу) не должен назначать админа.
    const assignableRolesByCurrentUser = <?php
        $assignable = ['user' => 'Пользователь', 'moder' => 'Модератор'];
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            $assignable['admin'] = 'Администратор';
        }
        echo json_encode($assignable);
    ?>;


    changeRoleBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const userId = this.dataset.userId;
            const currentRole = this.dataset.currentRole;
            const userName = this.dataset.userName;

            userNameForRoleChange.textContent = userName;
            currentRoleDisplay.textContent = allRoles[currentRole] || currentRole;
            userIdForRoleChangeInput.value = userId;
            
            newRoleSelect.innerHTML = ''; // Очищаем предыдущие опции
            roleChangeError.textContent = ''; // Очищаем сообщение об ошибке

            for (const roleKey in assignableRolesByCurrentUser) {
                if (roleKey !== currentRole) {
                    // Админ не может понизить другого админа до юзера или модератора через этот интерфейс,
                    // только если это не он сам (но он и не может менять свою роль)
                    // Также, админ не может повысить юзера/модера до админа, если сам не является админом (это уже учтено в assignableRolesByCurrentUser)
                    // Это правило для модераторов, которые не могут назначать админов.
                    // Для админа: он может назначать любую роль.
                    // Если текущий пользователь - админ, он может назначать все роли.
                    // Если текущий пользователь - не админ (теоретически, на этой странице не должно быть), то он не может назначить админа.
                    
                    // Логика для предотвращения понижения админа другим админом (кроме себя, что и так запрещено)
                    // или назначения админа не-админом
                    const currentSessionUserRole = '<?php echo $_SESSION['user_role']; ?>';
                    
                    if (currentRole === 'admin' && roleKey !== 'admin' && currentSessionUserRole === 'admin') {
                        // Если текущий юзер админ и он пытается изменить роль другого админа на не-админа
                        // Можно заблокировать или добавить предупреждение. Пока просто не добавляем эту опцию,
                        // чтобы админ не мог случайно понизить другого админа.
                        // Чтобы понизить админа, нужно будет сделать это напрямую в БД или через спец. интерфейс.
                        // Для простоты текущей задачи - админ может менять роль другого админа на любую другую, включая user/moder.
                        // Оставим возможность админу менять роль другого админа.
                    }

                    // Не даем НЕ-админу (если он как-то попал сюда) назначать роль 'admin'
                    if (roleKey === 'admin' && currentSessionUserRole !== 'admin') {
                        continue;
                    }
                    
                    const option = document.createElement('option');
                    option.value = roleKey;
                    option.textContent = assignableRolesByCurrentUser[roleKey];
                    newRoleSelect.appendChild(option);
                }
            }
            if (newRoleSelect.options.length === 0) {
                newRoleSelect.innerHTML = '<option value="">Нет доступных ролей для смены</option>';
                saveRoleChangeBtn.disabled = true;
            } else {
                saveRoleChangeBtn.disabled = false;
            }
            changeRoleModal.show();
        });
    });

    saveRoleChangeBtn.addEventListener('click', function () {
        const userId = userIdForRoleChangeInput.value;
        const newRole = newRoleSelect.value;
        roleChangeError.textContent = ''; // Clear previous errors

        if (!newRole) {
            roleChangeError.textContent = 'Пожалуйста, выберите новую роль.';
            return;
        }
        
        // AJAX запрос для обновления роли
        fetch('ajax_update_user_role.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}&new_role=${newRole}&current_page_user_id=<?php echo $_SESSION['user_id']; ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                changeRoleModal.hide();
                if (toastBody && roleChangeToast) {
                    toastBody.textContent = data.message || 'Роль успешно изменена!';
                    roleChangeToast.show();
                }
                // Перезагрузка страницы для отображения изменений
                // Можно также обновить только строку в таблице, но перезагрузка проще
                setTimeout(() => {
                    window.location.reload();
                }, 1500); // Небольшая задержка, чтобы пользователь увидел toast
            } else {
                roleChangeError.textContent = data.message || 'Не удалось изменить роль.';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            roleChangeError.textContent = 'Произошла ошибка при отправке запроса.';
        });
    });

     // Инициализация tooltips для кнопок смены роли (если используются)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Активация/деактивация пользователя
    document.querySelectorAll('.toggle-user-activation-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const action = this.dataset.action; // 'activate' or 'deactivate'
            const actionUrl = (action === 'activate') ? 'ajax_activate_user.php' : 'ajax_deactivate_user.php';
            
            const userRow = this.closest('tr');
            const statusBadge = userRow ? userRow.querySelector('td:nth-child(6) span.badge') : null; // Предполагаем, что статус 6-я колонка

            const formData = new FormData();
            formData.append('user_id', userId);
            // Добавляем CSRF токен, если используется в системе
            // formData.append('csrf_token', 'your_csrf_token_here'); 

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (toastBody && roleChangeToast) { // Используем существующий toast для простоты
                        toastBody.textContent = data.message || `Пользователь успешно ${action === 'activate' ? 'активирован' : 'деактивирован'}.`;
                        roleChangeToast.show();
                    }

                    // Динамическое обновление кнопки и статуса
                    if (action === 'activate') {
                        this.innerHTML = '<i class="bi bi-person-dash-fill"></i> Деактивировать';
                        this.dataset.action = 'deactivate';
                        this.classList.remove('btn-success');
                        this.classList.add('btn-warning');
                        if (statusBadge) {
                            statusBadge.textContent = 'Активен';
                            statusBadge.classList.remove('bg-danger');
                            statusBadge.classList.add('bg-success');
                        }
                    } else { // action === 'deactivate'
                        this.innerHTML = '<i class="bi bi-person-check-fill"></i> Активировать';
                        this.dataset.action = 'activate';
                        this.classList.remove('btn-warning');
                        this.classList.add('btn-success');
                        if (statusBadge) {
                            statusBadge.textContent = 'Деактивирован';
                            statusBadge.classList.remove('bg-success');
                            statusBadge.classList.add('bg-danger');
                        }
                    }
                } else {
                    if (toastBody && roleChangeToast) {
                        toastBody.textContent = data.message || `Не удалось ${action === 'activate' ? 'активировать' : 'деактивировать'} пользователя.`;
                        roleChangeToast.show();
                    }
                    alert("Ошибка: " + (data.message || 'Неизвестная ошибка от сервера.')); // Дополнительный alert для явной ошибки
                }
            })
            .catch(error => {
                console.error('Error toggling user activation:', error);
                if (toastBody && roleChangeToast) {
                    toastBody.textContent = 'Произошла ошибка при отправке запроса.';
                    roleChangeToast.show();
                }
                alert('Сетевая ошибка при попытке изменить статус пользователя.');
            });
        });
    });
});
</script>

<?php include_once 'footer.php'; ?> 