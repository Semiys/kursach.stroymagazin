<?php
session_start(); // Начинаем или возобновляем сессию

// Подключаем файл конфигурации для доступа к $pdo, если он понадобится для других операций
// require_once __DIR__ . '/../config.php'; 

// Проверяем, залогинен ли пользователь и является ли он администратором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Если не администратор, перенаправляем на главную страницу сайта (или на страницу логина)
    // Можно также добавить сообщение во flash-сессию, если у вас такая система есть
    // $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'У вас нет прав доступа к этому разделу.'];
    header("Location: /index.php"); // Перенаправляем на главную сайта
    exit;
}

// Если проверки пройдены, пользователь является администратором
// Подключаем заголовок
$page_title = "Главная - Панель Администратора"; // Для динамического заголовка в header.php
include_once 'header.php';
?>

<div class="container pt-0 mt-0">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="card-title">Добро пожаловать, <?php echo htmlspecialchars($_SESSION['user_login'] ?? 'Администратор'); ?>!</h4>
                    <p class="card-text">Это главная страница панели администратора. Здесь вы можете управлять ключевыми аспектами сайта и системы.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5><i class="bi bi-tools"></i> Основные функции управления</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="manage_products.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-box-seam-fill me-2"></i>Управление товарами
                        <small class="text-muted d-block">Добавление, редактирование, просмотр товаров</small>
                    </a>
                    <a href="manage_product_categories_text.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-tags-fill me-2"></i>Управление категориями 
                        <small class="text-muted d-block">Создание и редактирование категорий товаров</small>
                    </a>
                    <a href="manage_orders.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-list-check me-2"></i>Управление заказами 
                        <small class="text-muted d-block">Просмотр и изменение статусов заказов</small>
                    </a>
                    <a href="manage_users.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-people-fill me-2"></i>Управление пользователями 
                        <small class="text-muted d-block">Просмотр информации и управление ролями пользователей</small>
                    </a>
                    <a href="manage_promo_codes.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-ticket-perforated-fill me-2"></i>Управление промокодами
                        <small class="text-muted d-block">Создание, редактирование и управление промокодами</small>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5><i class="bi bi-shield-lock-fill"></i> Администрирование системы</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="audit_logs.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-journal-text me-2"></i>Журнал аудита
                        <small class="text-muted d-block">Просмотр системных событий и действий пользователей</small>
                    </a>
                    <!-- Сюда можно будет добавить другие административные функции -->
                    <!-- <a href="system_settings.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-sliders me-2"></i>Системные настройки
                        <small class="text-muted d-block">Управление глобальными настройками платформы</small>
                    </a> -->
                </div>
            </div>
        </div>
    </div>
    
</div>

<?php
// Подключаем подвал
include_once 'footer.php';
?> 