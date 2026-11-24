<?php
/**
 * Pied de page commun CloudDrop.
 */
require_once __DIR__ . '/Auth.php';
?>
<footer class="site-footer">
    <nav class="site-footer-links" aria-label="Navigation de pied de page">
        <a href="/index.php">Accueil</a>
        <?php if (Auth::isLoggedIn()): ?>
            <a href="/php/dashboard.php">Dashboard</a>
            <a href="/php/upload.php">Upload</a>
            <a href="/php/download.php">Fichiers</a>
            <a href="/php/shares.php">Mes liens</a>
            <a href="/php/profile.php">Profil</a>
            <?php if (Auth::isAdmin()): ?><a href="/admin/index.php">Administration</a><?php endif; ?>
        <?php else: ?>
            <a href="/auth/login.php">Connexion</a>
            <a href="/auth/register.php">Créer un compte</a>
        <?php endif; ?>
        <a href="/legal.php">Mentions légales · Confidentialité · Conditions d’utilisation</a>
    </nav>
    <div class="site-footer-copy">CloudDrop · <?= date('Y') ?></div>
</footer>
