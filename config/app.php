<?php
// config/app.php
declare(strict_types=1);

// Chemin vers le fichier .env (à la racine)
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, " \"'");
        }
    }
}

// === Base de données ===
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USERNAME', $_ENV['DB_USERNAME']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD']);

// === Configuration de l'application ===
define('REQUIRED_ROLE_NORM', strtolower($_ENV['REQUIRED_ROLE_NORM']));
define('BASE_URL', $_ENV['BASE_URL']);