Prerequisites and Setup Instructions
This project is only intended for Linux users with a good understanding of running and adminsitrating Linux and Apache.
Download all 5 files and place them in your apache WWW directory.
use the instructions below to edit a couple of files annd then access this dashboard by using your web browser to your apache servers ip address and add /server_status.html

1. Server Environment Requirements
The scripts require a standard PHP web server setup. Users must have:

Web Server: An Apache, Nginx, or similar web server.

PHP: PHP must be installed and configured to work with the web server.

Required PHP Extensions:

cURL: Required by get_server_info.php for the speed/latency checks against external sites like Google.

JSON: Required by all scripts to read (servers.json) and write data.

posix: Recommended, as your save_servers.php attempts to use the posix_getpwnam and chown/chgrp functions for file permission fixes. This function is typically only available on Linux/Unix systems.

2. File Permissions (Crucial)
This is one of the most common stumbling blocks for new users:

File Write Access: The web server user (e.g., www-data or apache) must have write permissions to the following file:

servers.json

Reason: The save_servers.php script writes new server configurations (after scanning or editing) back to servers.json. If permissions are wrong, the web page's edit/save functionality will fail.
I have added a sample servers.json file with a couple of servers that will need updating / editing but a network scan will allow users to add new servers.

3. Dependency on External Commands
   PHP scripts rely on calling external operating system programs, which requires PHP's exec() and shell_exec() functions to be enabled (they are sometimes disabled for security):

get_server_info.php: Requires the system's ping command for status checks.

network_scan.php: Requires the system's ping and arp commands for network discovery and MAC address lookup.

📝 Required User Edits
You will need to edit at least two core configuration files.

1. servers.json (Server List)
Action: You must edit the server entries to match the devices and network names on their local network.

Instruction: The hostname field can be a hostname (e.g., router.local) or a static IP address (e.g., 192.168.1.10).

2. network_scan.php (Network Subnet)
This as mandatory:

Action: Users must modify the $subnet variable.

Instruction: You must change:

PHP

$subnet = '192.168.8'; // <-- EDIT THIS
to match the first three octets of your network (e.g., '192.168.1', '10.0.0').

3. get_server_info.php (Optional - WAN Check)
Action: You may optionally change the external ping host.

Instruction: While not required, they can change the WAN_CHECK_HOST if they prefer not to use Google's DNS (or if it's blocked on their network) to check internet connectivity:

PHP

const WAN_CHECK_HOST = '8.8.8.8'; // Can be changed to another public DNS/IP
4. server_status.html (Optional - Chart Libraries)
Action: If a user runs this without an internet connection, they should copy the external libraries to a local directory.

Instruction: Note that the HTML file loads CSS and JavaScript libraries from external CDNs (like cdnjs.cloudflare.com and cdn.jsdelivr.net). If the server is offline (or for security), they should download these files and link to them locally.
