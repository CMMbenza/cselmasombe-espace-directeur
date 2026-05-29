<?php
// /directeur/cours/ponderation.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$coursId = (int)($_GET['cours_id'] ?? ($_POST['cours_id'] ?? 0));
$msg = '';
$err = '';

if ($coursId <= 0) {
    $err = "Cours introuvable (cours_id manquant).";
}

// 1) Récupérer le cours + classe + cycle
$cours = null;
$isPrimaire = false;
$cycleId = 0;

if (!$err) {
    $stmt = $pdo->prepare("
      SELECT 
        co.id,
        co.intitule,
        co.classe_id,
        c.description AS classe,
        cy.id          AS cycle_id,
        cy.description AS cycle
      FROM cours co
      JOIN classe c      ON c.id = co.classe_id
      LEFT JOIN cycle cy ON cy.id = c.cycle
      WHERE co.id = :id
    ");
    $stmt->execute([':id' => $coursId]);
    $cours = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cours) {
        $err = "Cours introuvable en base.";
    } else {
        $cycleId    = (int)($cours['cycle_id'] ?? 0);
        $cycleLabel = (string)($cours['cycle'] ?? '');

        // Détection du type :
        // PRIMAIRE et MATERNELLE utilisent le même modèle (P1..P6)
        // SECONDAIRE et HUMANITÉS utilisent le même modèle (P1..P4)
        $lower = mb_strtolower($cycleLabel, 'UTF-8');
        $isPrimaire = (
            str_contains($lower, 'prim')   // Primaire
            || str_contains($lower, 'mater') // Maternelle
        );
    }
}

// 2) Périodes utilisées selon le cycle
// Primaire/Maternelle : P1..P6 (P1 P2 EX1 | P3 P4 EX2 | P5 P6 EX3)
// Secondaire / Humanités (et autres) : P1..P4 (P1 P2 EX1 | P3 P4 EX2)
$perCodes = $isPrimaire
    ? ['P1','P2','P3','P4','P5','P6']
    : ['P1','P2','P3','P4'];

$perByCode = [];

