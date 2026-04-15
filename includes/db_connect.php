<?php
$host = getenv('DB_HOST') ?: 'vkusnyjugolok-vkusnyjugolok-33e7.k.aivencloud.com';
$db   = getenv('DB_NAME') ?: 'defaultdb';
$user = getenv('DB_USER') ?: 'avnadmin';
$pass = getenv('DB_PASS') ?: 'AVNS_sanRGmQ_dvKptNwcXZL';
$port = 23176;

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // SSL required by Aiven
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}
?>
