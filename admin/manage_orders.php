<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

$page_title = "Управление заказами";
include_once 'header.php';

$orders = [];
$error_message = '';
$success_message = '';

if (isset($_SESSION['flash_message'])) {
    $message_type = $_SESSION['flash_message']['type'];
    $message_text = $_SESSION['flash_message']['text'];
    if ($message_type === 'success') {
        $success_message = $message_text;
    } else {
        $error_message = $message_text;
    }
    unset($_SESSION['flash_message']);
}


// Параметры для пагинации
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$orders_per_page = 10;
$offset = ($page - 1) * $orders_per_page;

// Поиск и фильтрация
$search_query = $_GET['search'] ?? ''; // По ID заказа, email, телефону, ФИО
$filter_status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$show_hidden = isset($_GET['show_hidden']) && $_GET['show_hidden'] === '1';

$where_clauses = [];
$params = [];

if (!$show_hidden) {
    $where_clauses[] = "o.is_hidden = 0";
}

if (!empty($search_query)) {
    $where_clauses[] = "(o.id LIKE :search OR u.email LIKE :search OR u.name LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}

if (!empty($filter_status)) {
    $where_clauses[] = "o.status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($date_from)) {
    $where_clauses[] = "o.created_at >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}

if (!empty($date_to)) {
    $where_clauses[] = "o.created_at <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}

$where_sql = count($where_clauses) > 0 ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

try {
    // Запрос для общего количества заказов (для пагинации)
    $count_where_sql = $where_sql; // Копируем для подсчета
    // Если $where_sql пустой, но есть поиск, то WHERE будет добавлен в $total_stmt
    // Если $where_sql не пустой (из-за is_hidden), то JOIN для поиска нужно добавить к существующему WHERE

    $total_stmt_sql = "SELECT COUNT(*) FROM orders o ";
    if (strpos($count_where_sql, 'u.name') !== false || strpos($count_where_sql, 'u.email') !== false) {
        // Добавляем LEFT JOIN users если он нужен для WHERE, но еще не является частью $count_where_sql из-за is_hidden
        if (strpos($count_where_sql, 'LEFT JOIN users u ON o.user_id = u.id') === false) {
             $total_stmt_sql .= " LEFT JOIN users u ON o.user_id = u.id ";
        }
    }
    $total_stmt_sql .= $count_where_sql;

    $total_stmt = $pdo->prepare($total_stmt_sql);
    $total_stmt->execute($params);
    $total_orders = $total_stmt->fetchColumn();
    $total_pages = ceil($total_orders / $orders_per_page);

    // Запрос для получения заказов на текущую страницу
    $sql = "SELECT o.*, u.login as user_login, u.name as user_db_name, u.email as user_db_email
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id"
           . $where_sql
           . " ORDER BY o.id DESC LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $orders_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val); // PDO определит тип для строк и чисел
    }
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Ошибка при загрузке заказов: " . $e->getMessage();
}

// Статусы заказов для фильтра и отображения
$order_statuses = [
    'pending' => 'Ожидает обработки',
    'processing' => 'В обработке',
    'shipped' => 'Отправлен',
    'completed' => 'Выполнен',
    'cancelled' => 'Отменен',
    'refunded' => 'Возвращен'
];

?>

<div class="container pt-0 mt-0">
    <h4 class="mb-3"><?php echo htmlspecialchars($page_title); ?></h4>

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

    <!-- Форма поиска и фильтрации -->
    <form method="GET" action="manage_orders.php" class="mb-4 p-3 border rounded bg-light">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="search_query" class="form-label">Поиск</label>
                <input type="text" id="search_query" name="search" class="form-control" placeholder="ID, email, ФИО" value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="col-md-2">
                <label for="filter_status" class="form-label">Статус</label>
                <select id="filter_status" name="status" class="form-select">
                    <option value="">Все статусы</option>
                    <?php foreach ($order_statuses as $status_key => $status_name): ?>
                        <option value="<?php echo $status_key; ?>" <?php echo $filter_status === $status_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($status_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date_from" class="form-label">Дата от</label>
                <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Дата до</label>
                <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
            <div class="col-md-2 align-self-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="show_hidden" id="show_hidden" value="1" <?php echo $show_hidden ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="show_hidden">
                        Показать скрытые
                    </label>
                </div>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary-blue w-100">Фильтр</button>
            </div>
        </div>
    </form>


    <?php if (empty($orders) && empty($error_message)): ?>
        <div class="alert alert-info" role="alert">
            Заказы не найдены или соответствуют вашему запросу.
        </div>
    <?php elseif (!empty($orders)): ?>
        <p>Всего найдено: <?php echo $total_orders; ?></p>
        <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>ID Заказа</th>
                        <th>Клиент</th>
                        <th>Сумма</th>
                        <th>Статус</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                            <td>
                                <?php 
                                // Используем имя из таблицы users (user_db_name), если доступно.
                                // В противном случае, используем email или телефон из заказа как запасной вариант.
                                $client_name = $order['user_db_name'] ?: ($order['user_db_email'] ?: 'Информация отсутствует');
                                echo htmlspecialchars($client_name); 
                                ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($order['user_db_email'] ?? 'Email не указан'); ?></small>
                                <?php if ($order['user_id']): ?>
                                    <br><small class="text-info">ID: <?php echo htmlspecialchars($order['user_id']); ?> (Логин: <?php echo htmlspecialchars($order['user_login'] ?? 'N/A'); ?>)</small>
                                <?php else: ?>
                                    <br><small class="text-warning">Незарегистрированный</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars(number_format($order['total_amount'], 2, '.', ' ')); ?> ₽</td>
                            <td>
                                <select class="form-select form-select-sm status-change-select" data-order-id="<?php echo $order['id']; ?>">
                                    <?php foreach ($order_statuses as $status_key => $status_name): ?>
                                        <option value="<?php echo $status_key; ?>" <?php echo $order['status'] === $status_key ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small id="status-feedback-<?php echo $order['id']; ?>" class="d-block mt-1"></small>
                            </td>
                            <td><?php echo htmlspecialchars(date("d.m.Y H:i", strtotime($order['created_at']))); ?></td>
                            <td>
                                <button class="btn btn-info btn-sm view-order-details-btn mb-1" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#orderDetailsModal"
                                        data-order-id="<?php echo $order['id']; ?>">
                                    <i class="bi bi-eye-fill"></i> Детали
                                </button>
                                <?php if ($order['is_hidden'] == 1): ?>
                                    <button class="btn btn-success btn-sm mb-1 toggle-order-visibility-btn"
                                            data-order-id="<?php echo $order['id']; ?>"
                                            data-action="show">
                                        <i class="bi bi-eye-fill"></i> Показать
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm mb-1 toggle-order-visibility-btn"
                                            data-order-id="<?php echo $order['id']; ?>"
                                            data-action="hide">
                                        <i class="bi bi-eye-slash-fill"></i> Скрыть
                                    </button>
                                <?php endif; ?>
                                 <!-- <a href="edit_order.php?id=<?php echo $order['id']; ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil-fill"></i> Ред.</a> -->
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
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($filter_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Модальное окно для деталей заказа -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailsModalLabel">Детали заказа №<span id="modalOrderId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetailsModalBody">
                <!-- Содержимое будет загружено через AJAX -->
                <div class="text-center">
                    <div class="spinner-border text-primary-orange" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast для уведомлений -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1056;">
    <div id="orderActionToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
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
    const orderActionToastEl = document.getElementById('orderActionToast');
    const orderActionToast = new bootstrap.Toast(orderActionToastEl, { delay: 3500 });

    function showOrderToast(message, type = 'success') {
        const toastBody = orderActionToastEl.querySelector('.toast-body');
        toastBody.textContent = message;
        toastBody.className = 'toast-body'; // Сброс классов
        if (type === 'success') {
            toastBody.classList.add('text-success');
        } else if (type === 'error') {
            toastBody.classList.add('text-danger');
        } else {
            toastBody.classList.add('text-info');
        }
        orderActionToast.show();
    }

    // Обработка изменения статуса заказа
    document.querySelectorAll('.status-change-select').forEach(selectElement => {
        selectElement.addEventListener('change', function () {
            const orderId = this.dataset.orderId;
            const newStatus = this.value;
            const feedbackEl = document.getElementById(`status-feedback-${orderId}`);
            feedbackEl.textContent = 'Сохранение...';
            feedbackEl.className = 'text-muted d-block mt-1'; // Сброс цвета

            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('new_status', newStatus);

            fetch('ajax_update_order_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    feedbackEl.textContent = 'Статус обновлен!';
                    feedbackEl.classList.remove('text-muted', 'text-danger');
                    feedbackEl.classList.add('text-success');
                    showOrderToast(data.message || 'Статус заказа успешно обновлен.', 'success');
                } else {
                    feedbackEl.textContent = data.error || 'Ошибка обновления.';
                    feedbackEl.classList.remove('text-muted', 'text-success');
                    feedbackEl.classList.add('text-danger');
                    showOrderToast(data.error || 'Не удалось обновить статус заказа.', 'error');
                    // Можно вернуть select к предыдущему значению, если это нужно
                    // this.value = previousStatus; // Потребует хранения previousStatus
                }
            })
            .catch(error => {
                console.error('Error updating order status:', error);
                feedbackEl.textContent = 'Сетевая ошибка.';
                feedbackEl.classList.remove('text-muted', 'text-success');
                feedbackEl.classList.add('text-danger');
                showOrderToast('Произошла ошибка при отправке запроса.', 'error');
            });
        });
    });

    // Загрузка деталей заказа в модальное окно
    const orderDetailsModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    const orderDetailsModalBody = document.getElementById('orderDetailsModalBody');
    const modalOrderIdSpan = document.getElementById('modalOrderId');

    document.querySelectorAll('.view-order-details-btn').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            modalOrderIdSpan.textContent = orderId;
            orderDetailsModalBody.innerHTML = '<div class="text-center"><div class="spinner-border text-primary-orange" role="status"><span class="visually-hidden">Загрузка...</span></div></div>'; // Показываем спиннер

            fetch(`ajax_get_order_details.php?order_id=${orderId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text(); // Сначала получаем как текст, чтобы проверить на ошибки HTML/JSON
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text); // Пытаемся парсить как JSON
                        if (data.success && data.html) {
                            orderDetailsModalBody.innerHTML = data.html;
                        } else if (data.error) {
                            orderDetailsModalBody.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                        } else {
                            orderDetailsModalBody.innerHTML = '<div class="alert alert-warning">Не удалось получить детали заказа. Некорректный формат ответа.</div>';
                        }
                    } catch (e) { // Если парсинг JSON не удался, значит пришел не JSON
                        console.error("Failed to parse JSON from order details: ", text); // Логируем полученный текст
                        orderDetailsModalBody.innerHTML = '<div class="alert alert-danger">Ошибка при обработке ответа от сервера. Детали заказа не могут быть отображены.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error fetching order details:', error);
                    orderDetailsModalBody.innerHTML = `<div class="alert alert-danger">Не удалось загрузить детали заказа: ${error.message}</div>`;
                });
        });
    });

    // Скрытие/показ заказа
    document.querySelectorAll('.toggle-order-visibility-btn').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            const action = this.dataset.action; // 'hide' or 'show'
            const actionUrl = (action === 'hide') ? 'ajax_hide_order.php' : 'ajax_show_order.php';

            const formData = new FormData();
            formData.append('order_id', orderId);

            fetch(actionUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showOrderToast(data.message || `Заказ успешно ${action === 'hide' ? 'скрыт' : 'показан'}.`, 'success');
                    
                    // Динамическое обновление строки и кнопки
                    const row = button.closest('tr'); // Находим строку таблицы
                    const showHiddenCheckbox = document.getElementById('show_hidden');

                    if (action === 'hide') {
                        button.innerHTML = '<i class="bi bi-eye-fill"></i> Показать';
                        button.dataset.action = 'show';
                        button.classList.remove('btn-secondary');
                        button.classList.add('btn-success');
                        if (row && !showHiddenCheckbox.checked) {
                            row.style.display = 'none'; // Скрываем строку, если не стоит галочка "Показывать скрытые"
                        } else if (row) {
                            // Можно добавить класс для визуального отличия скрытой строки, если она отображается
                            row.classList.add('order-hidden-row'); 
                        }
                    } else { // action === 'show'
                        button.innerHTML = '<i class="bi bi-eye-slash-fill"></i> Скрыть';
                        button.dataset.action = 'hide';
                        button.classList.remove('btn-success');
                        button.classList.add('btn-secondary');
                        if (row) {
                            row.style.display = ''; // Показываем строку (если она была скрыта display:none)
                            row.classList.remove('order-hidden-row');
                        }
                    }
                    // Обновляем is_hidden в каком-нибудь data-атрибуте строки, если это нужно для других скриптов
                    if(row) row.dataset.isHidden = (action === 'hide') ? '1' : '0';
                } else {
                    showOrderToast(data.error || `Не удалось ${action === 'hide' ? 'скрыть' : 'показать'} заказ.`, 'error');
                }
            })
            .catch(error => {
                console.error(`Error ${action}ing order:`, error);
                showOrderToast(`Произошла ошибка при ${action === 'hide' ? 'скрытии' : 'показе'} заказа.`, 'error');
            });
        });
    });

});
</script>

<?php include_once 'footer.php'; ?> 