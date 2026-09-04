<?php
// directeur/cours_resume/index.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur();

// Inclusion de l'année scolaire en cours
// include_once __DIR__.'/../includes/get_annee_en_cours.php';

$annee_en_cours = '2026-2027';
// if (!isset($annee_en_cours) || empty($annee_en_cours)) {
//     $annee_en_cours = $_SESSION['annee_scolaire'] ?? date('Y').'-'.(date('Y') + 1);
// }

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// --- AJOUT D'UN RÉSUMÉ DE COURS ---
if (isset($_POST['add_resume'])) {
    $journal_id          = (int)$_POST['journal_id'];
    $fiche_no            = trim($_POST['fiche_no'] ?? '');
    $domaine             = trim($_POST['domaine'] ?? '');
    $discipline          = trim($_POST['discipline'] ?? '');
    $titre_lecon         = trim($_POST['titre_lecon'] ?? '');
    $type_lecon          = trim($_POST['type_lecon'] ?? '');
    $competence_attendue = trim($_POST['competence_attendue'] ?? '');
    $resume_texte        = trim($_POST['resume_texte'] ?? '');
    $devoir              = trim($_POST['devoir'] ?? '');
    $piece_jointe        = '';

    if (!empty($_FILES['piece_jointe']['name'])) {
        $uploadDir = __DIR__.'/../../../uploads/attachement_resume_cours/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['piece_jointe']['name'], PATHINFO_EXTENSION);
        $piece_jointe = uniqid('resume_').'.'.$ext;
        move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $uploadDir.$piece_jointe);
    }

    $stmt = $pdo->prepare("
        INSERT INTO resume_cours 
        (journal_id, fiche_no, domaine, discipline, titre_lecon, type_lecon, competence_attendue, resume_texte, devoir, piece_jointe) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $journal_id, $fiche_no, $domaine, $discipline, 
        $titre_lecon, $type_lecon, $competence_attendue, 
        $resume_texte, $devoir, $piece_jointe
    ]);

    header('Location: index.php?msg=resume_added');
    exit();
}

// --- MODIFICATION D'UN RÉSUMÉ ---
if (isset($_POST['edit_resume'])) {
    $resume_id           = (int)$_POST['resume_id'];
    $journal_id          = (int)$_POST['journal_id'];
    $fiche_no            = trim($_POST['fiche_no'] ?? '');
    $domaine             = trim($_POST['domaine'] ?? '');
    $discipline          = trim($_POST['discipline'] ?? '');
    $titre_lecon         = trim($_POST['titre_lecon'] ?? '');
    $type_lecon          = trim($_POST['type_lecon'] ?? '');
    $competence_attendue = trim($_POST['competence_attendue'] ?? '');
    $resume_texte        = trim($_POST['resume_texte'] ?? '');
    $devoir              = trim($_POST['devoir'] ?? '');

    $stmtFile = $pdo->prepare("SELECT piece_jointe FROM resume_cours WHERE id = ?");
    $stmtFile->execute([$resume_id]);
    $currentFile = $stmtFile->fetchColumn();
    $piece_jointe = $currentFile;

    if (!empty($_FILES['piece_jointe']['name'])) {
        $uploadDir = __DIR__.'/../../../uploads/attachement_resume_cours/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        if ($currentFile && file_exists($uploadDir.$currentFile)) {
            @unlink($uploadDir.$currentFile);
        }

        $ext = pathinfo($_FILES['piece_jointe']['name'], PATHINFO_EXTENSION);
        $piece_jointe = uniqid('resume_').'.'.$ext;
        move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $uploadDir.$piece_jointe);
    }

    $stmt = $pdo->prepare("
        UPDATE resume_cours 
        SET journal_id = ?, fiche_no = ?, domaine = ?, discipline = ?, titre_lecon = ?, 
            type_lecon = ?, competence_attendue = ?, resume_texte = ?, devoir = ?, piece_jointe = ? 
        WHERE id = ?
    ");
    $stmt->execute([
        $journal_id, $fiche_no, $domaine, $discipline, $titre_lecon, 
        $type_lecon, $competence_attendue, $resume_texte, $devoir, $piece_jointe, $resume_id
    ]);

    header('Location: index.php?msg=resume_updated');
    exit();
}

