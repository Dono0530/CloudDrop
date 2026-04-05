<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
Auth::requireAdmin();

require_once __DIR__ . '/../includes/FileManager.php';

$fm = new FileManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) { $flashError = 'Jeton CSRF invalide.'; }
    else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'delete_file') {
                $fm->deleteFile((int)($_POST['file_id'] ?? 0), $_SESSION['user_id'], true);
                $flashSuccess = 'Fichier supprimé.';
            } elseif ($action === 'delete_user') {
                $uid = (int)($_POST['user_id'] ?? 0);
                if ($uid === (int)$_SESSION['user_id']) { $flashError = 'Impossible de supprimer votre propre compte.'; }
                else {
                    $db = Database::getInstance();
                    $db->execute("UPDATE files SET deleted_at = NOW(), deleted_by = ? WHERE user_id = ? AND deleted_at IS NULL", [$_SESSION['user_id'], $uid]);
                    $db->execute("DELETE FROM users WHERE id = ?", [$uid]);
                    $flashSuccess = 'Utilisateur supprimé.';
                }
            } else { $flashError = 'Action inconnue.'; }
        } catch (Exception $e) { $flashError = $e->getMessage(); }
    }
}

$files = $fm->getFiles(['limit' => 100]);
$globalStats = $fm->getGlobalStats();
$activity = $fm->getRecentActivity(30);
$db = Database::getInstance();
$users = $db->fetchAll("SELECT u.*, COUNT(f.id) as file_count, COALESCE(SUM(f.file_size), 0) as total_size FROM users u LEFT JOIN files f ON u.id = f.user_id AND f.deleted_at IS NULL GROUP BY u.id ORDER BY u.id");
$tab = $_GET['tab'] ?? 'files';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - CloudDrop</title>
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
            <a href="/php/profile.php" class="nav-link"><i class="bi bi-person"></i> Profil</a>
            <a href="/admin/index.php" class="nav-link active"><i class="bi bi-shield-lock"></i> Admin</a>
            <a href="/auth/logout.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            <button class="theme-toggle" onclick="toggleTheme()"></button>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-4">
    <div class="page-header fade-in">
        <h1><i class="bi bi-gear-fill"></i> Administration</h1>
        <p>Gérez les fichiers et utilisateurs</p>
    </div>

    <?php if (!empty($flashSuccess)): ?>
        <div class="alert-modern alert-success-modern fade-in"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashError)): ?>
        <div class="alert-modern alert-danger-modern fade-in"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4 fade-in fade-in-delay-1">
        <div class="col-6 col-md-3">
            <div class="stat-card stat-primary">
                <div class="stat-icon"><i class="bi bi-file-earmark-fill"></i></div>
                <div class="stat-value"><?= $globalStats['total_files'] ?></div>
                <div class="stat-label">Fichiers</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-accent">
                <div class="stat-icon"><i class="bi bi-hdd-fill"></i></div>
                <div class="stat-value"><?= FileManager::formatBytes($globalStats['total_size']) ?></div>
                <div class="stat-label">Stockage</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-success">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-value"><?= count($users) ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-warning">
                <div class="stat-icon"><i class="bi bi-lightning-charge-fill"></i></div>
                <div class="stat-value"><?= count($activity) ?></div>
                <div class="stat-label">Actions récentes</div>
            </div>
        </div>
    </div>

    <div class="filter-pills mb-4 fade-in fade-in-delay-2">
        <a href="?tab=files" class="filter-pill <?= $tab === 'files' ? 'active' : '' ?>"><i class="bi bi-file-earmark"></i> Fichiers</a>
        <a href="?tab=users" class="filter-pill <?= $tab === 'users' ? 'active' : '' ?>"><i class="bi bi-people"></i> Utilisateurs</a>
        <a href="?tab=activity" class="filter-pill <?= $tab === 'activity' ? 'active' : '' ?>"><i class="bi bi-lightning-charge"></i> Activité</a>
    </div>

    <?php if ($tab === 'files'): ?>
    <div class="card-modern fade-in fade-in-delay-2">
        <div class="card-header-modern"><i class="bi bi-file-earmark"></i> Tous les fichiers</div>
        <div class="card-body-modern p-0">
            <?php if (empty($files)): ?>
                <div class="empty-state"><i class="bi bi-inbox" style="font-size:3rem;color:var(--text-muted);display:block;margin-bottom:1rem;"></i><h3>Aucun fichier</h3></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table-modern">
                        <thead><tr><th>Fichier</th><th>Utilisateur</th><th>Taille</th><th>Date</th><th>IP</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($files as $f): ?>
                            <tr>
                                <td><div class="file-name"><span class="icon"><?= FileManager::getFileIcon($f['file_extension']) ?></span> <?= htmlspecialchars($f['original_name']) ?></div></td>
                                <td><div class="d-flex align-items-center gap-2"><div class="avatar avatar-sm"><?= strtoupper(substr($f['uploader_name'] ?? '?', 0, 1)) ?></div> <?= htmlspecialchars($f['uploader_name'] ?? $f['user_id']) ?></div></td>
                                <td><?= FileManager::formatBytes($f['file_size']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($f['uploaded_at'])) ?></td>
                                <td style="font-family:monospace;font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($f['ip_address'] ?? '') ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <?php if ($fm->isPreviewable($f['file_extension'])): ?>
                                        <a href="/php/preview.php?id=<?= $f['id'] ?>" class="btn-modern btn-outline-modern btn-sm"><i class="bi bi-eye"></i></a>
                                        <?php endif; ?>
                                        <a href="/php/download_file.php?id=<?= $f['id'] ?>" class="btn-modern btn-outline-modern btn-sm"><i class="bi bi-download"></i></a>
                                        <form method="POST" onsubmit="return confirm('Supprimer ?');" style="display:inline;">
                                            <?= Auth::csrfField() ?>
                                            <input type="hidden" name="action" value="delete_file">
                                            <input type="hidden" name="file_id" value="<?= $f['id'] ?>">
                                            <button type="submit" class="btn-modern btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash3"></i></button>
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

    <?php elseif ($tab === 'users'): ?>
    <div class="card-modern fade-in fade-in-delay-2">
        <div class="card-header-modern"><i class="bi bi-people"></i> Utilisateurs</div>
        <div class="card-body-modern p-0">
            <div style="overflow-x:auto;">
                <table class="table-modern">
                    <thead><tr><th>Utilisateur</th><th>Rôle</th><th>Fichiers</th><th>Espace</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><div class="d-flex align-items-center gap-2"><div class="avatar"><?= strtoupper(substr($u['pseudo'], 0, 1)) ?></div><div><div style="font-weight:500;"><?= htmlspecialchars($u['pseudo']) ?></div><div style="font-size:0.75rem;color:var(--text-muted);">ID: <?= $u['id'] ?></div></div></div></td>
                            <td><span class="badge-modern <?= $u['role'] === 'admin' ? 'badge-danger' : 'badge-primary' ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                            <td><?= $u['file_count'] ?></td>
                            <td><?= FileManager::formatBytes($u['total_size']) ?></td>
                            <td>
                                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                <form method="POST" onsubmit="return confirm('Supprimer cet utilisateur et tous ses fichiers ?');" style="display:inline;">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-modern btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash3"></i> Supprimer</button>
                                </form>
                                <?php else: ?>
                                    <span class="badge-modern badge-success">Vous</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'activity'): ?>
    <div class="card-modern fade-in fade-in-delay-2">
        <div class="card-header-modern"><i class="bi bi-lightning-charge"></i> Journal d'activité</div>
        <div class="card-body-modern p-0">
            <?php if (empty($activity)): ?>
                <div class="empty-state"><i class="bi bi-inbox" style="font-size:3rem;color:var(--text-muted);display:block;margin-bottom:1rem;"></i><h3>Aucune activité</h3></div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table-modern">
                        <thead><tr><th>Utilisateur</th><th>Action</th><th>Détails</th><th>IP</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php $actionLabels = ['upload' => '<i class="bi bi-cloud-arrow-up"></i> Upload', 'delete' => '<i class="bi bi-trash3"></i> Suppression', 'login' => '<i class="bi bi-box-arrow-in-right"></i> Connexion', 'logout' => '<i class="bi bi-box-arrow-right"></i> Déconnexion'];
                            foreach ($activity as $act): ?>
                            <tr>
                                <td><div class="d-flex align-items-center gap-2"><div class="avatar avatar-sm"><?= strtoupper(substr($act['pseudo'] ?? '?', 0, 1)) ?></div> <?= htmlspecialchars($act['pseudo'] ?? 'Inconnu') ?></div></td>
                                <td><?= $actionLabels[$act['action']] ?? $act['action'] ?></td>
                                <td style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($act['details'] ?? '') ?></td>
                                <td style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($act['ip_address'] ?? '') ?></td>
                                <td><?= date('d/m/Y H:i:s', strtotime($act['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/theme.js"></script>
</body>
</html>

