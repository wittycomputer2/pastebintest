<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Should be disabled in production

define('PASTES_DIR', __DIR__ . '/pastes/');
define('RATE_LIMIT_FILE', __DIR__ . '/rate_limit.json');
define('MAX_PASTES_PER_HOUR', 10);
define('MAX_CONTENT_SIZE', 512 * 1024); // 512KB

// 1. Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// 2. Check Content Size
$content_length = (int) $_SERVER['CONTENT_LENGTH'];
if ($content_length > MAX_CONTENT_SIZE) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Paste too large. Limit is 512KB.']);
    exit;
}

// 3. Rate Limiting
function check_rate_limit($ip) {
    $data = [];
    if (file_exists(RATE_LIMIT_FILE)) {
        $data = json_decode(file_get_contents(RATE_LIMIT_FILE), true) ?: [];
    }
    
    // Clean old entries
    $now = time();
    foreach ($data as $logged_ip => $timestamps) {
        $data[$logged_ip] = array_filter($timestamps, function($ts) use ($now) {
            return ($now - $ts) < 3600; // Keep last hour
        });
        if (empty($data[$logged_ip])) {
            unset($data[$logged_ip]);
        }
    }

    // Check limit
    if (!isset($data[$ip])) {
        $data[$ip] = [];
    }

    if (count($data[$ip]) >= MAX_PASTES_PER_HOUR) {
        return false;
    }

    $data[$ip][] = $now;
    file_put_contents(RATE_LIMIT_FILE, json_encode($data));
    return true;
}

$user_ip = $_SERVER['REMOTE_ADDR'];
if (!check_rate_limit($user_ip)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Rate limit exceeded. Please try again later.']);
    exit;
}

// 4. Retrieve form data
$content = isset($_POST['content']) ? $_POST['content'] : '';
$expire = isset($_POST['expire']) ? $_POST['expire'] : '1_day'; // Default expiry
$password = isset($_POST['password']) ? $_POST['password'] : '';
$burn_after_read_value = isset($_POST['burn_after_read']) ? $_POST['burn_after_read'] : '0';

// Note: We do NOT use htmlspecialchars here anymore. We store raw data to allow future flexibility.
// Sanitization must happen strictly on Output (view.php).
$raw_content = $content; 

// 5. Encryption & Password Hashing
$password_hash = null;
$encryption_salt = null;
$encryption_iv = null;
$encrypted_content = $raw_content; 
$is_encrypted = false;
$iterations = 600000; // Stronger PBKDF2 default

if (!empty($password)) {
    if (!extension_loaded('openssl')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'OpenSSL extension is not available. Cannot encrypt content.']);
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Generate a salt for key derivation
    $encryption_salt = random_bytes(16);
    // Derive a key from the password and salt
    // Using SHA256 for PBKDF2, massive increase in iterations (10k -> 600k)
    $encryption_key = hash_pbkdf2('sha256', $password, $encryption_salt, $iterations, 32, true);

    // AES-256-CBC encryption
    $cipher = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length($cipher);
    $encryption_iv = openssl_random_pseudo_bytes($iv_length);

    $encrypted_data = openssl_encrypt($raw_content, $cipher, $encryption_key, OPENSSL_RAW_DATA, $encryption_iv);
    if ($encrypted_data === false) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Content encryption failed.']);
        exit;
    }
    $encrypted_content = base64_encode($encrypted_data); // Store as base64
    $is_encrypted = true;
}

// 6. Expiration Calculation
$current_time = time();
$expiration_timestamp = $current_time + (24 * 60 * 60); // Default to 1 day

if ($expire === '1_week') {
    $expiration_timestamp = $current_time + (7 * 24 * 60 * 60);
} elseif ($expire === '1_month') {
    // Approximate a month as 30 days for simplicity
    $expiration_timestamp = $current_time + (30 * 24 * 60 * 60);
}

// 7. Generate Unique ID
// Simple approach: uniqid() + some random bytes for more uniqueness
$unique_id = uniqid() . bin2hex(random_bytes(4));

// 8. Prepare Data for Storage
$burn_status = ($burn_after_read_value === '1');

$paste_data = [
    'id' => $unique_id,
    'content' => $encrypted_content, 
    'expiration_timestamp' => $expiration_timestamp,
    'password_hash' => $password_hash, // For authentication
    'burn_after_read' => $burn_status,
    'created_at' => $current_time,
    // Add encryption metadata if content was encrypted
    'encryption_salt' => $encryption_salt ? base64_encode($encryption_salt) : null,
    'encryption_iv' => $encryption_iv ? base64_encode($encryption_iv) : null,
    'is_encrypted' => $is_encrypted,
    'kdf_iterations' => $iterations // Store iteration count for forward compatibility
];

$serialized_data = json_encode($paste_data, JSON_PRETTY_PRINT);

// 9. Store the Paste
// Ensure pastes directory exists
if (!is_dir(PASTES_DIR)) {
    if (!mkdir(PASTES_DIR, 0770, true)) {
        error_log("Failed to create pastes directory: " . PASTES_DIR);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error: Could not create storage directory. Please check server permissions.']);
        exit;
    }
}

$file_path = PASTES_DIR . $unique_id . '.json';

// Write the serialized data to the file
if (file_put_contents($file_path, $serialized_data) === false) {
    error_log("Failed to write paste file: " . $file_path);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error: Could not save paste. Please try again later.']);
    exit;
}

// 10. Construct JSON Response
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https:" : "http:";
$host = $_SERVER['HTTP_HOST'];
// dirname($_SERVER['PHP_SELF']) can be tricky if script is in root.
// SCRIPT_NAME is usually more reliable for the full path to the script.
// To get the base directory, we find the last slash in SCRIPT_NAME.
$script_path = $_SERVER['SCRIPT_NAME'];
$last_slash_pos = strrpos($script_path, '/');
$base_url_path = ($last_slash_pos !== false) ? substr($script_path, 0, $last_slash_pos) : '';
$base_url_path = rtrim($base_url_path, '/'); // Ensure no trailing slash if not root

$paste_url = $protocol . '//' . $host . $base_url_path . '/view.php?id=' . $unique_id;

$response = [
    'status' => 'success',
    'url' => $paste_url,
    'message' => 'Paste created successfully! You can copy the URL below.'
    // 'message' => 'Paste created successfully! Press Ctrl+C (or Cmd+C on Mac) to copy your paste URL.'
];

header('Content-Type: application/json');
echo json_encode($response);
exit;

?>

