-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Хост: sql310.infinityfree.com
-- Время создания: Апр 02 2026 г., 16:44
-- Версия сервера: 11.4.10-MariaDB
-- Версия PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `if0_40780426_vkusnyjugolok`
--

-- --------------------------------------------------------

--
-- Структура таблицы `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL,
  `guests` int(11) NOT NULL,
  `contact` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `bookings`
--

INSERT INTO `bookings` (`id`, `name`, `date`, `time`, `guests`, `contact`) VALUES
(1, 'Иван Иванов', '2025-08-15', '18:00:00', 4, 'ivan@example.com'),
(2, 'Анна Петрова', '2025-08-16', '19:30:00', 2, '+79991234567'),
(3, 'Мария Сидорова', '2025-08-17', '12:00:00', 6, 'maria@example.com'),
(4, 'Сергей Кузнецов', '2025-09-10', '20:00:00', 3, 'sergey@example.com'),
(5, 'Елена Васильева', '2025-09-12', '14:30:00', 5, '+79992345678'),
(6, 'Алексей Смирнов', '2025-09-15', '19:00:00', 2, 'alex@example.com'),
(7, 'Ольга Новикова', '2025-09-18', '13:00:00', 4, 'olga@example.com');

-- --------------------------------------------------------

--
-- Структура таблицы `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `date`, `image`) VALUES
(1, 'Вечер живой музыки', 'Наслаждайтесь живой музыкой в уютной атмосфере!', '2025-08-20', 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f5/No-Image-Placeholder-landscape.svg/1024px-No-Image-Placeholder-landscape.svg.png'),
(2, 'Скидка 20% на десерты', 'Только в эти выходные скидка на все десерты!', '2025-08-15', 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f5/No-Image-Placeholder-landscape.svg/1024px-No-Image-Placeholder-landscape.svg.png'),
(3, 'Винная дегустация', 'Попробуйте лучшие вина региона', '2025-08-25', 'uploads/689c7c1b3f56a.jpg'),
(4, 'Вечер живой музыки', 'Наслаждайтесь живой музыкой!', '2025-08-20', 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f5/No-Image-Placeholder-landscape.svg/1024px-No-Image-Placeholder-landscape.svg.png'),
(5, 'Мастер-класс по приготовлению кофе', 'Научим готовить идеальный кофе от профессионального бариста', '2025-09-05', 'uploads/coffee_masterclass.jpg'),
(6, 'Джазовый вечер', 'Выступление джазового трио каждую пятницу', '2025-09-12', 'uploads/jazz_night.jpg'),
(7, 'Осеннее меню', 'Представляем новые блюда из сезонных продуктов', '2025-09-20', 'uploads/autumn_menu.jpg'),
(8, 'Хэллоуинская вечеринка', 'Тематическая вечеринка с коктейлями и угощениями', '2025-10-31', 'uploads/halloween_party.jpg');

-- --------------------------------------------------------

--
-- Структура таблицы `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `menu`
--

INSERT INTO `menu` (`id`, `name`, `description`, `price`, `category`, `image`) VALUES
(1, 'Паста Карбонара', 'Классическая итальянская паста с беконом и сливочным соусом', '450.00', 'Основные блюда', 'uploads/689b3dbec371c.jpg'),
(2, 'Тирамису', 'Нежный десерт с маскарпоне и кофе', '250.00', 'Десерты', 'uploads/689b3e008a93d.jpg'),
(3, 'Латте', 'Кофе с молоком и пышной пенкой', '150.00', 'Напитки', 'https://img.freepik.com/premium-vector/menu-card-icon-simple-illustration-menu-card-vector-icon-web-design-isolated-white-background_98396-28746.jpg?semt=ais_hybrid&w=740'),
(4, 'Цезарь с курицей', 'Салат с курицей, пармезаном и сухариками', '350.00', 'Салаты', 'https://img.freepik.com/premium-vector/menu-card-icon-simple-illustration-menu-card-vector-icon-web-design-isolated-white-background_98396-28746.jpg?semt=ais_hybrid&w=740'),
(5, 'Бутерброд', 'Вкусный', '150.00', 'Основные блюда', 'https://img.freepik.com/premium-vector/menu-card-icon-simple-illustration-menu-card-vector-icon-web-design-isolated-white-background_98396-28746.jpg?semt=ais_hybrid&w=740'),
(6, 'Стейк Рибай', 'Сочный стейк с овощами гриль и соусом песто', '850.00', 'Основные блюда', 'uploads/steak_ribeye.jpg'),
(7, 'Томатный суп', 'Ароматный суп с базиликом и гренками', '280.00', 'Супы', 'uploads/tomato_soup.jpg'),
(8, 'Шоколадный фондан', 'Тёплый шоколадный пирог с жидкой начинкой', '320.00', 'Десерты', 'uploads/chocolate_fondant.jpg'),
(9, 'Капучино', 'Кофе с молочной пенкой и корицей', '180.00', 'Напитки', 'uploads/cappuccino.jpg'),
(10, 'Греческий салат', 'Свежие овощи, фета, оливки и орегано', '320.00', 'Салаты', 'uploads/greek_salad.jpg'),
(11, 'Бургер с говядиной', 'Сочная котлета, сыр, овощи и фирменный соус', '420.00', 'Основные блюда', 'uploads/beef_burger.jpg'),
(12, 'Лосось на гриле', 'Филе лосося с лимонным соусом и рисом', '650.00', 'Основные блюда', 'uploads/grilled_salmon.jpg'),
(13, 'Мохито', 'Освежающий коктейль с мятой и лаймом', '350.00', 'Напитки', 'uploads/mojito.jpg'),
(14, 'Брауни', 'Шоколадный пирог с грецкими орехами', '240.00', 'Десерты', 'uploads/brownie.jpg'),
(15, 'Сырный суп', 'Сливочный суп с тремя видами сыра', '310.00', 'Супы', 'uploads/cheese_soup.jpg'),
(16, 'Шашлык из курицы', 'Маринованная курица с овощами на гриле', '480.00', 'Основные блюда', 'uploads/chicken_shashlik.jpg'),
(17, 'Морковный торт', 'Нежный торт с крем-чизом', '270.00', 'Десерты', 'uploads/carrot_cake.jpg'),
(18, 'Чай Ассам', 'Ароматный чёрный чай с молоком', '120.00', 'Напитки', 'uploads/assam_tea.jpg'),
(19, 'Оливье', 'Классический салат с ветчиной и овощами', '290.00', 'Салаты', 'uploads/olivier_salad.jpg'),
(20, 'Крем-брюле', 'Десерт с хрустящей карамельной корочкой', '300.00', 'Десерты', 'uploads/creme_brulee.jpg'),
(21, 'Пицца Маргарита', 'Классическая пицца с томатами и моцареллой', '520.00', 'Основные блюда', 'uploads/pizza_margarita.jpg'),
(22, 'Горячий шоколад', 'Густой шоколадный напиток со сливками', '220.00', 'Напитки', 'uploads/hot_chocolate.jpg'),
(23, 'Борщ', 'Традиционный суп со сметаной и зеленью', '260.00', 'Супы', 'uploads/borscht.jpg'),
(24, 'Фруктовый салат', 'Свежие сезонные фрукты с йогуртовой заправкой', '230.00', 'Салаты', 'uploads/fruit_salad.jpg'),
(25, 'Чизкейк Нью-Йорк', 'Классический чизкейк с ягодным соусом', '340.00', 'Десерты', 'uploads/newyork_cheesecake.jpg');

-- --------------------------------------------------------

--
-- Структура таблицы `reviews`
--

CREATE TABLE reviews (
id int(11) NOT NULL,
name varchar(255) NOT NULL,
message text NOT NULL,
rating int(11) NOT NULL,
status enum('pending','approved','rejected') DEFAULT 'pending',
created_at timestamp DEFAULT CURRENT_TIMESTAMP,
approved tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `reviews`
--

INSERT INTO `reviews` (`id`, `name`, `message`, `rating`, `status`, `created_at`, `approved`) VALUES
(1, 'Дима', 'очень вкусно', 5, 'pending', '2025-09-11 11:47:03', 0),
(2, 'Александр', 'Прекрасное место для ужина! Паста карбонара просто божественная. Обслуживание на высшем уровне.', 5, 'approved', '2025-09-15 18:22:45', 1),
(3, 'Екатерина', 'Очень уютная атмосфера. Особенно понравились десерты. Обязательно вернусь!', 4, 'approved', '2025-09-16 12:15:30', 1),
(4, 'Максим', 'Цены немного завышены, но качество соответствует. Кофе вкусный, персонал вежливый.', 4, 'pending', '2025-09-17 09:45:12', 0),
(5, 'Анна', 'Были на винную дегустацию - незабываемо! Сомелье очень профессиональный, подобрал идеальные сочетания.', 5, 'approved', '2025-09-18 21:30:15', 1),
(6, 'Виктор', 'Заказывали стейк - приготовлен идеально. Порции большие, наелись вдоволь. Рекомендую!', 5, 'approved', '2025-09-19 19:45:22', 1),
(7, 'Татьяна', 'Приятное место для встреч с друзьями. Музыка не слишком громкая, можно спокойно общаться.', 4, 'pending', '2025-09-20 16:20:18', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `password`) VALUES
(1, 'admin', '$2y$10$3g6z6Xz3Q8z9z2Q8z9z2Qe6z6Xz3Q8z9z2Q8z9z2Qe6z6Xz3Q8z9');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
