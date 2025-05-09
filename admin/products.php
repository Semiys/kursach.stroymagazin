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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php include_once "../template/header.php" ?>
    <div class="container mt-5">
        <h4 class="mt-5">Список товаров</h4>
        <table class="table table-hover mt-4">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Название</th>
                    <th scope="col">Категория</th>
                    <th scope="col">Склад</th>
                    <th scope="col">Хит</th>
                    <th scope="col">Скидка</th>
                    <th scope="col">Статус</th>
                    <th scope="col">Действия</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">1</th>
                    <td>Товар</td>
                    <td>Погонажные изделия</td>
                    <td>50</td>
                    <td>Да</td>
                    <td>0%</td>
                    <td>Активен</td>
                    <td>
                        <button type="button" class="btn"><i class="fas fa-pencil text-success"></i></button>
                        <button type="button" class="btn "><i class="fas fa-trash text-danger"></i></button>
                    </td>
                </tr>
                <tr>
                    <th scope="row">1</th>
                    <td>Товар</td>
                    <td>Погонажные изделия</td>
                    <td>50</td>
                    <td>Да</td>
                    <td>0%</td>
                    <td>Активен</td>
                    <td>
                        <button type="button" class="btn"><i class="fas fa-pencil text-success"></i></button>
                        <button type="button" class="btn "><i class="fas fa-trash text-danger"></i></button>
                    </td>
                </tr>
            </tbody>
        </table>
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