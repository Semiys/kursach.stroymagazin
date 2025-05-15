<?php
session_start(); // Начинаем или возобновляем сессию

// Подключаем файл конфигурации для доступа к $pdo
require_once __DIR__ . '/../config.php'; 

// Проверяем, залогинен ли пользователь и является ли он модератором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'moder') {
    header("Location: /index.php"); // Перенаправляем на главную сайта
    exit;
}

// Подключаем заголовок
$page_title = "Управление заказами";
include_once 'header.php';

// Получаем заказы из базы данных
$orders = [];
$error_message = '';

try {
    // TODO: Добавить пагинацию позже
    // TODO: Рассмотреть возможность JOIN с таблицей users для отображения имени пользователя вместо ID
    $stmt = $pdo->query("SELECT id, user_id, total_amount, status, created_at FROM orders ORDER BY created_at DESC");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Ошибка при загрузке заказов: " . $e->getMessage();
    // error_log($error_message);
}

?>

<div class="container pt-0 mt-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><?php echo htmlspecialchars($page_title); ?></h4>
        <!-- Возможно, кнопка "Создать заказ" здесь не нужна, т.к. заказы создаются пользователями -->
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['flash_message']['text']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($orders) && empty($error_message)): ?>
        <div class="alert alert-info" role="alert">
            Заказов пока нет.
        </div>
    <?php elseif (!empty($orders)): ?>
        <div class="table-responsive" style="max-height: 75vh; overflow-y: auto;"> 
            <table class="table table-striped table-bordered">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>ID Заказа</th>
                        <th>ID Пользователя</th>
                        <th>Общая сумма</th>
                        <th>Статус</th>
                        <th>Дата создания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                            <td><?php echo htmlspecialchars($order['user_id']); ?></td>
                            <td><?php echo htmlspecialchars(number_format($order['total_amount'], 2, '.', ' ')); ?>₽</td>
                            <td><?php echo htmlspecialchars($order['status']); ?></td>
                            <td><?php echo htmlspecialchars(date("d.m.Y H:i", strtotime($order['created_at']))); ?></td>
                            <td>
                                <button type="button" class="btn btn-warning btn-sm mb-1 view-order-details-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#orderDetailsModal" 
                                        data-order-id="<?php echo $order['id']; ?>">
                                    Детали
                                </button>
                                <!-- TODO: Добавить функционал изменения статуса -->
                                <button class="btn btn-danger btn-sm mb-1 change-order-status-btn" data-order-id="<?php echo $order['id']; ?>" data-current-status="<?php echo htmlspecialchars($order['status']); ?>">Изменить статус</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
                <p>Загрузка данных...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для изменения статуса заказа -->
<div class="modal fade" id="changeOrderStatusModal" tabindex="-1" aria-labelledby="changeOrderStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeOrderStatusModalLabel">Изменить статус заказа №<span id="changeStatusOrderId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="changeOrderStatusForm">
                    <input type="hidden" id="formOrderId" name="order_id">
                    <div class="mb-3">
                        <label for="newOrderStatus" class="form-label">Новый статус:</label>
                        <select class="form-select" id="newOrderStatus" name="new_status" required>
                            <option value="Ожидает оплаты">Ожидает оплаты</option>
                            <option value="В обработке">В обработке</option>
                            <option value="Оплачен">Оплачен</option>
                            <option value="Комплектуется">Комплектуется</option>
                            <option value="Передан в доставку">Передан в доставку</option>
                            <option value="Доставлен">Доставлен</option>
                            <option value="Отменен">Отменен</option>
                            <option value="Возврат">Возврат</option>
                        </select>
                    </div>
                    <div id="statusChangeError" class="text-danger mb-2"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="saveOrderStatusBtn">Сохранить изменения</button>
            </div>
        </div>
    </div>
</div>

