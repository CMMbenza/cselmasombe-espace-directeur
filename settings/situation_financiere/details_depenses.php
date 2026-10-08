<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$selectedAnnee = trim($_GET['anneeScolaire'] ?? '');
$search = trim($_GET['q'] ?? '');

$anneesStmt = $pdo->query("SELECT DISTINCT anneeScolaire FROM depenses WHERE anneeScolaire IS NOT NULL AND anneeScolaire != '' ORDER BY anneeScolaire DESC");
$listeAnnees = $anneesStmt->fetchAll(PDO::FETCH_COLUMN);

$where = ["1=1"];
$params = [];

if ($selectedAnnee !== '') {
    $where[] = "anneeScolaire = :annee";
    $params[':annee'] = $selectedAnnee;
}
if ($search !== '') {
    $where[] = "(beneficiaire LIKE :q OR reference LIKE :q OR description LIKE :q)";
    $params[':q'] = "%$search%";
}

$whereSql = implode(' AND ', $where);

// Somme des dépenses
$stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(montant),0) as total FROM depenses WHERE $whereSql");
$stmtTotal->execute($params);
$totalDepenses = (float)$stmtTotal->fetchColumn();

// Dépenses filtrées
$stmtList = $pdo->prepare("SELECT * FROM depenses WHERE $whereSql ORDER BY id DESC");
$stmtList->execute($params);
$depenses = $stmtList->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid my-4">
    <!-- BARRE ACTION -->
    <div class="d-flex justify-content-between align-items-center p-3 mb-4 bg-light border rounded no-print">
        <div>
            <a href="index.php" class="btn btn-sm btn-outline-secondary me-2">⬅ Retour au Bilan Général</a>
            <h4 class="fw-bold text-danger d-inline-block m-0">💸 Journal Général des Dépenses</h4>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-select form-select-sm" placeholder="Bénéficiaire, motif...">
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
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm border-start border-4 border-danger">
                <div class="card-body py-2">
                    <small class="text-muted fw-bold">TOTAL DES DÉPENSES EFFECTUÉES</small>
                    <h4 class="fw-bold mb-0 text-danger"><?= number_format($totalDepenses, 2, ',', ' ') ?> $</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLEAU -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-danger">
                        <tr>
                            <th>#ID</th>
                            <th>Bénéficiaire</th>
                            <th>Référence</th>
                            <th>N° Réf</th>
                            <th>Description / Motif</th>
                            <th class="text-end">Montant</th>
                            <th>Année</th>
                            <th>Agent</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($depenses)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-4">Aucune dépense enregistrée.</td></tr>
                        <?php else: ?>
                            <?php foreach ($depenses as $d): ?>
                                <tr>
                                    <td>#<?= $d['id'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($d['beneficiaire']) ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($d['reference']) ?></span></td>
                                    <td><?= $d['numero_reference'] ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($d['description']) ?></td>
                                    <td class="text-end text-danger fw-bold"><?= number_format((float)$d['montant'], 2, ',', ' ') ?> $</td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($d['anneeScolaire']) ?></span></td>
                                    <td class="small"><?= htmlspecialchars($d['createdby']) ?></td>
                                    <td class="small"><?= date('d/m/Y', strtotime($d['dateCreaty'])) ?></td>
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