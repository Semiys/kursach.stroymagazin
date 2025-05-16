<?php
session_start();
require_once __DIR__ . '/../config.php'; // Подключение к БД и конфигурации

// Проверка, что пользователь авторизован и является администратором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php"); // Перенаправление на главную страницу или страницу входа
    exit;
}

$page_title = "Журнал аудита";
include_once 'header.php';

/*
SQL для создания таблицы audit_log (пример для MySQL):

CREATE TABLE `audit_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NULL DEFAULT NULL COMMENT 'ID пользователя, совершившего действие (если применимо)',
  `action` VARCHAR(255) NOT NULL COMMENT 'Краткое описание действия, например, user_login, product_update, role_changed',
  `target_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Тип объекта, над которым совершено действие (user, product, order)',
  `target_id` INT NULL DEFAULT NULL COMMENT 'ID объекта, над которым совершено действие',
  `details` TEXT NULL DEFAULT NULL COMMENT 'Дополнительные сведения в формате JSON или текст (старое значение, новое значение)',
  `ip_address` VARCHAR(45) NULL DEFAULT NULL COMMENT 'IP-адрес, с которого было совершено действие',
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id` ASC),
  INDEX `idx_action` (`action` ASC),
  INDEX `idx_target_type_id` (`target_type` ASC, `target_id` ASC),
  INDEX `idx_timestamp` (`timestamp` DESC),
  CONSTRAINT `fk_audit_log_user_id`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Таблица для записи действий в системе';

*/

$logs = [];
$error_message = '';

// Параметры для пагинации
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$logs_per_page = 20;
$offset = ($page - 1) * $logs_per_page;

try {
    // Проверяем, существует ли таблица audit_log
    $table_check_stmt = $pdo->query("SHOW TABLES LIKE 'audit_log'");
    $table_exists = $table_check_stmt->rowCount() > 0;

    if ($table_exists) {
        // Получаем общее количество записей для пагинации
        $total_stmt = $pdo->query("SELECT COUNT(*) FROM audit_log");
        $total_logs = $total_stmt->fetchColumn();
        $total_pages = ceil($total_logs / $logs_per_page);

        // Получаем записи для текущей страницы
        // Присоединяем users для получения email или ФИО пользователя
        $sql = "SELECT al.*, u.email as user_email, u.name as user_name 
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.timestamp DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $logs_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error_message = "Таблица 'audit_log' не найдена в базе данных. Пожалуйста, создайте ее для просмотра журнала аудита.";
    }

} catch (PDOException $e) {
    $error_message = "Ошибка при загрузке журнала аудита: " . $e->getMessage();
    // Можно также логировать $e->getMessage() в файл ошибок сервера
}

?>

<div class="container pt-4">
    <h4 class="mb-3"><?php echo htmlspecialchars($page_title); ?></h4>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if ($table_exists && empty($logs) && empty($error_message)): ?>
        <div class="alert alert-info" role="alert">
            Записи в журнале аудита отсутствуют.
        </div>
    <?php elseif (!empty($logs)): ?>
        <p>Всего записей: <?php echo $total_logs; ?></p>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>ID</th>
                        <th>Время</th>
                        <th>Пользователь</th>
                        <th>Действие</th>
                        <th>Объект</th>
                        <th>ID объекта</th>
                        <th>Детали</th>
                        <th>IP адрес</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['id']); ?></td>
                            <td><?php echo htmlspecialchars(date("d.m.Y H:i:s", strtotime($log['timestamp']))); ?></td>
                            <td>
                                <?php 
                                if ($log['user_id']) {
                                    echo htmlspecialchars($log['user_name'] ?: ($log['user_email'] ?: 'ID: ' . $log['user_id']));
                                } else {
                                    echo 'Система';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['target_type'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($log['target_id'] ?: '-'); ?></td>
                            <td>
                                <small style="max-width: 300px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                       title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($log['details'] ?? '-'); ?>
                                </small>
                                <?php if (is_string($log['details']) && strlen($log['details']) > 50): // Показываем кнопку, если текст длинный и не null ?>
                                <button class="btn btn-sm btn-outline-secondary ms-1 view-details-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#detailsModal" 
                                        data-details="<?php echo htmlspecialchars($log['details']); ?>">
                                    <i class="bi bi-search"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?: '-'); ?></td>
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
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Модальное окно для просмотра полных деталей -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailsModalLabel">Полные детали записи аудита</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <pre id="fullDetailsContent" style="white-space: pre-wrap; word-break: break-all;"></pre>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const fullDetailsContent = document.getElementById('fullDetailsContent');

    document.querySelectorAll('.view-details-btn').forEach(button => {
        button.addEventListener('click', function () {
            const details = this.dataset.details;
            fullDetailsContent.textContent = details;
            // detailsModal.show(); // Bootstrap 5.3+ should handle this with data-bs-toggle/target
        });
    });
});
</script>

<?php include_once 'footer.php'; ?> 