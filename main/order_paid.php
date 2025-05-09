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

    <main class="container mt-4">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle text-success" style="font-size: 64px;"></i>
                            <h2 class="mt-3">Оплата прошла успешно!</h2>
                            <p class="lead">Заказ №80 успешно оплачен</p>
                            <p>Сумма: 1 520.00₽</p>
                            <hr>
                            <p>Спасибо за покупку! Вы можете отслеживать статус заказа в <a
                                    href="../main/profile.php">своем профиле</a>.</p>
                            <a href="../index.php" class="btn btn-primary">Вернуться на главную</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>


    <?php include_once "../template/footer.php" ?>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js"
        integrity="sha384-VQqxDN0EQCkWoxt/0vsQvZswzTHUVOImccYmSyhJTp7kGtPed0Qcx8rK9h9YEgx+"
        crossorigin="anonymous"></script>
</body>

</html>