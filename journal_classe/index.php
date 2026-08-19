<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur();
require_once __DIR__.'/../includes/get_annee_scolaire_encours.php';

// --- ACTIONS (CRÉER, MODIFIER, SUPPRIMER, CHANGER STATUT) ---
$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prof_id     = (int)$_POST['prof_id'];
    $classe_id   = (int)$_POST['classe_id'];
    $cours_id    = (int)$_POST['cours_id'];
    $jour_date   = $_POST['jour_date'];
    $matieres    = trim($_POST['matieres']);
    $note        = trim($_POST['note'] ?? '');
    $statut      = $_POST['statut'] ?? 'valider';
    $currentYear = ANNEE_SCOLAIRE_LIBELLE;

    // --- CRÉATION ---
    if (isset($_POST['add_journal'])) {
        $piece_jointe = null;
        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/attachement_journal_de_class/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
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
        header('Location: index.php?msg=added');
        exit();
    }

    // --- MODIFICATION ---
    if (isset($_POST['edit_journal'])) {
        $edit_id = (int)$_POST['edit_id'];
        
        // Récupérer la pièce jointe existante au cas où elle ne change pas
        $stmtFile = $pdo->prepare("SELECT piece_jointe FROM journal_classe WHERE id = ?");
        $stmtFile->execute([$edit_id]);
        $currentFile = $stmtFile->fetchColumn();

        if (isset($_FILES['piece_jointe']) && $_FILES['piece_jointe']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/attachement_journal_de_class/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = time() . '_' . basename($_FILES['piece_jointe']['name']);
            if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $uploadDir . $filename)) {
                // Supprimer l'ancien fichier s'il existe
                if ($currentFile && file_exists($uploadDir . $currentFile)) {
                    @unlink($uploadDir . $currentFile);
                }
                $currentFile = $filename;
            }
        }

        $stmt = $pdo->prepare("
            UPDATE journal_classe 
            SET jour_date = ?, prof_id = ?, classe_id = ?, cours_id = ?, matieres = ?, note = ?, piece_jointe = ?, statut = ?
            WHERE id = ?
        ");
        $stmt->execute([$jour_date, $prof_id, $classe_id, $cours_id, $matieres, $note, $currentFile, $statut, $edit_id]);
        header('Location: index.php?msg=updated');
        exit();
    }
}

// Action rapide de validation / rejet
if ($action === 'change_statut' && $id > 0 && isset($_GET['new_statut'])) {
    $stmt = $pdo->prepare("UPDATE journal_classe SET statut = ? WHERE id = ?");
    $stmt->execute([$_GET['new_statut'], $id]);
    header('Location: index.php?msg=statut_updated');
    exit();
}

