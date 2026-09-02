<?php
// /directeur/dashboard.php — Routeur de Dashboard
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_directeur(); // vérifie session + envoie headers no-cache

// Récupération du rôle en session
$userRole    = (string)($_SESSION['user']['role'] ?? '');
$isDirecteur = (strtolower(trim($userRole)) === 'directeur');

// Affiche la vue correspondant au rôle
if ($isDirecteur) {
    require_once __DIR__ . '/dashboard-generale.php';
} else {
    require_once __DIR__ . '/dashboard-collaborateur.php';
}