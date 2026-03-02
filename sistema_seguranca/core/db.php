<?php
$config = require __DIR__.'/config.php';
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
  $config['db']['host'],$config['db']['port'],$config['db']['name'],$config['db']['charset']);
$pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);


// **FORÇA o fuso horário do MySQL para São Paulo (-03:00)**
$pdo->exec("SET time_zone = '-03:00'");
