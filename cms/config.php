<?php
/*
 * Authentication stores the usernames and passwords for people to be able to use the CMS.
 * This will be used as well to write to the audit log once it has been implemented in code.
 */

$config = [
    'authentication' => [
        // Store password hashes generated with password_hash(...), not plaintext passwords.
        // Default password for this sample hash is: change-this-password
        'admin' => '$2y$10$.MiUOFPR.L9osXyKuUjqx.VeZeeD3bHrQrmPQAOzXSfWj.nene1ey',
    ],
    // Enable the auditing system
    'audit' => true,
    // Set to true if you do not want to see the welcome page and want to go straight to your site
    'skipWelcomePage' => false,
    // Show full URL of the page being edited on the control bar
    'showFullUrl' => true
];
