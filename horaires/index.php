<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur();
require_once __DIR__.'/../includes/get_annee_scolaire_encours.php';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// --- AJOUT MULTIPLE D'HORAIRES ---
if (isset($_POST['add_horaires']) && is_array($_POST['horaires'] ?? null)) {
    $annee_scolaire_id = ANNEE_SCOLAIRE_ID;
    $classe_id         = (int)($_POST['global_classe_id'] ?? 0);
    $type              = $_POST['global_type'] ?? 'Cours';
    
    if ($classe_id > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO horaire (type, classe_id, cours_id, prof_id, annee_scolaire_id, jour_semaine, date_evenement, heure_debut, heure_fin)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($_POST['horaires'] as $row) {
                $cours_id       = (int)($row['cours_id'] ?? 0);
                $prof_id        = (int)($row['prof_id'] ?? 0);
                $jour_semaine   = $row['jour_semaine'] ?? 'Lundi';
                $date_evenement = (!empty($row['date_evenement']) && in_array($type, ['Interrogation', 'Examen'], true)) ? $row['date_evenement'] : NULL;
                $heure_debut    = $row['heure_debut'] ?? '08:00';
                $heure_fin      = $row['heure_fin'] ?? '09:00';

                if ($cours_id > 0 && $prof_id > 0) {
                    $stmt->execute([
                        $type,
                        $classe_id,
                        $cours_id,
                        $prof_id,
                        $annee_scolaire_id,
                        $jour_semaine,
                        $date_evenement,
                        $heure_debut,
                        $heure_fin
                    ]);
                }
            }
            $pdo->commit();
            header('Location: index.php?msg=added');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    } else {
        $error_msg = "Veuillez sélectionner une classe globale.";
    }
}

// --- MODIFICATION D'UN CRÉNEAU ---
if (isset($_POST['update_horaire'])) {
    $edit_id        = (int)($_POST['edit_id'] ?? 0);
    $type           = $_POST['edit_type'] ?? 'Cours';
    $classe_id      = (int)($_POST['edit_classe_id'] ?? 0);
    $cours_id       = (int)($_POST['edit_cours_id'] ?? 0);
    $prof_id        = (int)($_POST['edit_prof_id'] ?? 0);
    $jour_semaine   = $_POST['edit_jour_semaine'] ?? 'Lundi';
    $date_evenement = (!empty($_POST['edit_date_evenement']) && in_array($type, ['Interrogation', 'Examen'], true)) ? $_POST['edit_date_evenement'] : NULL;
    $heure_debut    = $_POST['edit_heure_debut'] ?? '08:00';
    $heure_fin      = $_POST['edit_heure_fin'] ?? '09:00';

    if ($edit_id > 0 && $classe_id > 0 && $cours_id > 0 && $prof_id > 0) {
        try {
            $stmt = $pdo->prepare("
                UPDATE horaire 
                SET type = ?, classe_id = ?, cours_id = ?, prof_id = ?, jour_semaine = ?, date_evenement = ?, heure_debut = ?, heure_fin = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $type,
                $classe_id,
                $cours_id,
                $prof_id,
                $jour_semaine,
                $date_evenement,
                $heure_debut,
                $heure_fin,
                $edit_id
            ]);
            header('Location: index.php?msg=updated');
            exit();
        } catch (Exception $e) {
            $error_msg = "Erreur lors de la modification : " . $e->getMessage();
        }
    } else {
        $error_msg = "Veuillez remplir correctement tous les champs requis pour la modification.";
    }
}

// --- SUPPRESSION ---
if ($action === 'delete' && $id > 0) {
    $pdo->prepare("DELETE FROM horaire WHERE id = ?")->execute([$id]);
    header('Location: index.php?msg=deleted');
    exit();
}

// Données de base
$classes = $pdo->query("SELECT classe.id, CONCAT(classe.description ,' ', cy.description) AS description FROM classe JOIN cycle cy ON cy.id = classe.cycle ORDER BY description")->fetchAll(PDO::FETCH_ASSOC);

