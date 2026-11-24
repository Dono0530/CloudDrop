<?php
require_once __DIR__ . '/config/init.php';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Informations légales, conditions d'utilisation et politique de confidentialité de CloudDrop.">
    <title>Informations légales — CloudDrop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/modern.css?v=20261124" rel="stylesheet">
    <style>
        .legal-wrap { max-width:900px; }
        .legal-card { margin-bottom:1.25rem; }
        .legal-card h2 { font-size:1.25rem;font-weight:700;margin-bottom:1rem; }
        .legal-card h3 { font-size:1rem;font-weight:650;margin-top:1.25rem; }
        .legal-card p,.legal-card li { color:var(--text-muted);line-height:1.75; }
        .legal-placeholder { color:#b45309;font-weight:600; }
    </style>
</head>
<body>
<nav class="navbar-drive">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="/index.php"><i class="bi bi-cloud-fill"></i> CloudDrop</a>
        <div class="d-flex align-items-center gap-2">
            <?php if (Auth::isLoggedIn()): ?>
                <a href="/php/dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
                <a href="/php/shares.php" class="nav-link"><i class="bi bi-link-45deg"></i> Mes liens</a>
                <a href="/auth/logout.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
            <?php else: ?>
                <a href="/auth/login.php" class="nav-link nav-link-cta"><i class="bi bi-box-arrow-in-right"></i> Connexion</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="container-fluid px-4 py-5 legal-wrap">
    <header class="page-header">
        <h1><i class="bi bi-shield-check"></i> Informations légales</h1>
        <p>Mentions légales, conditions d’utilisation et confidentialité · Mise à jour : 24 novembre 2026</p>
    </header>

    <div class="alert alert-warning" role="alert">
        <strong>À compléter avant de rendre le site public :</strong> les champs surlignés sont des informations obligatoires à fournir par l’exploitant. Les mentions ci-dessous décrivent le fonctionnement connu de CloudDrop, mais ne remplacent pas une vérification juridique adaptée à l’activité réelle.
    </div>

    <section class="card-modern legal-card"><div class="card-body-modern">
        <h2>Éditeur du site</h2>
        <p>Nom du site : CloudDrop<br>
        Adresse du site : <span class="legal-placeholder">[à compléter : URL du site]</span><br>
        Responsable de publication : <span class="legal-placeholder">[à compléter : nom et prénom ou raison sociale]</span><br>
        Adresse : <span class="legal-placeholder">[à compléter : adresse professionnelle ou adresse requise selon le statut]</span><br>
        Contact : <span class="legal-placeholder">[à compléter : adresse e-mail de contact]</span><br>
        Identifiant d’entreprise : <span class="legal-placeholder">[à compléter si applicable : SIREN/SIRET]</span></p>
        <h3>Hébergement</h3>
        <p>Hébergeur : <span class="legal-placeholder">[à compléter : nom de l’hébergeur]</span><br>
        Adresse : <span class="legal-placeholder">[à compléter : adresse de l’hébergeur]</span><br>
        Contact de l’hébergeur : <span class="legal-placeholder">[à compléter : coordonnées ou URL de contact]</span></p>
    </div></section>

    <section class="card-modern legal-card"><div class="card-body-modern">
        <h2>Conditions d’utilisation</h2>
        <p>CloudDrop permet aux titulaires d’un compte de stocker, organiser et partager des fichiers. L’utilisateur reste responsable des contenus qu’il dépose et partage, ainsi que des droits nécessaires pour les utiliser.</p>
        <ul>
            <li>Ne déposez et ne partagez aucun contenu illicite, malveillant ou portant atteinte aux droits d’autrui.</li>
            <li>Un fichier marqué « public » peut être visible par les visiteurs. Un lien de partage doit être traité comme un secret transmissible à toute personne qui le possède.</li>
            <li>Protégez votre compte et révoquez les liens qui ne doivent plus fonctionner.</li>
            <li>L’éditeur peut suspendre un compte ou un contenu en cas d’abus, de risque de sécurité ou d’obligation légale.</li>
        </ul>
        <p>Le service est fourni en l’état. Sa disponibilité et la conservation permanente des fichiers ne sont pas garanties : gardez une copie des contenus importants.</p>
    </div></section>

    <section class="card-modern legal-card"><div class="card-body-modern">
        <h2>Confidentialité et données personnelles</h2>
        <p>Responsable du traitement : <span class="legal-placeholder">[à compléter : identité et contact de l’éditeur]</span>.</p>
        <h3>Données utilisées et finalités</h3>
        <ul>
            <li>Compte : pseudo et empreinte du mot de passe, pour créer et sécuriser l’accès.</li>
            <li>Fichiers : contenu, nom, taille, type, dossier et visibilité, pour fournir le stockage et le partage demandés.</li>
            <li>Sécurité et activité : adresse IP, date et action. Pour les téléchargements via lien partagé, l’adresse IP du visiteur et son navigateur sont enregistrés pour montrer l’historique au propriétaire du lien et prévenir les abus.</li>
        </ul>
        <p>Les adresses IP sont résolues à partir de l’en-tête Cloudflare uniquement lorsque la connexion réseau directe provient d’une plage proxy Cloudflare configurée par le site. Cloudflare et l’hébergeur peuvent traiter des données techniques pour transmettre et sécuriser les requêtes, selon leurs propres rôles et conditions.</p>
        <h3>Fondements et durée de conservation</h3>
        <p>Les données nécessaires au compte et au partage sont utilisées pour fournir les fonctionnalités demandées. Les journaux sont utilisés pour la sécurité et le suivi des liens. Les journaux applicatifs et les historiques de partage sont conservés jusqu’à 6 mois ; les essais de mot de passe incorrect sont purgés après 24 heures. Le compte et ses fichiers sont conservés jusqu’à leur suppression par l’utilisateur ou l’éditeur, sous réserve des copies de sauvegarde et obligations applicables.</p>
        <p><strong>Configuration requise :</strong> planifiez l’exécution quotidienne de <code>php/maintenance/purge_logs.php</code> pour appliquer la purge des journaux.</p>
        <h3>Destinataires et services externes</h3>
        <p>Les données sont accessibles à l’exploitant et aux prestataires techniques nécessaires à l’hébergement et à la sécurité du service. Les pages chargent également des ressources Bootstrap, Bootstrap Icons et Google Fonts depuis des CDN tiers, qui reçoivent des données techniques de connexion du navigateur. <span class="legal-placeholder">[à compléter : confirmer les prestataires réellement activés, leurs pays de traitement et les éventuels transferts hors EEE]</span>.</p>
        <h3>Vos droits</h3>
        <p>Selon les règles applicables, vous pouvez demander l’accès, la rectification, l’effacement, la limitation ou vous opposer à certains traitements. Écrivez à <span class="legal-placeholder">[à compléter : adresse de contact dédiée]</span>. Vous pouvez aussi adresser une réclamation à la CNIL. Les demandes nécessitent de vérifier raisonnablement l’identité du demandeur.</p>
        <p>CloudDrop utilise un cookie de session nécessaire à la connexion et le stockage local du navigateur pour mémoriser le thème d’affichage. Aucun cookie publicitaire n’est volontairement configuré par l’application.</p>
    </div></section>
    <p style="font-size:.85rem;color:var(--text-muted);">Pour les informations officielles sur les mentions légales et la protection des données, consultez la <a href="https://www.cnil.fr/fr/cnil-direct/question/les-mentions-legales-sur-un-site-web-est-ce-obligatoire" rel="noopener noreferrer">CNIL</a>.</p>
</main>
<script src="/js/theme.js"></script>
</body>
</html>
