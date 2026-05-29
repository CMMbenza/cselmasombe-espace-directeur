<?php
// /directeur/menages/index.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // fournit $pdo, e(), BASE_URL + session + anti-cache
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

// Filtres (serveur)
$classe  = (int)($_GET['classe_id'] ?? 0);                 // filtre classe
$cycleId = (int)($_GET['cycle_id'] ?? 0);                  // filtre cycle
$annee   = trim((string)($_GET['annee_scolaire'] ?? ''));  // filtre année scolaire

$error = '';
$rows  = [];
$classes = [];
$cycles  = [];
$annees  = [];

try {
  // Listes pour filtres
  $cycles = $pdo->query("SELECT id, description FROM cycle ORDER BY description")->fetchAll(PDO::FETCH_ASSOC);

  $sqlClasses = "
    SELECT c.id, c.description AS classe, cy.description AS cycle
    FROM classe c
    LEFT JOIN cycle cy ON cy.id = c.cycle
    ORDER BY cy.description, c.description
  ";
  $classes = $pdo->query($sqlClasses)->fetchAll(PDO::FETCH_ASSOC);

  // Années scolaires distinctes à partir de la table eleve
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
    $wheres[] = "e.classe = :classe_id";
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

  $whereSql = $wheres ? ('WHERE '.implode(' AND ', $wheres)) : '';

  // Requête principale
  $sql = "
    SELECT 
      e.id,
      e.nom, e.postnom, e.prenom, e.genre,
      e.anneeScolaire,
      m.id AS menage_id, m.noms AS menage_nom, m.telephone AS menage_tel,
      c.id AS classe_id, c.description AS classe_desc,
      cy.id AS cycle_id, cy.description AS cycle_desc
    FROM eleve e
    JOIN classe c ON c.id = e.classe
    LEFT JOIN cycle cy ON cy.id = c.cycle
    JOIN menage m ON m.id = e.menage
    $whereSql
    ORDER BY e.nom, e.postnom, e.prenom
    LIMIT 1000
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  $error = "Impossible de charger la liste des élèves.";
}
?>
<div class="container">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 mb-0">Élèves</h1>
      <div class="text-muted small">
        Liste des élèves avec accès rapide à la fiche détaillée (profil, présence, quiz).
      </div>
    </div>

    <!-- Filtres serveur -->
    <form class="row g-2" method="get" role="search">
      <div class="col-12 col-md-3">
        <select name="classe_id" class="form-select form-select-sm">
          <option value="0">Toutes les classes</option>
          <?php foreach ($classes as $cl): ?>
            <option value="<?= (int)$cl['id'] ?>" <?= $classe===(int)$cl['id']?'selected':'' ?>>
              <?= e($cl['classe']) ?><?= $cl['cycle'] ? ' — '.e($cl['cycle']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-3">
        <select name="cycle_id" class="form-select form-select-sm">
          <option value="0">Tous cycles</option>
          <?php foreach ($cycles as $cy): ?>
            <option value="<?= (int)$cy['id'] ?>" <?= $cycleId===(int)$cy['id']?'selected':'' ?>>
              <?= e($cy['description']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-6 col-md-3">
        <select name="annee_scolaire" class="form-select form-select-sm">
          <option value="">Toutes années</option>
          <?php foreach ($annees as $as): ?>
            <option value="<?= e($as) ?>" <?= $annee===$as?'selected':'' ?>><?= e($as) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-auto d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary">OK</button>
        <?php if ($classe>0 || $cycleId>0 || $annee!==''): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/menages/">Réinitialiser</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Recherche instantanée client -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <label class="form-label mb-1">Recherche instantanée (Nom élève)</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text">🔎</span>
        <input id="filterName" type="text" class="form-control" placeholder="Tapez un nom, postnom ou prénom…">
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
      <table class="table table-sm align-middle" id="elevesTable">
        <thead class="table-light">
          <tr>
            <th style="width:1%;">#</th>
            <th>Élève</th>
            <th>Genre</th>
            <th>Classe / Cycle</th>
            <th>Ménage</th>
            <!--<th>Téléphone</th>
            <th>Année scol.</th>-->
            <th style="width:1%;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr class="no-data"><td colspan="8"><em>Aucun élève trouvé.</em></td></tr>
          <?php else: foreach ($rows as $r):
            $fullName = trim($r['nom'].' '.$r['postnom'].' '.$r['prenom']);
            $dataName = mb_strtolower($fullName, 'UTF-8');

            $genre = strtoupper((string)$r['genre']);
            $genreLabel = $genre === 'F' ? 'F' : ($genre === 'M' ? 'M' : $genre);
          ?>
            <tr data-name="<?= e($dataName) ?>">
              <td class="text-muted small"><?= (int)$r['id'] ?></td>
              <td>
                <div class="fw-semibold">
                  <a href="<?= BASE_URL ?>/menages/fiche_eleve.php?id=<?= (int)$r['id'] ?>" class="link-primary text-decoration-none">
                    <?= e($fullName) ?>
                  </a>
                </div>
              </td>
              <td>
                <?php if ($genre === 'F'): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle">F</span>
                <?php elseif ($genre === 'M'): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle">M</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                    <?= e($genreLabel) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <div><?= e($r['classe_desc']) ?></div>
                <div class="small text-muted">
                  <?php if (!empty($r['cycle_desc'])): ?>
                    <span class="badge bg-light text-dark border">
                      <?= e($r['cycle_desc']) ?>
                    </span>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <div><?= e($r['menage_nom']) ?></div>
              </td>
              <!--<td><?= e($r['menage_tel'] ?? '—') ?></td>
              <td><?= e($r['anneeScolaire']) ?></td>-->
              <td class="text-nowrap">
                <div class="btn-group btn-group-sm" role="group">
                  <a class="btn btn-outline-success"
                     href="<?= BASE_URL ?>/menages/fiche_eleve.php?id=<?= (int)$r['id'] ?>">
                    Fiche élève
                  </a>
                  <a class="btn btn-outline-primary"
                     href="<?= BASE_URL ?>/menages/show.php?id=<?= (int)$r['menage_id'] ?>">
                    Ménage (famille)
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <div id="clientNoMatch" class="alert alert-info py-2 d-none mb-0">
        Aucun élève ne correspond à votre filtre.
      </div>

      <div class="text-muted small mt-2">
        Total (après filtre) : <span id="totalCount"><?= count($rows) ?></span> élève(s).
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script>
// Recherche instantanée par nom d'élève (client-side) + compteur dynamique
(function(){
  const input = document.getElementById('filterName');
  const btnClear = document.getElementById('btnClearFilter');
  const table = document.getElementById('elevesTable');
  const noMatch = document.getElementById('clientNoMatch');
  const totalCount = document.getElementById('totalCount');

  function normalize(str){
    return (str || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
  }

  function applyFilters(){
    const q = normalize((input?.value || '').trim().toLowerCase());
    const rows = table.querySelectorAll('tbody tr[data-name]');
    let shown = 0;

    rows.forEach(tr => {
      const name = tr.getAttribute('data-name') || '';
      const match = q === '' ? true : normalize(name).includes(q);
      tr.style.display = match ? '' : 'none';
      if (match) shown++;
    });

    if (noMatch) noMatch.classList.toggle('d-none', shown > 0);
    if (totalCount) totalCount.textContent = String(shown);
  }

  input?.addEventListener('input', applyFilters);
  btnClear?.addEventListener('click', () => {
    input.value = '';
    applyFilters();
    input.focus();
  });

  // initial
  applyFilters();
})();
</script>
