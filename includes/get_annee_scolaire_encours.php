<?php
// includes/get_annee_scolaire_encours.php
declare(strict_types=1);

// S'assure que la connexion $pdo est chargée
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
}

/**
 * Récupère l'année scolaire en cours depuis la base de données.
 */
function getAnneeScolaireEncours(PDO $pdo): ?array
{
    static $anneeEncours = null;

    if ($anneeEncours === null) {
        $stmt = $pdo->query("
            SELECT * 
            FROM annee_scolaire 
            WHERE status = 'encours' 
            LIMIT 1
        ");
        
        $result = $stmt->fetch();
        $anneeEncours = $result ?: null;
    }

    return $anneeEncours;
}

// Récupération globale
$annee_encours_data = getAnneeScolaireEncours($pdo);

// Constantes globales adaptées à ta table
define('ANNEE_SCOLAIRE_ID', $annee_encours_data['id'] ?? 0);
define('ANNEE_SCOLAIRE_LIBELLE', $annee_encours_data['annee_scolaire'] ?? 'Non définie');