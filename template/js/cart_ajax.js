// Выносим функцию updateCartBadge за пределы DOMContentLoaded, 
// чтобы она была доступна глобально
window.updateCartBadge = function(totalQuantity) {
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
};

// Выносим функцию showGlobalNotification за пределы DOMContentLoaded,
// чтобы она была доступна глобально для main.php и других скриптов
window.showGlobalNotification = function(message, type = 'info', duration = 3500) {
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
};

// Глобальная переменная, чтобы знать, что этот скрипт был загружен
window.cartAjaxScriptLoaded = true;

// Флаг, предотвращающий повторную обработку событий
window.cartEventProcessing = false;

// Немедленно удаляем существующие обработчики с кнопок корзины
// Здесь мы не ждем DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', removeCartEventHandlers);
} else {
    removeCartEventHandlers();
}

function removeCartEventHandlers() {
    // Заменяем все кнопки для удаления существующих обработчиков
    document.querySelectorAll('.cart-action-btn, .add-to-cart-btn, .increase-quantity-btn, .decrease-quantity-btn').forEach(btn => {
        const newBtn = btn.cloneNode(true);
        if (btn.tagName === 'A') {
            newBtn.removeAttribute('href');
            if (newBtn.tagName === 'A') {
                // Создаем button вместо ссылки
                const buttonElement = document.createElement('button');
                buttonElement.type = 'button';
                buttonElement.className = newBtn.className;
                buttonElement.innerHTML = newBtn.innerHTML;
                // Перенос всех data-атрибутов
                Array.from(newBtn.attributes).forEach(attr => {
                    if (attr.name.startsWith('data-')) {
                        buttonElement.setAttribute(attr.name, attr.value);
                    }
                });
                newBtn.parentNode.replaceChild(buttonElement, newBtn);
                return; // После замены продолжаем со следующей кнопкой
            }
        }
        btn.parentNode.replaceChild(newBtn, btn);
    });
    
    console.log('Removed existing cart event handlers');
}

