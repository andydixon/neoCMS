<?php
// Allowed MIME types
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

// Check if a file has been uploaded
if (isset($_FILES['file'])) {
    $file = $_FILES['file'];

    // Validate the file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type.']);
        exit;
    }

    // Set the upload directory
    $upload_dir = '../uploads/';

    // Ensure the upload directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate a unique name for the image
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;

    // Set the target file path
    $target_file = $upload_dir . $filename;

    // Move the uploaded file to the target directory
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Return a JSON response with the image location
        $location = $target_file;
        echo json_encode(['location' => $location]);
    } else {
        // Return an error response
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move uploaded file.']);
    }
} else {
    // No file was uploaded
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded.']);
}