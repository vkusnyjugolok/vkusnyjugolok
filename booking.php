<?php
session_start();
include 'includes/db_connect.php';

if (isset($_POST['book'])) {
    $name = $_POST['name'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $guests = $_POST['guests'];
    $contact = $_POST['contact'];

    $stmt = $pdo->prepare("INSERT INTO bookings (name, date, time, guests, contact) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $date, $time, $guests, $contact]);
    $success = 'Бронирование успешно!';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кафе Вкусный Уголок - Бронирование</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet"> <!-- основной стиль сайта -->
    <link href="css/booking.css" rel="stylesheet"> <!-- дополнительные стили для формы -->
</head>
<body>

<?php include 'includes/header.php'; ?>

<main class="container my-5">
    <section class="booking-section">
        <h2>Бронирование столика</h2>
        <?php if (isset($success)) echo '<p class="text-success">' . $success . '</p>'; ?>
        <form method="POST" class="booking-form p-4 shadow-sm rounded-3">
            <div class="mb-3">
                <label class="form-label">Имя</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Дата</label>
                <input type="date" name="date" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Время</label>
                <input type="time" name="time" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Количество гостей</label>
                <input type="number" name="guests" class="form-control" min="1" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Контакт (email или телефон)</label>
                <input type="text" name="contact" class="form-control" required>
            </div>
            <button type="submit" name="book" class="btn-coffee w-100">Забронировать</button>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
