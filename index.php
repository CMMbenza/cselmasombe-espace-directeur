<?php
// index.php — Connexion
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/config/app.php';
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/helpers.php';

/* Anti-cache sur l'écran de login */
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

        if (!$rows) {
            $error = "Identifiants incorrects.";
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

                // Redirection selon le rôle connecté
                $userRole = norm_role($matched['role']);
                // if ($userRole !== 'directeur') {
                //     redirect('dashboard-direction.php');
                // } else {
                    redirect('dashboard.php');
                // }
            } else {
                $error = "Identifiants incorrects.";
            }
        }
    }
}

require_once __DIR__.'/layout/header.php';
?>

<style>
/* Structure globale avec fond totalement blanc */
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    background-color: #ffffff;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.main-wrapper {
    flex: 1 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1rem;
}

.login-card {
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    width: 100%;
}

.login-header {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff;
    padding: 2.5rem 2rem 2rem 2rem;
    text-align: center;
}

.login-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-bottom: 1rem;
}

.form-control {
    border-radius: 12px;
    padding: 0.8rem 1.2rem;
    border: 1.5px solid #e2e8f0;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

.form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
}

.btn-primary {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border: none;
    border-radius: 12px;
    padding: 0.85rem;
    font-weight: 600;
    font-size: 1rem;
    letter-spacing: 0.3px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
}

.form-check-input:checked {
    background-color: #2563eb;
    border-color: #2563eb;
}

.alert-danger {
    border-radius: 12px;
    border: none;
    background-color: #fef2f2;
    color: #991b1b;
    font-size: 0.9rem;
}
</style>

<div class="main-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-7 col-lg-5">
                <div class="card login-card">
                    <div class="login-header">
                        <div class="login-icon">
                            🎓
                        </div>
                        <h1 class="h4 fw-bold mb-1">Espace de Connexion</h1>
                        <p class="mb-0 text-white-50 small">Veuillez vous authentifier pour continuer</p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if ($error): ?>
                        <div class="alert alert-danger p-3 mb-4 d-flex align-items-center">
                            <span class="me-2">⚠️</span>
                            <div><?= e($error) ?></div>
                        </div>
                        <?php endif; ?>

                        <form method="post" autocomplete="off">
                            <div class="mb-3 d-none">
                                <label class="form-label fw-semibold text-secondary">Nom d'utilisateur</label>
                                <input type="text" name="username" id="username" class="form-control" required value="cs elma">
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold text-secondary">Mot de passe</label>
                                <input type="password" name="password" id="password" class="form-control" required placeholder="Saisissez votre mot de passe">
                            </div>

                            <div class="form-check mb-4">
                                <input type="checkbox" class="form-check-input" id="showPassword">
                                <label class="form-check-label text-muted small" for="showPassword">Afficher le mot de passe</label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mb-2">Se connecter</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('showPassword').addEventListener('change', function() {
    const passField = document.getElementById('password');
    passField.type = this.checked ? 'text' : 'password';
});
</script>

<?php require_once __DIR__.'/layout/footer.php'; ?>