<?php
// /directeur/affectations/index.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur(); // session + anti-cache
require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';

$q = trim((string)($_GET['q'] ?? ''));
$error = '';

/**
 * 1) Résumé par professeur (1 ligne par prof)
 *    - nb_classes = COUNT(DISTINCT classe_id)
 *    - nb_cours   = COUNT(DISTINCT cours_id)
 *    - last_aff   = MAX(date_affect)
 */
$summary = [];
try {
  if ($q !== '') {
    $stmt = $pdo->prepare("
      SELECT
        ag.id AS agent_id,
        CONCAT(ag.nom, ' ', ag.postnom, ' ', ag.prenom) AS prof,
        COUNT(DISTINCT a.classe_id) AS nb_classes,
        COUNT(DISTINCT a.cours_id)  AS nb_cours,
        MAX(a.date_affect) AS last_aff
      FROM affectation_prof_classe a
      JOIN agent ag   ON ag.id = a.agent_id
      JOIN classe c   ON c.id = a.classe_id
      LEFT JOIN cycle cy ON cy.id = c.cycle
      JOIN cours co   ON co.id = a.cours_id
      WHERE
        CONCAT(ag.nom, ' ', ag.postnom, ' ', ag.prenom) LIKE :like
        OR c.description LIKE :like
        OR co.intitule   LIKE :like
        OR cy.description LIKE :like
      GROUP BY ag.id, prof
      ORDER BY prof
    ");
    $like = "%{$q}%";
    $stmt->execute([':like' => $like]);
    $summary = $stmt->fetchAll();
  } else {
    $summary = $pdo->query("
      SELECT
        ag.id AS agent_id,
        CONCAT(ag.nom, ' ', ag.postnom, ' ', ag.prenom) AS prof,
        COUNT(DISTINCT a.classe_id) AS nb_classes,
        COUNT(DISTINCT a.cours_id)  AS nb_cours,
        MAX(a.date_affect) AS last_aff
      FROM affectation_prof_classe a
      JOIN agent ag ON ag.id = a.agent_id
      GROUP BY ag.id, prof
      ORDER BY prof
    ")->fetchAll();
  }
} catch (Throwable $e) {
  $error = "Impossible de lire le résumé des affectations.";
}

/**
 * 2) Détails à afficher dans le modal
 *    - Sans DISTINCT (on montre les lignes réelles)
 *    - On ne charge que pour les profs présents dans $summary
 */
$details_by_agent = [];
if (!$error && $summary) {
  $agent_ids = array_map(fn($r) => (int)$r['agent_id'], $summary);
  $placeholders = implode(',', array_fill(0, count($agent_ids), '?'));

  try {
    $sql = "
      SELECT
        a.id AS affect_id,
        a.agent_id,
        c.description AS classe,
        cy.description AS cycle,
        co.intitule   AS cours,
        a.date_affect,
        a.created_at
      FROM affectation_prof_classe a
      JOIN classe c   ON c.id = a.classe_id
      LEFT JOIN cycle cy ON cy.id = c.cycle
      JOIN cours  co  ON co.id = a.cours_id
      WHERE a.agent_id IN ($placeholders)
      ORDER BY c.description, co.intitule, a.date_affect DESC, a.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($agent_ids);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
      $aid = (int)$r['agent_id'];
      if (!isset($details_by_agent[$aid])) {
        $details_by_agent[$aid] = [
          'classes' => [],  // uniques
          'cours'   => [],  // uniques
          'rows'    => [],  // bruts (sans DISTINCT)
        ];
      }
      // uniques (par libellé)
      $cls = $r['classe'] . (!empty($r['cycle']) ? " — Cycle: ".$r['cycle'] : '');
      $details_by_agent[$aid]['classes'][$cls] = true;
      $details_by_agent[$aid]['cours'][$r['cours']] = true;

      // bruts
      $details_by_agent[$aid]['rows'][] = [
        'affect_id'  => (int)$r['affect_id'],
        'classe'     => $r['classe'],
        'cycle'      => $r['cycle'] ?? null,
        'cours'      => $r['cours'],
        'date_affect'=> $r['date_affect'],
        'created_at' => $r['created_at'],
      ];
    }

    // transforme les sets en listes
    foreach ($details_by_agent as $aid => $pack) {
      $details_by_agent[$aid]['classes'] = array_keys($pack['classes']);
      $details_by_agent[$aid]['cours']   = array_keys($pack['cours']);
    }

  } catch (Throwable $e) {
    $error = "Impossible de lire les détails des affectations.";
  }
}

// Encodage pour JS
$details_json = json_encode($details_by_agent, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
?>
<div class="container">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Affectations (Résumé par professeur)</h1>
    <div class="d-flex gap-2">
      <form class="d-flex" method="get" role="search">
        <input class="form-control form-control-sm" type="search" name="q" placeholder="Rechercher (prof, classe, cours, cycle)" value="<?= e($q) ?>">
        <button class="btn btn-sm btn-outline-secondary ms-2">OK</button>
        <?php if ($q !== ''): ?>
          <a class="btn btn-sm btn-outline-secondary ms-1" href="<?= BASE_URL ?>/affectations/index.php">Réinitialiser</a>
        <?php endif; ?>
      </form>
      <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/affectations/create.php">Nouvelle affectation</a>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Prof</th>
            <th>Classes</th>
            <th>Cours</th>
            <th>Dernière affect.</th>
            <th style="width:1%;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$summary): ?>
            <tr><td colspan="6"><em>Aucune affectation trouvée.</em></td></tr>
          <?php else: foreach ($summary as $r): ?>
            <tr>
              <td><?= e($r['agent_id']) ?></td>
              <td><?= e($r['prof']) ?></td>
              <td><span class="badge text-bg-light border"><?= (int)$r['nb_classes'] ?></span></td>
              <td><span class="badge text-bg-light border"><?= (int)$r['nb_cours'] ?></span></td>
              <td><?= e($r['last_aff']) ?></td>
              <td class="text-nowrap">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary"
                  data-bs-toggle="modal"
                  data-bs-target="#detailModal"
                  data-agent-id="<?= (int)$r['agent_id'] ?>"
                  data-prof="<?= e($r['prof']) ?>"
                >
                  Détails
                </button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      <div class="text-muted small">Total profs : <?= count($summary) ?>.</div>
    </div>
  </div>
</div>

<!-- Modal Détails (par professeur) -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Détails — <span id="m-prof">Professeur</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <h6>Classes (uniques)</h6>
            <ul id="m-classes" class="mb-3"></ul>
          </div>
          <div class="col-md-6">
            <h6>Cours (uniques)</h6>
            <ul id="m-cours" class="mb-3"></ul>
          </div>
        </div>

        <h6 class="mt-2">Affectations (détails bruts)</h6>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>#Affect.</th>
                <th>Classe</th>
                <th>Cours</th>
                <th>Date d’affect.</th>
                <th>Créé le</th>
              </tr>
            </thead>
            <tbody id="m-rows">
              <tr><td colspan="5"><em>Aucune donnée.</em></td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<script>
// Données de détails (par agent) injectées côté serveur
const DETAILS = <?= $details_json ?: '{}' ?>;

const detailModal = document.getElementById('detailModal');
detailModal?.addEventListener('show.bs.modal', (event) => {
  const btn = event.relatedTarget;
  if (!btn) return;

  const agentId = parseInt(btn.getAttribute('data-agent-id') || '0', 10);
  const prof    = btn.getAttribute('data-prof') || 'Professeur';
  document.getElementById('m-prof').textContent = prof;

  const pack = DETAILS[agentId] || {classes: [], cours: [], rows: []};

  // Classes
  const ulClasses = document.getElementById('m-classes');
  ulClasses.innerHTML = '';
  if (pack.classes.length) {
    pack.classes.forEach(c => {
      const li = document.createElement('li'); li.textContent = c; ulClasses.appendChild(li);
    });
  } else {
    ulClasses.innerHTML = '<li><em>Aucune</em></li>';
  }

  // Cours
  const ulCours = document.getElementById('m-cours');
  ulCours.innerHTML = '';
  if (pack.cours.length) {
    pack.cours.forEach(co => {
      const li = document.createElement('li'); li.textContent = co; ulCours.appendChild(li);
    });
  } else {
    ulCours.innerHTML = '<li><em>Aucun</em></li>';
  }

  // Lignes brutes (sans DISTINCT)
  const tbody = document.getElementById('m-rows');
  tbody.innerHTML = '';
  if (pack.rows.length) {
    pack.rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${r.affect_id}</td>
        <td>${r.classe}${r.cycle ? ' <span class="badge text-bg-light border ms-1">Cycle: '+r.cycle+'</span>' : ''}</td>
        <td>${r.cours}</td>
        <td>${r.date_affect}</td>
        <td>${r.created_at}</td>
      `;
      tbody.appendChild(tr);
    });
  } else {
    tbody.innerHTML = '<tr><td colspan="5"><em>Aucune affectation trouvée.</em></td></tr>';
  }
});
</script>

<?php require_once __DIR__.'/../layout/footer.php'; ?>
