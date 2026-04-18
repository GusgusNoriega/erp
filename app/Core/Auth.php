<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    /** @return array{id: int, name: string, email: string, role: string}|null */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return $_SESSION['user'];
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function attempt(string $email, string $password): bool
    {
        $statement = Database::connection()->prepare('
            SELECT id, name, email, password_hash, role, active
            FROM users
            WHERE email = :email
            LIMIT 1
        ');
        $statement->execute(['email' => mb_strtolower(trim($email), 'UTF-8')]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user || (int) $user['active'] !== 1) {
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];

        $update = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $update->execute(['id' => (int) $user['id']]);

        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }

        header('Location: ' . url('/login'));
        exit;
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();

        $user = self::user();

        if (($user['role'] ?? '') === 'admin') {
            return;
        }

        http_response_code(403);
        View::render('errors/403', ['currentPath' => '/usuarios'], 'layouts/main');
        exit;
    }
}
