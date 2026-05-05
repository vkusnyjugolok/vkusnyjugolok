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
    <title>Кафе Вкусный Уголок — Акции и события</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="css/index.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include 'includes/header.php'; ?>

    <main class="flex-grow-1">
        <div class="container py-5">
            <div class="section-card">
                <h2 class="section-title mb-4">Акции и события</h2>

                <?php if (empty($events)): ?>
                    <div class="alert alert-info">Пока нет событий.</div>
                <?php else: ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
                    <?php foreach ($events as $i => $event):
                        $img  = htmlspecialchars($event['image'] ?? 'https://via.placeholder.com/400x200');
                        $title = htmlspecialchars($event['title']);
                        $desc  = htmlspecialchars($event['description']);
                        $date  = $event['date'] ? date('d.m.Y', strtotime($event['date'])) : '';
                    ?>
                    <div class="col">
                        <article class="card h-100 reveal" style="--delay: <?= $i * 60 ?>ms">
                            <div class="ratio ratio-16x9 overflow-hidden">
                                <img src="<?= $img ?>" class="card-img-top" alt="<?= $title ?>" loading="lazy">
                            </div>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= $title ?></h5>
                                <p class="card-text flex-grow-1"><?= $desc ?></p>
                                <?php if ($date): ?>
                                <div class="mt-2">
                                    <span class="badge bg-coffee-subtle text-coffee">
                                        <i class="bi bi-calendar-event me-1"></i><?= $date ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('show'); io.unobserve(e.target); } });
        }, { threshold: 0.12 });
        document.querySelectorAll('.reveal').forEach(el => io.observe(el));
    })();
    </script>
</body>
</html>
