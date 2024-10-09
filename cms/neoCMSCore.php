<?php

class neoCMSCore
{

    public $auditEnabled = true;
    public $users = [];
    public $loggedIn = false;
    public $version = '2.0';
    private $loggedInUser = '';

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
    public function writeAudit($value)
    {
        if ($this->auditEnabled)
            file_put_contents(__DIR__ . "/logs/" . date('y-m-d-') . "audit.txt", date('Y-m-d H:i:s') . "\t" . $this->loggedInUser . "\t" . trim($value) . "\n", FILE_APPEND);
    }

    public function isLoggedIn()
    {
        return $this->loggedIn;
    }

    public function getLoggedinUser()
    {
        return $this->loggedInUser;
    }

    public function saveContent($uri, $content)
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