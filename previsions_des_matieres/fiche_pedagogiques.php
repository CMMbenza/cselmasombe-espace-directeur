<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$msgSuccess = '';
$msgError = '';

// --- 1. TRAITEMENT DU FORMULAIRE (ENREGISTREMENT / MODIFICATION) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_fiche') {
        $fiche_id            = !empty($_POST['fiche_id']) ? (int)$_POST['fiche_id'] : null;
        $prevision_detail_id = (int)($_POST['prevision_detail_id'] ?? 0);
        $prof_id             = (int)($_POST['prof_id'] ?? 0);
        $date_cours          = $_POST['date_cours'] ?? date('Y-m-d');
        $domaine             = trim($_POST['domaine'] ?? '');
        $branche             = trim($_POST['branche'] ?? '');
        $sous_branche        = trim($_POST['sous_branche'] ?? '');
        $sujet               = trim($_POST['sujet'] ?? '');
        $matiere_txt         = trim($_POST['matiere'] ?? '');
        $obj_specifique      = trim($_POST['objectif_specifique'] ?? '');
        $obj_operationnel    = trim($_POST['objectif_operationnel'] ?? '');
        $strategies          = trim($_POST['strategies'] ?? '');
        $materiel_didactique = trim($_POST['materiel_didactique'] ?? '');

        // Étapes du cours (Prérequis, Motivation, Annonce, Analyse, Synthèse, Application, Évaluation)
        $prerequis_prof  = trim($_POST['prerequis_prof'] ?? '');
        $prerequis_eleve = trim($_POST['prerequis_eleve'] ?? '');
        $motivation_prof = trim($_POST['motivation_prof'] ?? '');
        $motivation_eleve= trim($_POST['motivation_eleve'] ?? '');
        $annonce_prof    = trim($_POST['annonce_prof'] ?? '');
        $annonce_eleve   = trim($_POST['annonce_eleve'] ?? '');
        $analyse_prof    = trim($_POST['analyse_prof'] ?? '');
        $analyse_eleve   = trim($_POST['analyse_eleve'] ?? '');
        $synthese_prof   = trim($_POST['synthese_prof'] ?? '');
        $synthese_eleve  = trim($_POST['synthese_eleve'] ?? '');
        $application_prof= trim($_POST['application_prof'] ?? '');
        $application_eleve= trim($_POST['application_eleve'] ?? '');
        $evaluation_prof = trim($_POST['evaluation_prof'] ?? '');
        $evaluation_eleve= trim($_POST['evaluation_eleve'] ?? '');

        if ($prevision_detail_id > 0 && $prof_id > 0) {
            try {
                if ($fiche_id) {
                    $stmt = $pdo->prepare("
                        UPDATE fiche_cours SET
                            prevision_detail_id = :pid, prof_id = :prof, date_cours = :date_c,
                            domaine = :domaine, branche = :branche, sous_branche = :sub_b, sujet = :sujet,
                            matiere = :matiere, objectif_specifique = :obj_s, objectif_operationnel = :obj_o,
                            strategies = :strat, materiel_didactique = :mat,
                            prerequis_prof = :pr_p, prerequis_eleve = :pr_e,
                            motivation_prof = :mo_p, motivation_eleve = :mo_e,
                            annonce_prof = :an_p, annonce_eleve = :an_e,
                            analyse_prof = :anl_p, analyse_eleve = :anl_e,
                            synthese_prof = :sy_p, synthese_eleve = :sy_e,
                            application_prof = :ap_p, application_eleve = :ap_e,
                            evaluation_prof = :ev_p, evaluation_eleve = :ev_e,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':pid' => $prevision_detail_id, ':prof' => $prof_id, ':date_c' => $date_cours,
                        ':domaine' => $domaine, ':branche' => $branche, ':sub_b' => $sous_branche, ':sujet' => $sujet,
                        ':matiere' => $matiere_txt, ':obj_s' => $obj_specifique, ':obj_o' => $obj_operationnel,
                        ':strat' => $strategies, ':mat' => $materiel_didactique,
                        ':pr_p' => $prerequis_prof, ':pr_e' => $prerequis_eleve,
                        ':mo_p' => $motivation_prof, ':mo_e' => $motivation_eleve,
                        ':an_p' => $annonce_prof, ':an_e' => $annonce_eleve,
                        ':anl_p' => $analyse_prof, ':anl_e' => $analyse_eleve,
                        ':sy_p' => $synthese_prof, ':sy_e' => $synthese_eleve,
                        ':ap_p' => $application_prof, ':ap_e' => $application_eleve,
                        ':ev_p' => $evaluation_prof, ':ev_e' => $evaluation_eleve,
                        ':id' => $fiche_id
                    ]);
                    $msgSuccess = "La fiche pédagogique a été mise à jour.";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO fiche_cours (
                            prevision_detail_id, prof_id, date_cours, domaine, branche, sous_branche, sujet, matiere,
                            objectif_specifique, objectif_operationnel, strategies, materiel_didactique,
                            prerequis_prof, prerequis_eleve, motivation_prof, motivation_eleve,
                            annonce_prof, annonce_eleve, analyse_prof, analyse_eleve,
                            synthese_prof, synthese_eleve, application_prof, application_eleve,
                            evaluation_prof, evaluation_eleve, created_at, updated_at
                        ) VALUES (
                            :pid, :prof, :date_c, :domaine, :branche, :sub_b, :sujet, :matiere,
                            :obj_s, :obj_o, :strat, :mat,
                            :pr_p, :pr_e, :mo_p, :mo_e,
                            :an_p, :an_e, :anl_p, :anl_e,
                            :sy_p, :sy_e, :ap_p, :ap_e,
                            :ev_p, :ev_e, NOW(), NOW()
                        )
                    ");
                    $stmt->execute([
                        ':pid' => $prevision_detail_id, ':prof' => $prof_id, ':date_c' => $date_cours,
                        ':domaine' => $domaine, ':branche' => $branche, ':sub_b' => $sous_branche, ':sujet' => $sujet,
                        ':matiere' => $matiere_txt, ':obj_s' => $obj_specifique, ':obj_o' => $obj_operationnel,
                        ':strat' => $strategies, ':mat' => $materiel_didactique,
                        ':pr_p' => $prerequis_prof, ':pr_e' => $prerequis_eleve,
                        ':mo_p' => $motivation_prof, ':mo_e' => $motivation_eleve,
                        ':an_p' => $annonce_prof, ':an_e' => $annonce_eleve,
                        ':anl_p' => $analyse_prof, ':anl_e' => $analyse_eleve,
                        ':sy_p' => $synthese_prof, ':sy_e' => $synthese_eleve,
                        ':ap_p' => $application_prof, ':ap_e' => $application_eleve,
                        ':ev_p' => $evaluation_prof, ':ev_e' => $evaluation_eleve
                    ]);
                    $msgSuccess = "La fiche pédagogique a été créée avec succès.";
                }
            } catch (Exception $e) {
                $msgError = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        } else {
            $msgError = "Veuillez associer la fiche à une prévision et à un enseignant.";
        }
    }

    if ($action === 'delete_fiche') {
        $fiche_id = (int)($_POST['id'] ?? 0);
        if ($fiche_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM fiche_cours WHERE id = :id");
            $stmt->execute([':id' => $fiche_id]);
            $msgSuccess = "La fiche pédagogique a été supprimée.";
        }
    }
}

