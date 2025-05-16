<?php
session_start();
require_once __DIR__ . '/../../config.php'; // Подключение к базе данных
require_once __DIR__ . '/../includes/audit_logger.php'; // Для логирования

// Проверка, залогинен ли пользователь и является ли он администратором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Если не админ, можно вернуть ошибку или редиректнуть
    // В данном случае просто завершим скрипт, т.к. это AJAX-подобный обработчик
    // или редирект на страницу логина с сообщением об ошибке
    header('Location: /admin/manage_promo_codes.php?error_message=' . urlencode('У вас нет прав для выполнения этого действия.'));
    exit;
}

$promo_code_id = $_GET['id'] ?? null;
$current_status = $_GET['current_status'] ?? null;

if (!$promo_code_id || !$current_status) {
    header('Location: /admin/manage_promo_codes.php?error_message=' . urlencode('Необходимые параметры отсутствуют.'));
    exit;
}

$new_status = ($current_status === 'active') ? 0 : 1; // 0 для неактивного, 1 для активного

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE promo_codes SET is_active = :is_active WHERE id = :id");
    $stmt->bindParam(':is_active', $new_status, PDO::PARAM_INT);
    $stmt->bindParam(':id', $promo_code_id, PDO::PARAM_INT);
    $stmt->execute();

    $action_log_detail = $new_status ? 'активировал' : 'деактивировал';
    // Формируем детали для лога
    $log_details = [
        'admin_login' => $_SESSION['user_login'] ?? 'N/A',
        'action_taken' => $action_log_detail,
        'promo_code_id' => $promo_code_id,
        'new_status' => $new_status ? 'active' : 'inactive'
    ];
    log_audit_action('promo_code_status_changed', $_SESSION['user_id'], 'promo_code', (int)$promo_code_id, $log_details);
    
    $pdo->commit();

    $message = $new_status ? 'Промокод успешно активирован.' : 'Промокод успешно деактивирован.';
    header('Location: /admin/manage_promo_codes.php?success_message=' . urlencode($message));
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    log_audit_action('error', $_SESSION['user_id'], "Ошибка при изменении статуса промокода ID {$promo_code_id}: " . $e->getMessage());
    header('Location: /admin/manage_promo_codes.php?error_message=' . urlencode('Ошибка при изменении статуса промокода: ' . $e->getMessage()));
    exit;
}
?> 