<?php
// /directeur/annonces/delete.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$error   = '';
$annonce = null;

// Si POST -> tentative de suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("DELETE FROM annonces WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        header('Location: index.php?deleted=1');
        exit;
    } catch (Throwable $e) {
        $error = "Erreur lors de la suppression : ".$e->getMessage();
    }
}

// Si GET -> afficher l'écran de confirmation
try {
    $stmt = $pdo->prepare("SELECT id, titre, visible_a, created_at FROM annonces WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$annonce) {
        $error = "Annonce introuvable.";
    }
} catch (Throwable $e) {
    $error = "Erreur lors du chargement : ".$e->getMessage();
}

// Helper label cible
function annonce_cible_label_delete(string $visible): string {
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
    <h1 class="h4 mb-0">Supprimer une annonce</h1>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">
      ← Retour à la liste
    </a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php elseif ($annonce): 
    $cibleLabel = annonce_cible_label_delete((string)$annonce['visible_a']);
  ?>
    <div class="alert alert-warning">
      <strong>Attention :</strong> cette action est irréversible.
      L'annonce sera définitivement supprimée.
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h2 class="h6">Annonce à supprimer</h2>
        <p class="mb-1">
          <strong>Titre :</strong> <?= e($annonce['titre']) ?>
        </p>
        <p class="mb-1">
          <strong>Cible :</strong> <?= e($cibleLabel) ?>
        </p>
        <p class="mb-0 text-muted small">
          Publiée le <?= e($annonce['created_at']) ?>
        </p>
      </div>
    </div>

    <form method="post" class="d-flex gap-2">
      <input type="hidden" name="id" value="<?= (int)$annonce['id'] ?>">
      <button type="submit" class="btn btn-danger">
        Oui, supprimer définitivement
      </button>
      <a href="show.php?id=<?= (int)$annonce['id'] ?>" class="btn btn-outline-secondary">
        Annuler
      </a>
    </form>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
