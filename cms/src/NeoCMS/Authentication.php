<?php

namespace NeoCMS;

class Authentication
{
    private array $credentials;

    public function __construct(array $credentials)
    {
        $this->startSession();
        $this->credentials = $credentials;
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_set_cookie_params([
            'httponly' => true,
            'path' => '/',
            'samesite' => 'Lax',
            'secure' => $isHttps,
        ]);

        session_start();
    }

    /**
     * Logs a user into the CMS
     * @param $username
     * @param $password
     * @return bool
     */
    public function login($username, $password)
    {
        if ($this->credentialsAreValid((string) $username, (string) $password)) {
            session_regenerate_id(true);
            $_SESSION['loggedIn'] = true;
            $_SESSION['loggedInUser'] = $username;
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
            return true;
        }

        $_SESSION['loggedIn'] = false;
        return false;
    }

    /**
     * Checks if a user has logged in
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $_SESSION['loggedIn'] ?? false;
    }

    /**
     * Returns the user logged in
     * @return string
     */
    public function getLoggedInUser(): string
    {
        return $_SESSION['loggedInUser'] ?? 'Not logged in';
    }

    public function getCsrfToken(): string
    {
        if (empty($_SESSION['csrfToken'])) {
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrfToken'];
    }

    public function isValidCsrfToken(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($this->getCsrfToken(), $token);
    }

    /**
     * Destroy the session
     * @return void
     */
    public function logout()
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    private function credentialsAreValid(string $username, string $password): bool
    {
        $storedCredential = $this->credentials[$username] ?? null;
        if (!is_string($storedCredential) || $storedCredential === '') {
            return false;
        }

        $passwordInfo = password_get_info($storedCredential);
        if (($passwordInfo['algo'] ?? null) !== null) {
            return password_verify($password, $storedCredential);
        }

        return hash_equals($storedCredential, $password);
    }
}
