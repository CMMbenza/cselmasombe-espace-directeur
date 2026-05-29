<?php
// includes/auth.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/helpers.php';

function require_login(): void {
  send_nocache_headers(); // empêche l'affichage depuis le cache
  if (empty($_SESSION['user'])) {
    redirect('index.php');
  }
}

function require_directeur(): void {
  require_login();
  $roleNorm = mb_strtolower(trim((string)($_SESSION['user']['role'] ?? '')), 'UTF-8');
  if ($roleNorm !== REQUIRED_ROLE_NORM) {
    // coupe toute session invalide
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();

    http_response_code(403);
    exit('Accès refusé : rôle non autorisé.');
  }
}
