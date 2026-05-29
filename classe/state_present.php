<?php
// /directeur/classe/state_present.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // fournit $pdo, e(), BASE_URL + session + anti-cache
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$error    = '';
$rows     = [];
$classes  = [];
$anneeEncours = null;

// ====== Filtres ======

// Filtre jour (par défaut)
$dateJour = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateJour)) {
    $dateJour = date('Y-m-d');
}

// Filtre période (deux dates)
$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$fromValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from);
$toValid   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);
if ($fromValid && $toValid && $from > $to) {
    // si l'utilisateur inverse les dates, on swap
    [$from, $to] = [$to, $from];
}

// Filtre mois
$mois  = isset($_GET['mois'])  ? (int)$_GET['mois']  : 0;
$annee = isset($_GET['annee']) ? (int)$_GET['annee'] : 0;
if ($mois < 1 || $mois > 12)   $mois = 0;
if ($annee < 2000 || $annee > (int)date('Y')+1) $annee = 0;

// Filtre classe & statut
$classeId     = (int)($_GET['classe_id'] ?? 0);
$statutFiltre = trim((string)($_GET['statut'] ?? '')); // '', 'present', 'absent'

// Détermination du mode de filtre sur la période
// Priorité : période (from/to) > mois/année > jour
$mode = 'jour'; // 'jour' | 'periode' | 'mois'
if ($fromValid && $toValid) {
    $mode = 'periode';
} elseif ($mois > 0 && $annee > 0) {
    $mode = 'mois';
}

