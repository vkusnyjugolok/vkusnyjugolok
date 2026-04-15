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
                    <a class="nav-link" href="ai_recommend.php"><i class="bi bi-robot"></i> ИИ-подбор</a>
                </div>
            </div>
        </div>
    </nav>
</header>

<script>
  // Не даём сервису засыпать пока пользователь на странице
  setInterval(function() {
    fetch('/index.php', { method: 'HEAD' })
      .catch(function() {}); // тихо игнорируем ошибки
  }, 20000); // каждые 20 секунд
</script>
