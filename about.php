<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кафе Вкусный Уголок - О нас</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/about.css" rel="stylesheet">
</head>
<body>
    <div class="page-wrapper d-flex flex-column min-vh-100">
        <?php include 'includes/header.php'; ?>

        <!-- Hero-блок -->
        <section class="hero-about text-center text-white d-flex align-items-center justify-content-center">
            <div class="hero-content">
                <h1 class="display-4 fw-bold">О нас</h1>
                <p class="lead">Вкус, уют и тёплая атмосфера в каждом визите</p>
            </div>
        </section>

        <!-- Основной контент -->
        <main class="container my-5 flex-grow-1">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <p>Кафе «Вкусный Уголок» открылось в 2020 году и быстро стало любимым местом для жителей города. Мы специализируемся на свежих ингредиентах и авторских рецептах.</p>
                    <img src="img/about.jpg" alt="Команда кафе" class="img-fluid my-4">
                    <h3>Наша команда</h3>
                    <p>Шеф-повар Иван Иванов, бармен Анна Сидорова и многие другие профессионалы работают для вашего удовольствия.</p>
                    <h3>Наши ценности</h3>
                    <ul>
                        <li>Качество продуктов</li>
                        <li>Уютная атмосфера</li>
                        <li>Доступные цены</li>
                    </ul>
                </div>
            </div>
        </main>

        <?php include 'includes/footer.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
