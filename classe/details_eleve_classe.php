<?php
// /directeur/classe/details_eleve_classe.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // $pdo, e(), BASE_URL, anti-cache
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$classeId = (int)($_GET['classe_id'] ?? 0);
if ($classeId <= 0) {
    header('Location: index.php'); exit;
}

$error   = '';
$classe  = null;
$eleves  = [];

try {
    // 1) Récupération des informations de la classe
    $stmtC = $pdo->prepare("
        SELECT c.id, c.description AS classe_nom, c.anneeScolaire, cy.description AS cycle_nom
        FROM classe c
        LEFT JOIN cycle cy ON cy.id = c.cycle
        WHERE c.id = :id
        LIMIT 1
    ");
    $stmtC->execute([':id' => $classeId]);
    $classe = $stmtC->fetch(PDO::FETCH_ASSOC);

    if (!$classe) {
        header('Location: index.php'); exit;
    }

    // 2) Récupération des élèves de cette classe
    $stmtE = $pdo->prepare("
        SELECT id, matricule, nom, postnom, prenom, genre, lieu, dateDeNaissance, nationalite, STATUS
        FROM eleve
        WHERE classe = :classe_id
        ORDER BY nom ASC, postnom ASC, prenom ASC
    ");
    $stmtE->execute([':classe_id' => $classeId]);
    $eleves = $stmtE->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error = "Impossible de charger la composition de la classe : " . $e->getMessage();
}
?>

<div class="container my-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-0">
        Composition de la classe : <span class="text-primary"><?= e($classe['classe_nom']) ?></span>
      </h1>
      <small class="text-muted">
        Cycle : <?= e($classe['cycle_nom'] ?: '—') ?> | Année scolaire : <?= e($classe['anneeScolaire'] ?: '—') ?>
      </small>
    </div>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">&larr; Retour aux classes</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php else: ?>

    <div class="card shadow-sm">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-bold">Liste des élèves</span>
        <span class="badge bg-primary fs-6"><?= count($eleves) ?> Élève(s)</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 1%;">#</th>
                <th>Matricule</th>
                <th>Nom complet</th>
                <th>Sexe</th>
                <th>Lieu & Date de Naissance</th>
                <th>Nationalité</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($eleves)): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">
                    Aucun élève inscrit dans cette classe pour le moment.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($eleves as $index => $e): ?>
                  <tr>
                    <td><?= $index + 1 ?></td>
                    <td><code><?= e($e['matricule']) ?></code></td>
                    <td class="fw-semibold">
                      <?= e(mb_strtoupper($e['nom']) . ' ' . $e['postnom'] . ' ' . $e['prenom']) ?>
                    </td>
                    <td><?= e($e['genre']) ?></td>
                    <td class="small">
                      <?= e($e['lieu']) ?>, <?= $e['dateDeNaissance'] ? date('d/m/Y', strtotime($e['dateDeNaissance'])) : '—' ?>
                    </td>
                    <td><?= e($e['nationalite']) ?></td>
                    <td>
                      <span class="badge bg-<?= $e['STATUS'] === 'actif' ? 'success' : 'danger' ?>">
                        <?= e($e['STATUS']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>