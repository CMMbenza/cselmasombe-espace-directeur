<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur();
require_once __DIR__.'/../includes/get_annee_scolaire_encours.php';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);
$msg    = $_GET['msg'] ?? '';
$currentYear = ANNEE_SCOLAIRE_LIBELLE;

// Date du jour au format YYYY-MM-DD
$today = date('Y-m-d');

// --- ACTIONS SUR LE JOURNAL DE CLASSE & RÉSUMÉS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- CRÉATION / MODIFICATION JOURNAL ---
    if (isset($_POST['add_journal']) || isset($_POST['edit_journal'])) {
        $prof_id     = (int)$_POST['prof_id'];
        $classe_id   = (int)$_POST['classe_id'];
        $cours_id    = (int)$_POST['cours_id'];
        $jour_date   = $_POST['jour_date'];
        $matieres    = trim($_POST['matieres']);
        $note        = trim($_POST['note'] ?? '');
        $statut      = $_POST['statut'] ?? 'valider';

        if (isset($_POST['add_journal'])) {
            $piece_jointe = null;
            if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '../../uploads/attachement_journal_de_class/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $filename = time() . '_' . basename($_FILES['piece_jointe']['name']);
                if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $uploadDir . $filename)) {
                    $piece_jointe = $filename;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO journal_classe (jour_date, prof_id, classe_id, cours_id, anneScolaire, matieres, note, piece_jointe, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$jour_date, $prof_id, $classe_id, $cours_id, $currentYear, $matieres, $note, $piece_jointe, $statut]);
            header("Location: index.php?classe_id=$classe_id&msg=added");
            exit();
        }

        if (isset($_POST['edit_journal'])) {
            $edit_id = (int)$_POST['edit_id'];
            $stmtFile = $pdo->prepare("SELECT piece_jointe FROM journal_classe WHERE id = ?");
            $stmtFile->execute([$edit_id]);
            $currentFile = $stmtFile->fetchColumn();

            if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../uploads/attachement_journal_de_class/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $filename = time() . '_' . basename($_FILES['piece_jointe']['name']);
                if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $uploadDir . $filename)) {
                    if ($currentFile && file_exists($uploadDir . $currentFile)) @unlink($uploadDir . $currentFile);
                    $currentFile = $filename;
                }
            }

            $stmt = $pdo->prepare("
                UPDATE journal_classe 
                SET jour_date = ?, prof_id = ?, classe_id = ?, cours_id = ?, matieres = ?, note = ?, piece_jointe = ?, statut = ?
                WHERE id = ?
            ");
            $stmt->execute([$jour_date, $prof_id, $classe_id, $cours_id, $matieres, $note, $currentFile, $statut, $edit_id]);
            header("Location: index.php?classe_id=$classe_id&msg=updated");
            exit();
        }
    }

    // --- ENREGISTREMENT / MODIFICATION DU RÉSUMÉ ---
    if (isset($_POST['save_resume'])) {
        $resume_id           = (int)($_POST['resume_id'] ?? 0);
        $journal_id          = (int)$_POST['journal_id'];
        $jour_date           = $_POST['jour_date'] ?? '';
        $fiche_no            = trim($_POST['fiche_no'] ?? '');
        $type_lecon          = trim($_POST['type_lecon'] ?? '');
        $discipline          = trim($_POST['discipline'] ?? '');
        $competence_attendue = trim($_POST['competence_attendue'] ?? '');
        $resume_texte        = trim($_POST['resume_texte'] ?? '');
        $devoir              = trim($_POST['devoir'] ?? '');
        $redirect_classe     = (int)($_POST['redirect_classe_id'] ?? 0);

        if ($journal_id > 0 && !empty($jour_date)) {
            $stmtDate = $pdo->prepare("UPDATE journal_classe SET jour_date = ? WHERE id = ?");
            $stmtDate->execute([$jour_date, $journal_id]);
        }

        $currentFile = null;
        if ($resume_id > 0) {
            $stmtFile = $pdo->prepare("SELECT piece_jointe FROM resume_cours WHERE id = ?");
            $stmtFile->execute([$resume_id]);
            $currentFile = $stmtFile->fetchColumn();
        }

        if (isset($_FILES['resume_piece_jointe']) && $_FILES['resume_piece_jointe']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/attachement_resume_cours/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $filename = time() . '_resume_' . basename($_FILES['resume_piece_jointe']['name']);
            if (move_uploaded_file($_FILES['resume_piece_jointe']['tmp_name'], $uploadDir . $filename)) {
                if ($currentFile && file_exists($uploadDir . $currentFile)) @unlink($uploadDir . $currentFile);
                $currentFile = $filename;
            }
        }

        if ($resume_id > 0) {
            $stmt = $pdo->prepare("
                UPDATE resume_cours 
                SET fiche_no = ?, type_lecon = ?, discipline = ?, competence_attendue = ?, resume_texte = ?, devoir = ?, piece_jointe = ?
                WHERE id = ?
            ");
            $stmt->execute([$fiche_no, $type_lecon, $discipline, $competence_attendue, $resume_texte, $devoir, $currentFile, $resume_id]);
        }

        header("Location: index.php?classe_id=$redirect_classe&msg=resume_saved");
        exit();
    }
}

