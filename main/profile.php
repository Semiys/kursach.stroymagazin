<?php
session_start(); // Запускаем сессию
require_once '../config.php'; // Подключаем конфигурацию БД

// Проверяем, залогинен ли пользователь
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Если нет, перенаправляем на страницу входа
    exit();
}

$user_id = $_SESSION['user_id'];
$user_data = null;
$error_message = '';

try {
    $stmt = $pdo->prepare("SELECT name, email, role, created_at FROM users WHERE id = ?"); // Предполагаем, что дата регистрации в created_at
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        // Это странная ситуация: user_id есть в сессии, но пользователя нет в БД
        // Уничтожаем сессию и перенаправляем на вход
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    $error_message = "Ошибка получения данных пользователя: " . $e->getMessage(); // Для отладки
    // В реальном приложении здесь может быть более общее сообщение или логирование
}

// Получаем историю заказов пользователя
$orders = [];
try {
    $stmt = $pdo->prepare("SELECT id, total_amount, status, created_at FROM orders WHERE user_id = ? AND is_hidden = 0 ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка при получении истории заказов: " . $e->getMessage());
}

// Обработка повторного заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repeat_order'])) {
    $order_id = (int)$_POST['repeat_order'];
    
    try {
        // Проверяем существование заказа и принадлежность текущему пользователю
        $check_stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ?");
        $check_stmt->execute([$order_id, $user_id]);
        
        if ($check_stmt->rowCount() > 0) {
            // Получаем товары из заказа
            $items_stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $items_stmt->execute([$order_id]);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Инициализируем корзину, если она ещё не создана
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            // Добавляем товары в корзину
            foreach ($items as $item) {
                // Проверяем, есть ли товар в наличии
                $product_stmt = $pdo->prepare("SELECT stock_quantity FROM goods WHERE id = ?");
                $product_stmt->execute([$item['product_id']]);
                $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product && $product['stock_quantity'] > 0) {
                    // Если товар уже есть в корзине, увеличиваем количество
                    if (isset($_SESSION['cart'][$item['product_id']])) {
                        $_SESSION['cart'][$item['product_id']] += $item['quantity'];
                    } else {
                        $_SESSION['cart'][$item['product_id']] = $item['quantity'];
                    }
                    
                    // Проверяем, не превышает ли новое количество доступное на складе
                    if ($_SESSION['cart'][$item['product_id']] > $product['stock_quantity']) {
                        $_SESSION['cart'][$item['product_id']] = $product['stock_quantity'];
                    }
                }
            }
            
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Товары из заказа добавлены в корзину!'];
            header('Location: cart.php');
            exit();
        } else {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Заказ не найден или не принадлежит вам.'];
        }
    } catch (PDOException $e) {
        error_log("Ошибка при повторении заказа: " . $e->getMessage());
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Произошла ошибка при обработке заказа.'];
    }
    
    // Перенаправляем обратно на страницу профиля, если что-то пошло не так
    header('Location: profile.php');
    exit();
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Мой профиль</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="stylesheet" href="../template/css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
    <?php include_once "../template/header.php" ?>

    <main class="container mt-4">

<div class="container mt-4">
    <h2 class="text-center">Мой профиль</h2>
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash_message']['type']; ?> alert-dismissible fade show">
            <?php echo $_SESSION['flash_message']['text']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php if ($user_data): ?>
    <div class="card">
        <div class="card_profile mb-4">
            <div class="card-body">
                <h5 class="card-title">Информация о пользователе</h5>
                <?php if (!empty($user_data['name'])): // Добавим вывод имени, если оно есть ?>
                <p><strong>Имя:</strong> <?php echo htmlspecialchars($user_data['name']); ?></p>
                <?php endif; ?>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></p>
                <p><strong>Роль:</strong> <?php echo htmlspecialchars($user_data['role']); ?></p>
                <p><strong>Дата регистрации:</strong> <?php echo htmlspecialchars(date("d.m.Y H:i", strtotime($user_data['created_at']))); // Форматируем дату ?></p>
                <a href="/main/logout.php" class="btn btn-danger mt-3">Выйти из аккаунта</a>
            </div>
        </div>
    </div>
    <?php else: ?>
        <?php if (empty($error_message)): // Если $user_data пуст, но нет ошибки $pdo, значит что-то пошло не так при выходе выше ?>
            <div class="alert alert-warning">Не удалось загрузить данные профиля.</div>
        <?php endif; ?>
    <?php endif; ?>

    <h3 class="mb-3">История заказов</h3>
            <table class="table table-bordered text-center">
            <thead class="table-dark">
                <tr>
                    <th>ID заказа</th>
                                        <th>Сумма</th>
                    <th>Статус</th>
                    <th>Дата</th>
                    <th>Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="5" class="text-center">У вас еще нет заказов</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo number_format($order['total_amount'], 2, '.', ' '); ?> ₽</td>
                        <td><?php echo htmlspecialchars($order['status']); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></td>
                        <td>
                            <div class="d-grid gap-2">
                                <button class="btn btn-info btn-sm view-order-btn" data-bs-toggle="modal" data-bs-target="#orderDetailsModal" data-order-id="<?php echo $order['id']; ?>">
                                    Посмотреть заказ
                                </button>
                                                                    <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="repeat_order" value="<?php echo $order['id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm w-100">Повторить заказ</button>
                                    </form>
                                                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                            </tbody>
        </table>
    </div>

