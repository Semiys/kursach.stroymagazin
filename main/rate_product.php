<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// 1. Проверяем, авторизован ли пользователь (ТЕПЕРЬ ОБЯЗАТЕЛЬНО)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Для оценки товара необходимо авторизоваться.']);
    exit;
}
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['product_id']) && isset($_POST['rating'])) {
        $product_id = filter_var($_POST['product_id'], FILTER_VALIDATE_INT);
        $rating_value = filter_var($_POST['rating'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);

        if ($product_id === false || $rating_value === false) {
            echo json_encode(['success' => false, 'message' => 'Неверные данные для оценки.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // 2. Проверяем, не оценивал ли пользователь этот товар ранее
            $stmt_check = $pdo->prepare("SELECT id FROM product_ratings WHERE user_id = ? AND product_id = ?");
            $stmt_check->execute([$user_id, $product_id]);
            if ($stmt_check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Вы уже оценили этот товар.']);
                $pdo->rollBack(); // Откатываем транзакцию, так как ничего не меняем
                exit;
            }

            // 3. Сохраняем новую оценку в product_ratings
            $stmt_insert_rating = $pdo->prepare("INSERT INTO product_ratings (user_id, product_id, rating_value) VALUES (?, ?, ?)");
            if (!$stmt_insert_rating->execute([$user_id, $product_id, $rating_value])) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении вашей оценки.']);
                exit;
            }

            // 4. Пересчитываем и обновляем средний рейтинг и количество оценок в таблице goods
            $stmt_recalculate = $pdo->prepare("
                SELECT AVG(rating_value) as average_rating, COUNT(id) as rating_count 
                FROM product_ratings 
                WHERE product_id = ?
            ");
            $stmt_recalculate->execute([$product_id]);
            $new_aggregated_data = $stmt_recalculate->fetch(PDO::FETCH_ASSOC);

            if ($new_aggregated_data) {
                $new_average = (float)($new_aggregated_data['average_rating'] ?? 0);
                $new_count = (int)($new_aggregated_data['rating_count'] ?? 0);

                $stmt_update_goods = $pdo->prepare("UPDATE goods SET rating = ?, rating_count = ? WHERE id = ?");
                if (!$stmt_update_goods->execute([$new_average, $new_count, $product_id])) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении общего рейтинга товара.']);
                    exit;
                }
            } else {
                // Эта ситуация маловероятна, если вставка выше прошла успешно, но на всякий случай
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Ошибка при пересчете общего рейтинга.']);
                exit;
            }
            
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Спасибо за вашу оценку!',
                'new_average_rating' => round($new_average, 1),
                'new_rating_count' => $new_count
            ]);

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Ошибка оценки товара (product_ratings): " . $e->getMessage());
            // Проверяем, не является ли ошибка нарушением уникального ключа (пользователь уже голосовал)
            // Код ошибки для Duplicate entry for key通常 является '23000'
            if ($e->getCode() == '23000') {
                 echo json_encode(['success' => false, 'message' => 'Вы уже оценили этот товар (ошибка БД).']);
            } else {
                 echo json_encode(['success' => false, 'message' => 'Ошибка базы данных при оценке товара.']);
            }
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Отсутствуют необходимые параметры.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Недопустимый метод запроса.']);
}
?> 