<!-- Контейнер для Toast уведомлений -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="orderStatusToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto">Уведомление</strong>
            <small>Только что</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            Статус заказа успешно обновлен!
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const orderDetailsModal = document.getElementById('orderDetailsModal');
    const modalOrderIdSpan = document.getElementById('modalOrderId');
    const modalBody = document.getElementById('orderDetailsModalBody');

    const detailButtons = document.querySelectorAll('.view-order-details-btn');

    // --- Код для модального окна изменения статуса ---
    const changeOrderStatusModal = new bootstrap.Modal(document.getElementById('changeOrderStatusModal'));
    const changeStatusOrderIdSpan = document.getElementById('changeStatusOrderId');
    const formOrderIdInput = document.getElementById('formOrderId');
    const newOrderStatusSelect = document.getElementById('newOrderStatus');
    const saveOrderStatusBtn = document.getElementById('saveOrderStatusBtn');
    const statusChangeErrorDiv = document.getElementById('statusChangeError');

    // --- Инициализация Toast --- 
    const toastElement = document.getElementById('orderStatusToast');
    const toastBody = toastElement.querySelector('.toast-body');
    const orderStatusToast = new bootstrap.Toast(toastElement, { delay: 3000 }); // Показывается 3 секунды

    const changeStatusButtons = document.querySelectorAll('.change-order-status-btn');

    changeStatusButtons.forEach(button => {
        button.addEventListener('click', function () {
            const orderId = this.dataset.orderId;
            const currentStatus = this.dataset.currentStatus;

            changeStatusOrderIdSpan.textContent = orderId;
            formOrderIdInput.value = orderId;
            newOrderStatusSelect.value = currentStatus;
            statusChangeErrorDiv.textContent = ''; // Очищаем предыдущие ошибки
            changeOrderStatusModal.show();
        });
    });

    saveOrderStatusBtn.addEventListener('click', function() {
        const orderId = formOrderIdInput.value;
        const newStatus = newOrderStatusSelect.value;
        statusChangeErrorDiv.textContent = '';

        // Блокируем кнопку на время запроса
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Сохранение...';


        fetch('ajax_update_order_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `order_id=${orderId}&new_status=${encodeURIComponent(newStatus)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                changeOrderStatusModal.hide();
                // Обновить статус в таблице
                const row = document.querySelector(`button.change-order-status-btn[data-order-id="${orderId}"]`).closest('tr');
                if (row) {
                    // Предполагаем, что статус находится в 4-й колонке (индекс 3)
                    const statusCell = row.cells[3]; 
                    if (statusCell) {
                        statusCell.textContent = newStatus;
                    }
                    // Обновить data-current-status у кнопки
                    const changeBtn = row.querySelector('.change-order-status-btn');
                    if(changeBtn) {
                        changeBtn.dataset.currentStatus = newStatus;
                    }
                }
                // Показать toast уведомление об успехе
                toastBody.textContent = data.message || 'Статус заказа успешно обновлен!';
                orderStatusToast.show(); 
            } else {
                statusChangeErrorDiv.textContent = data.error || 'Не удалось обновить статус.';
            }
        })
        .catch(error => {
            console.error('Ошибка при обновлении статуса:', error);
            statusChangeErrorDiv.textContent = 'Произошла ошибка сети. Попробуйте снова.';
        })
        .finally(() => {
            // Разблокируем кнопку
            this.disabled = false;
            this.innerHTML = 'Сохранить изменения';
        });
    });


    // --- Существующий код для модального окна деталей заказа ---
    detailButtons.forEach(button => {
        button.addEventListener('click', function () {
            const orderId = this.dataset.orderId;
            modalOrderIdSpan.textContent = orderId;
            modalBody.innerHTML = '<p>Загрузка данных для заказа №' + orderId + '...</p>';

            fetch('ajax_get_order_details.php?order_id=' + orderId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        modalBody.innerHTML = '<p class="text-danger">' + data.error + '</p>';
                        return;
                    }
                    
                    let content = '';
                    // Общая информация о заказе
                    content += `<h5>Общая информация:</h5>`;
                    content += `<dl class="row">`;
                    content += `<dt class="col-sm-4">ID Заказа:</dt><dd class="col-sm-8">${data.order.id}</dd>`;
                    content += `<dt class="col-sm-4">Статус:</dt><dd class="col-sm-8">${data.order.status}</dd>`;
                    content += `<dt class="col-sm-4">Дата создания:</dt><dd class="col-sm-8">${new Date(data.order.created_at).toLocaleString('ru-RU')}</dd>`;
                    content += `<dt class="col-sm-4">Сумма заказа:</dt><dd class="col-sm-8">${parseFloat(data.order.total_amount).toFixed(2)} ₽</dd>`;
                    if(data.order.promo_code) {
                        content += `<dt class="col-sm-4">Промокод:</dt><dd class="col-sm-8">${data.order.promo_code} (-${parseFloat(data.order.discount_amount).toFixed(2)} ₽)</dd>`;
                    }
                    content += `</dl>`;

                    // Информация о пользователе
                    if(data.user) {
                        content += `<h5>Информация о клиенте:</h5>`;
                        content += `<dl class="row">`;
                        content += `<dt class="col-sm-4">ID Пользователя:</dt><dd class="col-sm-8">${data.user.id}</dd>`;
                        content += `<dt class="col-sm-4">Логин:</dt><dd class="col-sm-8">${data.user.login ? data.user.login : '-'}</dd>`;
                        content += `<dt class="col-sm-4">Email:</dt><dd class="col-sm-8">${data.user.email ? data.user.email : '-'}</dd>`;
                        content += `<dt class="col-sm-4">Имя:</dt><dd class="col-sm-8">${data.user.name ? data.user.name : '-'}</dd>`;
                        content += `</dl>`;
                    } else {
                        content += '<p class="text-warning">Информация о пользователе не найдена.</p>';
                    }

                    // Адрес доставки
                    if(data.order.shipping_address) {
                        content += `<h5>Адрес доставки:</h5><p>${data.order.shipping_address}</p>`;
                    }
                    // Метод оплаты
                     if(data.order.payment_method) {
                        content += `<h5>Метод оплаты:</h5><p>${data.order.payment_method}</p>`;
                    }

                    // Товары в заказе
                    content += `<h5>Товары в заказе:</h5>`;
                    if (data.items && data.items.length > 0) {
                        content += `<table class="table table-sm table-bordered">`;
                        content += `<thead><tr><th>Товар</th><th>Кол-во</th><th>Цена за шт.</th><th>Скидка</th><th>Итого</th></tr></thead><tbody>`;
                        data.items.forEach(item => {
                            const price = parseFloat(item.price);
                            const discountPercentage = parseFloat(item.discount_percentage);
                            const discountedPrice = price * (1 - discountPercentage / 100);
                            const itemTotal = discountedPrice * item.quantity;
                            content += `<tr>
                                <td>${item.product_title} (ID: ${item.product_id})</td>
                                <td>${item.quantity}</td>
                                <td>${price.toFixed(2)} ₽</td>
                                <td>${discountPercentage > 0 ? discountPercentage + '%' : '-'}</td>
                                <td>${itemTotal.toFixed(2)} ₽</td>
                            </tr>`;
                        });
                        content += `</tbody></table>`;
                    } else {
                        content += '<p>В этом заказе нет товаров.</p>';
                    }

                    modalBody.innerHTML = content;
                })
                .catch(error => {
                    console.error('Ошибка при загрузке деталей заказа:', error);
                    modalBody.innerHTML = '<p class="text-danger">Не удалось загрузить детали заказа. Пожалуйста, попробуйте еще раз.</p>';
                });
        });
    });
});
</script>

<?php
// Подключаем подвал
include_once 'footer.php';
?> 