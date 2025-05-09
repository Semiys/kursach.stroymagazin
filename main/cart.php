<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="../template/css/main.css">
</head>

<body>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <?php include_once "../template/header.php" ?>
    <div class="container py-5">
        <h4 class="mb-4">Корзина</h4>
        <div class="row">
            <div class="col-lg-8">
                <!-- Cart Items -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row cart-item mb-3">
                            <div class="col-md-3">
                                <img src="../template/assets/500x500.png" alt="Product 1" class="img-fluid rounded">
                            </div>
                            <div class="col-md-5">
                                <h5 class="card-title">Product 1</h5>
                                <p class="text-muted">Категория: Electronics</p>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group">
                                    <button class="btn btn-outline-secondary btn-sm" type="button">-</button>
                                    <input style="max-width:100px" type="text"
                                        class="form-control  form-control-sm text-center quantity-input" value="1">
                                    <button class="btn btn-outline-secondary btn-sm" type="button">+</button>
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                <p class="fw-bold">99.99₽</p>
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                        <hr>
                        <div class="row cart-item">
                            <div class="col-md-3">
                                <img src="../template/assets/500x500.png" alt="Product 2" class="img-fluid rounded">
                            </div>
                            <div class="col-md-5">
                                <h5 class="card-title">Product 2</h5>
                                <p class="text-muted">Категория: Clothing</p>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group">
                                    <button class="btn btn-outline-secondary btn-sm" type="button">-</button>
                                    <input style="max-width:100px" type="text"
                                        class="form-control form-control-sm text-center quantity-input" value="2">
                                    <button class="btn btn-outline-secondary btn-sm" type="button">+</button>
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                <p class="fw-bold">49.99₽</p>
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Continue Shopping Button -->
                <div class="text-start mb-4">
                    <a href="/main/catalogue.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>Продолжить покупки
                    </a>
                </div>
            </div>
            <div class="col-lg-4">
                <!-- Cart Summary -->
                <div class="card cart-summary">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Сумма заказа</h5>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Общая стоимость</span>
                            <span>199.97₽</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Доставка</span>
                            <span>10.00₽</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Скидка</span>
                            <span>20.00₽</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-4">
                            <strong>Итого</strong>
                            <strong>229.97₽</strong>
                        </div>
                        <button class="btn btn-primary w-100">Перейти к оплате</button>
                    </div>
                </div>
                <!-- Promo Code -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Активировать промокод</h5>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" placeholder="Введите сюда промо-слово">
                            <button class="btn btn-outline-secondary" type="button">Применить</button>
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