// Функция для делегирования обработки событий корзины
function handleCartEvent(event) {
    // Предотвращаем повторную обработку одного и того же события
    if (window.cartEventProcessing) {
        return;
    }
    
    // Проверяем, находится ли event.target или его родитель внутри кнопки корзины
    const cartButton = event.target.closest('.cart-action-btn, .add-to-cart-btn, .increase-quantity-btn, .decrease-quantity-btn');
    
    if (!cartButton) {
        // Проверяем кнопку очистки корзины отдельно
        const clearCartBtn = event.target.closest('#clear-cart-btn');
        if (clearCartBtn) {
            event.preventDefault();
            handleClearCart();
        }
        return; // Если клик не по кнопке корзины или очистки, ничего не делаем
    }
    
    // Устанавливаем флаг, что обрабатываем событие
    window.cartEventProcessing = true;
    
    // Отменяем стандартное действие, если это ссылка
    event.preventDefault();
    
    // Получаем необходимые данные
    let action = cartButton.dataset.action;
    let productId = cartButton.dataset.productId;
    
    // Если атрибуты отсутствуют, пытаемся определить их по классам
    if (!action) {
        if (cartButton.classList.contains('add-to-cart-btn')) {
            action = 'add_to_cart';
        } else if (cartButton.classList.contains('increase-quantity-btn')) {
            action = 'add_to_cart';
        } else if (cartButton.classList.contains('decrease-quantity-btn')) {
            action = 'decrease_quantity';
        }
    }
    
    // Если продукт не определен, пытаемся найти его в родительском контейнере
    if (!productId) {
        const container = cartButton.closest('.cart-controls');
        if (container) {
            productId = container.dataset.productId;
        }
    }
    
    if (!action || !productId) {
        console.error('Missing action or productId:', action, productId);
        window.cartEventProcessing = false; // Сбрасываем флаг
        return;
    }
    
    console.log(`Cart action: ${action} for product ID: ${productId}`);
    
    // Определяем URL для запроса
    const ROOT_PATH = '/main/';
    let ajaxUrl = '';
    
    // Выбираем правильный обработчик в зависимости от действия и страницы
    if (action === 'remove_from_cart' || action === 'clear_cart') {
        ajaxUrl = `${ROOT_PATH}cart.php?action=${action}&id_to_cart=${productId}&ajax=1`;
    } else {
        ajaxUrl = `${ROOT_PATH}catalogue.php?action=${action}&id_to_cart=${productId}&ajax=1`;
    }
    
    // Если это обновление количества
    if (action === 'update_quantity') {
        const controlsContainer = cartButton.closest('.cart-controls');
        if (controlsContainer) {
            const inputField = controlsContainer.querySelector('.product-quantity-input');
            if (inputField) {
                const qty = parseInt(inputField.value);
                if (!isNaN(qty) && qty >= 0) {
                    ajaxUrl += `&qty=${qty}`;
                }
            }
        }
    }
    
    // Показываем уведомление о выполнении действия
    if (action === 'add_to_cart') {
        window.showGlobalNotification('Добавление товара...', 'info', 1000);
    } else if (action === 'decrease_quantity') {
        window.showGlobalNotification('Уменьшение количества...', 'info', 1000);
    } else if (action === 'update_quantity') {
        window.showGlobalNotification('Обновление количества...', 'info', 1000);
    } else if (action === 'remove_from_cart') {
        window.showGlobalNotification('Удаление товара...', 'info', 1000);
    }
    
    // Отправляем запрос
    fetch(ajaxUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Ошибка сети: ' + response.status);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Некорректный JSON:', text);
                    throw new Error('Некорректный формат ответа от сервера');
                }
            });
        })
        .then(data => {
            console.log('AJAX response data:', data);
            if (data.success) {
                // Обновляем счетчик корзины
                window.updateCartBadge(data.total_cart_quantity);
                
                // Обновляем UI
                updateCartUI(data.product_id, data.new_quantity, data.stock_quantity);
                
                // Показываем сообщение об успехе
                if (action === 'add_to_cart') {
                    window.showGlobalNotification('Товар добавлен в корзину!', 'success');
                } else if (action === 'decrease_quantity') {
                    window.showGlobalNotification('Количество товара уменьшено', 'success');
                } else if (action === 'update_quantity') {
                    window.showGlobalNotification('Количество товара обновлено', 'success');
                } else if (action === 'remove_from_cart') {
                    window.showGlobalNotification('Товар удален из корзины', 'success');
                    // На странице корзины перезагружаем страницу
                    if (window.location.pathname.includes('cart.php')) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }
                }
            } else {
                window.showGlobalNotification(data.message || 'Ошибка при выполнении действия', 'danger');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            window.showGlobalNotification('Ошибка: ' + error.message, 'danger');
        })
        .finally(() => {
            // Сбрасываем флаг после завершения запроса
            setTimeout(() => {
                window.cartEventProcessing = false;
            }, 500); // Небольшая задержка для предотвращения случайных двойных кликов
        });
}

// Функция для обработки очистки корзины
function handleClearCart() {
    // Показываем уведомление
    window.showGlobalNotification('Очистка корзины...', 'info', 1000);
    
    // Отправляем запрос на очистку корзины
    const ROOT_PATH = '/main/';
    const ajaxUrl = `${ROOT_PATH}cart.php?action=clear_cart&ajax=1`;
    
    fetch(ajaxUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Ошибка сети: ' + response.status);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Некорректный JSON:', text);
                    throw new Error('Некорректный формат ответа от сервера');
                }
            });
        })
        .then(data => {
            if (data.success) {
                // Обновляем счетчик корзины
                window.updateCartBadge(0);
                window.showGlobalNotification('Корзина успешно очищена', 'success');
                // Перезагружаем страницу
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                window.showGlobalNotification(data.message || 'Не удалось очистить корзину', 'danger');
            }
        })
        .catch(error => {
            console.error('Clear cart error:', error);
            window.showGlobalNotification('Ошибка: ' + error.message, 'danger');
        });
}

