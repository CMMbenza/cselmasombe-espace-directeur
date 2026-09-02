<?php
// /directeur/dashboard-generale.php — Vue Utilisateurs / Collaborateurs
declare(strict_types=1);

require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/layout/navbar.php';

$BASE = defined('BASE_URL') ? BASE_URL : '/directeur';

/* ============================================================
 *                         COMPTEURS (KPIs)
 * ============================================================ */
$nb_eleves = $nb_menages = $nb_classes = $nb_cours = 0;

try { $nb_eleves  = (int)$pdo->query("SELECT COUNT(*) n FROM eleve")->fetch()['n']; } catch (Throwable $e) { /* ignore */ }
try { $nb_menages = (int)$pdo->query("SELECT COUNT(*) n FROM menage")->fetch()['n']; } catch (Throwable $e) { /* ignore */ }
try { $nb_classes = (int)$pdo->query("SELECT COUNT(*) n FROM classe")->fetch()['n']; } catch (Throwable $e) { /* ignore */ }
try { $nb_cours   = (int)$pdo->query("SELECT COUNT(DISTINCT intitule) n FROM cours")->fetch()['n']; } catch (Throwable $e) { /* ignore */ }

/* DERNIÈRES AFFECTATIONS & ANNONCES */
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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container my-4">

    <!-- Header Espace Collaborateur -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="fa-solid fa-gauge-high text-primary me-2"></i>Espace Collaborateur</h1>
            <p class="text-muted mb-0 fs-7">Bienvenue sur votre tableau de bord opérationnel</p>
        </div>
        <span class="badge bg-secondary px-3 py-2 fs-7">Rôle : <?= e($_SESSION['user']['role'] ?? 'Utilisateur') ?></span>
    </div>

    <!-- KPIs Épurés (4 cartes) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-medium d-block">Élèves enregistrés</span>
                        <span class="h3 font-weight-bold text-dark mb-0"><?= $nb_eleves ?></span>
                    </div>
                    <div class="badge bg-primary-subtle text-primary p-3 rounded-circle"><i class="fa-solid fa-user-graduate fs-5"></i></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-medium d-block">Ménages</span>
                        <span class="h3 font-weight-bold text-secondary mb-0"><?= $nb_menages ?></span>
                    </div>
                    <div class="badge bg-secondary-subtle text-secondary p-3 rounded-circle"><i class="fa-solid fa-house-user fs-5"></i></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-medium d-block">Classes</span>
                        <span class="h3 font-weight-bold text-warning mb-0"><?= $nb_classes ?></span>
                    </div>
                    <div class="badge bg-warning-subtle text-warning p-3 rounded-circle"><i class="fa-solid fa-school fs-5"></i></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-medium d-block">Matières</span>
                        <span class="h3 font-weight-bold text-info mb-0"><?= $nb_cours ?></span>
                    </div>
                    <div class="badge bg-info-subtle text-info p-3 rounded-circle"><i class="fa-solid fa-book fs-5"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Accès Rapides -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="h6 font-weight-bold mb-3"><i class="fa-solid fa-bolt text-warning me-2"></i>Accès rapides aux outils</h5>
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <a href="<?= $BASE ?>/classe/" class="btn btn-light border w-100 text-start py-2">
                        <i class="fa-solid fa-chalkboard text-primary me-2"></i>Classes
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= $BASE ?>/cours/" class="btn btn-light border w-100 text-start py-2">
                        <i class="fa-solid fa-book-open text-success me-2"></i>Cours
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= $BASE ?>/horaires/" class="btn btn-light border w-100 text-start py-2">
                        <i class="fa-solid fa-calendar-days text-info me-2"></i>Horaires
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="<?= $BASE ?>/journal_classe/" class="btn btn-light border w-100 text-start py-2">
                        <i class="fa-solid fa-journal-whills text-danger me-2"></i>Journal de classe
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Dernières affectations / Dernières annonces -->
    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3">
                    <h2 class="h6 mb-0 fw-bold"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Dernières affectations</h2>
                    <a class="small text-decoration-none" href="<?= $BASE ?>/affectations/index.php">Voir tout <i class="fa-solid fa-arrow-right ms-1"></i></a>
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
                                <tr><td colspan="5" class="text-center py-3 text-muted"><em>Aucune affectation récente</em></td></tr>
                                <?php else: foreach ($last_affect as $a): ?>
                                <tr>
                                    <td class="ps-3 text-muted small">#<?= e($a['id']) ?></td>
                                    <td class="fw-medium"><?= e($a['prof']) ?></td>
                                    <td>
                                        <?= e($a['classe']) ?>
                                        <?php if (!empty($a['cycle'])): ?>
                                        <span class="badge bg-light text-secondary border ms-1 fs-8"><?= e($a['cycle']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= e($a['cours']) ?></span></td>
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
                <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 px-3">
                    <h2 class="h6 mb-0 fw-bold"><i class="fa-solid fa-bullhorn me-2 text-danger"></i>Dernières annonces</h2>
                    <a class="small text-decoration-none" href="<?= $BASE ?>/annonces/index.php">Voir tout <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <?php if (!$last_annonces): ?>
                    <div class="text-muted my-auto text-center py-4"><em>Aucune annonce publiée pour le moment.</em></div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($last_annonces as $an): ?>
                        <li class="list-group-item px-0 py-2 border-bottom-0">
                            <div class="fw-medium text-dark"><i class="fa-solid fa-circle-info text-primary me-1"></i><?= e($an['titre']) ?></div>
                            <div class="small text-muted">
                                Visible à : <span class="badge bg-light text-dark border"><?= e($an['visible_a']) ?></span> ·
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

<?php require_once __DIR__ . '/layout/footer.php'; ?>