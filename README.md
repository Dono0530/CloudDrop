# CloudDrop

CloudDrop est une application PHP/MySQL de dépôt et de partage de fichiers. Elle propose des comptes utilisateur, des dossiers, des fichiers publics ou privés, des liens de partage contrôlables et l'aperçu de certains formats.

## Sommaire

- Fonctionnalités
- Prérequis
- Installation neuve
- Mise à jour d'une installation existante
- Utilisation
- Cloudflare et adresses IP
- Confidentialité et maintenance
- Compte administrateur
- Dépannage
- Développement

## Fonctionnalités

- Inscription, connexion, gestion du profil et rôles utilisateur/administrateur.
- Upload par morceaux, choix d'un dossier et visibilité publique ou privée.
- Dossiers partagés : seuls les fichiers publics d'un dossier sont visibles aux autres utilisateurs.
- Page Mes liens : durée, mot de passe facultatif, nombre maximal de téléchargements, aperçu et historique.
- Aperçu de formats courants : images, vidéo, audio, PDF et fichiers texte.
- Pages d'erreur stylisées, thème clair/sombre et informations légales.

## Prérequis

- PHP 8.0 ou plus récent.
- MySQL ou MariaDB avec InnoDB.
- Extensions PHP PDO, pdo_mysql et fileinfo.
- Apache avec prise en charge de .htaccess et AllowOverride activé. Nginx nécessite des règles équivalentes configurées dans son serveur.
- HTTPS recommandé pour tout déploiement public.
- Pour Cloudflare : zone proxifiée et configuration de l'hébergement afin que l'origine ne soit joignable que via Cloudflare.

## Installation neuve

### 1. Récupérer le projet

Déployez le dépôt dans le dossier servi par l'hébergement. La branche dev contient les améliorations en cours ; main est la branche de production.

Avec Git :

~~~bash
git clone --branch dev https://github.com/Dono0530/CloudDrop.git
cd CloudDrop
~~~

Avec cPanel, clonez le dépôt via Git Version Control ou téléversez une archive de la branche choisie.

### 2. Créer la base de données

Créez une base MySQL/MariaDB et un utilisateur avec des droits sur cette base. Importez le schéma :

~~~bash
mysql -u UTILISATEUR -p NOM_BASE < config/schema.sql
~~~

Vous pouvez également importer config/schema.sql avec phpMyAdmin.

### 3. Configurer l'application

Copiez config/config.example.php vers config/config.php, puis renseignez DB_HOST, DB_USER, DB_PASS et DB_NAME.

Si Turnstile est activé, renseignez les clés Cloudflare. Pour un développement local sans clés, désactivez cette option dans la configuration. Ne publiez jamais config/config.php ni ses secrets dans Git.

### 4. Préparer le stockage

Le processus PHP doit pouvoir écrire dans uploads/ et uploads/temp/. Depuis la racine du projet :

~~~bash
mkdir -p uploads/temp
chmod 755 uploads uploads/temp
~~~

Adaptez le propriétaire et les permissions à l'utilisateur du serveur web. Évitez les permissions globales d'écriture.

### 5. Configurer le serveur web

Faites pointer le domaine vers la racine du projet, activez HTTPS et vérifiez que le serveur applique les protections de .htaccess. Les fichiers déposés ne doivent pas être exécutables. Sous Nginx, bloquez également l'accès direct à uploads/, aux fichiers de configuration et aux scripts de maintenance.

### 6. Premier démarrage

Ouvrez le domaine, créez un compte puis testez un petit fichier privé et un fichier public. Vérifiez les pages Fichiers et Mes liens.

Pour le développement local avec le serveur PHP intégré :

~~~bash
php -S 127.0.0.1:8000 -t .
~~~

Ce serveur intégré est destiné au développement, pas à une mise en production publique.

## Mise à jour d'une installation existante

Avant la mise à jour, sauvegardez la base, config/config.php et le dossier uploads/. Déployez le code de dev sans écraser la configuration ni les fichiers déposés.

Pour mettre à jour une base existante, sélectionnez d'abord la bonne base et exécutez le fichier unique :

~~~bash
mysql -u UTILISATEUR -p NOM_BASE < config/migrate_all.sql
~~~

Cette migration vérifie chaque colonne et table avant de l'ajouter. Elle peut donc être relancée sans dupliquer les éléments déjà présents. Elle couvre la visibilité des fichiers, les protections des liens, les journaux d'aperçu et la limitation des essais de mot de passe.

Avec phpMyAdmin, sélectionnez la base puis importez config/migrate_all.sql. Ne lancez pas simultanément les anciennes migrations individuelles.

