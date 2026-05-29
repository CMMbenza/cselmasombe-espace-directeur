<?php
// /directeur/paiements_divers/index.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

// Filtres
$q       = trim((string)($_GET['q'] ?? '')); // ménage / tel / obs / type_frais
$type    = trim((string)($_GET['type_frais'] ?? ''));
$annee   = trim((string)($_GET['annee_scolaire'] ?? ''));
$from    = trim((string)($_GET['from'] ?? ''));
$to      = trim((string)($_GET['to'] ?? ''));

$error = '';
$rows  = [];
$types = [];
$annees = [];

$totalAPayer = 0.0; $totalPaye = 0.0; $totalReste = 0.0;

try {
  // Types distincts
  $stmtT = $pdo->query("SELECT DISTINCT type_frais FROM paiement_divers WHERE type_frais <> '' ORDER BY type_frais");
  $types = $stmtT->fetchAll(PDO::FETCH_COLUMN);

  // Années distinctes
  $stmtA = $pdo->query("SELECT DISTINCT anneeScolaire FROM paiement_divers WHERE anneeScolaire IS NOT NULL AND anneeScolaire<>'' ORDER BY anneeScolaire DESC");
  $annees = $stmtA->fetchAll(PDO::FETCH_COLUMN);

  $wheres = [];
  $params = [];

  if ($q !== '') {
    $wheres[] = "(m.noms LIKE :like OR m.telephone LIKE :like OR d.observation LIKE :like OR d.type_frais LIKE :like)";
    $params[':like'] = "%{$q}%";
  }
  if ($type !== '') {
    $wheres[] = "d.type_frais = :type";
    $params[':type'] = $type;
  }
  if ($annee !== '') {
    $wheres[] = "d.anneeScolaire = :annee";
    $params[':annee'] = $annee;
  }
  if ($from !== '') {
    $wheres[] = "d.dateCreated >= :from";
    $params[':from'] = $from;
  }
  if ($to !== '') {
    $wheres[] = "d.dateCreated <= :to";
    $params[':to'] = $to;
  }

  $whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

  $sql = "
    SELECT
      d.id,
      d.type_frais,
      d.montantAPayer,
      d.montantPayer,
      d.resteAPayer,
      d.observation,
      d.dateCreated,
      d.anneeScolaire,
      m.id AS menage_id,
      m.noms AS menage_nom,
      m.telephone AS menage_tel
    FROM paiement_divers d
    JOIN menage m ON m.id = d.menage
    $whereSql
    ORDER BY d.dateCreated DESC, d.id DESC
    LIMIT 1000
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $r) {
    $totalAPayer += (float)$r['montantAPayer'];
    $totalPaye   += (float)$r['montantPayer'];
    $totalReste  += (float)$r['resteAPayer'];
  }

} catch (Throwable $e) {
  $error = "Impossible de charger les frais connexes.";
}
?>
<div class="container">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Frais connexes</h1>

    <form class="row g-2" method="get">
      <div class="col-12 col-md-3">
        <input class="form-control form-control-sm" type="search" name="q" placeholder="Ménage / Téléphone / Type / Observation" value="<?= e($q) ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="type_frais" class="form-select form-select-sm">
          <option value="">Tous types</option>
          <?php foreach ($types as $t): ?>
            <option value="<?= e($t) ?>" <?= $type===$t?'selected':'' ?>><?= e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="annee_scolaire" class="form-select form-select-sm">
          <option value="">Toutes années</option>
          <?php foreach ($annees as $as): ?>
            <option value="<?= e($as) ?>" <?= $annee===$as?'selected':'' ?>><?= e($as) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <input class="form-control form-control-sm" type="date" name="from" value="<?= e($from) ?>" placeholder="Du">
      </div>
      <div class="col-6 col-md-2">
        <input class="form-control form-control-sm" type="date" name="to" value="<?= e($to) ?>" placeholder="Au">
      </div>
      <div class="col-12 col-md-auto d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary">OK</button>
        <?php if ($q!=='' || $type!=='' || $annee!=='' || $from!=='' || $to!==''): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/paiements_divers/index.php">Réinitialiser</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <div class="border rounded p-2 bg-light">
            <div class="small text-muted">Total à payer</div>
            <div class="fw-semibold"><?= number_format($totalAPayer, 2, ',', ' ') ?></div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="border rounded p-2 bg-light">
            <div class="small text-muted">Total payé</div>
            <div class="fw-semibold text-success"><?= number_format($totalPaye, 2, ',', ' ') ?></div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="border rounded p-2 bg-light">
            <div class="small text-muted">Total reste</div>
            <div class="fw-semibold text-danger"><?= number_format($totalReste, 2, ',', ' ') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th style="width:1%;">#</th>
            <th>Ménage</th>
            <th>Téléphone</th>
            <th>Type</th>
            <th>Année</th>
            <th>Date</th>
            <th class="text-end">À payer</th>
            <th class="text-end">Payé</th>
            <th class="text-end">Reste</th>
            <th>Observation</th>
            <th style="width:1%;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="11"><em>Aucun frais connexe trouvé.</em></td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= e($r['menage_nom']) ?></td>
            <td><?= e($r['menage_tel'] ?? '—') ?></td>
            <td><?= e($r['type_frais']) ?></td>
            <td><?= e($r['anneeScolaire'] ?? '—') ?></td>
            <td><?= e($r['dateCreated']) ?></td>
            <td class="text-end"><?= number_format((float)$r['montantAPayer'], 2, ',', ' ') ?></td>
            <td class="text-end"><?= number_format((float)$r['montantPayer'], 2, ',', ' ') ?></td>
            <td class="text-end"><?= number_format((float)$r['resteAPayer'], 2, ',', ' ') ?></td>
            <td><?= nl2br(e($r['observation'] ?? '')) ?></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/menages/show.php?id=<?= (int)$r['menage_id'] ?>">Voir ménage</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
      <div class="text-muted small">Total : <?= count($rows) ?> ligne(s).</div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
