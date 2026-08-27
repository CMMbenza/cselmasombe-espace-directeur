<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$msgSuccess = '';
$msgError = '';

// --- 1. TRAITEMENT DES ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_prevision') {
        $prevision_detail_id = !empty($_POST['prevision_detail_id']) ? (int)$_POST['prevision_detail_id'] : null;
        $cours_id            = (int)($_POST['cours_id'] ?? 0);
        $enseignant_id       = (int)($_POST['enseignant_id'] ?? 0);
        $anneeScolaire       = trim($_POST['anneeScolaire'] ?? '');
        
        $periode            = trim($_POST['periode'] ?? '1ère Période');
        $mois               = trim($_POST['mois'] ?? '');
        $semaine_libelle    = trim($_POST['semaine_libelle'] ?? '');
        $savoirs_essentiels = trim($_POST['savoirs_essentiels'] ?? '');
        $observation        = trim($_POST['observation'] ?? '');
        $activites          = trim($_POST['activites'] ?? 'A1');

        if ($cours_id > 0 && $enseignant_id > 0 && $savoirs_essentiels !== '') {
            try {
                $pdo->beginTransaction();

                // 1. Récupérer ou créer la prévision principale
                $stmtPM = $pdo->prepare("SELECT id FROM prevision_matiere WHERE enseignant_id = :prof AND cours_id = :cours AND anneeScolaire = :annee LIMIT 1");
                $stmtPM->execute([':prof' => $enseignant_id, ':cours' => $cours_id, ':annee' => $anneeScolaire]);
                $prevision_id = $stmtPM->fetchColumn();

                if (!$prevision_id) {
                    $stmtInsPM = $pdo->prepare("INSERT INTO prevision_matiere (enseignant_id, cours_id, anneeScolaire, created_at) VALUES (:prof, :cours, :annee, NOW())");
                    $stmtInsPM->execute([':prof' => $enseignant_id, ':cours' => $cours_id, ':annee' => $anneeScolaire]);
                    $prevision_id = (int)$pdo->lastInsertId();
                }

                // 2. Génération automatique du code (Ex: C1.1, C1.2...)
                preg_match('/\d+/', $periode, $matches);
                $numPeriode = $matches[0] ?? '1';

                if ($prevision_detail_id) {
                    // Conserver le code existant ou recalculer
                    $code = trim($_POST['code'] ?? "C{$numPeriode}.1");

                    $stmtDet = $pdo->prepare("
                        UPDATE prevision_detail 
                        SET periode = :periode, mois = :mois, semaine_libelle = :semaine, 
                            savoirs_essentiels = :savoirs, code = :code, observation = :obs, activites = :act 
                        WHERE id = :id
                    ");
                    $stmtDet->execute([
                        ':periode' => $periode, ':mois' => $mois, ':semaine' => $semaine_libelle,
                        ':savoirs' => $savoirs_essentiels, ':code' => $code, ':obs' => $observation,
                        ':act' => $activites, ':id' => $prevision_detail_id
                    ]);
                    $msgSuccess = "Prévision mise à jour avec succès.";
                } else {
                    // Compter les items pour incrémenter (C1.1, C1.2...)
                    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM prevision_detail WHERE prevision_id = :pid AND periode = :periode");
                    $stmtCount->execute([':pid' => $prevision_id, ':periode' => $periode]);
                    $count = (int)$stmtCount->fetchColumn() + 1;
                    $code = "C{$numPeriode}.{$count}";

                    $stmtDet = $pdo->prepare("
                        INSERT INTO prevision_detail (prevision_id, periode, mois, semaine_libelle, savoirs_essentiels, code, observation, activites) 
                        VALUES (:pid, :periode, :mois, :semaine, :savoirs, :code, :obs, :act)
                    ");
                    $stmtDet->execute([
                        ':pid' => $prevision_id, ':periode' => $periode, ':mois' => $mois, 
                        ':semaine' => $semaine_libelle, ':savoirs' => $savoirs_essentiels, 
                        ':code' => $code, ':obs' => $observation, ':act' => $activites
                    ]);
                    $msgSuccess = "Nouvelle prévision ajoutée avec succès.";
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $msgError = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        } else {
            $msgError = "Veuillez remplir tous les champs requis.";
        }
    }

    if ($action === 'delete_prevision') {
        $detail_id = (int)($_POST['id'] ?? 0);
        if ($detail_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM prevision_detail WHERE id = :id");
            $stmt->execute([':id' => $detail_id]);
            $msgSuccess = "La prévision a été supprimée.";
        }
    }
}

// --- 2. RÉCUPÉRATION DES DONNÉES DE BASE ---
$anneesStmt = $pdo->query("SELECT DISTINCT anneeScolaire FROM classe WHERE anneeScolaire IS NOT NULL AND anneeScolaire != '' ORDER BY anneeScolaire DESC");
$listeAnnees = $anneesStmt->fetchAll(PDO::FETCH_COLUMN);

$selectedClasseId = (int)($_GET['classe_id'] ?? 0);
$selectedAnnee    = trim($_GET['anneeScolaire'] ?? ($listeAnnees[0] ?? ''));

$classesStmt = $pdo->query("SELECT classe.id, CONCAT(classe.description ,' ', cy.description) AS description FROM classe 
JOIN cycle cy ON cy.id = classe.cycle ORDER BY description ASC");
$listeClasses = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

if ($selectedClasseId === 0 && !empty($listeClasses)) {
    $selectedClasseId = (int)$listeClasses[0]['id'];
}

$profsStmt = $pdo->query("SELECT id, nom, postnom, prenom FROM agent ORDER BY nom ASC");
$listeProfesseurs = $profsStmt->fetchAll(PDO::FETCH_ASSOC);

$coursStmt = $pdo->prepare("SELECT id, intitule FROM cours WHERE classe_id = :cid ORDER BY intitule ASC");
$coursStmt->execute([':cid' => $selectedClasseId]);
$listeCours = $coursStmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. LISTE DES PRÉVISIONS ---
$previsions = [];
if ($selectedClasseId > 0) {
    $sql = "
        SELECT 
            pd.id AS detail_id,
            pd.periode, pd.mois, pd.semaine_libelle, pd.savoirs_essentiels, pd.code, pd.observation, pd.activites,
            pm.id AS prevision_id, pm.enseignant_id, pm.cours_id, pm.anneeScolaire,
            c.intitule AS cours_nom,
            cl.description AS classe_nom,
            CONCAT(ag.nom, ' ', ag.postnom, ' ', ag.prenom) AS prof_nom
        FROM prevision_detail pd
        JOIN prevision_matiere pm ON pd.prevision_id = pm.id
        JOIN cours c ON pm.cours_id = c.id
        JOIN classe cl ON c.classe_id = cl.id
        JOIN agent ag ON pm.enseignant_id = ag.id
        WHERE c.classe_id = :classe_id
    ";
    if ($selectedAnnee !== '') {
        $sql .= " AND pm.anneeScolaire = :annee";
    }
    $sql .= " ORDER BY c.intitule ASC, pd.id ASC";

    $stmtP = $pdo->prepare($sql);
    $paramsP = [':classe_id' => $selectedClasseId];
    if ($selectedAnnee !== '') { $paramsP[':annee'] = $selectedAnnee; }
    $stmtP->execute($paramsP);
    $previsions = $stmtP->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-3">

    <!-- EN-TÊTE -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 py-3">
            <div>
                <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary p-2 rounded-3">📚</span>
                    Prévisions des Matières & Programmes
                </h4>
                <small class="text-muted">Gestion et suivi des programmes de cours attribués aux enseignants</small>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- <form method="GET" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="classe_id" value="<?= $selectedClasseId ?>">
                    <select name="anneeScolaire" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Toutes les années --</option>
                        <?php foreach ($listeAnnees as $annee): ?>
                        <option value="<?= htmlspecialchars($annee) ?>"
                            <?= $selectedAnnee === $annee ? 'selected' : '' ?>>
                            <?= htmlspecialchars($annee) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form> -->

                <button class="btn btn-primary btn-sm fw-bold shadow-sm d-flex align-items-center gap-1"
                    data-bs-toggle="modal" data-bs-target="#modalPrevision">
                    <span>➕</span> Nouvelle Prévision
                </button>
            </div>
        </div>
    </div>

    <!-- ALERTES -->
    <?php if ($msgSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <?= htmlspecialchars($msgSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($msgError): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <?= htmlspecialchars($msgError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- SELECTION DES CLASSES -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="fw-bold text-secondary m-0">🏫 Choisir une classe :</h6>
        </div>
        <div class="card-body p-3">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($listeClasses as $c): ?>
                <?php $isActive = ($c['id'] == $selectedClasseId); ?>
                <a href="?classe_id=<?= $c['id'] ?>&anneeScolaire=<?= urlencode($selectedAnnee) ?>"
                    class="btn btn-sm <?= $isActive ? 'btn-primary fw-bold shadow-sm' : 'btn-outline-secondary' ?> rounded-pill px-3 py-2">
                    <?= htmlspecialchars($c['description']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- TABLEAU -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark m-0">
                📋 Programme de la Classe :
                <span class="text-primary">
                    <?php 
                        $currClasseName = array_filter($listeClasses, fn($cl) => $cl['id'] == $selectedClasseId);
                        echo !empty($currClasseName) ? reset($currClasseName)['description'] : '';
                    ?>
                </span>
            </h6>
            <span class="badge bg-light text-dark border"><?= count($previsions) ?> Chapitre(s) / Matière(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Cours / Matière</th>
                            <th>Enseignant Attitré</th>
                            <th>Période / Mois</th>
                            <th>Semaine</th>
                            <th>Savoirs Essentiels (Sujet)</th>
                            <th>Activités</th>
                            <th>Observation</th>
                            <th class="text-end no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($previsions)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                Aucune prévision de matière enregistrée pour cette classe.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($previsions as $p): ?>
                        <tr>
                            <td><span
                                    class="badge bg-dark-subtle text-dark font-monospace"><?= htmlspecialchars($p['code']) ?></span>
                            </td>
                            <td class="fw-bold text-primary">📘 <?= htmlspecialchars($p['cours_nom']) ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($p['prof_nom']) ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary border">
                                    <?= htmlspecialchars($p['periode'] . ' - ' . $p['mois']) ?>
                                </span>
                            </td>
                            <td><span
                                    class="badge bg-light text-dark border"><?= htmlspecialchars($p['semaine_libelle']) ?></span>
                            </td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($p['savoirs_essentiels']) ?></td>
                            <td>
                                <?php if (stripos($p['cours_nom'], 'anglais') !== false || stripos($p['cours_nom'], 'english') !== false): ?>
                                <span
                                    class="badge bg-info-subtle text-info-emphasis"><?= htmlspecialchars($p['activites']) ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted" style="max-width: 200px;">
                                <?= htmlspecialchars($p['observation'] ?: '-') ?></td>
                            <td class="text-end no-print">
                                <a href="fiche_pedagogiques.php?=<?= $p['detail_id'] ?>" class="btn btn-primary">Fiche
                                    détaillée</a>
                                <button class="btn btn-sm btn-secondary me-1 btn-edit"
                                    data-prevision='<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                    data-bs-toggle="modal" data-bs-target="#modalPrevision">
                                    Modifier
                                </button>

                                <form method="POST" class="d-inline"
                                    onsubmit="return confirm('Voulez-vous supprimer cette prévision ?');">
                                    <input type="hidden" name="action" value="delete_prevision">
                                    <input type="hidden" name="id" value="<?= $p['detail_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Supprimer</button>
                                </form>
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

<!-- MODAL CRÉATION / ÉDITION -->
<div class="modal fade" id="modalPrevision" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_prevision">
                <input type="hidden" name="prevision_detail_id" id="form_detail_id" value="">
                <!-- CHAMPS MASQUÉS -->
                <input type="hidden" name="anneeScolaire" id="form_anneeScolaire"
                    value="<?= htmlspecialchars($selectedAnnee) ?>">
                <input type="hidden" name="code" id="form_code" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalTitle">➕ Ajouter / Modifier une Prévision</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Cours / Matière <span class="text-danger">*</span></label>
                        <select name="cours_id" id="form_cours_id" class="form-select" required>
                            <option value="">-- Sélectionner le cours --</option>
                            <?php foreach ($listeCours as $cr): ?>
                            <option value="<?= $cr['id'] ?>" data-intitule="<?= htmlspecialchars($cr['intitule']) ?>">
                                <?= htmlspecialchars($cr['intitule']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Enseignant <span class="text-danger">*</span></label>
                        <select name="enseignant_id" id="form_enseignant_id" class="form-select" required>
                            <option value="">-- Sélectionner l'enseignant --</option>
                            <?php foreach ($listeProfesseurs as $prof): ?>
                            <option value="<?= $prof['id'] ?>">
                                <?= htmlspecialchars($prof['nom'] . ' ' . $prof['postnom'] . ' ' . $prof['prenom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Période</label>
                        <select name="periode" id="form_periode" class="form-select">
                            <option value="1ère Période">1ère Période</option>
                            <option value="2ème Période">2ème Période</option>
                            <option value="3ème Période">3ème Période</option>
                            <option value="4ème Période">4ème Période</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Mois</label>
                        <input type="text" name="mois" id="form_mois" class="form-control" placeholder="ex: Septembre">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Semaine</label>
                        <input type="text" name="semaine_libelle" id="form_semaine" class="form-control"
                            placeholder="ex: Semaine 1">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Savoirs Essentiels (Chapitre / Sujet) <span
                                class="text-danger">*</span></label>
                        <input type="text" name="savoirs_essentiels" id="form_savoirs" class="form-control" required>
                    </div>

                    <!-- CHAMP ACTIVITÉ : AFFICHÉ SEULEMENT POUR L'ANGLAIS -->
                    <div class="col-md-12 d-none" id="container_activites">
                        <label class="form-label fw-semibold">Activités (Anglais)</label>
                        <input type="text" name="activites" id="form_activites" class="form-control"
                            placeholder="ex: A1, Reading, Speaking...">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Observation</label>
                        <textarea name="observation" id="form_observation" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary fw-bold">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalTitle = document.getElementById('modalTitle');
    const formDetailId = document.getElementById('form_detail_id');
    const formCours = document.getElementById('form_cours_id');
    const formEnseignant = document.getElementById('form_enseignant_id');
    const formPeriode = document.getElementById('form_periode');
    const formMois = document.getElementById('form_mois');
    const formSemaine = document.getElementById('form_semaine');
    const formSavoirs = document.getElementById('form_savoirs');
    const formCode = document.getElementById('form_code');
    const formActivites = document.getElementById('form_activites');
    const containerActivites = document.getElementById('container_activites');
    const formObs = document.getElementById('form_observation');

    // DÉTECTION ET AFFICHAGE CONDITIONNEL CHAMP ACTIVITÉS (ANGLAIS)
    function checkEnglishCourse() {
        const selectedOption = formCours.options[formCours.selectedIndex];
        if (selectedOption) {
            const intitule = selectedOption.getAttribute('data-intitule') || selectedOption.text || '';
            if (intitule.toLowerCase().includes('anglais') || intitule.toLowerCase().includes('english')) {
                containerActivites.classList.remove('d-none');
            } else {
                containerActivites.classList.add('d-none');
            }
        } else {
            containerActivites.classList.add('d-none');
        }
    }

    formCours.addEventListener('change', checkEnglishCourse);

    // RÉINITIALISATION POUR AJOUT
    document.querySelector('[data-bs-target="#modalPrevision"]').addEventListener('click', function() {
        modalTitle.innerText = "➕ Ajouter une Prévision";
        formDetailId.value = "";
        formSavoirs.value = "";
        formCode.value = "";
        formObs.value = "";
        formActivites.value = "A1";
        checkEnglishCourse();
    });

    // ÉDITION
    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function() {
            const data = JSON.parse(this.getAttribute('data-prevision'));
            modalTitle.innerText = "✏️ Modifier la Prévision";

            formDetailId.value = data.detail_id;
            formCours.value = data.cours_id;
            formEnseignant.value = data.enseignant_id;
            formPeriode.value = data.periode;
            formMois.value = data.mois;
            formSemaine.value = data.semaine_libelle;
            formSavoirs.value = data.savoirs_essentiels;
            formCode.value = data.code;
            formActivites.value = data.activites;
            formObs.value = data.observation;

            checkEnglishCourse();
        });
    });
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>