<!-- Модальное окно для состава заказа -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" style="display: none;" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="orderDetailsModalLabel">Состав заказа</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="orderDetailsContent"> 
            <p class="text-center">Загрузка информации о заказе...</p>
            </div>
      </div>
    </div>
  </div>
</div>

<!-- Модальное окно для отправки письма -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="emailModalLabel">Написать письмо клиенту</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="emailForm">
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="email_order_id">
                    <input type="hidden" name="email" id="email_address">
                    
                    <div class="mb-3">
                        <label for="email_subject" class="form-label">Тема письма</label>
                        <input type="text" class="form-control" id="email_subject" name="subject" required="">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email_message" class="form-label">Текст письма</label>
                        <textarea class="form-control" id="email_message" name="message" rows="5" required=""></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Отправить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Добавляем обработчик для форм изменения статуса
document.querySelectorAll('.status-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const orderId = this.dataset.orderId;
        const statusSelect = this.querySelector('.status-select');
        const newStatus = statusSelect.value;
        
        try {
            const response = await fetch('update_order_status.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Обновляем выбранное значение в select
                statusSelect.value = newStatus;
                
                // Показываем уведомление об успехе
                const notification = document.createElement('div');
                notification.className = 'alert alert-success position-fixed top-0 end-0 m-3';
                notification.style.zIndex = '9999';
                notification.textContent = 'Статус заказа успешно обновлен';
                document.body.appendChild(notification);
                
                // Удаляем уведомление через 3 секунды
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            } else {
                throw new Error(result.message || 'Произошла ошибка');
            }
        } catch (error) {
            console.error('Ошибка:', error);
            alert('Произошла ошибка при обновлении статуса');
        }
    });
});

document.querySelectorAll('.view-order-btn').forEach(button => {
  button.addEventListener('click', function () {
    const orderId = this.getAttribute('data-order-id');
    const orderDetailsContent = document.getElementById('orderDetailsContent');
    
    // Показываем загрузку
    orderDetailsContent.innerHTML = '<p class="text-center"><i class="bi bi-hourglass-split me-2"></i>Загрузка информации о заказе...</p>';
    
    if (orderId) {
        fetch('get_order_details.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ order_id: orderId })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Ошибка сети: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
          console.log('Полученные данные:', data);
          
          if (data.success) {
            let itemsHtml = '';
            
            if (data.items && data.items.length > 0) {
            data.items.forEach(item => {
                const price = parseFloat(item.price) || 0;
                const quantity = parseInt(item.quantity) || 0;
                const discountPercentage = parseInt(item.discount_percentage) || 0;
                
                const totalPrice = price * quantity;
                const discountedPrice = price * (1 - discountPercentage/100) * quantity;
                
              itemsHtml += `
                <tr>
                    <td>${item.title || 'Товар не найден'}</td>
                    <td>${quantity} шт.</td>
                    <td>${price.toFixed(2)} ₽</td>
                    <td>${totalPrice.toFixed(2)} ₽</td>
                    <td>${discountedPrice.toFixed(2)} ₽</td>
                </tr>`;
            });
            } else {
              itemsHtml = '<tr><td colspan="5" class="text-center">Товары не найдены</td></tr>';
            }

            const totalAmount = parseFloat(data.total_amount) || 0;
            const discountAmount = parseFloat(data.discount_amount) || 0;
            const finalAmount = totalAmount - discountAmount;

            orderDetailsContent.innerHTML = ` 
              <p><strong>Номер заказа:</strong> #${data.order_id}</p>
              <p><strong>Дата заказа:</strong> ${data.created_at}</p>
              <p><strong>Статус заказа:</strong> ${data.status}</p>
              <p><strong>Адрес доставки:</strong> ${data.shipping_address || 'Не указан'}</p>
              <p><strong>Способ оплаты:</strong> ${data.payment_method || 'Не указан'}</p>
              <h4>Товары:</h4>
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Товар</th>
                    <th>Количество</th>
                    <th>Цена за шт.</th>
                    <th>Сумма</th>
                    <th>Сумма со скидкой</th>
                  </tr>
                </thead>
                <tbody>
                  ${itemsHtml}
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="3" class="text-end"><strong>Итого:</strong></td>
                    <td><strong>${totalAmount.toFixed(2)} ₽</strong></td>
                    <td><strong>${finalAmount.toFixed(2)} ₽</strong></td>
                  </tr>
                  ${discountAmount > 0 ? `
                  <tr>
                    <td colspan="5">
                      <strong>Применена скидка:</strong> ${discountAmount.toFixed(2)} ₽
                    </td>
                  </tr>
                  ` : ''}
                  ${data.promo_code ? `
                  <tr>
                    <td colspan="5">
                      <strong>Использован промокод:</strong> ${data.promo_code}
                    </td>
                  </tr>
                  ` : ''}
                </tfoot>
              </table>
              <div class="mt-3 d-flex justify-content-end">
                <button class="btn btn-sm btn-outline-primary send-order-email" data-order-id="${data.order_id}">
                  <i class="bi bi-envelope me-1"></i> Отправить на email
                </button>
              </div>
            `;
          } else {
            orderDetailsContent.innerHTML = `
              <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                ${data.message || 'Ошибка при загрузке данных заказа'}
              </div>`;
          }
        })
        .catch(error => {
          console.error('Ошибка:', error);
          orderDetailsContent.innerHTML = `
            <div class="alert alert-danger">
              <i class="bi bi-exclamation-triangle me-2"></i>
              Ошибка при загрузке данных заказа: ${error.message}
            </div>`;
        });
    }
  });
});

