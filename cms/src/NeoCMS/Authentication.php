<?php

namespace NeoCMS;

/**
 * Manages session authentication, role capabilities, and CSRF tokens.
 *
 * Credentials remain configuration-backed to preserve NeoCMS's database-free design. Only hashes
 * created by password_hash() are accepted; plaintext credentials are rejected.
 */
class Authentication
{
    /** Valid bcrypt hash used solely to equalise unknown-user password verification work. */
    private const DUMMY_PASSWORD_HASH = '$2y$10$NaiFDtsxcQzcxAiQrTmEPuDde8sDsdpjAlSgoiYwAvdnF5Cwytute';

    /** Map of usernames to password hashes. */
    private array $credentials;

    /** Map of usernames to editor, publisher, or administrator roles. */
    private array $roles;

    /** Maximum permitted inactivity for an authenticated session. */
    private int $idleTimeout;

    /** Maximum total lifetime of an authenticated session. */
    private int $absoluteTimeout;

    /** Optional explicit Secure-cookie setting for TLS-terminating reverse proxies. */
    private ?bool $cookieSecure;

    /** Initialise secure session handling and retain the configured account maps. */
    public function __construct(array $credentials, array $roles = [], array $sessionOptions = [])
    {
        $this->credentials = $credentials;
        $this->roles = $roles;
        $this->idleTimeout = max(300, (int) ($sessionOptions['idleTimeout'] ?? 1800));
        $this->absoluteTimeout = max($this->idleTimeout, (int) ($sessionOptions['absoluteTimeout'] ?? 43200));
        $this->cookieSecure = isset($sessionOptions['cookieSecure']) ? (bool) $sessionOptions['cookieSecure'] : null;
        $this->startSession();
        $this->enforceSessionLifetime();
    }

    /**
     * Start the PHP session with conservative cookie settings.
     *
     * HTTPS cookies are marked Secure when the request is encrypted. SameSite=Lax protects common
     * cross-site request scenarios without making ordinary login redirects needlessly dramatic.
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = $this->cookieSecure ?? (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        );

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_name('NEOCMSSESSID');

        session_set_cookie_params([
            'httponly' => true,
            'path' => '/cms',
            'samesite' => 'Lax',
            'secure' => $isHttps,
        ]);

        session_start();
    }

    /**
     * Authenticate a user and establish a fresh privileged session.
     *
     * @param mixed $username Submitted account name.
     * @param mixed $password Submitted plaintext password.
     */
    public function login($username, $password): bool
    {
        $username = (string) $username;
        if ($this->credentialsAreValid($username, (string) $password)) {
            // Rotate the identifier at the privilege boundary to prevent session fixation.
            session_regenerate_id(true);
            $_SESSION['loggedIn'] = true;
            $_SESSION['loggedInUser'] = $username;
            $configuredRole = $this->roles[$username] ?? 'editor';
            $_SESSION['role'] = in_array($configuredRole, ['editor', 'publisher', 'administrator'], true)
                ? $configuredRole
                : 'editor';
            $_SESSION['credentialFingerprint'] = hash('sha256', (string) $this->credentials[$username]);
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
            $_SESSION['authenticatedAt'] = time();
            $_SESSION['lastActivityAt'] = time();
            $_SESSION['lastRegeneratedAt'] = time();
            return true;
        }

        unset(
            $_SESSION['loggedIn'],
            $_SESSION['loggedInUser'],
            $_SESSION['role'],
            $_SESSION['credentialFingerprint'],
            $_SESSION['authenticatedAt'],
            $_SESSION['lastActivityAt'],
            $_SESSION['lastRegeneratedAt']
        );
        return false;
    }

    /** Return whether the current session belongs to an authenticated user. */
    public function isLoggedIn(): bool
    {
        return $_SESSION['loggedIn'] ?? false;
    }

    /** Return the current username, or a safe label when no user is authenticated. */
    public function getLoggedInUser(): string
    {
        return $_SESSION['loggedInUser'] ?? 'Not logged in';
    }

