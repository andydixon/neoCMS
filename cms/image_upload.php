<?php
/**
 * Authenticated TinyMCE image-upload endpoint.
 *
 * Files are checked by byte size, detected MIME type, and image decoding before receiving a random
 * server filename. Trusting only the browser-supplied extension would be admirably optimistic.
 */

// Load credentials, role assignments, and shared application settings.
require_once "config.php";

// Resolve project classes without requiring Composer for this deliberately lightweight CMS.
spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once "./src/{$classPath}.php";
});

use NeoCMS\Authentication;
use NeoCMS\FileStore;
use NeoCMS\SecurityHeaders;

SecurityHeaders::json(isset($config['security']['cookieSecure']) ? (bool) $config['security']['cookieSecure'] : null);

// Authentication is required before any upload details are processed.
$authentication = new Authentication($config['authentication'] ?? [], $config['roles'] ?? [], $config['security'] ?? []);
if (!$authentication->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

// Editors may upload; unauthorised roles receive a clear forbidden response.
if (!$authentication->can('upload')) {
    http_response_code(403);
    echo json_encode(['error' => 'Your role cannot upload files']);
    exit;
}

// Uploads alter server state and therefore accept POST only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Accept the token as form data or a conventional request header.
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!$authentication->isValidCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Map explicitly supported image MIME types to controlled filename extensions.
$allowed_types = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

// Apply configured byte, dimension, pixel-count, file-count, and total-storage limits.
$uploadConfig = is_array($config['uploads'] ?? null) ? $config['uploads'] : [];
$max_file_size = max(1024, (int) ($uploadConfig['maxFileBytes'] ?? 10 * 1024 * 1024));
$maxWidth = max(1, (int) ($uploadConfig['maxWidth'] ?? 8192));
$maxHeight = max(1, (int) ($uploadConfig['maxHeight'] ?? 8192));
$maxPixels = max(1, (int) ($uploadConfig['maxPixels'] ?? 24 * 1024 * 1024));
$maxFiles = max(1, (int) ($uploadConfig['maxFiles'] ?? 2000));
$maxTotalBytes = max($max_file_size, (int) ($uploadConfig['maxTotalBytes'] ?? 500 * 1024 * 1024));
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $max_file_size + 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'Upload request is too large.']);
    exit;
}

// Continue only when PHP reports a complete upload.
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];

    // Enforce the application limit even if php.ini permits larger files.
    if ($file['size'] > $max_file_size) {
        http_response_code(400);
        echo json_encode(['error' => 'File size exceeds the maximum limit.']);
        exit;
    }

    // Inspect file bytes rather than trusting the user-controlled original filename.
    $finfo     = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type.']);
        exit;
    }

    // Confirm that an image decoder recognises the payload as an actual image.
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Uploaded file is not a valid image.']);
        exit;
    }
    [$width, $height] = $imageInfo;
    if ($width > $maxWidth || $height > $maxHeight || $width * $height > $maxPixels) {
        http_response_code(400);
        echo json_encode(['error' => 'Image dimensions exceed the configured limit.']);
        exit;
    }

    // Random names prevent collisions and avoid publishing the original local filename.
    $extension = $allowed_types[$mime_type];
    $filename  = bin2hex(random_bytes(16)) . '.' . $extension;

    // Create the public upload directory on a fresh installation.
    $uploadRoot = __DIR__ . '/../uploads';
    if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create upload directory.']);
        exit;
    }

    $upload_dir = realpath($uploadRoot);
    if ($upload_dir === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Upload directory is invalid.']);
        exit;
    }
    $upload_dir .= DIRECTORY_SEPARATOR;
    @chmod($upload_dir, 0755);

    // Build the final path from the canonical upload directory and generated safe filename.
    $target_file = $upload_dir . $filename;

    // Serialise quota checks and moves so concurrent uploads cannot race past the storage ceiling.
    $store = new FileStore((string) ($config['dataDirectory'] ?? (__DIR__ . '/data')));
    $lockPath = $store->directory('locks') . 'uploads.lock';
    $uploadLock = fopen($lockPath, 'c+');
    if ($uploadLock === false || !flock($uploadLock, LOCK_EX)) {
        if (is_resource($uploadLock)) {
            fclose($uploadLock);
        }
        http_response_code(503);
        echo json_encode(['error' => 'Upload service is temporarily unavailable.']);
        exit;
    }
    @chmod($lockPath, 0600);

    $existingFiles = array_values(array_filter(glob($upload_dir . '*') ?: [], 'is_file'));
    $existingBytes = array_sum(array_map(static fn(string $path): int => (int) filesize($path), $existingFiles));
    if (count($existingFiles) >= $maxFiles || $existingBytes + (int) $file['size'] > $maxTotalBytes) {
        flock($uploadLock, LOCK_UN);
        fclose($uploadLock);
        http_response_code(507);
        echo json_encode(['error' => 'Upload storage quota has been reached.']);
        exit;
    }

    // move_uploaded_file() additionally confirms that PHP received the source as an upload.
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Public images need read access but no executable or world-writable permissions.
        chmod($target_file, 0644);

        // Seed media metadata from the original basename; editors can improve the alt text later.
        $location = '/uploads/' . $filename;
        $metadata = $store->read('media');
        $originalBase = pathinfo((string) $file['name'], PATHINFO_FILENAME);
        $altText = substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $originalBase) ?? '', 0, 200);
        $metadata[$filename] = ['alt' => $altText];
        try {
            $store->write('media', $metadata);
        } catch (\Throwable $exception) {
            @unlink($target_file);
            flock($uploadLock, LOCK_UN);
            fclose($uploadLock);
            http_response_code(500);
            echo json_encode(['error' => 'Unable to record uploaded media.']);
            exit;
        }
        flock($uploadLock, LOCK_UN);
        fclose($uploadLock);
        echo json_encode(['location' => $location]);
    } else {
        flock($uploadLock, LOCK_UN);
        fclose($uploadLock);
        // A failed move is a server error because validation has already succeeded.
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move uploaded file.']);
    }
} else {
    // Translate PHP's numeric upload failures into messages an editor can act upon.
    $error_message = 'No file uploaded.';
    if (isset($_FILES['file']['error'])) {
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'File size exceeds the maximum limit.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'The uploaded file was only partially uploaded.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'No file was uploaded.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message = 'Missing a temporary folder.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message = 'Failed to write file to disk.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message = 'A PHP extension stopped the file upload.';
                break;
            default:
                $error_message = 'Unknown upload error.';
                break;
        }
    }
    http_response_code(400);
    echo json_encode(['error' => $error_message]);
}
