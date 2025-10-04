<?php
// get_server_info.php

// Define constants
const CONFIG_FILE = 'servers.json';
const WAN_CHECK_HOST = '8.8.8.8'; // Google DNS for ICMP
const DEFAULT_TIMEOUT = 0.3; // 0.3 second ping/port timeout

// --- Custom Sorting Logic ---
function compareServers($a, $b) {
    // Get category values, defaulting to '99' if the field is missing
    $catA = isset($a['category']) ? (int)$a['category'] : 99;
    $catB = isset($b['category']) ? (int)$b['category'] : 99;

    // Primary sort: sort numerically by category (lower number comes first)
    if ($catA != $catB) {
        return ($catA < $catB) ? -1 : 1;
    }

    // Secondary sort: sort alphabetically by name if categories are the same
    return strcmp($a['name'], $b['name']);
}
// ----------------------------

// Function to safely read JSON config
function loadConfig() {
    if (!file_exists(CONFIG_FILE)) {
        return ['servers' => []];
    }
    $content = file_get_contents(CONFIG_FILE);
    $config = json_decode($content, true);
    return $config ?: ['servers' => []];
}

// Function to check port status (TCP check)
function checkPortStatus($host, $port) {
    $fp = @fsockopen($host, $port, $errno, $errstr, DEFAULT_TIMEOUT);
    if ($fp) {
        fclose($fp);
        return 'Online';
    }
    return 'Offline';
}

// MODIFIED: Accepts $checkType parameter and uses simple string matching
function checkHostStatus($hostname, $checkType = 'default') {
    $escapedHostname = escapeshellarg($hostname);
    $returnCode = 1;

    // --- FLEXIBLE CHECK LOGIC ---
    // If the check type is set to 'reliable_tcp', use a TCP Port 80 check
    if ($checkType === 'reliable_tcp') {
        return checkPortStatus($hostname, 80);
    }

    // --- DEFAULT PING LOGIC (for 'default' checkType or any unrecognised type) ---
    // -c 3: send 3 packets (optimized for local network)
    // -W 0.3: wait 0.3 second (optimized for local network)
    exec("ping -c 3 -W " . DEFAULT_TIMEOUT . " " . $escapedHostname . " 2>&1", $output, $returnCode);

    return $returnCode === 0 ? 'Online' : 'Offline';
}

// Function to resolve hostname to IP (used for port checks)
function resolveHostname($hostname) {
    $ip = gethostbyname($hostname);
    return ($ip !== $hostname) ? $ip : null;
}

/**
 * Perform a timed HTTP request via CURL for latency and speed measurement.
 * @param string $url The URL to test.
 * @return array Contains latency_ms, download_mbps, and error status.
 */
function timedCurlRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10s total timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Important for HTTPS targets like Google

    $start = microtime(true);
    $content = curl_exec($ch);
    $total_time = microtime(true) - $start;

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $download_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    $connect_time = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
    $curl_error = curl_error($ch);

    curl_close($ch);

    if ($curl_error) {
        return [
            'latency_ms' => -1,
            'download_mbps' => 0,
            'error' => "Curl Error: " . $curl_error
        ];
    }

    if ($http_code != 200 && $http_code != 302 && $http_code != 301) {
        return [
            'latency_ms' => -1,
            'download_mbps' => 0,
            'error' => "HTTP Code $http_code: Target server unresponsive or file not found."
        ];
    }

    // Calculate speed (Mbit/s)
    $download_mbps = ($download_size > 0 && $total_time > 0)
    ? (($download_size * 8) / (1024 * 1024)) / $total_time
    : 0;

    // Latency from the DNS lookup + TCP handshake
    $latency_ms = round($connect_time * 1000);

    return [
        'latency_ms' => $latency_ms > 0 ? $latency_ms : round($total_time * 1000), // Fallback to total time
        'download_mbps' => $download_mbps,
        'error' => null
    ];
}

