<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/audit_logger.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /index.php");
    exit;
}

$page_title = "Добавить/Редактировать промокод";
$is_editing = false;
$promo_code_id = null;
$errors = [];
$success_message = '';

// Инициализация данных промокода значениями по умолчанию
$promo_data = [
    'code' => '',
    'discount_type' => 'percentage',
    'discount_value' => '',
    'min_order_amount' => null,
    'is_active' => 1,
    'starts_at' => date('Y-m-d\TH:i'), // Текущая дата и время
    'expires_at' => null,
    'usage_limit' => null
];

if (isset($_GET['id'])) {
    $is_editing = true;
    $promo_code_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($promo_code_id) {
        try {
            $stmt = $pdo->prepare("SELECT *, DATE_FORMAT(starts_at, '%Y-%m-%dT%H:%i') as formatted_starts_at, DATE_FORMAT(expires_at, '%Y-%m-%dT%H:%i') as formatted_expires_at FROM promo_codes WHERE id = ?");
            $stmt->execute([$promo_code_id]);
            $fetched_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($fetched_data) {
                $promo_data = $fetched_data;
                // Используем отформатированные даты для полей формы
                $promo_data['starts_at'] = $fetched_data['formatted_starts_at'] ?? date('Y-m-d\TH:i');
                if ($fetched_data['formatted_expires_at']) {
                     $promo_data['expires_at'] = $fetched_data['formatted_expires_at'];
                } else {
                    $promo_data['expires_at'] = null; // Явно null, если нет даты окончания
                }
            } else {
                header("Location: manage_promo_codes.php?error_message=" . urlencode("Промокод не найден."));
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "Ошибка загрузки данных промокода: " . $e->getMessage();
        }
    } else {
        header("Location: manage_promo_codes.php?error_message=" . urlencode("Неверный ID промокода."));
        exit;
    }
    $page_title = "Редактировать промокод: " . htmlspecialchars($promo_data['code']);
} else {
    $page_title = "Добавить новый промокод";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Собираем данные из POST
    $promo_data['code'] = trim($_POST['code'] ?? '');
    $promo_data['discount_type'] = $_POST['discount_type'] ?? 'percentage';
    $promo_data['discount_value'] = trim($_POST['discount_value'] ?? '');
    $promo_data['min_order_amount'] = !empty(trim($_POST['min_order_amount'])) ? trim($_POST['min_order_amount']) : null;
    $promo_data['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $promo_data['starts_at'] = $_POST['starts_at'] ?? date('Y-m-d\TH:i');
    $promo_data['expires_at'] = !empty(trim($_POST['expires_at'])) ? trim($_POST['expires_at']) : null;
    $promo_data['usage_limit'] = !empty(trim($_POST['usage_limit'])) ? trim($_POST['usage_limit']) : null;

    // Валидация
    if (empty($promo_data['code'])) {
        $errors[] = "Код промокода обязателен.";
    } else {
        // Проверка уникальности кода (кроме случая редактирования того же самого промокода)
        $sql_check_code = "SELECT id FROM promo_codes WHERE code = ?";
        $params_check_code = [$promo_data['code']];
        if ($is_editing) {
            $sql_check_code .= " AND id != ?";
            $params_check_code[] = $promo_code_id;
        }
        $stmt_check = $pdo->prepare($sql_check_code);
        $stmt_check->execute($params_check_code);
        if ($stmt_check->fetch()) {
            $errors[] = "Промокод с таким кодом уже существует.";
        }
    }

    if (!in_array($promo_data['discount_type'], ['percentage', 'fixed'])) {
        $errors[] = "Неверный тип скидки.";
    }
    if (!is_numeric($promo_data['discount_value']) || $promo_data['discount_value'] <= 0) {
        $errors[] = "Значение скидки должно быть положительным числом.";
    }
    if ($promo_data['discount_type'] == 'percentage' && $promo_data['discount_value'] > 100) {
        $errors[] = "Скидка в процентах не может быть больше 100.";
    }
    if ($promo_data['min_order_amount'] !== null && (!is_numeric($promo_data['min_order_amount']) || $promo_data['min_order_amount'] < 0)) {
        $errors[] = "Минимальная сумма заказа должна быть числом (или пустой).";
    }
     if ($promo_data['usage_limit'] !== null && (!is_numeric($promo_data['usage_limit']) || intval($promo_data['usage_limit']) < 0)) {
        $errors[] = "Лимит использования должен быть целым положительным числом (или пустым).";
    }
    
    // Валидация дат
    if (empty($promo_data['starts_at'])) {
        $errors[] = "Дата начала обязательна.";
    } else {
        $start_timestamp = strtotime($promo_data['starts_at']);
        if ($start_timestamp === false) {
            $errors[] = "Неверный формат даты начала.";
        }
    }
    if ($promo_data['expires_at'] !== null) {
        $end_timestamp = strtotime($promo_data['expires_at']);
        if ($end_timestamp === false) {
            $errors[] = "Неверный формат даты окончания.";
        } elseif (isset($start_timestamp) && $end_timestamp < $start_timestamp) {
            $errors[] = "Дата окончания не может быть раньше даты начала.";
        }
    }


    if (empty($errors)) {
        try {
            $action_type = '';
            if ($is_editing) {
                $sql = "UPDATE promo_codes SET 
                            code = :code, 
                            discount_type = :discount_type, 
                            discount_value = :discount_value, 
                            min_order_amount = :min_order_amount, 
                            is_active = :is_active, 
                            starts_at = :starts_at, 
                            expires_at = :expires_at, 
                            usage_limit = :usage_limit 
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $promo_code_id, PDO::PARAM_INT);
                $action_type = 'PROMO_CODE_UPDATED';
                $success_msg_text = "Промокод успешно обновлен.";
            } else {
                $sql = "INSERT INTO promo_codes 
                            (code, discount_type, discount_value, min_order_amount, is_active, starts_at, expires_at, usage_limit, usage_count, created_at) 
                        VALUES 
                            (:code, :discount_type, :discount_value, :min_order_amount, :is_active, :starts_at, :expires_at, :usage_limit, 0, NOW())";
                $stmt = $pdo->prepare($sql);
                $action_type = 'PROMO_CODE_CREATED';
                $success_msg_text = "Промокод успешно добавлен.";
            }

            $stmt->bindParam(':code', $promo_data['code']);
            $stmt->bindParam(':discount_type', $promo_data['discount_type']);
            $stmt->bindParam(':discount_value', $promo_data['discount_value']);
            $stmt->bindParam(':min_order_amount', $promo_data['min_order_amount']);
            $stmt->bindParam(':is_active', $promo_data['is_active'], PDO::PARAM_INT);
            $stmt->bindParam(':starts_at', $promo_data['starts_at']);
            $stmt->bindParam(':expires_at', $promo_data['expires_at']);
            $stmt->bindParam(':usage_limit', $promo_data['usage_limit']);
            
            $stmt->execute();
            $target_id = $is_editing ? $promo_code_id : $pdo->lastInsertId();

            log_audit_action($action_type, $_SESSION['user_id'], 'promo_code', (int)$target_id, ['code' => $promo_data['code']]);
            header("Location: manage_promo_codes.php?success_message=" . urlencode($success_msg_text));
            exit;

        } catch (PDOException $e) {
            $errors[] = "Ошибка базы данных: " . $e->getMessage();
        }
    }
}

include_once 'header.php';
?>

<div class="container mt-4">
    <h2><?php echo $page_title; ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Обнаружены ошибки:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="edit_promo_code.php<?php echo $is_editing ? '?id=' . $promo_code_id : ''; ?>" method="POST">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="code" class="form-label">Код промокода <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code" value="<?php echo htmlspecialchars($promo_data['code']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="discount_type" class="form-label">Тип скидки <span class="text-danger">*</span></label>
                        <select class="form-select" id="discount_type" name="discount_type" required>
                            <option value="percentage" <?php echo ($promo_data['discount_type'] == 'percentage') ? 'selected' : ''; ?>>Процент (%)</option>
                            <option value="fixed" <?php echo ($promo_data['discount_type'] == 'fixed') ? 'selected' : ''; ?>>Фиксированная сумма</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="discount_value" class="form-label">Значение скидки <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="discount_value" name="discount_value" value="<?php echo htmlspecialchars($promo_data['discount_value']); ?>" step="0.01" min="0.01" required>
                        <small class="form-text text-muted">Для процентов - от 1 до 100. Для фикс. суммы - в валюте магазина.</small>
                    </div>
                     <div class="col-md-6 mb-3">
                        <label for="min_order_amount" class="form-label">Минимальная сумма заказа (необязательно)</label>
                        <input type="number" class="form-control" id="min_order_amount" name="min_order_amount" value="<?php echo htmlspecialchars($promo_data['min_order_amount'] ?? ''); ?>" step="0.01" min="0">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="starts_at" class="form-label">Дата начала <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="starts_at" name="starts_at" value="<?php echo htmlspecialchars($promo_data['starts_at']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="expires_at" class="form-label">Дата окончания (необязательно)</label>
                        <input type="datetime-local" class="form-control" id="expires_at" name="expires_at" value="<?php echo htmlspecialchars($promo_data['expires_at'] ?? ''); ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="usage_limit" class="form-label">Лимит общего использования (необязательно)</label>
                        <input type="number" class="form-control" id="usage_limit" name="usage_limit" value="<?php echo htmlspecialchars($promo_data['usage_limit'] ?? ''); ?>" min="0" step="1">
                        <small class="form-text text-muted">Оставьте пустым или 0 для безлимитного использования.</small>
                    </div>
                    <div class="col-md-6 mb-3 align-self-center">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($promo_data['is_active'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                Промокод активен
                            </label>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end">
                    <a href="manage_promo_codes.php" class="btn btn-secondary me-2">
                        <i class="bi bi-x-lg me-1"></i>Отмена
                    </a>
                    <button type="submit" class="btn btn-primary-blue">
                        <i class="bi <?php echo $is_editing ? 'bi-save-fill' : 'bi-plus-circle-fill'; ?> me-1"></i><?php echo $is_editing ? 'Сохранить изменения' : 'Добавить промокод'; ?>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include_once 'footer.php'; ?> 