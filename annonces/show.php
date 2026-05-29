<?php
// /directeur/annonces/show.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$error   = '';
$annonce = null;

try {
    $sql = "
        SELECT 
            a.id,
            a.titre,
            a.contenu,
            a.visible_a,
            a.created_at,
            a.created_by,
            u.username AS auteur_username
        FROM annonces a
        LEFT JOIN users u ON u.id = a.created_by
        WHERE a.id = :id
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$annonce) {
        $error = "Annonce introuvable.";
    }
} catch (Throwable $e) {
    $error = "Erreur lors du chargement de l'annonce : ".$e->getMessage();
}

// Helper cible
function annonce_cible_label(string $visible): string {
    $visible = trim($visible);
    switch ($visible) {
        case 'profs':
            return 'Professeurs';
        case 'parents':
        case 'eleves':
            return 'Parents & élèves';
        case 'tous':
        default:
            return 'Tous';
    }
}
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
      ← Retour à la liste
    </a>
    <?php if ($annonce): ?>
      <div class="d-flex gap-2">
        <a href="edit.php?id=<?= (int)$annonce['id'] ?>" class="btn btn-sm btn-outline-primary">
          Modifier
        </a>
        <a href="delete.php?id=<?= (int)$annonce['id'] ?>" class="btn btn-sm btn-outline-danger">
          Supprimer
        </a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php elseif ($annonce): 
    $cibleLabel = annonce_cible_label((string)$annonce['visible_a']);
  ?>
    <div class="card shadow-sm border-0">
      <div class="card-body">

        <div class="text-center mb-3">
          <div class="text-uppercase text-muted small">Communiqué officiel</div>
          <h1 class="h4 mb-1"><?= e($annonce['titre']) ?></h1>
          <div class="text-muted small">
            Destinataires : <strong><?= e($cibleLabel) ?></strong>
          </div>
        </div>

        <hr>

        <div class="mb-3" style="white-space:pre-line;">
          <?= nl2br(e($annonce['contenu'])) ?>
        </div>

        <hr>

        <div class="d-flex justify-content-between flex-wrap gap-2 text-muted small">
          <div>
            Publié le 
            <strong><?= e($annonce['created_at']) ?></strong>
            <?php if (!empty($annonce['auteur_username'])): ?>
              par <strong><?= e($annonce['auteur_username']) ?></strong>
            <?php endif; ?>
          </div>
          <div>
            <button class="btn btn-sm btn-outline-secondary" type="button" onclick="window.print()">
              🖨 Imprimer
            </button>
          </div>
        </div>

      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
