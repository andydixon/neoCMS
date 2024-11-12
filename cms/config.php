<?php
/*
 * Authentication stores the usernames and passwords for people to be able to use the CMS.
 * This will be used as well to write to the audit log once it has been implemented in code.
 */

$config = [
    'authentication' => [
        // Add more users as needed - its 'username'=>'password' if you hadn't have guessed
        'admin' => 'password123',
    ],
    // Enable the auditing system
    'audit' => true,
    // Set to true if you do not want to see the welcome page and want to go straight to your site
    'skipWelcomePage' => false,
    // Show full URL of the page being edited on the control bar
    'showFullUrl' => true
];
