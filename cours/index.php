<?php
// /directeur/cours/index.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // session + anti-cache + $pdo
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$q      = trim((string)($_GET['q'] ?? ''));
$cid    = (int)($_GET['classe_id'] ?? 0);
$ok     = isset($_GET['ok']) ? "Cours enregistré(s) avec succès." : '';
$error  = '';
$rows   = [];
$classes= [];

try {
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException("Connexion PDO indisponible.");
    }

    // Pour le filtre par classe
    $classes = $pdo->query("
      SELECT c.id, c.description AS classe, cy.description AS cycle
      FROM classe c
      LEFT JOIN cycle cy ON cy.id = c.cycle
      ORDER BY cy.description, c.description
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Base SELECT (avec statut de pondération)
    $baseSelect = "
      SELECT 
        co.id,
        co.intitule,
        c.description AS classe,
        cy.description AS cycle,
        co.created_at,
        co.classe_id,
        CASE 
          WHEN EXISTS (
            SELECT 1 
            FROM cours_ponderations cp 
            WHERE cp.cours_id = co.id
          )
          THEN 1 ELSE 0
        END AS has_ponderation
      FROM cours co
      JOIN classe c ON c.id = co.classe_id
      LEFT JOIN cycle cy ON cy.id = c.cycle
    ";

    if ($q !== '' || $cid > 0) {
        $sql = $baseSelect . "
          WHERE (:q = '' OR co.intitule LIKE :like OR c.description LIKE :like OR cy.description LIKE :like)
            AND (:cid = 0 OR co.classe_id = :cid)
          ORDER BY c.description, co.intitule
          LIMIT 1000
        ";
        $stmt = $pdo->prepare($sql);
        $like = "%{$q}%";
        $stmt->execute([
          ':q'    => $q,
          ':like' => $like,
          ':cid'  => $cid,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = $baseSelect . "
          ORDER BY c.description, co.intitule
          LIMIT 1000
        ";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $error = "Impossible de lire les cours (vérifie la table `cours` et `cours_ponderations`).";
    // Pour debug temporaire :
    // $error .= ' ('.$e->getMessage().')';
}
?>
<div class="container">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Cours</h1>
    <div class="d-flex flex-wrap gap-2">
      <form class="d-flex gap-2" method="get" role="search">
        <input
          class="form-control form-control-sm"
          type="search"
          name="q"
          placeholder="Rechercher (cours, classe, cycle)"
          value="<?= e($q) ?>"
        >

        <select name="classe_id" class="form-select form-select-sm">
          <option value="0">Toutes les classes</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $cid===(int)$c['id']?'selected':'' ?>>
              <?= e($c['classe']) ?><?= $c['cycle'] ? ' — Cycle: '.e($c['cycle']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button class="btn btn-sm btn-outline-secondary">OK</button>
        <?php if ($q!=='' || $cid>0): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/cours/">Réinitialiser</a>
        <?php endif; ?>
      </form>
      <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/cours/create.php">Nouveau cours</a>
      <a class="btn btn-sm btn-success" href="<?= BASE_URL ?>/cours/statut_periode.php">Statut des périodes</a>
    </div>
  </div>

  <?php if ($ok): ?>
    <div class="alert alert-success py-2"><?= e($ok) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Cours</th>
            <th>Classe</th>
            <th>Cycle</th>
            <th>Créé le</th>
            <th>Status</th>
            <th style="width:1%;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7"><em>Aucun cours trouvé.</em></td></tr>
          <?php else: foreach ($rows as $r):
            $hasPond = (int)$r['has_ponderation'] === 1;
          ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= e($r['intitule']) ?></td>
              <td><?= e($r['classe']) ?></td>
              <td><?= e($r['cycle'] ?? '—') ?></td>
              <td><?= e($r['created_at']) ?></td>
              <td>
                <?php if ($hasPond): ?>
                  <span class="badge text-bg-success">Pondéré</span>
                <?php else: ?>
                  <span class="badge text-bg-warning">Non pondéré</span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-secondary"
                  data-bs-toggle="modal"
                  data-bs-target="#detailModal"
                  data-id="<?= e($r['id']) ?>"
                  data-intitule="<?= e($r['intitule']) ?>"
                  data-classe="<?= e($r['classe']) ?>"
                  data-cycle="<?= e($r['cycle'] ?? '—') ?>"
                  data-created="<?= e($r['created_at']) ?>"
                >Détails</button>

                <a class="btn btn-sm <?= $hasPond ? 'btn-outline-primary' : 'btn-primary' ?> ms-1"
                   href="<?= BASE_URL ?>/cours/ponderation.php?cours_id=<?= (int)$r['id'] ?>">
                  <?= $hasPond ? 'Modifier la pondération' : 'Définir la pondération' ?>
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      <div class="text-muted small">Total : <?= count($rows) ?> cours.</div>
    </div>
  </div>
</div>

<!-- Modal Détails -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Détails du cours</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <dl class="row mb-0">
          <dt class="col-5"># ID</dt>
          <dd class="col-7" id="d-id">—</dd>

          <dt class="col-5">Intitulé</dt>
          <dd class="col-7" id="d-intitule">—</dd>

          <dt class="col-5">Classe</dt>
          <dd class="col-7" id="d-classe">—</dd>

          <dt class="col-5">Cycle</dt>
          <dd class="col-7" id="d-cycle">—</dd>

          <dt class="col-5">Créé le</dt>
          <dd class="col-7" id="d-created">—</dd>
        </dl>
      </div>
      <div class="modal-footer">
        <a id="editLink" href="#" class="btn btn-sm btn-outline-secondary disabled" tabindex="-1" aria-disabled="true">
          Modifier (à venir)
        </a>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<script>
const detailModal = document.getElementById('detailModal');
detailModal?.addEventListener('show.bs.modal', (event) => {
  const btn = event.relatedTarget; if (!btn) return;
  const data = {
    id:       btn.getAttribute('data-id') || '—',
    intitule: btn.getAttribute('data-intitule') || '—',
    classe:   btn.getAttribute('data-classe') || '—',
    cycle:    btn.getAttribute('data-cycle') || '—',
    created:  btn.getAttribute('data-created') || '—',
  };
  document.getElementById('d-id').textContent       = data.id;
  document.getElementById('d-intitule').textContent = data.intitule;
  document.getElementById('d-classe').textContent   = data.classe;
  document.getElementById('d-cycle').textContent    = data.cycle;
  document.getElementById('d-created').textContent  = data.created;

  const edit = document.getElementById('editLink');
  if (edit) edit.href = 'edit.php?id=' + encodeURIComponent(data.id);
});
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
