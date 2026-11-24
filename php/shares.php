<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();
require_once __DIR__ . '/../includes/FileManager.php';

$fm = new FileManager();
$userId = (int)$_SESSION['user_id'];
$shares = $fm->getUserShares($userId);
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$durations = [1 => '1 heure', 24 => '24 heures', 168 => '7 jours', 720 => '30 jours'];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes liens de partage - CloudDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/modern.css">
    <style>
        .share-link-row { min-width: 250px; }
        .share-link-row code { overflow-wrap:anywhere; }
        .share-actions { min-width: 250px; }
        .share-actions form { display:inline-flex; gap:.4rem; align-items:center; }
        @media (max-width: 640px) { .share-actions form { display:flex; margin-bottom:.4rem; } }
    </style>
</head>
<body>
<nav class="navbar-drive">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="/index.php"><i class="bi bi-cloud-fill"></i> CloudDrop</a>
        <div class="d-flex align-items-center gap-2">
            <a href="/php/dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="/php/upload.php" class="nav-link"><i class="bi bi-cloud-arrow-up"></i> Upload</a>
            <a href="/php/download.php" class="nav-link"><i class="bi bi-folder2-open"></i> Fichiers</a>
            <a href="/php/shares.php" class="nav-link active"><i class="bi bi-link-45deg"></i> Mes liens</a>
            <a href="/php/profile.php" class="nav-link"><i class="bi bi-person"></i> Profil</a>
            <?php if (Auth::isAdmin()): ?><a href="/admin/index.php" class="nav-link"><i class="bi bi-shield-lock"></i> Admin</a><?php endif; ?>
            <a href="/auth/logout.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
        </div>
    </div>
</nav>
<main class="container-fluid px-4 py-4">
    <div class="page-header fade-in">
        <h1><i class="bi bi-link-45deg"></i> Mes liens de partage</h1>
        <p>Consultez, prolongez ou révoquez les liens que vous avez créés.</p>
    </div>
    <?php if ($success): ?><div class="alert-modern alert-success-modern"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert-modern alert-danger-modern"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card-modern">
        <div class="card-body-modern p-0">
            <?php if (empty($shares)): ?>
                <div class="empty-state">
                    <i class="bi bi-link-45deg" style="font-size:3rem;color:var(--text-muted);display:block;margin-bottom:1rem;"></i>
                    <h3>Aucun lien de partage</h3>
                    <p>Créez un lien depuis la page Fichiers à l'aide du bouton de partage.</p>
                    <a href="/php/download.php" class="btn-modern btn-primary-modern"><i class="bi bi-folder2-open"></i> Mes fichiers</a>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table-modern">
                        <thead><tr><th>Fichier</th><th>Lien</th><th>Temps restant</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($shares as $share): ?>
                            <?php $fileDeleted = !empty($share['file_deleted_at']) || empty($share['original_name']); ?>
                            <tr>
                                <td>
                                    <div class="file-name">
                                        <span class="icon"><?= FileManager::getFileIcon($share['file_extension'] ?? '') ?></span>
                                        <span><?= htmlspecialchars($fileDeleted ? 'Fichier supprimé' : $share['original_name']) ?></span>
                                    </div>
                                    <?php if (!$fileDeleted): ?><small style="color:var(--text-muted);"><?= FileManager::formatBytes((int)$share['file_size']) ?></small><?php endif; ?>
                                </td>
                                <td class="share-link-row">
                                    <?php if (!$fileDeleted): ?>
                                        <a href="/php/share.php?token=<?= rawurlencode($share['token']) ?>" target="_blank" rel="noopener noreferrer">Ouvrir le lien</a>
                                        <button type="button" class="btn-modern btn-outline-modern btn-sm ms-1" data-token="<?= htmlspecialchars($share['token'], ENT_QUOTES, 'UTF-8') ?>" onclick="copyShareLink(this)" title="Copier le lien"><i class="bi bi-clipboard"></i></button>
                                    <?php else: ?><span style="color:var(--text-muted);">Lien indisponible</span><?php endif; ?>
                                </td>
                                <td>
                                    <span class="share-countdown" data-expires="<?= (int)strtotime($share['expires_at']) ?>">
                                        <?= strtotime($share['expires_at']) > time() ? 'Calcul…' : 'Expiré' ?>
                                    </span>
                                    <div><small style="color:var(--text-muted);">Échéance : <?= date('d/m/Y H:i', strtotime($share['expires_at'])) ?></small></div>
                                </td>
                                <td class="share-actions">
                                    <?php if (!$fileDeleted): ?>
                                    <form method="POST" action="/php/actions.php">
                                        <?= Auth::csrfField() ?>
                                        <input type="hidden" name="action" value="update_share_expiry">
                                        <input type="hidden" name="share_id" value="<?= (int)$share['id'] ?>">
                                        <select name="expiry_hours" class="form-control-modern" aria-label="Nouvelle durée">
                                            <?php foreach ($durations as $hours => $label): ?>
                                                <option value="<?= $hours ?>"><?= htmlspecialchars($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn-modern btn-outline-modern btn-sm" type="submit" title="Appliquer la durée">Prolonger</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" action="/php/actions.php" onsubmit="return confirm('Révoquer ce lien de partage ?');">
                                        <?= Auth::csrfField() ?>
                                        <input type="hidden" name="action" value="delete_share">
                                        <input type="hidden" name="share_id" value="<?= (int)$share['id'] ?>">
                                        <button class="btn-modern btn-ghost btn-sm" type="submit" title="Révoquer le lien"><i class="bi bi-trash3" style="color:var(--danger);"></i> Révoquer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="/js/theme.js"></script>
<script>
function copyShareLink(button) {
    const url = window.location.origin + '/php/share.php?token=' + encodeURIComponent(button.dataset.token);
    navigator.clipboard.writeText(url).then(() => {
        button.innerHTML = '<i class="bi bi-check-lg"></i>';
        setTimeout(() => button.innerHTML = '<i class="bi bi-clipboard"></i>', 1500);
    }).catch(() => window.prompt('Copiez ce lien :', url));
}
function updateShareCountdowns() {
    document.querySelectorAll('.share-countdown').forEach((element) => {
        const seconds = Number(element.dataset.expires);
        let remaining = seconds - Math.floor(Date.now() / 1000);
        if (!Number.isFinite(remaining) || remaining <= 0) {
            element.textContent = 'Expiré';
            return;
        }
        const days = Math.floor(remaining / 86400);
        remaining %= 86400;
        const hours = Math.floor(remaining / 3600);
        remaining %= 3600;
        const minutes = Math.floor(remaining / 60);
        const secs = remaining % 60;
        element.textContent = (days ? days + ' j ' : '') + hours + ' h ' + minutes + ' min ' + secs + ' s';
    });
}
updateShareCountdowns();
setInterval(updateShareCountdowns, 1000);
</script>
</body>
</html>
