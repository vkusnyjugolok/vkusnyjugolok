<?php
  $host = 'sql310.infinityfree.com'; 
  $db = 'if0_40780426_vkusnyjugolok';
  $user = 'if0_40780426';
  $pass = 'SxEf8ruMFVF'; 

  try {
      $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch (PDOException $e) {
      die("Ошибка подключения: " . $e->getMessage());
  }
  ?>