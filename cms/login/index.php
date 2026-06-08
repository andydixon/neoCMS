<?php
/** Login form and authentication request handler. */

// Load account configuration before constructing authentication services.
require_once "../config.php";

// Resolve NeoCMS classes without introducing a package-manager requirement.
spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once "../src/{$classPath}.php";
});

use NeoCMS\Authentication;
use NeoCMS\LoginRateLimiter;
use NeoCMS\Logger;
use NeoCMS\SecurityHeaders;

// Keep one escaped, user-facing error message for the rendered form.
$error = '';

// Authentication owns the session; the logger records successful and failed attempts.
$authentication = new Authentication($config['authentication'] ?? [], $config['roles'] ?? [], $config['security'] ?? []);
$logger = new Logger($config['audit'] ?? true, $config['security'] ?? []);
$dataDirectory = (string) ($config['dataDirectory'] ?? (__DIR__ . '/../data'));
$rateLimiter = new LoginRateLimiter($dataDirectory, $config['security'] ?? []);
SecurityHeaders::html(false, isset($config['security']['cookieSecure']) ? (bool) $config['security']['cookieSecure'] : null);

// GET displays the form, while POST validates its token and submitted credentials.
$maxLoginRequestBytes = 16 * 1024;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxLoginRequestBytes) {
    http_response_code(413);
    $error = 'Login request is too large';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!$authentication->isValidCsrfToken($csrfToken)) {
        http_response_code(400);
        $logger->write(
            'User login attempt rejected due to invalid CSRF token from ' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            'anonymous'
        );
        $error = "Invalid request token";
    } else {
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $address = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (strlen($username) > 128 || strlen($password) > 4096) {
            http_response_code(400);
            $error = 'Invalid username or password';
        } elseif (($retryAfter = $rateLimiter->retryAfter($address, $username)) > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            $logger->write("Login throttled from {$address}", $username ?: 'anonymous');
            $error = 'Too many login attempts. Please try again later.';
        } elseif ($authentication->login($username, $password)) {
            $rateLimiter->clear($address, $username);

            // Record the source address before entering the privileged interface.
            $logger->write(
                "User login for {$username} was successful from {$address}",
                $username
            );

            // Redirect after POST so browser refreshes do not resubmit credentials.
            header("Location: /cms/");
            exit;
        } else {
            $rateLimiter->recordFailure($address, $username);
            // Record denied attempts without logging the supplied password. Obviously.
            $logger->write(
                "User login for {$username} was denied due to incorrect credentials from {$address}",
                $username
            );

            $error = "Invalid username or password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-gb">
<head>
    <title>NeoCMS Login</title>
    <link rel="stylesheet" href="/cms/css/login.css"/>
</head>
<body>

<!-- The compact login card is intentionally independent of the heavier administration UI. -->
<div class="login-container">
    <img class="logo" src="/cms/img/loginlogo.png" alt="NeoCMS logo"/>
    <h2>NeoCMS Login</h2>
    <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <!-- CSRF protection applies to login too, preventing forced authentication state changes. -->
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($authentication->getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
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