// Обработчик для кнопки отправки письма
document.querySelectorAll('.send-email-btn').forEach(button => {
    button.addEventListener('click', function() {
        const orderId = this.dataset.orderId;
        const email = this.dataset.email;
        
        document.getElementById('email_order_id').value = orderId;
        document.getElementById('email_address').value = email;
    });
});

// Обработчик отправки формы письма
document.getElementById('emailForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('send_order_email.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Закрываем модальное окно
            bootstrap.Modal.getInstance(document.getElementById('emailModal')).hide();
            
            // Очищаем форму
            this.reset();
            
            // Показываем уведомление об успехе
            const notification = document.createElement('div');
            notification.className = 'alert alert-success position-fixed top-0 end-0 m-3';
            notification.style.zIndex = '9999';
            notification.textContent = 'Письмо успешно отправлено';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        } else {
            throw new Error(result.message || 'Произошла ошибка');
        }
    } catch (error) {
        console.error('Ошибка:', error);
        alert('Произошла ошибка при отправке письма: ' + error.message);
    }
});

// Обработчик для кнопки отправки заказа на email
document.addEventListener('click', async function(e) {
    if (e.target.classList.contains('send-order-email') || e.target.closest('.send-order-email')) {
        const button = e.target.classList.contains('send-order-email') ? e.target : e.target.closest('.send-order-email');
        const orderId = button.getAttribute('data-order-id');
        
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Отправка...';
        
        try {
            const formData = new FormData();
            formData.append('order_id', orderId);
            
            const response = await fetch('send_order_email.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Показываем уведомление об успехе
                const notification = document.createElement('div');
                notification.className = 'alert alert-success position-fixed top-0 end-0 m-3';
                notification.style.zIndex = '9999';
                notification.textContent = result.message || 'Информация о заказе отправлена на ваш email';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
                
                // Восстанавливаем кнопку
                button.disabled = false;
                button.innerHTML = '<i class="bi bi-envelope me-1"></i> Отправить на email';
            } else {
                throw new Error(result.message || 'Произошла ошибка');
            }
        } catch (error) {
            console.error('Ошибка:', error);
            
            // Показываем уведомление об ошибке
            const notification = document.createElement('div');
            notification.className = 'alert alert-danger position-fixed top-0 end-0 m-3';
            notification.style.zIndex = '9999';
            notification.textContent = 'Ошибка: ' + error.message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
            
            // Восстанавливаем кнопку
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-envelope me-1"></i> Отправить на email';
        }
    }
});
</script></main>

    <?php include_once "../template/footer.php" ?>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.min.js"
        integrity="sha384-VQqxDN0EQCkWoxt/0vsQvZswzTHUVOImccYmSyhJTp7kGtPed0Qcx8rK9h9YEgx+"
        crossorigin="anonymous"></script>
</body>

</html>