<?php
include '../template/header.php';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Заказ успешно оформлен</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="../template/css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <?php if (isset($_SESSION['flash_message'])): ?>
    <div class="container mt-3">
        <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['flash_message']['text']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php unset($_SESSION['flash_message']); endif; ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card text-center">
                    <div class="card-body p-5">
                        <div class="mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                        </div>
                        <h2 class="card-title mb-3">Заказ успешно оформлен!</h2>
                        <?php if (isset($_SESSION['last_order_number'])): ?>
                        <h4 class="mb-3">Номер вашего заказа: <strong><?php echo htmlspecialchars($_SESSION['last_order_number']); ?></strong></h4>
                        <?php endif; ?>
                        <p class="card-text mb-4">Благодарим за покупку! Уведомление о заказе отправлено на вашу электронную почту.</p>
                        <div class="d-flex justify-content-center mt-4">
                            <a href="/main/catalogue.php" class="btn btn-primary me-2">Вернуться в каталог</a>
                            <a href="/" class="btn btn-outline-secondary">На главную</a>
                        </div>
                    </div>
                </div>
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Что дальше?</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-envelope me-3 text-primary"></i>
                                <div>
                                    <strong>Проверьте почту</strong>
                                    <p class="mb-0 text-muted">Мы отправили подтверждение заказа на вашу электронную почту</p>
                                </div>
                            </li>
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-box-seam me-3 text-primary"></i>
                                <div>
                                    <strong>Отслеживание</strong>
                                    <p class="mb-0 text-muted">Когда ваш заказ будет отправлен, вы получите уведомление с номером для отслеживания</p>
                                </div>
                            </li>
                            <li class="list-group-item d-flex align-items-center">
                                <i class="bi bi-question-circle me-3 text-primary"></i>
                                <div>
                                    <strong>Поддержка</strong>
                                    <p class="mb-0 text-muted">Если у вас возникли вопросы, свяжитесь с нашей службой поддержки</p>
                                </div>
                            </li>
                        </ul>
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