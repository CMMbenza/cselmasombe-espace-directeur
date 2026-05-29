<?php
// /directeur/annonces/edit.php
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

$msg = '';
$msgType = 'info';
$annonce = null;

// Chargement de l'annonce
try {
    $stmt = $pdo->prepare("SELECT * FROM annonces WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$annonce) {
        $msg = "Annonce introuvable.";
        $msgType = 'danger';
    }
} catch (Throwable $e) {
    $msg = "Erreur lors du chargement : ".$e->getMessage();
    $msgType = 'danger';
}

if ($annonce) {
    // Valeurs initiales
    $titre   = $annonce['titre']   ?? '';
    $contenu = $annonce['contenu'] ?? '';
    $visible = $annonce['visible_a'] ?? 'tous';

    // Normaliser anciens 'eleves' vers 'parents'
    if ($visible === 'eleves') {
        $visible = 'parents';
    }

    // Traitement du POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $titre   = trim($_POST['titre'] ?? '');
        $contenu = trim($_POST['contenu'] ?? '');
        $visible = $_POST['visible_a'] ?? 'tous';

        $allowed = ['tous', 'profs', 'parents'];
        if (!in_array($visible, $allowed, true)) {
            $visible = 'tous';
        }

        if ($titre !== '' && $contenu !== '') {
            try {
                $stmtUp = $pdo->prepare("
                    UPDATE annonces
                    SET titre = :titre,
                        contenu = :contenu,
                        visible_a = :visible_a
                    WHERE id = :id
                    LIMIT 1
                ");
                $stmtUp->execute([
                    ':titre'     => $titre,
                    ':contenu'   => $contenu,
                    ':visible_a' => $visible,
                    ':id'        => $id,
                ]);

                $msg = "Annonce mise à jour avec succès.";
                $msgType = 'success';

            } catch (Throwable $e) {
                $msg = "Erreur lors de la mise à jour : ".$e->getMessage();
                $msgType = 'danger';
            }
        } else {
            $msg = "Veuillez compléter le titre et le contenu.";
            $msgType = 'warning';
        }
    }
} else {
    $titre = $contenu = '';
    $visible = 'tous';
}
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0">Modifier l'annonce</h1>
    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-sm btn-outline-secondary">← Retour à la liste</a>
      <a href="show.php?id=<?= (int)$id ?>" class="btn btn-sm btn-outline-secondary">Voir le communiqué</a>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= e($msgType) ?>"><?= e($msg) ?></div>
  <?php endif; ?>

  <?php if ($annonce): ?>
    <form method="post" class="card shadow-sm">
      <div class="card-body">

        <!-- Titre -->
        <div class="mb-3">
          <label class="form-label">Titre de l'annonce</label>
          <input 
            type="text"
            name="titre"
            class="form-control"
            required
            value="<?= e($titre) ?>"
          >
        </div>

        <!-- Destinataires -->
        <div class="mb-3">
          <label class="form-label d-block mb-2">Destinataires</label>

          <div class="row g-3">

            <!-- Tous -->
            <div class="col-12 col-md-4">
              <label class="d-flex align-items-start border rounded p-3 gap-2 h-100" style="cursor:pointer;">
                <input 
                  type="radio" 
                  name="visible_a" 
                  value="tous" 
                  class="form-check-input mt-1"
                  <?= $visible === 'tous' ? 'checked' : '' ?>
                >
                <div>
                  <div class="fw-bold">Tous</div>
                  <div class="text-muted small">Professeurs, parents & élèves</div>
                </div>
              </label>
            </div>

            <!-- Professeurs -->
            <div class="col-12 col-md-4">
              <label class="d-flex align-items-start border rounded p-3 gap-2 h-100" style="cursor:pointer;">
                <input 
                  type="radio" 
                  name="visible_a" 
                  value="profs" 
                  class="form-check-input mt-1"
                  <?= $visible === 'profs' ? 'checked' : '' ?>
                >
                <div>
                  <div class="fw-bold">Professeurs</div>
                  <div class="text-muted small">Communiqué interne aux enseignants</div>
                </div>
              </label>
            </div>

            <!-- Parents & élèves -->
            <div class="col-12 col-md-4">
              <label class="d-flex align-items-start border rounded p-3 gap-2 h-100" style="cursor:pointer;">
                <input 
                  type="radio" 
                  name="visible_a" 
                  value="parents" 
                  class="form-check-input mt-1"
                  <?= $visible === 'parents' ? 'checked' : '' ?>
                >
                <div>
                  <div class="fw-bold">Parents & élèves</div>
                  <div class="text-muted small">Informations destinées aux familles</div>
                </div>
              </label>
            </div>

          </div>

          <div class="form-text mt-1">
            La sélection détermine qui verra ce communiqué.
          </div>
        </div>

        <!-- Contenu -->
        <div class="mb-3">
          <label class="form-label">Contenu du communiqué</label>
          <textarea
            name="contenu"
            rows="6"
            class="form-control"
            required
          ><?= e($contenu) ?></textarea>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <div class="text-muted small">
            Dernière modification appliquée à cette annonce.
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary">Enregistrer les modifications</button>
            <a class="btn btn-outline-secondary" href="show.php?id=<?= (int)$id ?>">Annuler</a>
          </div>
        </div>

      </div>
    </form>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
