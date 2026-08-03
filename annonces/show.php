<?php
// /directeur/annonces/show.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$error   = '';
$annonce = null;

try {
    // Jointure dynamique selon le rôle avec la concaténation NOM POSTNOM PRENOM
    $sql = "
        SELECT 
            a.id,
            a.titre,
            a.contenu,
            a.sender_role,
            a.sender_id,
            a.dest_type,
            a.dest_id,
            a.anneeScolaire,
            a.created_at,
            CASE 
                WHEN a.sender_role IN ('directeur', 'prof') THEN 
                    TRIM(CONCAT(IFNULL(ag.nom, ''), ' ', IFNULL(ag.postnom, ''), ' ', IFNULL(ag.prenom, '')))
                WHEN a.sender_role = 'eleve' THEN 
                    TRIM(CONCAT(IFNULL(e.nom, ''), ' ', IFNULL(e.postnom, ''), ' ', IFNULL(e.prenom, '')))
                ELSE NULL
            END AS auteur_nom
        FROM annonces a
        LEFT JOIN agent ag ON ag.id = a.sender_id AND a.sender_role IN ('directeur', 'prof')
        LEFT JOIN eleve e  ON e.id  = a.sender_id AND a.sender_role = 'eleve'
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
    $error = "Erreur lors du chargement de l'annonce : " . $e->getMessage();
}

// Helper pour afficher le libellé des destinataires
function annonce_cible_label(string $destType, ?int $destId = null): string {
    switch ($destType) {
        case 'profs':
            return 'Tous les professeurs';
        case 'eleves':
            return 'Tous les élèves';
        case 'user':
            return $destId ? 'Utilisateur spécifique (ID : ' . $destId . ')' : 'Utilisateur ciblé';
        case 'tous':
        default:
            return 'Tous (Tout le monde)';
    }
}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container my-4">
    <div class="d-none justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            ← Retour à la liste
        </a>
        <?php if ($annonce): ?>
        <div class="d-flex gap-2">
            <a href="edit.php?id=<?= (int)$annonce['id'] ?>" class="btn btn-sm btn-outline-primary">
                Modifier
            </a>
            <a href="delete.php?id=<?= (int)$annonce['id'] ?>" class="btn btn-sm btn-outline-danger"
                onclick="return confirm('Voulez-vous vraiment supprimer cette annonce ?');">
                Supprimer
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($annonce): 
        $cibleLabel = annonce_cible_label(
            (string)$annonce['dest_type'], 
            $annonce['dest_id'] ? (int)$annonce['dest_id'] : null
        );
        $auteurNom = !empty($annonce['auteur_nom']) ? trim($annonce['auteur_nom']) : null;
    ?>
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">

            <div class="mb-4">
                <div class="text-uppercase text-muted small fw-bold">Communiqué/Annonce/Message
                    <?php if (!empty($annonce['anneeScolaire'])): ?>
                    <span class="badge bg-secondary">
                        Année scolaire : <?= htmlspecialchars($annonce['anneeScolaire']) ?>
                    </span>
                    <?php endif; ?>
                </div>

                <div class="d-none justify-content-center gap-2 flex-wrap text-muted small mt-2">
                    <span class="badge bg-info text-dark">
                        Destinataires : <?= htmlspecialchars($cibleLabel) ?>
                    </span>
                    <?php if (!empty($annonce['anneeScolaire'])): ?>
                    <span class="badge bg-secondary">
                        Année scolaire : <?= htmlspecialchars($annonce['anneeScolaire']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <hr>
            <h1 class="h3 mb-2 text-primary"><?= htmlspecialchars($annonce['titre']) ?></h1>
            <div class="my-4" style="white-space: pre-line; line-height: 1.7; font-size: 1.05rem;">
                <?= nl2br(htmlspecialchars($annonce['contenu'])) ?>
            </div>

            <hr>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 text-muted small">
                <div>
                    Publié le
                    <strong><?= date('d/m/Y à H:i', strtotime($annonce['created_at'])) ?></strong>
                    <?php if ($auteurNom): ?>
                    par <strong><?= htmlspecialchars($auteurNom) ?></strong>
                    (<?= htmlspecialchars(ucfirst($annonce['sender_role'])) ?>)
                    <?php else: ?>
                    par <strong><?= htmlspecialchars(ucfirst($annonce['sender_role'])) ?></strong>
                    <?php endif; ?>
                </div>
                <div>
                    <button class="btn btn-sm btn-primary" type="button" onclick="window.print()">
                        Répondre
                    </button>
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