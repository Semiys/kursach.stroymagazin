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
        <!-- Top Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Каталог</h4>
            <div class="d-flex gap-2 align-items-center">
                <span class="text-muted">Сортировка:</span>
                <button class="sort-btn">
                    Newest <i class="bi bi-chevron-down ms-2"></i>
                </button>
            </div>
        </div>

        <div class="col-md-12 mb-4">
            <form class="d-flex">
                <div class="input-group">
                    <input class="form-control form-control-lg" type="search" placeholder="Поиск по товарам или категориям" aria-label="Search">
                    <button class="btn btn-primary px-4" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="row g-4">
            <!-- Filters Sidebar -->
            <div class="col-lg-3">
                <div class="filter-sidebar p-4 shadow-sm">
                    <div class="filter-group">
                        <h6 class="mb-3">Категории</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="electronics">
                            <label class="form-check-label" for="electronics">
                                Electronics
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="clothing">
                            <label class="form-check-label" for="clothing">
                                Clothing
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="accessories">
                            <label class="form-check-label" for="accessories">
                                Accessories
                            </label>
                        </div>
                    </div>

                    <div class="filter-group">
                        <h6 class="mb-3">Диапазон цены</h6>
                        <input type="range" class="form-range" min="0" max="1000" value="500">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">0₽</span>
                            <span class="text-muted">100 000₽</span>
                        </div>
                    </div>

                    <!-- <div class="filter-group">
                        <h6 class="mb-3">Цвета</h6>
                        <div class="d-flex gap-2">
                            <div class="color-option selected" style="background: #000000;"></div>
                            <div class="color-option" style="background: #dc2626;"></div>
                            <div class="color-option" style="background: #2563eb;"></div>
                            <div class="color-option" style="background: #16a34a;"></div>
                        </div>
                    </div> -->

                    <div class="filter-group">
                        <h6 class="mb-3">Рейтинг</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="rating" id="rating4">
                            <label class="form-check-label" for="rating4">
                                <i class="bi bi-star-fill text-warning"></i> 4 и выше
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="rating" id="rating3">
                            <label class="form-check-label" for="rating3">
                                <i class="bi bi-star-fill text-warning"></i> 3 и выше
                            </label>
                        </div>
                    </div>

                    <button class="btn btn-outline-primary w-100">Применить фильтры</button>
                </div>
            </div>

            <!-- Product Grid -->
            <div class="col-lg-9">
                <div class="row g-4">
                    <!-- Product Card 1 -->
                    <div class="col-md-4">
                        <div class="product-card shadow-sm">
                            <div class="position-relative">
                                <img src="../template/assets/500x500.png" class="product-image w-100" alt="Product">
                                <div class="position-absolute top-0 start-0"
                                    style="padding-left: 6px; padding-top: 2px;">
                                    <span class="badge text-bg-danger discount-badge">СКИДКА 50%</span>
                                    <span class="badge text-bg-success discount-badge">ХИТ</span>
                                </div>
                                <!-- <button class="wishlist-btn">
                                    <i class="bi bi-heart"></i>
                                </button> -->
                            </div>
                            <div class="p-3">
                                <span class="category-badge mb-2 d-inline-block">Electronics</span>
                                <h6 class="mb-1">Wireless Headphones</h6>
                                <div class="rating-stars mb-2">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-half"></i>
                                    <span class="text-muted ms-2">(4.5)</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="price">129.99₽ <a style="color: gray;">шт.</a></span>
                                    <button class="btn cart-btn">
                                        <i class="bi bi-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Card 2 -->
                    <div class="col-md-4">
                        <div class="product-card shadow-sm">
                            <div class="position-relative">
                                <img src="../template/assets/500x500.png" class="product-image w-100" alt="Product">
                                <div class="position-absolute top-0 start-0"
                                    style="padding-left: 6px; padding-top: 2px;">
                                    <span class="badge text-bg-danger discount-badge">СКИДКА 50%</span>
                                    <span class="badge text-bg-success discount-badge">ХИТ</span>
                                </div>
                                <!-- <button class="wishlist-btn">
                                    <i class="bi bi-heart"></i>
                                </button> -->
                            </div>
                            <div class="p-3">
                                <span class="category-badge mb-2 d-inline-block">Electronics</span>
                                <h6 class="mb-1">Smart Watch Pro</h6>
                                <div class="rating-stars mb-2">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star"></i>
                                    <span class="text-muted ms-2">(4.0)</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="price">299.99₽ <a style="color: gray;">шт.</a></span>
                                    <button class="btn cart-btn">
                                        <i class="bi bi-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Card 3 -->
                    <div class="col-md-4">
                        <div class="product-card shadow-sm">
                            <div class="position-relative">
                                <img src="../template/assets/500x500.png" class="product-image w-100" alt="Product">
                                <div class="position-absolute top-0 start-0"
                                    style="padding-left: 6px; padding-top: 2px;">
                                    <span class="badge text-bg-danger discount-badge">СКИДКА 50%</span>
                                    <span class="badge text-bg-success discount-badge">ХИТ</span>
                                </div>
                                <!-- <button class="wishlist-btn">
                                    <i class="bi bi-heart"></i>
                                </button> -->
                            </div>
                            <div class="p-3">
                                <span class="category-badge mb-2 d-inline-block">Accessories</span>
                                <h6 class="mb-1">Leather Wallet</h6>
                                <div class="rating-stars mb-2">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <span class="text-muted ms-2">(5.0)</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="price">59.99₽ <a style="color: gray;">шт.</a></span>
                                    <button class="btn cart-btn">
                                        <i class="bi bi-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- More product cards can be added here -->

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