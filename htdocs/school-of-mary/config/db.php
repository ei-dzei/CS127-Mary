<?php
$dsn = 'mysql:host=127.0.0.1;
        dbname=mary127;
        charset=utf8mb4';
$user = 'root'; $pass = '';
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
$pdo = new PDO($dsn, $user, $pass, $options);