    /** Return the session role, defaulting unauthorised sessions to the least powerful role. */
    public function getRole(): string
    {
        $username = $this->getLoggedInUser();
        $configuredRole = $this->roles[$username] ?? 'editor';
        $role = in_array($configuredRole, ['editor', 'publisher', 'administrator'], true)
            ? $configuredRole
            : 'editor';
        $_SESSION['role'] = $role;
        return $role;
    }

    /**
     * Check whether the current role includes a named capability.
     *
     * Capability checks live here so controllers and upload handlers share one policy table.
     */
    public function can(string $capability): bool
    {
        $permissions = [
            'editor' => ['draft', 'upload'],
            'publisher' => ['draft', 'upload', 'publish', 'schedule'],
            'administrator' => ['draft', 'upload', 'publish', 'schedule', 'manage'],
        ];

        return in_array($capability, $permissions[$this->getRole()] ?? [], true);
    }

    /** Return the session's CSRF token, generating a cryptographically random token when absent. */
    public function getCsrfToken(): string
    {
        if (empty($_SESSION['csrfToken'])) {
            $_SESSION['csrfToken'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrfToken'];
    }

    /** Compare a submitted CSRF token in constant time to avoid leaking useful timing information. */
    public function isValidCsrfToken(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($this->getCsrfToken(), $token);
    }

    /** Clear session data, expire its cookie, and destroy the server-side session. */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
    }

    /**
     * Validate submitted credentials against a password_hash() value.
     */
    private function credentialsAreValid(string $username, string $password): bool
    {
        $storedCredential = $this->credentials[$username] ?? null;
        $passwordInfo = is_string($storedCredential) ? password_get_info($storedCredential) : [];
        $configuredHashIsValid = is_string($storedCredential)
            && $storedCredential !== ''
            && ($passwordInfo['algoName'] ?? 'unknown') !== 'unknown';
        $verificationHash = $configuredHashIsValid ? $storedCredential : self::DUMMY_PASSWORD_HASH;
        $verified = password_verify($password, $verificationHash);
        return $configuredHashIsValid && $verified;
    }

    /** Expire stale sessions and periodically rotate active session identifiers. */
    private function enforceSessionLifetime(): void
    {
        if (empty($_SESSION['loggedIn'])) {
            return;
        }

        $now = time();
        $username = is_string($_SESSION['loggedInUser'] ?? null) ? $_SESSION['loggedInUser'] : '';
        $storedCredential = $this->credentials[$username] ?? null;
        $passwordInfo = is_string($storedCredential) ? password_get_info($storedCredential) : [];
        if (!is_string($storedCredential) || ($passwordInfo['algoName'] ?? 'unknown') === 'unknown') {
            $this->invalidateSession();
            return;
        }
        $fingerprint = hash('sha256', $storedCredential);
        if (isset($_SESSION['credentialFingerprint']) && !hash_equals((string) $_SESSION['credentialFingerprint'], $fingerprint)) {
            $this->invalidateSession();
            return;
        }
        $_SESSION['credentialFingerprint'] = $fingerprint;

        $_SESSION['authenticatedAt'] = (int) ($_SESSION['authenticatedAt'] ?? $now);
        $_SESSION['lastActivityAt'] = (int) ($_SESSION['lastActivityAt'] ?? $now);
        $_SESSION['lastRegeneratedAt'] = (int) ($_SESSION['lastRegeneratedAt'] ?? $now);

        $idleExpired = $now - $_SESSION['lastActivityAt'] > $this->idleTimeout;
        $absoluteExpired = $now - $_SESSION['authenticatedAt'] > $this->absoluteTimeout;
        if ($idleExpired || $absoluteExpired) {
            $this->invalidateSession();
            return;
        }

        if ($now - $_SESSION['lastRegeneratedAt'] > 900) {
            session_regenerate_id(true);
            $_SESSION['lastRegeneratedAt'] = $now;
        }
        $_SESSION['lastActivityAt'] = $now;
    }

    /** Clear privileged session state and rotate the identifier after expiry or account revocation. */
    private function invalidateSession(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