// Функция обновления UI элементов корзины
function updateCartUI(productId, newQuantity, stockQuantity) {
    console.log(`Updating UI for product ${productId}, new quantity: ${newQuantity}, stock: ${stockQuantity}`);
    
    // 1. Обновляем UI на странице каталога/товара
    const controlsContainer = document.querySelector(`.cart-controls[data-product-id="${productId}"]`);
    if (controlsContainer) {
        // Обновляем дату stock
        if (stockQuantity !== undefined) {
            controlsContainer.dataset.stock = stockQuantity;
        } else {
            stockQuantity = parseInt(controlsContainer.dataset.stock) || 0;
        }
        
        // Если нет в наличии
        if (stockQuantity <= 0 && newQuantity <= 0) {
            controlsContainer.innerHTML = `
                <button class="btn btn-secondary w-100" disabled>
                    Нет в наличии
                </button>
            `;
            return;
        }
        
        // Если товар в корзине
        if (newQuantity > 0) {
            const currentInput = controlsContainer.querySelector('.product-quantity-input');
            if (currentInput) {
                // Если уже есть поле ввода, просто обновляем значение
                currentInput.value = newQuantity;
                currentInput.setAttribute('max', stockQuantity);
                
                // Обновляем состояние кнопки +
                const plusButton = controlsContainer.querySelector('[data-action="add_to_cart"]');
                if (plusButton) {
                    if (newQuantity >= stockQuantity) {
                        plusButton.classList.add('disabled');
                    } else {
                        plusButton.classList.remove('disabled');
                    }
                }
            } else {
                // Нужно создать контролы
                const isProductPage = window.location.pathname.includes('product.php');
                const btnSizeClass = isProductPage ? 'btn-lg' : 'btn-sm';
                const inputSizeClass = isProductPage ? 'form-control-lg' : 'form-control-sm';
                const plusDisabledClass = newQuantity >= stockQuantity ? 'disabled' : '';
                
                const controlHtml = `
                    ${isProductPage ? '<span class="me-3">Количество:</span>' : ''}
                    <div class="input-group quantity-control-group ajax-quantity-control" style="max-width: ${isProductPage ? '180px' : '185px'}; ${!isProductPage ? 'margin-left: auto; margin-right: auto;' : ''}">
                        <button type="button" class="btn btn-outline-warning ${btnSizeClass} cart-action-btn" data-action="decrease_quantity" data-product-id="${productId}">-</button>
                        <input type="number" class="form-control ${inputSizeClass} text-center product-quantity-input" value="${newQuantity}" min="1" max="${stockQuantity}" data-action="update_quantity">
                        <button type="button" class="btn btn-outline-warning ${btnSizeClass} cart-action-btn ${plusDisabledClass}" data-action="add_to_cart" data-product-id="${productId}">+</button>
                    </div>
                `;
                
                if (isProductPage) {
                    controlsContainer.innerHTML = `<div class="d-flex align-items-center">${controlHtml}</div>`;
                } else {
                    controlsContainer.innerHTML = controlHtml;
                }
            }
        } else {
            // Если товара нет в корзине, показываем кнопку "В корзину"
            const isProductPage = window.location.pathname.includes('product.php');
            const btnClass = isProductPage ? 'btn-primary btn-lg' : 'btn-primary w-100';
            const iconHtml = isProductPage ? '<i class="bi bi-cart-plus"></i> ' : '';
            
            controlsContainer.innerHTML = `
                <button type="button" class="btn ${btnClass} cart-action-btn" data-action="add_to_cart" data-product-id="${productId}" title="Добавить в корзину">
                    ${iconHtml}В корзину
                </button>
            `;
        }
    }
    
    // 2. Обновляем UI на главной странице
    const mainPageControl = document.getElementById('cart-controls-' + productId);
    if (mainPageControl) {
        if (newQuantity > 0) {
            mainPageControl.innerHTML = `
                <div class="quantity-control-group">
                    <button class="btn btn-sm btn-outline-primary decrease-quantity-btn" data-product-id="${productId}">
                        <i class="bi bi-dash"></i>
                    </button>
                    <span class="quantity-display mx-2">${newQuantity}</span>
                    <button class="btn btn-sm btn-outline-primary increase-quantity-btn" data-product-id="${productId}" ${newQuantity >= stockQuantity ? 'disabled' : ''}>
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
            `;
        } else {
            mainPageControl.innerHTML = `
                <button class="btn btn-primary cart-btn add-to-cart-btn" data-product-id="${productId}">
                    <i class="bi bi-cart-plus"></i>
                </button>
            `;
        }
    }
}

// Подключаем один глобальный обработчик на document
document.addEventListener('click', handleCartEvent);

// Добавляем обработчик изменения значения в поле ввода
document.addEventListener('change', function(event) {
    const input = event.target;
    if (input.classList.contains('product-quantity-input') && input.dataset.action === 'update_quantity') {
        const productId = input.closest('.cart-controls')?.dataset.productId;
        if (productId) {
            handleCartEvent({
                preventDefault: () => {},
                target: input
            });
        }
    }
}); 