// Labels mois pour affichage
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
        WHERE status='encours'
        ORDER BY id DESC
        LIMIT 1
    ");
    $anneeEncours = $stmtAn->fetchColumn() ?: null;

    // Liste des classes pour filtre
    $sqlClasses = "
        SELECT c.id, c.description AS classe, cy.description AS cycle
        FROM classe c
        LEFT JOIN cycle cy ON cy.id = c.cycle
        ORDER BY c.description
    ";
    $classes = $pdo->query($sqlClasses)->fetchAll(PDO::FETCH_ASSOC);

    // ====== Construction de la requête principale ======
    // On agrège les présences/absences pour chaque élève sur la période choisie.

    $wheresEleve = [];
    $params = [];

    if ($classeId > 0) {
        $wheresEleve[] = "e.classe = :classe_id";
        $params[':classe_id'] = $classeId;
    }
    if ($anneeEncours !== null) {
        $wheresEleve[] = "e.anneeScolaire = :anneeSco";
        $params[':anneeSco'] = $anneeEncours;
    }
    $whereEleveSql = $wheresEleve ? ('WHERE '.implode(' AND ', $wheresEleve)) : '';

    // Conditions de période sur a.date_appel (dans le JOIN sur appel)
    $condDateSql = "";
    if ($mode === 'jour') {
        $condDateSql = "AND a.date_appel = :date_appel";
        $params[':date_appel'] = $dateJour;
    } elseif ($mode === 'periode') {
        $condDateSql = "AND a.date_appel BETWEEN :from AND :to";
        $params[':from'] = $from;
        $params[':to']   = $to;
    } elseif ($mode === 'mois') {
        $condDateSql = "AND YEAR(a.date_appel) = :annee AND MONTH(a.date_appel) = :mois";
        $params[':annee'] = $annee;
        $params[':mois']  = $mois;
    }

    // Année scolaire sur appel (optionnel : on centre la période dans l'année encours)
    $condAnScoAppel = "";
    if ($anneeEncours !== null) {
        $condAnScoAppel = "AND a.anneeScolaire = :anneeScoAppel";
        $params[':anneeScoAppel'] = $anneeEncours;
    }

    $sql = "
        SELECT 
            e.id AS eleve_id,
            e.nom, e.postnom, e.prenom,
            e.genre,
            e.anneeScolaire,
            c.description AS classe_desc,
            cy.description AS cycle_desc,
            COUNT(ad.id) AS total_appels,
            SUM(CASE WHEN ad.statut = 'present' THEN 1 ELSE 0 END) AS nb_presents,
            SUM(CASE WHEN ad.statut = 'absent'  THEN 1 ELSE 0 END) AS nb_absents
        FROM eleve e
        LEFT JOIN classe c ON c.id = e.classe
        LEFT JOIN cycle  cy ON cy.id = c.cycle
        LEFT JOIN appel a 
          ON a.classe_id = c.id
         $condDateSql
         $condAnScoAppel
        LEFT JOIN appel_detail ad
          ON ad.appel_id = a.id
         AND ad.eleve_id = e.id
        $whereEleveSql
        GROUP BY 
            e.id, e.nom, e.postnom, e.prenom,
            e.genre, e.anneeScolaire,
            c.description, cy.description
        ORDER BY c.description, e.nom, e.postnom, e.prenom
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Application du filtre de statut (present/absent) sur la période agrégée
    foreach ($result as $r) {
        $total      = (int)($r['total_appels'] ?? 0);
        $nbAbs      = (int)($r['nb_absents']   ?? 0);
        $nbPres     = (int)($r['nb_presents']  ?? 0);

        // Détermination du statut "résumé"
        if ($total === 0) {
            $statutResume = '';
        } elseif ($nbAbs > 0) {
            $statutResume = 'absent';
        } else {
            $statutResume = 'present';
        }

        // Application du filtre
        if ($statutFiltre === 'present' && $statutResume !== 'present') {
            continue;
        }
        if ($statutFiltre === 'absent' && $statutResume !== 'absent') {
            continue;
        }

        $r['statut_resume'] = $statutResume;
        $rows[] = $r;
    }

} catch (Throwable $e) {
    $error = "Impossible de charger l'état de présence : ".$e->getMessage();
}
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0">État de présence — Tous les élèves</h1>
      <div class="text-muted small">
        <?php if ($mode === 'jour'): ?>
          Période : <strong>Jour du <?= e($dateJour) ?></strong>
        <?php elseif ($mode === 'periode'): ?>
          Période : <strong>du <?= e($from) ?> au <?= e($to) ?></strong>
        <?php elseif ($mode === 'mois'): ?>
          Période : <strong><?= e($moisLabels[$mois] ?? $mois) ?> <?= e((string)$annee) ?></strong>
        <?php endif; ?>
        <?php if ($anneeEncours): ?>
          — Année scolaire : <strong><?= e($anneeEncours) ?></strong>
        <?php endif; ?>
      </div>
    </div>
    <div>
      <a href="<?= BASE_URL ?>/classe/" class="btn btn-sm btn-outline-secondary">
        ← Retour aux classes
      </a>
    </div>
  </div>

  <!-- Filtres -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <!-- Filtre par jour -->
        <div class="col-12 col-md-3">
          <label class="form-label">Filtre par jour</label>
          <input type="date" name="date" class="form-control form-control-sm"
                 value="<?= e($dateJour) ?>">
          <div class="d-none form-text">Utilisé si aucune période ou mois n'est défini.</div>
        </div>

        <!-- Filtre par période (from/to) -->
        <div class="col-6 col-md-3">
          <label class="form-label">Du</label>
          <input type="date" name="from" class="form-control form-control-sm"
                 value="<?= e($from) ?>">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label">Au</label>
          <input type="date" name="to" class="form-control form-control-sm"
                 value="<?= e($to) ?>">
        </div>

        <!-- Filtre par mois -->
        <div class="col-6 col-md-2">
          <label class="form-label">Mois</label>
          <select name="mois" class="form-select form-select-sm">
            <option value="0">--</option>
            <?php foreach ($moisLabels as $num => $label): ?>
              <option value="<?= $num ?>" <?= $mois === $num ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label">Année</label>
          <input type="number" name="annee" class="form-control form-control-sm"
                 value="<?= $annee ?: date('Y') ?>" min="2000" max="<?= date('Y')+1 ?>">
        </div>

        <!-- Filtre classe -->
        <div class="col-12 col-md-3">
          <label class="form-label">Classe</label>
          <select name="classe_id" class="form-select form-select-sm">
            <option value="0">Toutes les classes</option>
            <?php foreach ($classes as $cl): ?>
              <option value="<?= (int)$cl['id'] ?>" <?= $classeId===(int)$cl['id']?'selected':'' ?>>
                <?= e($cl['classe']) ?><?= $cl['cycle'] ? ' — '.e($cl['cycle']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Filtre statut -->
        <div class="col-12 col-md-3">
          <label class="form-label">Statut (résumé sur la période)</label>
          <select name="statut" class="form-select form-select-sm">
            <option value="" <?= $statutFiltre==='' ? 'selected':'' ?>>Tous</option>
            <option value="present" <?= $statutFiltre==='present' ? 'selected':'' ?>>Présent(e) (aucune absence)</option>
            <option value="absent"  <?= $statutFiltre==='absent'  ? 'selected':'' ?>>Absent(e) au moins une fois</option>
          </select>
        </div>

        <div class="col-12 col-md-3 d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary mt-auto">Filtrer</button>
          <a href="<?= BASE_URL ?>/classe/state_present.php" class="btn btn-sm btn-outline-secondary mt-auto">
            Réinitialiser
          </a>
        </div>
      </form>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- Tableau -->
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-sm align-middle table-striped">
        <thead class="table-light">
          <tr>
            <th style="width:1%;">#</th>
            <th>Élève</th>
            <th>Genre</th>
            <th>Classe</th>
            <th>Cycle</th>
            <th>Année scol.</th>
            <th class="text-end">Séances</th>
            <th class="text-end">Présences</th>
            <th class="text-end">Absences</th>
            <th>Statut (résumé)</th>
            <th style="width:1%;">Détail</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="11"><em>Aucune donnée à afficher pour ces filtres.</em></td>
            </tr>
          <?php else: 
            $i = 0;
            foreach ($rows as $r):
              $i++;
              $fullName = trim($r['nom'].' '.$r['postnom'].' '.$r['prenom']);
              $total   = (int)($r['total_appels'] ?? 0);
              $nbPres  = (int)($r['nb_presents']  ?? 0);
              $nbAbs   = (int)($r['nb_absents']   ?? 0);
              $statRes = $r['statut_resume'] ?? '';

              $label  = 'Non saisi';
              $badgeClass = 'bg-secondary';
              if ($statRes === 'present' && $total > 0) {
                  $label = 'Présent(e) (aucune absence)';
                  $badgeClass = 'bg-success';
              } elseif ($statRes === 'absent' && $total > 0) {
                  $label = 'Absent(e) ≥ 1 fois';
                  $badgeClass = 'bg-danger';
              }
          ?>
            <tr>
              <td><?= $i ?></td>
              <td><?= e($fullName) ?></td>
              <td><?= e($r['genre'] ?? '') ?></td>
              <td><?= e($r['classe_desc'] ?? '') ?></td>
              <td><?= e($r['cycle_desc'] ?? '') ?></td>
              <td><?= e($r['anneeScolaire'] ?? '') ?></td>
              <td class="text-end"><?= $total ?></td>
              <td class="text-end text-success"><?= $nbPres ?></td>
              <td class="text-end text-danger"><?= $nbAbs ?></td>
              <td>
                <span class="badge <?= $badgeClass ?>"><?= $label ?></span>
              </td>
              <td class="text-nowrap">
                <a href="<?= BASE_URL ?>/classe/detail_d_appel.php?eleve_id=<?= (int)$r['eleve_id'] ?>"
                   class="btn btn-sm btn-outline-secondary">
                  Détail
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <div class="text-muted small mt-2">
        Total : <strong><?= count($rows) ?></strong> élève(s) listé(s) pour cette période.
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