// --- Simplified Ping/Speed Test Endpoint (Internet Health Panel) ---
if (isset($_GET['simplified_ping']) && $_GET['simplified_ping'] === 'true') {
    // 1. Try a large file (OVH 10MB) for accurate download speed
    $result = timedCurlRequest('http://proof.ovh.net/files/10Mb.dat');

    // 2. If the speed test is broken, use a fast/reliable target (Google robots.txt) for latency only
    if ($result['error'] || $result['download_mbps'] < 0.1) {
        $latency_only = timedCurlRequest('https://www.google.com/robots.txt');
        $result['latency_ms'] = $latency_only['latency_ms'];
        // Keep the speed result 0 and update the error message to reflect the fallback
        if ($latency_only['error'] === null) {
            $result['error'] = "Download speed test bypassed. Latency check successful.";
        }
    }

    if ($result['latency_ms'] === -1) {
        http_response_code(500);
        echo json_encode(['error' => $result['error'] ?: 'Network test failed.']);
        exit;
    }

    echo json_encode($result);
    exit;
}
// --- END Simplified Ping/Speed Test Endpoint ---

// --- START Dedicated Local Network Speed Test Endpoints (Top-Right Panel) ---
if (isset($_GET['speed_test'])) {
    if ($_GET['speed_test'] === 'download') {
        // Handle Download Speed Test
        $size_mb = (int)($_GET['size'] ?? 1); // Default to 1MB
        $bytes = $size_mb * 1024 * 1024;

        // Output headers to force download and set content type
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . $bytes);

        // Prevent caching
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');

        // Output a large, repeating string of data
        // We use str_repeat for consistency and speed in a local test
        echo str_repeat('x', $bytes);
        exit;
    }

    if ($_GET['speed_test'] === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle Upload Speed Test
        // Read the POST data (the client-side script will calculate time based on transfer completion)
        $data = file_get_contents('php://input');
        $upload_bytes = strlen($data);

        // Respond with success and the size received
        echo json_encode(['success' => true, 'upload_bytes' => $upload_bytes]);
        exit;
    }
}
// --- END Dedicated Local Network Speed Test Endpoints ---


// --- Main Server Status Logic ---
try {
    // 1. Load configuration
    $config = loadConfig();
    $results = [];

    // 2. Sort servers by category
    usort($config['servers'], 'compareServers');

    // 3. Process each server
    foreach ($config['servers'] as $server) {
        $hostname = $server['hostname'];
        $name = $server['name'];
        $icon = $server['icon'] ?? '🖥️';
        $category = $server['category'] ?? '99';
        // Get the check type, defaulting to 'default' if not set
        $checkType = $server['check_type'] ?? 'default';

        // 4. Resolve IP (if hostname is used)
        $ipAddress = resolveHostname($hostname);

        // 5. Check host status - passing the checkType determines the method used
        $status = checkHostStatus($hostname, $checkType);

        $services = [];

        if ($status === 'Online') {
            // Use the resolved IP for port checks if available, otherwise use original hostname
            $portCheckHost = $ipAddress ?: $hostname;
            $services['HTTP (80)'] = checkPortStatus($portCheckHost, 80);
            $services['SSH (22)'] = checkPortStatus($portCheckHost, 22);
            $services['Plex (32400)'] = checkPortStatus($portCheckHost, 32400);
        }

        // Format the 'ip' field to 'IP : Hostname' for the front end
        if ($ipAddress) {
            $displayAddress = "$ipAddress : $hostname";
        } else {
            // If resolution fails, only send the hostname
            $displayAddress = $hostname;
        }

        $results[] = [
            'name' => $name,
            'ip' => $displayAddress,
            'status' => $status,
            'icon' => $icon,
            'category' => $category,
            'services' => $services
        ];
    }

    // 6. Check WAN Status (Internet Connectivity) - defaults to 'default' check
    $wanStatus = checkHostStatus(WAN_CHECK_HOST);

    // 7. Return Final Results
    echo json_encode([
        'success' => true,
        'wan_status' => $wanStatus,
        'servers' => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server Error: ' . $e->getMessage()
    ]);
}
?>
