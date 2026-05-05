<?php

// admin.php
declare(strict_types=1);
session_start();
include 'includes/db_connect.php';

// ===== Настройки / константы =====
const UPLOAD_DIR = 'uploads/';
const MENU_PLACEHOLDER = 'https://via.placeholder.com/300x200';
const EVENT_PLACEHOLDER = 'https://via.placeholder.com/400x200';
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5MB
$errors = [];
$messages = [];

// ===== Вспомогательные функции =====
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

function sanitize(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ensureUploadDir(): void {
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0755, true);
    }
}

function isLocalFile(string $path): bool {
    return !preg_match('~^[a-z][a-z0-9+.-]*://~i', $path) && file_exists($path);
}

function handleImageUpload(string $field, string $fallback): array {
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return [$fallback, null];
    }
    $tmp  = $_FILES[$field]['tmp_name'];
    $size = (int)$_FILES[$field]['size'];
    if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
        return [$fallback, 'Недопустимый размер файла (макс. 5 МБ).'];
    }
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($tmp) ?: '';
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        return [$fallback, 'Недопустимый формат изображения. Разрешены: JPG, PNG, GIF, WEBP.'];
    }
    ensureUploadDir();
    $ext     = $allowed[$mime];
    $newName = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest    = rtrim(UPLOAD_DIR, '/') . '/' . $newName;
    if (!move_uploaded_file($tmp, $dest)) {
        return [$fallback, 'Ошибка загрузки изображения.'];
    }
    return [$dest, null];
}

function requireCsrf(?string $token): bool {
    return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}

function colExists(PDO $pdo, string $table, string $col): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$col]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

// ===== Проверка авторизации =====
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

// ===== Выход =====
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ===== Текущий раздел =====
$section = filter_input(INPUT_GET, 'section', FILTER_DEFAULT) ?? 'bookings';
ensureUploadDir();

// ===== Инициализация: колонка status в bookings =====
try {
    if (!colExists($pdo, 'bookings', 'status')) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'pending'");
    }
} catch (Throwable) {}

// ===== Инициализация: таблица menu_categories =====
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS menu_categories (
        id   INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    )");
    $count = (int)$pdo->query("SELECT COUNT(*) FROM menu_categories")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO menu_categories (name) VALUES (?)");
        foreach (['Основные блюда', 'Десерты', 'Напитки', 'Салаты'] as $cat) {
            $stmt->execute([$cat]);
        }
    }
} catch (Throwable) {}

// ===== Определяем название колонки с текстом отзыва =====
$reviewTextCol = colExists($pdo, 'reviews', 'review') ? 'review'
    : (colExists($pdo, 'reviews', 'message') ? 'message' : 'review');

