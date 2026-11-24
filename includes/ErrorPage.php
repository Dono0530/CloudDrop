<?php
function render_error_page(int $statusCode): void {
    $pages = [
        400 => ['title' => 'Requête invalide', 'message' => 'La demande ne peut pas être traitée. Vérifiez le lien et réessayez.'],
        401 => ['title' => 'Connexion requise', 'message' => 'Connectez-vous pour accéder à cette page.'],
        403 => ['title' => 'Accès refusé', 'message' => 'Votre compte ne dispose pas des droits nécessaires pour afficher cette page.'],
        404 => ['title' => 'Page introuvable', 'message' => 'Cette adresse n’existe pas ou le contenu n’est plus disponible.'],
        429 => ['title' => 'Trop de tentatives', 'message' => 'L’accès est temporairement limité. Réessayez dans quelques minutes.'],
        500 => ['title' => 'Incident temporaire', 'message' => 'CloudDrop rencontre un problème. Réessayez un peu plus tard.'],
    ];
    if (!isset($pages[$statusCode])) $statusCode = 500;
    http_response_code($statusCode);
    $page = $pages[$statusCode];
    $showLogin = in_array($statusCode, [401, 403], true);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title><?= $statusCode ?> · <?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?> — CloudDrop</title>
        <link href="/css/modern.css" rel="stylesheet">
        <style>
            body.error-page { min-height:100vh; margin:0; display:grid; place-items:center; padding:24px; background:radial-gradient(ellipse at top left,#e0e7ff 0,transparent 45%),radial-gradient(ellipse at bottom right,#cffafe 0,transparent 42%),#f8fafc; color:#0f172a; font-family:Inter,system-ui,sans-serif; }
            .error-card { width:min(100%,620px); padding:clamp(28px,6vw,56px); border:1px solid rgba(148,163,184,.25); border-radius:28px; background:rgba(255,255,255,.88); box-shadow:0 24px 80px rgba(15,23,42,.12); text-align:center; }
            .error-mark { width:76px;height:76px;margin:0 auto 22px;display:grid;place-items:center;border-radius:24px;background:linear-gradient(135deg,#6366f1,#06b6d4);color:white;font-size:30px;box-shadow:0 12px 30px rgba(99,102,241,.25); }
            .error-code { margin:0;color:#6366f1;font-size:clamp(4rem,13vw,7rem);font-weight:800;line-height:1;letter-spacing:-.07em; }
            .error-card h1 { margin:18px 0 10px;font-size:clamp(1.5rem,4vw,2rem);font-weight:700; }
            .error-card p { margin:0 auto 28px;max-width:430px;color:#64748b;line-height:1.7; }
            .error-actions { display:flex;justify-content:center;gap:12px;flex-wrap:wrap; }
            .error-actions a { display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:12px 18px;border-radius:12px;text-decoration:none;font-weight:600; }
            .error-primary { background:#4f46e5;color:white; }
            .error-secondary { border:1px solid #cbd5e1;color:#334155; }
            .error-footer { margin-top:32px;font-size:.82rem;color:#94a3b8; }
            .error-footer a { color:#6366f1;text-decoration:none; }
        </style>
    </head>
    <body class="error-page">
        <main class="error-card">
            <div class="error-mark" aria-hidden="true">☁</div>
            <p class="error-code"><?= $statusCode ?></p>
            <h1><?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars($page['message'], ENT_QUOTES, 'UTF-8') ?></p>
            <div class="error-actions">
                <a class="error-primary" href="/index.php">Retour à l’accueil</a>
                <?php if ($showLogin): ?><a class="error-secondary" href="/auth/login.php">Se connecter</a><?php endif; ?>
            </div>
            <div class="error-footer">Besoin d’aide ? <a href="/legal.php">Informations légales et confidentialité</a></div>
        </main>
    </body>
    </html>
    <?php
    exit;
}
