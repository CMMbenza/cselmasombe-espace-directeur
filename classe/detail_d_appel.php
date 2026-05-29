<?php
// /directeur/classe/detail_d_appel.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // fournit $pdo, e(), BASE_URL + session + anti-cache
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$eleveId = (int)($_GET['eleve_id'] ?? 0);
if ($eleveId <= 0) {
    header('Location: ' . BASE_URL . '/classe/');
    exit;
}

// Filtres mois / année (par défaut : mois/année courants)
$mois  = (int)($_GET['mois']  ?? date('n'));
$annee = (int)($_GET['annee'] ?? date('Y'));

$error = '';
$eleve = null;
$classeId = null;
$statsGlobales = [
    'total'        => 0,
    'present'      => 0,
    'absent'       => 0,
    'taux_absence' => 0.0,
];
$historique = [];
$anneeScolaireEncours = null;

// Labels mois
$moisLabels = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
];

try {
    // Année scolaire encours (optionnel)
    $stmtAn = $pdo->query("
        SELECT annee_scolaire 
        FROM annee_scolaire 
        WHERE status = 'encours'
        ORDER BY id DESC
        LIMIT 1
    ");
    $anneeScolaireEncours = $stmtAn->fetchColumn() ?: null;

    // Infos élève + classe
    $sqlEleve = "
        SELECT 
            e.id, e.nom, e.postnom, e.prenom, e.genre, e.anneeScolaire,
            c.id AS classe_id, c.description AS classe_desc,
            cy.description AS cycle_desc
        FROM eleve e
        LEFT JOIN classe c ON c.id = e.classe
        LEFT JOIN cycle  cy ON cy.id = c.cycle
        WHERE e.id = :id
        LIMIT 1
    ";
    $stmtEleve = $pdo->prepare($sqlEleve);
    $stmtEleve->execute([':id' => $eleveId]);
    $eleve = $stmtEleve->fetch(PDO::FETCH_ASSOC);

    if (!$eleve) {
        throw new RuntimeException("Élève introuvable.");
    }

    $classeId = (int)($eleve['classe_id'] ?? 0);

    // Statistiques pour le mois sélectionné
    $paramsGlobal = [
        ':eleve_id' => $eleveId,
        ':mois'     => $mois,
        ':annee'    => $annee,
    ];
    $sqlGlobal = "
        SELECT 
            COUNT(ad.id) AS total,
            SUM(CASE WHEN ad.statut = 'present' THEN 1 ELSE 0 END) AS presents,
            SUM(CASE WHEN ad.statut = 'absent'  THEN 1 ELSE 0 END) AS absents
        FROM appel_detail ad
        JOIN appel a ON a.id = ad.appel_id
        WHERE ad.eleve_id = :eleve_id
          AND MONTH(a.date_appel) = :mois
          AND YEAR(a.date_appel)  = :annee
    ";
    if ($anneeScolaireEncours !== null) {
        $sqlGlobal .= " AND a.anneeScolaire = :anneeSco";
        $paramsGlobal[':anneeSco'] = $anneeScolaireEncours;
    }

    $stmtGlobal = $pdo->prepare($sqlGlobal);
    $stmtGlobal->execute($paramsGlobal);
    $g = $stmtGlobal->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'presents'=>0,'absents'=>0];

    $total    = (int)($g['total']    ?? 0);
    $presents = (int)($g['presents'] ?? 0);
    $absents  = (int)($g['absents']  ?? 0);
    $tauxAbs  = $total > 0 ? ($absents * 100.0 / $total) : 0.0;

    $statsGlobales = [
        'total'        => $total,
        'present'      => $presents,
        'absent'       => $absents,
        'taux_absence' => $tauxAbs,
    ];

    // Historique détaillé pour ce mois
    $paramsHist = [
        ':eleve_id' => $eleveId,
        ':mois'     => $mois,
        ':annee'    => $annee,
    ];
    $sqlHist = "
        SELECT 
            a.date_appel,
            a.anneeScolaire,
            c.description AS classe_desc,
            ad.statut,
            ad.remarque
        FROM appel_detail ad
        JOIN appel a ON a.id = ad.appel_id
        LEFT JOIN classe c ON c.id = a.classe_id
        WHERE ad.eleve_id = :eleve_id
          AND MONTH(a.date_appel) = :mois
          AND YEAR(a.date_appel)  = :annee
    ";
    if ($anneeScolaireEncours !== null) {
        $sqlHist .= " AND a.anneeScolaire = :anneeSco";
        $paramsHist[':anneeSco'] = $anneeScolaireEncours;
    }
    $sqlHist .= " ORDER BY a.date_appel DESC";

    $stmtHist = $pdo->prepare($sqlHist);
    $stmtHist->execute($paramsHist);
    $historique = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0">Détail d'appel — Élève (par mois)</h1>
      <?php if ($eleve): 
        $fullName = trim($eleve['nom'].' '.$eleve['postnom'].' '.$eleve['prenom']);
      ?>
        <div class="text-muted small">
          Élève : <strong><?= e($fullName) ?></strong>
          <?php if (!empty($eleve['genre'])): ?>
            — Genre : <strong><?= e($eleve['genre']) ?></strong>
          <?php endif; ?>
          <?php if (!empty($eleve['classe_desc'])): ?>
            — Classe : <strong><?= e($eleve['classe_desc']) ?></strong>
          <?php endif; ?>
          <?php if (!empty($eleve['cycle_desc'])): ?>
            — Cycle : <strong><?= e($eleve['cycle_desc']) ?></strong>
          <?php endif; ?>
          <?php if ($anneeScolaireEncours): ?>
            — Année scolaire : <strong><?= e($anneeScolaireEncours) ?></strong>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2">
      <?php if (!empty($classeId)): ?>
        <a href="<?= BASE_URL ?>/classe/registre_d_appel.php?classe_id=<?= (int)$classeId ?>"
           class="btn btn-sm btn-outline-secondary">
          ← Retour registre de la classe
        </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/classe/" class="btn btn-sm btn-outline-secondary">
        ← Retour aux classes
      </a>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if (!$error && $eleve): ?>
    <!-- Filtres mois / année -->
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
          <input type="hidden" name="eleve_id" value="<?= (int)$eleveId ?>">
          <div class="col-6 col-md-3">
            <label class="form-label">Mois</label>
            <select name="mois" class="form-select form-select-sm">
              <?php foreach ($moisLabels as $num => $label): ?>
                <option value="<?= $num ?>" <?= $mois === $num ? 'selected' : '' ?>>
                  <?= $label ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Année</label>
            <input type="number" name="annee" class="form-control form-control-sm"
                   value="<?= $annee ?>" min="2000" max="<?= date('Y')+1 ?>">
          </div>
          <div class="col-12 col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary mt-auto">Afficher</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Statistiques globales du mois -->
    <div class="row g-3 mb-3">
      <div class="col-12 col-md-3">
        <div class="card shadow-sm border-primary">
          <div class="card-body py-2">
            <div class="text-muted small mb-1">Séances d'appel (mois)</div>
            <div class="h5 mb-0"><?= (int)$statsGlobales['total'] ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm border-success">
          <div class="card-body py-2">
            <div class="text-muted small mb-1">Présences</div>
            <div class="h5 mb-0 text-success"><?= (int)$statsGlobales['present'] ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm border-danger">
          <div class="card-body py-2">
            <div class="text-muted small mb-1">Absences</div>
            <div class="h5 mb-0 text-danger"><?= (int)$statsGlobales['absent'] ?></div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-3">
        <div class="card shadow-sm border-warning">
          <div class="card-body py-2">
            <div class="text-muted small mb-1">Taux d'absence (mois)</div>
            <div class="h5 mb-0">
              <?= number_format($statsGlobales['taux_absence'], 1, ',', ' ') ?> %
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Historique détaillé du mois -->
    <div class="card shadow-sm mb-4">
      <div class="card-body table-responsive">
        <h2 class="h6 mb-3">
          Historique des appels — <?= e($moisLabels[$mois] ?? $mois) ?> <?= $annee ?>
        </h2>
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:1%;">#</th>
              <th>Date</th>
              <th>Année scol.</th>
              <th>Classe</th>
              <th>Statut</th>
              <th>Remarque</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$historique): ?>
              <tr>
                <td colspan="6">
                  <em>Aucun appel enregistré pour cet élève sur ce mois.</em>
                </td>
              </tr>
            <?php else: 
              $i = 0;
              foreach ($historique as $row):
                $i++;
                $date = $row['date_appel'];
                $stat = $row['statut'];
                $badgeClass = $stat === 'absent' ? 'bg-danger' : 'bg-success';
                $label = $stat === 'absent' ? 'Absent(e)' : 'Présent(e)';
            ?>
              <tr>
                <td><?= $i ?></td>
                <td><?= e($date) ?></td>
                <td><?= e((string)$row['anneeScolaire']) ?></td>
                <td><?= e($row['classe_desc'] ?? '') ?></td>
                <td>
                  <span class="badge <?= $badgeClass ?>"><?= $label ?></span>
                </td>
                <td><?= e((string)$row['remarque']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
