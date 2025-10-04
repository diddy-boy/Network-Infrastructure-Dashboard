<?php
header('Content-Type: application/json');
set_time_limit(300); // Set a longer time limit for the scan

// --- CONFIGURATION ---
// Get local network subnet (adjust this to match your network)
$subnet = '192.168.8'; // ------------------- IMPORTANT : EDIT THIS TO YOUR LOCAL NETWORK -----------------
// Reduced from 1 to 0.5 seconds for faster checks on unresponsive IPs
$timeout = 0.5; // Ping timeout in seconds
// ---------------------

$discoveredDevices = [];

// Optimized: Scan the dynamic IP range (100 to 255) as requested
$start_ip = 100;
$end_ip = 255;

for ($i = $start_ip; $i <= $end_ip; $i++) {
    $ip = "$subnet.$i";

    // Quick ping check (using exec with ping command)
    // -c 1: send 1 packet, -W $timeout: wait $timeout seconds for response
    $pingResult = exec("ping -c 1 -W $timeout $ip 2>&1", $output, $returnCode);

    if ($returnCode === 0) {
        // Device responded to ping

        // Try to get hostname
        $hostname = gethostbyaddr($ip);
        if ($hostname === $ip) {
            $hostname = null; // No hostname found
        }

        // Try to get MAC address (Linux/Unix)
        $mac = null;
        // Use arp to find the MAC address
        $arpOutput = shell_exec("arp -n $ip 2>/dev/null");
        if ($arpOutput && preg_match('/([0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2}:[0-9a-f]{2})/i', $arpOutput, $matches)) {
            $mac = strtoupper($matches[1]);
        }

        // Add discovered device (no exclusion logic needed here, it's done in JS)
        $discoveredDevices[] = [
            'ip' => $ip,
            'hostname' => $hostname,
            'mac' => $mac
        ];
    }
}

echo json_encode([
    'success' => true,
    'devices' => $discoveredDevices,
    // The reported range is now updated to reflect the new scan range
    'scanned_range' => "$subnet.$start_ip-$end_ip"
]);
?>
