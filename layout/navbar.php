<?php
// /directeur/layout/navbar.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';

$uriPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$BASE    = defined('BASE_URL') ? BASE_URL : '/directeur';

function is_active(array|string $needles, string $haystack): string {
    $needles = is_array($needles) ? $needles : [$needles];
    foreach ($needles as $n) {
        if ($n !== '' && strpos($haystack, $n) !== false) {
            return 'active';
        }
    }
    return '';
}

function aria_current(string $class): string {
    return $class === 'active' ? 'aria-current="page"' : '';
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom mb-3">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= $BASE ?>/dashboard.php">Profil Directeur</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Basculer la navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div id="mainNav" class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <?php $a = is_active(['/dashboard.php'], $uriPath); ?>
                <li class="nav-item">
                    <a class="nav-link <?= $a ?>" <?= aria_current($a) ?> href="<?= $BASE ?>/dashboard.php">
                        Tableau de bord
                    </a>
                </li>

                <?php
                // Menages / Elèves
                $scholarNeedles = ['/menages/', '/classe/', '/paiements/', '/paiements_divers/'];
                $activeScholar  = is_active($scholarNeedles, $uriPath) ? 'active' : '';
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $activeScholar ?>" href="#" id="scolariteDropdown"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Menages/Elèves
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="scolariteDropdown">
                        <?php $a = is_active(['/menages/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/menages/">
                                Ménages (Famille)
                            </a>
                        </li>

                        <?php $a = is_active(['/menages/eleves.php'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/menages/eleves.php">
                                Liste des élèves
                            </a>
                        </li>
                    </ul>
                </li>

                <?php $a = is_active(['/annonces/'], $uriPath); ?>
                <li class="nav-item">
                    <a class="nav-link <?= $a ?>" <?= aria_current($a) ?> href="<?= $BASE ?>/annonces/">
                        Faire un communiqué
                    </a>
                </li>

                <?php $a = is_active(['/classe/'], $uriPath); ?>
                <li class="nav-item">
                    <a class="nav-link <?= $a ?>" <?= aria_current($a) ?> href="<?= $BASE ?>/classe/">
                        Gest. classe
                    </a>
                </li>

                <?php
                // Bloc scolarité (caché pour l’instant)
                $scholarNeedles = ['/menages/', '/classe/', '/paiements/', '/paiements_divers/'];
                $activeScholar  = is_active($scholarNeedles, $uriPath) ? 'active' : '';
                ?>
                <li class="d-none nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $activeScholar ?>" href="#" id="scolariteDropdown2"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Scolarité
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="scolariteDropdown2">
                        <?php $a = is_active(['/paiements/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/paiements/">
                                Paiements (scolarité)
                            </a>
                        </li>

                        <?php $a = is_active(['/paiements_divers/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/paiements_divers/">
                                Frais connexes
                            </a>
                        </li>
                    </ul>
                </li>

                <?php
                // Nouveau dropdown "Prof/Enseignant"
                $addProfNeedles = ['/agents/', '/quiz/', '/affectations/', '/cours/'];
                $activeAddProf  = is_active($addProfNeedles, $uriPath) ? 'active' : '';
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $activeAddProf ?>" href="#" id="ajouterProfDropdown"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Prof/Enseignant
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="ajouterProfDropdown">
                        <?php $a = is_active(['/agents/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/agents/">
                                Agent
                            </a>
                        </li>

                        <?php $a = is_active(['/quiz/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/quiz/">
                                Quiz
                            </a>
                        </li>

                        <?php $a = is_active(['/affectations/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/affectations/">
                                Affectation
                            </a>
                        </li>

                        <?php $a = is_active(['/cours/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/cours/">
                                Cours
                            </a>
                        </li>
                        <?php $a = is_active(['/palmares/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/palmares/">
                                Les Palmares
                            </a>
                        </li>
                        <?php $a = is_active(['/journal_classe/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/journal_classe/">
                                Journal de classe
                            </a>
                        </li>
                        <?php $a = is_active(['/cours_chapitre_lecon_resume/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/cours_chapitre_lecon_resume/">
                                Gest. cours
                            </a>
                        </li>
                        <?php $a = is_active(['/horaires/'], $uriPath); ?>
                        <li>
                            <a class="dropdown-item <?= $a ?>" href="<?= $BASE ?>/horaires/">
                                Horaires
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <div class="text-end me-2">
                    <div class="fw-semibold small">
                        <?= e($_SESSION['user']['username'] ?? '') ?>
                    </div>
                    <div class="text-muted small">
                        <?= e($_SESSION['user']['role'] ?? '') ?>
                    </div>
                </div>
                <a class="btn btn-outline-danger btn-sm" href="<?= $BASE ?>/logout.php">Déconnexion</a>
            </div>
        </div>
    </div>
</nav>
