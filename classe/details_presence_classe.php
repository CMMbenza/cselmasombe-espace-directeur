<?php
// /directeur/classe/details_presence_classe.php
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
$appels  = [];
$stats   = ['total_appels' => 0, 'presents' => 0, 'absents' => 0];

try {
    // 1) Infos classe
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

    // 2) Historique des appels avec statistiques par séance
    $stmtA = $pdo->prepare("
        SELECT 
            a.id,
            a.date_appel,
            a.anneeScolaire,
            a.created_at,
            COUNT(ad.id) AS total_eleves,
            SUM(CASE WHEN ad.statut = 'present' THEN 1 ELSE 0 END) AS nb_presents,
            SUM(CASE WHEN ad.statut = 'absent' THEN 1 ELSE 0 END) AS nb_absents
        FROM appel a
        LEFT JOIN appel_detail ad ON ad.appel_id = a.id
        WHERE a.classe_id = :classe_id
        GROUP BY a.id, a.date_appel, a.anneeScolaire, a.created_at
        ORDER BY a.date_appel DESC, a.id DESC
    ");
    $stmtA->execute([':classe_id' => $classeId]);
    $appels = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    // 3) Cumul global pour les cartes statistiques
    $stmtS = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT a.id) AS total_appels,
            SUM(CASE WHEN ad.statut = 'present' THEN 1 ELSE 0 END) AS total_presents,
            SUM(CASE WHEN ad.statut = 'absent' THEN 1 ELSE 0 END) AS total_absents
        FROM appel a
        JOIN appel_detail ad ON ad.appel_id = a.id
        WHERE a.classe_id = :classe_id
    ");
    $stmtS->execute([':classe_id' => $classeId]);
    $rowS = $stmtS->fetch(PDO::FETCH_ASSOC);
    if ($rowS) {
        $stats['total_appels'] = (int)$rowS['total_appels'];
        $stats['presents']     = (int)$rowS['total_presents'];
        $stats['absents']      = (int)$rowS['total_absents'];
    }

} catch (Throwable $e) {
    $error = "Impossible de charger le suivi des présences : " . $e->getMessage();
}
?>

<div class="container my-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-0">
        Suivi des présences : <span class="text-primary"><?= e($classe['classe_nom']) ?></span>
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

    <!-- Cartes statistiques de synthèses -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light shadow-sm text-center">
          <div class="text-muted small">Séances d'appel réalisées</div>
          <div class="fs-4 fw-bold text-dark"><?= $stats['total_appels'] ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light shadow-sm text-center">
          <div class="text-muted small">Cumul des Présences</div>
          <div class="fs-4 fw-bold text-success"><?= $stats['presents'] ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light shadow-sm text-center">
          <div class="text-muted small">Cumul des Absences</div>
          <div class="fs-4 fw-bold text-danger"><?= $stats['absents'] ?></div>
        </div>
      </div>
    </div>

    <!-- Table de l'historique des appels -->
    <div class="card shadow-sm">
      <div class="card-header bg-light">
        <span class="fw-bold">Historique des registres de présence</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 1%;">#</th>
                <th>Date d'Appel</th>
                <th>Année Scolaire</th>
                <th class="text-center">Effectif Évalué</th>
                <th class="text-center">Présents</th>
                <th class="text-center">Absents</th>
                <th class="text-end">Taux de présence</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($appels)): ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">
                    Aucun appel n'a encore été enregistré pour cette classe.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($appels as $a): ?>
                  <?php 
                    $tot = (int)$a['total_eleves'];
                    $pres = (int)$a['nb_presents'];
                    $taux = $tot > 0 ? round(($pres / $tot) * 100) : 0;
                  ?>
                  <tr>
                    <td><?= (int)$a['id'] ?></td>
                    <td class="fw-bold"><?= date('d/m/Y', strtotime($a['date_appel'])) ?></td>
                    <td><?= e($a['anneeScolaire'] ?: '—') ?></td>
                    <td class="text-center"><?= $tot ?></td>
                    <td class="text-center">
                      <span class="badge bg-success"><?= $pres ?></span>
                    </td>
                    <td class="text-center">
                      <span class="badge bg-danger"><?= (int)$a['nb_absents'] ?></span>
                    </td>
                    <td class="text-end">
                      <span class="fw-bold text-<?= $taux >= 80 ? 'success' : ($taux >= 50 ? 'warning' : 'danger') ?>">
                        <?= $taux ?>%
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