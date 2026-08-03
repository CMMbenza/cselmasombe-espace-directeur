<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur();
require_once __DIR__.'/../includes/get_annee_scolaire_encours.php';
require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';

// Params de filtrage
$q         = trim($_GET['q'] ?? '');
$trim      = trim($_GET['trim'] ?? '');
$classe    = (int)($_GET['classe'] ?? 0);
$annee_val = trim($_GET['annee_val'] ?? ANNEE_SCOLAIRE_LIBELLE); // Libellé par défaut (ex: "2025-2026")

// Données pour les filtres
$annees_scolaires = $pdo->query("SELECT id, annee_scolaire AS libelle FROM annee_scolaire ORDER BY id DESC")->fetchAll();

$classes = $pdo->query("
    SELECT c.id, CONCAT(c.description, ' - ', cy.description) AS description 
    FROM classe c 
    JOIN cycle cy ON cy.id = c.cycle 
    ORDER BY c.description
")->fetchAll();

$trimestres = $pdo->query("
    SELECT DISTINCT trimestre 
    FROM palmares_trimestre 
    ORDER BY trimestre
")->fetchAll(PDO::FETCH_COLUMN);

$eleves_search = $pdo->query("
    SELECT DISTINCT CONCAT(nom, ' ', postnom, ' ', prenom) AS nom_complet 
    FROM eleve 
    ORDER BY nom, postnom, prenom
")->fetchAll();

/* ================= REQUÊTE SQL AVEC RANG & ANNÉE SCOLAIRE ================= */
$where  = ["1=1"];
$params = [];

// Filtrage sur la vraie colonne : anneeScolaire
if ($annee_val !== '' && $annee_val !== '0') {
    $where[]  = "p.anneeScolaire = ?";
    $params[] = $annee_val;
}

if ($q !== '') {
    $where[]  = "(e.nom LIKE ? OR e.postnom LIKE ? OR e.prenom LIKE ? OR CONCAT(e.nom, ' ', e.postnom, ' ', e.prenom) LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($trim !== '') {
    $where[]  = "p.trimestre = ?";
    $params[] = $trim;
}

if ($classe > 0) {
    $where[]  = "p.classe_id = ?";
    $params[] = $classe;
}

// Calcul du RANG (DENSE_RANK) partitionné par classe, trimestre et anneeScolaire
$sql = "
SELECT 
    p.*,
    e.nom, e.postnom, e.prenom, e.genre,
    CONCAT(c.description, ' - ', cy.description) AS classe_nom,
    p.anneeScolaire AS annee_libelle,
    DENSE_RANK() OVER (
        PARTITION BY p.classe_id, p.trimestre, p.anneeScolaire 
        ORDER BY p.percent DESC, p.total DESC
    ) AS rang
FROM palmares_trimestre p
JOIN eleve e ON e.id = p.eleve_id
JOIN classe c ON c.id = p.classe_id
JOIN cycle cy ON cy.id = c.cycle
WHERE " . implode(" AND ", $where) . "
ORDER BY p.classe_id ASC, p.trimestre ASC, rang ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/**
 * Helper simple pour formater le rang (ex: 1er, 2ème)
 */
function formatRang(int $rang, string $genre): string {
    if ($rang === 1) {
        return ($genre === 'F') ? '1<sup>ère</sup>' : '1<sup>er</sup>';
    }
    return $rang . '<sup>ème</sup>';
}
?>

<style>
.form-check-input {
    width: 2.8rem;
    height: 1.3rem;
    cursor: pointer;
}

.badge-rang {
    font-size: 0.90rem;
    min-width: 55px;
}
</style>

<div class="container mt-3 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">📊 Palmarès & Classement des Élèves</h4>
            <small class="text-muted">Année en cours :
                <strong><?= htmlspecialchars(ANNEE_SCOLAIRE_LIBELLE) ?></strong></small>
        </div>
        <span class="badge bg-secondary"><?= count($rows) ?> enregistrement(s)</span>
    </div>

    <!-- ================= FILTRES ================= -->
    <form method="get" class="card card-body bg-light mb-3 border-0 shadow-sm">
        <div class="row g-2">

            <!-- Filtrer par Année Scolaire (sur le texte/libellé) -->
            <div class="col-md-2">
                <select name="annee_val" class="form-select fw-bold">
                    <option value="0">Toutes les années</option>
                    <?php foreach($annees_scolaires as $ans): ?>
                    <option value="<?= htmlspecialchars($ans['libelle']) ?>"
                        <?= $annee_val === $ans['libelle'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ans['libelle']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Recherche Élève -->
            <div class="col-md-3">
                <input type="text" name="q" list="liste-eleves" value="<?= htmlspecialchars($q) ?>" class="form-control"
                    placeholder="Rechercher un élève..." autocomplete="off">
                <datalist id="liste-eleves">
                    <?php foreach($eleves_search as $el): ?>
                    <option value="<?= htmlspecialchars($el['nom_complet']) ?>">
                        <?php endforeach; ?>
                </datalist>
            </div>

            <!-- Filtrer par Trimestre -->
            <div class="col-md-2">
                <select name="trim" class="form-select">
                    <option value="">Tous les trimestres</option>
                    <?php foreach($trimestres as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $trim === $t ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtrer par Classe -->
            <div class="col-md-3">
                <select name="classe" class="form-select">
                    <option value="0">Toutes les classes</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classe == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['description']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Boutons Actions -->
            <div class="col-md-2 text-end">
                <button type="submit" class="btn btn-dark w-100 mb-1">Filtrer</button>
                <a href="index.php" class="btn btn-sm btn-outline-secondary w-100">Réinitialiser</a>
            </div>
        </div>
    </form>

    <!-- ================= TABLEAU ================= -->
    <div class="card shadow-sm border-0">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover table-striped align-middle text-center m-0">
                <thead class="table-dark">
                    <tr>
                        <th class="text-start ps-3">Place</th>
                        <th class="text-start">Élève</th>
                        <th>Classe</th>
                        <th>Trimestre</th>
                        <th>Total Points</th>
                        <th>Pourcentage</th>
                        <th>Autorisé</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8" class="text-muted py-4">Aucun palmarès trouvé pour ces critères.</td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach($rows as $r): ?>
                    <tr>
                        <!-- PLACE / RANG -->
                        <td class="text-start ps-3 fw-bold">
                            <?php if ($r['rang'] == 1): ?>
                            <span class="badge bg-warning text-dark badge-rang">🥇
                                <?= formatRang((int)$r['rang'], $r['genre'] ?? 'M') ?></span>
                            <?php elseif ($r['rang'] == 2): ?>
                            <span class="badge bg-secondary badge-rang">🥈
                                <?= formatRang((int)$r['rang'], $r['genre'] ?? 'M') ?></span>
                            <?php elseif ($r['rang'] == 3): ?>
                            <span class="badge bg-danger badge-rang">🥉
                                <?= formatRang((int)$r['rang'], $r['genre'] ?? 'M') ?></span>
                            <?php else: ?>
                            <span
                                class="badge bg-light text-dark border badge-rang"><?= formatRang((int)$r['rang'], $r['genre'] ?? 'M') ?></span>
                            <?php endif; ?>
                        </td>

                        <!-- ELEVE -->
                        <td class="text-start fw-semibold">
                            <?= htmlspecialchars($r['nom'].' '.$r['postnom'].' '.$r['prenom']) ?>
                            <small class="text-muted"> (<?= htmlspecialchars($r['genre'] ?? '') ?>)</small>
                        </td>

                        <td><?= htmlspecialchars($r['classe_nom']) ?></td>

                        <td>
                            <span class="badge bg-dark"><?= htmlspecialchars($r['trimestre']) ?></span>
                        </td>

                        <td class="fw-bold text-primary">
                            <?= number_format((float)$r['total'], 2) ?>
                        </td>

                        <td>
                            <span
                                class="badge bg-<?= $r['percent'] >= 80 ? 'success' : ($r['percent'] >= 50 ? 'primary' : 'danger') ?>">
                                <?= number_format((float)$r['percent'], 2) ?>%
                            </span>
                        </td>

                        <!-- SWITCH AUTORISATION -->
                        <td>
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input toggle-switch" type="checkbox" data-id="<?= $r['id'] ?>"
                                    <?= $r['autorise'] ? 'checked' : '' ?>
                                    title="<?= $r['autorise'] ? 'Autorisé aux parents' : 'Masqué aux parents' ?>">
                            </div>
                        </td>

                        <!-- ACTIONS -->
                        <td class="text-end pe-3">
                            <button class="btn btn-sm btn-outline-primary" onclick="openView(<?= $r['id'] ?>)">
                                👁️ Voir
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================= MODAL DÉTAILS ================= -->
<?php 
$details_json = json_encode($rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); 
?>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">📋 Fiche Palmarès Élève</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="modal-content">
                <!-- Rempli en JS -->
            </div>
        </div>
    </div>
</div>

<script>
const DATA = <?= $details_json ?>;

function openView(id) {
    const d = DATA.find(x => x.id == id);
    if (!d) return;

    let statusBadge = d.autorise == 1 ?
        '<span class="badge bg-success">Autorisé (Visible)</span>' :
        '<span class="badge bg-danger">Bloqué (Masqué)</span>';

    let rangBadge = d.rang == 1 ? '🥇 1er' : (d.rang == 2 ? '🥈 2ème' : (d.rang == 3 ? '🥉 3ème' : d.rang + 'ème'));

    let html = `
        <div class="text-center mb-3">
            <h5 class="mb-0">${d.nom} ${d.postnom} ${d.prenom}</h5>
            <small class="text-muted">${d.classe_nom} — <strong>${d.trimestre}</strong> (${d.annee_libelle ?? ''})</small>
        </div>

        <div class="card bg-light border-0 p-3 mb-3">
            <div class="row text-center">
                <div class="col-4 border-end">
                    <small class="text-muted">Place / Rang</small>
                    <div class="fs-5 fw-bold text-dark">${rangBadge}</div>
                </div>
                <div class="col-4 border-end">
                    <small class="text-muted">Total Points</small>
                    <div class="fs-5 fw-bold text-primary">${parseFloat(d.total).toFixed(2)}</div>
                </div>
                <div class="col-4">
                    <small class="text-muted">Pourcentage</small>
                    <div class="fs-5 fw-bold ${d.percent >= 50 ? 'text-success' : 'text-danger'}">${parseFloat(d.percent).toFixed(2)}%</div>
                </div>
            </div>
        </div>

        <table class="table table-sm table-bordered mb-3">
            <thead class="table-light">
                <tr>
                    <th>Branche / Domaine</th>
                    <th class="text-center">Obtenu</th>
                    <th class="text-center">Maximum</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Langues</td>
                    <td class="text-center fw-bold">${d.lang ?? 0}</td>
                    <td class="text-center text-muted">${d.max_lang ?? '-'}</td>
                </tr>
                <tr>
                    <td>Mathématiques</td>
                    <td class="text-center fw-bold">${d.math ?? 0}</td>
                    <td class="text-center text-muted">${d.max_math ?? '-'}</td>
                </tr>
                <tr>
                    <td>Culture Générale</td>
                    <td class="text-center fw-bold">${d.cult ?? 0}</td>
                    <td class="text-center text-muted">${d.max_cult ?? '-'}</td>
                </tr>
            </tbody>
        </table>

        <div class="mb-2">
            <strong>Observation :</strong> <p class="text-muted mb-1">${d.obs && d.obs.trim() !== '' ? d.obs : 'Aucune observation'}</p>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
            <strong>Accès Parents :</strong>
            <div>${statusBadge}</div>
        </div>
    `;

    document.getElementById('modal-content').innerHTML = html;
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

// Gestion de la modification en AJAX via Switch
document.querySelectorAll('.toggle-switch').forEach(sw => {
    sw.addEventListener('change', async function() {
        let id = this.dataset.id;

        try {
            let response = await fetch('toggle_palmares.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + encodeURIComponent(id)
            });

            let data = await response.json();

            if (!data.success) {
                alert(data.message || 'Erreur lors du changement de statut.');
                this.checked = !this.checked;
            }
        } catch (e) {
            console.error(e);
            alert('Erreur serveur lors de la mise à jour.');
            this.checked = !this.checked;
        }
    });
});
</script>

<?php require_once __DIR__.'/../layout/footer.php'; ?>