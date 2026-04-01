<?php
/**
 * PAGE D'ACCUEIL - CloudDrop
 */
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/includes/Auth.php';

$loggedIn = Auth::isLoggedIn();
$isAdmin = Auth::isAdmin();

require_once __DIR__ . '/includes/FileManager.php';
$fm = new FileManager();
$stats = $fm->getGlobalStats();
$recentFiles = $fm->getFiles(['limit' => 5, 'sort' => 'date_desc']);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CloudDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/modern.css">
</head>
<body>

<nav class="navbar-drive">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="/index.php"><i class="bi bi-cloud-fill"></i> CloudDrop</a>
        <div class="d-flex align-items-center gap-2">
            <?php if ($loggedIn): ?>
                <a href="/php/dashboard.php" class="nav-link d-none d-md-inline"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="/php/upload.php" class="nav-link d-none d-md-inline"><i class="bi bi-cloud-arrow-up"></i> Upload</a>
                <a href="/php/download.php" class="nav-link d-none d-md-inline"><i class="bi bi-folder2-open"></i> Fichiers</a>
                <?php if ($isAdmin): ?>
                    <a href="/admin/index.php" class="nav-link d-none d-md-inline"><i class="bi bi-shield-lock"></i> Admin</a>
                <?php endif; ?>
                <a href="/auth/logout.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            <?php else: ?>
                <a href="/auth/login.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-in-right"></i> Connexion</a>
                <a href="/auth/register.php" class="btn-modern btn-outline-modern btn-sm"><i class="bi bi-person-plus"></i> S'inscrire</a>
            <?php endif; ?>
            <button class="theme-toggle" onclick="toggleTheme()" title="Changer de thème"></button>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-5">
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8 text-center fade-in">
            <h1 style="font-size:2.75rem;font-weight:800;margin-bottom:1rem;background:linear-gradient(135deg,var(--primary),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
                Partagez vos fichiers,<br>en toute simplicité
            </h1>
            <p style="font-size:1.15rem;color:var(--text-muted);max-width:550px;margin:0 auto 2rem;">
                Un espace de stockage partagé sécurisé. Uploadez, organisez et téléchargez vos fichiers depuis n'importe où.
            </p>
            <?php if ($loggedIn): ?>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="/php/upload.php" class="btn-modern btn-primary-modern btn-lg">
                        <i class="bi bi-cloud-arrow-up"></i> Uploader un fichier
                    </a>
                    <a href="/php/download.php" class="btn-modern btn-outline-modern btn-lg">
                        <i class="bi bi-folder2-open"></i> Parcourir les fichiers
                    </a>
                </div>
            <?php else: ?>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="/auth/register.php" class="btn-modern btn-primary-modern btn-lg">
                        <i class="bi bi-rocket-takeoff"></i> Créer un compte
                    </a>
                    <a href="/auth/login.php" class="btn-modern btn-outline-modern btn-lg">
                        <i class="bi bi-key"></i> Se connecter
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 mb-5 justify-content-center fade-in fade-in-delay-1">
        <div class="col-6 col-md-3">
            <div class="stat-card stat-primary">
                <div class="stat-icon"><i class="bi bi-folder-fill"></i></div>
                <div class="stat-value"><?= $stats['total_files'] ?></div>
                <div class="stat-label">Fichiers</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-accent">
                <div class="stat-icon"><i class="bi bi-hdd-fill"></i></div>
                <div class="stat-value"><?= FileManager::formatBytes($stats['total_size']) ?></div>
                <div class="stat-label">Stockage utilisé</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-success">
                <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                <div class="stat-value"><?= $stats['total_users'] ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card stat-warning">
                <div class="stat-icon"><i class="bi bi-shield-check"></i></div>
                <div class="stat-value">100%</div>
                <div class="stat-label">Sécurisé</div>
            </div>
        </div>
    </div>

    <?php if (!empty($recentFiles)): ?>
    <div class="row justify-content-center fade-in fade-in-delay-2">
        <div class="col-lg-10">
            <div class="card-modern">
                <div class="card-header-modern">
                    <i class="bi bi-clock-history"></i> Fichiers récents
                    <a href="/php/download.php" class="btn-modern btn-ghost btn-sm ms-auto">Voir tout <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="card-body-modern p-0">
                    <?php foreach ($recentFiles as $file): ?>
                    <div class="file-item">
                        <div class="file-icon"><?= FileManager::getFileIcon($file['file_extension']) ?></div>
                        <div class="file-info">
                            <div class="name"><?= htmlspecialchars($file['original_name']) ?></div>
                            <div class="meta">
                                <span><?= FileManager::formatBytes($file['file_size']) ?></span>
                                <span>Par <?= htmlspecialchars($file['uploader_name'] ?? 'Inconnu') ?></span>
                                <span><?= date('d/m/Y H:i', strtotime($file['uploaded_at'])) ?></span>
                            </div>
                        </div>
                        <?php if ($loggedIn): ?>
                        <div class="file-actions">
                            <a href="/php/download_file.php?id=<?= $file['id'] ?>" class="btn-modern btn-outline-modern btn-sm"><i class="bi bi-download"></i></a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/theme.js"></script>
</body>
</html>

