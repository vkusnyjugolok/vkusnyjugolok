<?php
include('login-check.php'); // подключение авторизации
include('includes/db_connect.php');

$stmt = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC");
$reviews = $stmt->fetchAll();

if (isset($_GET['approve'])) {
    $pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?")->execute([$_GET['approve']]);
    header('Location: admin_reviews.php'); exit;
}
if (isset($_GET['reject'])) {
    $pdo->prepare("UPDATE reviews SET status = 'rejected' WHERE id = ?")->execute([$_GET['reject']]);
    header('Location: admin_reviews.php'); exit;
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$_GET['delete']]);
    header('Location: admin_reviews.php'); exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Отзывы</title>
    <link rel="stylesheet" href="admin.css">
    <link href="css/reviews.css" rel="stylesheet">\
</head>
<body>
<div class="admin-container">
    <h2>Отзывы</h2>

    <?php foreach ($reviews as $r): ?>
        <div class="order-card">
            <strong><?= htmlspecialchars($r['name']) ?> (<?= $r['rating'] ?>/5)</strong>
            <p><?= nl2br(htmlspecialchars($r['message'])) ?></p>
            <small><?= $r['created_at'] ?></small><br>
            <strong>Статус: <?= strtoupper($r['status']) ?></strong><br><br>

            <?php if ($r['status'] === 'pending'): ?>
                <a href="?approve=<?= $r['id'] ?>">✅ Одобрить</a>
                <a href="?reject=<?= $r['id'] ?>">❌ Отклонить</a>
            <?php endif ?>
            <a href="?delete=<?= $r['id'] ?>" onclick="return confirm('Удалить отзыв?')">🗑 Удалить</a>
        </div>
    <?php endforeach ?>
</div>
</body>
</html>
