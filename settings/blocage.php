<?php
// /directeur/gestion_blocage.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../includes/auth.php'; 
require_once __DIR__ . '/../includes/helpers.php';

$usePdo = isset($pdo) && !isset($con);
$message = '';
$messageType = '';

// -------------------------------------------------------------------------
// 1) TRAITEMENT DES ACTIONS (CRÉATION ET SUPPRESSION/DÉBLOCAGE)
// -------------------------------------------------------------------------

// Supprimer un blocage (Débloquer)
if (isset($_GET['action']) && $_GET['action'] === 'supprimer' && !empty($_GET['id'])) {
    $blocageId = (int)$_GET['id'];
    try {
        if ($usePdo) {
            $stmt = $pdo->prepare("DELETE FROM blocage_acces WHERE id = ?");
            $stmt->execute([$blocageId]);
        } else {
            $stmt = $con->prepare("DELETE FROM blocage_acces WHERE id = ?");
            $stmt->bind_param('i', $blocageId);
            $stmt->execute();
            $stmt->close();
        }
        $message = "Le blocage a été levé avec succès. Les contenus redeviennent visibles pour ce ménage.";
        $messageType = "success";
    } catch (Throwable $e) {
        $message = "Erreur lors du déblocage : " . $e->getMessage();
        $messageType = "danger";
    }
}

