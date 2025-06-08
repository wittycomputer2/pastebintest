<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Should be disabled in production

define('PASTES_DIR', __DIR__ . '/pastes/');

// 1. Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect to index.html or show an error
    header('Location: index.html');
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
        // Handle error: directory could not be created
        error_log("Failed to create pastes directory: " . PASTES_DIR);
        die("Error: Could not create storage directory. Please check permissions.");
    }
}

$file_path = PASTES_DIR . $unique_id . '.json';

// Write the serialized data to the file
if (file_put_contents($file_path, $serialized_data) === false) {
    // Handle error: file could not be written
    error_log("Failed to write paste file: " . $file_path);
    die("Error: Could not save paste. Please try again later.");
}

// 9. Redirection
header("Location: view.php?id=" . $unique_id);
exit;

?>
