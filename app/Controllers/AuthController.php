<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\StreamToken;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) redirect('/dashboard');
        echo view('auth/login', [
            'error' => flash('error'),
            'email' => old('email', ''),
        ]);
    }

    public function login(): void
    {
        Csrf::verifyOrFail();

        $email = trim((string) ($_POST['email'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');

        // Throttle: more than 5 failed attempts from this IP in last 15 min -> reject
        $recent = (int) Database::scalar(
            "SELECT COUNT(*) FROM failed_logins WHERE ip_address = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)",
            [client_ip()]
        );
        if ($recent >= 5) {
            flash('error', 'Too many failed login attempts. Please try again later.');
            $_SESSION['__old']['email'] = $email;
            redirect('/login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') {
            flash('error', 'Please enter a valid email and password.');
            $_SESSION['__old']['email'] = $email;
            redirect('/login');
        }

        if (!Auth::attempt($email, $pass)) {
            flash('error', 'Invalid credentials.');
            $_SESSION['__old']['email'] = $email;
            redirect('/login');
        }

        unset($_SESSION['__old']);
        redirect('/dashboard');
    }

    public function logout(): void
    {
        Csrf::verifyOrFail();
        if (Auth::id()) StreamToken::revokeForUser(Auth::id());
        Auth::logout();
        redirect('/login');
    }
}
