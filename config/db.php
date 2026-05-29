<?php
// config/db.php
declare(strict_types=1);
require_once __DIR__ . '/app.php';

try {
  $pdo = new PDO(
    'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
    DB_USERNAME,
    DB_PASSWORD,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => true,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  exit('Erreur de connexion à la base de données.');
}
