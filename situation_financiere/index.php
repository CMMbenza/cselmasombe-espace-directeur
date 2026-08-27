<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

// Filtre par année scolaire
$selectedAnnee = trim($_GET['anneeScolaire'] ?? '');

// --- 1. ANNÉES SCOLAIRES DISPONIBLES ---
$anneesStmt = $pdo->query("
    SELECT DISTINCT anneeScolaire FROM (
        SELECT anneeScolaire FROM paiement WHERE anneeScolaire IS NOT NULL AND anneeScolaire != ''
        UNION
        SELECT anneeScolaire FROM paiement_divers WHERE anneeScolaire IS NOT NULL AND anneeScolaire != ''
        UNION
        SELECT anneeScolaire FROM depenses WHERE anneeScolaire IS NOT NULL AND anneeScolaire != ''
    ) AS combined_annees 
    ORDER BY anneeScolaire DESC
");
$listeAnnees = $anneesStmt->fetchAll(PDO::FETCH_COLUMN);

$whereClause = $selectedAnnee !== '' ? "WHERE anneeScolaire = :annee" : "";
$whereDepenseClass = $selectedAnnee !== '' ? "AND anneeScolaire = :annee" : "";

// --- 2. FRAIS SCOLAIRES ---
$stmtPaiement = $pdo->prepare("
    SELECT 
        COALESCE(SUM(montantAPayer), 0) AS attendu,
        COALESCE(SUM(montantPayer), 0) AS percu,
        COALESCE(SUM(resteAPayer), 0) AS reste
    FROM paiement $whereClause
");
if ($selectedAnnee !== '') { $stmtPaiement->bindValue(':annee', $selectedAnnee); }
$stmtPaiement->execute();
$fraisScolaires = $stmtPaiement->fetch(PDO::FETCH_ASSOC);

// Dépenses Scolaires
$stmtDepScolaire = $pdo->prepare("
    SELECT COALESCE(SUM(montant), 0) 
    FROM depenses 
    WHERE LOWER(reference) LIKE '%scolaire%' $whereDepenseClass
");
if ($selectedAnnee !== '') { $stmtDepScolaire->bindValue(':annee', $selectedAnnee); }
$stmtDepScolaire->execute();
$depensesScolaires = (float)$stmtDepScolaire->fetchColumn();

// --- 3. FRAIS CONNEXES ---
$stmtDivers = $pdo->prepare("
    SELECT 
        COALESCE(SUM(montantAPayer), 0) AS attendu,
        COALESCE(SUM(montantPayer), 0) AS percu,
        COALESCE(SUM(resteAPayer), 0) AS reste
    FROM paiement_divers $whereClause
");
if ($selectedAnnee !== '') { $stmtDivers->bindValue(':annee', $selectedAnnee); }
$stmtDivers->execute();
$fraisConnexes = $stmtDivers->fetch(PDO::FETCH_ASSOC);

// Dépenses Connexes
$stmtDepConnexe = $pdo->prepare("
    SELECT COALESCE(SUM(montant), 0) 
    FROM depenses 
    WHERE LOWER(reference) LIKE '%connexe%' $whereDepenseClass
");
if ($selectedAnnee !== '') { $stmtDepConnexe->bindValue(':annee', $selectedAnnee); }
$stmtDepConnexe->execute();
$depensesConnexes = (float)$stmtDepConnexe->fetchColumn();

// Détail par type de frais connexe
$stmtGroupConnexes = $pdo->prepare("
    SELECT 
        type_frais, 
        SUM(montantAPayer) AS attendu, 
        SUM(montantPayer) AS percu, 
        SUM(resteAPayer) AS reste
    FROM paiement_divers 
    $whereClause
    GROUP BY type_frais 
    ORDER BY percu DESC
");
if ($selectedAnnee !== '') { $stmtGroupConnexes->bindValue(':annee', $selectedAnnee); }
$stmtGroupConnexes->execute();
$connexesRubriques = $stmtGroupConnexes->fetchAll(PDO::FETCH_ASSOC);

// --- 4. DÉPENSES GLOBALES ---
$stmtDepensesTotal = $pdo->prepare("
    SELECT COALESCE(SUM(montant), 0) 
    FROM depenses $whereClause
");
if ($selectedAnnee !== '') { $stmtDepensesTotal->bindValue(':annee', $selectedAnnee); }
$stmtDepensesTotal->execute();
$totalDepensesGlobal = (float)$stmtDepensesTotal->fetchColumn();

// --- 5. SYNTHÈSE TOTALE EN HAUT ---
$totalAttenduGlobal = (float)$fraisScolaires['attendu'] + (float)$fraisConnexes['attendu'];
$totalPercuGlobal   = (float)$fraisScolaires['percu'] + (float)$fraisConnexes['percu'];
$totalResteGlobal   = (float)$fraisScolaires['reste'] + (float)$fraisConnexes['reste'];
$soldeDisponible    = $totalPercuGlobal - $totalDepensesGlobal;

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-3">

    <!-- BARRE D'ACTION ET FILTRE -->
    <div class="card border-0 shadow-sm mb-4 no-print">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 py-3">
            <div>
                <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary p-2 rounded-3">💼</span>
                    Situation Financière
                </h4>
                <small class="text-muted">Vue d'ensemble des entrées, sorties et créances</small>
            </div>

            <!-- <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white fw-medium">Année Scolaire</span>
                    <select name="anneeScolaire" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Toutes les années --</option>
                        <?php foreach ($listeAnnees as $annee): ?>
                        <option value="<?= htmlspecialchars($annee) ?>"
                            <?= $selectedAnnee === $annee ? 'selected' : '' ?>>
                            <?= htmlspecialchars($annee) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selectedAnnee !== ''): ?>
                <a href="index.php" class="btn btn-sm btn-outline-secondary">Réinitialiser</a>
                <?php endif; ?>

                <button type="button" onclick="window.print()"
                    class="btn btn-sm btn-primary shadow-sm d-flex align-items-center gap-1">
                    <span>🖨️</span> Imprimer
                </button>
            </form> -->
        </div>
    </div>

    <!-- =================================================== -->
    <!-- LES BLOCS DE SYNTHÈSE FINANCIÈRE HAUT DE PAGE (KPI) -->
    <!-- =================================================== -->
    <div class="row g-3 mb-4">

        <!-- BLOC 1 : FRAIS SCOLAIRES -->
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-primary">
                <div class="card-body p-3">
                    <span class="text-uppercase text-muted fw-bold extra-small d-block mb-1">🏫 Frais Scolaires</span>
                    <div class="d-flex justify-content-between text-muted small my-1">
                        <span>Attendu:</span>
                        <span class="fw-medium"><?= number_format((float)$fraisScolaires['attendu'], 2, ',', ' ') ?>
                            $</span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold text-success border-top pt-1 mt-1">
                        <span>Perçu:</span>
                        <span><?= number_format((float)$fraisScolaires['percu'], 2, ',', ' ') ?> $</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- BLOC 2 : FRAIS CONNEXES -->
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-info">
                <div class="card-body p-3">
                    <span class="text-uppercase text-muted fw-bold extra-small d-block mb-1">🎨 Frais Connexes</span>
                    <div class="d-flex justify-content-between text-muted small my-1">
                        <span>Attendu:</span>
                        <span class="fw-medium"><?= number_format((float)$fraisConnexes['attendu'], 2, ',', ' ') ?>
                            $</span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold text-info border-top pt-1 mt-1">
                        <span>Perçu:</span>
                        <span><?= number_format((float)$fraisConnexes['percu'], 2, ',', ' ') ?> $</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- BLOC 3 : DÉPENSES -->
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-danger">
                <div class="card-body p-3">
                    <span class="text-uppercase text-muted fw-bold extra-small d-block mb-1">💸 Sorties Totales</span>
                    <span class="text-muted small d-block">Dépenses globales</span>
                    <h5 class="fw-bold text-danger mb-0 mt-2"><?= number_format($totalDepensesGlobal, 2, ',', ' ') ?> $
                    </h5>
                </div>
            </div>
        </div>

        <!-- BLOC 4 : SOMME TOTALE PERÇUE -->
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-success">
                <div class="card-body p-3">
                    <span class="text-uppercase text-muted fw-bold extra-small d-block mb-1">💰 Recettes Totales</span>
                    <span class="text-muted small d-block">Scolaire + Connexe</span>
                    <h5 class="fw-bold text-success mb-0 mt-2"><?= number_format($totalPercuGlobal, 2, ',', ' ') ?> $
                    </h5>
                </div>
            </div>
        </div>

        <!-- BLOC 5 : RESTE TOTAL À RECOUVRER -->
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm h-100 border-top border-4 border-warning">
                <div class="card-body p-3">
                    <span class="text-uppercase text-muted fw-bold extra-small d-block mb-1">⚠️ Créances Totales</span>
                    <span class="text-muted small d-block">Reste Général</span>
                    <h5 class="fw-bold text-warning-emphasis mb-0 mt-2">
                        <?= number_format($totalResteGlobal, 2, ',', ' ') ?> $</h5>
                </div>
            </div>
        </div>

        <!-- BLOC 6 : DISPONIBLE ACTUEL EN CAISSE -->
        <div class="col-sm-6 col-md-4 col-xl-2">
            <div
                class="card border-0 shadow-sm h-100 border-top border-4 <?= $soldeDisponible >= 0 ? 'border-dark' : 'border-danger' ?> bg-light-subtle">
                <div class="card-body p-3">
                    <span class="text-uppercase text-muted fw-bold extra-small d-block mb-1">🏦 Solde Caisse</span>
                    <span class="text-muted small d-block">Perçu − Dépensé</span>
                    <h5 class="fw-bold <?= $soldeDisponible >= 0 ? 'text-dark' : 'text-danger' ?> mb-0 mt-2">
                        <?= number_format($soldeDisponible, 2, ',', ' ') ?> $
                    </h5>
                </div>
            </div>
        </div>

    </div>

    <!-- =================================================== -->
    <!-- DÉTAILS COMPLETS PAR RUBRIQUE ET PAR SECTION        -->
    <!-- =================================================== -->
    <div class="row g-4">

        <!-- DÉTAILS FRAIS SCOLAIRES -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold d-flex align-items-center gap-2">
                        🏫 DÉTAILS FRAIS SCOLAIRES
                    </h6>
                    <!-- <a href="details_frais_scolaire.php"
                        class="btn btn-sm btn-light text-primary fw-bold no-print shadow-sm">
                        Voir la liste ➔
                    </a> -->
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm align-middle mb-3">
                        <tbody>
                            <tr>
                                <td class="text-muted py-2">Montant Total Attendu</td>
                                <td class="text-end fw-bold py-2">
                                    <?= number_format((float)$fraisScolaires['attendu'], 2, ',', ' ') ?> $</td>
                            </tr>
                            <tr class="table-success-subtle">
                                <td class="fw-medium text-success py-2">Montant Réellement Perçu</td>
                                <td class="text-end fw-bold text-success py-2">
                                    <?= number_format((float)$fraisScolaires['percu'], 2, ',', ' ') ?> $</td>
                            </tr>
                            <tr class="table-warning-subtle">
                                <td class="fw-medium text-danger py-2">Reste à Payer (Créances)</td>
                                <td class="text-end fw-bold text-danger py-2">
                                    <?= number_format((float)$fraisScolaires['reste'], 2, ',', ' ') ?> $</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center border">
                        <span class="small fw-semibold text-secondary">Dépenses imputées aux Frais Scolaires :</span>
                        <span class="badge bg-danger-subtle text-danger fs-6 fw-bold border border-danger-subtle">
                            <?= number_format($depensesScolaires, 2, ',', ' ') ?> $
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- DÉTAILS FRAIS CONNEXES -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-info text-dark py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold d-flex align-items-center gap-2">
                        🎨 DÉTAILS FRAIS CONNEXES
                    </h6>
                    <!-- <a href="details_frais_connexe.php" class="btn btn-sm btn-dark fw-bold no-print shadow-sm">
                        Voir la liste ➔
                    </a> -->
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive mb-3" style="max-height: 185px; overflow-y: auto;">
                        <table class="table table-sm align-middle mb-3">
                            <tbody>
                                <?php if (empty($connexesRubriques)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Aucun frais connexe enregistré.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($connexesRubriques as $fc): ?>
                                <tr>
                                    <td class="text-muted py-2">Montant Total Attendu</td>
                                    <td class="text-end fw-bold py-2">
                                        <?= number_format((float)$fraisScolaires['attendu'], 2, ',', ' ') ?> $</td>
                                </tr>
                                <tr class="table-success-subtle">
                                    <td class="fw-medium text-success py-2">Montant Réellement Perçu</td>
                                    <td class="text-end text-success fw-bold">
                                        <?= number_format((float)$fc['percu'], 2, ',', ' ') ?> $</td>
                                </tr>
                                <tr class="table-warning-subtle">
                                    <td class="fw-medium text-danger py-2">Reste à Payer (Créances)</td>
                                    <td class="text-end fw-bold text-danger py-2">
                                        <?= number_format((float)$fc['reste'], 2, ',', ' ') ?> $</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center border">
                        <span class="small fw-semibold text-secondary">Dépenses imputées aux Frais Connexes :</span>
                        <span class="badge bg-danger-subtle text-danger fs-6 fw-bold border border-danger-subtle">
                            <?= number_format($depensesConnexes, 2, ',', ' ') ?> $
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION JOURNAL DES DÉPENSES -->
        <div class="col-12">
            <div class="card border-0 shadow-sm border-start border-4 border-danger">
                <div
                    class="card-body p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="badge bg-danger p-1">💸</span>
                            <h6 class="m-0 fw-bold text-dark">RÉCAPITULATIF TOTAL DES DÉPENSES</h6>
                        </div>
                        <small class="text-muted">Cumul de toutes les sorties effectuées (Scolaires + Connexes +
                            Autre)</small>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <h4 class="text-danger fw-bold m-0"><?= number_format($totalDepensesGlobal, 2, ',', ' ') ?> $
                        </h4>
                        <!-- <a href="details_depenses.php" class="btn btn-outline-danger btn-sm fw-bold no-print">
                            Consulter le journal ➔
                        </a> -->
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<style>
.extra-small {
    font-size: 0.72rem;
}

@media print {
    .no-print {
        display: none !important;
    }

    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
}
</style>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>