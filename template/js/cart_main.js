/**
 * Скрипт для обработки корзины на главной странице
 * Работает вместе с cart_ajax.js для общего делегирования событий
 */

// Проверяем, загружен ли основной скрипт корзины
if (!window.cartAjaxScriptLoaded) {
    console.warn('cart_ajax.js не загружен! Функциональность корзины может работать некорректно.');
}

// Глобальная переменная, чтобы знать, что этот скрипт был загружен
window.cartMainScriptLoaded = true;

// Очищаем существующие обработчики с кнопок главной страницы
// Здесь мы не ждем DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', removeMainCartEventHandlers);
} else {
    removeMainCartEventHandlers();
}

function removeMainCartEventHandlers() {
    // Заменяем все кнопки для удаления существующих обработчиков
    document.querySelectorAll('.add-to-cart-btn, .increase-quantity-btn, .decrease-quantity-btn').forEach(btn => {
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
    });
    
    console.log('Removed existing cart event handlers on main page');
}

// Функции для главной страницы доступны глобально, но используются только
// если событие произошло на главной странице

// Не добавляем свои обработчики событий, т.к. используем глобальный обработчик из cart_ajax.js
console.log('Main page cart handlers initialized via event delegation'); 