// --- SUPPRESSION D'UN RÉSUMÉ ---
if ($action === 'delete_resume' && $id > 0) {
    $stmtFile = $pdo->prepare("SELECT piece_jointe FROM resume_cours WHERE id = ?");
    $stmtFile->execute([$id]);
    $fileToDelete = $stmtFile->fetchColumn();
    if ($fileToDelete && file_exists(__DIR__.'/../../../uploads/attachement_resume_cours/'.$fileToDelete)) {
        @unlink(__DIR__.'/../../../uploads/attachement_resume_cours/'.$fileToDelete);
    }

    $pdo->prepare("DELETE FROM resume_cours WHERE id = ?")->execute([$id]);
    header('Location: index.php?msg=resume_deleted');
    exit();
}

// --- RÉCUPÉRATION ET FILTRAGE DES PARAMÈTRES (GET) ---
$filter_cours  = isset($_GET['cours_id']) && $_GET['cours_id'] !== '' ? (int)$_GET['cours_id'] : null;
$filter_classe = isset($_GET['classe_id']) && $_GET['classe_id'] !== '' ? (int)$_GET['classe_id'] : null;
$filter_prof   = isset($_GET['prof_id']) && $_GET['prof_id'] !== '' ? (int)$_GET['prof_id'] : null;

// Requête SQL de base
$sql = "
    SELECT rc.*, 
           jc.cours_id, jc.classe_id, jc.prof_id,
           CONCAT(c.description ,' ', IFNULL(cy.description, '')) AS classe_nom, 
           co.intitule AS cours_nom, 
           CONCAT(u.nom, ' ', u.prenom) AS prof_nom
    FROM resume_cours rc
    INNER JOIN journal_classe jc ON jc.id = rc.journal_id
    LEFT JOIN classe c ON c.id = jc.classe_id
    LEFT JOIN cours co ON co.id = jc.cours_id
    LEFT JOIN cycle cy ON cy.id = c.cycle 
    LEFT JOIN agent u ON u.id = jc.prof_id
    WHERE jc.anneScolaire = ?
";

$params = [$annee_en_cours];

if ($filter_cours !== null) {
    $sql .= " AND jc.cours_id = ?";
    $params[] = $filter_cours;
}

if ($filter_classe !== null) {
    $sql .= " AND jc.classe_id = ?";
    $params[] = $filter_classe;
}

if ($filter_prof !== null) {
    $sql .= " AND jc.prof_id = ?";
    $params[] = $filter_prof;
}

$sql .= " ORDER BY co.intitule ASC, c.description ASC, rc.created_at DESC";

$resumesRaw = $pdo->prepare($sql);
$resumesRaw->execute($params);
$resumesList = $resumesRaw->fetchAll(PDO::FETCH_ASSOC);

// Regroupement hiérarchique : [cours_id] => [classe_id] => [resumes]
$groupedData = [];
foreach ($resumesList as $res) {
    $cId  = $res['cours_id'];
    $clId = $res['classe_id'];

    if (!isset($groupedData[$cId])) {
        $groupedData[$cId] = [
            'cours_nom' => $res['cours_nom'] ?? 'Cours non spécifié',
            'classes'   => []
        ];
    }

    if (!isset($groupedData[$cId]['classes'][$clId])) {
        $groupedData[$cId]['classes'][$clId] = [
            'classe_nom' => $res['classe_nom'] ?? 'Classe non spécifiée',
            'resumes'    => []
        ];
    }

    $groupedData[$cId]['classes'][$clId]['resumes'][] = $res;
}

// Données pour les filtres et les modals
$profs    = $pdo->query("SELECT id, CONCAT(nom, ' ', prenom) AS nom_complet FROM agent ORDER BY nom")->fetchAll();
$classes  = $pdo->query("SELECT classe.id, CONCAT(classe.description ,' ', IFNULL(cy.description, '')) AS description FROM classe LEFT JOIN cycle cy ON cy.id = classe.cycle ORDER BY description")->fetchAll();
$cours    = $pdo->query("SELECT id, intitule FROM cours ORDER BY intitule")->fetchAll();
$journaux = $pdo->query("
    SELECT jc.id, CONCAT('Fiche/Journal #', jc.id, ' - ', co.intitule, ' (', c.description, ')') AS libelle
    FROM journal_classe jc
    LEFT JOIN cours co ON co.id = jc.cours_id
    LEFT JOIN classe c ON c.id = jc.classe_id
    WHERE jc.anneScolaire = '$annee_en_cours'
    ORDER BY jc.id DESC
")->fetchAll();

require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';
?>

<style>
.resume-container {
    transition: all 0.4s ease-in-out;
}

.resume-collapsed {
    max-height: 80px;
    overflow: hidden;
    position: relative;
}

.resume-collapsed::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 35px;
    background: linear-gradient(to bottom, rgba(255, 255, 255, 0), rgba(255, 255, 255, 1));
    pointer-events: none;
}

