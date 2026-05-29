<?php
// /directeur/cours/create.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur();
require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';

$error = '';
$msg   = '';
$classes = [];

try {
  $classes = $pdo->query("
    SELECT c.id, c.description AS classe, cy.description AS cycle
    FROM classe c
    LEFT JOIN cycle cy ON cy.id = c.cycle
    ORDER BY cy.description, c.description
  ")->fetchAll();
} catch (Throwable $e) {
  $error = "Impossible de charger les classes/cycles (vérifie les tables `classe`, `cycle`).";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $classe_id = (int)($_POST['classe_id'] ?? 0);
  $raw       = trim((string)($_POST['new_intitules'] ?? ''));

  // Parse multi: séparateurs = virgule et/ou saut de ligne
  $intitules = [];
  if ($raw !== '') {
    $tokens = preg_split('/[,\\n]+/u', $raw);
    foreach ($tokens as $tok) {
      $t = trim($tok);
      if ($t !== '') $intitules[] = $t;
    }
    $intitules = array_values(array_unique($intitules));
  }

  if ($classe_id <= 0) {
    $error = "Choisis une classe.";
  } elseif (!$intitules) {
    $error = "Saisis au moins un intitulé de cours (séparés par des virgules ou ligne par ligne).";
  } else {
    try {
      $created = 0; $reused = 0;

      $pdo->beginTransaction();
      foreach ($intitules as $int) {
        // existe déjà dans cette classe ?
        $stmt = $pdo->prepare("SELECT id FROM cours WHERE classe_id = ? AND intitule = ?");
        $stmt->execute([$classe_id, $int]);
        $row = $stmt->fetch();

        if ($row) {
          $reused++;
        } else {
          $ins = $pdo->prepare("INSERT INTO cours (intitule, classe_id, created_at) VALUES (?,?, NOW())");
          $ins->execute([$int, $classe_id]);
          $created++;
        }
      }
      $pdo->commit();

      // Redirection avec flag succès
      header('Location: '.BASE_URL.'/cours/index.php?ok=1&created='.$created.'&reused='.$reused);
      exit;
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = "Erreur lors de l’enregistrement : ".$e->getMessage();
    }
  }
}
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Créer des cours</h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/cours/index.php">Retour à la liste</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="card p-3 shadow-sm">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Classe *</label>
        <select name="classe_id" class="form-select" required>
          <option value="">— Sélectionner —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>">
              <?= e($c['classe']) ?><?= $c['cycle'] ? ' — Cycle: '.e($c['cycle']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label">Intitulés des cours *</label>
        <textarea name="new_intitules" rows="6" class="form-control" placeholder="Ex:
Mathématiques, Français, Chimie
ou une valeur par ligne"></textarea>
        <div class="form-text">
          Tu peux saisir plusieurs intitulés séparés par des <strong>virgules</strong> ou <strong>ligne par ligne</strong>.  
          Les doublons existants dans la même classe seront ignorés et réutilisés.
        </div>
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary">Enregistrer</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/cours/index.php">Annuler</a>
    </div>
  </form>
</div>
<?php require_once __DIR__.'/../layout/footer.php'; ?>
