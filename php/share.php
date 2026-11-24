<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/FileManager.php';

$fm = new FileManager();
$token = (string)($_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) render_error_page(404);

$share = $fm->getShareByToken($token);
$expired = !$share;
$unlockError = '';
if (!$share) $share = $fm->getShareByTokenForDisplay($token);

if (!$expired && !empty($share['password_hash']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') !== 'unlock_share'
        || !Auth::validateCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        render_error_page(403);
    }
    $ipAddress = clouddrop_client_ip();
    if ($fm->isSharePasswordRateLimited((int)$share['id'], $ipAddress)) {
        $unlockError = 'Trop de tentatives. Réessayez dans 15 minutes.';
    } elseif (!is_string($_POST['share_password'] ?? null)
        || !password_verify($_POST['share_password'], $share['password_hash'])) {
        $fm->recordFailedSharePasswordAttempt((int)$share['id'], $ipAddress);
        $unlockError = 'Mot de passe incorrect.';
    } else {
        $fm->clearSharePasswordAttempts((int)$share['id'], $ipAddress);
        if (!isset($_SESSION['share_link_access'])) $_SESSION['share_link_access'] = [];
        $_SESSION['share_link_access'][(int)$share['id']] = time() + 900;
        header('Location: /php/share.php?token=' . rawurlencode($token));
        exit;
    }
}

$passwordProtected = !$expired && !empty($share['password_hash']);
$shareUnlocked = !$passwordProtected
    || (int)($_SESSION['share_link_access'][(int)$share['id']] ?? 0) > time();
$canPreviewExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'mp4', 'webm', 'ogg', 'mp3', 'wav', 'pdf', 'txt', 'json', 'xml', 'csv', 'md', 'html', 'css', 'js'];
$canPreviewShare = !$expired && in_array(strtolower((string)$share['file_extension']), $canPreviewExtensions, true);
$downloadLimitReached = !$expired && $share['download_limit'] !== null
    && (int)$share['download_count'] >= (int)$share['download_limit'];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partage - CloudDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/modern.css">
</head>
<body>
<div class="share-page">
    <div class="share-card card-modern">
        <div class="card-body-modern">
            <div class="share-header">
                <span class="share-logo"><i class="bi bi-cloud-fill"></i></span>
                <h1>CloudDrop</h1>
            </div>

            <?php if ($expired || !$share): ?>
                <div class="share-expired">
                    <i class="bi bi-clock-history" style="font-size:3rem;color:var(--warning);"></i>
                    <h2>Lien expiré ou invalide</h2>
                    <p>Ce lien n'est plus disponible. Il a peut-être expiré ou le fichier a été supprimé.</p>
                </div>
            <?php elseif ($passwordProtected && !$shareUnlocked): ?>
                <div class="share-file-info">
                    <div class="share-file-icon"><i class="bi bi-lock-fill"></i></div>
                    <h2 class="share-filename">Lien protégé</h2>
                    <p>Entrez le mot de passe communiqué par la personne qui a partagé ce fichier.</p>
                </div>
                <?php if ($unlockError): ?><div class="alert-modern alert-danger-modern"><?= htmlspecialchars($unlockError) ?></div><?php endif; ?>
                <form method="POST" action="/php/share.php?token=<?= rawurlencode($token) ?>">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="unlock_share">
                    <div class="form-group-modern mb-3">
                        <label for="sharePassword">Mot de passe</label>
                        <input type="password" id="sharePassword" name="share_password" class="form-control-modern" required maxlength="128" autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn-modern btn-primary-modern btn-lg w-100">Accéder au partage</button>
                </form>
            <?php else: ?>
                <div class="share-file-info">
                    <div class="share-file-icon"><?= FileManager::getFileIcon($share['file_extension']) ?></div>
                    <h2 class="share-filename"><?= htmlspecialchars($share['original_name']) ?></h2>
                    <div class="share-file-meta">
                        <span><i class="bi bi-hdd-fill"></i> <?= FileManager::formatBytes($share['file_size']) ?></span>
                        <span><i class="bi bi-person-fill"></i> Partagé par <strong><?= htmlspecialchars($share['owner_name']) ?></strong></span>
                    </div>
                </div>

                <div class="share-expiry-badge">
                    <i class="bi bi-hourglass-split"></i>
                    <span>Expire le <?= date('d/m/Y à H:i', strtotime($share['expires_at'])) ?></span>
                    <span class="countdown" data-expires="<?= htmlspecialchars($share['expires_at'], ENT_QUOTES, 'UTF-8') ?>"></span>
                </div>

                <?php if ($downloadLimitReached): ?>
                    <div class="alert-modern alert-warning-modern">La limite de téléchargements est atteinte.</div>
                <?php else: ?>
                    <a href="/php/actions.php?share=<?= rawurlencode($token) ?>" class="btn-modern btn-primary-modern btn-lg w-100 share-download-btn">
                        <i class="bi bi-download"></i> Télécharger le fichier
                    </a>
                <?php endif; ?>

                <?php if (!empty($share['download_limit'])): ?>
                    <p style="text-align:center;color:var(--text-muted);font-size:.85rem;margin-top:1rem;">
                        Téléchargements restants : <?= max(0, (int)$share['download_limit'] - (int)$share['download_count']) ?> / <?= (int)$share['download_limit'] ?>
                    </p>
                <?php endif; ?>

                <?php if ($canPreviewShare): ?>
                    <details class="mt-3">
                        <summary style="cursor:pointer;font-weight:600;">Afficher l’aperçu</summary>
                        <iframe title="Aperçu du fichier partagé" src="/php/share_preview.php?token=<?= rawurlencode($token) ?>"
                            sandbox="allow-same-origin" referrerpolicy="no-referrer"
                            style="width:100%;height:65vh;border:0;border-radius:12px;margin-top:1rem;background:#fff;"></iframe>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
                <?php if (!empty($share['download_limit'])): ?>
                    <p style="text-align:center;color:var(--text-muted);font-size:.85rem;margin-top:1rem;">
                        Téléchargements restants : <?= max(0, (int)$share['download_limit'] - (int)$share['download_count']) ?> / <?= (int)$share['download_limit'] ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>

            <div class="share-footer">
                <p>Propulsé par <a href="/" style="color:var(--primary);text-decoration:none;font-weight:500;">CloudDrop</a></p>
            </div>
        </div>
    </div>
</div>

<script src="/js/theme.js"></script>
<script>
document.querySelectorAll('.countdown').forEach(function(el) {
    const expires = new Date(el.dataset.expires.replace(' ', 'T')).getTime();
    function update() {
        const now = Date.now();
        const diff = expires - now;
        if (diff <= 0) {
            el.textContent = 'Expiré';
            el.classList.add('expired');
            return;
        }
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        let text = 'Reste ';
        if (days > 0) text += days + 'j ';
        text += hours + 'h ' + minutes + 'm ' + seconds + 's';
        el.textContent = text;
        if (diff < 3600000) el.classList.add('urgent');
    }
    update();
    setInterval(update, 1000);
});
</script>
<?php require __DIR__ . '/../includes/SiteFooter.php'; ?>
</body>
</html>
