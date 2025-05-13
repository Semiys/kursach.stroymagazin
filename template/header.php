<?php
session_start(); // Запускаем сессию, если она еще не запущена
?>
<nav class="navbar navbar-expand-lg bg-body-tertiary" data-bs-theme="dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="/index.php">СтройМаркет</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="/index.php">Главная</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="/main/catalogue.php">Каталог</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="/main/cart.php">
                        Корзина 
                        <?php 
                            // Отображаем общее количество товаров в корзине (сумма всех quantity)
                            $total_quantity_in_header = 0;
                            if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                                $total_quantity_in_header = array_sum($_SESSION['cart']);
                            }
                            $badge_class = ($total_quantity_in_header > 0) ? '' : ' d-none'; // Добавляем d-none если пусто
                        ?>
                        <span class="badge bg-primary rounded-pill cart-total-quantity-badge<?php echo $badge_class; ?>">
                            <?php 
                                echo $total_quantity_in_header > 0 ? $total_quantity_in_header : ''; 
                            ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                        <a class="nav-link" aria-current="page" href="/main/profile.php">Мой профиль (<?php echo htmlspecialchars($_SESSION['user_email']); ?>)</a>
                </li>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): // Пример проверки на админа ?>
                <li class="nav-item">
                            <a class="nav-link link-danger" aria-current="page" href="/admin/dashboard.php">Панель управления</a>
                </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <div class="d-flex">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/main/logout.php" class="btn btn-outline-danger">Выйти</a>
                <?php else: ?>
                    <a href="/main/login.php" class="btn btn-outline-success me-2">Вход</a>
                    <a href="/main/register.php" class="btn btn-outline-primary">Регистрация</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>