// Suppression
if ($action === 'delete' && $id > 0) {
    // Récupérer et supprimer la pièce jointe du serveur
    $stmtFile = $pdo->prepare("SELECT piece_jointe FROM journal_classe WHERE id = ?");
    $stmtFile->execute([$id]);
    $fileToDelete = $stmtFile->fetchColumn();
    if ($fileToDelete) {
        @unlink(__DIR__ . '/../uploads/attachement_journal_de_class/' . $fileToDelete);
    }

    $stmt = $pdo->prepare("DELETE FROM journal_classe WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: index.php?msg=deleted');
    exit();
}

// Données de formulaires
$profs   = $pdo->query("SELECT id, CONCAT(nom, ' ', prenom) AS nom_complet FROM agent ORDER BY nom")->fetchAll();
$classes = $pdo->query("SELECT classe.id, CONCAT(classe.description ,' ', cy.description) AS description FROM classe LEFT JOIN cycle cy ON cy.id = classe.cycle ORDER BY description")->fetchAll();
$cours   = $pdo->query("SELECT id, intitule FROM cours ORDER BY intitule")->fetchAll();

// Recherche & Filtrage
$statut_filter = $_GET['statut'] ?? '';
$where  = ["j.anneScolaire = ?"];
$params = [ANNEE_SCOLAIRE_LIBELLE];

if ($statut_filter !== '') {
    $where[]  = "j.statut = ?";
    $params[] = $statut_filter;
}

$sql = "
    SELECT j.*, 
           CONCAT(u.nom, ' ', u.prenom) AS prof_nom,
           CONCAT(c.description ,' ', cy.description) AS classe_nom,
           co.intitule AS cours_nom
    FROM journal_classe j
    LEFT JOIN agent u ON u.id = j.prof_id
    LEFT JOIN classe c ON c.id = j.classe_id
    LEFT JOIN cycle cy ON cy.id = c.cycle
    LEFT JOIN cours co ON co.id = j.cours_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY j.jour_date DESC, j.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$journals = $stmt->fetchAll();

require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>📖 Journal de Classe (Directeur)</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">+ Nouveau Journal</button>
    </div>

    <!-- FILTRES -->
    <div class="card card-body bg-light mb-3 border-0 shadow-sm">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="statut" class="form-select" onchange="this.form.submit()">
                    <option value="#" selected disabled>Tous les statuts</option>
                    <option value="en attente" <?= $statut_filter === 'en attente' ? 'selected' : '' ?>>⏳ En attente
                    </option>
                    <option value="valider" <?= $statut_filter === 'valider' ? 'selected' : '' ?>>✅ Validé</option>
                    <option value="rejeter" <?= $statut_filter === 'rejeter' ? 'selected' : '' ?>>❌ Rejeté</option>
                </select>
            </div>
            <div class="col-md-2">
                <a href="index.php" class="btn btn-outline-secondary w-100">Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- TABLEAU DE CONSULTATION -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover align-middle m-0 text-center">
                <thead class="table">
                    <tr>
                        <th>Date</th>
                        <th>Professeur</th>
                        <th>Classe</th>
                        <th>Cours</th>
                        <th class="text-start">Matières / Leçon</th>
                        <th>Fichier</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($journals)): ?>
                    <tr>
                        <td colspan="8" class="py-4 text-muted">Aucun journal de classe trouvé pour cette année.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($journals as $j): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($j['jour_date'])) ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($j['prof_nom'] ?? 'Inconnu') ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($j['classe_nom'] ?? 'N/A') ?></span>
                        </td>
                        <td><?= htmlspecialchars($j['cours_nom'] ?? 'N/A') ?></td>
                        <td class="text-start">
                            <div class="fw-bold text-dark">
                                <?= htmlspecialchars(mb_strimwidth($j['matieres'], 0, 60, '...')) ?>
                            </div>
                            <?php if (!empty($j['note'])): ?>
                            <small class="text-muted d-block"><em>Note:
                                    <?= htmlspecialchars(mb_strimwidth($j['note'], 0, 40, '...')) ?></em></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($j['piece_jointe'])): ?>
                            <a href="../../uploads/attachement_journal_de_class/<?= urlencode($j['piece_jointe']) ?>"
                                target="_blank" class="btn btn-sm btn-info" title="Voir le fichier">
                                📎 Fichier
                            </a>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($j['statut'] === 'valider'): ?>
                            <span class="badge bg-success">Validé</span>
                            <?php elseif ($j['statut'] === 'rejeter'): ?>
                            <span class="badge bg-danger">Rejeté</span>
                            <?php else: ?>
                            <span class="badge bg-warning text-dark">En attente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <!-- Bouton Modifier avec transmission de données via data-* -->
                                <button type="button" class="btn btn-sm btn-outline-primary edit-btn"
                                    data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?= $j['id'] ?>"
                                    data-date="<?= $j['jour_date'] ?>" data-prof="<?= $j['prof_id'] ?>"
                                    data-classe="<?= $j['classe_id'] ?>" data-cours="<?= $j['cours_id'] ?>"
                                    data-matieres="<?= htmlspecialchars($j['matieres']) ?>"
                                    data-note="<?= htmlspecialchars($j['note'] ?? '') ?>"
                                    data-statut="<?= $j['statut'] ?>" title="Modifier">
                                    ✏️
                                </button>

                                <?php if ($j['statut'] !== 'valider'): ?>
                                <a href="index.php?action=change_statut&id=<?= $j['id'] ?>&new_statut=valider"
                                    class="btn btn-sm btn-success" title="Valider">✓</a>
                                <?php endif; ?>
                                <?php if ($j['statut'] !== 'rejeter'): ?>
                                <a href="index.php?action=change_statut&id=<?= $j['id'] ?>&new_statut=rejeter"
                                    class="btn btn-sm btn-warning" title="Rejeter">✕</a>
                                <?php endif; ?>
                                <a href="index.php?action=delete&id=<?= $j['id'] ?>" class="btn btn-sm btn-danger"
                                    onclick="return confirm('Supprimer ce journal ?')" title="Supprimer">🗑️</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL AJOUT -->
<div class="modal modal-xl fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une entrée au Journal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2">
                <div class="col-12">
                    <label class="form-label">Date :</label>
                    <input type="date" name="jour_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Professeur :</label>
                    <select name="prof_id" class="form-select" required>
                        <?php foreach($profs as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom_complet']) ?></option>
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
                <div class="col-6">
                    <label class="form-label">Cours :</label>
                    <select name="cours_id" class="form-select" required>
                        <?php foreach($cours as $co): ?>
                        <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['intitule']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Sujet / Leçon dispensée :</label>
                    <textarea name="matieres" class="form-control" rows="3" required></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Remarques / Observations :</label>
                    <textarea name="note" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Pièce jointe (facultatif) :</label>
                    <input type="file" name="piece_jointe" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_journal" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL MODIFICATION -->
<div class="modal modal-xl fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" enctype="multipart/form-data" class="modal-content">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le Journal de Classe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body row g-2">
                <div class="col-12">
                    <label class="form-label">Date :</label>
                    <input type="date" name="jour_date" id="edit_date" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Professeur :</label>
                    <select name="prof_id" id="edit_prof" class="form-select" required>
                        <?php foreach($profs as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nom_complet']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Classe :</label>
                    <select name="classe_id" id="edit_classe" class="form-select" required>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Cours :</label>
                    <select name="cours_id" id="edit_cours" class="form-select" required>
                        <?php foreach($cours as $co): ?>
                        <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['intitule']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Statut :</label>
                    <select name="statut" id="edit_statut" class="form-select" required>
                        <option value="en attente">⏳ En attente</option>
                        <option value="valider">✅ Validé</option>
                        <option value="rejeter">❌ Rejeté</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Sujet / Leçon dispensée :</label>
                    <textarea name="matieres" id="edit_matieres" class="form-control" rows="3" required></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Remarques / Observations :</label>
                    <textarea name="note" id="edit_note" class="form-control" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Remplacer la pièce jointe (facultatif) :</label>
                    <input type="file" name="piece_jointe" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="edit_journal" class="btn btn-warning">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPT JS POUR ALIMENTER LA MODAL D'ÉDITION -->
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
</script>

<?php require_once __DIR__.'/../layout/footer.php'; ?>