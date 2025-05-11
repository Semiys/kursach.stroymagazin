document.addEventListener('DOMContentLoaded', function () {
    // Определение базовых путей к обработчикам
    const ROOT_PATH = '/main/';

    function updateCartControls(productId, newQuantity, stockQuantity) {
        const controlsContainer = document.querySelector(`.cart-controls[data-product-id="${productId}"]`);
        if (!controlsContainer) return;

        // Update data-stock attribute if stockQuantity is provided
        if (typeof stockQuantity !== 'undefined') {
            controlsContainer.dataset.stock = stockQuantity;
        } else {
            // If not provided, try to read from existing attribute
            stockQuantity = parseInt(controlsContainer.dataset.stock);
            if (isNaN(stockQuantity)) {
                stockQuantity = 0; 
                console.warn(`Stock quantity for product ${productId} is unknown, defaulting to 0.`);
            }
        }

        // Определяем полный путь к обработчику
        let actionUrlBase = ROOT_PATH;
        // Используем текущий путь для определения страницы
        const currentPath = window.location.pathname;
        if (currentPath.includes('catalogue.php')) {
            actionUrlBase += 'catalogue.php';
        } else if (currentPath.includes('product.php')) {
            actionUrlBase += 'product.php';
        } else {
            console.warn('Cart controls update: Unknown page, trying to determine from URL params');
            const urlParams = new URLSearchParams(window.location.search);
            const pageProductId = urlParams.get('id');
            if (pageProductId && pageProductId == productId) {
                actionUrlBase += 'product.php';
            } else {
                actionUrlBase += 'catalogue.php'; // По умолчанию каталог
            }
        }

        // If stock is 0 or less, show "Out of stock" and disable controls
        if (stockQuantity <= 0 && newQuantity <= 0) { // Ensure newQuantity is also 0 if stock is 0
            const isProductPage = window.location.pathname.includes('product.php');
            const btnDisabledClass = isProductPage ? 'btn-secondary btn-lg disabled' : 'btn-secondary w-100 disabled';
            controlsContainer.innerHTML = `
                <button class="btn ${btnDisabledClass}" disabled>
                    Нет в наличии
                </button>
            `;
            // No need to attach listeners if it's just a disabled button
            return;
        }

        if (newQuantity > 0) {
            const currentInput = controlsContainer.querySelector('.product-quantity-input');
            if (currentInput) { // Если контрол уже есть, просто обновляем значение
                currentInput.value = newQuantity;
                currentInput.setAttribute('max', stockQuantity); // Update max stock
                // Disable/enable plus button based on stock
                const plusButton = controlsContainer.querySelector('a[data-action="add_to_cart"]');
                if (plusButton) {
                    if (newQuantity >= stockQuantity) {
                        plusButton.classList.add('disabled');
                    } else {
                        plusButton.classList.remove('disabled');
                    }
                }
            } else { // Если была кнопка "В корзину", заменяем ее на контрол
                const isProductPage = window.location.pathname.includes('product.php');
                const btnSizeClass = isProductPage ? 'btn-lg' : 'btn-sm';
                const inputSizeClass = isProductPage ? 'form-control-lg' : 'form-control-sm';
                // Determine if plus button should be disabled initially
                const plusDisabledClass = newQuantity >= stockQuantity ? 'disabled' : '';
                const controlHtml = `
                    ${isProductPage ? '<span class="me-3">Количество:</span>' : ''}
                    <div class="input-group quantity-control-group ajax-quantity-control" style="max-width: ${isProductPage ? '180px' : '185px'}; ${!isProductPage ? 'margin-left: auto; margin-right: auto;' : ''}">
                        <a href="#" class="btn btn-outline-secondary ${btnSizeClass} cart-action-btn" data-action="decrease_quantity">-</a>
                        <input type="number" class="form-control ${inputSizeClass} text-center product-quantity-input" value="${newQuantity}" min="1" max="${stockQuantity}" data-action="update_quantity">
                        <a href="#" class="btn btn-outline-secondary ${btnSizeClass} cart-action-btn ${plusDisabledClass}" data-action="add_to_cart">+</a>
                    </div>
                `;
                if (isProductPage) {
                     // На странице товара, d-flex align-items-center был внешним для input-group
                    controlsContainer.innerHTML = `<div class="d-flex align-items-center">${controlHtml}</div>`;
                } else {
                    controlsContainer.innerHTML = controlHtml;
                }
            }
        } else { // newQuantity = 0, значит, показываем кнопку "В корзину"
            const isProductPage = window.location.pathname.includes('product.php');
            const btnClass = isProductPage ? 'btn-primary btn-lg' : 'btn-primary w-100';
            const iconHtml = isProductPage ? '<i class="bi bi-cart-plus"></i> ' : '';
            controlsContainer.innerHTML = `
                <a href="#" class="btn ${btnClass} cart-action-btn" data-action="add_to_cart" title="Добавить в корзину">
                    ${iconHtml}В корзину
                </a>
            `;
        }
        // Переназначаем обработчики событий для новых элементов
        attachCartActionListeners(controlsContainer);
    }

    function updateCartBadge(totalQuantity) {
        const badge = document.querySelector('.cart-total-quantity-badge');
        if (badge) {
            if (totalQuantity > 0) {
                badge.textContent = totalQuantity;
                badge.classList.remove('d-none');
            } else {
                badge.textContent = '';
                badge.classList.add('d-none');
            }
        }
    }

    function handleCartAction(event) {
        event.preventDefault();
        const target = event.currentTarget;
        const controlsContainer = target.closest('.cart-controls');
        // Если кнопка удаления в корзине находится не внутри контейнера .cart-controls
        if (!controlsContainer && target.dataset.action === 'remove_from_cart') {
            // Получаем productId напрямую из атрибута кнопки
            const productId = target.dataset.productId;
            if (productId) {
                // Показываем глобальное уведомление об удалении
                showGlobalNotification('Удаление товара...', 'info');
                
                // Формируем URL для удаления товара
                let ajaxUrl = `${ROOT_PATH}cart.php?action=remove_from_cart&id_to_cart=${productId}&ajax=1`;
                
                // Отправляем запрос на удаление
                fetch(ajaxUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Ошибка сети: ' + response.status);
                        }
                        return response.text().then(text => {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                if (text.includes('<html') || text.includes('<body') || text.includes('<nav')) {
                                    throw new Error('Получен HTML вместо JSON. Проверьте, что header.php не включается для AJAX-запросов.');
                                } else {
                                    throw new Error('Некорректный формат ответа от сервера');
                                }
                            }
                        });
                    })
                    .then(data => {
                        if (data.success) {
                            // Обновляем индикатор корзины
                            updateCartBadge(data.total_cart_quantity);
                            showGlobalNotification('Товар удален из корзины', 'success');
                            // Перезагружаем страницу для обновления содержимого корзины
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            console.error('Remove item error:', data.message);
                            showGlobalNotification('Не удалось удалить товар: ' + data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        showGlobalNotification('Ошибка при удалении товара: ' + error.message, 'danger');
                    });
                return; // Выходим из функции, так как запрос уже отправлен
            }
        }
        
        if (!controlsContainer) return;

        const productId = controlsContainer.dataset.productId;
        const currentStock = parseInt(controlsContainer.dataset.stock); // Get current stock
        const action = target.dataset.action;
        let qty = null;
        const inputField = controlsContainer.querySelector('.product-quantity-input');

        // Client-side stock check for add_to_cart
        if (action === 'add_to_cart') {
            let currentQtyInCart = 0;
            if (inputField) { // If quantity input exists, get its value
                currentQtyInCart = parseInt(inputField.value) || 0;
            }
            if (currentQtyInCart + 1 > currentStock) {
                showProductNotification(productId, 'Доступно: ' + currentStock + ' шт. Больше нет на складе.', 'warning');
                if (target.innerHTML === '+') {
                    target.classList.add('disabled');
                }
                return; // Stop further action
            }
        }
        
        // Покажем сначала уведомление о том, что действие выполняется
        if (action === 'add_to_cart') {
            showProductNotification(productId, 'Добавление товара...', 'info', 1000);
        } else if (action === 'decrease_quantity') {
            showProductNotification(productId, 'Уменьшение количества...', 'info', 1000);
        } else if (action === 'update_quantity') {
            showProductNotification(productId, 'Обновление количества...', 'info', 1000);
        } else if (action === 'remove_from_cart') {
            showProductNotification(productId, 'Удаление товара...', 'info', 1000);
        }

        // Определяем полный путь к обработчику
        let actionUrlBase = ROOT_PATH;
        // Используем текущий путь для определения страницы
        const currentPath = window.location.pathname;
        if (currentPath.includes('catalogue.php')) {
            actionUrlBase += 'catalogue.php';
        } else if (currentPath.includes('product.php')) {
            actionUrlBase += 'product.php';
        } else {
            console.warn('Cart action: Unknown page, trying to determine from URL params');
            const urlParams = new URLSearchParams(window.location.search);
            const pageProductId = urlParams.get('id');
            if (pageProductId && pageProductId == productId) {
                actionUrlBase += 'product.php';
            } else {
                actionUrlBase += 'catalogue.php';
            }
        }
        
        // Формируем URL для AJAX запроса
        let ajaxUrl = `${actionUrlBase}?action=${action}&id_to_cart=${productId}&ajax=1`;

        if (action === 'update_quantity') {
            const inputField = controlsContainer.querySelector('.product-quantity-input');
            qty = parseInt(inputField.value);
            if (isNaN(qty) || qty < 0) {
                qty = 0; 
            }
            ajaxUrl += `&qty=${qty}`;
        }

        // Добавляем отладочную информацию в консоль
        console.log('Sending AJAX request to:', ajaxUrl);

        fetch(ajaxUrl)
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Ошибка сети: ' + response.status);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        if (text.includes('<html') || text.includes('<body') || text.includes('<nav')) {
                            console.error('Получен HTML вместо JSON:', text.substring(0, 100) + '...');
                            throw new Error('Получен HTML вместо JSON. Проверьте код серверной части.');
                        } else {
                            console.error('Некорректный JSON:', text);
                            throw new Error('Сервер вернул некорректный формат данных.');
                        }
                    }
                });
            })
            .then(data => {
                console.log('AJAX response data:', data);
                if (data.success) {
                    updateCartControls(data.product_id, data.new_quantity, data.stock_quantity);
                    updateCartBadge(data.total_cart_quantity);
                    
                    // Показываем уведомление об успешном действии
                    if (action === 'add_to_cart') {
                        showProductNotification(productId, 'Товар добавлен в корзину!', 'success');
                    } else if (action === 'update_quantity') {
                        showProductNotification(productId, 'Количество обновлено', 'success');
                    } else if (action === 'remove_from_cart') {
                        // Если мы на странице корзины и удаляем товар, перезагружаем страницу
                        if (window.location.pathname.includes('cart.php')) {
                            showProductNotification(productId, 'Товар удален из корзины', 'success');
                            // Маленькая задержка, чтобы пользователь увидел сообщение
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            showProductNotification(productId, 'Товар удален из корзины', 'success');
                        }
                    }
                } else {
                    console.error('Cart action error:', data.message);
                    showProductNotification(productId, data.message, 'danger');

                    if (typeof data.stock_quantity !== 'undefined') {
                        updateCartControls(productId, data.current_quantity_in_cart || 0, data.stock_quantity);
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                // Более конкретное сообщение в случае отсутствия подключения
                if (error.message.includes('Failed to fetch') || error.message.includes('Network error')) {
                    showProductNotification(productId, 'Ошибка соединения с сервером. Проверьте подключение к интернету.', 'danger');
                } else {
                    // Более подробное сообщение об ошибке для отладки
                    showProductNotification(productId, 'Ошибка: ' + error.message, 'danger');
                }
            });
    }

    function attachCartActionListeners(container) {
        const actionButtons = container.querySelectorAll('.cart-action-btn');
        actionButtons.forEach(button => {
            // Удаляем старые обработчики, чтобы избежать дублирования, если функция вызывается повторно
            button.removeEventListener('click', handleCartAction); 
            button.addEventListener('click', handleCartAction);
        });

        const quantityInputs = container.querySelectorAll('.product-quantity-input');
        quantityInputs.forEach(input => {
            input.removeEventListener('change', handleCartAction); // Используем change, data-action уже есть 'update_quantity'
            input.addEventListener('change', handleCartAction); // data-action на инпуте должен быть 'update_quantity'
            // Add input event listener for immediate validation against max attribute
            input.removeEventListener('input', handleQuantityInputChange);
            input.addEventListener('input', handleQuantityInputChange);
        });
    }

    // Handler for direct input changes in quantity field
    function handleQuantityInputChange(event) {
        const inputField = event.target;
        const controlsContainer = inputField.closest('.cart-controls');
        if (!controlsContainer) return;
        
        const stock = parseInt(controlsContainer.dataset.stock);
        let currentValue = parseInt(inputField.value);

        if (isNaN(currentValue) || currentValue < 0) {
            inputField.value = 0; // Or 1 if you don't allow 0 directly via input before 'change' event
            currentValue = 0;
        }

        if (!isNaN(stock) && currentValue > stock) {
            // alert(`Максимально доступное количество: ${stock} шт.`);
            showProductNotification(controlsContainer.dataset.productId, `Макс. доступно: ${stock} шт.`, 'warning');
            inputField.value = stock;
        }
        
        // Manage plus button state based on input relative to stock
        const plusButton = controlsContainer.querySelector('a[data-action="add_to_cart"]');
        if (plusButton) {
            if (currentValue >= stock) {
                plusButton.classList.add('disabled');
            } else {
                plusButton.classList.remove('disabled');
            }
        }
    }

    // Первоначальное назначение обработчиков для всех .cart-controls на странице
    const allControls = document.querySelectorAll('.cart-controls');
    allControls.forEach(controlsDiv => {
        attachCartActionListeners(controlsDiv);
    });

    // Добавляем обработчик для кнопки "Очистить корзину"
    const clearCartBtn = document.getElementById('clear-cart-btn');
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Заменяем confirm на вывод уведомления перед отправкой запроса
            showGlobalNotification('Очистка корзины...', 'info');
            
            // Формируем URL для AJAX запроса очистки корзины
            let ajaxUrl = `${ROOT_PATH}cart.php?action=clear_cart&ajax=1`;
            
            fetch(ajaxUrl)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error('Ошибка сети: ' + response.status);
                    }
                    
                    // Попробуем прочитать содержимое ответа как текст
                    return response.text().then(text => {
                        try {
                            // Попытка распарсить JSON
                            return JSON.parse(text);
                        } catch (e) {
                            // Если не удалось распарсить как JSON, проверим наличие HTML
                            if (text.includes('<html') || text.includes('<body') || text.includes('<nav')) {
                                console.error('Получен HTML вместо JSON:', text.substring(0, 100) + '...');
                                throw new Error('Получен HTML вместо JSON. Проверьте, что header.php не включается для AJAX-запросов.');
                            } else {
                                console.error('Некорректный JSON:', text);
                                throw new Error('Некорректный формат ответа от сервера');
                            }
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        // Обновляем индикатор корзины
                        updateCartBadge(0);
                        showGlobalNotification('Корзина успешно очищена', 'success');
                        // Перезагружаем страницу для обновления содержимого корзины
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        console.error('Clear cart error:', data.message);
                        showGlobalNotification('Не удалось очистить корзину: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    if (error.message.includes('Failed to fetch') || error.message.includes('Network error')) {
                        showGlobalNotification('Ошибка соединения с сервером. Проверьте подключение к интернету.', 'danger');
                    } else {
                        showGlobalNotification('Ошибка при очистке корзины: ' + error.message, 'danger');
                    }
                });
        });
    }

    // Функция для отображения временных уведомлений под элементом управления товаром
    function showProductNotification(productId, message, type = 'danger', duration = 3500) {
        const controlsContainer = document.querySelector(`.cart-controls[data-product-id="${productId}"]`);
        if (!controlsContainer) {
            // Если контролы не найдены, показываем глобальное уведомление
            showGlobalNotification(message, type);
            return;
        }

        // Удаляем предыдущее сообщение для этого товара, если оно есть
        const existingMessage = controlsContainer.nextElementSibling;
        if (existingMessage && existingMessage.classList.contains('cart-product-notification')) {
            existingMessage.remove();
        }

        const messageDiv = document.createElement('div');
        messageDiv.className = `cart-product-notification alert alert-${type} mt-1 mb-0 p-2 small`;
        messageDiv.textContent = message;
        messageDiv.style.fontSize = '0.85em'; // Чуть меньше стандартного small
        
        // Вставляем сообщение после блока .cart-controls
        controlsContainer.parentNode.insertBefore(messageDiv, controlsContainer.nextSibling);

        setTimeout(() => {
            messageDiv.remove();
        }, duration);
    }

    // Функция для отображения глобальных уведомлений
    function showGlobalNotification(message, type = 'info', duration = 3500) {
        // Проверяем, есть ли уже контейнер для глобальных уведомлений
        let notifContainer = document.getElementById('global-notifications');
        if (!notifContainer) {
            notifContainer = document.createElement('div');
            notifContainer.id = 'global-notifications';
            notifContainer.className = 'position-fixed top-0 end-0 p-3';
            notifContainer.style.zIndex = '1050';
            document.body.appendChild(notifContainer);
        }

        const notification = document.createElement('div');
        notification.className = `toast alert alert-${type} show`;
        notification.role = 'alert';
        notification.ariaLive = 'assertive';
        notification.ariaAtomic = 'true';
        
        notification.innerHTML = `
            <div class="toast-header">
                <strong class="me-auto">Уведомление</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;

        notifContainer.appendChild(notification);

        // Устанавливаем обработчик закрытия
        const closeBtn = notification.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                notification.remove();
            });
        }

        // Автоматически удаляем уведомление через указанное время
        setTimeout(() => {
            notification.remove();
        }, duration);
    }

}); 