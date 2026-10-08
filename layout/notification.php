<?php
// layout/notification.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$userId   = $_SESSION['user']['id'] ?? null;
$userRole = strtolower(trim((string)($_SESSION['user']['role'] ?? '')));

if (!$userId || !in_array(strtolower(trim((string)$userRole)), ['directeur', 'primaire', 'secondaire-humainte'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Non autorisé']);
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/get_annee_scolaire_encours.php';

try {
    $currentYear = defined('ANNEE_SCOLAIRE_LIBELLE') ? ANNEE_SCOLAIRE_LIBELLE : date('Y');
    $items = [];

    // 1. DÉTECTION : Journaux de classe en attente
    $stmtJ = $pdo->prepare("
        SELECT COUNT(*) 
        FROM journal_classe 
        WHERE anneScolaire = ? AND statut = 'en attente'
    ");
    $stmtJ->execute([$currentYear]);
    $countJournal = (int)$stmtJ->fetchColumn();

    if ($countJournal > 0) {
        $items[] = [
            'type'    => 'journal',
            'titre'   => 'Journaux en attente',
            'message' => "Vous avez <strong>{$countJournal}</strong> journal(x) de classe à valider.",
            'badge'   => $countJournal,
            'lien'    => '/directeur/journal_classe/?classe_id=0&single_date=&date_debut=&date_fin=&statut=en+attente'
        ];
    }

    // 2. DÉTECTION : Quiz en attente de validation
    $countQuiz = 0;
    try {
        $stmtQ = $pdo->prepare("
            SELECT COUNT(*)     
            FROM quiz 
            WHERE statut = 'en attente'
              AND (anneeScolaire = ? OR anneeScolaire IS NULL OR anneeScolaire = '')
        ");
        $stmtQ->execute([$currentYear]);
        $countQuiz = (int)$stmtQ->fetchColumn();

        if ($countQuiz > 0) {
            $items[] = [
                'type'    => 'quiz',
                'titre'   => 'Quiz à valider',
                'message' => "Vous avez <strong>{$countQuiz}</strong> quiz en attente de publication.",
                'badge'   => $countQuiz,
                'lien'    => '/directeur/quiz/index.php?statut=en+attente'
            ];
        }
    } catch (Exception $e) {
        error_log("Erreur Notif Quiz: " . $e->getMessage());
        $countQuiz = 0;
    }

    // 3. DÉTECTION : Annonces récentes (< 5 jours)
    $countAnnonces = 0;
    try {
        $stmtA = $pdo->prepare("
            SELECT COUNT(*) 
            FROM annonces 
            WHERE (anneeScolaire = ? OR anneeScolaire IS NULL OR anneeScolaire = '')
              AND (dest_type IN ('tous', 'profs') OR (dest_type = 'user' AND dest_id = ?))
              AND created_at >= NOW() - INTERVAL 5 DAY
        ");
        $stmtA->execute([$currentYear, $userId]);
        $countAnnonces = (int)$stmtA->fetchColumn();

        if ($countAnnonces > 0) {
            $items[] = [
                'type'    => 'annonce',
                'titre'   => 'Annonces récentes',
                'message' => "Vous avez <strong>{$countAnnonces}</strong> annonce(s) reçue(s) ces 5 derniers jours.",
                'badge'   => $countAnnonces,
                'lien'    => '/directeur/annonces/index.php'
            ];
        }
    } catch (Exception $e) {
        error_log("Erreur Notif Annonces: " . $e->getMessage());
        $countAnnonces = 0;
    }

    $totalUnread = $countJournal + $countQuiz + $countAnnonces;

    echo json_encode([
        'status' => 'success',
        'total'  => $totalUnread,
        'items'  => $items
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}