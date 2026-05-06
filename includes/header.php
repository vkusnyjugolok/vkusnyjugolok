<header class="site-header">
    <link rel="stylesheet" href="css/header.css">
    <div class="header-top text-center py-3">
        <h1 class="site-title m-0">Кафе «Вкусный Уголок»</h1>
    </div>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" 
                    aria-controls="mainNav" aria-expanded="false" aria-label="Переключить меню">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-center" id="mainNav">
                <div class="navbar-nav">
                    <a class="nav-link" href="index.php">Главная</a>
                    <a class="nav-link" href="menu.php">Меню</a>
                    <a class="nav-link" href="about.php">О нас</a>
                    <a class="nav-link" href="booking.php">Бронирование</a>
                    <a class="nav-link" href="contacts.php">Контакты</a>
                    <a class="nav-link" href="events.php">Акции и события</a>
                    <a class="nav-link" href="reviews.php">Отзывы</a>
                    <a class="nav-link" href="ai_recommend.php"><i class="bi bi-robot"></i> Онлайн-официант</a>
                </div>
            </div>
        </div>
    </nav>
</header>

<script>
  // Пинг каждые 14 секунд — не даём Render засыпать, пока пользователь на странице
  setInterval(function() {
    fetch(location.origin + '/index.php', { method: 'HEAD' }).catch(function() {});
  }, 14000);

  // Если страница грузится дольше 8 секунд и контента нет — перезагружаем автоматически
  (function() {
    var loaded = false;
    window.addEventListener('load', function() { loaded = true; });
    setTimeout(function() {
      if (!loaded || document.querySelector('main')?.innerText.trim().length < 20) {
        location.reload();
      }
    }, 8000);
  })();
</script>

<!-- Экран загрузки при холодном старте -->
<div id="site-loader" style="
  position:fixed;top:0;left:0;width:100%;height:100%;
  background:#fdf6ec;display:flex;flex-direction:column;
  align-items:center;justify-content:center;z-index:9999;
  transition:opacity .5s ease;
">
  <div style="font-family:'Playfair Display',serif;color:#8b5a2b;font-size:1.3rem;margin-bottom:1rem;">
    Кафе «Вкусный Уголок»
  </div>
  <div style="width:48px;height:48px;border:5px solid #e8d5b7;border-top-color:#8b5a2b;border-radius:50%;animation:spin .8s linear infinite;"></div>
  <p style="margin-top:1rem;color:#a0522d;font-size:.9rem;">Сайт просыпается, секунду…</p>
</div>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
<script>
  window.addEventListener('load', function() {
    var loader = document.getElementById('site-loader');
    if (!loader) return;
    loader.style.opacity = '0';
    setTimeout(function() { loader.style.display = 'none'; }, 500);
  });
  // Страховка: скрыть через 12 секунд в любом случае
  setTimeout(function() {
    var loader = document.getElementById('site-loader');
    if (loader) { loader.style.opacity = '0'; setTimeout(function() { loader.style.display='none'; }, 500); }
  }, 12000);
</script>
