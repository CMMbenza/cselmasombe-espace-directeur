<?php
// /directeur/quiz/view.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_directeur();
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/navbar.php';

$quizId = (int)($_GET['id'] ?? 0);
if ($quizId <= 0) {
    header('Location: ' . BASE_URL . '/quiz/index.php');
    exit;
}

$error = '';

try {

    /* =========================
       ENTÊTE QUIZ (corrigé quiz_classe)
    ========================== */
    $stmt = $pdo->prepare("
        SELECT 
            q.id,
            q.agent_id,
            q.type_quiz,
            q.format,
            q.titre,
            q.description,
            q.statut,
            q.date_limite,
            q.created_at,

            a.nom,
            a.postnom,
            a.prenom,

            GROUP_CONCAT(DISTINCT c.description SEPARATOR ', ') AS classes,
            GROUP_CONCAT(DISTINCT cy.description SEPARATOR ', ') AS cycles

        FROM quiz q
        JOIN agent a ON a.id = q.agent_id
        JOIN quiz_classe qc ON qc.quiz_id = q.id
        JOIN classe c ON c.id = qc.classe_id
        JOIN cycle cy ON cy.id = c.cycle

        WHERE q.id = :id
        GROUP BY q.id
        LIMIT 1
    ");

    $stmt->execute([':id'=>$quizId]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        header('Location: ' . BASE_URL . '/quiz/index.php');
        exit;
    }

    /* =========================
       QUESTIONS
    ========================== */
    $stmtQ = $pdo->prepare("
        SELECT 
            qq.id,
            qq.TYPE,
            qq.question_text,
            qq.points,
            qq.sort_order,
            qq.expected_answer,
            qq.similarity_min,
            GROUP_CONCAT(k.keyword SEPARATOR ', ') AS keywords
        FROM quiz_question qq
        LEFT JOIN quiz_question_keyword k 
               ON k.question_id = qq.id
        WHERE qq.quiz_id = :id
        GROUP BY 
            qq.id,
            qq.TYPE,
            qq.question_text,
            qq.points,
            qq.sort_order,
            qq.expected_answer,
            qq.similarity_min
        ORDER BY qq.sort_order, qq.id
    ");

    $stmtQ->execute([':id'=>$quizId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    /* =========================
       CHOIX QCM
    ========================== */
    $choicesByQ = [];

    if (!empty($questions)) {
        $ids = array_column($questions,'id');
        $in  = implode(',', array_fill(0,count($ids),'?'));

        $stmtC = $pdo->prepare("
            SELECT id, question_id, choice_text, is_correct, sort_order
            FROM quiz_choice
            WHERE question_id IN ($in)
            ORDER BY question_id, sort_order, id
        ");

        $stmtC->execute($ids);

        while ($c = $stmtC->fetch(PDO::FETCH_ASSOC)) {
            $choicesByQ[(int)$c['question_id']][] = $c;
        }
    }

    /* =========================
       PIÈCES JOINTES
    ========================== */
    $stmtA = $pdo->prepare("
        SELECT id, file_path, original_name, mime_type, file_size, uploaded_at
        FROM quiz_attachment
        WHERE quiz_id = :id
        ORDER BY id
    ");
    $stmtA->execute([':id'=>$quizId]);
    $attachments = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    /* =========================
       SOUMISSIONS
    ========================== */
    $stmtS = $pdo->prepare("
        SELECT COUNT(*) 
        FROM quiz_submission 
        WHERE quiz_id = :id
    ");
    $stmtS->execute([':id'=>$quizId]);
    $nbSub = (int)$stmtS->fetchColumn();

} catch (Throwable $e) {
    $error = "Impossible de charger les détails du quiz.";
}

$baremeTotal = 0;
foreach ($questions as $q) {
    $baremeTotal += (int)$q['points'];
}

// Fonction pour formater la date en français
function dateFr($dateStr) {
    if (!$dateStr) return '';
    setlocale(LC_TIME, 'fr_FR.UTF-8');
    $timestamp = strtotime($dateStr);
    return strftime('%d %B %Y', $timestamp);
}
?>

<div class="container my-4">

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
    <?php else: ?>

    <div class="d-flex align-items-start gap-3 mb-3">
        <div>
            <h1 class="h5 mb-1"><?= e($quiz['titre']) ?></h1>
            <div class="small text-muted">
                Prof:
                <strong><?= e($quiz['nom'].' '.$quiz['postnom'].' '.$quiz['prenom']) ?></strong><br>

                Classes:
                <strong><?= e($quiz['classes']) ?> <?= e($quiz['cycles']) ?></strong><br>

                <!-- Cycles:
                <strong><?= e($quiz['cycles']) ?></strong><br> -->

                <label for="" class="text-success">
                    Type: <?= e($quiz['type_quiz']) ?> •
                    Format: <?= e($quiz['format']) ?> •
                </label>
                <label for="" class="text-primary font-bold">Barème total: <?= $baremeTotal ?> pts</label> •
                Créé: <?= e(dateFr($quiz['created_at'])) ?>

                <?php if (!empty($quiz['date_limite'])): ?>
                • <label for="" class="text-danger">Date limite: <?= e(dateFr($quiz['date_limite'])) ?></label>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $badge = 'secondary';
        if ($quiz['statut']==='approuvé') $badge='success';
        elseif ($quiz['statut']==='en attente') $badge='warning';
        elseif ($quiz['statut']==='rejeter') $badge='danger';
        elseif ($quiz['statut']==='à revoir') $badge='info';
    ?>
        <span class="badge text-bg-<?= $badge ?> ms-auto">
            <?= e($quiz['statut']) ?>
        </span>
    </div>

    <?php if (!empty($quiz['description'])): ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <?= nl2br(e($quiz['description'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3">

        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3">Questions</h5>

                    <?php if (empty($questions)): ?>
                    <div class="text-muted">Aucune question.</div>
                    <?php else: ?>

                    <ol class="ps-3">
                        <?php foreach ($questions as $q): ?>
                        <li class="mb-3">

                            <div class="fw-semibold">
                                <?= nl2br(e($q['question_text'])) ?>
                            </div>

                            <div class="small text-muted mb-2">
                                Type: <?= e($q['TYPE']) ?> •
                                Points: <?= e((string)$q['points']) ?>
                            </div>

                            <?php if ($q['TYPE']==='QCM'): ?>

                            <?php $chs = $choicesByQ[(int)$q['id']] ?? []; ?>
                            <?php if ($chs): ?>
                            <ul class="list-unstyled ms-1">
                                <?php foreach ($chs as $ch): ?>
                                <li class="mb-1">
                                    <span
                                        class="badge text-bg-<?= (int)$ch['is_correct'] ? 'success' : 'secondary' ?> me-1">
                                        <?= (int)$ch['is_correct'] ? '✓' : '•' ?>
                                    </span>
                                    <?= e($ch['choice_text']) ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>

                            <?php else: ?>

                            <?php if (!empty($q['expected_answer'])): ?>
                            <div class="mt-2">
                                <span class="badge text-bg-primary">Réponse attendue</span>
                                <div class="mt-1"><?= nl2br(e($q['expected_answer'])) ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($q['keywords'])): ?>
                            <div class="mt-2">
                                <span class="badge text-bg-danger">Mots clés</span>
                                <div class="mt-1"><?= e($q['keywords']) ?></div>
                            </div>
                            <?php endif; ?>

                            <?php endif; ?>

                        </li>
                        <?php endforeach; ?>
                    </ol>

                    <?php endif; ?>

                </div>
            </div>
        </div>

        <div class="col-lg-4">

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="mb-2">Pièces jointes</h6>

                    <?php if (empty($attachments)): ?>
                    <div class="text-muted">Aucune pièce jointe.</div>
                    <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($attachments as $a): ?>
                        <li class="mb-2">
                            <a href="<?= e($a['file_path']) ?>" target="_blank">
                                <?= e($a['original_name']) ?>
                            </a>
                            <div class="small text-muted">
                                <?= e($a['mime_type']) ?> • <?= (int)$a['file_size'] ?> o
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="mb-2">Soumissions</h6>
                    <div class="display-6"><?= (int)$nbSub ?></div>
                    <div class="small text-muted">Nombre total de remises.</div>
                </div>
            </div>

        </div>
    </div>
    <div class="d-flex align-items-start justify-content-start mb-3 mt-4">
        <a href="<?= BASE_URL ?>/quiz/index.php" class="me-3 btn btn-dark btn-md">← Retour</a>

        <?php if (in_array($quiz['statut'], ['en attente', 'rejeter','à revoir'])): ?>

        <form method="post" action="<?= BASE_URL ?>/quiz/delete.php" class="d-inline"
            onsubmit="return confirm('Voulez-vous vraiment supprimer ce quiz ?');">
            <input type="hidden" name="id" value="<?= (int)$quiz['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="btn btn-danger btn-md me-3">🗑 Supprimer ce quiz</button>
        </form>

        <a href="<?= BASE_URL ?>/quiz/edit.php?id=<?= (int)$quiz['id'] ?>" class="btn btn-warning btn-md me-3">
            ✏ Modifier
        </a>

        <!-- =========================
         Boutons statut
         ========================= -->
        <form method="post" action="<?= BASE_URL ?>/quiz/update_status.php" class="d-inline me-2">
            <input type="hidden" name="quiz_id" value="<?= (int)$quiz['id'] ?>">
            <input type="hidden" name="statut" value="approuvé">
            <button type="submit" class="btn btn-success btn-md">✅ Approuver</button>
        </form>

        <form method="post" action="<?= BASE_URL ?>/quiz/update_status.php" class="d-inline me-2">
            <input type="hidden" name="quiz_id" value="<?= (int)$quiz['id'] ?>">
            <input type="hidden" name="statut" value="à revoir">
            <button type="submit" class="btn btn-info btn-md">📝 À revoir</button>
        </form>

        <form method="post" action="<?= BASE_URL ?>/quiz/update_status.php" class="d-inline">
            <input type="hidden" name="quiz_id" value="<?= (int)$quiz['id'] ?>">
            <input type="hidden" name="statut" value="rejeter">
            <button type="submit" class="btn btn-danger btn-md">❌ Rejeter</button>
        </form>

        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>