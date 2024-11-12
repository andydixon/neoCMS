<?php
/*
 * Authentication stores the usernames and passwords for people to be able to use the CMS.
 * This will be used as well to write to the audit log once it has been implemented in code.
 */
$authentication = [
    "admin" => "password",
    "editor" => "password",
];

// Set to true if you do not want to see the welcome page and want to go straight to your site
$skipWelcomePage = false;

// Show full URL of the page being edited on the control bar
$showFullUrl = true;


/**
 * DO NOT CHANGE ANYTHING BENEATH THIS LINE - IT WILL BUGGER STUFF UP
 */
session_start();
require_once "neoCMSCore.php";

if (empty($_SESSION["core"])) {
    $_SESSION["core"] = new neoCMSCore();
    $_SESSION["core"]->users = $authentication;
}
if (!$_SESSION["core"]->isLoggedIn() && stripos($_SERVER['REQUEST_URI'], "/login") === FALSE) {
    header('Location: /cms/login');
    die("Redirecting to login..");
}


// str_starts_with compatibility for php 7
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle): bool
    {
        //str_starts_with(string $haystack, string $needle): bool

        $strlen_needle = mb_strlen($needle);
        if (mb_substr($haystack, 0, $strlen_needle) == $needle) {
            return true;
        }
        return false;
    }
}

//str_ends_with compatibility for php 7
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle): bool
    {
        //str_starts_with(string $haystack, string $needle): bool

        $strlen_needle = mb_strlen($needle);
        if (mb_substr($haystack, -$strlen_needle, $strlen_needle) == $needle) {
            return true;
        }
        return false;
    }
}

$_SESSION["core"]->version = '2.00';