// --- SUPPRESSION UNIQUE DE LA LEÇON / RÉSUMÉ ---
if ($action === 'delete_resume' && $id > 0) {
    $target_classe = (int)($_GET['classe_id'] ?? 0);
    
    $stmtFile = $pdo->prepare("SELECT piece_jointe FROM resume_cours WHERE id = ?");
    $stmtFile->execute([$id]);
    $fileToDelete = $stmtFile->fetchColumn();
    if ($fileToDelete) {
        @unlink(__DIR__ . '/../uploads/attachement_resume_cours/' . $fileToDelete);
    }

    $stmt = $pdo->prepare("DELETE FROM resume_cours WHERE id = ?");
    $stmt->execute([$id]);

    header("Location: index.php?classe_id=$target_classe&msg=resume_deleted_only");
    exit();
}

// --- AUTRES ACTIONS DE L'ADMINISTRATEUR ---
if ($action === 'valider_tout') {
    $target_classe = (int)($_GET['classe_id'] ?? 0);
    if ($target_classe > 0) {
        $stmt = $pdo->prepare("UPDATE journal_classe SET statut = 'valider' WHERE anneScolaire = ? AND classe_id = ? AND statut = 'en attente' AND jour_date = ?");
        $stmt->execute([$currentYear, $target_classe, $today]);
        header("Location: index.php?classe_id=$target_classe&msg=all_validated");
        exit();
    } else {
        $stmt = $pdo->prepare("UPDATE journal_classe SET statut = 'valider' WHERE anneScolaire = ? AND statut = 'en attente' AND jour_date = ?");
        $stmt->execute([$currentYear, $today]);
        header("Location: index.php?msg=all_validated");
        exit();
    }
}

