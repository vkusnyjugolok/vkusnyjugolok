<?php
  $host = getenv('DB_HOST') ?: 'vkusnyjugolok-vkusnyjugolok-33e7.k.aivencloud.com';
  $db   = getenv('DB_NAME') ?: 'defaultdb';
  $user = getenv('DB_USER') ?: 'avnadmin';
  $pass = getenv('DB_PASS') ?: 'AVNS_sanRGmQ_dvKptNwcXZL'; 

  try {
      $pdo = new PDO("mysql:host=$host;port=23176;dbname=$db;charset=utf8", $user, $pass);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch (PDOException $e) {
      die("Ошибка подключения: " . $e->getMessage());
  }
  ?>
