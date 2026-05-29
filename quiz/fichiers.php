<?php
// /directeur/quiz/fichiers.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$quizId = (int)($_GET['id'] ?? 0);
if ($quizId <= 0) {
  header('Location: ' . BASE_URL . '/quiz/index.php'); exit;
}

$error = '';

try {
  // En-tête
  $stmt = $pdo->prepare("
    SELECT 
      q.id, q.agent_id, q.classe_id, q.type_quiz, q.format, q.titre, q.description, q.statut, q.date_limite, q.created_at,
      a.nom, a.postnom, a.prenom,
      c.description AS classe_desc, cy.description AS cycle_desc
    FROM quiz q
    JOIN agent a ON a.id=q.agent_id
    JOIN classe c ON c.id=q.classe_id
    JOIN cycle cy ON cy.id=c.cycle
    WHERE q.id=:id
    LIMIT 1
  ");
  $stmt->execute([':id'=>$quizId]);
  $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$quiz) { header('Location: ' . BASE_URL . '/quiz/index.php'); exit; }

  // Pièces jointes
  $stmtA = $pdo->prepare("
    SELECT id, file_path, original_name, mime_type, file_size, uploaded_at
    FROM quiz_attachment
    WHERE quiz_id=:id
    ORDER BY id
  ");
  $stmtA->execute([':id'=>$quizId]);
  $attachments = $stmtA->fetchAll(PDO::FETCH_ASSOC);

  // Compteurs
  $nbSub = (int)$pdo->prepare("SELECT COUNT(*) FROM quiz_submission WHERE quiz_id=:id")
                    ->execute([':id'=>$quizId]) ?: 0;
  $nbSub = (int)$pdo->query("SELECT COUNT(*) FROM quiz_submission WHERE quiz_id={$quizId}")->fetchColumn();

  $nbQ = (int)$pdo->query("SELECT COUNT(*) FROM quiz_question WHERE quiz_id={$quizId}")->fetchColumn();

} catch (Throwable $e) {
  $error = "Impossible de charger les fichiers du quiz.";
}
?>
<div class="container my-4">
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php else: ?>
    <div class="d-flex align-items-start gap-3 mb-3">
      <div>
        <h1 class="h5 mb-1"><?= e($quiz['titre']) ?></h1>
        <div class="small text-muted">
          Prof: <strong><?= e($quiz['nom'].' '.$quiz['postnom'].' '.$quiz['prenom']) ?></strong><br>
          Classe: <strong><?= e($quiz['classe_desc']) ?></strong> — Cycle: <strong><?= e($quiz['cycle_desc']) ?></strong><br>
          Type: <?= e($quiz['type_quiz']) ?> • Format: <?= e($quiz['format']) ?> • Créé: <?= e($quiz['created_at']) ?>
          <?php if (!empty($quiz['date_limite'])): ?> • Date limite: <?= e($quiz['date_limite']) ?><?php endif; ?>
        </div>
      </div>
      <?php
        $badge = 'secondary';
        if ($quiz['statut']==='approuvé') $badge='success';
        elseif ($quiz['statut']==='en attente') $badge='warning';
        elseif ($quiz['statut']==='rejeter') $badge='danger';
        elseif ($quiz['statut']==='à revoir') $badge='info';
      ?>
      <span class="badge text-bg-<?= $badge ?> ms-auto"><?= e($quiz['statut']) ?></span>
    </div>
    <?php if (!empty($quiz['description'])): ?>
      <div class="card shadow-sm mb-3"><div class="card-body">
        <div><?= nl2br(e($quiz['description'])) ?></div>
      </div></div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <h5 class="mb-3">Pièces jointes</h5>
            <?php if (!$attachments): ?>
              <div class="text-muted">Aucune pièce jointe.</div>
            <?php else: ?>
              <div class="list-group">
                <?php foreach ($attachments as $a): ?>
                  <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start"
                     href="<?= e($a['file_path']) ?>" target="_blank">
                    <div class="me-3">
                      <div class="fw-semibold"><?= e($a['original_name']) ?></div>
                      <div class="small text-muted">
                        <?= e($a['mime_type']) ?> • <?= (int)$a['file_size'] ?> o • <?= e($a['uploaded_at']) ?>
                      </div>
                    </div>
                    <span class="badge text-bg-secondary">Ouvrir</span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="mb-2">Résumé</h6>
            <div class="mb-1 small text-muted">Questions: <strong><?= (int)$nbQ ?></strong></div>
            <div class="mb-1 small text-muted">Soumissions: <strong><?= (int)$nbSub ?></strong></div>

            <hr>
            <form method="post" action="<?= BASE_URL ?>/quiz/update_status.php" class="d-flex flex-wrap gap-2">
              <input type="hidden" name="quiz_id" value="<?= (int)$quiz['id'] ?>">
              <button name="statut" value="approuvé" class="btn btn-success btn-sm">Approuver</button>
              <button name="statut" value="à revoir" class="btn btn-info btn-sm">À revoir</button>
              <button name="statut" value="rejeter" class="btn btn-danger btn-sm">Rejeter</button>
            </form>

            <a href="<?= BASE_URL ?>/quiz/index.php" class="btn btn-outline-secondary btn-sm mt-3">&larr; Retour</a>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
