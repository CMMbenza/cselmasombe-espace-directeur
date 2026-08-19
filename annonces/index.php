<?php
// /directeur/annonces/index.php
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
    __DIR__ . '/../config/db.php',
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
   ID Directeur (selon TON login)
   => $_SESSION['user']['id']
===================================================== */
$myId = (int)($_SESSION['user']['id'] ?? 0);
$myRole = mb_strtolower(trim((string)($_SESSION['user']['role'] ?? '')), 'UTF-8');

/* =====================================================
   Onglets
===================================================== */
$tab    = ($_GET['tab'] ?? 'recu') === 'envoye' ? 'envoye' : 'recu';
$search = trim((string)($_GET['q'] ?? ''));
$error  = '';
$rows   = [];

/* =====================================================
   Helpers UI
===================================================== */
function dest_label(string $type, ?int $id): string {
  return match ($type) {
    'tous'   => 'Tous',
    'profs'  => 'Professeurs',
    'eleves' => 'Élèves',
    'user'   => 'Utilisateur #' . (int)$id,
    default  => $type,
  };
}
function dest_badge(string $type): string {
  return match ($type) {
    'tous'   => 'bg-secondary',
    'profs'  => 'bg-primary',
    'eleves' => 'bg-success',
    'user'   => 'bg-dark',
    default  => 'bg-secondary',
  };
}
function sender_role_label(string $role): string {
  return match ($role) {
    'directeur' => 'Directeur',
    'prof'      => 'Professeur',
    'eleve'     => 'Élève',
    default     => $role,
  };
}

/* =====================================================
   Charger noms expéditeurs
   - directeur/prof -> table agent
   - eleve -> table eleve
===================================================== */
function load_sender_names(PDO $pdo, array $rows): array {
  $agentIds = [];
  $eleveIds = [];

  foreach ($rows as $r) {
    $sid = (int)($r['sender_id'] ?? 0);
    $role = (string)($r['sender_role'] ?? '');
    if ($sid <= 0) continue;

    if ($role === 'eleve') $eleveIds[$sid] = true;
    else $agentIds[$sid] = true;
  }

  $names = ['agent'=>[], 'eleve'=>[]];

  if ($agentIds) {
    $ids = array_keys($agentIds);
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, nom, postnom, prenom FROM agent WHERE id IN ($place)");
    $stmt->execute($ids);
    while ($a = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $id = (int)$a['id'];
      $names['agent'][$id] = trim(($a['nom'] ?? '').' '.($a['postnom'] ?? '').' '.($a['prenom'] ?? ''));
    }
  }

  if ($eleveIds) {
    $ids = array_keys($eleveIds);
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, nom, postnom, prenom FROM eleve WHERE id IN ($place)");
    $stmt->execute($ids);
    while ($e = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $id = (int)$e['id'];
      $names['eleve'][$id] = trim(($e['nom'] ?? '').' '.($e['postnom'] ?? '').' '.($e['prenom'] ?? ''));
    }
  }

  return $names;
}

/* =====================================================
   Sécurité minimale : si myId absent, on affiche erreur
===================================================== */
if ($myId <= 0) {
  $error = "Votre session n'a pas d'identifiant utilisateur. Reconnectez-vous.";
}

/* =====================================================
   Requête selon l’onglet
===================================================== */
try {
  if (!$error) {
    $params = [];
    $where  = [];

    if ($tab === 'envoye') {
      // ✅ Envoyés = messages dont je suis l'expéditeur
      $where[] = "a.sender_role = 'directeur' AND a.sender_id = :me";
      $params[':me'] = $myId;
    } else {
      // ✅ Reçus = destinés à tous OU en privé à moi
      // (si tu veux aussi recevoir les messages destinés aux directeurs en masse,
      // il faut ajouter dest_type='directeurs' dans la table.)
      $where[] = "(a.dest_type = 'tous' OR (a.dest_type = 'user' AND a.dest_id = :me))";
      $params[':me'] = $myId;
    }

    if ($search !== '') {
      $where[] = "(a.titre LIKE :q OR a.contenu LIKE :q)";
      $params[':q'] = '%'.$search.'%';
    }

    $sql = "
      SELECT
        a.id, a.titre, a.contenu,
        a.sender_role, a.sender_id,
        a.dest_type, a.dest_id,
        a.created_at
      FROM annonces a
      WHERE " . implode(' AND ', $where) . "
      ORDER BY a.created_at DESC
      LIMIT 200
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $error = $e->getMessage();
}

/* =====================================================
   Noms auteurs
===================================================== */
$senderNames = $rows ? load_sender_names($pdo, $rows) : ['agent'=>[], 'eleve'=>[]];

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>
<div class="container">

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h5 mb-0 text-uppercase">Communiqués/Annonces/Messages</h1>
      <div class="text-muted small">Reçus / Envoyés</div>
    </div>
    <a href="create.php" class="btn btn-sm btn-primary">+ Nouveau communiqué</a>
  </div>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link <?= $tab==='recu'?'active':'' ?>" href="?tab=recu">Reçus</a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $tab==='envoye'?'active':'' ?>" href="?tab=envoye">Envoyés</a>
    </li>
  </ul>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="col-12 col-md-8">
          <label class="form-label">Recherche</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= e($search) ?>" placeholder="Titre ou contenu...">
        </div>
        <div class="col-12 col-md-4 d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary mt-auto">Rechercher</button>
          <a class="btn btn-sm btn-outline-secondary mt-auto" href="?tab=<?= e($tab) ?>">Réinitialiser</a>
        </div>
      </form>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:1%;">#</th>
            <th>Titre</th>
            <th><?= $tab==='envoye' ? 'Destinataire' : 'Auteur' ?></th>
            <th>Extrait</th>
            <th class="text-nowrap">Date</th>
            <th style="width:1%;"></th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6"><em>Aucun communiqué.</em></td></tr>
        <?php else:
          $i = 0;
          foreach ($rows as $r):
            $i++;
            $preview = mb_substr(strip_tags((string)$r['contenu']), 0, 120, 'UTF-8');
            if (mb_strlen(strip_tags((string)$r['contenu']), 'UTF-8') > 120) $preview .= '…';

            if ($tab === 'envoye') {
              $label = dest_label((string)$r['dest_type'], $r['dest_id'] !== null ? (int)$r['dest_id'] : null);
              $badge = dest_badge((string)$r['dest_type']);
            } else {
              $srole = (string)$r['sender_role'];
              $sid   = (int)$r['sender_id'];

              if ($srole === 'eleve') {
                $nm = $senderNames['eleve'][$sid] ?? ('Élève #'.$sid);
                $label = $nm . ' (Élève)';
              } else {
                $nm = $senderNames['agent'][$sid] ?? ('Agent #'.$sid);
                $label = $nm . ' (' . sender_role_label($srole) . ')';
              }
              $badge = 'bg-secondary';
            }
        ?>
          <tr>
            <td><?= $i ?></td>
            <td><a href="show.php?id=<?= (int)$r['id'] ?>"><?= e($r['titre']) ?></a></td>
            <td><span class="badge <?= e($badge) ?>"><?= e($label) ?></span></td>
            <td class="text-muted small"><?= e($preview) ?></td>
            <td class="text-nowrap"><?= e($r['created_at']) ?></td>
            <td class="text-nowrap">
              <a href="show.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary">Voir</a>
              <?php if ($tab === 'envoye'): ?>
                <a href="edit.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary">Éditer</a>
                <a href="delete.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-danger">Supprimer</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>

      <div class="text-muted small mt-2">
        Total : <strong><?= (int)count($rows) ?></strong> communiqué(s).
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
