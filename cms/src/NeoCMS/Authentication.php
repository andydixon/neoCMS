<?php

namespace NeoCMS;

class Authentication
{
    private $credentials;

    public function __construct(array $credentials)
    {
        session_start();
        $this->credentials = $credentials;
    }

    /**
     * Logs a user into the CMS
     * @param $username
     * @param $password
     * @return bool
     */
    public function login($username, $password)
    {
        if (!empty($this->credentials[$username]) && $this->credentials[$username] === $password) {
            $_SESSION['loggedIn'] = true;
            $_SESSION['loggedInUser'] = $username;
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

    /**
     * Destroy the session
     * @return void
     */
    public function logout()
    {
        session_destroy();
    }
}
