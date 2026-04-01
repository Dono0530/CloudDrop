<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';

if (Auth::isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Jeton de sécurité invalide. Rechargez la page.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $result = Auth::register($username, $password, $confirm);

        if ($result['success']) {
            $success = $result['message'] . ' <a href="/auth/login.php" style="color:var(--primary);font-weight:500;">Connectez-vous</a>';
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - CloudDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/modern.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card card-modern">
        <div class="card-body-modern">
            <div class="auth-header">
                <span class="auth-logo"><i class="bi bi-rocket-takeoff"></i></span>
                <h1>Créer un compte</h1>
                <p>Rejoignez le CloudDrop</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-modern alert-danger-modern">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-modern alert-success-modern">
                    <i class="bi bi-check-circle-fill"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?= Auth::csrfField() ?>

                <div class="form-group-modern">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" class="form-control-modern"
                           placeholder="3-30 caractères (lettres, chiffres, - _)" required
                           pattern="[a-zA-Z0-9_-]{3,30}"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group-modern">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control-modern"
                           placeholder="Minimum 8 caractères" required minlength="8"
                           oninput="checkPasswordStrength(this.value)">
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <small id="strengthText" style="font-size:0.75rem;color:var(--text-muted);"></small>
                </div>

                <div class="form-group-modern">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control-modern"
                           placeholder="Retapez votre mot de passe" required minlength="8">
                </div>

                <button type="submit" class="btn-modern btn-primary-modern btn-lg w-100" style="justify-content:center;">
                    <i class="bi bi-person-plus"></i> Créer mon compte
                </button>
            </form>

            <p style="text-align:center; margin-top:1.25rem; font-size:0.9rem; color:var(--text-muted);">
                Déjà un compte ?
                <a href="/auth/login.php" style="color:var(--primary); text-decoration:none; font-weight:500;">Se connecter</a>
            </p>
        </div>
    </div>
</div>

<script src="/js/theme.js"></script>
<script>
function checkPasswordStrength(password) {
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password) && /[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    bar.className = 'strength-bar';
    const levels = [
        { cls: '', label: '' },
        { cls: 'strength-weak', label: 'Faible' },
        { cls: 'strength-fair', label: 'Moyen' },
        { cls: 'strength-good', label: 'Bon' },
        { cls: 'strength-good', label: 'Bon' },
        { cls: 'strength-strong', label: 'Fort' },
    ];
    const level = levels[Math.min(score, 5)];
    if (level.cls) bar.classList.add(level.cls);
    text.textContent = level.label ? 'Force : ' + level.label : '';
}
</script>
</body>
</html>

