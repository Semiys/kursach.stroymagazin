<?php
session_start(); // Начинаем или возобновляем сессию

// Подключаем файл конфигурации для доступа к $pdo
require_once __DIR__ . '/../config.php'; 

// Проверяем, залогинен ли пользователь и является ли он модератором
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'moder') {
    header("Location: /index.php"); // Перенаправляем на главную сайта
    exit;
}

// Если проверки пройдены, пользователь является модератором
// Подключаем заголовок
include_once 'header.php';

// Получаем товары из базы данных
$products = [];
$error_message = '';

try {
    // TODO: Добавить пагинацию позже
    $stmt = $pdo->query("SELECT id, title, category, price, stock_quantity, discount FROM goods ORDER BY id DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Ошибка при загрузке товаров: " . $e->getMessage();
    // Можно залогировать ошибку, если у вас есть система логирования
    // error_log($error_message);
}

?>

<div class="container pt-0 mt-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Управление товарами</h4>
        <a href="edit_product.php" class="btn btn-primary-orange">Добавить новый товар</a> <!-- edit_product.php будет и для создания, и для редактирования -->
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($products) && empty($error_message)): ?>
        <div class="alert alert-info" role="alert">
            Товары пока не добавлены.
        </div>
    <?php elseif (!empty($products)): ?>
        <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;"> 
            <table class="table table-striped table-bordered">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Категория</th>
                        <th>Цена</th>
                        <th>На складе</th>
                        <th>Скидка (%)</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['id']); ?></td>
                            <td><?php echo htmlspecialchars($product['title']); ?></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td><?php echo htmlspecialchars(number_format($product['price'], 2, '.', ' ')); ?>₽</td>
                            <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                            <td><?php echo htmlspecialchars($product['discount'] ?? 0); ?>%</td>
                            <td>
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">Редактировать</a>
                                <!-- TODO: Добавить кнопку Скрыть/Показать/Удалить с подтверждением -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<?php
// Подключаем подвал
include_once 'footer.php';
?> 