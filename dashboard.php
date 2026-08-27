<?php
// /directeur/dashboard.php — Module Directeur
declare(strict_types=1);

require_once __DIR__.'/includes/auth.php';
require_directeur(); // vérifie session + envoie les headers no-cache

require_once __DIR__.'/layout/header.php';
require_once __DIR__.'/layout/navbar.php';

$BASE = defined('BASE_URL') ? BASE_URL : '/directeur';

/* ============================================================
 *                         COMPTEURS (KPIs)
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

/* Nombre de matières (intitulés de cours uniques) */
try { 
    $nb_cours = (int)$pdo->query("SELECT COUNT(DISTINCT intitule) n FROM cours")->fetch()['n']; 
} catch (Throwable $e) { /* ignore */ }

/* 
 * Nombre de Professeurs distincts affectés à au moins une classe/cours.
 * (Si vous souhaitez compter le nombre d'associations uniques Prof + Classe :
 * SELECT COUNT(DISTINCT CONCAT(agent_id, '-', classe_id)) n FROM affectation_prof_classe)
 */
try { 
    $nb_affect = (int)$pdo->query("SELECT COUNT(DISTINCT agent_id) n FROM affectation_prof_classe")->fetch()['n']; 
} catch (Throwable $e) { /* ignore */ }

/* Pourcentages genre */
$pc_filles = $pc_garcons = 0;
if ($nb_eleves > 0) {
    $pc_filles  = round($nb_filles  * 100 / $nb_eleves, 1);
    $pc_garcons = round($nb_garcons * 100 / $nb_eleves, 1);
}

/* ============================================================
 *     TOP 5 PROFESSEURS (Classes & Matières uniques)
 * ============================================================ */
