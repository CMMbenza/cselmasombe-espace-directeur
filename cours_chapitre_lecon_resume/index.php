<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur();

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// --- AJOUT D'UN CHAPITRE ---
if (isset($_POST['add_chapitre'])) {
    $titre     = trim($_POST['titre']);
    $cours_id  = (int)$_POST['cours_id'];
    $classe_id = (int)$_POST['classe_id'];
    $prof_id   = (int)$_POST['prof_id'];

    $stmt = $pdo->prepare("INSERT INTO cours_chapitres (titre, cours_id, classe_id, prof_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$titre, $cours_id, $classe_id, $prof_id]);
    header('Location: index.php?msg=chap_added');
    exit();
}

// --- AJOUT D'UNE LEÇON DANS UN CHAPITRE ---
if (isset($_POST['add_lecon'])) {
    $chapitre_id = (int)$_POST['chapitre_id'];
    $titre       = trim($_POST['titre']);
    $description = trim($_POST['description'] ?? '');
    $type_format = $_POST['type_format'];
    $fichier     = '';

    if (!empty($_FILES['fichier']['name'])) {
        $uploadDir = __DIR__.'/../../uploads/lecons/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION);
        $fichier = uniqid('lecon_').'.'.$ext;
        move_uploaded_file($_FILES['fichier']['tmp_name'], $uploadDir.$fichier);
    }

    $stmt = $pdo->prepare("INSERT INTO cours_lecons (chapitre_id, titre, description, fichier, type_format) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$chapitre_id, $titre, $description, $fichier, $type_format]);
    header('Location: index.php?msg=lecon_added');
    exit();
}

// --- SUPPRESSION CHAPITRE OU LEÇON ---
if ($action === 'delete_chap' && $id > 0) {
    $pdo->prepare("DELETE FROM cours_chapitres WHERE id = ?")->execute([$id]);
    header('Location: index.php?msg=chap_deleted');
    exit();
}
if ($action === 'delete_lecon' && $id > 0) {
    $stmtFile = $pdo->prepare("SELECT fichier FROM cours_lecons WHERE id = ?");
    $stmtFile->execute([$id]);
    $fileToDelete = $stmtFile->fetchColumn();
    if ($fileToDelete && file_exists(__DIR__.'/../../uploads/lecons/'.$fileToDelete)) {
        @unlink(__DIR__.'/../../uploads/lecons/'.$fileToDelete);
    }

    $pdo->prepare("DELETE FROM cours_lecons WHERE id = ?")->execute([$id]);
    header('Location: index.php?msg=lecon_deleted');
    exit();
}

// --- RÉCUPÉRATION DES CHAPITRES AVEC JOINTURES ---
$chapitresRaw = $pdo->query("
    SELECT ch.*, 
           CONCAT(c.description ,' ', cy.description) AS classe_nom, 
           co.intitule AS cours_nom, 
           CONCAT(u.nom, ' ', u.prenom) AS prof_nom
    FROM cours_chapitres ch
    LEFT JOIN classe c ON c.id = ch.classe_id
    LEFT JOIN cours co ON co.id = ch.cours_id
    LEFT JOIN cycle cy ON cy.id = c.cycle 
    LEFT JOIN agent u ON u.id = ch.prof_id
    ORDER BY co.intitule ASC, c.description ASC, ch.id ASC
")->fetchAll();

// Structure regroupée : [cours_id] => [classe_id] => [liste des chapitres]
$groupedData = [];
foreach ($chapitresRaw as $chap) {
    $cId  = $chap['cours_id'];
    $clId = $chap['classe_id'];

    if (!isset($groupedData[$cId])) {
        $groupedData[$cId] = [
            'cours_nom' => $chap['cours_nom'] ?? 'Cours non spécifié',
            'classes'   => []
        ];
    }

    if (!isset($groupedData[$cId]['classes'][$clId])) {
        $groupedData[$cId]['classes'][$clId] = [
            'classe_nom' => $chap['classe_nom'] ?? 'Classe non spécifiée',
            'chapitres'  => []
        ];
    }

    $groupedData[$cId]['classes'][$clId]['chapitres'][] = $chap;
}

$profs   = $pdo->query("SELECT id, CONCAT(nom, ' ', prenom) AS nom_complet FROM agent ORDER BY nom")->fetchAll();
$classes = $pdo->query("SELECT classe.id, CONCAT(classe.description ,' ', cy.description) AS description FROM classe LEFT JOIN cycle cy ON cy.id = classe.cycle ORDER BY description")->fetchAll();
$cours   = $pdo->query("SELECT id, intitule FROM cours ORDER BY intitule")->fetchAll();

require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>📚 Programme : Cours > Classe > Chapitres</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addChapModal">+ Nouveau Chapitre</button>
    </div>

    <?php if (empty($groupedData)): ?>
        <div class="alert alert-info text-center">Aucun cours ni chapitre enregistré pour le moment.</div>
    <?php endif; ?>

    <!-- NIVEAU 1 : LES COURS -->
    <div class="accordion" id="accordionCours">
        <?php foreach ($groupedData as $coursId => $coursGroup): ?>
            <div class="accordion-item mb-3 border border-primary shadow-sm">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed bg-light text-primary fw-bold fs-5" type="button" data-bs-toggle="collapse" data-bs-target="#coursBlock<?= $coursId ?>">
                        📖 Cours : <?= htmlspecialchars($coursGroup['cours_nom']) ?>
                    </button>
                </h2>
                <div id="coursBlock<?= $coursId ?>" class="accordion-collapse collapse" data-bs-parent="#accordionCours">
                    <div class="accordion-body bg-white">

                        <!-- NIVEAU 2 : LES CLASSES POUR CE COURS -->
                        <div class="accordion" id="accordionClasses<?= $coursId ?>">
                            <?php foreach ($coursGroup['classes'] as $classeId => $classeGroup): ?>
                                <div class="accordion-item mb-2 border">
                                    <h3 class="accordion-header">
                                        <button class="accordion-button collapsed fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#classeBlock<?= $coursId ?>_<?= $classeId ?>">
                                            🏫 Classe : <?= htmlspecialchars($classeGroup['classe_nom']) ?>
                                            <span class="badge bg-secondary ms-2"><?= count($classeGroup['chapitres']) ?> chapitre(s)</span>
                                        </button>
                                    </h3>
                                    <div id="classeBlock<?= $coursId ?>_<?= $classeId ?>" class="accordion-collapse collapse" data-bs-parent="#accordionClasses<?= $coursId ?>">
                                        <div class="accordion-body">

                                            <!-- NIVEAU 3 : LES CHAPITRES ET LEÇONS -->
                                            <?php foreach ($classeGroup['chapitres'] as $chap): 
                                                $stmtL = $pdo->prepare("SELECT * FROM cours_lecons WHERE chapitre_id = ? ORDER BY id ASC");
                                                $stmtL->execute([$chap['id']]);
                                                $lecons = $stmtL->fetchAll();
                                            ?>
                                                <div class="card mb-3 border-0 bg-light shadow-sm">
                                                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="m-0 fw-bold text-dark">📑 Chapitre : <?= htmlspecialchars($chap['titre']) ?></h6>
                                                            <small class="text-muted">Professeur : <?= htmlspecialchars($chap['prof_nom'] ?? 'Inconnu') ?></small>
                                                        </div>
                                                        <div>
                                                            <button class="btn btn-sm btn-success" onclick="openAddLeconModal(<?= $chap['id'] ?>)">+ Leçon</button>
                                                            <a href="index.php?action=delete_chap&id=<?= $chap['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ce chapitre et ses leçons ?')">🗑️</a>
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <ul class="list-group">
                                                            <?php if (empty($lecons)): ?>
                                                                <li class="list-group-item text-muted small fst-italic">Aucune leçon rattachée à ce chapitre.</li>
                                                            <?php endif; ?>
                                                            <?php foreach ($lecons as $l): ?>
                                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <strong><?= htmlspecialchars($l['titre']) ?></strong> 
                                                                        <span class="badge bg-info text-dark"><?= strtoupper(htmlspecialchars($l['type_format'])) ?></span>
                                                                        <?php if (!empty($l['description'])): ?>
                                                                            <p class="mb-0 text-muted small"><?= htmlspecialchars($l['description']) ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div>
                                                                        <?php if (!empty($l['fichier'])): ?>
                                                                            <a href="../../uploads/lecons/<?= urlencode($l['fichier']) ?>" target="_blank" class="btn btn-sm btn-primary">📄 Fichier</a>
                                                                        <?php endif; ?>
                                                                        <a href="index.php?action=delete_lecon&id=<?= $l['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette leçon ?')">🗑️</a>
                                                                    </div>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>

                                        </div>
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

<!-- MODAL CRÉATION CHAPITRE -->
<div class="modal modal-xl fade" id="addChapModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Chapitre</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2">
                <div class="col-12">
                    <label class="form-label">Titre du chapitre :</label>
                    <input type="text" name="titre" class="form-control" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Cours :</label>
                    <select name="cours_id" class="form-select" required>
                        <?php foreach($cours as $co): ?>
                            <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['intitule']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Classe :</label>
                    <select name="classe_id" class="form-select" required>
                        <?php foreach($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Professeur désigné :</label>
                    <select name="prof_id" class="form-select" required>
                        <?php foreach($profs as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom_complet']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_chapitre" class="btn btn-primary">Créer Chapitre</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CRÉATION LEÇON -->
<div class="modal modal-xl fade" id="addLeconModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="chapitre_id" id="modal_chapitre_id">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Leçon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2">
                <div class="col-12">
                    <label class="form-label">Titre de la leçon :</label>
                    <input type="text" name="titre" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Format :</label>
                    <select name="type_format" class="form-select">
                        <option value="pdf">Document PDF</option>
                        <option value="video">Vidéo</option>
                        <option value="audio">Audio</option>
                        <option value="document">Texte / Autre</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Fichier joint :</label>
                    <input type="file" name="fichier" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Description :</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_lecon" class="btn btn-success">Ajouter Leçon</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddLeconModal(chapId) {
    document.getElementById('modal_chapitre_id').value = chapId;
    new bootstrap.Modal(document.getElementById('addLeconModal')).show();
}
</script>

<?php require_once __DIR__.'/../layout/footer.php'; ?>