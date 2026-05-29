<?php
// /directeur/annonces/create.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_directeur();

/* =====================================================
   Connexion PDO (assurée)
===================================================== */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $candidates = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../includes/db.php',
    __DIR__ . '/../includes/db_connect.php',
    __DIR__ . '/../includes/connexion.php',
  ];
  foreach ($candidates as $f) {
    if (is_file($f)) require_once $f;
    if (isset($pdo) && $pdo instanceof PDO) break;
  }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
  throw new RuntimeException("Connexion PDO indisponible.");
}

/* =====================================================
   Session Directeur (selon TON login)
===================================================== */
$myId = (int)($_SESSION['user']['id'] ?? 0);
if ($myId <= 0) {
  throw new RuntimeException("Identifiant directeur introuvable en session. Reconnectez-vous.");
}

/* =====================================================
   CSRF
===================================================== */
if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
$csrf = (string)$_SESSION['_csrf'];

/* =====================================================
   Helpers
===================================================== */
function post_str(string $k): string { return trim((string)($_POST[$k] ?? '')); }

/* =====================================================
   Charger les destinataires (profs + élèves)
   => on construit un tableau unifié pour l'autocomplete
===================================================== */
$destList = []; // {id, label, type}
try {
  // Profs / agents
  $st = $pdo->query("SELECT id, nom, postnom, prenom FROM agent ORDER BY nom, postnom, prenom");
  $profs = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
  foreach ($profs as $p) {
    $id = (int)($p['id'] ?? 0);
    if ($id <= 0) continue;
    $nm = trim(($p['nom'] ?? '').' '.($p['postnom'] ?? '').' '.($p['prenom'] ?? ''));
    $destList[] = [
      'id'    => $id,
      'type'  => 'prof',
      'label' => 'PROF — '.($nm ?: ('Agent #'.$id)),
    ];
  }

  // Élèves
  $st2 = $pdo->query("SELECT id, nom, postnom, prenom FROM eleve ORDER BY nom, postnom, prenom");
  $els = $st2 ? $st2->fetchAll(PDO::FETCH_ASSOC) : [];
  foreach ($els as $el) {
    $id = (int)($el['id'] ?? 0);
    if ($id <= 0) continue;
    $nm = trim(($el['nom'] ?? '').' '.($el['postnom'] ?? '').' '.($el['prenom'] ?? ''));
    $destList[] = [
      'id'    => $id,
      'type'  => 'eleve',
      'label' => 'ÉLÈVE — '.($nm ?: ('Élève #'.$id)),
    ];
  }
} catch (Throwable $e) {
  // si ça échoue, autocomplete sera vide
}

/* =====================================================
   POST
===================================================== */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postCsrf = (string)($_POST['_csrf'] ?? '');
  if (!hash_equals($csrf, $postCsrf)) {
    $error = "Session expirée (CSRF). Recharge la page et réessaie.";
  } else {

    $titre   = post_str('titre');
    $contenu = post_str('contenu');

    $dest_type = post_str('dest_type'); // tous|profs|eleves|user
    $dest_id   = (int)($_POST['dest_id'] ?? 0);

    if ($titre === '' || mb_strlen($titre, 'UTF-8') < 3) {
      $error = "Le titre est obligatoire (min 3 caractères).";
    } elseif ($contenu === '' || mb_strlen($contenu, 'UTF-8') < 3) {
      $error = "Le contenu est obligatoire (min 3 caractères).";
    } elseif (!in_array($dest_type, ['tous','profs','eleves','user'], true)) {
      $error = "Destinataire invalide.";
    } elseif ($dest_type === 'user' && $dest_id <= 0) {
      $error = "Veuillez choisir un destinataire (utilisateur) pour l'envoi particulier.";
    } else {

      // (Optionnel) vérifier que dest_id existe bien dans agent OU eleve quand dest_type=user
      if ($dest_type === 'user') {
        $ok = false;

        // agent ?
        $st = $pdo->prepare("SELECT id FROM agent WHERE id = :id LIMIT 1");
        $st->execute([':id' => $dest_id]);
        if ($st->fetch()) $ok = true;

        // eleve ?
        if (!$ok) {
          $st = $pdo->prepare("SELECT id FROM eleve WHERE id = :id LIMIT 1");
          $st->execute([':id' => $dest_id]);
          if ($st->fetch()) $ok = true;
        }

        if (!$ok) {
          $error = "Destinataire introuvable (prof/élève).";
        }
      }

      if ($error === '') {
        try {
          $sql = "
            INSERT INTO annonces (titre, contenu, sender_role, sender_id, dest_type, dest_id, created_at)
            VALUES (:titre, :contenu, 'directeur', :sender_id, :dest_type, :dest_id, NOW())
          ";
          $stmt = $pdo->prepare($sql);
          $stmt->execute([
            ':titre'     => $titre,
            ':contenu'   => $contenu,
            ':sender_id' => $myId,
            ':dest_type' => $dest_type,
            ':dest_id'   => ($dest_type === 'user') ? $dest_id : null,
          ]);

          redirect('index.php?tab=envoye');
        } catch (Throwable $e) {
          $error = "Impossible d'enregistrer : ".$e->getMessage();
        }
      }
    }
  }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0">Nouveau communiqué</h1>
      <div class="text-muted small">Envoyer un message (masse ou particulier)</div>
    </div>
    <a href="index.php?tab=envoye" class="btn btn-sm btn-outline-secondary">← Retour</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" autocomplete="off" id="frm">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div class="mb-3">
          <label class="form-label">Titre</label>
          <input type="text"
                 name="titre"
                 class="form-control"
                 required
                 value="<?= e($_POST['titre'] ?? '') ?>"
                 placeholder="Ex: Réunion pédagogique / Communiqué officiel...">
        </div>

        <div class="mb-3">
          <label class="form-label">Contenu</label>
          <textarea name="contenu"
                    class="form-control"
                    rows="6"
                    required
                    placeholder="Saisissez le message..."><?= e($_POST['contenu'] ?? '') ?></textarea>
        </div>

        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label">Type d’envoi</label>
            <?php $dt = (string)($_POST['dest_type'] ?? 'tous'); ?>
            <select name="dest_type" id="dest_type" class="form-select" onchange="toggleUserBlock()">
              <option value="tous"   <?= $dt==='tous'?'selected':'' ?>>Tous (masse)</option>
              <option value="profs"  <?= $dt==='profs'?'selected':'' ?>>Professeurs (masse)</option>
              <option value="eleves" <?= $dt==='eleves'?'selected':'' ?>>Élèves (masse)</option>
              <option value="user"   <?= $dt==='user'?'selected':'' ?>>Particulier (1 personne)</option>
            </select>
          </div>

          <!-- Auto-complete destinataire -->
          <div class="col-md-7" id="user_block" style="display:none;">
            <label class="form-label">Destinataire (Particulier)</label>

            <!-- ce champ est celui où tu tapes -->
            <input type="text"
                   id="dest_query"
                   class="form-control"
                   placeholder="Tape un nom... (ex : Jean, Mbenza...)"
                   value="<?= e($_POST['dest_label'] ?? '') ?>">

            <!-- on envoie l'id au serveur -->
            <input type="hidden" name="dest_id" id="dest_id" value="<?= (int)($_POST['dest_id'] ?? 0) ?>">
            <!-- on envoie aussi le label pour garder l'affichage si erreur -->
            <input type="hidden" name="dest_label" id="dest_label" value="<?= e($_POST['dest_label'] ?? '') ?>">

            <!-- liste de résultats -->
            <div class="list-group mt-2" id="dest_results" style="display:none; max-height: 240px; overflow:auto;"></div>

            <div class="form-text">
              Clique sur un résultat pour sélectionner. (PROF — ... / ÉLÈVE — ...)
            </div>

            <div class="small mt-2" id="dest_selected" style="display:none;">
              <span class="badge bg-success">Sélectionné</span>
              <span id="dest_selected_text"></span>
              <button type="button" class="btn btn-sm btn-link" onclick="clearDest()">Changer</button>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="index.php?tab=envoye" class="btn btn-outline-secondary">Annuler</a>
          <button class="btn btn-primary">Envoyer</button>
        </div>
      </form>
    </div>
  </div>

