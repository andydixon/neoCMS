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
$authentication = new Authentication($config['authentication']??[]);
$logger = new Logger($config['audit']??true);

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
    <title>Login</title>
    <style>
        /* Basic styling for the login form */
        body {
            font-family: Arial, sans-serif;
            background-color: #eef1f5;
        }

        .login-container {
            width: 350px;
            margin: 100px auto;
            padding: 40px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .login-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333333;
        }

        .login-container label {
            display: block;
            margin-bottom: 8px;
            color: #555555;
        }

        .login-container input[type="text"],
        .login-container input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .login-container button {
            width: 100%;
            padding: 12px;
            background-color: #0066cc;
            border: none;
            border-radius: 4px;
            color: #ffffff;
            font-size: 16px;
            cursor: pointer;
        }

        .login-container button:hover {
            background-color: #005bb5;
        }

        .error-message {
            color: #ff4d4d;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="login-container">
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
    </form>
</div>

</body>
</html>