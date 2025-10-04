<?php
// Set headers for JSON response
header('Content-Type: application/json');

// 1. Get the raw POST data (the new server list from the browser)
$json_data = file_get_contents('php://input');
$new_config = json_decode($json_data, true);

// Basic validation: ensure we received a valid array under the 'servers' key
if ($new_config === null || !isset($new_config['servers']) || !is_array($new_config['servers'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid data format received.']);
    exit;
}

$servers_file = 'servers.json';

// 2. Encode the validated data back into a formatted JSON string
// JSON_PRETTY_PRINT makes the file readable
$json_to_save = json_encode($new_config, JSON_PRETTY_PRINT);

// 3. Attempt to write the data to servers.json
if (file_put_contents($servers_file, $json_to_save) !== false) {
    // Crucial: Set ownership for the file to be readable/writable by Apache
    // This assumes the PHP script runs as www-data and is in the same folder.
    if (fileowner($servers_file) !== posix_getpwnam('www-data')['uid']) {
        // Attempt to set ownership just in case it was created by another user
        @chown($servers_file, 'www-data');
        @chgrp($servers_file, 'www-data');
    }
    
    echo json_encode(['success' => true, 'message' => 'Server list saved successfully.']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to write to servers.json. Check file permissions (should be writable by Apache user).'
    ]);
}
?>
