<?php
// logout.php
declare(strict_types=1);
if (session_status()===PHP_SESSION_NONE) session_start();

// Vider les variables de session
$_SESSION = [];

// Supprimer le cookie de session (client)
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Détruire la session
session_destroy();

// Empêcher que la page soit mise en cache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Rediriger vers la page de login
header('Location: index.php');
exit;
