<?php
// /directeur/classe/fiche_eleve.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur(); // fournit $pdo, e(), BASE_URL + session + anti-cache
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$eleveId = (int)($_GET['id'] ?? 0);

$err = '';
$eleve = null;
$presenceStats = [
  'total'   => 0,
  'present' => 0,
  'absent'  => 0,
];
$quizStats = [
  'total'      => 0,
  'corrige'    => 0,
  'moyenne'    => null,
  'max'        => null,
  'min'        => null,
];
$quizRecent = [];

if ($eleveId <= 0) {
  $err = "Élève introuvable (ID manquant).";
}

if (!$err) {
  try {
    // 1) Profil de base
    $stmt = $pdo->prepare("
      SELECT 
        e.*,
        c.description AS classe_desc,
        cy.description AS cycle_desc,
        m.noms       AS menage_nom,
        m.telephone  AS menage_tel,
        m.avenue     AS menage_avenue,
        m.quartier   AS menage_quartier,
        m.commune    AS menage_commune
      FROM eleve e
      JOIN classe c ON c.id = e.classe
      LEFT JOIN cycle cy ON cy.id = c.cycle
      JOIN menage m ON m.id = e.menage
      WHERE e.id = :id
      LIMIT 1
    ");
    $stmt->execute([':id' => $eleveId]);
    $eleve = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$eleve) {
      $err = "Élève introuvable en base.";
    }
  } catch (Throwable $e) {
    $err = "Erreur lors du chargement du profil élève.";
  }
}

if (!$err) {
  try {
    // 2) Statistiques de présence globales (toutes années / toutes classes)
    // Basées sur appel + appel_detail
    $stmt = $pdo->prepare("
      SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN ad.statut = 'present' THEN 1 ELSE 0 END) AS present,
        SUM(CASE WHEN ad.statut = 'absent'  THEN 1 ELSE 0 END) AS absent
      FROM appel_detail ad
      JOIN appel a ON a.id = ad.appel_id
      WHERE ad.eleve_id = :id
    ");
    $stmt->execute([':id' => $eleveId]);
    $presenceStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $presenceStats;

    $presenceStats['total']   = (int)($presenceStats['total'] ?? 0);
    $presenceStats['present'] = (int)($presenceStats['present'] ?? 0);
    $presenceStats['absent']  = (int)($presenceStats['absent'] ?? 0);
  } catch (Throwable $e) {
    // On ne bloque pas tout si erreur : on garde les valeurs par défaut
  }
}

