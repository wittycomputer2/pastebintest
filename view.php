<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Should be disabled in production
session_start(); // Useful for flash messages if we extend, e.g., for wrong password

define('PASTES_DIR', __DIR__ . '/pastes/');

// 1. Get Paste ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect or show error
    header('Location: index.html?error=missing_id');
    exit;
}
$paste_id = basename($_GET['id']); // Basic sanitization for filename

// 2. Construct File Path (already have PASTES_DIR)
$file_path = PASTES_DIR . $paste_id . '.json';

// 3. Check if Paste File Exists
if (!file_exists($file_path) || !is_readable($file_path)) {
    $error_message = "Paste not found. It may have expired or never existed.";
    // We'll display this error within the HTML structure later for consistency
} else {
    // 4. Load Paste Data
    $json_content = file_get_contents($file_path);
    if ($json_content === false) {
        $error_message = "Error reading paste data.";
    } else {
        $paste_data = json_decode($json_content, true);
        if ($paste_data === null) {
            $error_message = "Error decoding paste data. The file might be corrupted.";
            // Potentially delete corrupted file
            // unlink($file_path);
        } else {
            // 5. Check for Expiration
            if (isset($paste_data['expiration_timestamp']) && time() >= $paste_data['expiration_timestamp']) {
                unlink($file_path); // Delete expired paste
                $error_message = "This paste has expired and has been deleted.";
                $paste_data = null; // Clear paste data so it doesn't get processed further
            }
        }
    }
}

$display_content = null;
$show_password_form = false;
$password_error = null;
$show_burn_message = false;

// Proceed only if no error message so far and paste_data is loaded
if (!isset($error_message) && isset($paste_data) && $paste_data !== null) {
    // 6. Handle Password Protection
    if (!empty($paste_data['password_hash'])) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password_submission'])) {
            if (isset($_POST['password'])) {
                if (password_verify($_POST['password'], $paste_data['password_hash'])) {
                    // Password correct, proceed to display
                    $display_content = $paste_data['content'];
                } else {
                    $password_error = "Incorrect password.";
                    $show_password_form = true; // Show form again
                }
            } else {
                 // Should not happen if form is submitted correctly
                $password_error = "Please enter a password.";
                $show_password_form = true;
            }
        } else {
            // Password required, but not yet submitted. Show form.
            $show_password_form = true;
        }
    } else {
        // Not password protected, display directly
        $display_content = $paste_data['content'];
    }

    // If content is ready to be displayed (either not password protected or password was correct)
    if ($display_content !== null && !$show_password_form) {
        // 7. Handle "Burn After Reading"
        if (isset($paste_data['burn_after_read']) && $paste_data['burn_after_read'] === true) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            // Even if unlinked, we still show the content for this one view.
            // The prompt implies showing a message *after* displaying.
            // We can show a burn message if it *was* a burn paste.
            $show_burn_message = true;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Paste</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1 class="main-title">Private and free pastebin</h1>
    <div class="container">
        <!-- <h1>View Paste</h1> -->

        <?php if (isset($error_message)): ?>
            <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php elseif ($show_password_form): ?>
            <form action="view.php?id=<?php echo htmlspecialchars($paste_id); ?>" method="POST">
                <p>This paste is password protected. Please enter the password to view:</p>
                <?php if ($password_error): ?>
                    <p class="error"><?php echo htmlspecialchars($password_error); ?></p>
                <?php endif; ?>
                <div>
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div>
                    <input type="submit" name="password_submission" value="View Paste">
                </div>
            </form>
        <?php elseif ($display_content !== null): ?>
            <h2>Paste Content:</h2>
            <pre class="paste-content-wrapper"><?php echo $display_content; // Content is already htmlspecialchars'd from create.php ?></pre>
            <?php if ($show_burn_message): ?>
                <p><em>Note: This paste was set to "burn after reading" and has now been deleted.</em></p>
            <?php endif; ?>
        <?php else: ?>
            <p>An unexpected error occurred, or the paste could not be loaded.</p>
        <?php endif; ?>

        <hr>
        <p class="create-new-link-p"><a href="index.html">Create New Paste</a></p>
    </div>
<footer class="site-footer">
  <p>Minimal, private and free pastebin from <a href="https://witty.computer">Witty Computer</a></p>
  <p><a href="https://witty.computer">Logo goes here.</a></p>
</footer>
</body>
</html>
