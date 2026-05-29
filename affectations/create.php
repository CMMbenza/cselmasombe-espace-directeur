<?php
// /directeur/affectations/create.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur(); // session + anti-cache
require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';

$msg = '';
$error = '';

/* ========= Préchargements ========= */
$agents = $classes = $intitules_existants = [];

try {
  // Professeurs
  $agents = $pdo->query("
    SELECT id, nom, postnom, prenom
    FROM agent
    ORDER BY nom, postnom, prenom
  ")->fetchAll();

  // Classes + Cycle pour affichage (checkboxes)
  $classes = $pdo->query("
    SELECT c.id, c.description AS classe, cy.description AS cycle
    FROM classe c
    LEFT JOIN cycle cy ON cy.id = c.cycle
    ORDER BY cy.description, c.description
  ")->fetchAll();

  // Intitulés de cours existants (distinct, toutes classes)
  $intitules_existants = $pdo->query("
    SELECT DISTINCT intitule
    FROM cours
    WHERE intitule IS NOT NULL AND intitule <> ''
    ORDER BY intitule
  ")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
  $error = "Vérifie les tables (agent, classe, cycle, cours, affectation_prof_classe).";
}

/* ========= Soumission ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $agent_id          = (int)($_POST['agent_id'] ?? 0);
  $classe_ids        = (array)($_POST['classe_ids'] ?? []);
  $date_affect       = trim((string)($_POST['date_affect'] ?? ''));
  $selected_existing = (array)($_POST['existing_intitules'] ?? []);
  $new_intitules_raw = trim((string)($_POST['new_intitules'] ?? ''));

  // Nettoyage des classes cochées
  $classe_ids = array_values(array_unique(array_filter(array_map('intval', $classe_ids), fn($x)=>$x>0)));

  // Parse des nouveaux intitulés (séparés par virgules)
  $new_intitules = [];
  if ($new_intitules_raw !== '') {
    foreach (explode(',', $new_intitules_raw) as $tok) {
      $t = trim($tok);
      if ($t !== '') $new_intitules[] = $t;
    }
  }

  // Construit la liste finale d’intitulés à appliquer (existants + nouveaux)
  $intitules = array_values(array_unique(array_merge($selected_existing, $new_intitules)));

  // Validations
  if ($agent_id <= 0) {
    $error = "Sélectionnez un professeur.";
  } elseif (!$classe_ids) {
    $error = "Cochez au moins une classe.";
  } elseif (!$intitules) {
    $error = "Choisissez au moins un cours existant ou saisissez un ou plusieurs nouveaux cours.";
  } elseif ($date_affect === '') {
    $error = "Choisissez une date d’affectation.";
  }

  if ($error === '') {
    try {
      $pdo->beginTransaction();

      $created_courses = 0;
      $reused_courses  = 0;
      $created_aff     = 0;
      $skipped_aff     = 0;

      foreach ($classe_ids as $cid) {
        foreach ($intitules as $intitule) {
          // 1) trouver / créer le cours pour cette classe
          $stmt = $pdo->prepare("SELECT id FROM cours WHERE classe_id = ? AND intitule = ?");
          $stmt->execute([$cid, $intitule]);
          $c = $stmt->fetch();

          if ($c) {
            $cours_id = (int)$c['id'];
            $reused_courses++;
          } else {
            $ins = $pdo->prepare("INSERT INTO cours (intitule, classe_id, created_at) VALUES (?,?, NOW())");
            $ins->execute([$intitule, $cid]);
            $cours_id = (int)$pdo->lastInsertId();
            $created_courses++;
          }

          // 2) créer l’affectation si elle n’existe pas déjà
          $chk = $pdo->prepare("
            SELECT id FROM affectation_prof_classe
            WHERE agent_id = ? AND classe_id = ? AND cours_id = ?
            LIMIT 1
          ");
          $chk->execute([$agent_id, $cid, $cours_id]);
          $already = $chk->fetch();

          if ($already) {
            $skipped_aff++;
          } else {
            $ins_aff = $pdo->prepare("
              INSERT INTO affectation_prof_classe (agent_id, classe_id, cours_id, date_affect, created_at)
              VALUES (?,?,?,?, NOW())
            ");
            $ins_aff->execute([$agent_id, $cid, $cours_id, $date_affect]);
            $created_aff++;
          }
        }
      }

      $pdo->commit();
      $msg = "Opération terminée : cours créés = {$created_courses}, cours réutilisés = {$reused_courses}, affectations créées = {$created_aff}, doublons ignorés = {$skipped_aff}.";
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = "Échec de l’enregistrement : ".$e->getMessage();
    }

    // Recharger listes
    try {
      $agents = $pdo->query("SELECT id, nom, postnom, prenom FROM agent ORDER BY nom, postnom, prenom")->fetchAll();
      $classes= $pdo->query("SELECT c.id, c.description AS classe, cy.description AS cycle FROM classe c LEFT JOIN cycle cy ON cy.id=c.cycle ORDER BY cy.description, c.description")->fetchAll();
      $intitules_existants = $pdo->query("SELECT DISTINCT intitule FROM cours WHERE intitule<>'' ORDER BY intitule")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { /* ignore */ }
  }
}
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Affecter un professeur à une ou plusieurs classes</h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/affectations/index.php">Retour à la liste</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="card p-3 shadow-sm">
    <!-- Professeur -->
    <div class="mb-3">
      <label class="form-label">Professeur (agent) *</label>
      <select name="agent_id" class="form-select" required>
        <option value="">— Sélectionner —</option>
        <?php foreach ($agents as $a): ?>
          <option value="<?= $a['id'] ?>"><?= e($a['nom'].' '.$a['postnom'].' '.$a['prenom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Classes (checkboxes avec cycle) -->
    <div class="mb-3">
      <label class="form-label d-block">Classes (cocher une ou plusieurs) *</label>

      <?php if (!$classes): ?>
        <div class="alert alert-warning mb-2">Aucune classe trouvée. Crée d’abord des classes et cycles.</div>
      <?php else: ?>
        <div class="border rounded p-2" style="max-height: 340px; overflow:auto">
          <div class="mb-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleClasses(true)">Tout cocher</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleClasses(false)">Tout décocher</button>
          </div>
          <div class="row">
            <?php foreach ($classes as $c): ?>
              <div class="col-12 col-md-6 col-lg-4">
                <label class="form-check">
                  <input class="form-check-input classe-check" type="checkbox" name="classe_ids[]" value="<?= $c['id'] ?>">
                  <span class="form-check-label">
                    <?= e($c['classe']) ?><?= $c['cycle'] ? ' — Cycle: '.e($c['cycle']) : '' ?>
                  </span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Cours : existants + nouveaux -->
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Cours existants (multi-sélection)</label>
        <select name="existing_intitules[]" class="form-select" multiple size="10">
          <?php foreach ($intitules_existants as $int): ?>
            <option value="<?= e($int) ?>"><?= e($int) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Maintiens Ctrl (ou Cmd) pour choisir plusieurs cours existants.</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Nouveaux cours (séparés par des virgules)</label>
        <textarea name="new_intitules" rows="4" class="form-control" placeholder="Ex: Mathématiques, Français, Chimie"></textarea>
        <div class="form-text">Chaque libellé saisi sera créé (ou réutilisé s’il existe déjà) pour chacune des classes cochées.</div>
      </div>
    </div>

    <!-- Date d’affectation -->
    <div class="mt-3 col-md-4">
      <label class="form-label">Date d’affectation *</label>
      <input type="date" name="date_affect" class="form-control" required>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary">Créer/associer les cours & affecter</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/affectations/index.php">Annuler</a>
    </div>
  </form>
</div>

<script>
function toggleClasses(check) {
  document.querySelectorAll('.classe-check').forEach(cb => cb.checked = !!check);
}
</script>

<?php require_once __DIR__.'/../layout/footer.php'; ?>
