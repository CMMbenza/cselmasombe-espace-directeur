<?php
// /directeur/classe/index.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // fournit $pdo, e(), BASE_URL + session + anti-cache
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

// Filtres (serveur)
$classe  = (int)($_GET['classe_id'] ?? 0);
$cycleId = (int)($_GET['cycle_id'] ?? 0);
$annee   = trim((string)($_GET['annee_scolaire'] ?? ''));

$error   = '';
$rows    = [];
$classes = [];
$cycles  = [];
$annees  = [];

try {
    // Cycles pour les filtres
    $cycles = $pdo->query("SELECT id, description FROM cycle ORDER BY description")->fetchAll(PDO::FETCH_ASSOC);

    // Liste des classes — TRI UNIQUEMENT PAR CLASSE
    $sqlClasses = "
        SELECT 
          c.id,
          c.description AS classe,
          cy.description AS cycle
        FROM classe c
        LEFT JOIN cycle cy ON cy.id = c.cycle
        ORDER BY c.description
    ";
    $classes = $pdo->query($sqlClasses)->fetchAll(PDO::FETCH_ASSOC);

    // Années scolaires
    $stmtAnnees = $pdo->query("
        SELECT DISTINCT anneeScolaire 
        FROM eleve 
        WHERE anneeScolaire<>'' 
        ORDER BY anneeScolaire DESC
    ");
    $annees = $stmtAnnees->fetchAll(PDO::FETCH_COLUMN);

    // WHERE dynamique
    $wheres = [];
    $params = [];

    if ($classe > 0) {
        $wheres[] = "c.id = :classe_id";
        $params[':classe_id'] = $classe;
    }
    if ($cycleId > 0) {
        $wheres[] = "c.cycle = :cycle_id";
        $params[':cycle_id'] = $cycleId;
    }
    if ($annee !== '') {
        $wheres[] = "e.anneeScolaire = :annee";
        $params[':annee'] = $annee;
    }

    $whereSql = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';

    // Requête principale : TRI UNIQUEMENT PAR CLASSE
    $sql = "
        SELECT 
            c.id AS classe_id,
            c.description AS classe_desc,
            cy.id AS cycle_id,
            cy.description AS cycle_desc,
            COUNT(e.id) AS nb_eleves
        FROM classe c
        LEFT JOIN cycle cy ON cy.id = c.cycle
        LEFT JOIN eleve e ON e.classe = c.id
        $whereSql
        GROUP BY c.id, c.description, cy.id, cy.description
        ORDER BY c.description
        LIMIT 1000
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error = "Impossible de charger la liste des classes.";
}
?>
<div class="container">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Classes & effectifs</h1>

    <!-- Filtres -->
    <form class="row g-2" method="get" role="search">

      <div class="col-12 col-md-3">
        <label class="form-label form-label-sm mb-1">Classe</label>
        <select name="classe_id" class="form-select form-select-sm">
          <option value="0">Toutes les classes</option>
          <?php foreach ($classes as $cl): ?>
            <option value="<?= (int)$cl['id'] ?>" <?= $classe === (int)$cl['id'] ? 'selected' : '' ?>>
              <?= e($cl['classe']) ?><?= $cl['cycle'] ? ' — '.e($cl['cycle']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-3">
        <label class="form-label form-label-sm mb-1">Cycle</label>
        <select name="cycle_id" class="form-select form-select-sm">
          <option value="0">Tous cycles</option>
          <?php foreach ($cycles as $cy): ?>
            <option value="<?= (int)$cy['id'] ?>" <?= $cycleId === (int)$cy['id'] ? 'selected' : '' ?>>
              <?= e($cy['description']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-3">
        <label class="form-label form-label-sm mb-1">Année scolaire</label>
        <select name="annee_scolaire" class="form-select form-select-sm">
          <option value="">Toutes années</option>
          <?php foreach ($annees as $as): ?>
            <option value="<?= e($as) ?>" <?= $annee === $as ? 'selected' : '' ?>><?= e($as) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-auto d-flex align-items-end gap-2">
        <button class="btn btn-sm btn-outline-secondary">Valider</button>
        <?php if ($classe > 0 || $cycleId > 0 || $annee !== ''): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/classe/">Réinitialiser</a>
        <?php endif; ?>
      </div>

    </form>
  </div>

  <!-- Recherche instantanée -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <label class="form-label mb-1">Recherche instantanée</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text">🔎</span>
        <input id="filterName" type="text" class="form-control" placeholder="Filtrer classes / cycles…">
        <button id="btnClearFilter" class="btn btn-outline-secondary" type="button">Effacer</button>
      </div>
      <a href="state_present.php" class="btn btn-danger btn-md mt-3">Voir le statistique général de A/P</a>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">

      <table class="table table-md align-middle table-hover" id="elevesTable">
        <thead class="table-light">
          <tr>
            <!--<th>#</th>-->
            <th>Classe</th>
            <th>Cycle</th>
            <th class="text-end">Élèves</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>

          <?php if (!$rows): ?>
            <tr><td colspan="5"><em>Aucune classe trouvée.</em></td></tr>
          <?php else: 
            $totalEleves = 0;

            foreach ($rows as $r):
              $nb = (int)$r['nb_eleves'];
              $totalEleves += $nb;

              $dataName = mb_strtolower(trim($r['classe_desc']." ".$r['cycle_desc']), 'UTF-8');
          ?>

          <tr data-name="<?= e($dataName) ?>">
            <td class="d-none text-muted"><?= $r['classe_id'] ?></td>

            <td><strong><?= e($r['classe_desc']) ?></strong></td>

            <td>
              <?= $r['cycle_desc'] 
                ? '<span class="badge bg-light text-muted border">'.e($r['cycle_desc']).'</span>' 
                : '<span class="text-muted">—</span>' ?>
            </td>

            <td class="text-end">
              <span class="badge bg-primary"><?= $nb ?></span>
            </td>

            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary"
                 href="<?= BASE_URL ?>/classe/registre_d_appel.php?classe_id=<?= (int)$r['classe_id'] ?>">
                Registre d'appel
              </a>
            </td>
          </tr>

          <?php endforeach; endif; ?>

        </tbody>
      </table>

      <div id="clientNoMatch" class="alert alert-info py-2 d-none mb-0">
        Aucune classe ne correspond au filtre.
      </div>

      <div class="d-flex justify-content-between mt-2 text-muted small">
        <div>Total : <span id="totalCount"><?= count($rows) ?></span> classes</div>
        <div>Effectif global : <strong><?= $totalEleves ?></strong></div>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script>
// Recherche instantanée côté client
(function(){
  const input = document.getElementById('filterName');
  const btnClear = document.getElementById('btnClearFilter');
  const rows = document.querySelectorAll('#elevesTable tbody tr[data-name]');
  const noMatch = document.getElementById('clientNoMatch');
  const totalCount = document.getElementById('totalCount');

  function normalize(s){
    return s.toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  }

  function applyFilter(){
    const q = normalize(input.value.trim());
    let visible = 0;

    rows.forEach(tr=>{
      const name = normalize(tr.dataset.name || '');
      const show = (q === '' || name.includes(q));
      tr.style.display = show ? '' : 'none';
      if(show) visible++;
    });

    totalCount.textContent = visible;
    noMatch.classList.toggle('d-none', visible > 0);
  }

  input.addEventListener('input', applyFilter);
  btnClear.addEventListener('click', ()=>{
    input.value = '';
    applyFilter();
    input.focus();
  });

  applyFilter();
})();
</script>
