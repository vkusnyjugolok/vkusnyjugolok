<?php
  $host = getenv('DB_HOST') ?: 'sql7.freesqldatabase.com';
  $db   = getenv('DB_NAME') ?: 'sql7822094';
  $user = getenv('DB_USER') ?: 'sql7822094';
  $pass = getenv('DB_PASS') ?: 'a1jlKSQsAc'; 

  try {
      $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch (PDOException $e) {
      die("Ошибка подключения: " . $e->getMessage());
  }
  ?>