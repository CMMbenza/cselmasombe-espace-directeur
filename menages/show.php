<?php
// /directeur/menages/show.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // $pdo, e(), BASE_URL, anti-cache
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$menageId = (int)($_GET['id'] ?? 0);
if ($menageId <= 0) {
    header('Location: ' . BASE_URL . '/menages/index.php'); exit;
}

$error   = '';
$menage  = null;
$enfants = [];

// Agrégats scolarité
$pAgg   = ['a_payer'=>0.0, 'paye'=>0.0, 'reste'=>0.0];
$pRows  = [];

// Agrégats frais connexes (paiement_divers)
$dAgg          = ['a_payer'=>0.0, 'paye'=>0.0, 'reste'=>0.0];
$dByType       = []; // par type_frais
$dRows         = []; // historique

try {
    // 1) Info ménage avec province et tous les autres champs
    $stmt = $pdo->prepare("
        SELECT id, noms, telephone, email, numero, avenue, quartier, commune, province,
               dateCreated, montantAPayer, montantAPayerFC, start_tranche, STATUS
        FROM menage
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $menageId]);
    $menage = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$menage) {
        header('Location: ' . BASE_URL . '/menages/index.php'); exit;
    }

    // 2) Enfants du ménage (avec détails complets)
    $stmtE = $pdo->prepare("
        SELECT 
          e.id,
          e.matricule,
          e.nom, e.postnom, e.prenom, e.genre,
          e.lieu, e.dateDeNaissance,
          e.nationalite, e.STATUS,
          e.anneeScolaire,
          c.description AS classe_desc,
          cy.description AS cycle_desc
        FROM eleve e
        JOIN classe c ON c.id = e.classe
        LEFT JOIN cycle cy ON cy.id = c.cycle
        WHERE e.menage = :id
        ORDER BY e.nom, e.postnom, e.prenom
    ");
    $stmtE->execute([':id' => $menageId]);
    $enfants = $stmtE->fetchAll(PDO::FETCH_ASSOC);

    // 3) Agrégat Paiement (frais scolaires) — table paiement
    $stmtPA = $pdo->prepare("
        SELECT 
          COALESCE(SUM(montantAPayer), 0) AS total_a_payer,
          COALESCE(SUM(montantPayer),  0) AS total_paye,
          COALESCE(SUM(resteAPayer),   0) AS total_reste
        FROM paiement
        WHERE menage = :id
    ");
    $stmtPA->execute([':id' => $menageId]);
    $agg = $stmtPA->fetch(PDO::FETCH_ASSOC) ?: [];
    $a_payer = (float)($agg['total_a_payer'] ?? 0);
    $paye    = (float)($agg['total_paye'] ?? 0);
    $reste   = max(0.0, $a_payer - $paye);
    $pAgg    = ['a_payer'=>$a_payer, 'paye'=>$paye, 'reste'=>$reste];

    // 4) Historique des lignes de paiement (scolarité)
    $stmtPL = $pdo->prepare("
        SELECT id, montantAPayer, montantPayer, resteAPayer, observation, dateCreated, anneeScolaire
        FROM paiement
        WHERE menage = :id
        ORDER BY dateCreated DESC, id DESC
    ");
    $stmtPL->execute([':id' => $menageId]);
    $pRows = $stmtPL->fetchAll(PDO::FETCH_ASSOC);

    // 5) Agrégats FRAIS CONNEXES — table paiement_divers (global)
    $stmtDA = $pdo->prepare("
        SELECT 
          COALESCE(SUM(montantAPayer), 0) AS total_a_payer,
          COALESCE(SUM(montantPayer),  0) AS total_paye,
          COALESCE(SUM(resteAPayer),   0) AS total_reste
        FROM paiement_divers
        WHERE menage = :id
    ");
    $stmtDA->execute([':id' => $menageId]);
    $dAggRow = $stmtDA->fetch(PDO::FETCH_ASSOC) ?: [];
    $d_a_payer = (float)($dAggRow['total_a_payer'] ?? 0);
    $d_paye    = (float)($dAggRow['total_paye'] ?? 0);
    $d_reste   = max(0.0, $d_a_payer - $d_paye);
    $dAgg = ['a_payer'=>$d_a_payer, 'paye'=>$d_paye, 'reste'=>$d_reste];

    // 6) Agrégats par type_frais
    $stmtDT = $pdo->prepare("
        SELECT type_frais,
               COALESCE(SUM(montantAPayer), 0) AS a_payer,
               COALESCE(SUM(montantPayer),  0) AS paye,
               COALESCE(SUM(resteAPayer),   0) AS reste_col
        FROM paiement_divers
        WHERE menage = :id
        GROUP BY type_frais
        ORDER BY type_frais
    ");
    $stmtDT->execute([':id' => $menageId]);
    while ($row = $stmtDT->fetch(PDO::FETCH_ASSOC)) {
        $a  = (float)($row['a_payer'] ?? 0);
        $p  = (float)($row['paye'] ?? 0);
        $re = max(0.0, $a - $p);
        $dByType[] = [
          'type_frais' => (string)$row['type_frais'],
          'a_payer'    => $a,
          'paye'       => $p,
          'reste'      => $re,
        ];
    }

    // 7) Historique des écritures paiement_divers
    $stmtDL = $pdo->prepare("
        SELECT id, type_frais, montantAPayer, montantPayer, resteAPayer, observation, anneeScolaire, dateCreated
        FROM paiement_divers
        WHERE menage = :id
        ORDER BY dateCreated DESC, id DESC
    ");
    $stmtDL->execute([':id' => $menageId]);
    $dRows = $stmtDL->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error = "Impossible de charger les détails du ménage.";
}
?>
<div class="container my-4">

    <div class="d-flex justify-content-between align-items-start mb-3">
        <h1 class="h5 mb-0">
            Ménage #<?= e((string)$menageId) ?>
            <?php if ($menage): ?>
            <span class="badge bg-<?= $menage['STATUS'] === 'actif' ? 'success' : 'secondary' ?> ms-2">
                <?= e(strtoupper($menage['STATUS'])) ?>
            </span>
            <?php endif; ?>
        </h1>
        <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/menages/index.php">&larr; Retour à la
            liste</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
    <?php else: ?>

    <!-- Infos ménage -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="mb-1"><span class="text-muted small">Ménage</span></div>
                    <div class="fw-semibold fs-6"><?= e($menage['noms']) ?></div>
                    <div class="small text-muted">
                        Téléphone: <strong><?= e($menage['telephone'] ?: '—') ?></strong>
                    </div>
                    <div class="small text-muted">
                        Email: <strong><?= e($menage['email'] ?: '—') ?></strong>
                    </div>
                </div>

                <div class="col-md-4">
                    <?php
              $addr  = implode(' ', array_filter(['N°' . ($menage['numero'] ?: ''), $menage['avenue'] ?: '']));
              $addr2 = implode(' - ', array_filter([$menage['quartier'] ?: '', $menage['commune'] ?: '']));
            ?>
                    <div class="mb-1"><span class="text-muted small">Adresse & Localisation</span></div>
                    <div><?= e(trim($addr)) ?: '—' ?></div>
                    <div class="small text-muted"><?= e($addr2 ?: '—') ?></div>
                    <div class="small text-muted">Province : <strong><?= e($menage['province'] ?: '—') ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scolarité (paiement) -->
    <?php $progress = ($pAgg['a_payer'] > 0) ? min(100, max(0, round($pAgg['paye'] * 100 / $pAgg['a_payer']))) : 0; ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                <h5 class="h6 mb-0">Modalité de paiement — Frais scolaire</h5>
                <div class="small text-muted">
                    Montant fixe (ménage) : <strong><?= number_format((float)$menage['montantAPayer'], 2, ',', ' ') ?>
                        USD</strong>
                    / <strong><?= number_format((float)$menage['montantAPayerFC'], 2, ',', ' ') ?> USD</strong>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="border rounded p-2 bg-light">
                        <div class="small text-muted">Total à payer (agrégé paiements)</div>
                        <div class="fw-semibold"><?= number_format($pAgg['a_payer'], 2, ',', ' ') ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="border rounded p-2 bg-light">
                        <div class="small text-muted">Total payé</div>
                        <div class="fw-semibold text-success"><?= number_format($pAgg['paye'], 2, ',', ' ') ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="border rounded p-2 bg-light">
                        <div class="small text-muted">Reste</div>
                        <div class="fw-semibold text-danger"><?= number_format($pAgg['reste'], 2, ',', ' ') ?></div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="progress" role="progressbar" aria-label="Progression paiement"
                        aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?= $progress ?>%"><?= $progress ?>%</div>
                    </div>
                </div>
            </div>

            <?php if ($pRows): ?>
            <hr>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr class="table-light">
                            <th style="width:1%;">#</th>
                            <th>Date</th>
                            <th>Année scolaire</th>
                            <th class="text-end">Montant à payer</th>
                            <th class="text-end">Montant payé</th>
                            <th class="text-end">Reste</th>
                            <th>Observation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pRows as $p): ?>
                        <tr>
                            <td><?= (int)$p['id'] ?></td>
                            <td><?= e($p['dateCreated']) ?></td>
                            <td><?= e($p['anneeScolaire'] ?? '—') ?></td>
                            <td class="text-end"><?= number_format((float)$p['montantAPayer'], 2, ',', ' ') ?> USD</td>
                            <td class="text-end"><?= number_format((float)$p['montantPayer'], 2, ',', ' ') ?> USD</td>
                            <td class="text-end text-danger"><?= number_format((float)$p['resteAPayer'], 2, ',', ' ') ?>
                                USD</td>
                            <td><?= nl2br(e($p['observation'] ?? '')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-muted small mt-2">Aucun enregistrement de paiement (scolarité) trouvé pour ce ménage.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FRAIS CONNEXES (paiement_divers) -->
    <?php $d_progress = ($dAgg['a_payer'] > 0) ? min(100, max(0, round($dAgg['paye'] * 100 / $dAgg['a_payer']))) : 0; ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                <h5 class="h6 mb-0">Frais connexes — paiement_divers</h5>
                <div class="small text-muted">Synthèse globale (tous types)</div>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <div class="border rounded p-2 bg-light">
                        <div class="small text-muted">Total à payer</div>
                        <div class="fw-semibold"><?= number_format($dAgg['a_payer'], 2, ',', ' ') ?> USD</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="border rounded p-2 bg-light">
                        <div class="small text-muted">Total payé</div>
                        <div class="fw-semibold text-success"><?= number_format($dAgg['paye'], 2, ',', ' ') ?> USD</div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="border rounded p-2 bg-light">
                        <div class="small text-muted">Reste</div>
                        <div class="fw-semibold text-danger"><?= number_format($dAgg['reste'], 2, ',', ' ') ?> USD</div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="progress" role="progressbar" aria-label="Progression paiement divers"
                        aria-valuenow="<?= $d_progress ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?= $d_progress ?>%"><?= $d_progress ?>%</div>
                    </div>
                </div>
            </div>

            <hr>
            <h6 class="mb-2">Historique des écritures</h6>
            <?php if ($dRows): ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr class="table-light">
                            <th style="width:1%;">#</th>
                            <th>Date</th>
                            <th>Année scolaire</th>
                            <th class="text-end">Montant à payer</th>
                            <th class="text-end">Montant payé</th>
                            <th class="text-end">Reste</th>
                            <th>Observation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dRows as $d): ?>
                        <tr>
                            <td><?= (int)$d['id'] ?></td>
                            <td><?= e($d['dateCreated']) ?></td>
                            <td><?= e($d['anneeScolaire'] ?? '—') ?></td>
                            <td class="text-end"><?= number_format((float)$d['montantAPayer'], 2, ',', ' ') ?> USD</td>
                            <td class="text-end"><?= number_format((float)$d['montantPayer'], 2, ',', ' ') ?> USD</td>
                            <td class="text-end text-danger"><?= number_format((float)$d['resteAPayer'], 2, ',', ' ') ?>
                                USD</td>
                            <td><?= nl2br(e($d['observation'] ?? '')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-muted small">Aucun enregistrement dans <code>paiement_divers</code> pour ce ménage.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Enfants du ménage -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="h6 mb-0">Enfants</h5>
                <span class="badge text-bg-secondary"><?= count($enfants) ?></span>
            </div>

            <?php if (!$enfants): ?>
            <div class="text-muted">Aucun enfant enregistré pour ce ménage.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr class="table-light">
                            <th style="width:1%;">#</th>
                            <th>Matricule</th>
                            <th>Élève</th>
                            <th>Genre</th>
                            <th>Naissance</th>
                            <th>Nationalité</th>
                            <th>Classe</th>
                            <!-- <th>Cycle</th> -->
                            <th>Année scolaire</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enfants as $e_item): ?>
                        <?php 
                    // Génération dynamique du matricule miroir
                    $iNom     = mb_strtoupper(mb_substr(trim($e_item['nom'] ?? ''), 0, 1));
                    $iPostnom = mb_strtoupper(mb_substr(trim($e_item['postnom'] ?? ''), 0, 1));
                    $iPrenom  = mb_strtoupper(mb_substr(trim($e_item['prenom'] ?? ''), 0, 1));
                    $matriculeMiroir = $e_item['id'].'-'. $iNom . $iPostnom . $iPrenom.'-'.'CSES26';
                  ?>
                        <tr>
                            <td><?= (int)$e_item['id'] ?></td>
                            <td><code><?= e($matriculeMiroir) ?></code></td>
                            <td><?= e($e_item['nom'].' '.$e_item['postnom'].' '.$e_item['prenom']) ?></td>
                            <td><?= e($e_item['genre']) ?></td>
                            <td>
                                <?= e($e_item['lieu'] ?: '—') ?>,
                                <?= $e_item['dateDeNaissance'] ? date('d/m/Y', strtotime($e_item['dateDeNaissance'])) : '—' ?>
                            </td>
                            <td><?= e($e_item['nationalite']) ?></td>
                            <td><?= e($e_item['classe_desc']) ?>-<?= e($e_item['cycle_desc'] ?? '—') ?></td>
                            <!-- <td><?= e($e_item['cycle_desc'] ?? '—') ?></td> -->
                            <td><?= e($e_item['anneeScolaire']) ?></td>
                            <td>
                                <span class="badge bg-<?= $e_item['STATUS'] === 'actif' ? 'success' : 'danger' ?>">
                                    <?= e($e_item['STATUS']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>