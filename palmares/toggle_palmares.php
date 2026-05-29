<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';

header('Content-Type: application/json');

try {

    require_directeur();

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {

        echo json_encode([
            'success' => false,
            'message' => 'ID invalide'
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE palmares_trimestre
        SET autorise = IF(autorise = 1, 0, 1)
        WHERE id = ?
    ");

    $ok = $stmt->execute([$id]);

    echo json_encode([
        'success' => $ok
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}