.resume-expanded {
    max-height: 2000px;
}
</style>

<div class="container-fluid mt-4 mb-5" style="padding-left: 2.5rem; padding-right: 2.5rem">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>📚 Résumés de cours : Cours > Classe</h4>
        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#addResumeModal">+ Nouveau
            Résumé</button>
    </div>

    <!-- FILTRE DYNAMIQUE PAR PARAMÈTRES (GET) -->
    <div class="d-none card mb-4 bg-light shadow-sm">
        <div class="card-body">
            <form method="get" action="index.php" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Filtrer par Cours :</label>
                    <select name="cours_id" class="form-select form-select-sm">
                        <option value="">Tous les cours</option>
                        <?php foreach($cours as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= $filter_cours === (int)$co['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($co['intitule']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Filtrer par Classe :</label>
                    <select name="classe_id" class="form-select form-select-sm">
                        <option value="">Toutes les classes</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filter_classe === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['description']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Filtrer par Enseignant :</label>
                    <select name="prof_id" class="form-select form-select-sm">
                        <option value="">Tous les enseignants</option>
                        <?php foreach($profs as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filter_prof === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nom_complet']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">Filtrer</button>
                    <a href="index.php" class="btn btn-sm btn-outline-secondary w-100">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($groupedData)): ?>
    <div class="alert alert-info text-center">Aucun résumé trouvé.</div>
    <?php endif; ?>

    <!-- NIVEAU 1 : LES COURS -->
    <div class="accordion" id="accordionCours">
        <div class="row">

            <?php foreach ($groupedData as $coursId => $coursGroup): ?>
            <div class="col-md-4">

                <div class="accordion-item mb-3 border border-primary shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed bg-light text-primary fw-bold fs-5" type="button"
                            data-bs-toggle="collapse" data-bs-target="#coursBlock<?= $coursId ?>">
                            📖 Cours : <?= htmlspecialchars($coursGroup['cours_nom']) ?>
                        </button>
                    </h2>
                    <div id="coursBlock<?= $coursId ?>" class="accordion-collapse collapse"
                        data-bs-parent="#accordionCours">
                        <div class="accordion-body bg-white">

                            <!-- NIVEAU 2 : LES CLASSES POUR CE COURS -->
                            <div class="accordion" id="accordionClasses<?= $coursId ?>">
                                <?php foreach ($coursGroup['classes'] as $classeId => $classeGroup): ?>
                                <div class="accordion-item mb-2 border">
                                    <h3 class="accordion-header">
                                        <button class="accordion-button collapsed fw-bold text-dark" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#classeBlock<?= $coursId ?>_<?= $classeId ?>">
                                            🏫 Classe : <?= htmlspecialchars($classeGroup['classe_nom']) ?>
                                            <span class="badge bg-secondary ms-2"><?= count($classeGroup['resumes']) ?>
                                                résumé(s)</span>
                                        </button>
                                    </h3>
                                    <div id="classeBlock<?= $coursId ?>_<?= $classeId ?>"
                                        class="accordion-collapse collapse"
                                        data-bs-parent="#accordionClasses<?= $coursId ?>">
                                        <div class="accordion-body">

                                            <!-- NIVEAU 3 : AFFICHAGE EN 4 COLONNES -->
                                            <div class="row g-3">
                                                <?php foreach ($classeGroup['resumes'] as $r): ?>
                                                <div class="col-12">
                                                    <div class="card h-100 shadow-sm border rounded">
                                                        <div
                                                            class="card-header bg-light d-flex justify-content-between align-items-start p-2">
                                                            <div>
                                                                <h6 class="fw-bold mb-1 text-dark"
                                                                    style="font-size: 0.95rem;">
                                                                    📑
                                                                    <?= htmlspecialchars($r['titre_lecon'] ?? 'Sans titre') ?>
                                                                </h6>
                                                                <?php if (!empty($r['fiche_no'])): ?>
                                                                <span class="badge bg-secondary"
                                                                    style="font-size: 0.7rem;">Fiche N° :
                                                                    <?= htmlspecialchars($r['fiche_no']) ?></span>
                                                                <?php endif; ?>
                                                                <?php if (!empty($r['type_lecon'])): ?>
                                                                <span class="badge bg-info text-dark"
                                                                    style="font-size: 0.7rem;"><?= strtoupper(htmlspecialchars($r['type_lecon'])) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="d-flex gap-1">
                                                                <button class="btn btn-sm btn-warning p-1"
                                                                    onclick='openEditResumeModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)'
                                                                    title="Modifier">✏️</button>
                                                                <a href="index.php?action=delete_resume&id=<?= $r['id'] ?>"
                                                                    class="btn btn-sm btn-danger p-1"
                                                                    onclick="return confirm('Supprimer ce résumé ?')">🗑️</a>
                                                            </div>
                                                        </div>

                                                        <div class="card-body p-2" style="font-size: 0.85rem;">
                                                            <small class="text-muted d-block mb-2">
                                                                👤 Prof :
                                                                <strong><?= htmlspecialchars($r['prof_nom'] ?? 'Inconnu') ?></strong><br>
                                                                📌 Domaine :
                                                                <?= htmlspecialchars($r['domaine'] ?? 'N/A') ?><br>
                                                                📚 Discipline :
                                                                <?= htmlspecialchars($r['discipline'] ?? 'N/A') ?><br>
                                                                📅 Date :
                                                                <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>
                                                            </small>

                                                            <?php if (!empty($r['competence_attendue'])): ?>
                                                            <div
                                                                class="mb-2 p-2 bg-light border-start border-3 border-info rounded">
                                                                <small class="fw-bold text-primary d-block">🎯
                                                                    Compétence attendue :</small>
                                                                <small
                                                                    class="text-dark"><?= htmlspecialchars($r['competence_attendue']) ?></small>
                                                            </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($r['resume_texte'])): 
                                                        $isLong = mb_strlen($r['resume_texte']) > 120;
                                                    ?>
                                                            <div class="p-2 bg-light border rounded mb-2">
                                                                <strong class="text-success d-block small mb-1">📝
                                                                    Résumé du cours :</strong>
                                                                <div id="resume_box_<?= $r['id'] ?>"
                                                                    class="resume-container small text-dark <?= $isLong ? 'resume-collapsed' : '' ?>"
                                                                    style="white-space: pre-line;">
                                                                    <?= htmlspecialchars($r['resume_texte']) ?></div>

                                                                <?php if ($isLong): ?>
                                                                <button
                                                                    class="btn btn-link btn-sm p-0 mt-1 text-decoration-none fw-bold"
                                                                    onclick="toggleResume(<?= $r['id'] ?>, this)">
                                                                    Voir plus 👇
                                                                </button>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($r['devoir'])): ?>
                                                            <div
                                                                class="p-2 bg-warning bg-opacity-10 border-start border-3 border-warning rounded mb-2">
                                                                <small class="fw-bold text-dark d-block">📌 Devoir
                                                                    :</small>
                                                                <small class="text-dark"
                                                                    style="white-space: pre-line;"><?= htmlspecialchars($r['devoir']) ?></small>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>

                                                        <?php if (!empty($r['piece_jointe'])): ?>
                                                        <div class="card-footer bg-white p-2 border-top-0 text-center">
                                                            <a href="../../../uploads/attachement_resume_cours/<?= urlencode($r['piece_jointe']) ?>"
                                                                target="_blank"
                                                                class="btn btn-sm btn-outline-primary w-100 fw-bold"
                                                                style="font-size: 0.8rem;">
                                                                📄 Pièce jointe
                                                            </a>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- MODAL CRÉATION RÉSUMÉ -->
