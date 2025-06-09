<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Should be disabled in production

define('PASTES_DIR', __DIR__ . '/pastes/');

// 1. Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// 2. Retrieve form data
$content = isset($_POST['content']) ? $_POST['content'] : '';
$expire = isset($_POST['expire']) ? $_POST['expire'] : '1_day'; // Default expiry
$password = isset($_POST['password']) ? $_POST['password'] : '';
$burn_after_read_value = isset($_POST['burn_after_read']) ? $_POST['burn_after_read'] : '0';

// 3. Input Sanitization
$sanitized_content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

// 4. Password Hashing
$password_hash = null;
if (!empty($password)) {
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
}

// 5. Expiration Calculation
$current_time = time();
$expiration_timestamp = $current_time + (24 * 60 * 60); // Default to 1 day

if ($expire === '1_week') {
    $expiration_timestamp = $current_time + (7 * 24 * 60 * 60);
} elseif ($expire === '1_month') {
    // Approximate a month as 30 days for simplicity
    $expiration_timestamp = $current_time + (30 * 24 * 60 * 60);
}

// 6. Generate Unique ID
// Simple approach: uniqid() + some random bytes for more uniqueness
$unique_id = uniqid() . bin2hex(random_bytes(4));

// 7. Prepare Data for Storage
$burn_status = ($burn_after_read_value === '1');

$paste_data = [
    'id' => $unique_id,
    'content' => $sanitized_content,
    'expiration_timestamp' => $expiration_timestamp,
    'password_hash' => $password_hash,
    'burn_after_read' => $burn_status,
    'created_at' => $current_time
];

$serialized_data = json_encode($paste_data, JSON_PRETTY_PRINT);

// 8. Store the Paste
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

// 9. Construct JSON Response
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
