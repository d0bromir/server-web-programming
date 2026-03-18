<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Auth – помощни функции за автентикация и авторизация.
 */
class Auth
{
    /** Проверява дали потребителят е влязъл в системата. */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /** Връща ID на текущия потребител или null. */
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /** Връща данните на текущия потребител от сесията. */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /** Връща ролята на текущия потребител (или 'guest'). */
    public static function role(): string
    {
        return $_SESSION['user']['role'] ?? 'guest';
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    // ── Guards ────────────────────────────────────────────────────────

    /**
     * Изисква влязъл потребител.
     * При неуспех пренасочва към /login.
     */
    public static function requireAuth(): void
    {
        if (!self::isLoggedIn()) {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '/';
            Request::redirect('/login');
        }
    }

    /**
     * Изисква конкретна роля.
     * При неуспех връща 403.
     */
    public static function requireRole(string $role): void
    {
        self::requireAuth();
        if (self::role() !== $role) {
            http_response_code(403);
            echo '<h1>403 – Нямате права за достъп</h1>';
            exit;
        }
    }

    // ── Login / Logout ────────────────────────────────────────────────

    /** Записва потребителя в сесията след успешен вход. */
    public static function login(array $user): void
    {
        // Предотвратяване на session fixation attack
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
    }

    /** Унищожава сесията. */
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
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}
