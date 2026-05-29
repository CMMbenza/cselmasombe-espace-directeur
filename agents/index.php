<?php
// /directeur/agents/index.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur(); // vérifie session + anti-cache
require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';

$q = trim((string)($_GET['q'] ?? ''));
$ok = isset($_GET['ok']) ? 'Agent enregistré avec succès.' : '';

$rows = [];
$error = '';

try {
  if ($q !== '') {
    $stmt = $pdo->prepare("
      SELECT id, nom, postnom, prenom, telephone, adresse_complete, email, code_connexion, created_at
      FROM agent
      WHERE nom LIKE :q OR postnom LIKE :q OR prenom LIKE :q
         OR telephone LIKE :q OR email LIKE :q
      ORDER BY nom ASC
      LIMIT 300
    ");
    $like = "%{$q}%";
    $stmt->execute([':q' => $like]);
    $rows = $stmt->fetchAll();
  } else {
    $stmt = $pdo->query("
      SELECT id, nom, postnom, prenom, telephone, adresse_complete, email, code_connexion, created_at
      FROM agent
      ORDER BY nom ASC
      LIMIT 300
    ");
    $rows = $stmt ? $stmt->fetchAll() : [];
  }
} catch (Throwable $e) {
  $error = "Impossible de lire les agents (vérifie la table `agent`).";
}
?>
<div class="container">
  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Agents</h1>
    <div class="d-flex gap-2">
      <form class="d-flex" method="get" role="search">
        <input class="form-control form-control-sm" type="search" name="q" placeholder="Rechercher (nom, tel, email)" value="<?= e($q) ?>">
        <button class="btn btn-sm btn-outline-secondary ms-2">OK</button>
      </form>
      <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/agents/create.php">Nouvel agent</a>
    </div>
  </div>

  <?php if ($ok): ?><div class="alert alert-success py-2"><?= e($ok) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Nom complet</th>
            <th>Téléphone</th>
            <th>Adresse</th>
            <th>Email</th>
            <th>Code connexion</th>
            <th>Créé le</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="6"><em>Aucun agent trouvé.</em></td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= e($r['id']) ?></td>
              <td><?= e($r['nom'].' '.$r['postnom'].' '.$r['prenom']) ?></td>
              <td><?= e($r['telephone']) ?></td>
              <td><?= e($r['adresse_complete']) ?></td>
              <td><?= e($r['email'] ?: '—') ?></td>
              <td><?= e($r['code_connexion'] ?: '—') ?></td>
              <td><?= e($r['created_at']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      <div class="text-muted small">Affichage limité aux 300 derniers.</div>
    </div>
  </div>
</div>
<?php require_once __DIR__.'/../layout/footer.php'; ?>
