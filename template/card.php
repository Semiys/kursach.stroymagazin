<?php

function get_card($product)
{
    $productTitle = htmlspecialchars($product['title']);
    $productPrice = number_format($product['price'], 0, ',', ' ');
    $productLink = "/main/item.php?id=" . $product['id'];
    $productId = htmlspecialchars($product['id']);
    $productCategory = htmlspecialchars($product['category']);
    
    // Путь к изображению товара
    $defaultImagePath = '/template/assets/500x500.png';
    $imagePath = $defaultImagePath;
    if (!empty($product['img'])) {
        $potentialImagePath = '/template/assets/' . basename(htmlspecialchars($product['img']));
        $absolutePotentialImagePath = $_SERVER['DOCUMENT_ROOT'] . $potentialImagePath;
        if (file_exists($absolutePotentialImagePath)) {
            $imagePath = $potentialImagePath;
        }
    }
    
    // Имеется ли товар в наличии
    $inStock = $product['stock_quantity'] > 0;
    
    // Расчет цены со скидкой
    $hasDiscount = isset($product['discount']) && $product['discount'] > 0;
    $discountedPrice = $hasDiscount 
                        ? number_format($product['price'] * (1 - $product['discount'] / 100), 0, ',', ' ') 
                        : $productPrice;
    
    $card = '
    <div class="card mb-4">
        <div class="position-relative">
            <a href="' . $productLink . '">
                <img src="' . $imagePath . '" class="card-img-top" alt="' . $productTitle . '">
            </a>
            ' . ($hasDiscount ? '<div class="badge-discount position-absolute top-0 end-0 m-2 py-2 px-3 rounded-pill" style="background-color: #ff7a00; color: white; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">-' . $product['discount'] . '%</div>' : '') . '
        </div>
        <div class="card-body d-flex flex-column">
            <h5 class="card-title product-title">' . $productTitle . '</h5>
            <p class="card-text text-muted small mb-3">' . $productCategory . '</p>
            <div class="d-flex justify-content-between align-items-center mt-auto">
                <div>
                    ' . ($hasDiscount ? '<span class="product-discount-price">' . $discountedPrice . ' ₽</span> <span class="product-original-price small">' . $productPrice . ' ₽</span>' : '<span class="product-price">' . $productPrice . ' ₽</span>') . '
                </div>
                <div>
                    ' . ($inStock ? '<button class="btn add-to-cart" data-product-id="' . $productId . '" style="background-color: #ff7a00; border-color: #ff7a00; color: white;">
                        <i class="bi bi-cart-plus"></i>
                    </button>' : '<button class="btn btn-secondary btn-sm" disabled>
                        <i class="bi bi-x-circle"></i> Нет в наличии
                    </button>') . '
                </div>
            </div>
        </div>
    </div>';

    return $card;
}

?> 