$top_profs = [];
try {
    $top_profs = $pdo->query("
        SELECT
          ag.id,
          CONCAT(ag.nom, ' ', ag.postnom, ' ', ag.prenom) AS prof,
          COUNT(DISTINCT a.classe_id) AS nb_classes,
          COUNT(DISTINCT co.intitule) AS nb_cours
        FROM affectation_prof_classe a
        JOIN agent ag ON ag.id = a.agent_id
        JOIN cours co ON co.id = a.cours_id
        GROUP BY ag.id, prof
        ORDER BY nb_classes DESC, nb_cours DESC, prof
        LIMIT 5
    ")->fetchAll();
} catch (Throwable $e) { /* ignore */ }

/* ============================================================
 *     TOP 5 CLASSES (Matières distinctes)
 * ============================================================ */
$top_classes = [];
try {
    $top_classes = $pdo->query("
        SELECT
          c.id,
          c.description AS classe,
          cy.description AS cycle,
          COUNT(DISTINCT co.intitule) AS nb_cours
        FROM classe c
        LEFT JOIN cycle cy ON cy.id = c.cycle
        LEFT JOIN cours co ON co.classe_id = c.id
        GROUP BY c.id, c.description, cy.description
        ORDER BY nb_cours DESC, classe
        LIMIT 5
    ")->fetchAll();
} catch (Throwable $e) { /* ignore */ }

/* ============================================================
 *     DERNIÈRES AFFECTATIONS (Groupées par Prof/Classe/Cours)
 * ============================================================ */
$last_affect = [];
try {
    $stmt = $pdo->query("
        SELECT
          MAX(a.id) AS id,
          MAX(a.date_affect) AS date_affect,
          MAX(a.created_at) AS created_at,
          CONCAT(ag.nom, ' ', ag.postnom, ' ', ag.prenom) AS prof,
          c.description AS classe,
          co.intitule   AS cours,
          cy.description AS cycle
        FROM affectation_prof_classe a
        JOIN agent ag   ON ag.id = a.agent_id
        JOIN classe c   ON c.id = a.classe_id
        LEFT JOIN cycle cy ON cy.id = c.cycle
        JOIN cours  co  ON co.id = a.cours_id
        GROUP BY a.agent_id, a.classe_id, co.intitule, prof, classe, cycle
        ORDER BY created_at DESC, id DESC
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

<!-- Inclusion de Font Awesome pour les icônes si pas encore inclus dans header.php -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container my-4">

    <!-- Header Dashboard -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="fa-solid fa-gauge-high text-primary me-2"></i>Tableau de bord
            </h1>
            <p class="text-muted mb-0 fs-7">Vue d'ensemble et statistiques de l'établissement</p>
        </div>
    </div>

    <!-- KPIs Carte de statistiques -->
    <div class="row g-3 mb-4">

        <!-- Filles -->
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-medium">Filles</span>
                        <span class="badge bg-primary-subtle text-primary p-2"><i class="fa-solid fa-venus"></i></span>
                    </div>
                    <div class="h3 mb-1 font-weight-bold text-primary"><?= $nb_filles ?></div>
                    <span
                        class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= $pc_filles ?>%</span>
                </div>
            </div>
        </div>

        <!-- Garçons -->
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-medium">Garçons</span>
                        <span class="badge bg-info-subtle text-info p-2"><i class="fa-solid fa-mars"></i></span>
                    </div>
                    <div class="h3 mb-1 font-weight-bold text-info"><?= $nb_garcons ?></div>
                    <span class="badge bg-info-subtle text-info border border-info-subtle"><?= $pc_garcons ?>%</span>
                </div>
            </div>
        </div>

        <!-- Élèves Totaux -->
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-medium">Élèves</span>
                        <span class="badge bg-dark-subtle text-dark p-2"><i
                                class="fa-solid fa-user-graduate"></i></span>
                    </div>
                    <div class="h3 mb-1 font-weight-bold text-dark"><?= $nb_eleves ?></div>
                    <span class="badge bg-light text-secondary border"><i
                            class="fa-solid fa-users me-1"></i>Total</span>
                </div>
            </div>
        </div>

        <!-- Ménages -->
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-medium">Ménages</span>
                        <span class="badge bg-secondary-subtle text-secondary p-2"><i
                                class="fa-solid fa-house-user"></i></span>
                    </div>
                    <div class="h3 mb-1 font-weight-bold text-secondary"><?= $nb_menages ?></div>
                    <span class="badge bg-light text-secondary border">Inscrits</span>
                </div>
            </div>
        </div>

        <!-- Agents -->
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-medium">Agents</span>
                        <span class="badge bg-success-subtle text-success p-2"><i
                                class="fa-solid fa-user-tie"></i></span>
                    </div>
                    <div class="h3 mb-1 font-weight-bold text-success"><?= $nb_agents ?></div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Actifs</span>
                </div>
            </div>
        </div>

        <!-- Classes / Matières / Affectations -->
        <div class="col-6 col-md-3 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-medium">Classes / Cours</span>
                        <span class="badge bg-warning-subtle text-warning p-2"><i
                                class="fa-solid fa-chalkboard-user"></i></span>
                    </div>
                    <div class="h3 mb-1 font-weight-bold text-warning"><?= $nb_classes ?> <span
                            class="fs-6 text-muted">/ <?= $nb_cours ?></span></div>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i
                            class="fa-solid fa-link me-1"></i><?= $nb_affect ?> Profs Affect.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 5 Profs / Top 5 Classes -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div
                    class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3">
                    <h2 class="h6 mb-0 fw-bold"><i class="fa-solid fa-award text-warning me-2"></i>Top 5 professeurs
                    </h2>
                    <a class="small text-decoration-none" href="<?= $BASE ?>/affectations/index.php">Voir tout <i
                            class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th class="ps-3"><i class="fa-solid fa-user me-1"></i>Professeur</th>
                                    <th class="text-center"><i class="fa-solid fa-school me-1"></i>Classes</th>
                                    <th class="text-center pe-3"><i class="fa-solid fa-book me-1"></i>Matières</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$top_profs): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted"><em>Aucune donnée
                                            disponible</em></td>
                                </tr>
                                <?php else: foreach ($top_profs as $p): ?>
                                <tr>
                                    <td class="ps-3 fw-medium"><?= e($p['prof']) ?></td>
                                    <td class="text-center"><span
                                            class="badge bg-light text-dark border"><?= (int)$p['nb_classes'] ?></span>
                                    </td>
                                    <td class="text-center pe-3"><span
                                            class="badge bg-light text-dark border"><?= (int)$p['nb_cours'] ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div
                    class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3">
                    <h2 class="h6 mb-0 fw-bold"><i class="fa-solid fa-ranking-star text-info me-2"></i>Top 5 classes
                        (par matières)</h2>
                    <a class="small text-decoration-none" href="<?= $BASE ?>/cours/index.php">Voir tout <i
                            class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th class="ps-3"><i class="fa-solid fa-chalkboard me-1"></i>Classe</th>
                                    <th>Cycle</th>
                                    <th class="text-center pe-3"><i class="fa-solid fa-book me-1"></i>Matières</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$top_classes): ?>
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted"><em>Aucune donnée
                                            disponible</em></td>
                                </tr>
                                <?php else: foreach ($top_classes as $c): ?>
                                <tr>
                                    <td class="ps-3 fw-medium"><?= e($c['classe']) ?></td>
                                    <td><span class="text-muted small"><?= e($c['cycle'] ?? '—') ?></span></td>
                                    <td class="text-center pe-3"><span
                                            class="badge bg-light text-dark border"><?= (int)$c['nb_cours'] ?></span>
                                    </td>
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
    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div
                    class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3">
                    <h2 class="h6 mb-0 fw-bold"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Dernières
                        affectations</h2>
                    <a class="small text-decoration-none" href="<?= $BASE ?>/affectations/index.php">Voir tout <i
                            class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="small text-muted">
                                    <th class="ps-3">#</th>
                                    <th>Professeur</th>
                                    <th>Classe / Cycle</th>
                                    <th>Matière</th>
                                    <th class="pe-3"><i class="fa-regular fa-calendar me-1"></i>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$last_affect): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-3 text-muted"><em>Aucune affectation
                                            récente</em></td>
                                </tr>
                                <?php else: foreach ($last_affect as $a): ?>
                                <tr>
                                    <td class="ps-3 text-muted small">#<?= e($a['id']) ?></td>
                                    <td class="fw-medium"><?= e($a['prof']) ?></td>
                                    <td>
                                        <?= e($a['classe']) ?>
                                        <?php if (!empty($a['cycle'])): ?>
                                        <span
                                            class="badge bg-light text-secondary border ms-1 fs-8"><?= e($a['cycle']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span
                                            class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($a['cours']) ?></span>
                                    </td>
                                    <td class="pe-3 text-muted small"><?= e($a['date_affect']) ?></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div
                    class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3">
                    <h2 class="h6 mb-0 fw-bold"><i class="fa-solid fa-bullhorn me-2 text-danger"></i>Dernières annonces
                    </h2>
                    <a class="small text-decoration-none" href="<?= $BASE ?>/annonces/index.php">Voir tout <i
                            class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <?php if (!$last_annonces): ?>
                    <div class="text-muted my-auto text-center py-4"><em>Aucune annonce publiée pour le moment.</em>
                    </div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($last_annonces as $an): ?>
                        <li class="list-group-item px-0 py-2 border-bottom-0">
                            <div class="fw-medium text-dark"><i
                                    class="fa-solid fa-circle-info text-primary me-1"></i><?= e($an['titre']) ?></div>
                            <div class="small text-muted">
                                Visible à : <span
                                    class="badge bg-light text-dark border"><?= e($an['visible_a']) ?></span> ·
                                <i class="fa-regular fa-user me-1"></i><?= e($an['username'] ?? '—') ?> ·
                                <?= e($an['created_at']) ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <div class="pt-2">
                        <a class="btn btn-sm btn-primary w-100" href="<?= $BASE ?>/annonces/create.php">
                            <i class="fa-solid fa-plus me-1"></i> Publier une annonce
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__.'/layout/footer.php'; ?>