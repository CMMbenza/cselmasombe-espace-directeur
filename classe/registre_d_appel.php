<?php
// /directeur/classe/registre_d_appel.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // fournit $pdo, e(), BASE_URL + session + anti-cache
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$classeId = (int)($_GET['classe_id'] ?? 0);
if ($classeId <= 0) {
    header('Location: ' . BASE_URL . '/classe/');
    exit;
}

// Date d'appel (GET ?date=YYYY-MM-DD) ou aujourd'hui
$dateParam = $_GET['date'] ?? '';
if ($dateParam !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
    $dateAppel = $dateParam;
} else {
    $dateAppel = date('Y-m-d');
}

$error   = '';
$success = '';
$classe  = null;
$eleves  = [];
$statutsExistants   = [];
$absencesSemaine    = [];
$anneeEncours       = null;
$compteurs = [
    'total'   => 0,
    'present' => 0,
    'absent'  => 0,
];

try {
    // Année scolaire encours
    $stmtAn = $pdo->query("
        SELECT annee_scolaire 
        FROM annee_scolaire 
        WHERE status='encours' 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $anneeEncours = $stmtAn->fetchColumn() ?: null;

    // Infos classe
    $stmtClasse = $pdo->prepare("
        SELECT c.id, c.description AS classe_desc, cy.description AS cycle_desc
        FROM classe c
        LEFT JOIN cycle cy ON cy.id = c.cycle
        WHERE c.id = :id
        LIMIT 1
    ");
    $stmtClasse->execute([':id' => $classeId]);
    $classe = $stmtClasse->fetch(PDO::FETCH_ASSOC);
    if (!$classe) {
        throw new RuntimeException("Classe introuvable.");
    }

    // Traitement POST (enregistrement de l'appel)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $datePost = $_POST['date_appel'] ?? $dateAppel;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePost)) {
            throw new RuntimeException("Date d'appel invalide.");
        }
        $dateAppel = $datePost;

        $statuts   = $_POST['statut']   ?? []; // statut[eleve_id]
        $remarques = $_POST['remarque'] ?? []; // remarque[eleve_id]

        $pdo->beginTransaction();

        // Récupérer / créer l'en-tête appel
        $paramsAppel = [
            ':classe_id'  => $classeId,
            ':date_appel' => $dateAppel,
        ];
        $sqlFindAppel = "
            SELECT id 
            FROM appel 
            WHERE classe_id = :classe_id 
              AND date_appel = :date_appel
        ";
        if ($anneeEncours !== null) {
            $sqlFindAppel .= " AND anneeScolaire = :annee";
            $paramsAppel[':annee'] = $anneeEncours;
        } else {
            $sqlFindAppel .= " AND anneeScolaire IS NULL";
        }

        $stmtFind = $pdo->prepare($sqlFindAppel . " LIMIT 1");
        $stmtFind->execute($paramsAppel);
        $appelId = (int)$stmtFind->fetchColumn();

        if ($appelId <= 0) {
            $userId = $_SESSION['user_id'] ?? null;

            $sqlInsertAppel = "
                INSERT INTO appel (classe_id, date_appel, anneeScolaire, created_by)
                VALUES (:classe_id, :date_appel, :anneeScolaire, :created_by)
            ";
            $stmtIns = $pdo->prepare($sqlInsertAppel);
            $stmtIns->execute([
                ':classe_id'     => $classeId,
                ':date_appel'    => $dateAppel,
                ':anneeScolaire' => $anneeEncours,
                ':created_by'    => $userId,
            ]);
            $appelId = (int)$pdo->lastInsertId();
        }

        // Récupérer tous les élèves de la classe (année en cours si dispo)
        $paramsEle = [':classe_id' => $classeId];
        $sqlEle = "
            SELECT e.id
            FROM eleve e
            WHERE e.classe = :classe_id
        ";
        if ($anneeEncours !== null) {
            $sqlEle .= " AND e.anneeScolaire = :annee";
            $paramsEle[':annee'] = $anneeEncours;
        }
        $stmtEle = $pdo->prepare($sqlEle);
        $stmtEle->execute($paramsEle);
        $idsEleves = $stmtEle->fetchAll(PDO::FETCH_COLUMN);

        // Préparer les requêtes détail
        $stmtSelDetail = $pdo->prepare("
            SELECT id, statut 
            FROM appel_detail
            WHERE appel_id = :appel_id AND eleve_id = :eleve_id
            LIMIT 1
        ");
        $stmtInsDetail = $pdo->prepare("
            INSERT INTO appel_detail (appel_id, eleve_id, statut, remarque)
            VALUES (:appel_id, :eleve_id, :statut, :remarque)
        ");
        $stmtUpdDetail = $pdo->prepare("
            UPDATE appel_detail
            SET statut = :statut, remarque = :remarque
            WHERE id = :id
        ");

        foreach ($idsEleves as $idEleve) {
            $idEleve = (int)$idEleve;
            $statut = $statuts[(string)$idEleve] ?? 'present';
            if (!in_array($statut, ['present','absent'], true)) {
                $statut = 'present';
            }
            $remarque = trim((string)($remarques[(string)$idEleve] ?? ''));

            $stmtSelDetail->execute([
                ':appel_id' => $appelId,
                ':eleve_id' => $idEleve,
            ]);
            $detail = $stmtSelDetail->fetch(PDO::FETCH_ASSOC);

            if ($detail) {
                $stmtUpdDetail->execute([
                    ':statut'   => $statut,
                    ':remarque' => $remarque !== '' ? $remarque : null,
                    ':id'       => $detail['id'],
                ]);
            } else {
                $stmtInsDetail->execute([
                    ':appel_id' => $appelId,
                    ':eleve_id' => $idEleve,
                    ':statut'   => $statut,
                    ':remarque' => $remarque !== '' ? $remarque : null,
                ]);
            }
        }

        $pdo->commit();
        $success = "Registre d'appel enregistré pour le {$dateAppel}.";
    }

    // Recharger l'appel et les statuts pour affichage
    $paramsAppel2 = [
        ':classe_id'  => $classeId,
        ':date_appel' => $dateAppel,
    ];
    $sqlFindAppel2 = "
        SELECT id 
        FROM appel 
        WHERE classe_id = :classe_id 
          AND date_appel = :date_appel
    ";
    if ($anneeEncours !== null) {
        $sqlFindAppel2 .= " AND anneeScolaire = :annee";
        $paramsAppel2[':annee'] = $anneeEncours;
    } else {
        $sqlFindAppel2 .= " AND anneeScolaire IS NULL";
    }
    $stmtFind2 = $pdo->prepare($sqlFindAppel2 . " LIMIT 1");
    $stmtFind2->execute($paramsAppel2);
    $appelId = (int)$stmtFind2->fetchColumn();

    if ($appelId > 0) {
        $stmtDetails = $pdo->prepare("
            SELECT eleve_id, statut, remarque
            FROM appel_detail
            WHERE appel_id = :appel_id
        ");
        $stmtDetails->execute([':appel_id' => $appelId]);
        foreach ($stmtDetails->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $eid = (int)$row['eleve_id'];
            $statutsExistants[$eid] = [
                'statut'   => $row['statut'],
                'remarque' => $row['remarque'],
            ];
        }
    }

    // Charger les élèves de la classe
    $paramsEle2 = [':classe_id' => $classeId];
    $sqlEleves = "
        SELECT e.id, e.nom, e.postnom, e.prenom, e.genre, e.anneeScolaire
        FROM eleve e
        WHERE e.classe = :classe_id
    ";
    if ($anneeEncours !== null) {
        $sqlEleves .= " AND e.anneeScolaire = :annee";
        $paramsEle2[':annee'] = $anneeEncours;
    }
    $sqlEleves .= " ORDER BY e.nom, e.postnom, e.prenom";

    $stmtEleves2 = $pdo->prepare($sqlEleves);
    $stmtEleves2->execute($paramsEle2);
    $eleves = $stmtEleves2->fetchAll(PDO::FETCH_ASSOC);

    // Calcul des compteurs présents/absents du jour
    foreach ($eleves as $el) {
        $compteurs['total']++;
        $eid = (int)$el['id'];
        $stat = $statutsExistants[$eid]['statut'] ?? 'present';
        if ($stat === 'absent') {
            $compteurs['absent']++;
        } else {
            $compteurs['present']++;
        }
    }

    // 🔴 Calcul des absences dans la semaine (pour la classe)
    if ($eleves) {
        $paramsWeek = [
            ':classe_id'  => $classeId,
            ':date_appel' => $dateAppel,
        ];
        $sqlWeek = "
            SELECT ad.eleve_id, COUNT(*) AS nb_absences
            FROM appel_detail ad
            JOIN appel a ON a.id = ad.appel_id
            WHERE ad.statut = 'absent'
              AND a.classe_id = :classe_id
              AND YEARWEEK(a.date_appel, 1) = YEARWEEK(:date_appel, 1)
        ";
        if ($anneeEncours !== null) {
            $sqlWeek .= " AND a.anneeScolaire = :annee";
            $paramsWeek[':annee'] = $anneeEncours;
        }
        $sqlWeek .= " GROUP BY ad.eleve_id";

        $stmtWeek = $pdo->prepare($sqlWeek);
        $stmtWeek->execute($paramsWeek);
        foreach ($stmtWeek->fetchAll(PDO::FETCH_ASSOC) as $w) {
            $absencesSemaine[(int)$w['eleve_id']] = (int)$w['nb_absences'];
        }
    }

} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0">Registre d'appel</h1>
      <?php if ($classe): ?>
        <div class="text-muted small">
          Classe : <strong><?= e($classe['classe_desc']) ?></strong>
          <?php if (!empty($classe['cycle_desc'])): ?>
            — Cycle : <strong><?= e($classe['cycle_desc']) ?></strong>
          <?php endif; ?>
          <?php if ($anneeEncours): ?>
            — Année scolaire : <strong><?= e($anneeEncours) ?></strong>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <a href="<?= BASE_URL ?>/classe/" class="btn btn-sm btn-outline-secondary">
        ← Retour aux classes
      </a>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>

  <!-- Choix de la date -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="classe_id" value="<?= (int)$classeId ?>">
        <div class="col-12 col-sm-4 col-md-3">
          <label class="form-label">Date de l'appel</label>
          <input type="date" name="date" class="form-control form-control-sm" value="<?= e($dateAppel) ?>">
        </div>
        <div class="col-12 col-sm-auto">
          <button class="btn btn-sm btn-outline-secondary mt-3 mt-sm-0">Changer la date</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 🔍 Recherche instantanée -->
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <label class="form-label mb-1">Recherche instantanée (Nom élève)</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text">🔎</span>
        <input id="filterName" type="text" class="form-control" placeholder="Tapez un nom, postnom ou prénom…">
        <button id="btnClearFilter" class="btn btn-outline-secondary" type="button">Effacer</button>
      </div>
      <div class="form-text">Filtre côté navigateur, en écoutant la saisie du clavier.</div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <?php if (!$eleves): ?>
        <p class="mb-0"><em>Aucun élève trouvé pour cette classe.</em></p>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="date_appel" value="<?= e($dateAppel) ?>">

          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="text-muted small">
              Total : <strong><span id="totalCount"><?= $compteurs['total'] ?></span></strong> élève(s) |
              Présents : <strong class="text-success"><?= $compteurs['present'] ?></strong> |
              Absents : <strong class="text-danger"><?= $compteurs['absent'] ?></strong>
              <br>
              <span class="small">
                <span class="badge bg-danger">Ligne rouge</span> = élève avec ≥ 3 absences cette semaine.
              </span>
            </div>
            <div class="d-flex gap-2">
              <button type="button" id="btnAllPresent" class="btn btn-sm btn-outline-success">
                Tout cocher Présent(e)
              </button>
              <button type="button" id="btnAllAbsent" class="btn btn-sm btn-outline-danger">
                Tout cocher Absent(e)
              </button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm align-middle" id="elevesTable">
              <thead class="table-light">
                <tr>
                  <th style="width:1%;">#</th>
                  <th>Élève</th>
                  <th>Genre</th>
                  <th>Année scol.</th>
                  <th class="text-center" style="width:160px;">Statut</th>
                  <th class="text-center" style="width:120px;">Abs. semaine</th>
                  <th>Remarque</th>
                  <th style="width:1%;">Détail</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($eleves as $idx => $el):
                  $fullName = trim($el['nom'].' '.$el['postnom'].' '.$el['prenom']);
                  $idEleve = (int)$el['id'];
                  $ex = $statutsExistants[$idEleve] ?? ['statut' => 'present', 'remarque' => ''];
                  $statut   = $ex['statut'] ?? 'present';
                  $remarque = $ex['remarque'] ?? '';
                  $nbAbsSem = (int)($absencesSemaine[$idEleve] ?? 0);
                  $rowClass = $nbAbsSem >= 3 ? 'table-danger' : '';
                  $dataName = mb_strtolower($fullName, 'UTF-8');
                ?>
                  <tr class="<?= $rowClass ?>" data-name="<?= e($dataName) ?>">
                    <td><?= $idx+1 ?></td>
                    <td><?= e($fullName) ?></td>
                    <td><?= e($el['genre']) ?></td>
                    <td><?= e($el['anneeScolaire']) ?></td>
                    <td class="text-center">
                      <div class="btn-group btn-group-sm" role="group">
                        <input type="radio"
                               class="btn-check statut-present"
                               name="statut[<?= $idEleve ?>]"
                               id="statut-present-<?= $idEleve ?>"
                               value="present"
                               <?= $statut === 'present' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-success" for="statut-present-<?= $idEleve ?>">Présent(e)</label>

                        <input type="radio"
                               class="btn-check statut-absent"
                               name="statut[<?= $idEleve ?>]"
                               id="statut-absent-<?= $idEleve ?>"
                               value="absent"
                               <?= $statut === 'absent' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-danger" for="statut-absent-<?= $idEleve ?>">Absent(e)</label>
                      </div>
                    </td>
                    <td class="text-center">
                      <?php if ($nbAbsSem > 0): ?>
                        <span class="badge <?= $nbAbsSem >= 3 ? 'bg-danger' : 'bg-warning' ?>">
                          <?= $nbAbsSem ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">0</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <input type="text"
                             name="remarque[<?= $idEleve ?>]"
                             class="form-control form-control-sm"
                             placeholder="Remarque (facultatif)"
                             value="<?= e((string)$remarque) ?>">
                    </td>
                    <td class="text-nowrap">
                      <a href="<?= BASE_URL ?>/classe/detail_d_appel.php?eleve_id=<?= $idEleve ?>"
                         class="btn btn-sm btn-outline-secondary">
                        Détail
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <div id="clientNoMatch" class="alert alert-info py-2 d-none mb-0">
              Aucun élève ne correspond à votre filtre.
            </div>
          </div>

          <div class="mt-3 d-flex justify-content-between align-items-center">
            <div class="text-muted small">
              Vérifiez bien avant d'enregistrer le registre d'appel.
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
              Enregistrer le registre d'appel
            </button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script>
// Boutons "tout présent" / "tout absent"
(function(){
  const btnAllPresent = document.getElementById('btnAllPresent');
  const btnAllAbsent  = document.getElementById('btnAllAbsent');

  btnAllPresent?.addEventListener('click', () => {
    document.querySelectorAll('.statut-present').forEach(input => {
      input.checked = true;
    });
  });

  btnAllAbsent?.addEventListener('click', () => {
    document.querySelectorAll('.statut-absent').forEach(input => {
      input.checked = true;
    });
  });
})();

// 🔍 Recherche instantanée sur la saisie clavier
(function(){
  const input = document.getElementById('filterName');
  const btnClear = document.getElementById('btnClearFilter');
  const table = document.getElementById('elevesTable');
  const noMatch = document.getElementById('clientNoMatch');
  const totalCount = document.getElementById('totalCount');

  function normalize(str){
    return (str || '').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();
  }

  function applyFilters(){
    const q = normalize((input?.value || '').trim());
    const rows = table.querySelectorAll('tbody tr[data-name]');
    let shown = 0;

    rows.forEach(tr => {
      const name = normalize(tr.getAttribute('data-name') || '');
      const match = q === '' ? true : name.includes(q);
      tr.style.display = match ? '' : 'none';
      if (match) shown++;
    });

    if (noMatch) noMatch.classList.toggle('d-none', shown > 0);
    if (totalCount) totalCount.textContent = String(shown);
  }

  input?.addEventListener('input', applyFilters);
  btnClear?.addEventListener('click', () => {
    input.value = '';
    applyFilters();
    input.focus();
  });

  // initial
  applyFilters();
})();
</script>
