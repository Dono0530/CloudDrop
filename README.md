# ☁️ CloudDrop

Plateforme de partage de fichiers sécurisée — comme un Google Drive partagé entre utilisateurs.

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat&logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

## Fonctionnalités

### Upload
- Drag & drop multi-fichiers
- Upload par morceaux (chunks de 10 Mo) — fichiers jusqu'à 100 Go
- Progression temps réel avec débit et ETA
- Choix du dossier de destination
- Retry automatique en cas d'échec

### Fichiers
- Recherche, filtres par type/date/taille, pagination
- Dossiers partagés pour organiser les fichiers
- Déplacer des fichiers entre dossiers
- Aperçu : images, PDF, vidéo, audio, code/texte
- Partage par lien avec expiration (1h, 24h, 7j, 30j)

### Sécurité
- Hash bcrypt (cost 12) avec rehash automatique
- Tokens CSRF sur tous les formulaires
- Protection brute-force (5 tentatives → verrouillage 15 min)
- Sessions sécurisées (HttpOnly, SameSite, régénération d'ID)
- Blocage des extensions dangereuses (.exe, .php, etc.)
- Protection path traversal

### Interface
- Design glassmorphism avec gradients
- Mode clair/sombre auto-détecté
- Bootstrap 5 + Bootstrap Icons
- Animations fluides, responsive
- Toast notifications

### Administration
- Gestion des fichiers et utilisateurs
- Journal d'activité détaillé
- Statistiques globales
- Suppression de comptes

## Installation

### Prérequis
- PHP 8.2+
- MySQL 8.0+
- Apache avec mod_rewrite

### Étapes

```bash
# 1. Cloner
git clone https://github.com/votre-repo/clouddrop.git
cd clouddrop

# 2. Configurer
cp config/config.example.php config/config.php
# Éditer config/config.php avec vos paramètres DB

# 3. Créer la base de données
mysql -u root -p -e "CREATE DATABASE clouddrop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. Importer le schéma
mysql -u root -p clouddrop < config/schema.sql

# 5. Créer les dossiers
mkdir -p uploads/temp
chmod 755 uploads uploads/temp

# 6. Lancer
# Avec XAMPP : démarrer Apache, aller sur http://localhost/
# Avec PHP : php -S localhost:8000 -t . 
```

### Premier compte admin

Après inscription, rendez-vous manuellement dans la base :

```sql
UPDATE users SET role = 'admin' WHERE pseudo = 'votre_pseudo';
```

## Structure

```
clouddrop/
├── config/
│   ├── config.php              # Configuration (DB, sécurité, uploads)
│   ├── config.example.php      # Template de configuration
│   ├── Database.php            # Connexion PDO singleton
│   ├── init.php                # Sessions, sécurité, autoload
│   └── schema.sql              # Schéma complet de la BDD
├── includes/
│   ├── Auth.php                # Login, register, CSRF, sessions
│   └── FileManager.php         # CRUD fichiers, dossiers, partages
├── auth/
│   ├── login.php               # Page connexion
│   ├── register.php            # Page inscription
│   └── logout.php              # Déconnexion
├── admin/
│   ├── index.php               # Panel admin (fichiers, users, logs)
│   └── upload_chunk.php        # Réception des chunks d'upload
├── php/
│   ├── dashboard.php           # Tableau de bord utilisateur
│   ├── upload.php              # Upload avec drag & drop
│   ├── download.php            # Liste fichiers + dossiers + recherche
│   ├── download_file.php       # Téléchargement sécurisé
│   ├── preview.php             # Aperçu de fichiers
│   ├── preview_serve.php       # Service de fichiers pour aperçu
│   ├── profile.php             # Profil utilisateur
│   └── actions.php             # Handler POST (CRUD, partage, déplacer)
├── css/
│   └── modern.css              # Styles glassmorphism
├── js/
│   ├── theme.js                # Thème clair/sombre + toasts
│   └── upload.js               # Upload chunked avec queue parallèle
├── uploads/                    # Fichiers uploadés (git-ignoré)
├── index.php                   # Page d'accueil
├── .htaccess                   # Sécurité Apache
└── .gitignore
```

## API

L'upload utilise un endpoint JSON :

```
POST /admin/upload_chunk.php
Content-Type: multipart/form-data

chunk       : morceau du fichier (blob)
chunkIndex  : index du chunk (0, 1, 2...)
totalChunks : nombre total de chunks
fileName    : nom original du fichier
fileId      : identifiant unique (timestamp_random)
folder_id   : ID du dossier de destination (0 = racine)

Response: { "success": true, "complete": false, "chunkIndex": 0 }
```

## Licence

MIT
