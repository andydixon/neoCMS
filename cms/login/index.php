<?php
/** Login form and authentication request handler. */

// Load account configuration before constructing authentication services.
require_once __DIR__ . '/../bootstrap.php';

use NeoCMS\Authentication;
use NeoCMS\LoginRateLimiter;
use NeoCMS\Logger;
use NeoCMS\SecurityHeaders;
use NeoCMS\UserStore;

// Keep one escaped, user-facing error message for the rendered form.
$error = '';

// Authentication owns the session; the logger records successful and failed attempts.
$authentication = new Authentication(...\NeoCMS\UserStore::authArgs($config));
$logger = new Logger($config['audit'] ?? true, $config['security'] ?? []);
$dataDirectory = (string) ($config['dataDirectory'] ?? (__DIR__ . '/../data'));
$rateLimiter = new LoginRateLimiter($dataDirectory, $config['security'] ?? []);
SecurityHeaders::html(false, isset($config['security']['cookieSecure']) ? (bool) $config['security']['cookieSecure'] : null);

// An invitation link (?invite=TOKEN) shows a set-password form instead of the login form.
$users = UserStore::fromConfig($config);
$invite = is_string($_POST['invite'] ?? null) ? $_POST['invite'] : (is_string($_GET['invite'] ?? null) ? $_GET['invite'] : '');
$invite = preg_match('/^[a-f0-9]{64}$/', $invite) ? $invite : '';

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
    } elseif ($invite !== '' && isset($_POST['invite'])) {
        $address = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
        if (($retryAfter = $rateLimiter->retryAfter($address, 'invite')) > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            $error = 'Too many attempts. Please try again later.';
        } elseif (!hash_equals($password, $confirm)) {
            $error = 'The passwords do not match';
        } else {
            try {
                $username = $users->acceptInvite($invite, $password);
                $rateLimiter->clear($address, 'invite');
                $authentication = new Authentication(...UserStore::authArgs($config));
                $authentication->login($username, $password);
                $logger->write("Invitation accepted for {$username} from {$address}", $username);
                header('Location: ' . $config['basePath'] . '/cms/');
                exit;
            } catch (\RuntimeException $exception) {
                $rateLimiter->recordFailure($address, 'invite');
                $logger->write('Invitation rejected from ' . $address, 'anonymous');
                $error = $exception->getMessage();
            }
        }
    } else {
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $address = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (strlen($username) > 128 || strlen($password) > 4096) {
            http_response_code(400);
            $error = 'Invalid login name or password';
        } elseif (($retryAfter = $rateLimiter->retryAfter($address, $username)) > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            $logger->write("Login throttled from {$address}", $username ?: 'anonymous');
            $error = 'Too many login attempts. Please try again later.';
        } elseif ($authentication->login($username, $password)) {
            $rateLimiter->clear($address, $username);
            $users->rehashIfNeeded($username, $password);

            // Record the source address before entering the privileged interface.
            $logger->write(
                "User login for {$username} was successful from {$address}",
                $username
            );

            // Redirect after POST so browser refreshes do not resubmit credentials.
            header("Location: " . $config['basePath'] . "/cms/");
            exit;
        } else {
            $rateLimiter->recordFailure($address, $username);
            // Record denied attempts without logging the supplied password. Obviously.
            $logger->write(
                "User login for {$username} was denied due to incorrect credentials from {$address}",
                $username
            );

            $error = "Invalid login name or password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en-gb">
<head>
    <title>NeoCMS Login</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($config['basePath'], ENT_QUOTES, 'UTF-8'); ?>/cms/css/login.css"/>
</head>
<body>

<!-- The compact login card is intentionally independent of the heavier administration UI. -->
<div class="login-container">
    <img class="logo" src="<?php echo htmlspecialchars($config['basePath'], ENT_QUOTES, 'UTF-8'); ?>/cms/img/loginlogo.png" alt="NeoCMS logo"/>
    <h2><?php echo $invite !== '' ? 'Set your password' : 'NeoCMS Login'; ?></h2>
    <?php if (!empty($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <!-- CSRF protection applies to login too, preventing forced authentication state changes. -->
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($authentication->getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($invite !== ''): ?>
        <input type="hidden" name="invite" value="<?php echo htmlspecialchars($invite, ENT_QUOTES, 'UTF-8'); ?>">
        <label for="password">New password (12 to 72 characters)</label>
        <input type="password" id="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password" autofocus>

        <label for="confirm">Confirm password</label>
        <input type="password" id="confirm" name="confirm" required minlength="12" maxlength="72" autocomplete="new-password">

        <button type="submit">Set password and sign in</button>
        <?php else: ?>
        <label for="username">Login name</label>
        <input type="text" id="username" name="username" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit">Log In</button>
        <?php endif; ?>
        <p class="shamelessPlug">NeoCMS &copy;<?php echo date('Y'); ?> Andy Dixon</p>
    </form>
</div>

</body>
</html>
