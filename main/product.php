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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php include_once "../template/header.php" ?>



    <div class="container mt-5">
        <div class="row">
            <!-- Product Images -->
            <div class="col-md-6 mb-4">
                <img src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w0NzEyNjZ8MHwxfHNlYXJjaHwxfHxoZWFkcGhvbmV8ZW58MHwwfHx8MTcyMTMwMzY5MHww&ixlib=rb-4.0.3&q=80&w=1080"
                    alt="Product" class="img-fluid rounded mb-3 " id="mainImage">
                <div class="d-flex justify-content-between">
                    <img src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w0NzEyNjZ8MHwxfHNlYXJjaHwxfHxoZWFkcGhvbmV8ZW58MHwwfHx8MTcyMTMwMzY5MHww&ixlib=rb-4.0.3&q=80&w=1080"
                        alt="Thumbnail 1" class="thumbnail rounded active" onclick="changeImage(event, this.src)">
                    <img src="https://images.unsplash.com/photo-1528148343865-51218c4a13e6?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w0NzEyNjZ8MHwxfHNlYXJjaHwzfHxoZWFkcGhvbmV8ZW58MHwwfHx8MTcyMTMwMzY5MHww&ixlib=rb-4.0.3&q=80&w=1080"
                        alt="Thumbnail 2" class="thumbnail rounded" onclick="changeImage(event, this.src)">
                    <img src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w0NzEyNjZ8MHwxfHNlYXJjaHwxfHxoZWFkcGhvbmV8ZW58MHwwfHx8MTcyMTMwMzY5MHww&ixlib=rb-4.0.3&q=80&w=1080"
                        alt="Thumbnail 3" class="thumbnail rounded" onclick="changeImage(event, this.src)">
                    <img src="https://images.unsplash.com/photo-1528148343865-51218c4a13e6?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w0NzEyNjZ8MHwxfHNlYXJjaHwzfHxoZWFkcGhvbmV8ZW58MHwwfHx8MTcyMTMwMzY5MHww&ixlib=rb-4.0.3&q=80&w=1080"
                        alt="Thumbnail 4" class="thumbnail rounded" onclick="changeImage(event, this.src)">
                </div>
            </div>

            <!-- Product Details -->
            <div class="col-md-6">
                <h2 class="mb-3">Premium Wireless Headphones</h2>
                <p class="text-muted mb-4">Артикул: 80</p>
                <div class="mb-3">
                    <span class="h4 me-2">349.99₽</span>
                    <span class="text-muted"><s>499.99₽</s></span>
                </div>
                <div class="mb-3">
                    <i class="bi bi-star-fill text-warning"></i>
                    <i class="bi bi-star-fill text-warning"></i>
                    <i class="bi bi-star-fill text-warning"></i>
                    <i class="bi bi-star-fill text-warning"></i>
                    <i class="bi bi-star-half text-warning"></i>
                    <span class="ms-2">4.5 (120 оценок)</span>
                </div>
                <p class="mb-4">Experience premium sound quality and industry-leading noise cancellation with these
                    wireless
                    headphones. Perfect for music lovers and frequent travelers.</p>
                <button class="btn btn-primary btn-lg mb-3 me-2">
                    <i class="bi bi-cart-plus"></i> Добавить в корзину
                </button>
                <button class="btn btn-outline-secondary btn-lg mb-3">
                    <i class="bi bi-heart"></i> Оценить товар
                </button>
                <div class="mt-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-truck text-primary me-2"></i>
                        <span>Беслплатная доставка от 5000 рублей</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-undo text-primary me-2"></i>
                        <span>Политика возврата в течении 30 суток</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-shield-alt text-primary me-2"></i>
                        <span>Гарантия на все товары 2 года</span>
                    </div>
                </div>
            </div>
            <div class="cold-md-12 mt-3">
                <h4 class="mb-3">Характеристики товара:</h4>
                <ul>
                    <li>
                        <p><b>123: </b>123</p>
                    </li>
                    <li>
                        <p><b>123: </b>123</p>
                    </li>
                    <li>
                        <p><b>123: </b>123</p>
                    </li>
                    <li>
                        <p><b>123: </b>123</p>
                    </li>
                    <li>
                        <p><b>123: </b>123</p>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function changeImage(event, src) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.thumbnail').forEach(thumb => thumb.classList.remove('active'));
            event.target.classList.add('active');
        }
    </script>




    <?php include_once "../template/footer.php" ?>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js"
        integrity="sha384-VQqxDN0EQCkWoxt/0vsQvZswzTHUVOImccYmSyhJTp7kGtPed0Qcx8rK9h9YEgx+"
        crossorigin="anonymous"></script>
</body>

</html>