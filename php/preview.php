<?php
/**
 * PREVIEW - Aperçu de fichiers (images, PDF, vidéo, audio, texte)
 * Les fichiers sont servis via PHP pour éviter les restrictions .htaccess
 */
require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/Auth.php';
Auth::requireLogin();

require_once __DIR__ . '/../includes/FileManager.php';

$fm = new FileManager();
$fileId = (int)($_GET['id'] ?? 0);
$file = $fm->getFileById($fileId);

if (!$file) { http_response_code(404); die('Fichier introuvable'); }

$ext = strtolower($file['file_extension']);
$type = FileManager::getPreviewType($ext);

if ($type === 'none') { header('Location: /php/download_file.php?id=' . $fileId); exit; }

$filePath = UPLOAD_DIR . $file['filename'];
if (!file_exists($filePath)) { http_response_code(404); die('Fichier physique introuvable'); }

// Servir le fichier via PHP (pas d'accès direct /uploads/)
$fileUrl = '/php/preview_serve.php?id=' . $fileId;
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aperçu - <?= htmlspecialchars($file['original_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/modern.css">
    <style>
        .preview-container { max-width:100%; max-height:75vh; display:flex; align-items:center; justify-content:center; }
        .preview-container img { max-width:100%; max-height:70vh; border-radius:8px; object-fit:contain; }
        .preview-container video { max-width:100%; max-height:70vh; border-radius:8px; }
        .preview-container audio { width:100%; }
        .preview-container .pdf-frame { width:100%; height:75vh; border:none; border-radius:8px; }
        .preview-container pre { background:var(--light-2); color:var(--text); padding:1.5rem; border-radius:8px; max-height:70vh; overflow:auto; width:100%; font-size:0.85rem; line-height:1.6; text-align:left; }
        [data-theme="dark"] .preview-container pre { background:#0f172a; color:#f1f5f9; }
    </style>
</head>
<body>

<nav class="navbar-drive">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="/index.php"><i class="bi bi-cloud-fill"></i> CloudDrop</a>
        <div class="d-flex align-items-center gap-2">
            <a href="/php/download.php" class="nav-link"><i class="bi bi-folder2-open"></i> Fichiers</a>
            <a href="/php/dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <button class="theme-toggle" onclick="toggleTheme()"></button>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-4" style="max-width:1000px;">
    <div class="page-header fade-in d-flex justify-content-between align-items-start">
        <div>
            <h1 style="font-size:1.3rem;"><?= FileManager::getFileIcon($ext) ?> <?= htmlspecialchars($file['original_name']) ?></h1>
            <p><?= FileManager::formatBytes($file['file_size']) ?> · Uploadé par <?= htmlspecialchars($file['uploader_name']) ?> · <?= date('d/m/Y H:i', strtotime($file['uploaded_at'])) ?></p>
        </div>
        <a href="/php/download_file.php?id=<?= $file['id'] ?>" class="btn-modern btn-primary-modern"><i class="bi bi-download"></i> Télécharger</a>
    </div>

    <div class="card-modern fade-in fade-in-delay-1">
        <div class="card-body-modern">
            <div class="preview-container">
                <?php if ($type === 'image'): ?>
                    <img src="<?= $fileUrl ?>" alt="<?= htmlspecialchars($file['original_name']) ?>">

                <?php elseif ($type === 'video'): ?>
                    <video controls preload="metadata" src="<?= $fileUrl ?>">
                        Votre navigateur ne supporte pas la vidéo.
                    </video>

                <?php elseif ($type === 'audio'): ?>
                    <audio controls preload="metadata" src="<?= $fileUrl ?>">
                        Votre navigateur ne supporte pas l'audio.
                    </audio>

                <?php elseif ($type === 'pdf'): ?>
                    <iframe src="<?= $fileUrl ?>" class="pdf-frame"></iframe>

                <?php elseif ($type === 'text'): ?>
                    <pre id="textContent">Chargement...</pre>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/theme.js"></script>
<?php if ($type === 'text'): ?>
<script>
fetch('<?= $fileUrl ?>')
    .then(r => { if (!r.ok) throw new Error(); return r.text(); })
    .then(t => { document.getElementById('textContent').textContent = t; })
    .catch(() => { document.getElementById('textContent').textContent = 'Erreur de chargement du fichier.'; });
</script>
<?php endif; ?>
</body>
</html>

