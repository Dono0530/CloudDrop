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
    } elseif (defined('TURNSTILE_ENABLED') && TURNSTILE_ENABLED
        && !Auth::verifyTurnstile($_POST['cf-turnstile-response'] ?? '')) {
        $error = 'Vérification anti-robot échouée. Veuillez réessayer.';
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
    <link rel="stylesheet" href="/css/modern.css?v=20261125-1">
</head>
<body>
<?php require __DIR__ . '/../includes/SiteHeader.php'; ?>
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
                           placeholder="Minimum 12 caractères" required minlength="12"
                           oninput="checkPasswordStrength(this.value)">
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>
                    </div>
                    <small id="strengthText" aria-live="polite" style="font-size:0.75rem;color:var(--text-muted);"></small>
                </div>

                <div class="form-group-modern">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control-modern"
                           placeholder="Retapez votre mot de passe" required minlength="8">
                </div>

                <?php if (defined('TURNSTILE_ENABLED') && TURNSTILE_ENABLED): ?>
                <div class="turnstile-container" style="margin:1rem 0;display:flex;justify-content:center;">
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                    <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars(TURNSTILE_SITE_KEY, ENT_QUOTES, 'UTF-8') ?>"></div>
                </div>
                <?php endif; ?>

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

    const levels = [
        { label: '', width: 0, color: '' },
        { label: 'Faible', width: 25, color: '--danger' },
        { label: 'Moyen', width: 50, color: '--warning' },
        { label: 'Bon', width: 75, color: '--accent' },
        { label: 'Bon', width: 75, color: '--accent' },
        { label: 'Fort', width: 100, color: '--success' },
    ];
    const level = levels[Math.min(score, levels.length - 1)];
    const color = level.color ? getComputedStyle(document.documentElement).getPropertyValue(level.color).trim() : 'transparent';

    bar.className = 'strength-bar';
    bar.style.width = level.width + '%';
    bar.style.backgroundColor = color;
    bar.setAttribute('aria-valuenow', String(level.width));
    text.textContent = level.label ? 'Force : ' + level.label : '';
    text.style.color = level.color ? color : 'var(--text-muted)';
}
</script>
<?php require __DIR__ . '/../includes/SiteFooter.php'; ?>
</body>
</html>

