<?php
// /directeur/menages/index.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // session + anti-cache + $pdo + helpers (e, BASE_URL)
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

// Vérification du rôle Directeur
$userRole    = (string)($_SESSION['user']['role'] ?? '');
$isDirecteur = (strtolower(trim($userRole)) === 'directeur');

$q        = trim((string)($_GET['q'] ?? ''));
$commune  = trim((string)($_GET['commune'] ?? ''));
$error    = '';
$rows     = [];
$enfantsG = []; // enfants groupés par menage_id
$payG     = []; // agrégats paiements groupés par menage_id
$communes = [];

try {
  // Liste distincte des communes pour filtre
  $stmtCom = $pdo->query("SELECT DISTINCT TRIM(commune) AS commune FROM menage WHERE TRIM(commune)<>'' ORDER BY commune");
  $communes = $stmtCom->fetchAll(PDO::FETCH_COLUMN);

  // Ménages filtrés côté serveur
  $wheres = [];
  $params = [];
  if ($q !== '') {
    $wheres[] = "(m.noms LIKE :like OR m.telephone LIKE :like OR m.quartier LIKE :like OR m.avenue LIKE :like OR m.numero LIKE :like)";
    $params[':like'] = "%{$q}%";
  }
  if ($commune !== '') {
    $wheres[] = "m.commune = :commune";
    $params[':commune'] = $commune;
  }
  $whereSql = $wheres ? ('WHERE '.implode(' AND ', $wheres)) : '';

  $sqlMen = "
    SELECT 
      m.id,
      m.noms,
      m.telephone,
      m.numero,
      m.avenue,
      m.quartier,
      m.commune,
      m.dateCreated,
      m.montantAPayer
    FROM menage m
    $whereSql
    ORDER BY m.noms
    LIMIT 1000
  ";
  $stmtMen = $pdo->prepare($sqlMen);
  $stmtMen->execute($params);
  $rows = $stmtMen->fetchAll(PDO::FETCH_ASSOC);

  // Charger tous les enfants des ménages affichés
  if ($rows) {
    $ids = array_map(fn($r) => (int)$r['id'], $rows);

    // Enfants (1 requête IN)
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $sqlEnf = "
      SELECT 
        e.id,
        e.nom, e.postnom, e.prenom, e.genre,
        e.menage,
        e.classe,
        e.anneeScolaire,
        c.description AS classe_desc,
        cy.description AS cycle_desc
      FROM eleve e
      JOIN classe c ON c.id = e.classe
      LEFT JOIN cycle  cy ON cy.id = c.cycle
      WHERE e.menage IN ($in)
      ORDER BY e.menage, e.nom, e.postnom, e.prenom
    ";
    $stmtEnf = $pdo->prepare($sqlEnf);
    $stmtEnf->execute($ids);
    while ($e = $stmtEnf->fetch(PDO::FETCH_ASSOC)) {
      $mid = (int)$e['menage'];
      $enfantsG[$mid][] = $e;
    }

    // Uniquement charger les paiements si c'est un directeur
    if ($isDirecteur) {
      $sqlPay = "
        SELECT p.menage,
               SUM(p.montantAPayer) AS total_a_payer,
               SUM(p.montantPayer)  AS total_paye,
               SUM(p.resteAPayer)   AS total_reste_col
        FROM paiement p
        WHERE p.menage IN ($in)
        GROUP BY p.menage
      ";
      $stmtPay = $pdo->prepare($sqlPay);
      $stmtPay->execute($ids);
      while ($p = $stmtPay->fetch(PDO::FETCH_ASSOC)) {
        $mid = (int)$p['menage'];
        $total_a_payer = (float)($p['total_a_payer'] ?? 0);
        $total_paye    = (float)($p['total_paye'] ?? 0);
        $reste_calc    = max(0.0, $total_a_payer - $total_paye);
        $payG[$mid] = [
          'a_payer' => $total_a_payer,
          'paye'    => $total_paye,
          'reste'   => $reste_calc,
        ];
      }
    }
  }

} catch (Throwable $e) {
  $error = "Impossible de charger les ménages.";
}
?>
<div class="container">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Ménages</h1>

    <!-- Recherche serveur -->
    <form class="row g-2" method="get">
      <div class="col-12 col-md-5">
        <input class="form-control form-control-sm" type="search" name="q"
               placeholder="Recherche serveur (noms, téléphone, adresse)"
               value="<?= e($q) ?>">
      </div>
      <div class="col-12 col-md-4">
        <select name="commune" class="form-select form-select-sm">
          <option value="">Toutes les communes</option>
          <?php foreach ($communes as $c): ?>
            <option value="<?= e($c) ?>" <?= $commune===$c?'selected':'' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-auto d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary">OK</button>
        <?php if ($q!=='' || $commune!==''): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/menages/index.php">Réinitialiser</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Recherche instantanée client -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <label class="form-label mb-1">Recherche instantanée (Nom ménage)</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text">🔎</span>
        <input id="filterName" type="text" class="form-control" placeholder="Tapez un nom…">
        <button id="btnClearFilter" class="btn btn-outline-secondary" type="button">Effacer</button>
      </div>
      <div class="form-text">Filtre côté navigateur, sans recharger la page.</div>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-sm align-middle" id="menagesTable">
        <thead>
          <tr>
            <th style="width:1%;">#</th>
            <th>Ménage</th>
            <th>Téléphone</th>
            <th>Adresse</th>
            <?php if ($isDirecteur): ?>
              <th>Montant à payer (ménage)</th>
            <?php endif; ?>
            <th>Enfants</th>
            <th style="width:1%;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr class="no-data"><td colspan="<?= $isDirecteur ? '7' : '6' ?>"><em>Aucun ménage trouvé.</em></td></tr>
        <?php else: foreach ($rows as $r):
          $mid       = (int)$r['id'];
          $kids      = $enfantsG[$mid] ?? [];
          $nbKids    = count($kids);
          $addr      = implode(' ', array_filter([
                        'N°'.($r['numero'] ?: ''),
                        $r['avenue'] ?: '',
                      ]));
          $addr2     = implode(' - ', array_filter([
                        $r['quartier'] ?: '',
                        $r['commune']  ?: '',
                      ]));
          $collapseId = 'kids-'.$mid;

          $nameLower = mb_strtolower((string)$r['noms'], 'UTF-8');

          if ($isDirecteur) {
            $agg = $payG[$mid] ?? ['a_payer'=>0.0, 'paye'=>0.0, 'reste'=>0.0];
            $a_payer = (float)$agg['a_payer'];
            $paye    = (float)$agg['paye'];
            $reste   = (float)$agg['reste'];
            $progress = ($a_payer > 0) ? min(100, max(0, round($paye * 100 / $a_payer))) : 0;
          }
        ?>
          <tr data-row="menage" data-name="<?= e($nameLower) ?>">
            <td><?= $mid ?></td>
            <td class="fw-semibold"><?= e($r['noms']) ?></td>
            <td><?= e($r['telephone']) ?></td>
            <td>
              <div><?= e(trim($addr)) ?: '—' ?></div>
              <div class="text-muted small"><?= e($addr2) ?: '' ?></div>
            </td>
            <?php if ($isDirecteur): ?>
              <td><?= e(number_format((float)$r['montantAPayer'], 2, ',', ' ')) ?></td>
            <?php endif; ?>
            <td><span class="badge text-bg-secondary"><?= $nbKids ?></span></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/menages/show.php?id=<?= (int)$mid ?>">
                Voir
              </a>
            </td>
          </tr>

          <tr class="collapse" id="<?= $collapseId ?>" data-row="children" data-parent="<?= $mid ?>">
            <td colspan="<?= $isDirecteur ? '7' : '6' ?>">
              
              <?php if ($isDirecteur): ?>
              <!-- Modalité de paiement (Frais scolaire) - Réservé aux directeurs -->
              <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                  <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <h6 class="mb-2">Modalité de paiement — Frais scolaire</h6>
                    <div class="small text-muted">Ménage #<?= $mid ?></div>
                  </div>
                  <div class="row g-3">
                    <div class="col-12 col-md-4">
                      <div class="border rounded p-2 bg-light">
                        <div class="small text-muted">Total à payer (agrégé paiements)</div>
                        <div class="fw-semibold"><?= number_format($a_payer, 2, ',', ' ') ?></div>
                      </div>
                    </div>
                    <div class="col-12 col-md-4">
                      <div class="border rounded p-2 bg-light">
                        <div class="small text-muted">Total payé</div>
                        <div class="fw-semibold text-success"><?= number_format($paye, 2, ',', ' ') ?></div>
                      </div>
                    </div>
                    <div class="col-12 col-md-4">
                      <div class="border rounded p-2 bg-light">
                        <div class="small text-muted">Reste</div>
                        <div class="fw-semibold text-danger"><?= number_format($reste, 2, ',', ' ') ?></div>
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="progress" role="progressbar" aria-label="Progression paiement" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?= $progress ?>%"><?= $progress ?>%</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php endif; ?>

              <!-- Enfants -->
              <?php if (!$kids): ?>
                <div class="text-muted">Aucun enfant enregistré pour ce ménage.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr class="table-light">
                        <th style="width:1%;">#</th>
                        <th>Élève</th>
                        <th>Genre</th>
                        <th>Classe</th>
                        <th>Cycle</th>
                        <th>Année scolaire</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($kids as $k): ?>
                        <tr>
                          <td><?= (int)$k['id'] ?></td>
                          <td><?= e($k['nom'].' '.$k['postnom'].' '.$k['prenom']) ?></td>
                          <td><?= e($k['genre']) ?></td>
                          <td><?= e($k['classe_desc']) ?></td>
                          <td><?= e($k['cycle_desc'] ?? '—') ?></td>
                          <td><?= e($k['anneeScolaire']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>

      <div id="clientNoMatch" class="alert alert-info py-2 d-none mb-0">
        Aucun ménage ne correspond à votre filtre.
      </div>

      <div class="text-muted small mt-2">Total : <span id="totalCount"><?= count($rows) ?></span> ménage(s).</div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script>
(function(){
  const input = document.getElementById('filterName');
  const btnClear = document.getElementById('btnClearFilter');
  const table = document.getElementById('menagesTable');
  const noMatch = document.getElementById('clientNoMatch');

  function normalize(str){
    return (str || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
  }

  function applyFilters(){
    const q = normalize((input?.value || '').trim().toLowerCase());
    const rows = table.querySelectorAll('tbody tr[data-row="menage"]');
    let anyVisible = false;

    rows.forEach(tr => {
      const name = tr.getAttribute('data-name') || '';
      const id = tr.querySelector('td')?.textContent.trim() || '';
      const mid = parseInt(id, 10) || 0;

      const matchName = q === '' ? true : normalize(name).includes(q);
      const show = matchName;

      tr.style.display = show ? '' : 'none';

      const child = document.querySelector(`tr[data-row="children"][data-parent="${mid}"]`);
      if (child) {
        if (!show) {
          const collapseEl = document.getElementById('kids-' + mid);
          if (collapseEl && collapseEl.classList.contains('show')) {
            try {
              const c = bootstrap.Collapse.getOrCreateInstance(collapseEl);
              c.hide();
            } catch(e){}
          }
          child.style.display = 'none';
        } else {
          child.style.display = '';
        }
      }

      if (show) anyVisible = true;
    });

    if (noMatch) noMatch.classList.toggle('d-none', anyVisible);
  }

  input?.addEventListener('input', applyFilters);
  btnClear?.addEventListener('click', () => {
    input.value = '';
    applyFilters();
    input.focus();
  });

  applyFilters();
})();
</script>