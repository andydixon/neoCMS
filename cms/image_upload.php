<?php
// upload.php

require_once "config.php";

// Autoload classes (if not using Composer)
spl_autoload_register(function ($class) {
    $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once "./src/{$classPath}.php";
});

use NeoCMS\Authentication;

// Ensure the user is authenticated
$authentication = new Authentication($config['authentication']??[]);
if (!$authentication->isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorised']);
    exit;
}

// Set appropriate headers
header('Content-Type: application/json');

// Allowed MIME types and extensions
$allowed_types = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'text/pdf'  => 'pdf',
    'text/docx'  => 'docx',
    'text/xlsx'  => 'xlsx',
    'text/doc' => 'doc',
];

// Maximum file size in bytes (50MB)
$max_file_size = 50 * 1024 * 1024;

// Check if a file has been uploaded
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];

    // Check file size
    if ($file['size'] > $max_file_size) {
        http_response_code(400);
        echo json_encode(['error' => 'File size exceeds the maximum limit.']);
        exit;
    }

    // Validate the file type
    $finfo     = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type.']);
        exit;
    }

    // Validate that the file is a real image
    if (getimagesize($file['tmp_name']) === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Uploaded file is not a valid image.']);
        exit;
    }

    // Generate a unique name for the image
    $extension = $allowed_types[$mime_type];
    $filename  = uniqid('', true) . '.' . $extension;

    // Set the upload directory
    $upload_dir = realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR;

    // Ensure the upload directory exists
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create upload directory.']);
        exit;
    }

    // Set the target file path
    $target_file = $upload_dir . $filename;

    // Move the uploaded file to the target directory
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Set file permissions
        chmod($target_file, 0644);

        // Return a JSON response with the image location
        $location = '/uploads/' . $filename;
        echo json_encode(['location' => $location]);
    } else {
        // Return an error response
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move uploaded file.']);
    }
} else {
    // Handle upload errors
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
