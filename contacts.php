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
            <p>Адрес: ул. Центральная, 10, Москва</p>
            <p>Телефон: +7 (495) 123-45-67</p>
            <p>Email: info@vkusnyugolok.ru</p>
            <p>Часы работы: Пн-Вс 9:00-22:00</p>
        </div>

        <div class="map-wrapper mb-5">
            <!-- Яндекс.Карта вместо Google Maps -->
            <iframe
                src="https://yandex.ru/map-widget/v1/?ll=37.6173%2C55.7558&z=14&mode=search&text=%D1%83%D0%BB.%20%D0%A6%D0%B5%D0%BD%D1%82%D1%80%D0%B0%D0%BB%D1%8C%D0%BD%D0%B0%D1%8F%2C%2010%2C%20%D0%9C%D0%BE%D1%81%D0%BA%D0%B2%D0%B0"
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
