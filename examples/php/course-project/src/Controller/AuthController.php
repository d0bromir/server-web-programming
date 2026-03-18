<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Model\User;

/**
 * AuthController – вход, регистрация, изход.
 */
class AuthController
{
    // ── Login ─────────────────────────────────────────────────────────

    public function loginForm(array $params = []): void
    {
        if (Auth::isLoggedIn()) {
            Request::redirect('/');
        }
        require dirname(__DIR__, 2) . '/views/login.php';
    }

    public function login(array $params = []): void
    {
        if (Auth::isLoggedIn()) {
            Request::redirect('/');
        }

        Request::verifyCsrf();

        $email    = Request::post('email');
        $password = Request::post('password');
        $errors   = [];

        if (empty($email)) {
            $errors['email'] = 'Имейлът е задължителен.';
        }
        if (empty($password)) {
            $errors['password'] = 'Паролата е задължителна.';
        }

        if (empty($errors)) {
            $userModel = new User();
            $user      = $userModel->authenticate($email, $password);

            if ($user === null) {
                $errors['general'] = 'Невалиден имейл или парола.';
            } else {
                Auth::login($user);

                $intended = $_SESSION['intended'] ?? '/';
                unset($_SESSION['intended']);
                Request::redirect($intended);
            }
        }

        require dirname(__DIR__, 2) . '/views/login.php';
    }

    // ── Register ──────────────────────────────────────────────────────

    public function registerForm(array $params = []): void
    {
        if (Auth::isLoggedIn()) {
            Request::redirect('/');
        }
        require dirname(__DIR__, 2) . '/views/register.php';
    }

    public function register(array $params = []): void
    {
        if (Auth::isLoggedIn()) {
            Request::redirect('/');
        }

        Request::verifyCsrf();

        $name      = Request::post('name');
        $email     = Request::post('email');
        $password  = Request::post('password');
        $password2 = Request::post('password_confirm');
        $errors    = [];

        // Валидация
        if (empty(trim($name))) {
            $errors['name'] = 'Името е задължително.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'Името трябва да е поне 2 символа.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Невалиден имейл адрес.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Паролата трябва да е поне 8 символа.';
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors['password'] = 'Паролата трябва да съдържа главна буква и цифра.';
        }

        if ($password !== $password2) {
            $errors['password_confirm'] = 'Паролите не съвпадат.';
        }

        if (empty($errors)) {
            $userModel = new User();
            if ($userModel->emailExists($email)) {
                $errors['email'] = 'Имейлът вече е регистриран.';
            }
        }

        if (empty($errors)) {
            $userModel = new User();
            $userId    = $userModel->create($name, $email, $password);

            // Автоматичен вход след регистрация
            $user = $userModel->findById($userId);
            Auth::login($user);

            Request::flash('success', 'Добре дошли, ' . htmlspecialchars($name) . '!');
            Request::redirect('/venues');
        }

        require dirname(__DIR__, 2) . '/views/register.php';
    }

    // ── Logout ────────────────────────────────────────────────────────

    public function logout(array $params = []): void
    {
        Request::verifyCsrf();
        Auth::logout();
        Request::redirect('/');
    }
}
