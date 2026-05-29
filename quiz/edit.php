<?php
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
    // Charger le quiz
    $stmt = $pdo->prepare("SELECT * FROM quiz WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$quizId]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        header('Location: ' . BASE_URL . '/quiz/index.php');
        exit;
    }

    // Charger les questions
    $stmtQ = $pdo->prepare("
        SELECT q.*, 
        GROUP_CONCAT(k.keyword SEPARATOR ', ') AS keywords
        FROM quiz_question q
        LEFT JOIN quiz_question_keyword k 
            ON k.question_id = q.id
        WHERE q.quiz_id = :id
        GROUP BY q.id
        ORDER BY q.sort_order, q.id
    ");
    $stmtQ->execute([':id'=>$quizId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $error = "Erreur lors du chargement.";
}
?>

<div class="container my-4">
    <h1 class="h5 mb-3">Modifier le Quiz</h1>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form id="quiz-form">
                <!-- Titre (non modifiable) -->
                <div class="mb-3">
                    <label class="form-label">Titre</label>
                    <input type="text" name="titre" class="form-control" value="<?= e($quiz['titre']) ?>" readonly>
                </div>

                <!-- Description -->
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= e($quiz['description']) ?></textarea>
                </div>

                <!-- Type & Format -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Type</label>
                        <select name="type_quiz" class="form-select" required>
                            <?php foreach (['Exercice','Devoir','Interrogation','Examen'] as $t): ?>
                            <option value="<?= $t ?>" <?= $quiz['type_quiz']===$t?'selected':'' ?>><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Format</label>
                        <select name="format" class="form-select" disabled>
                            <?php foreach (['QCM','RQ','PJ'] as $f): ?>
                            <option value="<?= $f ?>" <?= $quiz['format']===$f?'selected':'' ?>><?= $f ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Date limite -->
                <div class="mb-3">
                    <label class="form-label">Date limite</label>
                    <input type="date" name="date_limite" class="form-control" value="<?= e($quiz['date_limite']) ?>">
                </div>

                <hr class="my-4">
                <h5 class="mb-3">Questions</h5>

                <div id="questions-container">
                    <?php foreach ($questions as $q): ?>
                    <div class="card mb-3 question-card" data-id="<?= $q['id'] ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <strong>Question #<?= $q['id'] ?></strong>
                                <button type="button" class="btn btn-sm btn-danger delete-question">Supprimer</button>
                            </div>

                            <textarea class="form-control mt-2 question-text"><?= e($q['question_text']) ?></textarea>

                            <div class="row mt-2">
                                <div class="col-md-4">
                                    <input type="number" class="form-control question-points"
                                        value="<?= $q['points'] ?>" placeholder="Points">
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select question-type" disabled>
                                        <option value="QCM" <?= $q['TYPE']==='QCM'?'selected':'' ?>>QCM</option>
                                        <option value="RQ" <?= $q['TYPE']==='RQ'?'selected':'' ?>>RQ</option>
                                        <option value="PJ" <?= $q['TYPE']==='PJ'?'selected':'' ?>>PJ</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Réponse attendue (RQ ou PJ) -->
                            <?php if (in_array($q['TYPE'], ['RQ','PJ'])): ?>
                            <div class="mt-3">
                                <label>Réponse attendue</label>
                                <textarea class="form-control expected-answer"
                                    rows="2"><?= e($q['expected_answer'] ?? '') ?></textarea>
                            </div>

                            <div class="mt-3">
                                <label>Mots clés IA</label>
                                <input type="text" class="form-control keywords-input"
                                    value="<?= e($q['keywords'] ?? '') ?>">
                            </div>
                            <?php endif; ?>

                            <!-- QCM -->
                            <?php if ($q['TYPE']==='QCM'): ?>
                            <?php
                                $stmtC = $pdo->prepare("SELECT * FROM quiz_choice WHERE question_id = ? ORDER BY sort_order, id");
                                $stmtC->execute([$q['id']]);
                                $choices = $stmtC->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <div class="mt-3 choices-container">
                                <h6>Choix QCM</h6>
                                <?php foreach ($choices as $ch): ?>
                                <div class="input-group mb-2 choice-item" data-id="<?= $ch['id'] ?>">
                                    <input type="text" class="form-control choice-text"
                                        value="<?= e($ch['choice_text']) ?>">
                                    <span class="input-group-text">
                                        <input type="checkbox" class="choice-correct"
                                            <?= $ch['is_correct']?'checked':'' ?>>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= BASE_URL ?>/quiz/view.php?id=<?= $quizId ?>" class="btn btn-dark">← Annuler</a>
                    <button type="button" id="save-all" class="btn btn-success">💾 Enregistrer toutes les
                        modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let deletedQuestions = [];

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('delete-question')) {
        let card = e.target.closest('.question-card');
        let qid = card.dataset.id;
        if (card.classList.contains('deleted')) {
            card.classList.remove('deleted');
            e.target.textContent = "Supprimer";
            deletedQuestions = deletedQuestions.filter(id => id != qid);
        } else {
            if (!confirm("Supprimer cette question ?")) return;
            card.classList.add('deleted');
            e.target.textContent = "Annuler";
            deletedQuestions.push(qid);
        }
    }
});

document.getElementById('save-all').addEventListener('click', async function() {
    let form = document.getElementById('quiz-form');

    let quiz = {
        id: <?= $quizId ?>,
        titre: form.querySelector('[name="titre"]').value,
        description: form.querySelector('[name="description"]').value,
        type_quiz: form.querySelector('[name="type_quiz"]').value,
        format: form.querySelector('[name="format"]').value,
        date_limite: form.querySelector('[name="date_limite"]').value
    };

    let questions = [];
    document.querySelectorAll('.question-card').forEach(card => {
        let qid = card.dataset.id;
        if (!card.classList.contains('deleted')) {
            let q = {
                id: qid,
                text: card.querySelector('.question-text').value,
                points: card.querySelector('.question-points').value,
                type: card.querySelector('.question-type').value,
                keywords: card.querySelector('.keywords-input') ? card.querySelector(
                    '.keywords-input').value : '',
                expected_answer: card.querySelector('.expected-answer') ? card.querySelector(
                    '.expected-answer').value : '',
                choices: []
            };
            card.querySelectorAll('.choice-item').forEach(c => {
                q.choices.push({
                    id: c.dataset.id,
                    text: c.querySelector('.choice-text').value,
                    correct: c.querySelector('.choice-correct').checked ? 1 : 0
                });
            });
            questions.push(q);
        }
    });

    let res = await fetch('ajax/save_full_quiz.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            quiz,
            questions,
            deletedQuestions
        })
    });

    let data = await res.json();
    if (data.success) {
        alert("Quiz modifié avec succès");
        window.location.href = data.redirect;
    } else {
        alert(data.error);
    }
});
</script>

<style>
.question-card.deleted {
    background-color: #f8d7da !important;
    text-decoration: line-through;
}
</style>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>