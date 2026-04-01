<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/FileManager.php';

$fm = new FileManager();
$userId = $_SESSION['user_id'];
$userStats = $fm->getUserStats($userId);
$myFiles = $fm->getFiles(['user_id' => $userId, 'limit' => 20]);
$globalStats = $fm->getGlobalStats();

$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date_desc';
if ($search) {
    $myFiles = $fm->getFiles(['user_id' => $userId, 'search' => $search, 'sort' => $sort, 'limit' => 50]);
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CloudDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/modern.css">
</head>
<body>

<nav class="navbar-drive">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="/index.php"><i class="bi bi-cloud-fill"></i> CloudDrop</a>
        <div class="d-flex align-items-center gap-2">
            <a href="/index.php" class="nav-link"><i class="bi bi-house"></i> Accueil</a>
            <a href="/php/upload.php" class="nav-link"><i class="bi bi-cloud-arrow-up"></i> Upload</a>
            <a href="/php/download.php" class="nav-link"><i class="bi bi-folder2-open"></i> Fichiers</a>
            <a href="/php/profile.php" class="nav-link"><i class="bi bi-person"></i> Profil</a>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <a href="/admin/index.php" class="nav-link"><i class="bi bi-shield-lock"></i> Admin</a>
            <?php endif; ?>
            <a href="/auth/logout.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-right"></i></a>
            <button class="theme-toggle" onclick="toggleTheme()"></button>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-4">
    <div class="page-header fade-in">
        <h1>Bonjour, <?= htmlspecialchars($_SESSION['username']) ?></h1>
        <p>Voici un aperçu de votre activité</p>
    </div>

    <div class="row g-4 mb-4 fade-in fade-in-delay-1">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-primary">
                <div class="stat-icon"><i class="bi bi-file-earmark-fill"></i></div>
                <div class="stat-value"><?= $userStats['total_uploads'] ?></div>
                <div class="stat-label">Mes fichiers</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-accent">
                <div class="stat-icon"><i class="bi bi-hdd-fill"></i></div>
                <div class="stat-value"><?= FileManager::formatBytes($userStats['total_size']) ?></div>
                <div class="stat-label">Mon espace</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-success">
                <div class="stat-icon"><i class="bi bi-globe"></i></div>
                <div class="stat-value"><?= $globalStats['total_files'] ?></div>
                <div class="stat-label">Total fichiers</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-warning">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-value"><?= $globalStats['total_users'] ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
        </div>
    </div>

    <div class="row g-4 fade-in fade-in-delay-2">
        <div class="col-lg-8">
            <div class="card-modern">
                <div class="card-header-modern">
                    <i class="bi bi-folder2"></i> Mes fichiers
                    <div class="ms-auto d-flex gap-2">
                        <form method="GET" class="d-flex gap-2">
                            <div class="search-bar">
                                <span class="search-icon"><i class="bi bi-search"></i></span>
                                <input type="text" name="q" class="form-control-modern" style="padding-left:2.5rem;width:200px;"
                                       placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </form>
                        <a href="/php/upload.php" class="btn-modern btn-primary-modern btn-sm"><i class="bi bi-plus-lg"></i> Upload</a>
                    </div>
                </div>
                <div class="card-body-modern p-0">
                    <?php if (empty($myFiles)): ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox empty-icon" style="font-size:3.5rem;color:var(--text-muted);"></i>
                            <h3>Aucun fichier</h3>
                            <p>Vous n'avez pas encore uploadé de fichiers.</p>
                            <a href="/php/upload.php" class="btn-modern btn-primary-modern mt-3"><i class="bi bi-cloud-arrow-up"></i> Uploader maintenant</a>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="table-modern">
                                <thead>
                                    <tr><th>Fichier</th><th>Taille</th><th>Date</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($myFiles as $file): ?>
                                    <tr>
                                        <td>
                                            <div class="file-name">
                                                <span class="icon"><?= FileManager::getFileIcon($file['file_extension']) ?></span>
                                                <?= htmlspecialchars($file['original_name']) ?>
                                            </div>
                                        </td>
                                        <td><?= FileManager::formatBytes($file['file_size']) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($file['uploaded_at'])) ?></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <?php if ($fm->isPreviewable($file['file_extension'])): ?>
                                                <a href="/php/preview.php?id=<?= $file['id'] ?>" class="btn-modern btn-outline-modern btn-sm" title="Aperçu"><i class="bi bi-eye"></i></a>
                                                <?php endif; ?>
                                                <a href="/php/download_file.php?id=<?= $file['id'] ?>" class="btn-modern btn-outline-modern btn-sm" title="Télécharger"><i class="bi bi-download"></i></a>
                                                <button class="btn-modern btn-outline-modern btn-sm" title="Partager" onclick="shareFile(<?= $file['id'] ?>, '<?= htmlspecialchars(addslashes($file['original_name'])) ?>')"><i class="bi bi-share"></i></button>
                                                <form method="POST" action="/php/actions.php" onsubmit="return confirm('Supprimer ce fichier ?');" style="display:inline;">
                                                    <?= Auth::csrfField() ?>
                                                    <input type="hidden" name="action" value="delete_file">
                                                    <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                                    <button type="submit" class="btn-modern btn-ghost btn-sm" title="Supprimer"><i class="bi bi-trash3" style="color:var(--danger);"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card-modern mb-4">
                <div class="card-header-modern"><i class="bi bi-trophy"></i> Top contributeurs</div>
                <div class="card-body-modern">
                    <?php if (empty($globalStats['top_uploaders'])): ?>
                        <p style="color:var(--text-muted);font-size:0.9rem;">Aucun upload pour le moment.</p>
                    <?php else: ?>
                        <?php foreach ($globalStats['top_uploaders'] as $i => $uploader): ?>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="avatar"><?= strtoupper(substr($uploader['pseudo'], 0, 1)) ?></div>
                            <div class="flex-grow-1">
                                <div style="font-weight:500;font-size:0.9rem;"><?= htmlspecialchars($uploader['pseudo']) ?></div>
                                <div style="font-size:0.8rem;color:var(--text-muted);">
                                    <?= $uploader['file_count'] ?> fichier(s) · <?= FileManager::formatBytes($uploader['total_size']) ?>
                                </div>
                            </div>
                            <span class="badge-modern badge-primary">#<?= $i + 1 ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-modern">
                <div class="card-header-modern"><i class="bi bi-lightning-charge"></i> Activité récente</div>
                <div class="card-body-modern">
                    <?php $activity = $fm->getRecentActivity(8); if (empty($activity)): ?>
                        <p style="color:var(--text-muted);font-size:0.9rem;">Aucune activité.</p>
                    <?php else: ?>
                        <?php foreach ($activity as $act): ?>
                        <div class="activity-item">
                            <div class="activity-dot <?= $act['action'] ?>"></div>
                            <div style="flex:1;">
                                <div style="font-size:0.85rem;">
                                    <strong><?= htmlspecialchars($act['pseudo'] ?? 'Inconnu') ?></strong>
                                    <?php $labels = ['upload' => 'a uploadé', 'delete' => 'a supprimé', 'login' => 's\'est connecté', 'logout' => 's\'est déconnecté']; echo $labels[$act['action']] ?? $act['action']; ?>
                                </div>
                                <?php if ($act['details']): ?>
                                    <div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($act['details']) ?></div>
                                <?php endif; ?>
                                <div style="font-size:0.75rem;color:var(--text-muted);"><?= date('d/m H:i', strtotime($act['created_at'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Partage -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog modal-modern">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-share"></i> Partager le fichier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="shareFileName" style="font-weight:500;"></p>
                <div class="form-group-modern">
                    <label>Durée de validité</label>
                    <select id="shareExpiry" class="form-control-modern">
                        <option value="1">1 heure</option>
                        <option value="24" selected>24 heures</option>
                        <option value="168">7 jours</option>
                        <option value="720">30 jours</option>
                    </select>
                </div>
                <div id="shareResult" style="display:none;">
                    <label style="font-size:0.85rem;font-weight:500;margin-bottom:0.3rem;display:block;">Lien de partage</label>
                    <div class="input-group">
                        <input type="text" id="shareLink" class="form-control-modern" readonly style="font-size:0.8rem;">
                        <button class="btn-modern btn-primary-modern btn-sm" onclick="copyShareLink()"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modern btn-ghost" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn-modern btn-primary-modern" id="shareBtn" onclick="createShare()"><i class="bi bi-link-45deg"></i> Générer le lien</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/theme.js"></script>
<script>
let shareFileId = 0;
function shareFile(id, name) {
    shareFileId = id;
    document.getElementById('shareFileName').textContent = name;
    document.getElementById('shareResult').style.display = 'none';
    document.getElementById('shareBtn').disabled = false;
    new bootstrap.Modal(document.getElementById('shareModal')).show();
}
async function createShare() {
    const btn = document.getElementById('shareBtn');
    btn.disabled = true;
    const fd = new FormData();
    fd.append('action', 'create_share');
    fd.append('file_id', shareFileId);
    fd.append('expiry_hours', document.getElementById('shareExpiry').value);
    fd.append('_csrf_token', '<?= $_SESSION[CSRF_TOKEN_NAME] ?? '' ?>');
    const res = await fetch('/php/actions.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
        document.getElementById('shareLink').value = data.url;
        document.getElementById('shareResult').style.display = 'block';
        showToast('Lien de partage créé', 'success');
    } else {
        showToast(data.error || 'Erreur', 'error');
    }
    btn.disabled = false;
}
function copyShareLink() {
    const input = document.getElementById('shareLink');
    input.select();
    navigator.clipboard.writeText(input.value);
    showToast('Lien copié !', 'success');
}
</script>
</body>
</html>

