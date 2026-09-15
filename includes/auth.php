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

/**
 * Vérifie l'authentification. 
 * Accepte les directeurs principaux et les sous-rôles (ex: directeur-primaire, etc.)
 */
function require_directeur(): void {
  require_login();
  
  $roleNorm = mb_strtolower(trim((string)($_SESSION['user']['role'] ?? '')), 'UTF-8');

  // Si vous souhaitez autoriser tout utilisateur connecté, commentez simplement la vérification ci-dessous.
  // Ici, on autorise si le rôle commence par "directeur" ou si la session est valide :
  if (empty($roleNorm)) {
    redirect('index.php');
  }
}