<?php
session_start();
include 'includes/db_connect.php';

// Включим исключения (если не включено в db_connect)
if ($pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// ================= Утилиты =================
if (!function_exists('e')) {
    function e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

function colExists(PDO $pdo, string $table, string $col): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$col]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

// Определяем схему таблицы reviews
$hasReview   = colExists($pdo, 'reviews', 'review');
$hasMessage  = colExists($pdo, 'reviews', 'message');
$hasApproved = colExists($pdo, 'reviews', 'approved');
$hasStatus   = colExists($pdo, 'reviews', 'status');

$reviewCol = $hasReview ? 'review' : ($hasMessage ? 'message' : null);

// CSRF
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Flash
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ================= Обработка формы =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    try {
        // CSRF
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            throw new RuntimeException('Ошибка безопасности. Попробуйте ещё раз.');
        }

        if (!$reviewCol) {
            throw new RuntimeException('Таблица reviews не содержит поля для текста отзыва (review/message).');
        }

        $name   = trim((string)($_POST['name'] ?? ''));
        $review = trim((string)($_POST['message'] ?? ''));
        $rating = (int)($_POST['rating'] ?? 0);

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new RuntimeException('Имя должно быть от 2 до 100 символов.');
        }
        if ($review === '' || mb_strlen($review) < 5 || mb_strlen($review) > 2000) {
            throw new RuntimeException('Текст отзыва должен быть от 5 до 2000 символов.');
        }
        if ($rating < 1 || $rating > 5) {
            throw new RuntimeException('Оценка должна быть от 1 до 5.');
        }

        // Сборка INSERT под схему
        if ($hasApproved) {
            $sql = "INSERT INTO reviews (name, `$reviewCol`, rating, approved, created_at) VALUES (?, ?, ?, 0, NOW())";
            $params = [$name, $review, $rating];
        } elseif ($hasStatus) {
            // сохраняем как ожидающий модерации
            $sql = "INSERT INTO reviews (name, `$reviewCol`, rating, status, created_at) VALUES (?, ?, ?, 'pending', NOW())";
            $params = [$name, $review, $rating];
        } else {
            $sql = "INSERT INTO reviews (name, `$reviewCol`, rating, created_at) VALUES (?, ?, ?, NOW())";
            $params = [$name, $review, $rating];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $_SESSION['flash_success'] = 'Спасибо за отзыв! Он будет опубликован после модерации.';
    } catch (Throwable $ex) {
        $_SESSION['flash_error'] = $ex->getMessage();
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Кафе Вкусный Уголок — Главная</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Стили -->
    <link href="css/index.css" rel="stylesheet" />
</head>
<body>
<?php include 'includes/header.php'; ?>

<main>
    <div class="container py-5">

        <?php if ($flash_success): ?>
            <div class="alert alert-success"><?= e($flash_success) ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger"><?= e($flash_error) ?></div>
        <?php endif; ?>

        <!-- О нас -->
        <section class="section-card reveal" aria-labelledby="about-title">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <h2 id="about-title" class="section-title">О нашем кафе</h2>
                    <p class="mb-3">Кафе «Вкусный Уголок» — это место, где домашний уют встречается с изысканным вкусом. Мы готовим из отборных ингредиентов и уделяем внимание каждой детали — от подачи до сервиса.</p>
                    <p class="mb-4">Загляните на завтрак, ланч или ужин — у нас всегда тепло и вкусно.</p>
                    <a href="booking.php" class="btn btn-coffee"><i class="bi bi-cup-hot me-2"></i>Забронировать</a>
                </div>
                <div class="col-lg-6">
                    <img src="img/glav.jpg" class="img-fluid rounded-4 shadow-sm" alt="Интерьер кафе" loading="lazy">
                </div>
            </div>
        </section>

        <!-- Популярные блюда -->
        <section class="section-card mt-5 reveal" aria-labelledby="popular-title">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h2 id="popular-title" class="section-title m-0">Популярные блюда</h2>
                <a href="menu.php" class="link-coffee fw-semibold">Полное меню <i class="bi bi-arrow-right-short"></i></a>
            </div>
            <div class="row">
                <?php
                try {
                    $stmt = $pdo->query("SELECT id, name, price, image FROM menu ORDER BY id DESC LIMIT 3");
                    $i = 0;
                    while ($dish = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $i++;
                        $img = $dish['image'] ? e($dish['image']) : 'https://via.placeholder.com/600x400?text=Dish';
                        $name = e($dish['name']);
                        $price = isset($dish['price']) ? number_format((float)$dish['price'], 0, ',', ' ') : '—';
                        echo '<div class="col-md-4 mb-4">
                                <div class="card plate-card h-100 reveal" style="--delay: '.($i*80).'ms;">
                                    <div class="ratio ratio-4x3 overflow-hidden">
                                        <img src="'.$img.'" class="card-img-top" alt="'.$name.'" loading="lazy">
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h3 class="card-title h5 mb-2">'.$name.'</h3>
                                        <p class="card-text text-muted mb-3">Цена: '.$price.' ₽</p>
                                        <div class="mt-auto">
                                            <a href="menu.php" class="btn btn-outline-coffee w-100">Смотреть меню</a>
                                        </div>
                                    </div>
                                </div>
                            </div>';
                    }
                } catch (Throwable $ex) {
                    echo '<div class="alert alert-warning">Не удалось загрузить популярные блюда.</div>';
                }
                ?>
            </div>
        </section>

        <!-- Акции и события -->
        <section class="section-card mt-5 reveal" aria-labelledby="events-title">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h2 id="events-title" class="section-title m-0">Последние акции и события</h2>
                <a href="events.php" class="link-coffee fw-semibold">Все события <i class="bi bi-arrow-right-short"></i></a>
            </div>
            <div class="row">
                <?php
                try {
                    $stmt = $pdo->query("SELECT id, title, description, date, image FROM events ORDER BY date DESC LIMIT 3");
                    $i = 0;
                    while ($event = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $i++;
                        $img = $event['image'] ? e($event['image']) : 'https://via.placeholder.com/600x400?text=Event';
                        $title = e($event['title']);
                        $desc = e($event['description']);
                        $date = $event['date'] ? date('d.m.Y', strtotime($event['date'])) : '';
                        echo '<div class="col-md-4 mb-4">
                                <article class="card event-card h-100 reveal" style="--delay: '.($i*80).'ms;">
                                    <div class="ratio ratio-16x9 overflow-hidden">
                                        <img src="'.$img.'" class="card-img-top" alt="'.$title.'" loading="lazy">
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h3 class="card-title h5 mb-2">'.$title.'</h3>
                                        <p class="card-text text-muted flex-grow-1">'.$desc.'</p>
                                        <div class="d-flex align-items-center justify-content-between mt-2">
                                            <span class="badge bg-coffee-subtle text-coffee"><i class="bi bi-calendar-event me-1"></i>'.$date.'</span>
                                            <a href="events.php" class="btn btn-outline-coffee">Подробнее</a>
                                        </div>
                                    </div>
                                </article>
                            </div>';
                    }
                } catch (Throwable $ex) {
                    echo '<div class="alert alert-warning">Не удалось загрузить события.</div>';
                }
                ?>
            </div>
        </section>

        <!-- Отзывы -->
        <section class="section-card mt-5 reveal" aria-labelledby="reviews-title">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <h2 id="reviews-title" class="section-title m-0">Отзывы наших гостей</h2>
                <button type="button" class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#reviewModal">
                    <i class="bi bi-chat-quote me-2"></i>Оставить отзыв
                </button>
            </div>

            <div class="row">
                <?php
                try {
                    if (!$reviewCol) {
                        throw new RuntimeException('Нет колонки с текстом отзыва (review/message).');
                    }
                    // WHERE под обе схемы
                    if ($hasApproved) {
                        $where = "approved = 1";
                    } elseif ($hasStatus) {
                        $where = "status = 'approved'";
                    } else {
                        // если нет поля статуса — показываем все
                        $where = "1=1";
                    }

                    $sql = "SELECT name, `$reviewCol` AS review, rating FROM reviews WHERE $where ORDER BY id DESC LIMIT 3";
                    $stmt = $pdo->query($sql);
                    $empty = true;
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $empty = false;
                        $nm = e($r['name']);
                        $rv = nl2br(e($r['review']));
                        $rt = max(0, min(5, (int)$r['rating']));
                        echo '<div class="col-md-4 mb-4">
                                <div class="card h-100 shadow-sm border-0">
                                    <div class="card-body">
                                        <h5 class="card-title">'.$nm.'</h5>
                                        <p class="card-text">'.$rv.'</p>
                                        <p class="text-warning m-0" aria-label="Оценка: '.$rt.' из 5">';
                        for ($i=0; $i<$rt; $i++) echo '★';
                        for ($i=$rt; $i<5; $i++) echo '☆';
                        echo '          </p>
                                    </div>
                                </div>
                              </div>';
                    }
                    if ($empty) {
                        echo '<div class="col-12"><div class="alert alert-info mb-0">Пока нет одобренных отзывов.</div></div>';
                    }
                } catch (Throwable $ex) {
                    echo '<div class="alert alert-warning">Не удалось загрузить отзывы: '.e($ex->getMessage()).'</div>';
                }
                ?>
            </div>

            <div class="text-center mt-3">
                <a href="reviews.php" class="link-coffee fw-semibold">Читать все отзывы <i class="bi bi-arrow-right-short"></i></a>
            </div>
        </section>

    </div>
