<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Динамический заголовок страницы, если $page_title установлена, иначе стандартный -->
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Панель администратора' : 'Панель администратора'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Кастомные стили для администраторской панели -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="admin-panel-body">

<nav class="navbar navbar-expand-lg navbar-dark admin-navbar mb-0">
    <div class="container-fluid">
        <a class="navbar-brand" href="/admin/index.php">
            <i class="bi bi-shield-shaded"></i> Панель администратора
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbarSupportedContent" aria-controls="adminNavbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNavbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active-blue' : ''; ?>" href="/admin/index.php"><i class="bi bi-house-door-fill me-2"></i>Главная</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_products.php' || basename($_SERVER['PHP_SELF']) == 'edit_product.php' ? 'active-blue' : ''; ?>" href="/admin/manage_products.php"><i class="bi bi-box-seam-fill me-2"></i>Товары</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_orders.php' || basename($_SERVER['PHP_SELF']) == 'view_order.php' ? 'active-blue' : ''; ?>" href="/admin/manage_orders.php"><i class="bi bi-list-check me-2"></i>Заказы</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active-blue' : ''; ?>" href="/admin/manage_users.php"><i class="bi bi-people-fill me-2"></i>Пользователи</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_product_categories_text.php' ? 'active-blue' : ''; ?>" href="/admin/manage_product_categories_text.php"><i class="bi bi-tags-fill me-2"></i>Категории</a>
                </li>
                <!-- Сюда можно будет добавить специфичные для админа пункты меню, например, Журнал аудита -->
                 <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active-blue' : ''; ?>" href="/admin/audit_logs.php"><i class="bi bi-journal-text me-2"></i>Журнал аудита</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_promo_codes.php' || basename($_SERVER['PHP_SELF']) == 'edit_promo_code.php' ? 'active-blue' : ''; ?>" href="/admin/manage_promo_codes.php"><i class="bi bi-ticket-perforated-fill me-2"></i>Промокоды</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/" target="_blank"><i class="bi bi-box-arrow-up-right me-2"></i>На сайт</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Выход</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="main-content-flex"> <!-- Основной контейнер для контента страницы. Закрывается в footer.php -->
    <!-- <main> Этот тег был здесь, но Bootstrap обычно использует контейнеры напрямую -->
