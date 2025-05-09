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
                                    <tr>
                        <td>#80</td>
                                                <td>1 520.00 ₽</td>
                        <td>
                                                            В обработке                                                    </td>
                        <td>03.05.2025 11:29</td>
                        <td>
                            <div class="d-grid gap-2">
                                <button class="btn btn-info btn-sm view-order-btn" data-bs-toggle="modal" data-bs-target="#orderDetailsModal" data-order-id="80">
                                    Посмотреть заказ
                                </button>

                                
                                                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="repeat_order" value="80">
                                        <button type="submit" class="btn btn-success btn-sm w-100">Повторить заказ</button>
                                    </form>
                                                            </div>
                        </td>
                    </tr>
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
              <p><strong>Имя клиента:</strong> Артур Вадимович Мишин</p>
              <p><strong>Телефон:</strong> 89626345055</p>
              <p><strong>Адрес:</strong> Камышинская 15</p>
              <p><strong>Примечание:</strong> 123</p>
              <p><strong>Способ оплаты:</strong> Оплата картой</p>
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
                  
                <tr>
                  <td>Игрушка "Развивашка"</td>
                  <td>1 шт.</td>
                  <td>1 520.00 ₽</td>
                  <td>1 520.00 ₽</td>
                  <td>1 520.00 ₽</td>
                </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="3" class="text-end"><strong>Итого:</strong></td>
                    <td><strong>1 520.00 ₽</strong></td>
                    <td><strong>1 520.00 ₽</strong></td>
                  </tr>
                  
                </tfoot>
              </table>
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
    if (orderId) {
        fetch('/public/get_order_details.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ order_id: orderId })
        })
        .then(response => response.json())
        .then(data => {
          const orderDetailsContent = document.getElementById('orderDetailsContent');
          if (data.success) {
            let itemsHtml = '';
            data.items.forEach(item => {
              itemsHtml += `
                <tr>
                  <td>${item.name}</td>
                  <td>${item.quantity} шт.</td>
                  <td>${item.price} ₽</td>
                  <td>${item.sum} ₽</td>
                  <td>${item.sum_with_discount} ₽</td>
                </tr>`;
            });

            orderDetailsContent.innerHTML = ` 
              <p><strong>Имя клиента:</strong> ${data.client.full_name}</p>
              <p><strong>Телефон:</strong> ${data.client.phone}</p>
              <p><strong>Адрес:</strong> ${data.client.address}</p>
              <p><strong>Примечание:</strong> ${data.client.note}</p>
              <p><strong>Способ оплаты:</strong> ${data.client.payment_method}</p>
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
                    <td><strong>${data.total_price_without_discount} ₽</strong></td>
                    <td><strong>${data.total_price} ₽</strong></td>
                  </tr>
                  ${data.discount_percentage > 0 ? `
                  <tr>
                    <td colspan="5">
                      <strong>Применена скидка:</strong> ${data.discount_percentage}%<br>
                      <strong>Ваша выгода:</strong> ${data.discount_amount} ₽
                    </td>
                  </tr>
                  ` : ''}
                </tfoot>
              </table>
            `;
          } else {
            orderDetailsContent.innerHTML = `<p>${data.message}</p>`;
          }
        })
        .catch(error => {
          console.error('Ошибка:', error);
          const orderDetailsContent = document.getElementById('orderDetailsContent');
          orderDetailsContent.innerHTML = '<p>Ошибка при загрузке данных заказа.</p>';
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