// ===== Загружаем категории для форм меню =====
function getCategories(PDO $pdo): array {
    try {
        return $pdo->query("SELECT id, name FROM menu_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

// ========================
// MENUS
// ========================
if ($section === 'menu') {
    if (isset($_POST['add_dish'])) {
        if (!requireCsrf($_POST['csrf'] ?? null)) {
            $errors[] = 'Неверный CSRF-токен.';
        } else {
            $name        = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $price       = (float)($_POST['price'] ?? 0);
            $category    = trim((string)($_POST['category'] ?? ''));
            [$imagePath, $err] = handleImageUpload('image', MENU_PLACEHOLDER);
            if ($err) $errors[] = $err;

            if (!$errors) {
                $stmt = $pdo->prepare("INSERT INTO menu (name, description, price, category, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $price, $category, $imagePath]);
                $messages[] = 'Блюдо добавлено.';
            }
        }
    }

    if (isset($_GET['delete'])) {
        $id    = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
        $token = $_GET['token'] ?? null;
        if ($id && requireCsrf($token)) {
            $stmt = $pdo->prepare("SELECT image FROM menu WHERE id = ?");
            $stmt->execute([$id]);
            $dish = $stmt->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("DELETE FROM menu WHERE id = ?")->execute([$id]);
            if ($dish && isset($dish['image']) && $dish['image'] !== MENU_PLACEHOLDER && isLocalFile($dish['image'])) {
                @unlink($dish['image']);
            }
            $messages[] = 'Блюдо удалено.';
        } else {
            $errors[] = 'Не удалось удалить блюдо (проверьте CSRF).';
        }
    }

    if (isset($_POST['edit_dish'])) {
        if (!requireCsrf($_POST['csrf'] ?? null)) {
            $errors[] = 'Неверный CSRF-токен.';
        } else {
            $id            = (int)($_POST['id'] ?? 0);
            $name          = trim((string)($_POST['name'] ?? ''));
            $description   = trim((string)($_POST['description'] ?? ''));
            $price         = (float)($_POST['price'] ?? 0);
            $category      = trim((string)($_POST['category'] ?? ''));
            $currentImage  = (string)($_POST['existing_image'] ?? MENU_PLACEHOLDER);
            [$newImage, $err] = handleImageUpload('image', $currentImage);
            if ($err) $errors[] = $err;

            if (!$errors) {
                if ($newImage !== $currentImage && $currentImage !== MENU_PLACEHOLDER && isLocalFile($currentImage)) {
                    @unlink($currentImage);
                }
                $stmt = $pdo->prepare("UPDATE menu SET name = ?, description = ?, price = ?, category = ?, image = ? WHERE id = ?");
                $stmt->execute([$name, $description, $price, $category, $newImage, $id]);
                $messages[] = 'Блюдо обновлено.';
            }
        }
    }
}

// ========================
// EVENTS
// ========================
if ($section === 'events') {
    if (isset($_POST['add_event'])) {
        if (!requireCsrf($_POST['csrf'] ?? null)) {
            $errors[] = 'Неверный CSRF-токен.';
        } else {
            $title       = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $date        = (string)($_POST['date'] ?? '');
            [$imagePath, $err] = handleImageUpload('image', EVENT_PLACEHOLDER);
            if ($err) $errors[] = $err;

            if (!$errors) {
                $stmt = $pdo->prepare("INSERT INTO events (title, description, date, image) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $description, $date, $imagePath]);
                $messages[] = 'Событие добавлено.';
            }
        }
    }

    if (isset($_GET['delete_event'])) {
        $id    = filter_input(INPUT_GET, 'delete_event', FILTER_VALIDATE_INT);
        $token = $_GET['token'] ?? null;
        if ($id && requireCsrf($token)) {
            $stmt = $pdo->prepare("SELECT image FROM events WHERE id = ?");
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);
            if ($event && isset($event['image']) && $event['image'] !== EVENT_PLACEHOLDER && isLocalFile($event['image'])) {
                @unlink($event['image']);
            }
            $messages[] = 'Событие удалено.';
        } else {
            $errors[] = 'Не удалось удалить событие (проверьте CSRF).';
        }
    }

    if (isset($_POST['edit_event'])) {
        if (!requireCsrf($_POST['csrf'] ?? null)) {
            $errors[] = 'Неверный CSRF-токен.';
        } else {
            $id            = (int)($_POST['id'] ?? 0);
            $title         = trim((string)($_POST['title'] ?? ''));
            $description   = trim((string)($_POST['description'] ?? ''));
            $date          = (string)($_POST['date'] ?? '');
            $currentImage  = (string)($_POST['existing_image'] ?? EVENT_PLACEHOLDER);
            [$newImage, $err] = handleImageUpload('image', $currentImage);
            if ($err) $errors[] = $err;

            if (!$errors) {
                if ($newImage !== $currentImage && $currentImage !== EVENT_PLACEHOLDER && isLocalFile($currentImage)) {
                    @unlink($currentImage);
                }
                $stmt = $pdo->prepare("UPDATE events SET title = ?, description = ?, date = ?, image = ? WHERE id = ?");
                $stmt->execute([$title, $description, $date, $newImage, $id]);
                $messages[] = 'Событие обновлено.';
            }
        }
    }
}

// ========================
// REVIEWS
// ========================
if ($section === 'reviews') {
    $token = $_GET['token'] ?? null;

    if (isset($_GET['delete_review'])) {
        $id = filter_input(INPUT_GET, 'delete_review', FILTER_VALIDATE_INT);
        if ($id && requireCsrf($token)) {
            $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
            $messages[] = 'Отзыв удалён.';
        } else {
            $errors[] = 'Не удалось удалить отзыв (проверьте CSRF).';
        }
    }

    if (isset($_GET['approve_review'])) {
        $id    = filter_input(INPUT_GET, 'approve_review', FILTER_VALIDATE_INT);
        $token = $_GET['token'] ?? '';
        if ($id && requireCsrf($token)) {
            $pdo->prepare("UPDATE reviews SET approved = 1 WHERE id = ?")->execute([$id]);
            $messages[] = 'Отзыв одобрен.';
        } else {
            $errors[] = 'Не удалось одобрить отзыв (проверьте CSRF).';
        }
    }

    if (isset($_GET['reject_review'])) {
        $id = filter_input(INPUT_GET, 'reject_review', FILTER_VALIDATE_INT);
        if ($id && requireCsrf($token)) {
            $pdo->prepare("UPDATE reviews SET approved = 0 WHERE id = ?")->execute([$id]);
            $messages[] = 'Отзыв отклонён.';
        } else {
            $errors[] = 'Не удалось отклонить отзыв (проверьте CSRF).';
        }
    }
}

// ========================
// BOOKINGS
// ========================
if ($section === 'bookings') {
    $token = $_GET['token'] ?? null;

    if (isset($_GET['delete_booking'])) {
        $id = filter_input(INPUT_GET, 'delete_booking', FILTER_VALIDATE_INT);
        if ($id && requireCsrf($token)) {
            $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$id]);
            $messages[] = 'Бронирование удалено.';
        } else {
            $errors[] = 'Не удалось удалить бронирование (проверьте CSRF).';
        }
    }

    if (isset($_POST['update_booking_status'])) {
        if (!requireCsrf($_POST['csrf'] ?? null)) {
            $errors[] = 'Неверный CSRF-токен.';
        } else {
            $id             = (int)($_POST['booking_id'] ?? 0);
            $status         = trim((string)($_POST['status'] ?? ''));
            $allowedStatuses = ['pending', 'confirmed', 'cancelled'];
            if ($id && in_array($status, $allowedStatuses, true)) {
                $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?")->execute([$status, $id]);
                $messages[] = 'Статус бронирования обновлён.';
            } else {
                $errors[] = 'Неверные данные для обновления статуса.';
            }
        }
    }
}

// ========================
// CATEGORIES
// ========================
if ($section === 'categories') {
    $token = $_GET['token'] ?? null;

    if (isset($_POST['add_category'])) {
        if (!requireCsrf($_POST['csrf'] ?? null)) {
            $errors[] = 'Неверный CSRF-токен.';
        } else {
            $name = trim((string)($_POST['cat_name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 100) {
                $errors[] = 'Название категории: от 1 до 100 символов.';
            } else {
                try {
                    $pdo->prepare("INSERT INTO menu_categories (name) VALUES (?)")->execute([$name]);
                    $messages[] = 'Категория добавлена.';
                } catch (PDOException $ex) {
                    $errors[] = ($ex->getCode() === '23000') ? 'Такая категория уже существует.' : 'Ошибка при добавлении.';
                }
            }
        }
    }

    if (isset($_GET['delete_category'])) {
        $id = filter_input(INPUT_GET, 'delete_category', FILTER_VALIDATE_INT);
        if ($id && requireCsrf($token)) {
            $stmt = $pdo->prepare("SELECT name FROM menu_categories WHERE id = ?");
            $stmt->execute([$id]);
            $cat = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cat) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE category = ?");
                $countStmt->execute([$cat['name']]);
                $dishCount = (int)$countStmt->fetchColumn();
                if ($dishCount > 0) {
                    $errors[] = "Нельзя удалить категорию «{$cat['name']}»: используется в {$dishCount} блюд(ах).";
                } else {
                    $pdo->prepare("DELETE FROM menu_categories WHERE id = ?")->execute([$id]);
                    $messages[] = 'Категория удалена.';
                }
            }
        } else {
            $errors[] = 'Не удалось удалить категорию (проверьте CSRF).';
        }
    }

    if (isset($_POST['edit_category'])) {
        if (!requireCsrf($_POST['csrf'] ?? null)) {
            $errors[] = 'Неверный CSRF-токен.';
        } else {
            $id   = (int)($_POST['cat_id'] ?? 0);
            $name = trim((string)($_POST['cat_name'] ?? ''));
            if ($id && $name !== '' && mb_strlen($name) <= 100) {
                $stmt = $pdo->prepare("SELECT name FROM menu_categories WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($old) {
                    try {
                        $pdo->beginTransaction();
                        $pdo->prepare("UPDATE menu_categories SET name = ? WHERE id = ?")->execute([$name, $id]);
                        $pdo->prepare("UPDATE menu SET category = ? WHERE category = ?")->execute([$name, $old['name']]);
                        $pdo->commit();
                        $messages[] = 'Категория обновлена.';
                    } catch (PDOException) {
                        $pdo->rollBack();
                        $errors[] = 'Ошибка при обновлении категории.';
                    }
                }
            } else {
                $errors[] = 'Неверные данные.';
            }
        }
    }
}

$statusLabels = ['pending' => 'Ожидает', 'confirmed' => 'Подтверждено', 'cancelled' => 'Отменено'];
$statusColors = ['pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'danger'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/header.php'; ?>
<div class="container my-5">
    <h2>Админ-панель</h2>

    <nav class="mb-4 d-flex flex-wrap gap-2">
        <a href="admin.php?section=bookings"   class="btn btn-<?= $section==='bookings'   ? 'primary' : 'secondary' ?>">Бронирования</a>
        <a href="admin.php?section=menu"       class="btn btn-<?= $section==='menu'       ? 'primary' : 'secondary' ?>">Меню</a>
        <a href="admin.php?section=categories" class="btn btn-<?= $section==='categories' ? 'primary' : 'secondary' ?>">Категории</a>
        <a href="admin.php?section=events"     class="btn btn-<?= $section==='events'     ? 'primary' : 'secondary' ?>">Акции и события</a>
        <a href="admin.php?section=reviews"    class="btn btn-<?= $section==='reviews'    ? 'primary' : 'secondary' ?>">Отзывы</a>
        <a href="admin.php?logout=1" class="btn btn-danger ms-auto">Выход</a>
    </nav>

    <?php foreach ($messages as $m): ?>
        <div class="alert alert-success py-2"><?= sanitize($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger py-2"><?= sanitize($e) ?></div>
    <?php endforeach; ?>

    <?php /* ==================== BOOKINGS ==================== */ ?>
    <?php if ($section === 'bookings'): ?>
        <h3>Список бронирований</h3>
        <div class="mb-3">
            <input type="text" id="search" class="form-control" placeholder="Поиск по имени, дате, времени или контакту" onkeyup="searchBookings()">
        </div>
        <div class="table-responsive">
        <table class="table table-striped align-middle" id="bookingsTable">
            <thead>
            <tr>
                <th>ID</th><th>Имя</th><th>Дата</th><th>Время</th><th>Гости</th><th>Контакт</th><th>Статус</th><th>Действия</th>
            </tr>
            </thead>
            <tbody id="bookingsBody">
            <?php
            $stmt = $pdo->query("SELECT * FROM bookings ORDER BY date DESC");
            while ($booking = $stmt->fetch(PDO::FETCH_ASSOC)):
                $bStatus = $booking['status'] ?? 'pending';
                $bColor  = $statusColors[$bStatus] ?? 'secondary';
                $bLabel  = $statusLabels[$bStatus] ?? sanitize($bStatus);
            ?>
            <tr data-name="<?= sanitize($booking['name']) ?>"
                data-date="<?= sanitize($booking['date']) ?>"
                data-time="<?= sanitize($booking['time']) ?>"
                data-contact="<?= sanitize($booking['contact']) ?>">
                <td><?= (int)$booking['id'] ?></td>
                <td><?= sanitize($booking['name']) ?></td>
                <td><?= sanitize($booking['date']) ?></td>
                <td><?= sanitize($booking['time']) ?></td>
                <td><?= (int)$booking['guests'] ?></td>
                <td><?= sanitize($booking['contact']) ?></td>
                <td><span class="badge bg-<?= $bColor ?>"><?= $bLabel ?></span></td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <!-- Изменить статус -->
                        <form method="POST" class="d-flex gap-1">
                            <input type="hidden" name="csrf" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                            <select name="status" class="form-select form-select-sm" style="width:auto">
                                <?php foreach ($statusLabels as $val => $lbl): ?>
                                    <option value="<?= $val ?>" <?= $bStatus === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="update_booking_status" class="btn btn-sm btn-primary">OK</button>
                        </form>
                        <!-- Удалить -->
                        <a href="admin.php?section=bookings&delete_booking=<?= (int)$booking['id'] ?>&token=<?= sanitize($csrf) ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Удалить бронирование #<?= (int)$booking['id'] ?>?')">Удалить</a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <script>
        function searchBookings() {
            const q = document.getElementById('search').value.toLowerCase();
            document.querySelectorAll('#bookingsBody tr').forEach(row => {
                const text = ['name','date','time','contact'].map(a => row.dataset[a] ?? '').join(' ').toLowerCase();
                row.style.display = text.includes(q) ? '' : 'none';
            });
        }
        </script>

    <?php /* ==================== MENU ==================== */ ?>
    <?php elseif ($section === 'menu'): ?>
        <h3>Редактирование меню</h3>

        <?php $dbCategories = getCategories($pdo); ?>

        <h4>Добавить блюдо</h4>
        <form method="POST" enctype="multipart/form-data" class="mb-5">
            <input type="hidden" name="csrf" value="<?= sanitize($csrf) ?>">
            <div class="mb-3"><label class="form-label">Название</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Описание</label><textarea name="description" class="form-control"></textarea></div>
            <div class="mb-3"><label class="form-label">Цена</label><input type="number" step="0.01" name="price" class="form-control" required></div>
            <div class="mb-3">
                <label class="form-label">Категория</label>
                <select name="category" class="form-control" required>
                    <option value="">Выберите категорию</option>
                    <?php foreach ($dbCategories as $cat): ?>
                        <option value="<?= sanitize($cat['name']) ?>"><?= sanitize($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Изображение</label><input type="file" name="image" class="form-control" accept="image/*"></div>
            <button type="submit" name="add_dish" class="btn btn-primary">Добавить</button>
        </form>

        <h4>Список блюд</h4>
        <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>Название</th><th>Цена</th><th>Категория</th><th>Изображение</th><th>Действия</th></tr></thead>
            <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM menu ORDER BY id DESC");
            while ($dish = $stmt->fetch(PDO::FETCH_ASSOC)):
            ?>
            <tr>
                <td><?= (int)$dish['id'] ?></td>
                <td><?= sanitize($dish['name']) ?></td>
                <td><?= sanitize((string)$dish['price']) ?></td>
                <td><?= sanitize($dish['category']) ?></td>
                <td><img src="<?= sanitize($dish['image']) ?>" alt="Изображение" style="width:80px;height:auto"></td>
                <td>
                    <a href="admin.php?section=menu&edit=<?= (int)$dish['id'] ?>" class="btn btn-sm btn-warning me-1">Редактировать</a>
                    <a href="admin.php?section=menu&delete=<?= (int)$dish['id'] ?>&token=<?= sanitize($csrf) ?>"
                       class="btn btn-sm btn-danger" onclick="return confirm('Удалить блюдо?')">Удалить</a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>

        <?php if (isset($_GET['edit'])):
            $id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM menu WHERE id = ?");
                $stmt->execute([$id]);
                $dish = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!empty($dish)):
        ?>
            <h4>Редактировать блюдо</h4>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <input type="hidden" name="existing_image" value="<?= sanitize($dish['image']) ?>">
                <div class="mb-3"><label class="form-label">Название</label><input type="text" name="name" value="<?= sanitize($dish['name']) ?>" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Описание</label><textarea name="description" class="form-control"><?= sanitize($dish['description']) ?></textarea></div>
                <div class="mb-3"><label class="form-label">Цена</label><input type="number" step="0.01" name="price" value="<?= sanitize((string)$dish['price']) ?>" class="form-control" required></div>
                <div class="mb-3">
                    <label class="form-label">Категория</label>
                    <select name="category" class="form-control" required>
                        <?php foreach ($dbCategories as $cat): ?>
                            <option value="<?= sanitize($cat['name']) ?>" <?= $dish['category'] === $cat['name'] ? 'selected' : '' ?>>
                                <?= sanitize($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Текущее изображение</label><br><img src="<?= sanitize($dish['image']) ?>" alt="Изображение" style="width:100px;height:auto"></div>
                <div class="mb-3"><label class="form-label">Новое изображение</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                <button type="submit" name="edit_dish" class="btn btn-primary">Сохранить</button>
                <a href="admin.php?section=menu" class="btn btn-secondary">Отмена</a>
            </form>
        <?php endif; endif; ?>

    <?php /* ==================== CATEGORIES ==================== */ ?>
    <?php elseif ($section === 'categories'): ?>
        <h3>Управление категориями блюд</h3>

        <h4>Добавить категорию</h4>
        <form method="POST" class="row g-2 mb-4">
            <input type="hidden" name="csrf" value="<?= sanitize($csrf) ?>">
            <div class="col-md-6">
                <input type="text" name="cat_name" class="form-control" placeholder="Название категории" maxlength="100" required>
            </div>
            <div class="col-auto">
                <button type="submit" name="add_category" class="btn btn-primary">Добавить</button>
            </div>
        </form>

        <h4>Список категорий</h4>
        <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead><tr><th>ID</th><th>Название</th><th>Блюд</th><th>Действия</th></tr></thead>
            <tbody>
            <?php
            $cats = $pdo->query("
                SELECT mc.id, mc.name,
                       COUNT(m.id) AS dish_count
                FROM menu_categories mc
                LEFT JOIN menu m ON m.category = mc.name
                GROUP BY mc.id, mc.name
                ORDER BY mc.name
            ")->fetchAll(PDO::FETCH_ASSOC);

            $editCatId = filter_input(INPUT_GET, 'edit_cat', FILTER_VALIDATE_INT);

            foreach ($cats as $cat):
            ?>
            <tr>
                <td><?= (int)$cat['id'] ?></td>
                <td>
                    <?php if ($editCatId === (int)$cat['id']): ?>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="csrf" value="<?= sanitize($csrf) ?>">
                            <input type="hidden" name="cat_id" value="<?= (int)$cat['id'] ?>">
                            <input type="text" name="cat_name" value="<?= sanitize($cat['name']) ?>" class="form-control form-control-sm" maxlength="100" required>
                            <button type="submit" name="edit_category" class="btn btn-sm btn-success">Сохранить</button>
                            <a href="admin.php?section=categories" class="btn btn-sm btn-secondary">Отмена</a>
                        </form>
                    <?php else: ?>
                        <?= sanitize($cat['name']) ?>
                    <?php endif; ?>
                </td>
                <td><?= (int)$cat['dish_count'] ?></td>
                <td class="d-flex gap-1">
                    <a href="admin.php?section=categories&edit_cat=<?= (int)$cat['id'] ?>" class="btn btn-sm btn-warning">Переименовать</a>
                    <?php if ((int)$cat['dish_count'] === 0): ?>
                        <a href="admin.php?section=categories&delete_category=<?= (int)$cat['id'] ?>&token=<?= sanitize($csrf) ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Удалить категорию «<?= sanitize($cat['name']) ?>»?')">Удалить</a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-danger" disabled title="Сначала переместите или удалите блюда этой категории">Удалить</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php /* ==================== EVENTS ==================== */ ?>
    <?php elseif ($section === 'events'): ?>
        <h3>Акции и события</h3>

        <h4>Добавить событие</h4>
        <form method="POST" enctype="multipart/form-data" class="mb-5">
            <input type="hidden" name="csrf" value="<?= sanitize($csrf) ?>">
            <div class="mb-3"><label class="form-label">Название</label><input type="text" name="title" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Описание</label><textarea name="description" class="form-control"></textarea></div>
            <div class="mb-3"><label class="form-label">Дата</label><input type="date" name="date" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Изображение</label><input type="file" name="image" class="form-control" accept="image/*"></div>
            <button type="submit" name="add_event" class="btn btn-primary">Добавить</button>
        </form>

        <h4>Список событий</h4>
        <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>Название</th><th>Дата</th><th>Изображение</th><th>Действия</th></tr></thead>
            <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM events ORDER BY date DESC");
            while ($event = $stmt->fetch(PDO::FETCH_ASSOC)):
            ?>
            <tr>
                <td><?= (int)$event['id'] ?></td>
                <td><?= sanitize($event['title']) ?></td>
                <td><?= sanitize($event['date']) ?></td>
                <td><img src="<?= sanitize($event['image']) ?>" alt="Изображение" style="width:80px;height:auto"></td>
                <td>
                    <a href="admin.php?section=events&edit_event=<?= (int)$event['id'] ?>" class="btn btn-sm btn-warning me-1">Редактировать</a>
                    <a href="admin.php?section=events&delete_event=<?= (int)$event['id'] ?>&token=<?= sanitize($csrf) ?>"
                       class="btn btn-sm btn-danger" onclick="return confirm('Удалить событие?')">Удалить</a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>

        <?php if (isset($_GET['edit_event'])):
            $id = filter_input(INPUT_GET, 'edit_event', FILTER_VALIDATE_INT);
            if ($id) {
                $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
                $stmt->execute([$id]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!empty($event)):
        ?>
            <h4>Редактировать событие</h4>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <input type="hidden" name="existing_image" value="<?= sanitize($event['image']) ?>">
                <div class="mb-3"><label class="form-label">Название</label><input type="text" name="title" value="<?= sanitize($event['title']) ?>" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Описание</label><textarea name="description" class="form-control"><?= sanitize($event['description']) ?></textarea></div>
                <div class="mb-3"><label class="form-label">Дата</label><input type="date" name="date" value="<?= sanitize($event['date']) ?>" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Текущее изображение</label><br><img src="<?= sanitize($event['image']) ?>" alt="Изображение" style="width:100px;height:auto"></div>
                <div class="mb-3"><label class="form-label">Новое изображение</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                <button type="submit" name="edit_event" class="btn btn-primary">Сохранить</button>
                <a href="admin.php?section=events" class="btn btn-secondary">Отмена</a>
            </form>
        <?php endif; endif; ?>

    <?php /* ==================== REVIEWS ==================== */ ?>
    <?php elseif ($section === 'reviews'): ?>
        <h3>Отзывы</h3>
        <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
            <tr><th>ID</th><th>Имя</th><th>Отзыв</th><th>Рейтинг</th><th>Дата</th><th>Статус</th><th>Действия</th></tr>
            </thead>
            <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC");
            while ($review = $stmt->fetch(PDO::FETCH_ASSOC)):
                $reviewText = $review[$reviewTextCol] ?? '';
            ?>
            <tr>
                <td><?= (int)$review['id'] ?></td>
                <td><?= sanitize($review['name']) ?></td>
                <td><?= nl2br(sanitize($reviewText)) ?></td>
                <td><?= (int)($review['rating'] ?? 0) ?> ★</td>
                <td><?= sanitize($review['created_at']) ?></td>
                <td>
                    <?php if ((int)($review['approved'] ?? 0)): ?>
                        <span class="badge bg-success">Одобрен</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Ожидает</span>
                    <?php endif; ?>
                </td>
                <td class="d-flex gap-1 flex-wrap">
                    <?php if (!(int)($review['approved'] ?? 0)): ?>
                        <a href="admin.php?section=reviews&approve_review=<?= (int)$review['id'] ?>&token=<?= sanitize($csrf) ?>"
                           class="btn btn-success btn-sm">Одобрить</a>
                    <?php else: ?>
                        <a href="admin.php?section=reviews&reject_review=<?= (int)$review['id'] ?>&token=<?= sanitize($csrf) ?>"
                           class="btn btn-warning btn-sm">Отклонить</a>
                    <?php endif; ?>
                    <a href="admin.php?section=reviews&delete_review=<?= (int)$review['id'] ?>&token=<?= sanitize($csrf) ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Удалить отзыв?')">Удалить</a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
