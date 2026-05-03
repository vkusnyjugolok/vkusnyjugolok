<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кафе Вкусный Уголок - Контакты</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet"> <!-- основной стиль сайта -->
    <link href="css/contacts.css" rel="stylesheet"> <!-- дополнительные стили для контактов -->
</head>
<body>

<?php include 'includes/header.php'; ?>

<main class="container my-5">
    <section class="contact-section">
        <h2>Контакты</h2>
        <div class="contact-info mb-4">
            <p>Адрес: Красная площадь, 6, Курск</p>
            <p>Телефон: +7 (4712) 123-45-67</p>
            <p>Email: info@vkusnyugolok.ru</p>
            <p>Часы работы: Пн-Вс 9:00-22:00</p>
        </div>

        <div class="map-wrapper mb-5">
            <!-- Яндекс.Карта вместо Google Maps -->
            <iframe
                src="https://yandex.ru/map-widget/v1/?ll=36.1874%2C51.7373&z=16&mode=search&text=%D0%9A%D1%83%D1%80%D1%81%D0%BA%2C%20%D0%9A%D1%80%D0%B0%D1%81%D0%BD%D0%B0%D1%8F%20%D0%BF%D0%BB%D0%BE%D1%89%D0%B0%D0%B4%D1%8C%2C%206"
                width="100%" height="400" frameborder="0"
                allowfullscreen
                style="border:0;"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>

    </section>
</main>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
