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
        $result = Auth::login($username, $password);
        if ($result['success']) {
            header('Location: /index.php');
            exit;
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
    <title>Connexion - CloudDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/modern.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-card card-modern">
        <div class="card-body-modern">
            <div class="auth-header">
                <span class="auth-logo"><i class="bi bi-cloud-fill"></i></span>
                <h1>CloudDrop</h1>
                <p>Connectez-vous pour accéder à vos fichiers</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-modern alert-danger-modern">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-modern alert-success-modern">
                    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <?= Auth::csrfField() ?>

                <div class="form-group-modern">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" class="form-control-modern"
                           placeholder="Votre pseudo" required autofocus
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="form-group-modern">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" class="form-control-modern"
                           placeholder="Votre mot de passe" required>
                </div>

                <button type="submit" class="btn-modern btn-primary-modern btn-lg w-100" style="justify-content:center;">
                    <i class="bi bi-box-arrow-in-right"></i> Se connecter
                </button>
            </form>

            <p style="text-align:center; margin-top:1.25rem; font-size:0.9rem; color:var(--text-muted);">
                Pas encore de compte ?
                <a href="/auth/register.php" style="color:var(--primary); text-decoration:none; font-weight:500;">Créer un compte</a>
            </p>
        </div>
    </div>
</div>

<script src="/js/theme.js"></script>
</body>
</html>

