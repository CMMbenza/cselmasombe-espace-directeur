<?php
// /directeur/classe/index.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // $pdo, e(), BASE_URL, anti-cache
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$error   = '';
$classes = [];

try {
    // Récupération des classes avec jointure cycle et décompte des élèves
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.description AS classe_nom,
            c.anneeScolaire,
            c.dateCreaty,
            cy.description AS cycle_nom,
            COUNT(e.id) AS nb_eleves
        FROM classe c
        LEFT JOIN cycle cy ON cy.id = c.cycle
        LEFT JOIN eleve e ON e.classe = c.id
        GROUP BY c.id, c.description, c.anneeScolaire, c.dateCreaty, cy.description
        ORDER BY cy.description ASC, c.description ASC
    ");
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error = "Impossible de charger la liste des classes : " . $e->getMessage();
}
?>

<div class="container my-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Gestion des Classes</h1>
        <!-- <a href="add.php" class="btn btn-primary btn-sm">
      + Ajouter une classe
    </a> -->
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <!-- <th style="width: 1%;">#</th> -->
                            <th>Classe</th>
                            <!-- <th>Cycle</th> -->
                            <th>Année Scolaire</th>
                            <th class="text-center">Élèves Inscrits</th>
                            <th>Date de Création</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classes)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Aucune classe enregistrée pour le moment.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($classes as $c): ?>
                        <tr>
                            <!-- <td><?= (int)$c['id'] ?></td> -->
                            <td class=" "><?= e($c['classe_nom']) ?> - <?= e($c['cycle_nom'] ?: '—') ?></td>
                            <!-- <td>
                                <span class="badge bg-secondary">
                                    <?= e($c['cycle_nom'] ?: '—') ?>
                                </span>
                            </td> -->
                            <td><?= e($c['anneeScolaire'] ?: '—') ?></td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-info text-dark fs-6 px-3">
                                    <?= (int)$c['nb_eleves'] ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= $c['dateCreaty'] ? date('d/m/Y', strtotime($c['dateCreaty'])) : '—' ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <!-- Bouton voir les élèves de la classe -->
                                    <a href="details_eleve_classe.php?classe_id=<?= (int)$c['id'] ?>"
                                        class="btn btn-outline-primary" title="Voir les élèves de cette classe">
                                        La composition
                                    </a>

                                    <!-- Bouton consulter les présences / appels -->
                                    <a href="details_presence_classe.php?classe_id=<?= (int)$c['id'] ?>"
                                        class="btn btn-outline-success" title="Consulter les présences">
                                        Présences
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>