if (!$err) {
  try {
    // 3) Stat global quiz (table quiz_submission + quiz)
    $stmt = $pdo->prepare("
      SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN qs.statut = 'corrige' THEN 1 ELSE 0 END) AS corrige,
        AVG(qs.note_totale) AS moyenne,
        MAX(qs.note_totale) AS max_note,
        MIN(qs.note_totale) AS min_note
      FROM quiz_submission qs
      JOIN quiz q ON q.id = qs.quiz_id
      WHERE qs.eleve_id = :id
    ");
    $stmt->execute([':id' => $eleveId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      $quizStats['total']   = (int)($row['total'] ?? 0);
      $quizStats['corrige'] = (int)($row['corrige'] ?? 0);
      $quizStats['moyenne'] = $row['moyenne'] !== null ? (float)$row['moyenne'] : null;
      $quizStats['max']     = $row['max_note'] !== null ? (float)$row['max_note'] : null;
      $quizStats['min']     = $row['min_note'] !== null ? (float)$row['min_note'] : null;
    }

    // 4) Derniers quiz de l'élève (liste courte)
    $stmt = $pdo->prepare("
      SELECT 
        qs.id,
        qs.date_submitted,
        qs.note_totale,
        qs.statut,
        q.titre,
        q.type_quiz,
        q.format
      FROM quiz_submission qs
      JOIN quiz q ON q.id = qs.quiz_id
      WHERE qs.eleve_id = :id
      ORDER BY qs.date_submitted DESC
      LIMIT 10
    ");
    $stmt->execute([':id' => $eleveId]);
    $quizRecent = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    // Pas bloquant si erreur
  }
}
?>
<div class="container">
  <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-3">
    <h1 class="h4 mb-0">Fiche élève</h1>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= BASE_URL ?>/classe/index.php" class="btn btn-sm btn-outline-secondary">
        ← Retour à la liste
      </a>
    </div>
  </div>

  <?php if ($err): ?>
    <div class="alert alert-danger py-2"><?= e($err) ?></div>
  <?php else: ?>

    <!-- Profil -->
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
          <div>
            <h2 class="h5 mb-1">
              <?= e(trim($eleve['nom'].' '.$eleve['postnom'].' '.$eleve['prenom'])) ?>
            </h2>
            <div class="text-muted small">
              ID : <?= (int)$eleve['id'] ?> — Année scolaire : <?= e($eleve['anneeScolaire']) ?>
            </div>
          </div>
          <div class="text-end">
            <?php
              $genre = strtoupper((string)$eleve['genre']);
              $genreLabel = $genre === 'F' ? 'Fille' : ($genre === 'M' ? 'Garçon' : $genre);
            ?>
            <div>
              <?php if ($genre === 'F'): ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Fille</span>
              <?php elseif ($genre === 'M'): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Garçon</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                  <?= e($genreLabel) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <h6 class="text-uppercase text-muted small mb-2">Scolarité</h6>
            <div><strong>Classe :</strong> <?= e($eleve['classe_desc'] ?? '—') ?></div>
            <div><strong>Cycle :</strong> <?= e($eleve['cycle_desc'] ?? '—') ?></div>
            <div><strong>Date de naissance :</strong> <?= e($eleve['dateDeNaissance'] ?? '—') ?></div>
            <div><strong>Lieu de naissance :</strong> <?= e($eleve['lieu'] ?? '—') ?></div>
          </div>

          <div class="col-md-4">
            <h6 class="text-uppercase text-muted small mb-2">Ménage</h6>
            <div><strong>Responsable :</strong> <?= e($eleve['menage_nom']) ?></div>
            <div><strong>Téléphone :</strong> <?= e($eleve['menage_tel'] ?? '—') ?></div>
            <div><strong>Adresse :</strong> 
              <?= e(($eleve['menage_avenue'] ?? '').' '.$eleve['menage_quartier'].' '.$eleve['menage_commune']) ?>
            </div>
          </div>

          <div class="col-md-4">
            <h6 class="text-uppercase text-muted small mb-2">Infos internes</h6>
            <div class="d-none"><strong>Montant à payer :</strong> <?= number_format((float)$eleve['montant_a_payer'], 2, ',', ' ') ?></div>
            <div><strong>Créé le :</strong> <?= e($eleve['dateCreated'] ?? '—') ?></div>
            <div><strong>Modifié le :</strong> <?= e($eleve['dateUpdate'] ?? '—') ?></div>
            <div><strong>Créé par :</strong> <?= e($eleve['createdby'] ?? '—') ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistiques globales -->
    <div class="row g-3 mb-3">
      <!-- Présence -->
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="h6 mb-0">Présence (global)</h2>
              <span class="badge bg-light text-muted">
                Total jours : <?= (int)$presenceStats['total'] ?>
              </span>
            </div>

            <?php
              $total = max((int)$presenceStats['total'], 0);
              $present = max((int)$presenceStats['present'], 0);
              $absent  = max((int)$presenceStats['absent'], 0);
              $tauxPresence = $total > 0 ? round(($present / $total) * 100) : 0;
            ?>

            <?php if ($total === 0): ?>
              <p class="text-muted small mb-0">
                Aucune donnée de présence n’a encore été encodée pour cet élève.
              </p>
            <?php else: ?>
              <ul class="list-unstyled mb-2 small">
                <li><strong>Présent :</strong> <?= $present ?> jour(s)</li>
                <li><strong>Absent :</strong> <?= $absent ?> jour(s)</li>
                <li><strong>Taux de présence :</strong> <?= $tauxPresence ?> %</li>
              </ul>
              <div class="progress" style="height:8px;">
                <div class="progress-bar bg-success" role="progressbar"
                     style="width: <?= $tauxPresence ?>%;"
                     aria-valuenow="<?= $tauxPresence ?>" aria-valuemin="0" aria-valuemax="100">
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Quiz -->
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="h6 mb-0">Quiz / Évaluations</h2>
              <span class="badge bg-light text-muted">
                Total : <?= (int)$quizStats['total'] ?> quiz
              </span>
            </div>

            <?php if ((int)$quizStats['total'] === 0): ?>
              <p class="text-muted small mb-0">
                Aucun quiz n’a encore été remis par cet élève.
              </p>
            <?php else: ?>
              <ul class="list-unstyled mb-2 small">
                <li><strong>Quiz corrigés :</strong> <?= (int)$quizStats['corrige'] ?></li>
                <li><strong>Note moyenne :</strong> 
                  <?= $quizStats['moyenne'] !== null ? number_format($quizStats['moyenne'], 2, ',', ' ') : '—' ?>
                </li>
                <li><strong>Note max :</strong> 
                  <?= $quizStats['max'] !== null ? number_format($quizStats['max'], 2, ',', ' ') : '—' ?>
                </li>
                <li><strong>Note min :</strong> 
                  <?= $quizStats['min'] !== null ? number_format($quizStats['min'], 2, ',', ' ') : '—' ?>
                </li>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Derniers quiz -->
    <div class="card shadow-sm mb-4">
      <div class="card-body table-responsive">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h2 class="h6 mb-0">Derniers quiz de l'élève</h2>
          <span class="text-muted small">10 plus récents</span>
        </div>

        <?php if (!$quizRecent): ?>
          <p class="text-muted small mb-0">
            Aucun quiz trouvé pour cet élève.
          </p>
        <?php else: ?>
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Titre</th>
                <th>Type</th>
                <th>Format</th>
                <th>Note</th>
                <th>Statut</th>
                <th>Date de remise</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($quizRecent as $q): ?>
                <tr>
                  <td><?= e($q['titre']) ?></td>
                  <td><?= e($q['type_quiz']) ?></td>
                  <td><?= e($q['format']) ?></td>
                  <td>
                    <?php if ($q['note_totale'] === null): ?>
                      <span class="text-muted small">—</span>
                    <?php else: ?>
                      <?= number_format((float)$q['note_totale'], 2, ',', ' ') ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($q['statut'] === 'corrige'): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle">Corrigé</span>
                    <?php else: ?>
                      <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                        <?= e($q['statut']) ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= e($q['date_submitted']) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>
