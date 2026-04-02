<?php
session_start();
include 'includes/db_connect.php';

if ($pdo instanceof PDO) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

/* ============ Утилиты ============ */
if (!function_exists('e')) {
    function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
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
$reviewCol   = $hasReview ? 'review' : ($hasMessage ? 'message' : null);

// CSRF токен
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Flash
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error   = $_SESSION['flash_error']   ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/* ============ Обработка формы отзыва (модалка) ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    try {
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            throw new RuntimeException('Ошибка безопасности. Попробуйте ещё раз.');
        }
        if (!$reviewCol) {
            throw new RuntimeException('Таблица reviews не содержит поля с текстом (review/message).');
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

        if ($hasApproved) {
            $sql = "INSERT INTO reviews (name, `$reviewCol`, rating, approved, created_at) VALUES (?, ?, ?, 0, NOW())";
            $params = [$name, $review, $rating];
        } elseif ($hasStatus) {
            $sql = "INSERT INTO reviews (name, `$reviewCol`, rating, status, created_at) VALUES (?, ?, ?, 'pending', NOW())";
            $params = [$name, $review, $rating];
        } else {
            $sql = "INSERT INTO reviews (name, `$reviewCol`, rating, created_at) VALUES (?, ?, ?, NOW())";
            $params = [$name, $review, $rating];
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $_SESSION['flash_success'] = 'Спасибо за отзыв! Он появится после модерации.';
    } catch (Throwable $ex) {
        $_SESSION['flash_error'] = $ex->getMessage();
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ============ Фильтр по оценке и пагинация (без поиска) ============ */
$ratingFilter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0; // 0 — любой
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 9;
$offset       = ($page - 1) * $perPage;

// Базовое условие "одобрен"
if ($hasApproved) {
    $statusWhere = "approved = 1";
} elseif ($hasStatus) {
    $statusWhere = "status = 'approved'";
} else {
    $statusWhere = "1=1"; // если статуса нет — показываем все
}

$where = [$statusWhere];
$params = [];

if ($ratingFilter >= 1 && $ratingFilter <= 5) {
    $where[] = "rating = ?";
    $params[] = $ratingFilter;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ============ Подсчёт и выборка ============ */
$total = 0;
$reviews = [];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM reviews $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    if ($reviewCol) {
        $sql = "SELECT id, name, `$reviewCol` AS review, rating, created_at 
                FROM reviews 
                $whereSql 
                ORDER BY id DESC 
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $ex) {
    $flash_error = 'Не удалось загрузить отзывы: ' . e($ex->getMessage());
}

$totalPages = max(1, (int)ceil($total / $perPage));

function stars(int $n): string {
    $n = max(0, min(5, $n));
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Отзывы — Кафе Вкусный Уголок</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Styles -->
    <link href="css/index.css" rel="stylesheet" />
</head>
<!-- sticky footer: body — flex-колонка, main — flex-grow -->
<body class="d-flex flex-column min-vh-100">
<?php include 'includes/header.php'; ?>

<main class="flex-grow-1">
    <div class="container py-5">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <h1 class="section-title m-0">Отзывы гостей</h1>
            <button type="button" class="btn btn-coffee" data-bs-toggle="modal" data-bs-target="#reviewModal">
                <i class="bi bi-chat-quote me-2"></i>Оставить отзыв
            </button>
        </div>

        <?php if ($flash_success): ?>
            <div class="alert alert-success"><?= e($flash_success) ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="alert alert-danger"><?= e($flash_error) ?></div>
        <?php endif; ?>

        <!-- Фильтр (без поиска) -->
        <form class="row g-2 mb-4" method="get" action="reviews.php">
            <div class="col-md-4">
                <select name="rating" class="form-select">
                    <option value="0" <?= $ratingFilter===0?'selected':''; ?>>Любая оценка</option>
                    <?php for ($r=5; $r>=1; $r--): ?>
                        <option value="<?= $r ?>" <?= $ratingFilter===$r?'selected':''; ?>><?= $r ?> и выше</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-8 d-flex gap-2">
                <button class="btn btn-outline-coffee" type="submit"><i class="bi bi-funnel me-1"></i>Применить</button>
                <a class="btn btn-outline-secondary" href="reviews.php"><i class="bi bi-x-circle me-1"></i>Сбросить</a>
            </div>
        </form>

        <!-- Список отзывов -->
        <div class="row">
            <?php if ($reviews && $reviewCol): ?>
                <?php foreach ($reviews as $rv): 
                    $nm = e($rv['name']);
                    $txt = nl2br(e($rv['review']));
                    $rt  = (int)$rv['rating'];
                    $dt  = $rv['created_at'] ? date('d.m.Y', strtotime($rv['created_at'])) : '';
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0"><?= $nm ?></h5>
                                <span class="text-warning" aria-label="Оценка: <?= $rt ?> из 5"><?= stars($rt) ?></span>
                            </div>
                            <p class="card-text flex-grow-1"><?= $txt ?></p>
                            <?php if ($dt): ?><div class="text-muted small mt-2"><i class="bi bi-calendar3 me-1"></i><?= e($dt) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">Пока нет подходящих отзывов.</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Пагинация -->
        <?php if ($totalPages > 1): 
            $qs = $_GET; unset($qs['page']);
            $base = 'reviews.php' . (count($qs) ? ('?' . http_build_query($qs) . '&') : '?') . 'page=';
        ?>
        <nav aria-label="Пагинация">
            <ul class="pagination justify-content-center mt-3">
                <li class="page-item <?= $page<=1?'disabled':'' ?>">
                    <a class="page-link" href="<?= $base . max(1,$page-1) ?>" aria-label="Предыдущая">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php
                $window = 1;
                for ($p=1; $p<=$totalPages; $p++) {
                    if ($p==1 || $p==$totalPages || ($p>=$page-$window && $p<=$page+$window) || $p<=2 || $p>$totalPages-2) {
                        $active = $p==$page ? 'active' : '';
                        echo '<li class="page-item '.$active.'"><a class="page-link" href="'.$base.$p.'">'.$p.'</a></li>';
                        $lastPrinted = $p;
                    } elseif (!isset($ellipsis) || $ellipsis !== $lastPrinted+1) {
                        echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                        $ellipsis = $lastPrinted+1;
                    }
                }
                ?>
                <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                    <a class="page-link" href="<?= $base . min($totalPages,$page+1) ?>" aria-label="Следующая">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</main>

<?php include 'includes/footer.php'; ?>

<!-- Модалка "Оставить отзыв" -->
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

<!-- JS (bundle обязателен для модалки) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
