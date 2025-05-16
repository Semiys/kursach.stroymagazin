<?php
session_start();
require_once __DIR__ . '/../config.php'; // Подключение к базе данных
require_once __DIR__ . '/includes/audit_logger.php'; // Для логирования

// Проверка, залогинен ли пользователь и является ли он администратором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

$page_title = "Управление промокодами";
include_once 'header.php';

// Сохраним сообщения в переменные, если они есть, для передачи в JS
$success_message_js = isset($_GET['success_message']) ? htmlspecialchars($_GET['success_message']) : null;
$error_message_js = isset($_GET['error_message']) ? htmlspecialchars($_GET['error_message']) : null;

// Получение промокодов из базы данных
try {
    $stmt = $pdo->query("SELECT * FROM promo_codes ORDER BY created_at DESC");
    $promo_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Ошибка при загрузке промокодов: " . $e->getMessage() . "</div>";
    $promo_codes = []; // Пустой массив в случае ошибки
}
?>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055"> <!-- z-index выше чем у многих элементов Bootstrap -->
  <!-- Сюда будут добавляться Toast сообщения -->
</div>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Управление промокодами</h2>
                <a href="/admin/edit_promo_code.php" class="btn btn-light">
                    <i class="bi bi-plus-circle-fill me-2"></i>Добавить промокод
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($promo_codes)): ?>
                <div class="alert alert-info">Промокоды еще не добавлены.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead class="thead-primary-blue">
                            <tr>
                                <th>ID</th>
                                <th>Код</th>
                                <th>Тип скидки</th>
                                <th>Значение</th>
                                <th>Мин. сумма заказа</th>
                                <th>Статус</th>
                                <th>Начало</th>
                                <th>Окончание</th>
                                <th>Лимит исп.</th>
                                <th>Исп. раз</th>
                                <th>Создан</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promo_codes as $promo_code): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($promo_code['id']); ?></td>
                                    <td><?php echo htmlspecialchars($promo_code['code']); ?></td>
                                    <td><?php echo htmlspecialchars($promo_code['discount_type']); ?></td>
                                    <td><?php echo htmlspecialchars($promo_code['discount_value']); ?></td>
                                    <td><?php echo htmlspecialchars($promo_code['min_order_amount'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($promo_code['is_active']): ?>
                                            <span class="badge bg-success">Активен</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Неактивен</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($promo_code['starts_at'] ?? 'now'))); ?></td>
                                    <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($promo_code['expires_at'] ?? 'never'))); ?></td>
                                    <td><?php echo htmlspecialchars($promo_code['usage_limit'] ?? 'Безлимит'); ?></td>
                                    <td><?php echo htmlspecialchars($promo_code['usage_count'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($promo_code['created_at']))); ?></td>
                                    <td>
                                        <a href="/admin/edit_promo_code.php?id=<?php echo $promo_code['id']; ?>" class="btn btn-sm btn-warning me-1" title="Редактировать">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                        <a href="#" 
                                           class="btn btn-sm <?php echo $promo_code['is_active'] ? 'btn-danger' : 'btn-success'; ?>" 
                                           title="<?php echo $promo_code['is_active'] ? 'Деактивировать' : 'Активировать'; ?>"
                                           data-bs-toggle="modal" 
                                           data-bs-target="#confirmationModal"
                                           data-bs-message="Вы уверены, что хотите <?php echo $promo_code['is_active'] ? 'деактивировать' : 'активировать'; ?> этот промокод (ID: <?php echo htmlspecialchars($promo_code['id']); ?>, Код: <?php echo htmlspecialchars($promo_code['code']); ?>)?"
                                           data-bs-action-url="/admin/actions/toggle_promo_code_status.php?id=<?php echo $promo_code['id']; ?>&current_status=<?php echo $promo_code['is_active'] ? 'active' : 'inactive'; ?>">
                                            <i class="bi <?php echo $promo_code['is_active'] ? 'bi-x-circle-fill' : 'bi-check-circle-fill'; ?>"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmationModalLabel">Подтверждение действия</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="confirmationModalMessage"><!-- Сообщение подтверждения будет здесь --></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <a href="#" class="btn btn-primary-blue" id="confirmActionBtn">ОК</a>
      </div>
    </div>
  </div>
</div>

<?php include_once 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toastContainer = document.querySelector('.toast-container');
    let toastIdCounter = 0;

    function showToast(message, type = 'success') {
        const toastId = 'toast-' + (toastIdCounter++);
        const toastHTML = `
            <div class="toast align-items-center text-white ${type === 'success' ? 'bg-success' : 'bg-danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true" id="${toastId}" data-bs-delay="5000">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement);
        
        toastElement.addEventListener('hidden.bs.toast', function () {
            toastElement.remove(); // Удаляем элемент из DOM после скрытия
        });
        
        toast.show();
    }

    <?php if ($success_message_js): ?>
        showToast(<?php echo json_encode($success_message_js); ?>, 'success');
    <?php endif; ?>

    <?php if ($error_message_js): ?>
        showToast(<?php echo json_encode($error_message_js); ?>, 'danger');
    <?php endif; ?>

    // --- Confirmation Modal Logic ---
    const confirmationModal = document.getElementById('confirmationModal');
    if (confirmationModal) {
        const modalMessageElement = confirmationModal.querySelector('#confirmationModalMessage');
        const confirmActionBtn = confirmationModal.querySelector('#confirmActionBtn');

        confirmationModal.addEventListener('show.bs.modal', function (event) {
            // Кнопка, которая вызвала модальное окно
            const button = event.relatedTarget;
            
            // Извлекаем информацию из data-* атрибутов
            const message = button.getAttribute('data-bs-message');
            const actionUrl = button.getAttribute('data-bs-action-url');

            // Обновляем содержимое модального окна
            if (modalMessageElement) {
                modalMessageElement.textContent = message;
            }
            if (confirmActionBtn) {
                confirmActionBtn.setAttribute('href', actionUrl);
            }
        });
    }
});
</script> 