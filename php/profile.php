<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/FileManager.php';

$fm = new FileManager();
$user = Auth::getUser();
$userStats = $fm->getUserStats($_SESSION['user_id']);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Jeton de sécurité invalide.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_profile') {
            $newPseudo = trim($_POST['new_pseudo'] ?? '');
            if (strlen($newPseudo) < 3 || strlen($newPseudo) > 30) {
                $error = 'Le pseudo doit faire entre 3 et 30 caractères.';
            } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $newPseudo)) {
                $error = 'Caractères autorisés : lettres, chiffres, tirets et underscores.';
            } else {
                $db = Database::getInstance();
                $exists = $db->fetchOne("SELECT id FROM users WHERE pseudo = ? AND id != ?", [$newPseudo, $_SESSION['user_id']]);
                if ($exists) { $error = 'Ce pseudo est déjà pris.'; }
                else {
                    $db->execute("UPDATE users SET pseudo = ? WHERE id = ?", [$newPseudo, $_SESSION['user_id']]);
                    $_SESSION['username'] = $newPseudo;
                    $success = 'Profil mis à jour.'; $user = Auth::getUser();
                }
            }
        }
        if ($action === 'change_password') {
            $currentPw = $_POST['current_password'] ?? '';
            $newPw = $_POST['new_password'] ?? '';
            $confirmPw = $_POST['confirm_new_password'] ?? '';
            $db = Database::getInstance();
            $row = $db->fetchOne("SELECT mdp FROM users WHERE id = ?", [$_SESSION['user_id']]);
            if (!password_verify($currentPw, $row['mdp'])) { $error = 'Mot de passe actuel incorrect.'; }
            elseif (strlen($newPw) < 8) { $error = 'Le nouveau mot de passe doit faire au moins 8 caractères.'; }
            elseif ($newPw !== $confirmPw) { $error = 'Les nouveaux mots de passe ne correspondent pas.'; }
            else {
                $hashed = password_hash($newPw, PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]);
                $db->execute("UPDATE users SET mdp = ? WHERE id = ?", [$hashed, $_SESSION['user_id']]);
                $success = 'Mot de passe modifié avec succès.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - CloudDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/modern.css">
</head>
<body>

<nav class="navbar-drive">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="/index.php"><i class="bi bi-cloud-fill"></i> CloudDrop</a>
        <div class="d-flex align-items-center gap-2">
            <a href="/php/dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="/php/upload.php" class="nav-link"><i class="bi bi-cloud-arrow-up"></i> Upload</a>
            <a href="/php/download.php" class="nav-link"><i class="bi bi-folder2-open"></i> Fichiers</a>
            <a href="/php/profile.php" class="nav-link active"><i class="bi bi-person"></i> Profil</a>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <a href="/admin/index.php" class="nav-link"><i class="bi bi-shield-lock"></i> Admin</a>
            <?php endif; ?>
            <a href="/auth/logout.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            <button class="theme-toggle" onclick="toggleTheme()"></button>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-4" style="max-width:800px;">
    <div class="page-header fade-in">
        <h1><i class="bi bi-person-circle"></i> Mon profil</h1>
        <p>Gérez votre compte et vos paramètres</p>
    </div>

    <?php if ($success): ?>
        <div class="alert-modern alert-success-modern fade-in"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-modern alert-danger-modern fade-in"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4 fade-in fade-in-delay-1">
        <div class="col-md-6">
            <div class="card-modern">
                <div class="card-header-modern"><i class="bi bi-bar-chart"></i> Statistiques</div>
                <div class="card-body-modern">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="avatar avatar-lg"><?= strtoupper(substr($user['pseudo'], 0, 1)) ?></div>
                        <div>
                            <div style="font-size:1.1rem;font-weight:600;"><?= htmlspecialchars($user['pseudo']) ?></div>
                            <span class="badge-modern badge-primary"><?= htmlspecialchars($user['role']) ?></span>
                        </div>
                    </div>
                    <div style="font-size:0.9rem;color:var(--text-muted);">
                        <div class="mb-2"><i class="bi bi-file-earmark"></i> <strong><?= $userStats['total_uploads'] ?></strong> fichiers uploadés</div>
                        <div class="mb-2"><i class="bi bi-hdd"></i> <strong><?= FileManager::formatBytes($userStats['total_size']) ?></strong> utilisés</div>
                        <?php if ($user['created_at']): ?>
                        <div class="mb-2"><i class="bi bi-calendar3"></i> Membre depuis le <strong><?= date('d/m/Y', strtotime($user['created_at'])) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($userStats['last_upload']): ?>
                        <div><i class="bi bi-clock"></i> Dernier upload : <strong><?= date('d/m/Y H:i', strtotime($userStats['last_upload'])) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-modern">
                <div class="card-header-modern"><i class="bi bi-pie-chart"></i> Par type de fichier</div>
                <div class="card-body-modern">
                    <?php if (empty($userStats['by_extension'])): ?>
                        <p style="color:var(--text-muted);font-size:0.9rem;">Aucun fichier uploadé.</p>
                    <?php else: ?>
                        <?php foreach ($userStats['by_extension'] as $ext): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span style="font-size:0.9rem;"><?= FileManager::getFileIcon($ext['file_extension']) ?> .<?= htmlspecialchars($ext['file_extension']) ?></span>
                            <span class="badge-modern badge-primary"><?= $ext['count'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-modern mb-4 fade-in fade-in-delay-2">
        <div class="card-header-modern"><i class="bi bi-pencil-square"></i> Modifier le pseudo</div>
        <div class="card-body-modern">
            <form method="POST">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label" style="font-size:0.9rem;">Nouveau pseudo</label>
                        <input type="text" name="new_pseudo" class="form-control-modern" value="<?= htmlspecialchars($user['pseudo']) ?>" required minlength="3" maxlength="30" pattern="[a-zA-Z0-9_-]+">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn-modern btn-primary-modern w-100"><i class="bi bi-check-lg"></i> Enregistrer</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card-modern fade-in fade-in-delay-3">
        <div class="card-header-modern"><i class="bi bi-shield-lock"></i> Changer le mot de passe</div>
        <div class="card-body-modern">
            <form method="POST">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="form-group-modern">
                    <label>Mot de passe actuel</label>
                    <input type="password" name="current_password" class="form-control-modern" required>
                </div>
                <div class="form-group-modern">
                    <label>Nouveau mot de passe (min. 8 caractères)</label>
                    <input type="password" name="new_password" class="form-control-modern" required minlength="8">
                </div>
                <div class="form-group-modern">
                    <label>Confirmer le nouveau mot de passe</label>
                    <input type="password" name="confirm_new_password" class="form-control-modern" required minlength="8">
                </div>
                <button type="submit" class="btn-modern btn-primary-modern"><i class="bi bi-key"></i> Modifier le mot de passe</button>
            </form>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/theme.js"></script>
</body>
</html>

