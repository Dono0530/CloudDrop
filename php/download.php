<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/FileManager.php';

$fm = new FileManager();

$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date_desc';
$ext = trim($_GET['ext'] ?? '');
$folderId = (int)($_GET['folder'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$filters = [
    'search' => $search, 'sort' => $sort,
    'limit' => $perPage, 'offset' => $offset,
    'folder_id' => $folderId,
];
if ($ext) $filters['extension'] = $ext;

$files = $fm->getFiles($filters);
$totalFiles = $fm->countFiles($filters);
$totalPages = max(1, ceil($totalFiles / $perPage));
$extensions = $fm->getAvailableExtensions();
$folders = $fm->getFolders($_SESSION['user_id']);
$currentFolder = $folderId ? $fm->getFolderById($folderId) : null;
$allFolders = Auth::isAdmin() ? $fm->getAllFolders() : $folders;

$sortLabels = [
    'date_desc' => 'Plus récent', 'date_asc' => 'Plus ancien',
    'name_asc' => 'Nom A-Z', 'name_desc' => 'Nom Z-A',
    'size_desc' => 'Plus gros', 'size_asc' => 'Plus petit',
];

// Créer dossier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_folder') {
    if (Auth::validateCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $name = trim($_POST['folder_name'] ?? '');
        if ($name) {
            $fm->createFolder($name, $_SESSION['user_id'], $folderId);
            header('Location: ?folder=' . $folderId);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fichiers - CloudDrop</title>
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
            <a href="/auth/logout.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-right"></i></a>
            <button class="theme-toggle" onclick="toggleTheme()"></button>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-4">
    <div class="page-header fade-in">
        <h1><i class="bi bi-folder2-open"></i> <?php if ($currentFolder): ?><?= htmlspecialchars($currentFolder['name']) ?><?php else: ?>Tous les fichiers<?php endif; ?></h1>
        <p><?= $totalFiles ?> fichier(s) <?php if ($currentFolder): ?>dans ce dossier<?php endif; ?></p>
    </div>

    <!-- Dossiers -->
    <?php if (!$currentFolder && !empty($folders)): ?>
    <div class="mb-4 fade-in fade-in-delay-1">
        <div class="d-flex align-items-center gap-2 mb-3">
            <h6 style="font-weight:600;margin:0;"><i class="bi bi-collection"></i> Dossiers</h6>
        </div>
        <div class="d-flex flex-wrap gap-3">
            <?php foreach ($folders as $folder): ?>
            <a href="?folder=<?= $folder['id'] ?>" class="card-modern" style="padding:1rem 1.5rem;text-decoration:none;color:var(--text);min-width:160px;">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-folder-fill" style="color:var(--warning);font-size:1.5rem;"></i>
                    <div>
                        <div style="font-weight:500;font-size:0.9rem;"><?= htmlspecialchars($folder['name']) ?></div>
                        <div style="font-size:0.75rem;color:var(--text-muted);"><?= $folder['file_count'] ?> fichier(s)</div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Breadcrumb + actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in fade-in-delay-1">
        <div>
            <?php if ($currentFolder): ?>
                <a href="/php/download.php" class="btn-modern btn-ghost btn-sm"><i class="bi bi-arrow-left"></i> Retour</a>
            <?php endif; ?>
        </div>
        <button class="btn-modern btn-outline-modern btn-sm" data-bs-toggle="modal" data-bs-target="#newFolderModal">
            <i class="bi bi-folder-plus"></i> Nouveau dossier
        </button>
    </div>

    <!-- Filtres -->
    <div class="card-modern mb-4 fade-in fade-in-delay-1">
        <div class="card-body-modern">
            <form method="GET" class="row g-3 align-items-end">
                <?php if ($folderId): ?><input type="hidden" name="folder" value="<?= $folderId ?>"><?php endif; ?>
                <div class="col-md-4">
                    <div class="search-bar">
                        <span class="search-icon"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control-modern" placeholder="Rechercher un fichier..."
                               value="<?= htmlspecialchars($search) ?>" style="padding-left:2.5rem;">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="sort" class="form-control-modern">
                        <?php foreach ($sortLabels as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $sort === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="ext" class="form-control-modern">
                        <option value="">Tous les types</option>
                        <?php foreach ($extensions as $e): ?>
                            <option value="<?= htmlspecialchars($e['file_extension']) ?>" <?= $ext === $e['file_extension'] ? 'selected' : '' ?>>
                                .<?= htmlspecialchars($e['file_extension']) ?> (<?= $e['count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn-modern btn-primary-modern w-100"><i class="bi bi-funnel"></i> Filtrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste -->
    <div class="card-modern fade-in fade-in-delay-2">
        <div class="card-body-modern p-0">
            <?php if (empty($files)): ?>
                <div class="empty-state">
                    <i class="bi bi-search" style="font-size:3rem;color:var(--text-muted);display:block;margin-bottom:1rem;"></i>
                    <h3>Aucun fichier trouvé</h3>
                    <p>Essayez de modifier vos critères de recherche.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table-modern">
                        <thead>
                            <tr><th>Fichier</th><th>Taille</th><th>Uploadé par</th><th>Date</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                            <tr>
                                <td>
                                    <div class="file-name">
                                        <span class="icon"><?= FileManager::getFileIcon($file['file_extension']) ?></span>
                                        <span><?= htmlspecialchars($file['original_name']) ?></span>
                                    </div>
                                </td>
                                <td><?= FileManager::formatBytes($file['file_size']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar avatar-sm"><?= strtoupper(substr($file['uploader_name'] ?? '?', 0, 1)) ?></div>
                                        <span><?= htmlspecialchars($file['uploader_name'] ?? 'Inconnu') ?></span>
                                    </div>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($file['uploaded_at'])) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <?php if ($fm->isPreviewable($file['file_extension'])): ?>
                                        <a href="/php/preview.php?id=<?= $file['id'] ?>" class="btn-modern btn-outline-modern btn-sm" title="Aperçu"><i class="bi bi-eye"></i></a>
                                        <?php endif; ?>
                                        <a href="/php/download_file.php?id=<?= $file['id'] ?>" class="btn-modern btn-primary-modern btn-sm" title="Télécharger"><i class="bi bi-download"></i></a>
                                        <button class="btn-modern btn-outline-modern btn-sm" title="Déplacer"
                                            onclick="openMoveModal(<?= $file['id'] ?>, '<?= htmlspecialchars(addslashes($file['original_name'])) ?>')">
                                            <i class="bi bi-folder-symlink"></i>
                                        </button>
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

    <?php if ($totalPages > 1): ?>
    <div class="mt-4 fade-in fade-in-delay-3">
        <div class="pagination-modern">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"><i class="bi bi-chevron-left"></i></a>
            <?php endif; ?>
            <?php $start = max(1, $page - 2); $end = min($totalPages, $page + 2); for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Modal Nouveau Dossier -->
<div class="modal fade" id="newFolderModal" tabindex="-1">
    <div class="modal-dialog modal-modern">
        <div class="modal-content">
            <form method="POST">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="create_folder">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-folder-plus"></i> Nouveau dossier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group-modern">
                        <label>Nom du dossier</label>
                        <input type="text" name="folder_name" class="form-control-modern" required placeholder="Mon dossier">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-ghost" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn-modern btn-primary-modern"><i class="bi bi-folder-plus"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Déplacer Fichier -->
<div class="modal fade" id="moveModal" tabindex="-1">
    <div class="modal-dialog modal-modern">
        <div class="modal-content">
            <form method="POST" action="/php/actions.php">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="move_file">
                <input type="hidden" name="file_id" id="moveFileId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-folder-symlink"></i> Déplacer le fichier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="moveFileName" style="font-weight:500;margin-bottom:1rem;"></p>
                    <div class="form-group-modern">
                        <label>Déplacer vers</label>
                        <select name="target_folder" class="form-control-modern" id="moveTargetFolder">
                            <option value="0">Racine (aucun dossier)</option>
                            <?php foreach ($allFolders as $f): ?>
                                <option value="<?= $f['id'] ?>">
                                    <?= htmlspecialchars($f['name']) ?>
                                    <?php if (Auth::isAdmin() && isset($f['owner_name'])): ?>
                                        — <?= htmlspecialchars($f['owner_name']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-ghost" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn-modern btn-primary-modern"><i class="bi bi-folder-symlink"></i> Déplacer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/theme.js"></script>
<script>
function openMoveModal(fileId, fileName) {
    document.getElementById('moveFileId').value = fileId;
    document.getElementById('moveFileName').textContent = fileName;
    new bootstrap.Modal(document.getElementById('moveModal')).show();
}
</script>
</body>
</html>