<div class="modal modal-xl fade" id="addResumeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Nouveau Résumé de Cours</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Journal de classe associé :</label>
                    <select name="journal_id" class="form-select" required>
                        <option value="">-- Sélectionner un journal --</option>
                        <?php foreach($journaux as $j): ?>
                        <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">N° Fiche :</label>
                    <input type="text" name="fiche_no" class="form-control" placeholder="Ex: F-01">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Type de leçon :</label>
                    <input type="text" name="type_lecon" class="form-control" placeholder="Ex: Théorique / Pratique">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Domaine :</label>
                    <input type="text" name="domaine" class="form-control" placeholder="Ex: Sciences et Technologies">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Discipline :</label>
                    <input type="text" name="discipline" class="form-control" placeholder="Ex: Informatique">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">Titre de la leçon :</label>
                    <input type="text" name="titre_lecon" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Compétence attendue :</label>
                    <textarea name="competence_attendue" class="form-control" rows="2"
                        placeholder="Objectifs pédagogiques..."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Résumé du cours (Texte) :</label>
                    <textarea name="resume_texte" class="form-control" rows="4"
                        placeholder="Résumé du contenu de la leçon..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Devoir à domicile :</label>
                    <textarea name="devoir" class="form-control" rows="2"
                        placeholder="Consignes pour les élèves..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Pièce jointe (Optionnel) :</label>
                    <input type="file" name="piece_jointe" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="add_resume" class="btn btn-primary fw-bold">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL MODIFICATION RÉSUMÉ -->
