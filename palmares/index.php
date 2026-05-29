<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur();
require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';

$q = trim($_GET['q'] ?? '');
$trim = trim($_GET['trim'] ?? '');
$classe = (int)($_GET['classe'] ?? 0);
$min = (float)($_GET['min'] ?? 0);
$max = (float)($_GET['max'] ?? 100);

$classes = $pdo->query("SELECT id, description FROM classe ORDER BY description")->fetchAll();

if (isset($_GET['toggle'])) {

    $id = (int)$_GET['toggle'];

    $stmt = $pdo->prepare("
        UPDATE palmares_trimestre
        SET autorise = IF(autorise = 1, 0, 1)
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    // header("Location: ../palmares/");
    // exit;
}

/* ================= QUERY ================= */
$where = ["1=1"];
$params = [];

if ($q !== '') {
    $where[] = "(e.nom LIKE ? OR e.postnom LIKE ? OR e.prenom LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($trim !== '') {
    $where[] = "p.trimestre = ?";
    $params[] = $trim;
}

if ($classe > 0) {
    $where[] = "p.classe_id = ?";
    $params[] = $classe;
}

$where[] = "p.percent BETWEEN ? AND ?";
$params[] = $min;
$params[] = $max;

$sql = "
SELECT 
    p.*,
    e.nom, e.postnom, e.prenom, e.genre,
    CONCAT(c.description, ' - ', cy.description) AS classe
FROM palmares_trimestre p
JOIN eleve e ON e.id = p.eleve_id
JOIN classe c ON c.id = p.classe_id
JOIN cycle cy ON cy.id = c.cycle
WHERE ".implode(" AND ", $where)."
ORDER BY p.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<style>
/* .form-check-input {
    width: 3rem;
    height: 1.4rem;
    cursor: pointer;
} */

.form-check-input {
    width: 3rem;
    height: 1.4rem;
    cursor: pointer;
}
</style>

<div class="container mt-3">

    <h4>📊 Historique Palmarès</h4>

    <?php

/* ================= LISTE ELEVES AUTOCOMPLETE ================= */

$eleves_search = $pdo->query("
    SELECT DISTINCT
        CONCAT(nom) AS nom_complet
    FROM eleve
    ORDER BY nom, postnom, prenom
")->fetchAll();

/* ================= TRIMESTRES DYNAMIQUES ================= */

$trimestres = $pdo->query("
    SELECT DISTINCT trimestre
    FROM palmares_trimestre
    ORDER BY trimestre
")->fetchAll(PDO::FETCH_COLUMN);
?>

    <!-- ================= FILTRES ================= -->
    <form method="get" class="row g-2 mb-3">

        <div class="col-md-3">

            <input type="text" name="q" list="liste-eleves" value="<?= htmlspecialchars($q) ?>" class="form-control"
                placeholder="Rechercher un élève..." autocomplete="off">

            <datalist id="liste-eleves">

                <?php foreach($eleves_search as $el): ?>

                <option value="<?= htmlspecialchars($el['nom_complet']) ?>">

                    <?php endforeach; ?>

            </datalist>

        </div>

        <div class="col-md-2">

            <select name="trim" class="form-select">

                <option value="">
                    Tous trimestres
                </option>

                <?php foreach($trimestres as $t): ?>

                <option value="<?= htmlspecialchars($t) ?>" <?= $trim === $t ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t) ?>
                </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="col-md-3">
            <select name="classe" class="form-select">
                <option value="0">Toutes classes</option>
                <?php foreach($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $classe==$c['id']?'selected':'' ?>>
                    <?= htmlspecialchars($c['description']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3 text-end">
            <button class="btn btn-dark btn-md">Filtrer</button>
            <a href="../palmares/" class="btn btn-danger btn-md">Réinitialisation</a>
        </div>

        <!-- <div class="col-md-2">
            <input type="number" step="0.01" name="min" value="<?= $min ?>" class="form-control" placeholder="Min %">
        </div>

        <div class="col-md-2">
            <input type="number" step="0.01" name="max" value="<?= $max ?>" class="form-control" placeholder="Max %">
        </div> -->



    </form>

    <!-- ================= TABLE ================= -->
    <div class="card">
        <div class="card-body table-responsive">

            <table class="table table-hover table-sm text-center align-middle">

                <thead class="table-light">
                    <tr>
                        <th>Élèves</th>
                        <th>Classe</th>
                        <th>Trimestre</th>
                        <th>Total</th>
                        <th>%</th>
                        <!-- <th>Date</th> -->
                        <th></th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach($rows as $r): ?>

                    <tr>
                        <td><?= htmlspecialchars($r['nom'].' '.$r['postnom'].' '.$r['prenom'].' '.$r['genre']) ?></td>
                        <td><?= htmlspecialchars($r['classe']) ?></td>
                        <td><span class="badge bg-dark"><?= $r['trimestre'] ?></span></td>

                        <td class="fw-bold text-primary">
                            <?= number_format((float)$r['total'], 2) ?>
                        </td>

                        <td>
                            <span
                                class="badge bg-<?= $r['percent']>=80?'success':($r['percent']>=60?'primary':'danger') ?>">
                                <?= number_format((float)$r['percent'],2) ?>%
                            </span>
                        </td>

                        <!-- <td><?= $r['created_at'] ?></td> -->
                        <td class="text-nowrap">

                            <!-- BTN VOIR -->
                            <button class="btn btn-sm btn-primary" onclick="openView(<?= $r['id'] ?>)">
                                Voir
                            </button>

                            <!-- SWITCH -->
                            <div class="form-check form-switch d-inline-block ms-2">

                                <input class="form-check-input shadow-none toggle-switch" type="checkbox"
                                    data-id="<?= $r['id'] ?>" <?= $r['autorise'] ? 'checked' : '' ?>>

                            </div>
                        </td>
                    </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>
    </div>

</div>
<?php $details = $pdo->query("
SELECT p.*, e.nom, e.postnom, e.prenom, CONCAT(c.description,' ',cy.description) AS classe
FROM palmares_trimestre p
JOIN eleve e ON e.id = p.eleve_id
JOIN classe c ON c.id = p.classe_id
JOIN cycle cy ON cy.id = c.cycle
")->fetchAll();

$details_json = json_encode($details); ?>

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Détails Palmarès</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div id="modal-content"></div>

            </div>

        </div>
    </div>
</div>

<script>
const DATA = <?= $details_json ?>;

function openView(id) {

    const d = DATA.find(x => x.id == id);
    if (!d) return;

    let html = `
        <div class="mb-2">
            <strong>Élève :</strong> ${d.nom} ${d.postnom} ${d.prenom}
        </div>

        <div class="mb-2">
            <strong>Classe :</strong> ${d.classe}
        </div>

        <div class="row">
            <div class="col">
                <p>Lang : ${d.lang} / ${d.max_lang}</p>
                <p>Math : ${d.math} / ${d.max_math}</p>
                <p>Cult : ${d.cult} / ${d.max_cult}</p>
            </div>

            <div class="col">
                <p><strong>Total :</strong> ${d.total}</p>
                <p><strong>% :</strong> ${d.percent}%</p>
            </div>
        </div>

        <hr>

        <p><strong>Observation :</strong> ${d.obs ?? '-'}</p>

        <p>
            <strong>Status :</strong>
            ${d.autorise == 1 ? 
                '<span class="badge bg-success">Autorisé</span>' :
                '<span class="badge bg-danger">Bloqué</span>'
            }
        </p>
    `;

    document.getElementById('modal-content').innerHTML = html;

    new bootstrap.Modal(document.getElementById('viewModal')).show();
}
</script>

<script>
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

            let text = await response.text();

            console.log(text);

            let data = JSON.parse(text);

            if (!data.success) {

                alert(data.message || 'Erreur.');

                this.checked = !this.checked;
            }

        } catch (e) {

            console.error(e);

            alert('Erreur serveur AJAX.');

            this.checked = !this.checked;
        }

    });

});
</script>

<?php require_once __DIR__.'/../layout/footer.php'; ?>