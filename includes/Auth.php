<?php
/**
 * CLASSE AUTH - Gestion de l'authentification
 * Login, register, logout, vérification de rôle
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../config/Database.php';

class Auth {

    /**
     * Vérifier si l'utilisateur est connecté
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Vérifier si l'utilisateur est admin
     */
    public static function isAdmin(): bool {
        return self::isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /**
     * Exiger une connexion (redirige si non connecté)
     */
    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            if (self::isAjax()) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Connexion requise']);
                exit;
            }
            header('Location: /auth/login.php');
            exit;
        }
    }

    /**
     * Exiger le rôle admin
     */
    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Accès interdit');
        }
    }

    /**
     * Connexion avec protection brute-force
     */
    public static function login(string $username, string $password): array {
        $db = Database::getInstance();

        // Vérifier le verrouillage
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $lockout = $db->fetchOne(
            "SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$ip, LOGIN_LOCKOUT_TIME]
        );

        if ($lockout && $lockout['attempts'] >= LOGIN_MAX_ATTEMPTS) {
            return ['success' => false, 'error' => 'Trop de tentatives. Réessayez dans 15 minutes.'];
        }

        // Chercher l'utilisateur
        $user = $db->fetchOne("SELECT id, pseudo, mdp, role FROM users WHERE pseudo = ?", [$username]);

        if (!$user || !password_verify($password, $user['mdp'])) {
            // Enregistrer la tentative échouée
            $db->execute("INSERT INTO login_attempts (ip_address, username, attempted_at) VALUES (?, ?, NOW())", [$ip, $username]);

            return ['success' => false, 'error' => 'Identifiants incorrects'];
        }

        // Mettre à jour le hash si nécessaire (upgrade bcrypt cost)
        if (password_needs_rehash($user['mdp'], PASSWORD_DEFAULT, ['cost' => BCRYPT_COST])) {
            $newHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]);
            $db->execute("UPDATE users SET mdp = ? WHERE id = ?", [$newHash, $user['id']]);
        }

        // Nettoyer les tentatives échouées
        $db->execute("DELETE FROM login_attempts WHERE ip_address = ?", [$ip]);

        // Créer la session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['pseudo'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_regeneration'] = time();

        // Logger la connexion
        $db->execute("INSERT INTO activity_log (user_id, action, ip_address, created_at) VALUES (?, 'login', ?, NOW())",
            [$user['id'], $ip]);

        return ['success' => true, 'user' => ['id' => $user['id'], 'pseudo' => $user['pseudo'], 'role' => $user['role']]];
    }

    /**
     * Inscription
     */
    public static function register(string $username, string $password, string $confirmPassword): array {
        $db = Database::getInstance();

        // Validations
        $username = trim($username);
        if (empty($username) || empty($password)) {
            return ['success' => false, 'error' => 'Tous les champs sont requis'];
        }
        if (strlen($username) < 3 || strlen($username) > 30) {
            return ['success' => false, 'error' => 'Le nom d\'utilisateur doit faire entre 3 et 30 caractères'];
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            return ['success' => false, 'error' => 'Caractères autorisés : lettres, chiffres, tirets et underscores'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Le mot de passe doit faire au moins 8 caractères'];
        }
        if ($password !== $confirmPassword) {
            return ['success' => false, 'error' => 'Les mots de passe ne correspondent pas'];
        }

        // Vérifier l'unicité
        $exists = $db->fetchOne("SELECT id FROM users WHERE pseudo = ?", [$username]);
        if ($exists) {
            return ['success' => false, 'error' => 'Ce nom d\'utilisateur est déjà pris'];
        }

        // Créer l'utilisateur
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => BCRYPT_COST]);
        $userId = $db->insert(
            "INSERT INTO users (pseudo, mdp, role, created_at) VALUES (?, ?, 'user', NOW())",
            [$username, $hashedPassword]
        );

        return ['success' => true, 'message' => 'Compte créé avec succès'];
    }

    /**
     * Déconnexion
     */
    public static function logout(): void {
        if (self::isLoggedIn()) {
            $db = Database::getInstance();
            $db->execute("INSERT INTO activity_log (user_id, action, ip_address, created_at) VALUES (?, 'logout', ?, NOW())",
                [$_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        }

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
        }
        session_destroy();
    }

    /**
     * Obtenir les infos de l'utilisateur connecté
     */
    public static function getUser(): ?array {
        if (!self::isLoggedIn()) return null;
        $db = Database::getInstance();
        return $db->fetchOne("SELECT id, pseudo, role, created_at FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }

    /**
     * Vérifier si la requête est AJAX
     */
    private static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Valider le token CSRF
     */
    public static function validateCsrf(string $token): bool {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }

    /**
     * Obtenir le token CSRF pour les formulaires
     */
    public static function csrfField(): string {
        return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $_SESSION[CSRF_TOKEN_NAME] . '">';
    }

    /**
     * Compter les tentatives de connexion récentes
     */
    public static function getRemainingAttempts(): int {
        $db = Database::getInstance();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $result = $db->fetchOne(
            "SELECT COUNT(*) as attempts FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$ip, LOGIN_LOCKOUT_TIME]
        );
        return max(0, LOGIN_MAX_ATTEMPTS - ($result['attempts'] ?? 0));
    }
}