if ($action === 'change_statut' && $id > 0 && isset($_GET['new_statut'])) {
    $target_classe = (int)($_GET['classe_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE journal_classe SET statut = ? WHERE id = ?");
    $stmt->execute([$_GET['new_statut'], $id]);
    header("Location: index.php?classe_id=$target_classe&msg=statut_updated");
    exit();
}

if ($action === 'delete' && $id > 0) {
    $target_classe = (int)($_GET['classe_id'] ?? 0);
    
    $stmtFile = $pdo->prepare("SELECT piece_jointe FROM journal_classe WHERE id = ?");
    $stmtFile->execute([$id]);
    $fileToDelete = $stmtFile->fetchColumn();
    if ($fileToDelete) @unlink(__DIR__ . '/../../uploads/attachement_journal_de_class/' . $fileToDelete);

    $stmtResFile = $pdo->prepare("SELECT id, piece_jointe FROM resume_cours WHERE journal_id = ?");
    $stmtResFile->execute([$id]);
    $resData = $stmtResFile->fetch(PDO::FETCH_ASSOC);
    if ($resData) {
        if ($resData['piece_jointe']) @unlink(__DIR__ . '/../uploads/attachement_resume_cours/' . $resData['piece_jointe']);
        $pdo->prepare("DELETE FROM resume_cours WHERE id = ?")->execute([$resData['id']]);
    }

    $stmt = $pdo->prepare("DELETE FROM journal_classe WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?classe_id=$target_classe&msg=deleted");
    exit();
}

// --- DONNÉES DE BASE ---
$profs   = $pdo->query("SELECT id, CONCAT(nom, ' ', prenom) AS nom_complet FROM agent ORDER BY nom")->fetchAll();
$classes = $pdo->query("SELECT classe.id, CONCAT(classe.description ,' ', IFNULL(cy.description, '')) AS description FROM classe LEFT JOIN cycle cy ON cy.id = classe.cycle ORDER BY description")->fetchAll();
$cours   = $pdo->query("SELECT id, intitule FROM cours ORDER BY intitule")->fetchAll();

// PARAMÈTRES DE RECHERCHE ET FILTRES
$selected_classe = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : 0;
$statut_filter   = $_GET['statut'] ?? '';
$single_date     = $_GET['single_date'] ?? '';
$date_debut      = $_GET['date_debut'] ?? '';
$date_fin        = $_GET['date_fin'] ?? '';
$view_all        = isset($_GET['view_all']) && $_GET['view_all'] == '1';

// CONSTRUCTION DE LA REQUÊTE SQL
$where  = ["j.anneScolaire = ?"];
$params = [$currentYear];

if ($selected_classe > 0) {
    $where[]  = "j.classe_id = ?";
    $params[] = $selected_classe;
}

if (!empty($single_date)) {
    $where[]  = "j.jour_date = ?";
    $params[] = $single_date;
} elseif (!empty($date_debut) || !empty($date_fin)) {
    if (!empty($date_debut)) {
        $where[]  = "j.jour_date >= ?";
        $params[] = $date_debut;
    }
    if (!empty($date_fin)) {
        $where[]  = "j.jour_date <= ?";
        $params[] = $date_fin;
    }
} elseif (!$view_all) {
    // PAR DÉFAUT (si on n'a pas cliqué sur Voir Tout) : Uniquement aujourd'hui
    $where[]  = "j.jour_date = ?";
    $params[] = $today;
}

if ($statut_filter !== '') {
    $where[]  = "j.statut = ?";
    $params[] = $statut_filter;
}

$sql = "
    SELECT j.*, 
           CONCAT(u.nom, ' ', u.prenom) AS prof_nom,
           CONCAT(c.description ,' ', IFNULL(cy.description, '')) AS classe_nom,
           co.intitule AS cours_nom,
           rc.id AS resume_id,
           rc.fiche_no,
           rc.domaine,
           rc.discipline,
           rc.titre_lecon,
           rc.type_lecon,
           rc.competence_attendue,
           rc.resume_texte,
           rc.devoir,
           rc.piece_jointe AS resume_piece_jointe
    FROM journal_classe j
    LEFT JOIN agent u ON u.id = j.prof_id
    LEFT JOIN classe c ON c.id = j.classe_id
    LEFT JOIN cycle cy ON cy.id = c.cycle
    LEFT JOIN cours co ON co.id = j.cours_id
    LEFT JOIN resume_cours rc ON rc.journal_id = j.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY j.jour_date DESC, j.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- EN-TÊTE PRINCIPAL -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1 text-dark">📖 Journal de Classe & Résumés</h3>
                <p class="text-muted mb-0 small">Espace d'administration et de suivi pédagogique (Directeur)</p>
            </div>
            <button class="btn btn-primary rounded-3 px-4 py-2 fw-semibold shadow-sm" data-bs-toggle="modal"
                data-bs-target="#addModal">
                ➕ Nouveau Journal
            </button>
        </div>
    </div>

    <!-- MESSAGE DE NOTIFICATION -->
    <?php if ($msg === 'resume_deleted_only'): ?>
    <div class="alert alert-warning alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
        <strong>🗑️ Leçon supprimée :</strong> Le résumé de cours a bien été supprimé. Le journal de classe est resté
        intact.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- SÉLECTION DE LA CLASSE -->
    <div class="row g-2 mb-4">
        <div class="col-lg-2 col-md-3 col-6">
            <a href="index.php#tableauResume"
                class="card border-0 shadow-sm rounded-3 text-decoration-none text-center p-3 transition-all <?= ($selected_classe === 0) ? 'bg-primary text-white' : 'bg-white text-dark hover-shadow' ?>">
                <div class="small fw-semibold text-uppercase tracking-wider opacity-75">Vue Générale</div>
                <div class="fs-6 fw-bold mt-1">🏫 Toutes les classes</div>
            </a>
        </div>
        <?php foreach ($classes as $cl): ?>
        <?php $isActive = ($selected_classe === (int)$cl['id']); ?>
        <div class="col-lg-2 col-md-3 col-6">
            <a href="?classe_id=<?= $cl['id'] ?>#tableauResume"
                class="card border-0 shadow-sm rounded-3 text-decoration-none text-center p-3 transition-all <?= $isActive ? 'bg-primary text-white' : 'bg-white text-dark hover-shadow' ?>">
                <div class="small fw-semibold text-uppercase tracking-wider opacity-75">Classe</div>
                <div class="fs-6 fw-bold mt-1">🏫 <?= htmlspecialchars($cl['description']) ?></div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- FILTRES DE RECHERCHE -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
            <form method="get" class="row g-3 align-items-end">

                <div class="row mb-3">
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small fw-bold text-secondary">Classe :</label>
                        <select name="classe_id" class="form-select rounded-3 border-light-subtle">
                            <option value="0">Toutes les classes</option>
                            <?php foreach ($classes as $cl): ?>
                            <option value="<?= $cl['id'] ?>"
                                <?= $selected_classe === (int)$cl['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cl['description']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small fw-bold text-secondary">Date précise :</label>
                        <input type="date" name="single_date" class="form-control rounded-3 border-light-subtle"
                            value="<?= htmlspecialchars($single_date) ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small fw-bold text-secondary">Du :</label>
                        <input type="date" name="date_debut" class="form-control rounded-3 border-light-subtle"
                            value="<?= htmlspecialchars($date_debut) ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small fw-bold text-secondary">Au :</label>
                        <input type="date" name="date_fin" class="form-control rounded-3 border-light-subtle"
                            value="<?= htmlspecialchars($date_fin) ?>">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label small fw-bold text-secondary">Statut :</label>
                        <select name="statut" class="form-select rounded-3 border-light-subtle">
                            <option value="">Tous les statuts</option>
                            <option value="en attente" <?= $statut_filter === 'en attente' ? 'selected' : '' ?>>⏳ En
                                attente</option>
                            <option value="valider" <?= $statut_filter === 'valider' ? 'selected' : '' ?>>✅ Validé
                            </option>
                            <option value="rejeter" <?= $statut_filter === 'rejeter' ? 'selected' : '' ?>>❌ Rejeté
                            </option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-4 col-md-4 d-flex gap-1">
                        <button type="submit" class="btn btn-primary rounded-3 w-100 fw-semibold"
                            title="Filtrer">Filtrer</button>
                        <a href="index.php?view_all=1<?= $selected_classe > 0 ? '&classe_id='.$selected_classe : '' ?>"
                            class="btn btn-info text-white rounded-3 w-100 fw-semibold text-nowrap"
                            title="Afficher tout l'historique">📜 Voir tout</a>
                        <a href="index.php" class="btn btn-danger rounded-3 w-100 fw-semibold"
                            title="Aujourd'hui">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLEAU -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 bg-white" id="tableauResume">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <?php 
                    if ($view_all) {
                        echo "📜 Tous les journaux (Historique complet)";
                    } elseif ($single_date || $date_debut || $date_fin) {
                        echo "🔍 Résultats filtrés";
                    } else {
                        echo "📅 Journaux d'Aujourd'hui (" . date('d/m/Y') . ")";
                    }
                ?>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-light border-0">
                    <tr class="text-secondary small text-uppercase">
                        <th class="py-3">Date</th>
                        <th class="py-3">Classe</th>
                        <th class="py-3">Professeur</th>
                        <th class="py-3">Cours</th>
                        <th class="py-3 text-start">Matières / Leçon</th>
                        <th class="py-3">Résumé de cours</th>
                        <th class="py-3">Fichier</th>
                        <th class="py-3">Statut</th>
                        <th class="py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php if (empty($journals)): ?>
                    <tr>
                        <td colspan="9" class="py-5 text-muted fw-semibold">
                            Aucun journal trouvé.
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($journals as $j): ?>
                    <tr>
                        <td class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($j['jour_date'])) ?></td>
                        <td><span
                                class="badge bg-primary-subtle text-primary border px-3 py-2 rounded-pill fw-bold"><?= htmlspecialchars($j['classe_nom'] ?? 'N/A') ?></span>
                        </td>
                        <td class="fw-bold text-dark"><?= htmlspecialchars($j['prof_nom'] ?? 'Inconnu') ?></td>
                        <td><span
                                class="badge bg-light text-dark border px-3 py-2 rounded-pill"><?= htmlspecialchars($j['cours_nom'] ?? 'N/A') ?></span>
                        </td>
                        <td class="text-start">
                            <div class="fw-bold text-dark">
                                <?= htmlspecialchars(mb_strimwidth($j['matieres'], 0, 60, '...')) ?></div>
                            <?php if (!empty($j['note'])): ?>
                            <small class="text-muted d-block mt-1"><em>Note:
                                    <?= htmlspecialchars(mb_strimwidth($j['note'], 0, 40, '...')) ?></em></small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if (!empty($j['resume_id'])): ?>
                            <button type="button"
                                class="btn btn-sm btn-info text-white fw-semibold rounded-pill px-3 shadow-sm"
                                onclick='openResumeModal(<?= htmlspecialchars(json_encode($j), ENT_QUOTES, 'UTF-8') ?>)'>
                                📑 Voir / Modifier
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 opacity-75"
                                disabled>
                                ⚠️ Aucun résumé
                            </button>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if (!empty($j['piece_jointe'])): ?>
                            <a href="../../uploads/attachement_journal_de_class/<?= urlencode($j['piece_jointe']) ?>"
                                target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3">📎 Voir</a>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($j['statut'] === 'valider'): ?>
                            <span
                                class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold">Validé</span>
                            <?php elseif ($j['statut'] === 'rejeter'): ?>
                            <span
                                class="badge bg-danger-subtle text-danger px-3 py-2 rounded-pill fw-bold">Rejeté</span>
                            <?php else: ?>
                            <span
                                class="badge bg-warning-subtle text-warning-emphasis px-3 py-2 rounded-pill fw-bold">En
                                attente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group shadow-sm rounded-3 overflow-hidden" role="group">
                                <button type="button" class="btn btn-sm btn-light border edit-btn"
                                    data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?= $j['id'] ?>"
                                    data-date="<?= $j['jour_date'] ?>" data-prof="<?= $j['prof_id'] ?>"
                                    data-classe="<?= $j['classe_id'] ?>" data-cours="<?= $j['cours_id'] ?>"
                                    data-matieres="<?= htmlspecialchars($j['matieres']) ?>"
                                    data-note="<?= htmlspecialchars($j['note'] ?? '') ?>"
                                    data-statut="<?= $j['statut'] ?>" title="Modifier">✏️</button>

                                <?php if ($j['statut'] !== 'valider'): ?>
                                <a href="index.php?action=change_statut&id=<?= $j['id'] ?>&new_statut=valider&classe_id=<?= $selected_classe ?>"
                                    class="btn btn-sm btn-success" title="Valider">✓</a>
                                <?php endif; ?>
                                <?php if ($j['statut'] !== 'rejeter'): ?>
                                <a href="index.php?action=change_statut&id=<?= $j['id'] ?>&new_statut=rejeter&classe_id=<?= $selected_classe ?>"
                                    class="btn btn-sm btn-warning" title="Rejeter">✕</a>
                                <?php endif; ?>
                                <a href="index.php?action=delete&id=<?= $j['id'] ?>&classe_id=<?= $selected_classe ?>"
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Attention : Cela supprimera le journal DE CLASSE ET son résumé de cours associé. Continuer ?')"
                                    title="Supprimer Tout">🗑️</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-4">
        <a href="index.php?action=valider_tout&classe_id=<?= $selected_classe ?>"
            class="btn btn-success btn-md rounded-3 fw-bold px-4 shadow"
            onclick="return confirm('Voulez-vous vraiment valider tous les journaux en attente pour aujourd’hui ?');">
            ⚡ Tout Valider pour Aujourd'hui
        </a>
    </div>

</div>

<!-- MODAL CONSULTATION ET ÉDITION DU RÉSUMÉ -->
<div class="modal fade" id="resumeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="resume_id" id="res_resume_id">
            <input type="hidden" name="journal_id" id="res_journal_id">
            <input type="hidden" name="redirect_classe_id" value="<?= $selected_classe ?>">

            <div class="modal-header bg-info text-white p-4 border-0">
                <h5 class="modal-title fw-bold">Édition de la Leçon (Résumé)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">Date du cours :</label>
                    <input type="date" name="jour_date" id="res_jour_date" class="form-control rounded-3" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">N° Fiche :</label>
                    <input type="text" name="fiche_no" id="res_fiche_no" class="form-control rounded-3">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">Type Leçon :</label>
                    <input type="text" name="type_lecon" id="res_type_lecon" class="form-control rounded-3">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-secondary">Discipline :</label>
                    <input type="text" name="discipline" id="res_discipline" class="form-control rounded-3">
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small text-secondary">Compétence attendue :</label>
                    <textarea name="competence_attendue" id="res_competence" class="form-control rounded-3"
                        rows="2"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small text-secondary">Résumé du cours :</label>
                    <textarea name="resume_texte" id="res_texte" class="form-control rounded-3" rows="4"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small text-secondary">Devoir à domicile :</label>
                    <textarea name="devoir" id="res_devoir" class="form-control rounded-3" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small text-secondary">Pièce jointe (Optionnel) :</label>
                    <input type="file" name="resume_piece_jointe" class="form-control rounded-3">
                    <div class="mt-2" id="res_file_container"></div>
                </div>
            </div>
            <div class="modal-footer p-4 border-0 bg-light-subtle d-flex justify-content-between">
                <div>
                    <a href="#" id="btn_delete_resume" class="btn btn-danger rounded-3 px-3 fw-bold"
                        style="display: none;">
                        🗑️ Supprimer cette leçon uniquement
                    </a>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Fermer</button>
                    <button type="submit" name="save_resume" class="btn btn-success rounded-3 px-4 fw-bold">💾
                        Enregistrer</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- MODAL MODIFICATION JOURNAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="modal-header p-4 border-0">
                <h5 class="modal-title fw-bold">Modifier le Journal de Classe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-secondary">Date :</label>
                    <input type="date" name="jour_date" id="edit_date" class="form-control rounded-3" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-secondary">Professeur :</label>
                    <select name="prof_id" id="edit_prof" class="form-select rounded-3" required>
                        <?php foreach($profs as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom_complet']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-secondary">Classe :</label>
                    <select name="classe_id" id="edit_classe" class="form-select rounded-3" required>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-secondary">Cours :</label>
                    <select name="cours_id" id="edit_cours" class="form-select rounded-3" required>
                        <?php foreach($cours as $co): ?>
                        <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['intitule']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-secondary">Statut :</label>
                    <select name="statut" id="edit_statut" class="form-select rounded-3" required>
                        <option value="en attente">⏳ En attente</option>
                        <option value="valider">✅ Validé</option>
                        <option value="rejeter">❌ Rejeté</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small text-secondary">Sujet / Leçon dispensée :</label>
                    <textarea name="matieres" id="edit_matieres" class="form-control rounded-3" rows="3"
                        required></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small text-secondary">Remarques / Observations :</label>
                    <textarea name="note" id="edit_note" class="form-control rounded-3" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold small text-secondary">Remplacer la pièce jointe (facultatif)
                        :</label>
                    <input type="file" name="piece_jointe" class="form-control rounded-3">
                </div>
            </div>
            <div class="modal-footer p-4 border-0 bg-light-subtle">
                <button type="submit" name="edit_journal" class="btn btn-warning rounded-3 px-4 fw-bold">Mettre à
                    jour</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editButtons = document.querySelectorAll('.edit-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_date').value = this.dataset.date;
            document.getElementById('edit_prof').value = this.dataset.prof;
            document.getElementById('edit_classe').value = this.dataset.classe;
            document.getElementById('edit_cours').value = this.dataset.cours;
            document.getElementById('edit_matieres').value = this.dataset.matieres;
            document.getElementById('edit_note').value = this.dataset.note;
            document.getElementById('edit_statut').value = this.dataset.statut;
        });
    });
});

