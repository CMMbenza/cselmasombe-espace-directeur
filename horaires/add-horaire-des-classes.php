<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur();
require_once __DIR__.'/../includes/get_annee_scolaire_encours.php';

$error_msg   = '';
$success_msg = '';

// --- TRAITEMENT DE L'ENREGISTREMENT DE L'HORAIRE ---
if (isset($_POST['save_horaires']) && is_array($_POST['horaires'] ?? null)) {
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

            $insertedCount = 0;
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
                    $insertedCount++;
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
        $error_msg = "Veuillez sélectionner une classe valide.";
    }
}

// Données de base : Liste des classes
$classes = $pdo->query("
    SELECT classe.id, CONCAT(classe.description ,' ', cy.description) AS description 
    FROM classe 
    JOIN cycle cy ON cy.id = classe.cycle 
    ORDER BY description
")->fetchAll(PDO::FETCH_ASSOC);

// Récupération globale des cours par classe
$stmtCours = $pdo->query("SELECT id, intitule, classe_id FROM cours ORDER BY intitule");
$allCours  = $stmtCours->fetchAll(PDO::FETCH_ASSOC);

// Récupération des enseignants affectés par classe
$stmtAffectations = $pdo->query("
    SELECT DISTINCT a.classe_id, a.agent_id, CONCAT(ag.nom, ' ', ag.prenom) AS prof_nom
    FROM affectation_prof_classe a
    JOIN agent ag ON ag.id = a.agent_id
    ORDER BY ag.nom, ag.prenom
");
$allAffectations = $stmtAffectations->fetchAll(PDO::FETCH_ASSOC);

// Structuration du mapping JS
$mappingClasseData = [];
foreach ($classes as $c) {
    $cId = (int)$c['id'];
    $mappingClasseData[$cId] = [
        'cours' => array_values(array_filter($allCours, fn($item) => (int)$item['classe_id'] === $cId)),
        'profs' => array_values(array_filter($allAffectations, fn($item) => (int)$item['classe_id'] === $cId))
    ];
}

require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';
?>

<div class="container-fluid mt-4 mb-5 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>⚡ Générateur d'Horaire Automatique</h4>
        <a href="index.php" class="btn btn-secondary">⬅️ Retour au tableau général</a>
    </div>

    <?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <form method="post" id="form-horaire">
        <!-- ETAPE 1 : CONFIGURATION GLOBALE DE LA CLASSE ET DE LA DURÉE -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white fw-bold">
                1. Configuration de la Classe et des Plages Horaires
            </div>
            <div class="card-body bg-light">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Classe concernée :</label>
                        <select name="global_classe_id" id="global_classe_id" class="form-select form-select-lg" required onchange="onClasseSelectChange()">
                            <option value="">-- Sélectionner --</option>
                            <?php foreach($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['description']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Type de Créneaux :</label>
                        <select name="global_type" id="global_type" class="form-select form-select-lg" required onchange="onTypeSelectChange()">
                            <option value="Cours">Cours</option>
                            <option value="Interrogation">Interrogation</option>
                            <option value="Examen">Examen</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Durée d'un cours :</label>
                        <select id="global_duree" class="form-select form-select-lg" onchange="recalculateAllEndTimes()">
                            <option value="45">45 min</option>
                            <option value="50">50 min</option>
                            <option value="55">55 min</option>
                            <option value="60" selected>1 heure (60 min)</option>
                            <option value="90">1h30 (90 min)</option>
                            <option value="120">2 heures (120 min)</option>
                        </select>
                    </div>

                    <div class="col-md-4 d-flex gap-2">
                        <button type="button" class="btn btn-warning btn-lg flex-fill fw-bold" id="btn-generate" onclick="generateHoraireAutomatique()">
                            ⚡ Générer l'horaire
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-lg" id="btn-reset" onclick="resetHoraire()">
                            🔄 Réinitialiser
                        </button>
                    </div>
                </div>

                <!-- PANNEAU D'INFORMATIONS DE LA CLASSE -->
                <div id="info-classe-box" class="mt-3 p-3 bg-white border rounded d-none">
                    <div class="row">
                        <div class="col-md-6 border-end">
                            <h6 class="fw-bold text-primary mb-2">📚 Cours attribués (<span id="count-cours">0</span>) :</h6>
                            <ul id="list-cours" class="mb-0 small text-muted ps-3"></ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-success mb-2">👨‍🏫 Professeurs affectés (<span id="count-profs">0</span>) :</h6>
                            <ul id="list-profs" class="mb-0 small text-muted ps-3"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ETAPE 2 : TABLEAU DES CRÉNEAUX GÉNÉRÉS ET AJUSTABLES -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">2. Ajustement des créneaux horaires</span>
                <button type="button" class="btn btn-success btn-sm" onclick="addNewManualRow()">➕ Ajouter un créneau manuel</button>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle m-0" id="table-horaire">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 140px;">Jour</th>
                            <th style="width: 150px;">Date précise</th>
                            <th style="width: 110px;">Début</th>
                            <th style="width: 110px;">Fin (Auto)</th>
                            <th>Cours</th>
                            <th>Enseignant</th>
                            <th style="width: 50px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="rows-container">
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Veuillez sélectionner une classe puis cliquer sur "Générer l'horaire".</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white text-end py-3">
                <button type="submit" name="save_horaires" class="btn btn-primary btn-lg fw-bold">💾 Enregistrer directement cet horaire</button>
            </div>
        </div>
    </form>
</div>

<script>
const classeDataMap = <?= json_encode($mappingClasseData) ?>;
const joursSemaine = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi"];
let rowIndex = 0;

// Fonction de calcul de l'heure de fin à partir de l'heure de début + durée
function calculateEndTime(startTimeStr, durationMinutes) {
    if (!startTimeStr) return "09:00";
    
    const parts = startTimeStr.split(':');
    let hours = parseInt(parts[0], 10);
    let minutes = parseInt(parts[1], 10);

    let totalMinutes = hours * 60 + minutes + parseInt(durationMinutes, 10);

    let endHours = Math.floor(totalMinutes / 60) % 24;
    let endMinutes = totalMinutes % 60;

    const formattedHours = String(endHours).padStart(2, '0');
    const formattedMinutes = String(endMinutes).padStart(2, '0');

    return `${formattedHours}:${formattedMinutes}`;
}

// Mise à jour de l'heure de fin d'une ligne spécifique
function updateRowEndTime(index) {
    const startInput = document.querySelector(`input[name="horaires[${index}][heure_debut]"]`);
    const endInput   = document.querySelector(`input[name="horaires[${index}][heure_fin]"]`);
    const duration   = document.getElementById('global_duree').value;

    if (startInput && endInput) {
        endInput.value = calculateEndTime(startInput.value, duration);
    }
}

// Recalculer l'heure de fin pour toutes les lignes
function recalculateAllEndTimes() {
    const rows = document.querySelectorAll('#rows-container tr');
    rows.forEach(tr => {
        if (tr.id.startsWith('row-')) {
            const idx = tr.id.replace('row-', '');
            updateRowEndTime(idx);
        }
    });
}

// Callback sélection classe
function onClasseSelectChange() {
    const classeId = document.getElementById('global_classe_id').value;
    const infoBox  = document.getElementById('info-classe-box');
    const listCours = document.getElementById('list-cours');
    const listProfs = document.getElementById('list-profs');

    if (!classeId || !classeDataMap[classeId]) {
        infoBox.classList.add('d-none');
        resetHoraire();
        return;
    }

    const data = classeDataMap[classeId];

    listCours.innerHTML = '';
    document.getElementById('count-cours').textContent = data.cours.length;
    if (data.cours.length > 0) {
        data.cours.forEach(c => listCours.innerHTML += `<li>${c.intitule}</li>`);
    } else {
        listCours.innerHTML = '<li class="text-danger">Aucun cours disponible</li>';
    }

    listProfs.innerHTML = '';
    document.getElementById('count-profs').textContent = data.profs.length;
    if (data.profs.length > 0) {
        data.profs.forEach(p => listProfs.innerHTML += `<li>${p.prof_nom}</li>`);
    } else {
        listProfs.innerHTML = '<li class="text-danger">Aucun professeur affecté</li>';
    }

    infoBox.classList.remove('d-none');
    resetHoraire();
}

// Génération dynamique répétable
function generateHoraireAutomatique() {
    const classeId = document.getElementById('global_classe_id').value;
    const duration = parseInt(document.getElementById('global_duree').value, 10);

    if (!classeId) {
        alert('Veuillez d\'abord sélectionner une classe !');
        return;
    }

    const data = classeDataMap[classeId];

    if (!data || data.cours.length === 0) {
        alert('Cette classe ne possède aucun cours paramétré.');
        return;
    }

    document.getElementById('rows-container').innerHTML = '';
    rowIndex = 0;

    let coursIndex = Math.floor(Math.random() * data.cours.length);

    // Définition dynamique des créneaux de la journée basés sur la durée choisie
    const baseStartTimes = ["08:00", "09:15", "10:30", "11:45"];

    joursSemaine.forEach(jour => {
        baseStartTimes.forEach(startTime => {
            const currentCours = data.cours[coursIndex % data.cours.length];
            const currentProf  = data.profs.length > 0 ? data.profs[coursIndex % data.profs.length].agent_id : null;

            addRow({
                jour: jour,
                heure_debut: startTime,
                heure_fin: calculateEndTime(startTime, duration),
                cours_id: currentCours.id,
                prof_id: currentProf
            });

            coursIndex++;
        });
    });
}

// Ajouter une ligne dans le tableau
function addRow(dataPreset = null) {
    const container = document.getElementById('rows-container');
    const tr = document.createElement('tr');
    tr.id = `row-${rowIndex}`;
    const currentIndex = rowIndex;
    const duration = document.getElementById('global_duree').value;

    const selectedJour  = dataPreset ? dataPreset.jour : "Lundi";
    const selectedDebut = dataPreset ? dataPreset.heure_debut : "08:00";
    const selectedFin   = dataPreset ? dataPreset.heure_fin : calculateEndTime(selectedDebut, duration);

    tr.innerHTML = `
        <td>
            <select name="horaires[${currentIndex}][jour_semaine]" class="form-select form-select-sm" required>
                ${joursSemaine.concat(["Samedi"]).map(j => `<option value="${j}" ${j === selectedJour ? 'selected' : ''}>${j}</option>`).join('')}
            </select>
        </td>
        <td>
            <input type="date" name="horaires[${currentIndex}][date_evenement]" id="date-${currentIndex}" class="form-control form-select-sm" disabled>
        </td>
        <td>
            <input type="time" name="horaires[${currentIndex}][heure_debut]" class="form-control form-select-sm" required value="${selectedDebut}" onchange="updateRowEndTime(${currentIndex})">
        </td>
        <td>
            <input type="time" name="horaires[${currentIndex}][heure_fin]" class="form-control form-select-sm" required value="${selectedFin}">
        </td>
        <td>
            <select name="horaires[${currentIndex}][cours_id]" id="cours-${currentIndex}" class="form-select form-select-sm" required>
                <option value="">-- Choisir le cours --</option>
            </select>
        </td>
        <td>
            <select name="horaires[${currentIndex}][prof_id]" id="prof-${currentIndex}" class="form-select form-select-sm" required>
                <option value="">-- Choisir le prof --</option>
            </select>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(${currentIndex})">❌</button>
        </td>
    `;

    container.appendChild(tr);
    populateRowOptions(currentIndex, dataPreset ? dataPreset.cours_id : null, dataPreset ? dataPreset.prof_id : null);
    updateDateInputState(currentIndex);
    rowIndex++;
}

function populateRowOptions(index, selectedCoursId = null, selectedProfId = null) {
    const classeId    = document.getElementById('global_classe_id').value;
    const coursSelect = document.getElementById(`cours-${index}`);
    const profSelect  = document.getElementById(`prof-${index}`);

    if (!coursSelect || !profSelect) return;

    coursSelect.innerHTML = '<option value="">-- Sélectionner cours --</option>';
    profSelect.innerHTML  = '<option value="">-- Sélectionner prof --</option>';

    if (classeId && classeDataMap[classeId]) {
        const data = classeDataMap[classeId];

        data.cours.forEach(c => {
            const selected = selectedCoursId && parseInt(selectedCoursId) === parseInt(c.id) ? 'selected' : '';
            coursSelect.innerHTML += `<option value="${c.id}" ${selected}>${c.intitule}</option>`;
        });

        data.profs.forEach(p => {
            const selected = selectedProfId && parseInt(selectedProfId) === parseInt(p.agent_id) ? 'selected' : '';
            profSelect.innerHTML += `<option value="${p.agent_id}" ${selected}>${p.prof_nom}</option>`;
        });
    }
}

function addNewManualRow() {
    const classeId = document.getElementById('global_classe_id').value;
    if (!classeId) {
        alert('Veuillez d\'abord choisir une classe.');
        return;
    }
    const container = document.getElementById('rows-container');
    if (container.children.length === 1 && container.children[0].cells.length === 1) {
        container.innerHTML = '';
    }
    addRow();
}

function updateDateInputState(index) {
    const type = document.getElementById('global_type').value;
    const dateInput = document.getElementById(`date-${index}`);
    if (!dateInput) return;

    if (type === 'Examen' || type === 'Interrogation') {
        dateInput.disabled = false;
        dateInput.required = true;
    } else {
        dateInput.disabled = true;
        dateInput.required = false;
        dateInput.value = '';
    }
}

function onTypeSelectChange() {
    const rows = document.querySelectorAll('#rows-container tr');
    rows.forEach(tr => {
        if (tr.id.startsWith('row-')) {
            const idx = tr.id.replace('row-', '');
            updateDateInputState(idx);
        }
    });
}

function resetHoraire() {
    document.getElementById('rows-container').innerHTML = `
        <tr>
            <td colspan="7" class="text-center py-4 text-muted">Créneaux remis à zéro. Cliquez sur "Générer l'horaire" pour reconstruire.</td>
        </tr>
    `;
    rowIndex = 0;
}

function removeRow(index) {
    const row = document.getElementById(`row-${index}`);
    if (row) row.remove();
}
</script>

<?php require_once __DIR__.'/../layout/footer.php'; ?>