</main>

<?php include 'includes/footer.php'; ?>

<!-- ======= Модалка “Оставить отзыв” ======= -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header">
        <h5 class="modal-title" id="reviewModalLabel">Оставить отзыв</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="mb-3">
                <label class="form-label">Ваше имя</label>
                <input type="text" name="name" class="form-control" maxlength="100" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Оценка</label>
                <select name="rating" class="form-select" required>
                    <option value="">Выберите оценку</option>
                    <option value="5">5 — Отлично</option>
                    <option value="4">4 — Хорошо</option>
                    <option value="3">3 — Нормально</option>
                    <option value="2">2 — Плохо</option>
                    <option value="1">1 — Ужасно</option>
                </select>
            </div>
            <div class="mb-0">
                <label class="form-label">Ваш отзыв</label>
                <textarea name="message" class="form-control" rows="4" maxlength="2000" required></textarea>
                <div class="form-text">Отзыв появится после модерации.</div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
          <button type="submit" name="submit_review" class="btn btn-coffee">Отправить</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- JS: обязательно bundle (включает Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Плавное появление секций
    (function() {
        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReduced) return;
        const els = document.querySelectorAll('.reveal');
        const io = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('show');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });
        els.forEach(el => io.observe(el));
    })();
</script>
</body>
</html>
