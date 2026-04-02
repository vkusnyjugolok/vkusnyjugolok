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

// ===== Вспомогательные =====
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
    // считаем локальным, если это относительный путь без схемы
    return !preg_match('~^[a-z][a-z0-9+.-]*://~i', $path) && file_exists($path);
}

function handleImageUpload(string $field, string $fallback): array {
    // Возвращает [string $path, ?string $error]
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return [$fallback, null];
    }

    $tmp = $_FILES[$field]['tmp_name'];
    $size = (int)$_FILES[$field]['size'];
    if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
        return [$fallback, 'Недопустимый размер файла (макс. 5 МБ).'];
    }

    // MIME-проверка
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        return [$fallback, 'Недопустимый формат изображения. Разрешены: JPG, PNG, GIF, WEBP.'];
    }

    ensureUploadDir();
    $ext = $allowed[$mime];
    $newName = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = rtrim(UPLOAD_DIR, '/').'/'.$newName;

    if (!move_uploaded_file($tmp, $dest)) {
        return [$fallback, 'Ошибка загрузки изображения.'];
    }
    return [$dest, null];
}

function requireCsrf(?string $token): bool {
    return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
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

// ===== Обработка действий =====
ensureUploadDir();

// MENUS
if ($section === 'menu') {
    // Добавление блюда
    if (isset($_POST['add_dish'])) {
        if (!requireCsrf($_POST['csrf'] ?? null)) {
            $errors[] = 'Неверный CSRF-токен.';
        } else {
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $price = (float)($_POST['price'] ?? 0);
            $category = trim((string)($_POST['category'] ?? ''));
            [$imagePath, $err] = handleImageUpload('image', MENU_PLACEHOLDER);
            if ($err) $errors[] = $err;

            if (!$errors) {
                $stmt = $pdo->prepare("INSERT INTO menu (name, description, price, category, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $price, $category, $imagePath]);
                $messages[] = 'Блюдо добавлено.';
            }
        }
    }

    // Удаление блюда
    if (isset($_GET['delete'])) {
        $id = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
        $token = $_GET['token'] ?? null;
        if ($id && requireCsrf($token)) {
            $stmt = $pdo->prepare("SELECT image FROM menu WHERE id = ?");
            $stmt->execute([$id]);
            $dish = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("DELETE FROM menu WHERE id = ?");
            $stmt->execute([$id]);

            if ($dish && isset($dish['image']) && $dish['image'] !== MENU_PLACEHOLDER && isLocalFile($dish['image'])) {
                @unlink($dish['image']);
            }
            $messages[] = 'Блюдо удалено.';
        } else {
            $errors[] = 'Не удалось удалить блюдо (проверьте CSRF).';
        }
    }

    // Редактирование блюда
    if (isset($_POST['edit_dish'])) {
        if (!requireCsrf($_POST['csrf'] ?? null)) {
            $errors[] = 'Неверный CSRF-токен.';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $price = (float)($_POST['price'] ?? 0);
            $category = trim((string)($_POST['category'] ?? ''));
            $currentImage = (string)($_POST['existing_image'] ?? MENU_PLACEHOLDER);

            [$newImage, $err] = handleImageUpload('image', $currentImage);
            if ($err) $errors[] = $err;

            if (!$errors) {
                // если было новое изображение — удалить старое
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

// EVENTS
if ($section === 'events') {
    if (isset($_POST['add_event'])) {
        if (!requireCsrf($_POST['csrf'] ?? null)) {
            $errors[] = 'Неверный CSRF-токен.';
        } else {
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $date = (string)($_POST['date'] ?? '');
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
        $id = filter_input(INPUT_GET, 'delete_event', FILTER_VALIDATE_INT);
        $token = $_GET['token'] ?? null;
        if ($id && requireCsrf($token)) {
            $stmt = $pdo->prepare("SELECT image FROM events WHERE id = ?");
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$id]);

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
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $date = (string)($_POST['date'] ?? '');
            $currentImage = (string)($_POST['existing_image'] ?? EVENT_PLACEHOLDER);

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

// REVIEWS
if ($section === 'reviews') {
    $token = $_GET['token'] ?? null;

    if (isset($_GET['delete_review'])) {
        $id = filter_input(INPUT_GET, 'delete_review', FILTER_VALIDATE_INT);
        if ($id && requireCsrf($token)) {
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$id]);
            $messages[] = 'Отзыв удалён.';
        } else {
            $errors[] = 'Не удалось удалить отзыв (проверьте CSRF).';
        }
    }

    if (isset($_GET['approve_review'])) {
    $id = filter_input(INPUT_GET, 'approve_review', FILTER_VALIDATE_INT);
    $token = $_GET['token'] ?? '';

    if ($id && requireCsrf($token)) {
        $stmt = $pdo->prepare("UPDATE reviews SET approved = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $messages[] = 'Отзыв одобрен.';
    } else {
        $errors[] = 'Не удалось одобрить отзыв (проверьте CSRF).';
    }
}


    if (isset($_GET['reject_review'])) {
        $id = filter_input(INPUT_GET, 'reject_review', FILTER_VALIDATE_INT);
        if ($id && requireCsrf($token)) {
            $stmt = $pdo->prepare("UPDATE reviews SET approved = 0 WHERE id = ?");
            $stmt->execute([$id]);
            $messages[] = 'Отзыв отклонён.';
        } else {
            $errors[] = 'Не удалось отклонить отзыв (проверьте CSRF).';
        }
    }
}
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

    <nav class="mb-4">
        <a href="admin.php?section=bookings" class="btn btn-secondary me-2">Просмотр бронирований</a>
        <a href="admin.php?section=menu" class="btn btn-secondary me-2">Редактирование меню</a>
        <a href="admin.php?section=events" class="btn btn-secondary me-2">Акции и события</a>
        <a href="admin.php?section=reviews" class="btn btn-secondary me-2">Отзывы</a>
        <a href="admin.php?logout=1" class="btn btn-danger">Выход</a>
    </nav>

    <?php foreach ($messages as $m): ?>
        <div class="alert alert-success py-2"><?= sanitize($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger py-2"><?= sanitize($e) ?></div>
    <?php endforeach; ?>

    <?php if ($section === 'bookings'): ?>
        <h3>Список бронирований</h3>
        <div class="mb-3">
            <label for="search" class="form-label">Поиск</label>
            <input type="text" id="search" class="form-control" placeholder="Введите имя, дату, время или контакт" onkeyup="searchBookings()">
        </div>
        <table class="table table-striped" id="bookingsTable">
            <thead>
            <tr><th>ID</th><th>Имя</th><th>Дата</th><th>Время</th><th>Гости</th><th>Контакт</th></tr>
            </thead>
            <tbody id="bookingsBody">
            <?php
            $stmt = $pdo->query("SELECT * FROM bookings ORDER BY date DESC");
            while ($booking = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo '<tr data-name="'.sanitize($booking['name']).'" data-date="'.sanitize($booking['date']).'" data-time="'.sanitize($booking['time']).'" data-contact="'.sanitize($booking['contact']).'">';
                echo '<td>'.(int)$booking['id'].'</td><td>'.sanitize($booking['name']).'</td><td>'.sanitize($booking['date']).'</td><td>'.sanitize($booking['time']).'</td><td>'.(int)$booking['guests'].'</td><td>'.sanitize($booking['contact']).'</td></tr>';
            }
            ?>
            </tbody>
        </table>
        <script>
            function searchBookings() {
                const input = document.getElementById('search').value.toLowerCase();
                const rows = document.getElementById('bookingsBody').getElementsByTagName('tr');
                for (let i = 0; i < rows.length; i++) {
                    const name = rows[i].getAttribute('data-name').toLowerCase();
                    const date = rows[i].getAttribute('data-date').toLowerCase();
                    const time = rows[i].getAttribute('data-time').toLowerCase();
                    const contact = rows[i].getAttribute('data-contact').toLowerCase();
                    rows[i].style.display = (name.includes(input) || date.includes(input) || time.includes(input) || contact.includes(input)) ? '' : 'none';
                }
            }
        </script>

    <?php elseif ($section === 'menu'): ?>
        <h3>Редактирование меню</h3>

        <h4>Добавить блюдо</h4>
        <form method="POST" enctype="multipart/form-data" class="mb-5">
            <input type="hidden" name="csrf" value="<?= sanitize($csrf) ?>">
            <div class="mb-3"><label class="form-label">Название</label><input type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Описание</label><textarea name="description" class="form-control"></textarea></div>
            <div class="mb-3"><label class="form-label">Цена</label><input type="number" step="0.01" name="price" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Категория</label>
                <select name="category" class="form-control" required>
                    <option value="">Выберите категорию</option>
                    <option value="Основные блюда">Основные блюда</option>
                    <option value="Десерты">Десерты</option>
                    <option value="Напитки">Напитки</option>
                    <option value="Салаты">Салаты</option>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Изображение</label><input type="file" name="image" class="form-control" accept="image/*"></div>
            <button type="submit" name="add_dish" class="btn btn-primary">Добавить</button>
        </form>

        <h4>Список блюд</h4>
        <table class="table table-striped">
            <thead>
            <tr><th>ID</th><th>Название</th><th>Цена</th><th>Категория</th><th>Изображение</th><th>Действия</th></tr>
            </thead>
            <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM menu ORDER BY id DESC");
            while ($dish = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo '<tr><td>'.(int)$dish['id'].'</td><td>'.sanitize($dish['name']).'</td><td>'.sanitize((string)$dish['price']).'</td><td>'.sanitize($dish['category']).'</td>';
                echo '<td><img src="'.sanitize($dish['image']).'" alt="Изображение" style="width: 100px; height: auto;"></td>';
                echo '<td>
                        <a href="admin.php?section=menu&edit='.(int)$dish['id'].'" class="btn btn-sm btn-warning me-1">Редактировать</a>
                        <a href="admin.php?section=menu&delete='.(int)$dish['id'].'&token='.sanitize($csrf).'" class="btn btn-sm btn-danger" onclick="return confirm(\'Удалить?\')">Удалить</a>
                      </td></tr>';
            }
            ?>
            </tbody>
        </table>

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
                <div class="mb-3"><label class="form-label">Категория</label>
                    <select name="category" class="form-control" required>
                        <?php
                        $cats = ['Основные блюда','Десерты','Напитки','Салаты'];
                        foreach ($cats as $cat) {
                            $sel = ($dish['category'] === $cat) ? 'selected' : '';
                            echo '<option value="'.sanitize($cat).'" '.$sel.'>'.sanitize($cat).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Текущее изображение</label><br><img src="<?= sanitize($dish['image']) ?>" alt="Изображение" style="width: 100px; height: auto;"></div>
                <div class="mb-3"><label class="form-label">Новое изображение</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                <button type="submit" name="edit_dish" class="btn btn-primary">Сохранить</button>
            </form>
        <?php endif; endif; ?>

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
        <table class="table table-striped">
            <thead><tr><th>ID</th><th>Название</th><th>Дата</th><th>Изображение</th><th>Действия</th></tr></thead>
            <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM events ORDER BY date DESC");
            while ($event = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo '<tr><td>'.(int)$event['id'].'</td><td>'.sanitize($event['title']).'</td><td>'.sanitize($event['date']).'</td>';
                echo '<td><img src="'.sanitize($event['image']).'" alt="Изображение" style="width: 100px; height: auto;"></td>';
                echo '<td>
                        <a href="admin.php?section=events&edit_event='.(int)$event['id'].'" class="btn btn-sm btn-warning me-1">Редактировать</a>
                        <a href="admin.php?section=events&delete_event='.(int)$event['id'].'&token='.sanitize($csrf).'" class="btn btn-sm btn-danger" onclick="return confirm(\'Удалить?\')">Удалить</a>
                      </td></tr>';
            }
            ?>
            </tbody>
        </table>

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
                <div class="mb-3"><label class="form-label">Текущее изображение</label><br><img src="<?= sanitize($event['image']) ?>" alt="Изображение" style="width: 100px; height: auto;"></div>
                <div class="mb-3"><label class="form-label">Новое изображение</label><input type="file" name="image" class="form-control" accept="image/*"></div>
                <button type="submit" name="edit_event" class="btn btn-primary">Сохранить</button>
            </form>
        <?php endif; endif; ?>

    <?php elseif ($section === 'reviews'): ?>
        <h3>Отзывы</h3>
        <table class="table table-striped">
            <thead>
            <tr><th>ID</th><th>Имя</th><th>Отзыв</th><th>Дата</th><th>Статус</th><th>Действия</th></tr>
            </thead>
            <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM reviews ORDER BY created_at DESC");
            while ($review = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo '<tr>';
                echo '<td>'.(int)$review['id'].'</td>';
                echo '<td>'.sanitize($review['name']).'</td>';
                echo '<td>'.nl2br(sanitize($review['review'])).'</td>';
                echo '<td>'.sanitize($review['created_at']).'</td>';
                echo '<td>'.($review['approved'] ? 'Одобрен' : 'Ожидает').'</td>';
                echo '<td>';
                if (!(int)$review['approved']) {
                    echo '<a href="admin.php?section=reviews&approve_review='.(int)$review['id'].'&token='.sanitize($csrf).'" class="btn btn-success btn-sm me-1">Одобрить</a>';
                } else {
                    echo '<a href="admin.php?section=reviews&reject_review='.(int)$review['id'].'&token='.sanitize($csrf).'" class="btn btn-warning btn-sm me-1">Отклонить</a>';
                }
                echo '<a href="admin.php?section=reviews&delete_review='.(int)$review['id'].'&token='.sanitize($csrf).'" class="btn btn-danger btn-sm" onclick="return confirm(\'Удалить отзыв?\')">Удалить</a>';
                echo '</td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
