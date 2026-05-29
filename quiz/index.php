<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$status    = trim((string)($_GET['statut'] ?? ''));
$classe    = (int)($_GET['classe_id'] ?? 0);
$createdAt = trim((string)($_GET['created_at'] ?? ''));
$ok        = isset($_GET['ok']) ? "Action effectuée avec succès." : '';
$error     = '';
$rows      = [];
$classes   = [];

try {

    // 🔹 Liste des classes pour filtre
    $classes = $pdo->query("
        SELECT c.id, c.description AS classe, cy.description AS cycle
        FROM classe c
        LEFT JOIN cycle cy ON cy.id = c.cycle
        ORDER BY cy.description, c.description
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 🔹 Construire WHERE dynamique
    $wheres = [];
    $params = [];

    $filtersApplied = ($status !== '' || $classe > 0 || $createdAt !== '');

    if (!$filtersApplied) {
        // Par défaut : quiz du jour
        $wheres[] = "DATE(q.created_at) = CURRENT_DATE";
    }

    // Filtre statut
    if ($status !== '' && in_array($status, ['en attente','approuvé','rejeter','à revoir'], true)) {
        $wheres[] = "q.statut = :st";
        $params[':st'] = $status;
    }

    // Filtre classe
    if ($classe > 0) {
        $wheres[] = "qc.classe_id = :cid";
        $params[':cid'] = $classe;
    }

    // Filtre date création
    if ($createdAt !== '') {
        $wheres[] = "DATE(q.created_at) = :createdAt";
        $params[':createdAt'] = $createdAt;
    }

    $whereSql = $wheres ? ('WHERE '.implode(' AND ', $wheres)) : '';

    // 🔹 Requête principale
    $sql = "
        SELECT 
          q.id,
          q.type_quiz,
          q.format,
          q.titre,
          q.description,
          q.statut,
          q.date_limite,
          q.created_at,

          a.id AS agent_id,
          a.nom,
          a.postnom,
          a.prenom,

          GROUP_CONCAT(DISTINCT c.description SEPARATOR ', ') AS classes,
          GROUP_CONCAT(DISTINCT cy.description SEPARATOR ', ') AS cycles,

          COUNT(DISTINCT qq.id) AS nb_questions,
          COUNT(DISTINCT qa.id) AS nb_pj,
          COUNT(DISTINCT qs.id) AS nb_submissions

        FROM quiz q
        JOIN agent a ON a.id = q.agent_id
        JOIN quiz_classe qc ON qc.quiz_id = q.id
        JOIN classe c ON c.id = qc.classe_id
        JOIN cycle cy ON cy.id = c.cycle

        LEFT JOIN quiz_question qq ON qq.quiz_id = q.id
        LEFT JOIN quiz_attachment qa ON qa.quiz_id = q.id
        LEFT JOIN quiz_submission qs ON qs.quiz_id = q.id

        $whereSql

        GROUP BY q.id
        ORDER BY q.created_at DESC
        LIMIT 1000
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error = "Impossible de lire les quiz.";
}
?>

<div class="container">
    <div class="d-block flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Quiz du jour</h1>
        <p class="text-lead">Veuillez consulter le quiz du jour envoyé par les professeurs.</p>
    </div>

    <?php if ($ok): ?>
    <div class="alert alert-success py-2"><?= e($ok) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- FILTRES -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2" method="get">
                <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="" <?= $status===''?'selected':'' ?>>Tous</option>
                        <option value="en attente" <?= $status==='en attente'?'selected':'' ?>>En attente</option>
                        <option value="approuvé" <?= $status==='approuvé'?'selected':'' ?>>Approuvé</option>
                        <option value="rejeter" <?= $status==='rejeter'?'selected':'' ?>>Rejeté</option>
                        <option value="à revoir" <?= $status==='à revoir'?'selected':'' ?>>À revoir</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Classe</label>
                    <select name="classe_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0" <?= $classe===0?'selected':'' ?>>Toutes</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $classe===(int)$c['id']?'selected':'' ?>>
                            <?= e($c['classe']) ?>
                            <?= $c['cycle'] ? ' — Cycle: '.e($c['cycle']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Date création</label>
                    <input type="date" name="created_at" class="form-control form-control-sm"
                        value="<?= e($createdAt) ?>" onchange="this.form.submit()">
                </div>

                <?php if ($status!=='' || $classe>0 || $createdAt!==''): ?>
                <div class="col-md-2 d-flex align-items-end">
                    <a class="btn btn-sm btn-danger" href="<?= BASE_URL ?>/quiz/?">
                        Réinitialiser
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if (!$rows): ?>
    <div class="alert alert-info">Aucun quiz trouvé.</div>
    <?php else: ?>

    <div class="row g-3">
        <?php foreach ($rows as $q): ?>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start">
                        <h5 class="h6 mb-2"><?= e($q['titre']) ?></h5>

                        <?php
                          $badge = 'secondary';
                          if ($q['statut']==='approuvé') $badge='success';
                          elseif ($q['statut']==='en attente') $badge='warning';
                          elseif ($q['statut']==='rejeter') $badge='danger';
                          elseif ($q['statut']==='à revoir') $badge='info';
                        ?>

                        <span class="badge text-bg-<?= $badge ?>">
                            <?= e($q['statut']) ?>
                        </span>
                    </div>

                    <div class="small text-muted mb-2">
                        Prof: <strong><?= e($q['nom'].' '.$q['postnom'].' '.$q['prenom']) ?></strong><br>
                        Classes: <strong><?= e($q['classes']) ?></strong><br>
                        Cycles: <strong><?= e($q['cycles']) ?></strong><br>
                        Type: <?= e($q['type_quiz']) ?> • Format: <?= e($q['format']) ?><br>
                        Créé: <?= e($q['created_at']) ?>
                        <?php if (!empty($q['date_limite'])): ?>
                        • Date limite: <?= e($q['date_limite']) ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($q['description'])): ?>
                    <p class="mb-2"><?= nl2br(e($q['description'])) ?></p>
                    <?php endif; ?>

                    <div class="d-flex flex-wrap gap-3 small text-muted mb-3">
                        <span>Questions: <strong><?= (int)$q['nb_questions'] ?></strong></span>
                        <span>PJ: <strong><?= (int)$q['nb_pj'] ?></strong></span>
                        <span>Soumissions: <strong><?= (int)$q['nb_submissions'] ?></strong></span>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-outline-primary btn-sm"
                            href="<?= BASE_URL ?>/quiz/view.php?id=<?= (int)$q['id'] ?>">
                            Voir
                        </a>

                        <form method="post" action="<?= BASE_URL ?>/quiz/update_status.php" class="d-inline">

                            <input type="hidden" name="quiz_id" value="<?= (int)$q['id'] ?>">

                            <button name="statut" value="approuvé" class="btn btn-success btn-sm">
                                Approuver
                            </button>

                            <button name="statut" value="à revoir" class="btn btn-info btn-sm">
                                À revoir
                            </button>

                            <button name="statut" value="rejeter" class="btn btn-danger btn-sm">
                                Rejeter
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>