// Récupération des cours filtrés par classe
$stmtCours = $pdo->query("SELECT id, intitule, classe_id FROM cours ORDER BY intitule");
$allCours = $stmtCours->fetchAll(PDO::FETCH_ASSOC);

// Récupération des enseignants affectés par classe
$stmtAffectations = $pdo->query("
    SELECT DISTINCT a.classe_id, a.agent_id, CONCAT(ag.nom, ' ', ag.prenom) AS prof_nom
    FROM affectation_prof_classe a
    JOIN agent ag ON ag.id = a.agent_id
    ORDER BY ag.nom, ag.prenom
");
$allAffectations = $stmtAffectations->fetchAll(PDO::FETCH_ASSOC);

// Structure JSON pour JavaScript
$mappingClasseData = [];
foreach ($classes as $c) {
    $cId = (int)$c['id'];
    
    // Cours de cette classe
    $coursClasse = array_values(array_filter($allCours, fn($item) => (int)$item['classe_id'] === $cId));
    
    // Professeurs affectés à cette classe
    $profsClasse = array_values(array_filter($allAffectations, fn($item) => (int)$item['classe_id'] === $cId));
    
    $mappingClasseData[$cId] = [
        'cours' => $coursClasse,
        'profs' => $profsClasse
    ];
}

// Consultation & Filtres (Classe + Type)
$classe_filter = (int)($_GET['classe_id'] ?? 0);
$type_filter   = $_GET['type'] ?? '';

$where  = ["h.annee_scolaire_id = ?"];
$params = [ANNEE_SCOLAIRE_ID];

if ($classe_filter > 0) {
    $where[]  = "h.classe_id = ?";
    $params[] = $classe_filter;
}

if (!empty($type_filter) && in_array($type_filter, ['Cours', 'Interrogation', 'Examen'], true)) {
    $where[]  = "h.type = ?";
    $params[] = $type_filter;
}

$sql = "
    SELECT h.*, 
    CONCAT (c.description ,' ', cy.description) AS classe_nom, co.intitule AS cours_nom, CONCAT(u.nom, ' ', u.prenom) AS prof_nom
    FROM horaire h
    LEFT JOIN classe c ON c.id = h.classe_id
    LEFT JOIN cours co ON co.id = h.cours_id
    LEFT JOIN cycle cy ON cy.id = c.cycle
    LEFT JOIN agent u ON u.id = h.prof_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY FIELD(h.jour_semaine, 'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'), h.heure_debut ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$horaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';
?>

<div class="container-fluid mt-4 mb-5 px-4">
    <?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>📅 Horaire & Examens (<?= htmlspecialchars(ANNEE_SCOLAIRE_LIBELLE) ?>)</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHoraireModal">+ Programmer des
            créneaux</button>
    </div>

    <!-- FILTRE DE CONSULTATION -->
    <div class="card card-body bg-light mb-3 border-0 shadow-sm">
        <form method="get" class="row g-2">
            <div class="col-md-4">
                <select name="classe_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">Toutes les classes</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classe_filter === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['description']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- FILTRE PAR TYPE -->
            <div class="col-md-3">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="">Tous les types (Cours & Évaluations)</option>
                    <option value="Cours" <?= $type_filter === 'Cours' ? 'selected' : '' ?>>Cours</option>
                    <option value="Interrogation" <?= $type_filter === 'Interrogation' ? 'selected' : '' ?>>
                        Interrogation</option>
                    <option value="Examen" <?= $type_filter === 'Examen' ? 'selected' : '' ?>>Examen</option>
                </select>
            </div>

            <div class="col-md-2">
                <a href="index.php" class="btn btn-danger w-100">Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- TABLEAU DE CONSULTATION -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead class="table">
                    <tr>
                        <th>Jour</th>
                        <th>Type</th>
                        <th>Cours</th>
                        <th>Classe</th>
                        <!-- <th>Date Précise</th> -->
                        <th>Heure</th>
                        <th>Professeur</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($horaires)): ?>
                    <tr>
                        <td colspan="8" class="py-4 text-muted">Aucun horaire programmé.</td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($horaires as $h): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($h['jour_semaine']) ?></td>
                        <td>
                            <?php 
                                // Formatage du texte à afficher dans le badge
                                $displayType = $h['type'];
                                if (in_array($h['type'], ['Interrogation', 'Examen'], true) && !empty($h['date_evenement'])) {
                                    $displayType .= ' ' . date('d/m/Y', strtotime($h['date_evenement']));
                                }
                            ?>
                            <span
                                class="badge bg-<?= $h['type'] === 'Examen' ? 'danger' : ($h['type'] === 'Interrogation' ? 'success text-white' : 'primary') ?>">
                                <?= htmlspecialchars($displayType) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($h['cours_nom'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($h['classe_nom'] ?? 'N/A') ?></td>
                        <td class="d-none">
                            <?php if (in_array($h['type'], ['Interrogation', 'Examen'], true) && !empty($h['date_evenement'])): ?>
                            <span
                                class="badge bg-light text-dark"><?= date('d/m/Y', strtotime($h['date_evenement'])) ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= substr($h['heure_debut'], 0, 5) ?> - <?= substr($h['heure_fin'], 0, 5) ?></td>

                        <td><?= htmlspecialchars($h['prof_nom'] ?? 'N/A') ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning me-1"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($h)) ?>)">✏️ Modifier</button>
                            <a href="index.php?action=delete&id=<?= $h['id'] ?>" class="btn btn-sm btn-danger"
                                onclick="return confirm('Supprimer ce créneau ?')">Supprimer</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL DE MODIFICATION UNIQUE -->
<div class="modal fade" id="editHoraireModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" class="modal-content">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le créneau</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Classe :</label>
                        <select name="edit_classe_id" id="edit_classe_id" class="form-select" required
                            onchange="onEditClasseChange()">
                            <option value="">-- Choisir la classe --</option>
                            <?php foreach($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['description']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Type :</label>
                        <select name="edit_type" id="edit_type" class="form-select" required
                            onchange="updateEditDateFieldState()">
                            <option value="Cours">Cours</option>
                            <option value="Interrogation">Interrogation</option>
                            <option value="Examen">Examen</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Jour de la semaine :</label>
                        <select name="edit_jour_semaine" id="edit_jour_semaine" class="form-select" required>
                            <option value="Lundi">Lundi</option>
                            <option value="Mardi">Mardi</option>
                            <option value="Mercredi">Mercredi</option>
                            <option value="Jeudi">Jeudi</option>
                            <option value="Vendredi">Vendredi</option>
                            <option value="Samedi">Samedi</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Date précise :</label>
                        <input type="date" name="edit_date_evenement" id="edit_date_evenement" class="form-control">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Heure Début :</label>
                        <input type="time" name="edit_heure_debut" id="edit_heure_debut" class="form-control" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Heure Fin :</label>
                        <input type="time" name="edit_heure_fin" id="edit_heure_fin" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Cours :</label>
                        <select name="edit_cours_id" id="edit_cours_id" class="form-select" required>
                            <option value="">-- Sélectionner cours --</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Enseignant :</label>
                        <select name="edit_prof_id" id="edit_prof_id" class="form-select" required>
                            <option value="">-- Sélectionner prof --</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="update_horaire" class="btn btn-warning">Enregistrer les
                    modifications</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CRÉATION DYNAMIQUE -->
<div class="modal fade" id="addHoraireModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajout multiple de créneaux</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">

                <!-- SELECTION GLOBALE : CLASSE & TYPE -->
                <div class="card card-body bg-light border-0 mb-3 shadow-sm">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sélectionner la Classe :</label>
                            <select name="global_classe_id" id="global_classe_id" class="form-select form-select-lg"
                                required onchange="onGlobalClasseChange()">
                                <option value="">-- Choisissez la classe --</option>
                                <?php foreach($classes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['description']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Type de Créneaux :</label>
                            <select name="global_type" id="global_type" class="form-select form-select-lg" required
                                onchange="onGlobalTypeChange()">
                                <option value="Cours">Cours</option>
                                <option value="Interrogation">Interrogation</option>
                                <option value="Examen">Examen</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- TABLEAU DES CRÉNEAUX -->
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="table-dynamic">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 130px;">Jour</th>
                                <th style="width: 150px;">Date précise</th>
                                <th style="width: 110px;">Début</th>
                                <th style="width: 110px;">Fin</th>
                                <th>Cours</th>
                                <th>Enseignant</th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody id="rows-container">
                            <!-- Lignes dynamiques JS -->
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-success btn-sm mt-2" id="add-row-btn">
                    ➕ Ajouter un créneau
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" name="add_horaires" class="btn btn-primary">Enregistrer tout</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let rowIndex = 0;

    // Structure JSON envoyée par PHP
    const classeDataMap = <?= json_encode($mappingClasseData) ?>;

    function addRow() {
        const container = document.getElementById('rows-container');
        const tr = document.createElement('tr');
        tr.id = `row-${rowIndex}`;
        const currentIndex = rowIndex;

        tr.innerHTML = `
            <td>
                <select name="horaires[${currentIndex}][jour_semaine]" class="form-select form-select-sm" required>
                    <option value="Lundi">Lundi</option>
                    <option value="Mardi">Mardi</option>
                    <option value="Mercredi">Mercredi</option>
                    <option value="Jeudi">Jeudi</option>
                    <option value="Vendredi">Vendredi</option>
                    <option value="Samedi">Samedi</option>
                </select>
            </td>
            <td>
                <input type="date" name="horaires[${currentIndex}][date_evenement]" id="date-${currentIndex}" class="form-control form-select-sm" disabled>
            </td>
            <td>
                <input type="time" name="horaires[${currentIndex}][heure_debut]" class="form-control form-select-sm" required value="08:00">
            </td>
            <td>
                <input type="time" name="horaires[${currentIndex}][heure_fin]" class="form-control form-select-sm" required value="09:00">
            </td>
            <td>
                <select name="horaires[${currentIndex}][cours_id]" id="cours-${currentIndex}" class="form-select form-select-sm" required>
                    <option value="">-- Choisir classe d'abord --</option>
                </select>
            </td>
            <td>
                <select name="horaires[${currentIndex}][prof_id]" id="prof-${currentIndex}" class="form-select form-select-sm" required>
                    <option value="">-- Choisir classe d'abord --</option>
                </select>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(${currentIndex})">❌</button>
            </td>
        `;

        container.appendChild(tr);
        populateRowSelects(currentIndex);
        updateDateFieldState(currentIndex);
        rowIndex++;
    }

    function populateRowSelects(index) {
        const globalClasseId = document.getElementById('global_classe_id').value;
        const coursSelect = document.getElementById(`cours-${index}`);
        const profSelect = document.getElementById(`prof-${index}`);

        if (!coursSelect || !profSelect) return;

        coursSelect.innerHTML = '<option value="">-- Sélectionner cours --</option>';
        profSelect.innerHTML = '<option value="">-- Sélectionner prof --</option>';

        if (globalClasseId && classeDataMap[globalClasseId]) {
            const data = classeDataMap[globalClasseId];

            if (data.cours && data.cours.length > 0) {
                data.cours.forEach(c => {
                    coursSelect.innerHTML += `<option value="${c.id}">${c.intitule}</option>`;
                });
            } else {
                coursSelect.innerHTML = '<option value="">Aucun cours trouvé</option>';
            }

            if (data.profs && data.profs.length > 0) {
                data.profs.forEach(p => {
                    profSelect.innerHTML += `<option value="${p.agent_id}">${p.prof_nom}</option>`;
                });
            } else {
                profSelect.innerHTML = '<option value="">Aucun prof affecté</option>';
            }
        }
    }

    function updateDateFieldState(index) {
        const globalType = document.getElementById('global_type').value;
        const dateInput = document.getElementById(`date-${index}`);

        if (!dateInput) return;

        if (globalType === 'Examen' || globalType === 'Interrogation') {
            dateInput.disabled = false;
            dateInput.required = true;
        } else {
            dateInput.disabled = true;
            dateInput.required = false;
            dateInput.value = '';
        }
    }

    window.onGlobalClasseChange = function() {
        const rows = document.querySelectorAll('#rows-container tr');
        rows.forEach(tr => {
            const idx = tr.id.replace('row-', '');
            populateRowSelects(idx);
        });
    };

    window.onGlobalTypeChange = function() {
        const rows = document.querySelectorAll('#rows-container tr');
        rows.forEach(tr => {
            const idx = tr.id.replace('row-', '');
            updateDateFieldState(idx);
        });
    };

    window.removeRow = function(index) {
        const row = document.getElementById(`row-${index}`);
        if (row) {
            row.remove();
        }
    };

    // --- FONCTIONS JS POUR MODAL MODIFICATION ---
    window.openEditModal = function(data) {
        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_classe_id').value = data.classe_id;
        document.getElementById('edit_type').value = data.type;
        document.getElementById('edit_jour_semaine').value = data.jour_semaine;
        document.getElementById('edit_heure_debut').value = data.heure_debut.substr(0, 5);
        document.getElementById('edit_heure_fin').value = data.heure_fin.substr(0, 5);
        document.getElementById('edit_date_evenement').value = data.date_evenement || '';

        // Peupler et présélectionner cours & enseignant
        populateEditSelects(data.classe_id, data.cours_id, data.prof_id);
        updateEditDateFieldState();

        const editModal = new bootstrap.Modal(document.getElementById('editHoraireModal'));
        editModal.show();
    };

    function populateEditSelects(classeId, selectedCoursId = null, selectedProfId = null) {
        const coursSelect = document.getElementById('edit_cours_id');
        const profSelect = document.getElementById('edit_prof_id');

        coursSelect.innerHTML = '<option value="">-- Sélectionner cours --</option>';
        profSelect.innerHTML = '<option value="">-- Sélectionner prof --</option>';

        if (classeId && classeDataMap[classeId]) {
            const data = classeDataMap[classeId];

            if (data.cours && data.cours.length > 0) {
                data.cours.forEach(c => {
                    const isSelected = selectedCoursId && parseInt(selectedCoursId) === parseInt(c.id) ?
                        'selected' : '';
                    coursSelect.innerHTML +=
                        `<option value="${c.id}" ${isSelected}>${c.intitule}</option>`;
                });
            } else {
                coursSelect.innerHTML = '<option value="">Aucun cours disponible</option>';
            }

            if (data.profs && data.profs.length > 0) {
                data.profs.forEach(p => {
                    const isSelected = selectedProfId && parseInt(selectedProfId) === parseInt(p
                        .agent_id) ? 'selected' : '';
                    profSelect.innerHTML +=
                        `<option value="${p.agent_id}" ${isSelected}>${p.prof_nom}</option>`;
                });
            } else {
                profSelect.innerHTML = '<option value="">Aucun prof affecté</option>';
            }
        }
    }

    window.onEditClasseChange = function() {
        const classeId = document.getElementById('edit_classe_id').value;
        populateEditSelects(classeId);
    };

    window.updateEditDateFieldState = function() {
        const type = document.getElementById('edit_type').value;
        const dateInput = document.getElementById('edit_date_evenement');

        if (type === 'Examen' || type === 'Interrogation') {
            dateInput.disabled = false;
            dateInput.required = true;
        } else {
            dateInput.disabled = true;
            dateInput.required = false;
            dateInput.value = '';
        }
    };

    document.getElementById('add-row-btn').addEventListener('click', addRow);

    // Ligne initiale
    addRow();
});
</script>

<?php require_once __DIR__.'/../layout/footer.php'; ?>