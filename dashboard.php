<?php
// /directeur/dashboard.php — Module Directeur
declare(strict_types=1);

require_once __DIR__.'/includes/auth.php';
require_directeur(); // vérifie session + envoie les headers no-cache

require_once __DIR__.'/layout/header.php';
require_once __DIR__.'/layout/navbar.php';

$BASE = defined('BASE_URL') ? BASE_URL : '/directeur';

/* ============================================================
 *                       COMPTEURS (KPIs)
 * ============================================================ */
$nb_filles = $nb_garcons = $nb_eleves = 0;
$nb_menages = $nb_agents = $nb_classes = $nb_cours = $nb_affect = 0;

try {
  $row = $pdo->query("
    SELECT
      SUM(CASE WHEN genre='F' THEN 1 ELSE 0 END) AS filles,
      SUM(CASE WHEN genre='M' THEN 1 ELSE 0 END) AS garcons,
      COUNT(*) AS total
    FROM eleve
  ")->fetch();
  if ($row) {
    $nb_filles  = (int)($row['filles']  ?? 0);
    $nb_garcons = (int)($row['garcons'] ?? 0);
    $nb_eleves  = (int)($row['total']   ?? 0);
  }
} catch (Throwable $e) { /* ignore */ }

try { $nb_menages = (int)$pdo->query("SELECT COUNT(*) n FROM menage")->fetch()['n']; } catch (Throwable $e) { /* ignore */ }
try { $nb_agents  = (int)$pdo->query("SELECT COUNT(*) n FROM agent")->fetch()['n']; } catch (Throwable $e) { /* ignore */ }
try { $nb_classes = (int)$pdo->query("SELECT COUNT(*) n FROM classe")->fetch()['n']; } catch (Throwable $e) { /* ignore */ }
try { $nb_cours   = (int)$pdo->query("SELECT COUNT(*) n FROM cours")->fetch()['n']; } catch (Throwable $e) { /* ignore */ }
try { $nb_affect  = (int)$pdo->query("SELECT COUNT(*) n FROM affectation_prof_classe")->fetch()['n']; } catch (Throwable $e) { /* ignore */ }

/* Pourcentages genre (éviter division par zéro) */
$pc_filles = $pc_garcons = 0;
if ($nb_eleves > 0) {
  $pc_filles  = round($nb_filles  * 100 / $nb_eleves, 1);
  $pc_garcons = round($nb_garcons * 100 / $nb_eleves, 1);
}

/* ============================================================
 *            TOP 5 PROFESSEURS (par classes & cours)
 * ============================================================ */
$top_profs = [];
try {
  $top_profs = $pdo->query("
    SELECT
      ag.id,
      CONCAT(ag.nom, ' ', ag.postnom, ' ', ag.prenom) AS prof,
      COUNT(DISTINCT a.classe_id) AS nb_classes,
      COUNT(DISTINCT a.cours_id)  AS nb_cours
    FROM affectation_prof_classe a
    JOIN agent ag ON ag.id = a.agent_id
    GROUP BY ag.id, prof
    ORDER BY nb_classes DESC, nb_cours DESC, prof
    LIMIT 5
  ")->fetchAll();
} catch (Throwable $e) { /* ignore */ }

/* ============================================================
 *             TOP 5 CLASSES (par nombre de cours)
 * ============================================================ */
$top_classes = [];
try {
  $top_classes = $pdo->query("
    SELECT
      c.id,
      c.description AS classe,
      cy.description AS cycle,
      COUNT(co.id) AS nb_cours
    FROM classe c
    LEFT JOIN cycle cy ON cy.id = c.cycle
    LEFT JOIN cours co ON co.classe_id = c.id
    GROUP BY c.id, classe, cycle
    ORDER BY nb_cours DESC, classe
    LIMIT 5
  ")->fetchAll();
} catch (Throwable $e) { /* ignore */ }

/* ============================================================
 *            DERNIÈRES AFFECTATIONS (10 plus récentes)
 * ============================================================ */
$last_affect = [];
try {
  $stmt = $pdo->query("
    SELECT
      a.id,
      a.date_affect,
      a.created_at,
      CONCAT(ag.nom,' ',ag.postnom,' ',ag.prenom) AS prof,
      c.description AS classe,
      co.intitule   AS cours,
      cy.description AS cycle
    FROM affectation_prof_classe a
    JOIN agent ag   ON ag.id = a.agent_id
    JOIN classe c   ON c.id = a.classe_id
    LEFT JOIN cycle cy ON cy.id = c.cycle
    JOIN cours  co  ON co.id = a.cours_id
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT 5
  ");
  $last_affect = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable $e) { /* ignore */ }

/* ============================================================
 *                  DERNIÈRES ANNONCES (5)
 * ============================================================ */
$last_annonces = [];
try {
  $stmt = $pdo->query("
    SELECT a.id, a.titre, a.visible_a, a.created_at, u.username
    FROM annonces a
    LEFT JOIN users u ON u.id = a.created_by
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT 5
  ");
  $last_annonces = $stmt ? $stmt->fetchAll() : [];
} catch (Throwable $e) { /* ignore */ }
?>
<div class="container">

  <!-- Raccourcis -->
  <div class="d-none row g-3 mb-2">
    <div class="col-12">
      <h2 class="h5 mb-2">Actions rapides</h2>
    </div>
    <div class="col-6 col-md-3">
      <a class="text-decoration-none" href="<?= $BASE ?>/agents/create.php">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">Agents</div>
            <div class="h5 mb-0">Ajouter un agent</div>
          </div>
        </div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a class="text-decoration-none" href="<?= $BASE ?>/affectations/create.php">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">Affectations</div>
            <div class="h5 mb-0">Affecter un prof</div>
          </div>
        </div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a class="text-decoration-none" href="<?= $BASE ?>/cours/create.php">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">Cours</div>
            <div class="h5 mb-0">Créer des cours</div>
          </div>
        </div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a class="text-decoration-none" href="<?= $BASE ?>/annonces/create.php">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small">Annonces</div>
            <div class="h5 mb-0">Publier une annonce</div>
          </div>
        </div>
      </a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3">
    <div class="col-12">
      <h2 class="h5 mb-2">Indicateurs</h2>
    </div>

    <div class="col-6 col-md-3 col-lg-2">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Filles</div>
        <div class="h3 mb-0"><?= $nb_filles ?></div>
        <div class="small text-muted"><?= $pc_filles ?>%</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Garçons</div>
        <div class="h3 mb-0"><?= $nb_garcons ?></div>
        <div class="small text-muted"><?= $pc_garcons ?>%</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Élèves</div>
        <div class="h3 mb-0"><?= $nb_eleves ?></div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Ménages</div>
        <div class="h3 mb-0"><?= $nb_menages ?></div>
      </div></div>
    </div>

    <div class="col-6 col-md-3 col-lg-2">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Agents</div>
        <div class="h3 mb-0"><?= $nb_agents ?></div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Classes</div>
        <div class="h3 mb-0"><?= $nb_classes ?></div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Cours</div>
        <div class="h3 mb-0"><?= $nb_cours ?></div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Affectations</div>
        <div class="h3 mb-0"><?= $nb_affect ?></div>
      </div></div>
    </div>
  </div>

  <!-- Top 5 Profs / Top 5 Classes -->
  <div class="row g-3 mt-1">
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">Top 5 professeurs (par classes & cours)</h2>
            <a class="small" href="<?= $BASE ?>/affectations/index.php">Voir tout</a>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Prof</th>
                  <th class="text-center">Classes</th>
                  <th class="text-center">Cours</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$top_profs): ?>
                  <tr><td colspan="3"><em>Aucune donnée</em></td></tr>
                <?php else: foreach ($top_profs as $p): ?>
                  <tr>
                    <td><?= e($p['prof']) ?></td>
                    <td class="text-center"><span class="badge text-bg-light border"><?= (int)$p['nb_classes'] ?></span></td>
                    <td class="text-center"><span class="badge text-bg-light border"><?= (int)$p['nb_cours'] ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">Top 5 classes (par nombre de cours)</h2>
            <a class="small" href="<?= $BASE ?>/cours/index.php">Voir tout</a>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Classe</th>
                  <th>Cycle</th>
                  <th class="text-center">Cours</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$top_classes): ?>
                  <tr><td colspan="3"><em>Aucune donnée</em></td></tr>
                <?php else: foreach ($top_classes as $c): ?>
                  <tr>
                    <td><?= e($c['classe']) ?></td>
                    <td><?= e($c['cycle'] ?? '—') ?></td>
                    <td class="text-center"><span class="badge text-bg-light border"><?= (int)$c['nb_cours'] ?></span></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Dernières affectations / Dernières annonces -->
  <div class="row g-3 mt-1">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">Dernières affectations</h2>
            <a class="small" href="<?= $BASE ?>/affectations/index.php">Voir tout</a>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Prof</th>
                  <th>Classe</th>
                  <th>Cours</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$last_affect): ?>
                  <tr><td colspan="5"><em>Aucune affectation récente</em></td></tr>
                <?php else: foreach ($last_affect as $a): ?>
                  <tr>
                    <td><?= e($a['id']) ?></td>
                    <td><?= e($a['prof']) ?></td>
                    <td>
                      <?= e($a['classe']) ?>
                      <?php if (!empty($a['cycle'])): ?>
                        <span class="badge text-bg-light border ms-1">Cycle: <?= e($a['cycle']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?= e($a['cours']) ?></td>
                    <td><?= e($a['date_affect']) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">Dernières annonces</h2>
            <a class="small" href="<?= $BASE ?>/annonces/index.php">Voir tout</a>
          </div>

          <?php if (!$last_annonces): ?>
            <div class="text-muted"><em>Aucune annonce récente</em></div>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($last_annonces as $an): ?>
                <li class="list-group-item px-0">
                  <div class="fw-semibold"><?= e($an['titre']) ?></div>
                  <div class="small text-muted">
                    Visible à : <?= e($an['visible_a']) ?> ·
                    Par : <?= e($an['username'] ?? '—') ?> ·
                    Le : <?= e($an['created_at']) ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <div class="mt-3">
            <a class="btn btn-sm btn-primary" href="<?= $BASE ?>/annonces/create.php">Publier une annonce</a>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
<?php require_once __DIR__.'/layout/footer.php'; ?>
