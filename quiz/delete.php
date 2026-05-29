<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/quiz/index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$token = $_POST['csrf_token'] ?? '';

if (!$id || !$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    die('Requête invalide (CSRF)');
}

try {

    $pdo->beginTransaction();

    /* =========================
       Vérifier quiz existant
    ========================== */
    $stmt = $pdo->prepare("
        SELECT statut 
        FROM quiz 
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id'=>$id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        throw new Exception("Quiz introuvable.");
    }

    /* =========================
       Vérifier statut autorisé
    ========================== */
    if (!in_array($quiz['statut'], ['rejeter','à revoir'])) {
        throw new Exception("Suppression non autorisée.");
    }

    /* =========================
       Vérifier s'il y a des soumissions
    ========================== */
    $stmtS = $pdo->prepare("
        SELECT COUNT(*) 
        FROM quiz_submission 
        WHERE quiz_id = :id
    ");
    $stmtS->execute([':id'=>$id]);
    $nbSub = (int)$stmtS->fetchColumn();

    if ($nbSub > 0) {
        throw new Exception("Impossible de supprimer : des élèves ont déjà soumis.");
    }

    /* =========================
       Suppression physique
       (les tables liées ont ON DELETE CASCADE)
    ========================== */
    $stmtDel = $pdo->prepare("
        DELETE FROM quiz 
        WHERE id = :id
    ");
    $stmtDel->execute([':id'=>$id]);

    $pdo->commit();

    header('Location: ' . BASE_URL . '/quiz/index.php?deleted=1');
    exit;

} catch (Throwable $e) {

    $pdo->rollBack();

    header('Location: ' . BASE_URL . '/quiz/view.php?id=' . $id . '&error=1');
    exit;
}