// 3) S'assurer que les périodes nécessaires existent pour CE cycle
if (!$err && $cycleId > 0) {
    $pdo->beginTransaction();
    try {
        $needed = [];

        if ($isPrimaire) {
            $needed = [
                ['P1', 'Période 1', 1],
                ['P2', 'Période 2', 2],
                ['P3', 'Période 3', 3],
                ['P4', 'Période 4', 4],
                ['P5', 'Période 5', 5],
                ['P6', 'Période 6', 6],
            ];
        } else {
            $needed = [
                ['P1', 'Période 1', 1],
                ['P2', 'Période 2', 2],
                ['P3', 'Période 3', 3],
                ['P4', 'Période 4', 4],
            ];
        }

        // Table periodes : (id, cycle_id, CODE, libelle, ordre, actif)
        $selectP = $pdo->prepare("
          SELECT id 
          FROM periodes 
          WHERE cycle_id = :cycle_id AND CODE = :code 
          LIMIT 1
        ");
        $insertP = $pdo->prepare("
          INSERT INTO periodes (cycle_id, CODE, libelle, ordre, actif)
          VALUES (:cycle_id, :code, :libelle, :ordre, 1)
        ");

        foreach ($needed as [$code, $libelle, $ordre]) {
            $selectP->execute([
                ':cycle_id' => $cycleId,
                ':code'     => $code,
            ]);
            $row = $selectP->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $insertP->execute([
                    ':cycle_id' => $cycleId,
                    ':code'     => $code,
                    ':libelle'  => $libelle,
                    ':ordre'    => $ordre,
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        $err = "Erreur lors de l'initialisation des périodes.";
    }
} elseif (!$err && $cycleId <= 0) {
    $err = "Cycle non défini pour ce cours.";
}

// 4) Charger les périodes P1..P4 ou P1..P6 pour CE cycle
if (!$err) {
    $inCodes = implode("','", array_map('addslashes', $perCodes));
    $stmt = $pdo->prepare("
      SELECT id, CODE, libelle, ordre, actif
      FROM periodes
      WHERE cycle_id = :cycle_id
        AND CODE IN ('$inCodes')
      ORDER BY ordre
    ");
    $stmt->execute([':cycle_id' => $cycleId]);
    $periodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($periodes as $p) {
        $perByCode[$p['CODE']] = $p;
    }

    foreach ($perCodes as $code) {
        if (!isset($perByCode[$code])) {
            $err = "Impossible de charger la période $code.";
            break;
        }
    }
}

// 5) Traitement du formulaire : insertion / mise à jour des pondérations
// Utilise cours_ponderations(points)
if (!$err && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $points = $_POST['points'] ?? []; // points[periode_id] = valeur

    try {
        $pdo->beginTransaction();

        $sqlUpsert = "
          INSERT INTO cours_ponderations (cours_id, periode_id, points)
          VALUES (:cours_id, :periode_id, :points)
          ON DUPLICATE KEY UPDATE points = VALUES(points)
        ";
        $stmtUp = $pdo->prepare($sqlUpsert);

        foreach ($perCodes as $code) {
            $p         = $perByCode[$code];
            $periodeId = (int)$p['id'];

            $raw  = trim((string)($points[$periodeId] ?? ''));
            if ($raw === '') {
                $valPoints = 0.0;
            } else {
                $valPoints = (float)str_replace(',', '.', $raw);
            }
            if ($valPoints < 0) $valPoints = 0;

            $stmtUp->execute([
                ':cours_id'   => $coursId,
                ':periode_id' => $periodeId,
                ':points'     => $valPoints,
            ]);
        }

        $pdo->commit();
        $msg = "Pondération enregistrée pour ce cours.";
    } catch (Throwable $e) {
        $pdo->rollBack();
        $err = "Erreur lors de l'enregistrement de la pondération.";
    }
}

// 6) Charger les valeurs existantes pour ce cours
$values = [];
foreach ($perCodes as $code) {
    $values[$code] = 0.0;
}

if (!$err) {
    $periodeIds = [];
    foreach ($perCodes as $code) {
        $periodeIds[] = (int)$perByCode[$code]['id'];
    }
    $in = implode(',', $periodeIds);

    $stmt = $pdo->prepare("
      SELECT periode_id, points
      FROM cours_ponderations
      WHERE cours_id = :cours_id
        AND periode_id IN ($in)
    ");
    $stmt->execute([':cours_id' => $coursId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $periodeIdToCode = [];
    foreach ($perCodes as $code) {
        $periodeIdToCode[(int)$perByCode[$code]['id']] = $code;
    }

    foreach ($rows as $r) {
        $pid  = (int)$r['periode_id'];
        $code = $periodeIdToCode[$pid] ?? null;
        if ($code) {
            $values[$code] = (float)$r['points'];
        }
    }
}

// 7) Calculs serveur (valeurs initiales)
if ($isPrimaire) {
    // Primaire / Maternelle : P1 P2 EX1 | P3 P4 EX2 | P5 P6 EX3
    $P1 = $values['P1'] ?? 0;
    $P2 = $values['P2'] ?? 0;
    $P3 = $values['P3'] ?? 0;
    $P4 = $values['P4'] ?? 0;
    $P5 = $values['P5'] ?? 0;
    $P6 = $values['P6'] ?? 0;

    $EX1 = $P1 + $P2;
    $EX2 = $P3 + $P4;
    $EX3 = $P5 + $P6;
    $TOT = $EX1 + $EX2 + $EX3;
} else {
    // Secondaire / Humanités (et autres) : P1 P2 EX1 | P3 P4 EX2
    $P1 = $values['P1'] ?? 0;
    $P2 = $values['P2'] ?? 0;
    $P3 = $values['P3'] ?? 0;
    $P4 = $values['P4'] ?? 0;

    $EX1 = $P1 + $P2;
    $EX2 = $P3 + $P4;
    $TOT = $EX1 + $EX2;
}
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h5 mb-0">Pondération du cours</h1>
    <div>
      <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/cours/">← Retour aux cours</a>
      <a class="btn btn-sm btn-outline-success" href="<?= BASE_URL ?>/cours/statut_periode.php">Status période</a>
    </div>
  </div>

  <?php if ($err): ?>
    <div class="alert alert-danger py-2"><?= e($err) ?></div>
  <?php endif; ?>

  <?php if ($cours): ?>
    <div class="card mb-3 shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-2">Cours sélectionné</h2>
        <div><strong>Cours :</strong> <?= e($cours['intitule']) ?></div>
        <div>
          <strong>Classe :</strong> <?= e($cours['classe']) ?>
          <?= $cours['cycle'] ? ' — Cycle '.e($cours['cycle']) : '' ?>
        </div>
        <div class="text-muted small mt-1">
          <?php if ($isPrimaire): ?>
            Primaire / Maternelle : P1, P2, EX1 — P3, P4, EX2 — P5, P6, EX3.
          <?php else: ?>
            Secondaire / Humanités : P1, P2, EX1 — P3, P4, EX2.
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-success py-2"><?= e($msg) ?></div>
  <?php endif; ?>

  <?php if (!$err && $cours): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3">Formulaire de pondération</h2>
        <p class="text-muted small">
          Saisis les points maximum pour chaque période.  
          Les colonnes EX et TOTAL GÉNÉRAL se recalculent automatiquement.
        </p>

        <form method="post" action="" class="table-responsive">
          <input type="hidden" name="cours_id" value="<?= (int)$coursId ?>">

          <?php if ($isPrimaire): ?>
            <!-- PRIMAIRE / MATERNELLE : P1 P2 EX1 | P3 P4 EX2 | P5 P6 EX3 -->
            <table class="table table-sm align-middle" id="ponderationTable" data-mode="primaire">
              <thead>
                <tr>
                  <th rowspan="2">Cours</th>
                  <th class="text-center" colspan="3">Bloc 1</th>
                  <th class="text-center" colspan="3">Bloc 2</th>
                  <th class="text-center" colspan="3">Bloc 3</th>
                  <th class="text-center" rowspan="2">TOT-GEN<br><span class="small text-muted">(EX1 + EX2 + EX3)</span></th>
                </tr>
                <tr>
                  <th class="text-center">P1</th>
                  <th class="text-center">P2</th>
                  <th class="text-center">EX1<br><span class="small text-muted">(P1+P2)</span></th>

                  <th class="text-center">P3</th>
                  <th class="text-center">P4</th>
                  <th class="text-center">EX2<br><span class="small text-muted">(P3+P4)</span></th>

                  <th class="text-center">P5</th>
                  <th class="text-center">P6</th>
                  <th class="text-center">EX3<br><span class="small text-muted">(P5+P6)</span></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong><?= e($cours['intitule']) ?></strong></td>

                  <?php
                    $pidP1 = (int)$perByCode['P1']['id'];
                    $pidP2 = (int)$perByCode['P2']['id'];
                    $pidP3 = (int)$perByCode['P3']['id'];
                    $pidP4 = (int)$perByCode['P4']['id'];
                    $pidP5 = (int)$perByCode['P5']['id'];
                    $pidP6 = (int)$perByCode['P6']['id'];
                  ?>

                  <!-- P1 -->
                  <td class="text-center" style="min-width:70px;">
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      name="points[<?= $pidP1 ?>]"
                      class="form-control form-control-sm text-center js-p-input"
                      data-code="P1"
                      value="<?= e((string)$values['P1']) ?>"
                    >
                  </td>

                  <!-- P2 -->
                  <td class="text-center" style="min-width:70px;">
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      name="points[<?= $pidP2 ?>]"
                      class="form-control form-control-sm text-center js-p-input"
                      data-code="P2"
                      value="<?= e((string)$values['P2']) ?>"
                    >
                  </td>

                  <!-- EX1 -->
                  <td class="text-center">
                    <span class="badge bg-light text-dark border" id="ex1Val">
                      <?= e((string)$EX1) ?>
                    </span>
                  </td>

                  <!-- P3 -->
                  <td class="text-center" style="min-width:70px;">
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      name="points[<?= $pidP3 ?>]"
                      class="form-control form-control-sm text-center js-p-input"
                      data-code="P3"
                      value="<?= e((string)$values['P3']) ?>"
                    >
                  </td>

                  <!-- P4 -->
                  <td class="text-center" style="min-width:70px;">
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      name="points[<?= $pidP4 ?>]"
                      class="form-control form-control-sm text-center js-p-input"
                      data-code="P4"
                      value="<?= e((string)$values['P4']) ?>"
                    >
                  </td>

                  <!-- EX2 -->
                  <td class="text-center">
                    <span class="badge bg-light text-dark border" id="ex2Val">
                      <?= e((string)$EX2) ?>
                    </span>
                  </td>

                  <!-- P5 -->
                  <td class="text-center" style="min-width:70px;">
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      name="points[<?= $pidP5 ?>]"
                      class="form-control form-control-sm text-center js-p-input"
                      data-code="P5"
                      value="<?= e((string)$values['P5']) ?>"
                    >
                  </td>

                  <!-- P6 -->
                  <td class="text-center" style="min-width:70px;">
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      name="points[<?= $pidP6 ?>]"
                      class="form-control form-control-sm text-center js-p-input"
                      data-code="P6"
                      value="<?= e((string)$values['P6']) ?>"
                    >
                  </td>

                  <!-- EX3 -->
                  <td class="text-center">
                    <span class="badge bg-light text-dark border" id="ex3Val">
                      <?= e((string)$EX3) ?>
                    </span>
                  </td>

                  <!-- TOTAL -->
                  <td class="text-center">
                    <span class="badge bg-primary" id="totVal">
                      <?= e((string)$TOT) ?>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>

          <?php else: ?>
            <!-- SECONDAIRE / HUMANITÉS : P1 P2 EX1 | P3 P4 EX2 -->
            <table class="table table-sm align-middle" id="ponderationTable" data-mode="secondaire">
              <thead>
                <tr>
                  <th rowspan="2">Cours</th>
                  <th class="text-center" colspan="3">Bloc 1</th>
                  <th class="text-center" colspan="3">Bloc 2</th>
                  <th class="text-center" rowspan="2">TOT-GEN<br><span class="small text-muted">(EX1 + EX2)</span></th>
                </tr>
                <tr>
                  <th class="text-center">P1</th>
                  <th class="text-center">P2</th>
                  <th class="text-center">EX1<br><span class="small text-muted">(P1+P2)</span></th>

                  <th class="text-center">P3</th>
                  <th class="text-center">P4</th>
                  <th class="text-center">EX2<br><span class="small text-muted">(P3+P4)</span></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong><?= e($cours['intitule']) ?></strong></td>

                  <?php
                    $pidP1 = (int)$perByCode['P1']['id'];
                    $pidP2 = (int)$perByCode['P2']['id'];
                    $pidP3 = (int)$perByCode['P3']['id'];
                    $pidP4 = (int)$perByCode['P4']['id'];
                  ?>

                  <!-- P1 -->
                  <td class="text-center" style="min-width:70px;">
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      name="points[<?= $pidP1 ?>]"
                      class="form-control form-control-sm text-center js-p-input"
                      data-code="P1"
                      value="<?= e((string)$values['P1']) ?>"
                    >
                  </td>

                  <!-- P2 -->
                  <td class="text-center" style="min-width:70px;">
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      name="points[<?= $pidP2 ?>]"
                      class="form-control form-control-sm text-center js-p-input"
                      data-code="P2"
                      value="<?= e((string)$values['P2']) ?>"
                    >
                  </td>

                  <!-- EX1 -->
                  <td class="text-center">
                    <span class="badge bg-light text-dark border" id="ex1Val">
                      <?= e((string)$EX1) ?>
                    </span>
                  </td>

                  <!-- P3 -->
                  <td class="text-center" style="min-width:70px;">
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      name="points[<?= $pidP3 ?>]"
                      class="form-control form-control-sm text-center js-p-input"
                      data-code="P3"
                      value="<?= e((string)$values['P3']) ?>"
                    >
                  </td>

                  <!-- P4 -->
                  <td class="text-center" style="min-width:70px;">
                    <input
                      type="number"
                      step="0.5"
                      min="0"
                      name="points[<?= $pidP4 ?>]"
                      class="form-control form-control-sm text-center js-p-input"
                      data-code="P4"
                      value="<?= e((string)$values['P4']) ?>"
                    >
                  </td>

                  <!-- EX2 -->
                  <td class="text-center">
                    <span class="badge bg-light text-dark border" id="ex2Val">
                      <?= e((string)$EX2) ?>
                    </span>
                  </td>

                  <!-- TOTAL -->
                  <td class="text-center">
                    <span class="badge bg-primary" id="totVal">
                      <?= e((string)$TOT) ?>
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          <?php endif; ?>

          <div class="mt-3 d-flex justify-content-end">
            <button class="btn btn-sm btn-primary">
              Enregistrer la pondération
            </button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script>
// Recalcul EX1, EX2, EX3 (si primaire), TOT en direct
(function(){
  const table = document.getElementById('ponderationTable');
  if (!table) return;

  const mode    = table.getAttribute('data-mode') || 'secondaire';
  const inputs  = table.querySelectorAll('.js-p-input');
  const ex1Span = document.getElementById('ex1Val');
  const ex2Span = document.getElementById('ex2Val');
  const ex3Span = document.getElementById('ex3Val'); // peut être null en secondaire
  const totSpan = document.getElementById('totVal');

  function val(code) {
    const inp = table.querySelector('.js-p-input[data-code="'+code+'"]');
    if (!inp) return 0;
    const raw = (inp.value || '').toString().replace(',', '.');
    const v   = parseFloat(raw);
    return isNaN(v) ? 0 : v;
  }

  function updateTotals() {
    let ex1 = 0, ex2 = 0, ex3 = 0;

    if (mode === 'primaire') {
      // Primaire / Maternelle : EX1 = P1+P2 ; EX2 = P3+P4 ; EX3 = P5+P6
      ex1 = val('P1') + val('P2');
      ex2 = val('P3') + val('P4');
      ex3 = val('P5') + val('P6');
    } else {
      // Secondaire / Humanités : EX1 = P1+P2 ; EX2 = P3+P4
      ex1 = val('P1') + val('P2');
      ex2 = val('P3') + val('P4');
    }

    const tot = ex1 + ex2 + ex3;

    if (ex1Span) ex1Span.textContent = ex1.toString();
    if (ex2Span) ex2Span.textContent = ex2.toString();
    if (ex3Span) ex3Span.textContent = ex3.toString();
    if (totSpan) totSpan.textContent = tot.toString();
  }

  inputs.forEach(inp => {
    inp.addEventListener('input', updateTotals);
  });

  // Initial
  updateTotals();
})();
</script>
