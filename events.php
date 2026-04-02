<?php
session_start();
include 'includes/db_connect.php';

$stmt = $pdo->prepare("SELECT * FROM events ORDER BY date DESC");
$stmt->execute();
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Кафе Вкусный Уголок - Акции и события</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/events.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main>
        <h2>Акции и события</h2>
        <div class="row">
    <?php foreach ($events as $event): ?>
        <div class="col">
            <div class="card">
                <img src="<?php echo htmlspecialchars($event['image'] ?? 'https://via.placeholder.com/400x200'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($event['title']); ?>">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                    <p class="card-text"><?php echo htmlspecialchars($event['description']); ?></p>
                    <p class="card-text"><small>Дата: <?php echo htmlspecialchars($event['date']); ?></small></p>
                </div>
            </div>
            </div>
                <?php endforeach; ?>
        </div>

    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