function openResumeModal(data) {
    const resumeId = data.resume_id || 0;
    document.getElementById('res_resume_id').value = resumeId;
    document.getElementById('res_journal_id').value = data.id;
    document.getElementById('res_jour_date').value = data.jour_date || '';
    document.getElementById('res_fiche_no').value = data.fiche_no || '';
    document.getElementById('res_type_lecon').value = data.type_lecon || '';
    document.getElementById('res_discipline').value = data.discipline || '';
    document.getElementById('res_competence').value = data.competence_attendue || '';
    document.getElementById('res_texte').value = data.resume_texte || '';
    document.getElementById('res_devoir').value = data.devoir || '';

    const btnDelete = document.getElementById('btn_delete_resume');
    if (resumeId > 0) {
        btnDelete.href = 'index.php?action=delete_resume&id=' + resumeId + '&classe_id=<?= $selected_classe ?>';
        btnDelete.style.display = 'inline-block';
        btnDelete.onclick = function() {
            return confirm(
                "Voulez-vous vraiment supprimer UNIQUEMENT cette leçon ? Le journal de classe sera conservé.");
        };
    } else {
        btnDelete.style.display = 'none';
    }

    const fileContainer = document.getElementById('res_file_container');
    if (data.resume_piece_jointe) {
        fileContainer.innerHTML = '<a href="../uploads/attachement_resume_cours/' + encodeURIComponent(data
                .resume_piece_jointe) +
            '" target="_blank" class="btn btn-sm btn-outline-primary w-100 fw-bold rounded-3">📄 Fichier joint à la leçon (Ouvrir)</a>';
    } else {
        fileContainer.innerHTML = '<span class="text-muted small">Aucun fichier joint à la leçon.</span>';
    }

    new bootstrap.Modal(document.getElementById('resumeModal')).show();
}
</script>

<?php require_once __DIR__.'/../layout/footer.php'; ?>