<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Request – помощни функции за HTTP заявки, CSRF и redirect.
 */
class Request
{
    // ── Input helpers ─────────────────────────────────────────────────

    /** Prочита стойност от POST (по подразбиране) или GET заявката. */
    public static function input(string $key, string $default = ''): string
    {
        return trim($_POST[$key] ?? $_GET[$key] ?? $default);
    }

    public static function post(string $key, string $default = ''): string
    {
        return trim($_POST[$key] ?? $default);
    }

    public static function query(string $key, string $default = ''): string
    {
        return trim($_GET[$key] ?? $default);
    }

    public static function queryInt(string $key, int $default = 0): int
    {
        $val = $_GET[$key] ?? null;
        return ($val !== null && ctype_digit($val)) ? (int) $val : $default;
    }

    /** Проверява дали заявката е AJAX / fetch (чака JSON). */
    public static function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json');
    }

    /** Декодира JSON тялото на заявката (за REST API). */
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    // ── CSRF ──────────────────────────────────────────────────────────

    /** Генерира или връща вече съществуващ CSRF токен за сесията. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** HTML hidden поле за CSRF – вмъква се в ВСЯКА форма. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::csrfToken() . '">';
    }

    /**
     * Проверява CSRF токена.
     * При неуспех прекратява изпълнението с 403.
     */
    public static function verifyCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? '';

        if (
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $token)
        ) {
            http_response_code(403);
            exit('Невалиден CSRF токен.');
        }
    }

    // ── Redirect ──────────────────────────────────────────────────────

    public static function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    // ── Flash messages ────────────────────────────────────────────────

    public static function flash(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string
    {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }

    // ── Bearer Token (за API) ─────────────────────────────────────────

    /** Извлича Bearer токен от Authorization хедъра. */
    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }
}