// Ajouter un nouveau blocage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_blocage'])) {
    $menageId  = !empty($_POST['menage_id']) ? (int)$_POST['menage_id'] : 0;
    $typeAcces = $_POST['type_acces'] ?? 'tous';
    $dateDebut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : date('Y-m-d');
    $motif     = trim($_POST['motif'] ?? '');
    $createdBy = $_SESSION['user']['nom'] ?? $_SESSION['username'] ?? 'Directeur';

    if ($menageId <= 0) {
        $message = "Veuillez sélectionner un ménage à bloquer.";
        $messageType = "warning";
    } else {
        try {
            $sqlIns = "INSERT INTO blocage_acces (menage_id, type_acces, date_debut, statut, motif, created_by) 
                       VALUES (?, ?, ?, 'actif', ?, ?)";
            
            if ($usePdo) {
                $stmt = $pdo->prepare($sqlIns);
                $stmt->execute([$menageId, $typeAcces, $dateDebut, $motif, $createdBy]);
            } else {
                $stmt = $con->prepare($sqlIns);
                $stmt->bind_param('issss', $menageId, $typeAcces, $dateDebut, $motif, $createdBy);
                $stmt->execute();
                $stmt->close();
            }

            $message = "Le blocage a été appliqué au ménage avec succès.";
            $messageType = "success";
        } catch (Throwable $e) {
            $message = "Erreur SQL : " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// -------------------------------------------------------------------------
// 2) CHARGEMENT DES MÉNAGES AVEC LEURS ÉLÈVES ET CLASSES ASSOCIÉES
// -------------------------------------------------------------------------

$listMenages = [];
$listBlocages = [];

try {
    // Requête récupérant chaque ménage et la liste formatée de ses enfants avec leur classe
    $sqlMenages = "
        SELECT 
            m.id, 
            m.noms AS menage_nom, 
            m.telephone,
            GROUP_CONCAT(
                CONCAT('• ', e.nom, ' ', e.postnom, ' (', CONCAT(cl.description, cy.description), ')') 
                SEPARATOR '<br>'
            ) AS liste_enfants
        FROM menage m
        LEFT JOIN eleve e ON e.menage = m.id AND e.STATUS = 'actif'
        LEFT JOIN classe cl ON e.classe = cl.id
        LEFT JOIN cycle cy ON cy.id = cl.cycle
        WHERE m.STATUS = 'actif'
        GROUP BY m.id
        ORDER BY m.noms ASC
    ";

    if ($usePdo) {
        $listMenages = $pdo->query($sqlMenages)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $resM = $con->query($sqlMenages);
        if ($resM) {
            while ($r = $resM->fetch_assoc()) $listMenages[] = $r;
        }
    }

    // Requête des blocages actifs avec détails des élèves/classes
    $sqlBlocages = "
        SELECT 
            b.id,
            b.type_acces,
            b.date_debut,
            b.motif,
            b.created_at,
            m.noms AS menage_nom,
            m.telephone,
            GROUP_CONCAT(
                CONCAT('• ', e.nom, ' ', e.postnom, ' (', CONCAT(cl.description, cy.description), ')') 
                SEPARATOR '<br>'
            ) AS enfants_details
        FROM blocage_acces b
        INNER JOIN menage m ON b.menage_id = m.id
        LEFT JOIN eleve e ON e.menage = m.id AND e.STATUS = 'actif'
        LEFT JOIN classe cl ON e.classe = cl.id
        LEFT JOIN cycle cy ON cy.id = cl.cycle
        WHERE b.statut = 'actif'
        GROUP BY b.id
        ORDER BY b.id DESC
    ";

    if ($usePdo) {
        $listBlocages = $pdo->query($sqlBlocages)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $resB = $con->query($sqlBlocages);
        if ($resB) {
            while ($r = $resB->fetch_assoc()) $listBlocages[] = $r;
        }
    }

} catch (Throwable $e) {}

require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="mb-4">
        <h3 class="fw-bold text-dark mb-0">🔒 Restreindre l'accès aux contenus</h3>
        <p class="text-muted small">Bloquez l'accès au Journal de classe et Résumés de cours par ménage (famille).</p>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show rounded-3 shadow-sm" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- FORMULAIRE DE BLOCAGE -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-danger text-white py-3 fw-bold rounded-top-4">
                    ➕ Appliquer un blocage
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <input type="hidden" name="creer_blocage" value="1">

                        <!-- Sélection Ménage -->
                        <div class="mb-3">
                            <label for="menage_id" class="form-label fw-bold">Sélectionner le Ménage :</label>
                            <select name="menage_id" id="menage_id" class="form-select" required>
                                <option value="" selected disabled>-- Choisir une famille --</option>
                                <?php foreach ($listMenages as $m): ?>
                                <option value="<?= $m['id'] ?>">
                                    <?= htmlspecialchars($m['menage_nom']) ?> (Tel:
                                    <?= htmlspecialchars($m['telephone']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Type d'accès à bloquer -->
                        <div class="mb-3">
                            <label for="type_acces" class="form-label fw-bold">Éléments à bloquer :</label>
                            <select name="type_acces" id="type_acces" class="form-select" required>
                                <option value="tous">🚫 TOUT (Journal + Résumés)</option>
                                <option value="journal">📖 Journal de classe uniquement</option>
                                <option value="resume">📝 Résumés de cours uniquement</option>
                            </select>
                        </div>

                        <!-- Date de début -->
                        <div class="mb-3">
                            <label for="date_debut" class="form-label fw-bold">Date de début du blocage :</label>
                            <input type="date" name="date_debut" id="date_debut" class="form-control"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <!-- Motif -->
                        <div class="mb-3">
                            <label for="motif" class="form-label fw-bold">Motif / Observation :</label>
                            <input type="text" name="motif" id="motif" class="form-control"
                                placeholder="Ex: Défaut de paiement minerval tranche 2">
                        </div>

                        <button type="submit" class="btn btn-danger w-100 fw-bold py-2 rounded-3">
                            🔒 Valider le blocage
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TABLEAU DES BLOCAGES EN COURS -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-light py-3 fw-bold">
                    📋 Liste des ménages sous restriction
                </div>
                <div class="card-body p-0">
                    <?php if (empty($listBlocages)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-shield-check display-4 d-block mb-3 text-success"></i>
                        <h5 class="fw-bold">Aucun blocage actif</h5>
                        <p class="mb-0 small">Toutes les familles ont actuellement un accès complet.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%;">Ménage & Élève(s)</th>
                                    <th style="width: 20%;">Contenu bloqué</th>
                                    <th style="width: 15%;">Depuis le</th>
                                    <th style="width: 20%;">Motif</th>
                                    <th style="width: 15%;" class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listBlocages as $b): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark fs-6">
                                            👨‍👩‍👧‍👦 <?= htmlspecialchars($b['menage_nom']) ?>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            <?= $b['enfants_details'] ?: '<i>Aucun élève rattaché</i>' ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                                    $badgeClass = match($b['type_acces']) {
                                                        'journal' => 'bg-info text-dark',
                                                        'resume'  => 'bg-warning text-dark',
                                                        default   => 'bg-danger'
                                                    };
                                                    $typeLabel = match($b['type_acces']) {
                                                        'journal' => '📖 Journal seul',
                                                        'resume'  => '📝 Résumés seuls',
                                                        default   => '🚫 Journal + Résumés'
                                                    };
                                                ?>
                                        <span class="badge <?= $badgeClass ?> px-2 py-1">
                                            <?= $typeLabel ?>
                                        </span>
                                    </td>
                                    <td class="small fw-bold text-muted">
                                        📅 <?= date('d/m/Y', strtotime($b['date_debut'])) ?>
                                    </td>
                                    <td class="small text-secondary">
                                        <?= htmlspecialchars($b['motif'] ?: '—') ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="?action=supprimer&id=<?= $b['id'] ?>"
                                            class="btn btn-sm btn-outline-success fw-bold rounded-pill px-3"
                                            onclick="return confirm('Voulez-vous levé le blocage pour <?= addslashes($b['menage_nom']) ?> ?')">
                                            🔓 Débloquer
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>