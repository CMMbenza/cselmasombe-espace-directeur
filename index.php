<?php
// index.php — Login Directeur
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/config/app.php';
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/helpers.php';

/* Anti-cache sur l'écran de login aussi (évite les artefacts back/forward) */
send_nocache_headers();

/** Détecte si une chaîne ressemble à un hash password_* */
function looks_hashed(?string $hash): bool {
    if (!$hash) return false;
    $info = password_get_info($hash);
    return !empty($info['algo']);
}

/** Normalise un rôle (trim + minuscules) */
function norm_role(?string $r): string {
    return mb_strtolower(trim((string)$r), 'UTF-8');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Veuillez renseigner l'identifiant et le mot de passe.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = :u");
        $stmt->execute([':u' => $username]);
        $rows = $stmt->fetchAll();

        $rows = array_values(array_filter($rows, fn($r) => norm_role($r['role']) === REQUIRED_ROLE_NORM));

        if (!$rows) {
            $error = "Accès refusé : ce module est réservé au Directeur.";
        } else {
            $matched = null;

            foreach ($rows as $row) {
                $stored = (string)$row['password'];

                if (looks_hashed($stored)) {
                    if (password_verify($password, $stored)) {
                        $matched = $row;
                        if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $upd = $pdo->prepare("UPDATE users SET password = :p, dateModification = CURDATE() WHERE id = :id");
                            $upd->execute([':p' => $newHash, ':id' => $row['id']]);
                        }
                        break;
                    }
                } else {
                    if (hash_equals($stored, $password)) {
                        $matched = $row;
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare("UPDATE users SET password = :p, dateModification = CURDATE() WHERE id = :id");
                        $upd->execute([':p' => $newHash, ':id' => $row['id']]);
                        break;
                    }
                }
            }

            if ($matched) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'       => (int)$matched['id'],
                    'username' => (string)$matched['username'],
                    'role'     => (string)$matched['role'],
                ];
                redirect('dashboard.php');
            } else {
                $error = "Identifiants incorrects.";
            }
        }
    }
}

require_once __DIR__.'/layout/header.php';
?>

<style>
body {
    background: #f5f7fa;
    min-height: 100vh;
    align-items: center;
    justify-content: center;
}

.card {
    border-radius: 15px;
    border: none;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.card-body h1 {
    font-weight: 600;
    color: #333;
}

.form-control {
    border-radius: 10px;
    padding: 0.75rem 1rem;
    transition: all 0.3s;
}

.form-control:focus {
    border-color: #4e73df;
    box-shadow: 0 0 8px rgba(78, 115, 223, 0.3);
}

.btn-primary {
    /* background: linear-gradient(90deg, #4e73df 0%, #1cc88a 100%); */
    border: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-primary:hover {
    /* background: linear-gradient(90deg, #1cc88a 0%, #4e73df 100%); */
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.alert-danger {
    border-radius: 10px;
    font-size: 0.9rem;
}

.small-text-muted {
    color: #6c757d;
    font-size: 0.85rem;
}

.show-pass-container {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.show-pass-container input[type="checkbox"] {
    margin-right: 0.5rem;
}
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-6 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h1 class="h4 mb-3 text-center">Connexion (Directeur)</h1>
                    <p class="text-center text-muted small">Veuillez entrer votre mot de passe pour accéder à votre
                        espace Directeur.</p>
                    <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post" autocomplete="off">
                        <div class="mb-3 d-none">
                            <label class="form-label">Nom d'utilisateur</label>
                            <input type="text" name="username" id="username" class="form-control" required
                                placeholder="Entrez votre nom d'utilisateur" value="cs elma">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mot de passe</label>
                            <input type="password" name="password" id="password" class="form-control" required
                                placeholder="Entrez votre mot de passe">
                        </div>

                        <!-- Checkbox show/hide -->
                        <div class="show-pass-container">
                            <input type="checkbox" id="showPassword">
                            <label for="showPassword" class="mb-0">Afficher le mot de passe</label>
                        </div>

                        <button class="btn btn-primary w-100">Se connecter</button>
                        <!-- <p class="text-center text-muted small mt-2">Assurez-vous de garder votre mot de passe
                            confidentiel.</p> -->
                    </form>
                </div>
            </div>
            <!-- <p class="text-center small-text-muted mt-3">Accès réservé au rôle “Directeur”.</p> -->
        </div>
    </div>
</div>

<script>
document.getElementById('showPassword').addEventListener('change', function() {
    const passField = document.getElementById('password');
    if (this.checked) {
        passField.type = 'text';
    } else {
        passField.type = 'password';
    }
});
</script>

<?php require_once __DIR__.'/layout/footer.php'; ?>