-- SQL-запрос для добавления поля скидки в таблицу товаров
ALTER TABLE goods ADD COLUMN discount INT DEFAULT 0 COMMENT 'Скидка в процентах';

-- Обновляем существующие популярные товары с разными скидками для демонстрации
-- Обновляем товары с высоким рейтингом (4.5-5.0) - скидка 25%
UPDATE goods SET discount = 25 WHERE rating >= 4.5;

-- Товары с рейтингом 4.0-4.4 - скидка 15%
UPDATE goods SET discount = 15 WHERE rating >= 4.0 AND rating < 4.5;

-- Товары с рейтингом 3.5-3.9 - скидка 10%
UPDATE goods SET discount = 10 WHERE rating >= 3.5 AND rating < 4.0; 