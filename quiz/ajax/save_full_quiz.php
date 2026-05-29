<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_directeur();

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data || !isset($data['questions']) || !isset($data['quiz'])) {
        throw new Exception("Données invalides");
    }

    $quiz = $data['quiz'];
    $questions = $data['questions'];
    $deletedQuestions = $data['deletedQuestions'] ?? [];

    if (empty($questions) && empty($deletedQuestions)) {
        throw new Exception("Aucune question à enregistrer");
    }

    /* =========================
       Commencer transaction
    ========================== */
    $pdo->beginTransaction();

    /* =========================
       Supprimer les questions
    ========================== */
    if (!empty($deletedQuestions)) {
        $stmtDel = $pdo->prepare("DELETE FROM quiz_question WHERE id = ?");
        foreach ($deletedQuestions as $qid) {
            $stmtDel->execute([(int)$qid]);
        }
    }

    /* =========================
       Récupérer quiz_id pour la mise à jour
    ========================== */
    $quizId = (int)$quiz['id'];
    $stmtCheck = $pdo->prepare("SELECT id FROM quiz WHERE id = ? LIMIT 1");
    $stmtCheck->execute([$quizId]);
    if (!$stmtCheck->fetchColumn()) {
        throw new Exception("Quiz introuvable");
    }

    /* =========================
       UPDATE Quiz
       Seules les valeurs modifiables sont changées (Description, Date limite)
       Titre et Format sont considérés comme non modifiables
    ========================== */
    $stmtQuiz = $pdo->prepare("
        UPDATE quiz
        SET description = :description,
            date_limite = :date
        WHERE id = :id
    ");
    $stmtQuiz->execute([
        ':description' => $quiz['description'] ?? '',
        ':date'        => $quiz['date_limite'] ?: null,
        ':id'          => $quizId
    ]);

    /* =========================
       UPDATE Questions
    ========================== */
    foreach ($questions as $q) {
        $qid = (int)$q['id'];

        // Récupérer le type actuel côté serveur (sécurité)
        $stmtType = $pdo->prepare("SELECT TYPE FROM quiz_question WHERE id = ? LIMIT 1");
        $stmtType->execute([$qid]);
        $typeQuestion = $stmtType->fetchColumn();
        if (!$typeQuestion) continue; // question supprimée côté serveur

        // Mise à jour
        $stmtQ = $pdo->prepare("
            UPDATE quiz_question
            SET question_text = :text,
                points = :points,
                expected_answer = :answer
            WHERE id = :id
        ");
        $stmtQ->execute([
            ':text'   => $q['text'] ?? '',
            ':points' => (int)($q['points'] ?? 0),
            ':answer' => $q['expected_answer'] ?? null,
            ':id'     => $qid
        ]);

        /* =========================
           Mots clés
        ========================== */
        $pdo->prepare("DELETE FROM quiz_question_keyword WHERE question_id = ?")
            ->execute([$qid]);

        if (!empty($q['keywords'])) {
            $keywords = array_map('trim', explode(',', $q['keywords']));
            $stmtK = $pdo->prepare("INSERT INTO quiz_question_keyword (question_id, keyword) VALUES (?, ?)");
            foreach ($keywords as $k) {
                if ($k !== '') $stmtK->execute([$qid, $k]);
            }
        }

        /* =========================
           Choix QCM
        ========================== */
        if ($typeQuestion === 'QCM' && !empty($q['choices'])) {
            $stmtC = $pdo->prepare("
                UPDATE quiz_choice
                SET choice_text = :text,
                    is_correct = :correct
                WHERE id = :id
            ");
            foreach ($q['choices'] as $c) {
                $stmtC->execute([
                    ':text'    => $c['text'] ?? '',
                    ':correct' => $c['correct'] ? 1 : 0,
                    ':id'      => (int)$c['id']
                ]);
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'redirect' => BASE_URL . "/quiz/view.php?id=" . $quizId
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}