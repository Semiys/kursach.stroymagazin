<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="../template/css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <?php include_once "../template/header.php" ?>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <div class="container py-4">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Панель управления</h1>
                <p class="text-muted small mb-0">Добро пожаловать, пользователь!</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-bell me-1"></i> Уведомления
                </button>
                <button class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Новый товар
                </button>
            </div>
        </div>

        <!-- Stats Cards Row -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Всего регистраций</h6>
                                <h3 class="mb-0">8,549</h3>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i>12.5%
                                </small>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-users text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Оборот</h6>
                                <h3 class="mb-0">$24,890</h3>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i>8.2%
                                </small>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-dollar-sign text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Заказы</h6>
                                <h3 class="mb-0">1,236</h3>
                                <small class="text-danger">
                                    <i class="fas fa-arrow-down me-1"></i>3.1%
                                </small>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-shopping-cart text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Рост</h6>
                                <h3 class="mb-0">15.3%</h3>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i>5.8%
                                </small>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="fas fa-chart-line text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="row g-3">
            <!-- Activity Timeline -->
            <div class="col-12 col-lg-8 ">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Уведомления</h5>
                            <button class="btn btn-link text-decoration-none p-0">Показать все</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-primary bg-opacity-10 p-2 rounded">
                                        <i class="fas fa-user-plus text-primary"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Новый пользователь</h6>
                                    <p class="text-muted small mb-0">дата и время</p>
                                </div>
                            </div>
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-success bg-opacity-10 p-2 rounded">
                                        <i class="fas fa-check text-success"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Новый заказ</h6>
                                    <p class="text-muted small mb-0">дата и время</p>
                                </div>
                            </div>
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-success bg-opacity-10 p-2 rounded">
                                        <i class="fas fa-check text-success"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Новый заказ</h6>
                                    <p class="text-muted small mb-0">дата и время</p>
                                </div>
                            </div>
                            <div class="d-flex">
                                <div class="flex-shrink-0">
                                    <div class="bg-warning bg-opacity-10 p-2 rounded">
                                        <i class="fas fa-bell text-warning"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-1">Товар закончился</h6>
                                    <p class="text-muted small mb-0">дата и время</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Инструменты</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a class="btn btn-outline-primary" href="/admin/products.php">
                                <i class="fas fa-cart-shopping me-2"></i>Список товаров
                            </a>
                            <a class="btn btn-outline-success" href="/admin/users.php">
                                <i class="fas fa-users me-2"></i>Список пользователей
                            </a>
                            <a class="btn btn-outline-info" href="/admin/parametrs.php">
                                <i class="fas fa-cog me-2"></i>Параметры товаров
                            </a>
                            <a class="btn btn-outline-warning" href="/admin/categories.php">
                                <i class="fas fa-icons me-2"></i>Категории товаров
                            </a>
                            <a class="btn btn-outline-danger" href="/admin/promoreffs.php">
                                <i class="fas fa-receipt me-2"></i>Реферальная система и промокоды
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <?php include_once "../template/footer.php" ?>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js"
        integrity="sha384-VQqxDN0EQCkWoxt/0vsQvZswzTHUVOImccYmSyhJTp7kGtPed0Qcx8rK9h9YEgx+"
        crossorigin="anonymous"></script>
</body>

</html>