</div>

<script>
const DEST_LIST = <?= json_encode($destList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function toggleUserBlock(){
  const dt = document.getElementById('dest_type');
  const block = document.getElementById('user_block');
  if(!dt || !block) return;
  block.style.display = (dt.value === 'user') ? 'block' : 'none';
}

function clearDest(){
  document.getElementById('dest_id').value = '0';
  document.getElementById('dest_label').value = '';
  document.getElementById('dest_query').value = '';
  document.getElementById('dest_selected').style.display = 'none';
  document.getElementById('dest_selected_text').textContent = '';
  document.getElementById('dest_query').focus();
}

function renderResults(items){
  const box = document.getElementById('dest_results');
  box.innerHTML = '';

  if(!items.length){
    box.style.display = 'block';
    const div = document.createElement('div');
    div.className = 'list-group-item text-muted';
    div.textContent = 'Aucun résultat.';
    box.appendChild(div);
    return;
  }

  items.slice(0, 20).forEach(it => {
    const a = document.createElement('button');
    a.type = 'button';
    a.className = 'list-group-item list-group-item-action';
    a.innerHTML = `<div class="d-flex justify-content-between">
      <span>${escapeHtml(it.label)}</span>
      <span class="badge bg-light text-muted">${it.type}</span>
    </div>`;
    a.addEventListener('click', () => selectDest(it));
    box.appendChild(a);
  });

  box.style.display = 'block';
}

function selectDest(it){
  document.getElementById('dest_id').value = String(it.id);
  document.getElementById('dest_label').value = it.label;
  document.getElementById('dest_query').value = it.label;

  document.getElementById('dest_results').style.display = 'none';

  document.getElementById('dest_selected').style.display = 'block';
  document.getElementById('dest_selected_text').textContent = it.label;
}

function escapeHtml(s){
  return String(s)
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

(function initAutoComplete(){
  toggleUserBlock();

  const input = document.getElementById('dest_query');
  const results = document.getElementById('dest_results');

  if(!input) return;

  let t = null;
  input.addEventListener('input', () => {
    // dès qu'on retape, on invalide l'id si le texte ne correspond plus
    document.getElementById('dest_id').value = '0';
    document.getElementById('dest_selected').style.display = 'none';

    const q = input.value.trim().toLowerCase();
    clearTimeout(t);
    t = setTimeout(() => {
      if(q.length < 1){
        results.style.display = 'none';
        results.innerHTML = '';
        return;
      }
      const filtered = DEST_LIST.filter(it => it.label.toLowerCase().includes(q));
      renderResults(filtered);
    }, 80);
  });

  // cacher la liste si clic ailleurs
  document.addEventListener('click', (ev) => {
    const within = ev.target.closest('#user_block');
    if(!within){
      results.style.display = 'none';
    }
  });

  // si on est revenu après erreur POST et dest_id existe, afficher "sélectionné"
  const existingId = parseInt(document.getElementById('dest_id').value || '0', 10);
  const existingLabel = document.getElementById('dest_label').value || '';
  if(existingId > 0 && existingLabel){
    document.getElementById('dest_selected').style.display = 'block';
    document.getElementById('dest_selected_text').textContent = existingLabel;
  }
})();
</script>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