// --- 2. RÉCUPÉRATION DES REQUÊTES ---
$selectedDetailId = (int)($_GET['prevision_detail_id'] ?? $_GET['val'] ?? 0);

// Liste des fiches ou fiche spécifique
$sql = "
    SELECT 
        fc.*, 
        pd.savoirs_essentiels, pd.code, pd.periode, pd.mois, pd.semaine_libelle,
        c.intitule AS cours_nom,
        cl.description AS classe_nom,
        CONCAT(ag.nom, ' ', ag.postnom, ' ', ag.prenom) AS prof_nom
    FROM fiche_cours fc
    JOIN prevision_detail pd ON fc.prevision_detail_id = pd.id
    JOIN prevision_matiere pm ON pd.prevision_id = pm.id
    JOIN cours c ON pm.cours_id = c.id
    JOIN classe cl ON c.classe_id = cl.id
    JOIN agent ag ON fc.prof_id = ag.id
";

if ($selectedDetailId > 0) {
    $sql .= " WHERE fc.prevision_detail_id = :pdid ORDER BY fc.id DESC";
    $stmtF = $pdo->prepare($sql);
    $stmtF->execute([':pdid' => $selectedDetailId]);
} else {
    $sql .= " ORDER BY fc.id DESC LIMIT 50";
    $stmtF = $pdo->query($sql);
}
$fiches = $stmtF->fetchAll(PDO::FETCH_ASSOC);

