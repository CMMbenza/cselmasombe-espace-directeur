<?php
// /directeur/quiz/update_status.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // fournit $pdo

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . BASE_URL . '/quiz/index.php'); exit;
}

$quiz_id = (int)($_POST['quiz_id'] ?? 0);
$statut  = trim((string)($_POST['statut'] ?? ''));

$allowed = ['approuvé','rejeter','à revoir'];
if ($quiz_id <= 0 || !in_array($statut, $allowed, true)) {
  header('Location: ' . BASE_URL . '/quiz/index.php'); exit;
}

$stmt = $pdo->prepare("UPDATE quiz SET statut=:s WHERE id=:id");
$stmt->execute([':s'=>$statut, ':id'=>$quiz_id]);

$back = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/quiz/index.php?ok=1');
header('Location: ' . $back);
exit;
