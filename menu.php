<?php
session_start();
include 'includes/db_connect.php';

// Получаем все блюда, группируем по категориям
$categories = [];
$stmt = $pdo->query("SELECT * FROM menu ORDER BY category");
while ($dish = $stmt->fetch()) {
    $categories[$dish['category']][] = $dish;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кафе Вкусный Уголок - Меню</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/menu.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="container my-5">
        <h2>Наше меню</h2>
        <?php foreach ($categories as $category => $dishes): ?>
            <section class="my-4">
                <h3><?php echo htmlspecialchars($category ?: 'Без категории'); ?></h3>
                <div class="row">
                    <?php foreach ($dishes as $dish): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <img src="<?php echo htmlspecialchars($dish['image'] ?? 'https://avatars.mds.yandex.net/i?id=3aa4189be0d544fe0de7efd7eb465bb334e75c95-12727346-images-thumbs&n=13'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($dish['name']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($dish['name']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($dish['description']); ?></p>
                                    <p class="card-text fw-semibold">Цена: <?php echo htmlspecialchars($dish['price']); ?> ₽</p>
                                    <a href="booking.php" class="btn btn-coffee w-100">Заказать</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>