// Liste des prévisions sans fiche pour la sélection
$previsionsDispoStmt = $pdo->query("
    SELECT 
        pd.id, pd.savoirs_essentiels, pd.code, c.intitule AS cours_nom, cl.description AS classe_nom, pm.enseignant_id
    FROM prevision_detail pd
    JOIN prevision_matiere pm ON pd.prevision_id = pm.id
    JOIN cours c ON pm.cours_id = c.id
    JOIN classe cl ON c.classe_id = cl.id
    ORDER BY pd.id DESC
");
$listePrevisions = $previsionsDispoStmt->fetchAll(PDO::FETCH_ASSOC);

// Liste des agents (Enseignants)
$profsStmt = $pdo->query("SELECT id, nom, postnom, prenom FROM agent ORDER BY nom ASC");
$listeProfesseurs = $profsStmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-3">

    <!-- EN-TÊTE -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 py-3">
            <div>
                <h4 class="fw-bold text-dark m-0 d-flex align-items-center gap-2">
                    <span class="badge bg-primary-subtle text-primary p-2 rounded-3">📝</span>
                    Fiches Pédagogiques de Préparation
                </h4>
                <small class="text-muted">Consulter, préparer et valider le déroulement méthodique des cours</small>
            </div>

            <button class="btn btn-primary btn-sm fw-bold shadow-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalFiche">
                <span>➕</span> Nouvelle Fiche
            </button>
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

    <!-- TABLEAU DES FICHES -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark m-0">📋 Liste des Fiches Pédagogiques Registrées</h6>
            <span class="badge bg-light text-dark border"><?= count($fiches) ?> Fiche(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Classe & Cours</th>
                            <th>Enseignant</th>
                            <th>Sujet / Savoirs Essentiels</th>
                            <th>Domaine / Branche</th>
                            <th class="text-end no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fiches)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Aucune fiche pédagogique disponible.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fiches as $f): ?>
                                <tr>
                                    <td class="small fw-semibold text-secondary">
                                        <?= htmlspecialchars(date('d/m/Y', strtotime($f['date_cours'] ?? $f['created_at']))) ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($f['cours_nom']) ?></div>
                                        <small class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($f['classe_nom']) ?></small>
                                    </td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($f['prof_nom']) ?></td>
                                    <td class="fw-bold text-primary">
                                        [<?= htmlspecialchars($f['code']) ?>] <?= htmlspecialchars($f['sujet'] ?: $f['savoirs_essentiels']) ?>
                                    </td>
                                    <td>
                                        <small class="d-block text-muted">Domaine: <?= htmlspecialchars($f['domaine'] ?: '-') ?></small>
                                        <small class="d-block text-muted">Branche: <?= htmlspecialchars($f['branche'] ?: '-') ?></small>
                                    </td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-outline-primary me-1 btn-edit-fiche" 
                                                data-fiche='<?= json_encode($f, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalFiche">
                                            ✏️ Éditer
                                        </button>

                                        <form method="POST" class="d-inline" onsubmit="return confirm('Voulez-vous supprimer cette fiche ?');">
                                            <input type="hidden" name="action" value="delete_fiche">
                                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">🗑️</button>
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

<!-- MODAL CRÉATION / ÉDITION COMPLÈTE -->
<div class="modal fade" id="modalFiche" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="save_fiche">
                <input type="hidden" name="fiche_id" id="form_fiche_id" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalFicheTitle">➕ Élaboration de la Fiche Pédagogique</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body row g-3" style="max-height: 75vh; overflow-y: auto;">
                    
                    <!-- INFORMATIONS GÉNÉRALES -->
                    <div class="col-12"><h6 class="fw-bold text-primary border-bottom pb-2">📌 En-tête & Profil du Cours</h6></div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Lier à la Prévision <span class="text-danger">*</span></label>
                        <select name="prevision_detail_id" id="form_prevision_detail_id" class="form-select" required>
                            <option value="">-- Sélectionner le chapitre prévisionnel --</option>
                            <?php foreach ($listePrevisions as $p): ?>
                                <option value="<?= $p['id'] ?>" data-prof="<?= $p['enseignant_id'] ?>">
                                    [<?= htmlspecialchars($p['code']) ?>] <?= htmlspecialchars($p['classe_nom']) ?> - <?= htmlspecialchars($p['cours_nom']) ?> : <?= htmlspecialchars($p['savoirs_essentiels']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Enseignant <span class="text-danger">*</span></label>
                        <select name="prof_id" id="form_prof_id" class="form-select" required>
                            <option value="">-- Sélectionner l'enseignant --</option>
                            <?php foreach ($listeProfesseurs as $prof): ?>
                                <option value="<?= $prof['id'] ?>">
                                    <?= htmlspecialchars($prof['nom'] . ' ' . $prof['postnom'] . ' ' . $prof['prenom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Date du Cours</label>
                        <input type="date" name="date_cours" id="form_date_cours" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Domaine</label>
                        <input type="text" name="domaine" id="form_domaine" class="form-control" placeholder="ex: Sciences & Technologies">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Branche</label>
                        <input type="text" name="branche" id="form_branche" class="form-control" placeholder="ex: Mathématiques">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Sous-Branche</label>
                        <input type="text" name="sous_branche" id="form_sous_branche" class="form-control" placeholder="ex: Algèbre">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Sujet de la Leçon</label>
                        <input type="text" name="sujet" id="form_sujet" class="form-control" placeholder="ex: Équations du premier degré">
                    </div>

                    <!-- OBJECTIFS ET MATÉRIEL -->
                    <div class="col-12 mt-4"><h6 class="fw-bold text-primary border-bottom pb-2">🎯 Objectifs & Stratégies Pédagogiques</h6></div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Objectif Spécifique</label>
                        <textarea name="objectif_specifique" id="form_obj_specifique" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Objectif Opérationnel</label>
                        <textarea name="objectif_operationnel" id="form_obj_operationnel" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Stratégies & Méthodes</label>
                        <input type="text" name="strategies" id="form_strategies" class="form-control" placeholder="ex: Interrogative, Participative...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Matériel Didactique</label>
                        <input type="text" name="materiel_didactique" id="form_materiel" class="form-control" placeholder="ex: Tableau, Règle, Graphiques...">
                    </div>

                    <!-- DÉROULEMENT DU COURS -->
                    <div class="col-12 mt-4"><h6 class="fw-bold text-primary border-bottom pb-2">🔄 Déroulement de la Leçon (Activités Professeur / Élèves)</h6></div>

                    <!-- Prérequis -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">1. Prérequis (Professeur)</label>
                        <textarea name="prerequis_prof" id="form_prerequis_prof" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">1. Prérequis (Élève)</label>
                        <textarea name="prerequis_eleve" id="form_prerequis_eleve" class="form-control" rows="2"></textarea>
                    </div>

                    <!-- Motivation & Annonce -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">2. Motivation (Professeur)</label>
                        <textarea name="motivation_prof" id="form_motivation_prof" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">2. Motivation (Élève)</label>
                        <textarea name="motivation_eleve" id="form_motivation_eleve" class="form-control" rows="2"></textarea>
                    </div>

                    <!-- Analyse -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">3. Analyse / Corps du cours (Professeur)</label>
                        <textarea name="analyse_prof" id="form_analyse_prof" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">3. Analyse (Élève)</label>
                        <textarea name="analyse_eleve" id="form_analyse_eleve" class="form-control" rows="3"></textarea>
                    </div>

                    <!-- Synthèse & Application -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">4. Synthèse (Professeur)</label>
                        <textarea name="synthese_prof" id="form_synthese_prof" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">4. Synthèse (Élève)</label>
                        <textarea name="synthese_eleve" id="form_synthese_eleve" class="form-control" rows="2"></textarea>
                    </div>

                    <!-- Évaluation -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">5. Évaluation (Professeur)</label>
                        <textarea name="evaluation_prof" id="form_evaluation_prof" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">5. Évaluation (Élève)</label>
                        <textarea name="evaluation_eleve" id="form_evaluation_eleve" class="form-control" rows="2"></textarea>
                    </div>

                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary fw-bold">Enregistrer la Fiche</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalTitle = document.getElementById('modalFicheTitle');
    const formFicheId = document.getElementById('form_fiche_id');
    const formPrevId = document.getElementById('form_prevision_detail_id');
    const formProfId = document.getElementById('form_prof_id');

    // Auto-sélection du prof selon la prévision choisie
    formPrevId.addEventListener('change', function() {
        const selectedOpt = this.options[this.selectedIndex];
        const profId = selectedOpt.getAttribute('data-prof');
        if(profId) {
            formProfId.value = profId;
        }
    });

    // Reset formulaire lors de la création
    document.querySelector('[data-bs-target="#modalFiche"]').addEventListener('click', function() {
        modalTitle.innerText = "➕ Élaboration de la Fiche Pédagogique";
        formFicheId.value = "";
    });

    // Remplissage rapide des champs lors d'une édition
    document.querySelectorAll('.btn-edit-fiche').forEach(button => {
        button.addEventListener('click', function () {
            const data = JSON.parse(this.getAttribute('data-fiche'));
            modalTitle.innerText = "✏️ Modifier la Fiche Pédagogique";

            formFicheId.value = data.id;
            formPrevId.value = data.prevision_detail_id;
            formProfId.value = data.prof_id;
            
            document.getElementById('form_date_cours').value = data.date_cours || '';
            document.getElementById('form_domaine').value = data.domaine || '';
            document.getElementById('form_branche').value = data.branche || '';
            document.getElementById('form_sous_branche').value = data.sous_branche || '';
            document.getElementById('form_sujet').value = data.sujet || '';
            document.getElementById('form_obj_specifique').value = data.objectif_specifique || '';
            document.getElementById('form_obj_operationnel').value = data.objectif_operationnel || '';
            document.getElementById('form_strategies').value = data.strategies || '';
            document.getElementById('form_materiel').value = data.materiel_didactique || '';

            document.getElementById('form_prerequis_prof').value = data.prerequis_prof || '';
            document.getElementById('form_prerequis_eleve').value = data.prerequis_eleve || '';
            document.getElementById('form_motivation_prof').value = data.motivation_prof || '';
            document.getElementById('form_motivation_eleve').value = data.motivation_eleve || '';
            document.getElementById('form_analyse_prof').value = data.analyse_prof || '';
            document.getElementById('form_analyse_eleve').value = data.analyse_eleve || '';
            document.getElementById('form_synthese_prof').value = data.synthese_prof || '';
            document.getElementById('form_synthese_eleve').value = data.synthese_eleve || '';
            document.getElementById('form_evaluation_prof').value = data.evaluation_prof || '';
            document.getElementById('form_evaluation_eleve').value = data.evaluation_eleve || '';
        });
    });
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>