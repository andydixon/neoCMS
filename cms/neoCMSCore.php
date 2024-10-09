<?php

class neoCMSCore
{

    public bool $auditEnabled = true;
    public array $users = [];
    public bool $loggedIn = false;
    public string $version = '2.0';
    private string $loggedInUser = '';

    public function __construct()
    {

    }

    public function login($username, $password)
    {
        $this->loggedIn = (!empty($this->users[$username]) && $this->users[$username] == $password);
        if ($this->loggedIn) $this->loggedInUser = $username;
        $this->writeAudit("User login for " . $username . " was " . ($this->loggedIn ? "successful" : "denied due to incorrect credentials") . " from " . $_SERVER['REMOTE_ADDR']);
        return $this->loggedIn;
    }

    /**
     * Write an entry into the audit log
     * @param $value
     * @return void
     */
    public function writeAudit($value): void
    {
        if ($this->auditEnabled)
            file_put_contents(__DIR__ . "/logs/" . date('y-m-d-') . "audit.txt", date('Y-m-d H:i:s') . "\t" . $this->loggedInUser . "\t" . trim($value) . "\n", FILE_APPEND);
    }

    public function isLoggedIn()
    {
        return $this->loggedIn;
    }

    public function getLoggedinUser(): string
    {
        return $this->loggedInUser;
    }

    public function saveContent($uri, $content): void
    {
        $this->writeAudit("Content for " . $uri . " was saved.");
        $uri = str_replace("../", "./", $uri);

        //check to see if we're dealing with a default (index.htm{l}) page
        if (str_ends_with($uri, "/") || $uri == '/') {
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . $uri . 'index.htm')) {
                $uri = $uri . '/index.htm';
            } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . $uri . 'index.html')) {
                $uri = $uri . '/index.html';
            } else {
                die('Unknown default file:' . $uri);
            }
        }
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . $uri, $content);
    }
}