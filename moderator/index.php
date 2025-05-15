<?php
session_start(); // Начинаем или возобновляем сессию

// Подключаем файл конфигурации для доступа к $pdo, если он понадобится для других операций
// require_once __DIR__ . '/../config.php'; 

// Проверяем, залогинен ли пользователь и является ли он модератором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'moder') {
    // Если не модератор, перенаправляем на главную страницу сайта (или на страницу логина)
    // Можно также добавить сообщение во flash-сессию, если у вас такая система есть
    // $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'У вас нет прав доступа к этому разделу.'];
    header("Location: /index.php"); // Перенаправляем на главную сайта
    exit;
}

// Если проверки пройдены, пользователь является модератором
// Подключаем заголовок
$page_title = "Главная"; // Для динамического заголовка в header.php
include_once 'header.php';
?>

<div class="container pt-0 mt-0">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="card-title">Добро пожаловать, <?php echo htmlspecialchars($_SESSION['user_login'] ?? 'Модератор'); ?>!</h4>
                    <p class="card-text">Это главная страница модераторской панели. Здесь вы можете управлять различными аспектами сайта.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5><i class="bi bi-tools"></i> Основные функции</h5>
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
                        <small class="text-muted d-block">Просмотр информации о пользователях</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Кнопка выхода убрана, так как есть в основной навигации -->
    <!-- <p><a href="logout.php" class="btn btn-danger">Выйти из модераторской панели</a></p> -->
</div>

<?php
// Подключаем подвал
include_once 'footer.php';
?> 