<?php
// /directeur/agents/create.php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_directeur();
require_once __DIR__.'/../layout/header.php';
require_once __DIR__.'/../layout/navbar.php';

$error = '';
$msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nom      = trim((string)($_POST['nom'] ?? ''));
  $postnom  = trim((string)($_POST['postnom'] ?? ''));
  $prenom   = trim((string)($_POST['prenom'] ?? ''));
  $telephone= trim((string)($_POST['telephone'] ?? ''));
  $adresse  = trim((string)($_POST['adresse_complete'] ?? ''));
  $email    = trim((string)($_POST['email'] ?? ''));
  $embauche = trim((string)($_POST['dateEmbauche'] ?? ''));
  $code_connexion = trim((string)($_POST['code_connexion'] ?? ''));

  // validations simples
  if ($nom === '' || $postnom === '' || $prenom === '' || $telephone === '' || $adresse === '') {
    $error = "Veuillez renseigner tous les champs obligatoires (*).";
  } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Email invalide.";
  } else {
    try {
      $stmt = $pdo->prepare("
        INSERT INTO agent (nom, postnom, prenom, telephone, adresse_complete, email, code_connexion, dateEmbauche, created_at)
        VALUES (:nom, :postnom, :prenom, :tel, :adr, :email, :code_connexion, :emb, NOW())
      ");
      $stmt->execute([
        ':nom'    => $nom,
        ':postnom'=> $postnom,
        ':prenom' => $prenom,
        ':tel'    => $telephone,
        ':adr'    => $adresse,
        ':code_connexion'    => $code_connexion,
        ':email'  => ($email !== '' ? $email : null),
        ':emb'    => ($embauche !== '' ? $embauche : null),
      ]);

      // Redirection avec message de succès
      header('Location: '.BASE_URL.'/agents/index.php?ok=1');
      exit;
    } catch (Throwable $e) {
      $error = "Erreur lors de l'enregistrement (vérifie la table `agent`).";
    }
  }
}
?>
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Nouvel agent</h1>
    <a class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/agents/index.php">Retour à la liste</a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

  <form method="post" class="card p-3 shadow-sm">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Nom *</label>
        <input name="nom" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Postnom *</label>
        <input name="postnom" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Prénom *</label>
        <input name="prenom" class="form-control" required>
      </div>

      <div class="col-md-4">
        <label class="form-label">Téléphone *</label>
        <input name="telephone" class="form-control" required>
      </div>
      <div class="col-md-8">
        <label class="form-label">Adresse complète *</label>
        <input name="adresse_complete" class="form-control" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label">Date d’embauche</label>
        <input type="date" name="dateEmbauche" class="form-control">
      </div>
      
      <div class="col-md-12">
        <label class="form-label">Code connexion</label>
        <input type="text" name="code_connexion" class="form-control">
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-primary">Enregistrer</button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/agents/index.php">Annuler</a>
    </div>
  </form>
</div>
<?php require_once __DIR__.'/../layout/footer.php'; ?>
