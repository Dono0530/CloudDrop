<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/FileManager.php';

$fm = new FileManager();

$token = $_GET['token'] ?? '';
if (!$token) { http_response_code(404); die('Lien invalide'); }

$share = $fm->getShareByToken($token);
if (!$share) {
    $expired = true;
    $share = $fm->getShareByTokenForDisplay($token);
} else {
    $expired = false;
}
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
                    <p>Ce lien de partage n'est plus disponible. Il a peut-être expiré ou été supprimé.</p>
                </div>
            <?php else: ?>
                <div class="share-file-info">
                    <div class="share-file-icon">
                        <?= FileManager::getFileIcon($share['file_extension']) ?>
                    </div>
                    <h2 class="share-filename"><?= htmlspecialchars($share['original_name']) ?></h2>
                    <div class="share-file-meta">
                        <span><i class="bi bi-hdd-fill"></i> <?= FileManager::formatBytes($share['file_size']) ?></span>
                        <span><i class="bi bi-person-fill"></i> Partagé par <strong><?= htmlspecialchars($share['owner_name']) ?></strong></span>
                    </div>
                </div>

                <div class="share-expiry-badge">
                    <i class="bi bi-hourglass-split"></i>
                    <span>Expire le <?= date('d/m/Y à H:i', strtotime($share['expires_at'])) ?></span>
                    <span class="countdown" data-expires="<?= $share['expires_at'] ?>"></span>
                </div>

                <a href="/php/actions.php?share=<?= htmlspecialchars($token) ?>" class="btn-modern btn-primary-modern btn-lg w-100 share-download-btn">
                    <i class="bi bi-download"></i> Télécharger le fichier
                </a>
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
        requestAnimationFrame(update);
    }
    update();
    setInterval(update, 1000);
});
</script>
</body>
</html>
