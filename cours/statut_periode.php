<?php
// /directeur/cours/statut_periode.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

// Messages
$msg = '';
$err = '';

/**
 * On suppose que $pdo (PDO) est disponible via vos includes
 */

/**
 * 1) Récupérer les cycles
 */
try {
    $stmt = $pdo->query("
        SELECT id, description
        FROM cycle
        ORDER BY id ASC
    ");
    $cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $cycles = [];
    $err = "Erreur lors du chargement des cycles.";
}

/**
 * 2) Traitement du formulaire (mise à jour des périodes)
 */
if (!$err && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['periodes']) && is_array($_POST['periodes'])) {
    $periodesPost = $_POST['periodes'];

    try {
        $pdo->beginTransaction();

        $sqlUpdate = "
          UPDATE periodes
          SET libelle = :libelle,
              ordre   = :ordre,
              actif   = :actif
          WHERE id = :id
        ";
        $stUpdate = $pdo->prepare($sqlUpdate);

        foreach ($periodesPost as $id => $data) {
            $id = (int)$id;
            if ($id <= 0) {
                continue;
            }

            $libelle = trim((string)($data['libelle'] ?? ''));
            $ordre   = (int)($data['ordre'] ?? 1);
            $actif   = isset($data['actif']) && (string)$data['actif'] === '1' ? 1 : 0;

            if ($libelle === '') {
                $libelle = 'Période ' . $id;
            }
            if ($ordre <= 0) {
                $ordre = 1;
            }

            $stUpdate->execute([
                ':libelle' => $libelle,
                ':ordre'   => $ordre,
                ':actif'   => $actif,
                ':id'      => $id,
            ]);
        }

        $pdo->commit();
        $msg = "Statut des périodes mis à jour avec succès.";
    } catch (Throwable $e) {
        $pdo->rollBack();
        $err = "Erreur lors de la mise à jour des périodes.";
    }
}

/**
 * 3) Recharger les périodes par cycle
 */
$periodesByCycle = [];
if (!$err) {
    try {
        $sql = "
          SELECT 
            p.id,
            p.cycle_id,
            p.CODE,
            p.libelle,
            p.ordre,
            p.actif
          FROM periodes p
          ORDER BY p.cycle_id, p.ordre, p.id
        ";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $cid = (int)$row['cycle_id'];
            if (!isset($periodesByCycle[$cid])) {
                $periodesByCycle[$cid] = [];
            }
            $periodesByCycle[$cid][] = $row;
        }
    } catch (Throwable $e) {
        $err = "Erreur lors du chargement des périodes.";
    }
}
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h5 mb-0">Statut des périodes</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/cours/">← Retour aux cours</a>
  </div>

  <?php if ($err): ?>
    <div class="alert alert-danger py-2"><?= e($err) ?></div>
  <?php endif; ?>

  <?php if ($msg): ?>
    <div class="alert alert-success py-2"><?= e($msg) ?></div>
  <?php endif; ?>

  <?php if (!$err): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <p class="text-muted small mb-0">
          Cette page permet d'activer/désactiver les périodes par cycle, et d'ajuster leurs libellés et ordres d'affichage.<br>
          <strong>Astuce :</strong> seules les périodes avec <span class="badge bg-success">Actif</span> seront utilisées pour les pondérations et calculs de notes.
        </p>
      </div>
    </div>

    <!-- Boutons Cocher tout / Décocher tout -->
    <div class="d-flex justify-content-end mb-3 gap-2">
      <button type="button" id="btnCheckAll" class="btn btn-sm btn-outline-primary">
        Cocher tout
      </button>
      <button type="button" id="btnUncheckAll" class="btn btn-sm btn-outline-secondary">
        Décocher tout
      </button>
    </div>

    <form method="post">
      <?php foreach ($cycles as $cycle): 
        $cid   = (int)$cycle['id'];
        $label = $cycle['description'] ?? ('Cycle '.$cid);
        $periodes = $periodesByCycle[$cid] ?? [];

        // Détection type de cycle pour affichage du badge
        $lower = mb_strtolower($label, 'UTF-8');
        $type = '';
        if (str_contains($lower, 'prim') || str_contains($lower, 'mater')) {
            $type = '<span class="badge bg-primary ms-2">Primaire / Maternelle</span>';
        } elseif (str_contains($lower, 'sec') || str_contains($lower, 'huma')) {
            $type = '<span class="badge bg-success ms-2">Secondaire / Humanités</span>';
        }
        ?>
        <div class="card mb-4 shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div>
              <strong>Cycle : <?= e($label) ?> <?= $type ?></strong>
            </div>
            <span class="badge bg-light text-muted">
              <?= count($periodes) ?> période(s)
            </span>
          </div>
          <div class="card-body p-0">
            <?php if (!$periodes): ?>
              <div class="p-3 text-muted small">
                Aucune période définie pour ce cycle.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 8%;">ID</th>
                      <th style="width: 12%;">Code</th>
                      <th style="width: 40%;">Libellé</th>
                      <th style="width: 15%;">Ordre</th>
                      <th style="width: 15%;">Actif ?</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($periodes as $p): 
                      $pid     = (int)$p['id'];
                      $code    = $p['CODE'] ?? '';
                      $libelle = $p['libelle'] ?? '';
                      $ordre   = (int)$p['ordre'];
                      $actif   = (int)$p['actif'] === 1;
                    ?>
                      <tr>
                        <td>
                          <?= $pid ?>
                          <input type="hidden" name="periodes[<?= $pid ?>][id]" value="<?= $pid ?>">
                        </td>
                        <td>
                          <span class="badge bg-secondary"><?= e($code) ?></span>
                        </td>
                        <td>
                          <input
                            type="text"
                            name="periodes[<?= $pid ?>][libelle]"
                            class="form-control form-control-sm"
                            value="<?= e($libelle) ?>"
                          >
                        </td>
                        <td style="max-width: 90px;">
                          <input
                            type="number"
                            name="periodes[<?= $pid ?>][ordre]"
                            class="form-control form-control-sm text-center"
                            min="1"
                            step="1"
                            value="<?= $ordre ?>"
                          >
                        </td>
                        <td class="text-center">
                          <div class="form-check d-inline-flex align-items-center gap-1">
                            <input
                              class="form-check-input js-periode-actif"
                              type="checkbox"
                              id="p_actif_<?= $pid ?>"
                              name="periodes[<?= $pid ?>][actif]"
                              value="1"
                              <?= $actif ? 'checked' : '' ?>
                            >
                            <label class="form-check-label small" for="p_actif_<?= $pid ?>">
                              <?= $actif ? 'Actif' : 'Inactif' ?>
                            </label>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="d-flex justify-content-end mb-5">
        <button type="submit" class="btn btn-primary btn-sm">
          Enregistrer les modifications
        </button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>

<script>
// Boutons "Cocher tout" / "Décocher tout" pour les colonnes Actif
(function() {
  const btnCheckAll   = document.getElementById('btnCheckAll');
  const btnUncheckAll = document.getElementById('btnUncheckAll');

  if (!btnCheckAll || !btnUncheckAll) return;

  function setAllActive(checked) {
    const boxes = document.querySelectorAll('.js-periode-actif');
    boxes.forEach(cb => {
      cb.checked = checked;
    });
  }

  btnCheckAll.addEventListener('click', function (e) {
    e.preventDefault();
    setAllActive(true);
  });

  btnUncheckAll.addEventListener('click', function (e) {
    e.preventDefault();
    setAllActive(false);
  });
})();
</script>
