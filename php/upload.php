<?php
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/FileManager.php';
$fm = new FileManager();
$folders = $fm->getFolders($_SESSION['user_id']);
$selectedFolder = (int)($_GET['folder'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload - CloudDrop</title>
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
            <a href="/php/upload.php" class="nav-link active"><i class="bi bi-cloud-arrow-up"></i> Upload</a>
            <a href="/php/download.php" class="nav-link"><i class="bi bi-folder2-open"></i> Fichiers</a>
            <a href="/php/profile.php" class="nav-link"><i class="bi bi-person"></i> Profil</a>
            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                <a href="/admin/index.php" class="nav-link"><i class="bi bi-shield-lock"></i> Admin</a>
            <?php endif; ?>
            <a href="/auth/logout.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            <button class="theme-toggle" onclick="toggleTheme()"></button>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-4" style="max-width:900px;">
    <div class="page-header fade-in">
        <h1><i class="bi bi-cloud-arrow-up"></i> Uploader des fichiers</h1>
        <p>Glissez-déposez vos fichiers ou cliquez pour les sélectionner</p>
    </div>

    <div class="card-modern fade-in fade-in-delay-1">
        <div class="card-body-modern">
            <!-- Sélecteur de dossier -->
            <?php if (!empty($folders)): ?>
            <div class="form-group-modern mb-3">
                <label><i class="bi bi-folder"></i> Destination</label>
                <select id="folderSelect" class="form-control-modern">
                    <option value="0">— Racine (aucun dossier) —</option>
                    <?php foreach ($folders as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $selectedFolder === (int)$f['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['name']) ?> (<?= $f['file_count'] ?> fichier<?= $f['file_count'] > 1 ? 's' : '' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Drop Zone -->
            <div class="upload-zone" id="dropZone">
                <input type="file" id="fileInput" multiple>
                <i class="bi bi-cloud-arrow-up-fill upload-icon-bi"></i>
                <div class="upload-text">Glissez vos fichiers ici</div>
                <div class="upload-hint">ou cliquez pour parcourir — Max 100 Go par fichier</div>
            </div>

            <!-- File Queue -->
            <div id="fileQueue" class="mt-4"></div>

            <!-- Global Progress -->
            <div id="globalProgress" class="mt-4" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span id="globalProgressLabel" style="font-size:0.9rem;font-weight:500;">Envoi en cours...</span>
                    <span id="globalProgressPercent" style="font-size:0.85rem;color:var(--text-muted);">0%</span>
                </div>
                <div class="progress-modern progress-large">
                    <div class="progress-bar-modern" id="globalProgressBar" style="width:0%"></div>
                </div>
                <div id="globalProgressInfo" style="font-size:0.8rem;color:var(--text-muted);margin-top:0.4rem;"></div>
            </div>

            <!-- Actions -->
            <div class="mt-4 d-flex justify-content-between align-items-center">
                <button id="clearBtn" class="btn-modern btn-ghost" style="display:none;">
                    <i class="bi bi-x-circle"></i> Tout effacer
                </button>
                <div class="d-flex gap-2 ms-auto">
                    <button id="cancelBtn" class="btn-modern btn-danger-modern" style="display:none;">
                        <i class="bi bi-stop-circle"></i> Annuler
                    </button>
                    <button id="uploadBtn" class="btn-modern btn-primary-modern btn-lg" disabled>
                        <i class="bi bi-upload"></i> Lancer le transfert
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Info -->
    <div class="card-modern mt-4 fade-in fade-in-delay-2">
        <div class="card-body-modern">
            <div class="alert-modern alert-info-modern">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    <strong>Bon à savoir :</strong>
                    L'upload se fait par morceaux de 10 Mo. Les fichiers sont uploadés en parallèle (2 simultanés).
                    La progression est mise à jour en temps réel avec le débit et le temps restant estimé.
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/theme.js"></script>
<script src="/js/upload.js"></script>
</body>
</html>