Une nouvelle installation importe config/schema.sql ; elle n'a pas besoin d'exécuter migrate_all.sql.


## Utilisation

### Fichiers et dossiers

1. Créez un compte puis ouvrez Upload.
2. Sélectionnez les fichiers, leur dossier de destination et leur visibilité.
3. Un fichier privé est visible par son propriétaire et les administrateurs. Un fichier public peut être consulté et téléchargé sans compte.
4. Dans Fichiers, vous pouvez ouvrir des dossiers, prévisualiser, télécharger, changer la visibilité, déplacer ou supprimer vos fichiers.

### Liens de partage

1. Depuis Fichiers, choisissez l'action de partage sur un fichier.
2. Choisissez la durée, éventuellement un mot de passe d'au moins 8 caractères et une limite de téléchargements, ou Illimité.
3. Copiez le lien et envoyez le mot de passe par un autre canal.
4. Les visiteurs peuvent prévisualiser les formats pris en charge. Les aperçus sont journalisés séparément et ne consomment pas le quota de téléchargements.
5. Ouvrez Mes liens pour consulter le temps restant, changer la durée ou la protection, consulter l'historique et révoquer le lien.

Un lien est un secret : toute personne qui le possède peut l'utiliser tant qu'il n'est pas expiré ou révoqué. La limite compte les téléchargements réussis ; elle n'empêche pas la prévisualisation.

### Visibilité des dossiers

Un autre utilisateur voit un dossier seulement s'il contient au moins un fichier public. Il voit alors les fichiers publics du dossier, pas les fichiers privés des autres utilisateurs. Le nom du créateur du dossier est affiché.

## Cloudflare et adresses IP

Quand la connexion directe vient d'une plage officielle Cloudflare, CloudDrop utilise l'en-tête CF-Connecting-IP après validation de l'adresse du proxy. Si le pair réseau n'est pas dans une plage Cloudflare connue, l'application ignore cet en-tête et utilise REMOTE_ADDR, pour ne pas faire confiance à un en-tête falsifiable.

Les plages IPv4 et IPv6 sont configurées dans config/init.php. Vérifiez-les périodiquement sur les listes officielles : https://www.cloudflare.com/ips-v4 et https://www.cloudflare.com/ips-v6. Pour masquer l'IP de l'origine, bloquez les connexions directes au serveur dans le pare-feu ou le panneau d'hébergement ; un proxy seul ne suffit pas si l'origine reste joignable.

## Confidentialité et maintenance

La page Informations légales décrit les traitements actuellement connus. Remplacez-y tous les champs « à compléter » avant l'ouverture au public. L'identité de l'éditeur, son contact et l'hébergeur doivent être renseignés.

Les journaux d'accès aux liens incluent l'adresse IP et le navigateur. La tâche de maintenance purge les journaux applicatifs après 180 jours et les échecs de mot de passe après 24 heures. Configurez son lancement quotidien avec cron :

~~~cron
0 3 * * * /usr/bin/php /CHEMIN/ABSOLU/CloudDrop/php/maintenance/purge_logs.php
~~~

Remplacez les chemins par ceux de votre hébergeur. Sur cPanel, créez une tâche cron quotidienne. La suppression d'un lien supprime aussi son historique.

Sauvegardez régulièrement la base et les fichiers téléversés, et protégez ces sauvegardes.

## Compte administrateur

Après inscription, attribuez le rôle admin depuis une session SQL protégée :

~~~sql
UPDATE users SET role = 'admin' WHERE pseudo = 'votre_pseudo';
~~~

Déconnectez-vous puis reconnectez-vous pour actualiser le rôle enregistré dans la session.

## Dépannage

- Erreur de base : vérifiez la configuration, les identifiants MySQL et les droits de l'utilisateur.
- Upload refusé : vérifiez les droits d'écriture de uploads/ et uploads/temp/, les limites PHP et celles du proxy/hébergeur.
- Lien protégé inaccessible : vérifiez le mot de passe, l'échéance, le quota et l'application des migrations.
- Un lien expiré apparaît encore dans Mes liens : il reste listé pour permettre sa révocation ; son accès public est désactivé.
- Les journaux montrent l'IP Cloudflare : assurez-vous que la requête arrive via Cloudflare et que les plages du code sont à jour. Ne faites pas confiance aveuglément à X-Forwarded-For.
- Erreur 500 : consultez les journaux PHP, vérifiez les migrations et conservez config/config.php pendant le déploiement.

## Développement

- main : production.
- dev : intégration des changements.
- config/ : exemples de configuration, schéma et migrations.
- N'ajoutez aucun secret, configuration de production ou fichier téléversé au dépôt.
