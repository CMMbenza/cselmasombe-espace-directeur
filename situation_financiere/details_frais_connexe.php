<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$selectedAnnee = trim($_GET['anneeScolaire'] ?? '');
$search = trim($_GET['q'] ?? '');

$anneesStmt = $pdo->query("SELECT DISTINCT anneeScolaire FROM paiement_divers WHERE anneeScolaire IS NOT NULL AND anneeScolaire != '' ORDER BY anneeScolaire DESC");
$listeAnnees = $anneesStmt->fetchAll(PDO::FETCH_COLUMN);

$where = ["1=1"];
$params = [];

if ($selectedAnnee !== '') {
    $where[] = "anneeScolaire = :annee";
    $params[':annee'] = $selectedAnnee;
}
if ($search !== '') {
    $where[] = "(type_frais LIKE :q OR menage LIKE :q OR observation LIKE :q)";
    $params[':q'] = "%$search%";
}

$whereSql = implode(' AND ', $where);

// Synthèse KPI
$stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(montantAPayer),0) as attendu, COALESCE(SUM(montantPayer),0) as percu, COALESCE(SUM(resteAPayer),0) as reste FROM paiement_divers WHERE $whereSql");
$stmtTotal->execute($params);
$totaux = $stmtTotal->fetch(PDO::FETCH_ASSOC);

// Liste frais connexes
$stmtList = $pdo->prepare("SELECT * FROM paiement_divers WHERE $whereSql ORDER BY id DESC");
$stmtList->execute($params);
$paiements = $stmtList->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid my-4">
    <!-- BARRE ACTION -->
    <div class="d-flex justify-content-between align-items-center p-3 mb-4 bg-light border rounded no-print">
        <div>
            <a href="index.php" class="btn btn-sm btn-outline-secondary me-2">⬅ Retour au Bilan Général</a>
            <h4 class="fw-bold text-info d-inline-block text-dark m-0">🎨 Liste Détaillée - Frais Connexes</h4>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-select form-select-sm" placeholder="Type, ménage...">
            <select name="anneeScolaire" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">-- Toutes les années --</option>
                <?php foreach ($listeAnnees as $annee): ?>
                    <option value="<?= htmlspecialchars($annee) ?>" <?= $selectedAnnee === $annee ? 'selected' : '' ?>><?= htmlspecialchars($annee) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-dark">Filtrer</button>
            <button type="button" onclick="window.print()" class="btn btn-sm btn-primary">🖨️ Imprimer</button>
        </form>
    </div>

    <!-- KPI HAUT -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-4 border-info">
                <div class="card-body py-2">
                    <small class="text-muted fw-bold">TOTAL ATTENDU</small>
                    <h5 class="fw-bold mb-0 text-dark"><?= number_format((float)$totaux['attendu'], 2, ',', ' ') ?> $</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-4 border-success">
                <div class="card-body py-2">
                    <small class="text-muted fw-bold">TOTAL PERÇU</small>
                    <h5 class="fw-bold mb-0 text-success"><?= number_format((float)$totaux['percu'], 2, ',', ' ') ?> $</h5>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-start border-4 border-warning">
                <div class="card-body py-2">
                    <small class="text-muted fw-bold">RESTE À RECOUVRER</small>
                    <h5 class="fw-bold mb-0 text-danger"><?= number_format((float)$totaux['reste'], 2, ',', ' ') ?> $</h5>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLEAU -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-info">
                        <tr>
                            <th>#ID</th>
                            <th>Ménage</th>
                            <th>Type de Frais</th>
                            <th>Année</th>
                            <th class="text-end">Attendu</th>
                            <th class="text-end">Perçu</th>
                            <th class="text-end">Reste</th>
                            <th>Agent</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paiements)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Aucun enregistrement trouvé.</td></tr>
                        <?php else: ?>
                            <?php foreach ($paiements as $p): ?>
                                <tr>
                                    <td>#<?= $p['id'] ?></td>
                                    <td class="fw-bold">Ménage N° <?= htmlspecialchars((string)$p['menage']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($p['type_frais']) ?></span></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['anneeScolaire'] ?? '-') ?></span></td>
                                    <td class="text-end"><?= number_format((float)$p['montantAPayer'], 2, ',', ' ') ?> $</td>
                                    <td class="text-end text-success fw-bold"><?= number_format((float)$p['montantPayer'], 2, ',', ' ') ?> $</td>
                                    <td class="text-end text-danger fw-bold"><?= number_format((float)$p['resteAPayer'], 2, ',', ' ') ?> $</td>
                                    <td class="small"><?= htmlspecialchars($p['createdby'] ?? '-') ?></td>
                                    <td class="small"><?= date('d/m/Y H:i', strtotime($p['dateCreated'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>@media print { .no-print { display: none !important; } }</style>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>