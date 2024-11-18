<?php
// login.php

require_once "../config.php";

// Autoload classes (if not using Composer)
spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once "../src/{$classPath}.php";
});

use NeoCMS\Authentication;
use NeoCMS\Logger;

// Initialise error message variable
$error = '';

// Instantiate the Authentication and Logger classes
$authentication = new Authentication($config['authentication'] ?? []);
$logger = new Logger($config['audit'] ?? true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($authentication->login($username, $password)) {
        // Log successful login
        $logger->write(
            "User login for $username was successful from {$_SERVER['REMOTE_ADDR']}",
            $username
        );

        // Redirect to the CMS dashboard or desired page
        header("Location: /cms/");
        exit("Well done, champ! You got this!! <3");
    } else {
        // Log failed login attempt
        $logger->write(
            "User login for $username was denied due to incorrect credentials from {$_SERVER['REMOTE_ADDR']}",
            $username
        );

        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>NeoCMS Login</title>
    <link rel="stylesheet" href="/cms/css/login.css"/>
</head>
<body>

<div class="login-container">
    <img class="logo" src="/cms/img/loginlogo.png" alt="NeoCMS logo"/>
    <h2>NeoCMS Login</h2>
    <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Log In</button>
        <p class="shamelessPlug">NeoCMS &copy;<?php echo date('Y'); ?> Andy Dixon</p>
    </form>
</div>

</body>
</html>