<div class="modal modal-xl fade" id="editResumeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="resume_id" id="edit_resume_id">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold">Modifier le Résumé</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Journal de classe associé :</label>
                    <select name="journal_id" id="edit_journal_id" class="form-select" required>
                        <?php foreach($journaux as $j): ?>
                        <option value="<?= $j['id'] ?>"><?= htmlspecialchars($j['libelle']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">N° Fiche :</label>
                    <input type="text" name="fiche_no" id="edit_fiche_no" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Type de leçon :</label>
                    <input type="text" name="type_lecon" id="edit_type_lecon" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Domaine :</label>
                    <input type="text" name="domaine" id="edit_domaine" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Discipline :</label>
                    <input type="text" name="discipline" id="edit_discipline" class="form-control">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold">Titre de la leçon :</label>
                    <input type="text" name="titre_lecon" id="edit_titre_lecon" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Compétence attendue :</label>
                    <textarea name="competence_attendue" id="edit_competence_attendue" class="form-control"
                        rows="2"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Résumé du cours (Texte) :</label>
                    <textarea name="resume_texte" id="edit_resume_texte" class="form-control" rows="4"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Devoir à domicile :</label>
                    <textarea name="devoir" id="edit_devoir" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Changer la pièce jointe :</label>
                    <input type="file" name="piece_jointe" class="form-control">
                    <small id="edit_current_file_info" class="text-muted d-block mt-1"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="edit_resume" class="btn btn-warning fw-bold">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditResumeModal(res) {
    document.getElementById('edit_resume_id').value = res.id;
    document.getElementById('edit_journal_id').value = res.journal_id;
    document.getElementById('edit_fiche_no').value = res.fiche_no || '';
    document.getElementById('edit_type_lecon').value = res.type_lecon || '';
    document.getElementById('edit_domaine').value = res.domaine || '';
    document.getElementById('edit_discipline').value = res.discipline || '';
    document.getElementById('edit_titre_lecon').value = res.titre_lecon || '';
    document.getElementById('edit_competence_attendue').value = res.competence_attendue || '';
    document.getElementById('edit_resume_texte').value = res.resume_texte || '';
    document.getElementById('edit_devoir').value = res.devoir || '';

    const fileInfo = document.getElementById('edit_current_file_info');
    if (res.piece_jointe) {
        fileInfo.innerHTML = 'Fichier actuel : <strong>' + res.piece_jointe + '</strong>';
    } else {
        fileInfo.innerHTML = 'Aucune pièce jointe.';
    }

    new bootstrap.Modal(document.getElementById('editResumeModal')).show();
}

function toggleResume(id, btn) {
    const box = document.getElementById('resume_box_' + id);
    if (box.classList.contains('resume-collapsed')) {
        box.classList.remove('resume-collapsed');
        box.classList.add('resume-expanded');
        btn.innerHTML = 'Voir moins ☝️';
    } else {
        box.classList.remove('resume-expanded');
        box.classList.add('resume-collapsed');
        btn.innerHTML = 'Voir plus 👇';
    }
}
</script>

<?php require_once __DIR__.'/../layout/footer.php'; ?>