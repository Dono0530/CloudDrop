<?php
/**
 * Navigation commune CloudDrop.
 * Les liens visibles et leur état actif sont définis ici pour toutes les pages HTML.
 */
require_once __DIR__ . '/Auth.php';

$siteHeaderPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$siteHeaderActive = static function (string $key) use ($siteHeaderPath): string {
    $routes = [
        'home' => ['/','/index.php'],
        'dashboard' => ['/php/dashboard.php'],
        'upload' => ['/php/upload.php'],
        'files' => ['/php/download.php'],
        'shares' => ['/php/shares.php'],
        'profile' => ['/php/profile.php'],
        'admin' => ['/admin/index.php'],
        'legal' => ['/legal.php'],
        'login' => ['/auth/login.php'],
        'register' => ['/auth/register.php'],
    ];
    return in_array($siteHeaderPath, $routes[$key] ?? [], true) ? ' active' : '';
};
$siteHeaderLoggedIn = Auth::isLoggedIn();
$siteHeaderIsAdmin = Auth::isAdmin();
?>
<nav class="navbar-drive" aria-label="Navigation principale">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <a class="navbar-brand" href="/index.php"><i class="bi bi-cloud-fill"></i> CloudDrop</a>
        <div class="site-nav-links d-flex align-items-center gap-1">
            <a href="/index.php" class="nav-link<?= $siteHeaderActive('home') ?>"><i class="bi bi-house"></i> Accueil</a>
            <?php if ($siteHeaderLoggedIn): ?>
                <a href="/php/dashboard.php" class="nav-link<?= $siteHeaderActive('dashboard') ?>"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="/php/upload.php" class="nav-link<?= $siteHeaderActive('upload') ?>"><i class="bi bi-cloud-arrow-up"></i> Upload</a>
                <a href="/php/download.php" class="nav-link<?= $siteHeaderActive('files') ?>"><i class="bi bi-folder2-open"></i> Fichiers</a>
                <a href="/php/shares.php" class="nav-link<?= $siteHeaderActive('shares') ?>"><i class="bi bi-link-45deg"></i> Mes liens</a>
                <a href="/php/profile.php" class="nav-link<?= $siteHeaderActive('profile') ?>"><i class="bi bi-person"></i> Profil</a>
                <?php if ($siteHeaderIsAdmin): ?>
                    <a href="/admin/index.php" class="nav-link<?= $siteHeaderActive('admin') ?>"><i class="bi bi-shield-lock"></i> Admin</a>
                <?php endif; ?>
                <a href="/auth/logout.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            <?php else: ?>
                <a href="/auth/login.php" class="nav-link nav-link-cta<?= $siteHeaderActive('login') ?>"><i class="bi bi-box-arrow-in-right"></i> Connexion</a>
                <a href="/auth/register.php" class="btn-modern btn-outline-modern btn-sm<?= $siteHeaderActive('register') ?>"><i class="bi bi-person-plus"></i> S'inscrire</a>
            <?php endif; ?>
            <button class="theme-toggle" onclick="toggleTheme()" title="Changer de thème" aria-label="Changer de thème"></button>
        </div>
    </div>
</nav>
