<?php
session_start(); // Запускаем сессию, если она еще не запущена

// Попытка обновить роль пользователя из БД, если он залогинен
// Это предполагает, что $pdo (из config.php) доступен в этой области видимости
if (isset($_SESSION['user_id']) && isset($pdo)) { // Убедимся, что $pdo существует
    try {
        $stmt_role_check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt_role_check->execute([$_SESSION['user_id']]);
        $current_db_role = $stmt_role_check->fetchColumn();

        if ($current_db_role && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $current_db_role)) {
            $_SESSION['user_role'] = $current_db_role;
        }
    } catch (PDOException $e) {
        // Ошибку можно залогировать, но не прерывать работу хедера
        // error_log("Error fetching user role for session update: " . $e->getMessage());
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: var(--orange-primary);">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="/">
            <i class="bi bi-building me-2" style="color: white;"></i>
            <span style="color: black; font-weight: 600;">СтройМаркет</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon" style="color:white;"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/" style="color: black; font-weight: 500;">Главная</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/main/catalogue.php" style="color: black; font-weight: 500;">Каталог</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <?php if (isset($_SESSION['user_id'])):
                    // Если пользователь авторизован, показываем кнопку Профиль
                    // (Предполагается, что если есть user_id, то есть и user_login)
                    $user_login = isset($_SESSION['user_login']) ? $_SESSION['user_login'] : 'Пользователь';
                        ?>
                    <a href="/main/profile.php" class="btn me-2" style="background-color: var(--orange-dark); border-color: var(--orange-dark); color: white;">
                        <i class="bi bi-person-fill me-1"></i>Профиль
                    </a>
                    <?php 
                    // Добавляем кнопку для модераторской панели, если роль пользователя - moder
                    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'moder'): 
                    ?>
                        <a href="/moderator/index.php" class="btn me-2 btn-warning" style="color: black;">
                            <i class="bi bi-shield-lock-fill me-1"></i>Модерка
                        </a>
                    <?php 
                    endif; 
                    // Добавляем кнопку для администраторской панели, если роль пользователя - admin
                    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): 
                    ?>
                        <a href="/admin/index.php" class="btn me-2 btn-danger" style="color: white;"> <!-- Используем btn-danger для отличия -->
                            <i class="bi bi-shield-shaded me-1"></i>Админка
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/main/login.php" class="btn me-2" style="background-color: var(--orange-dark); border-color: var(--orange-dark); color: white;">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Войти
                    </a>
                    <a href="/main/register.php" class="btn me-2" style="background-color: var(--orange-dark); border-color: var(--orange-dark); color: white;">
                        <i class="bi bi-person-plus-fill me-1"></i>Регистрация
                    </a>
                <?php endif; ?>
                <a href="/main/cart.php" class="btn position-relative" style="background-color: var(--dark-gray); color: white;">
                    <i class="bi bi-cart3 me-1"></i>Корзина
                    <?php
                    // Отображаем количество товаров в корзине
                    $cartCount = 0;
                    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
                        foreach ($_SESSION['cart'] as $quantity) {
                            $cartCount += $quantity;
                        }
                    }
                    // Добавляем класс cart-total-quantity-badge и d-none если корзина пуста
                    $badge_d_none_class = ($cartCount > 0) ? '' : ' d-none';
                    echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill cart-total-quantity-badge' . $badge_d_none_class . '" style="background-color: var(--complement-green);">';
                    if ($cartCount > 0) {
                        echo $cartCount;
                    }
                    echo '</span>';
                    ?>
                </a>
            </div>
        </div>
    </div>
</nav>