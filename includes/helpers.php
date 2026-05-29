<?php
// includes/helpers.php
declare(strict_types=1);

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect(string $path){ header("Location: $path"); exit; }

/** En-têtes anti-cache (anti "retour navigateur") */
function send_nocache_headers(): void {
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');
  header('Expires: 0');
}
