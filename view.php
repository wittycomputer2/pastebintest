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
    // Use file locking to prevent race conditions (Critical for Burn After Reading)
    $fp = fopen($file_path, 'r+');
    if ($fp && flock($fp, LOCK_EX)) { // Acquire exclusive lock
        $json_content = stream_get_contents($fp);

        if ($json_content === false) {
            $error_message = "Error reading paste data.";
        } else {
            $paste_data = json_decode($json_content, true);
            if ($paste_data === null) {
                $error_message = "Error decoding paste data. The file might be corrupted.";
            } else {
                // 5. Check for Expiration
                if (isset($paste_data['expiration_timestamp']) && time() >= $paste_data['expiration_timestamp']) {
                    // Truncate and delete
                    ftruncate($fp, 0);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    unlink($file_path);
                    $error_message = "This paste has expired and has been deleted.";
                    $paste_data = null;
                }
            }
        }

        // We DO NOT release the lock yet if we plan to delete it after checking burn status logic below
        // Actually, we need to read, check password (if any), THEN check burn logic.
        // This makes it tricky because we need user interaction (password form) between read and burn.
        // Wait, for Burn After Reading:
        // Logic: View once. If it's password protected, does a failed password attempt count as a view?
        // Usually NO. Only successful view counts.
        // So we can release lock if we are just showing the password form.
        // BUT if we successfully decrypt, we MUST burn it inside the SAME lock session to be safe?
        // No, standard HTTP is stateless. The user POSTs the password.
        // SO:
        // Request 1 (GET): Read file. If Burn=1, standard view. Lock -> Read -> Unlock.
        // Request 2 (POST Password): Lock -> Read -> Decrypt -> If Success AND Burn=1 -> Delete -> Unlock.

        // Let's refine the flow inside the lock for the current request.

        // Note: We released lock above if expired.
        // If not expired, we still hold lock? No, we shouldn't hold lock across user think-time.
        // We only hold it for the duration of THIS script execution.

        if ($paste_data !== null) {
            // 6. Handle Password Protection
            // ... Logic continues below ...
        }

    } else {
        $error_message = "Could not access paste file (Locked). Please try again.";
    }
    // We will ensure to close $fp at the end or when suitable.

    $display_content = null;
    $show_password_form = false;
    $password_error = null;
    $show_burn_message = false;
    $should_burn = false;

    // Proceed only if no error message so far and paste_data is loaded
    if (!isset($error_message) && isset($paste_data) && $paste_data !== null) {

        $decrypted_successfully = false;

        // 6. Handle Password Protection
        if (!empty($paste_data['password_hash'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password_submission'])) {
                if (isset($_POST['password'])) {
                    if (password_verify($_POST['password'], $paste_data['password_hash'])) {
                        // Password matches
                        $decrypted_successfully = true; // Potentially

                        // Attempt decryption if needed
                        if (isset($paste_data['is_encrypted']) && $paste_data['is_encrypted'] === true) {
                            if (!extension_loaded('openssl')) {
                                $error_message = "OpenSSL extension is not available. Cannot decrypt content.";
                            } elseif (empty($paste_data['encryption_salt']) || empty($paste_data['encryption_iv'])) {
                                $error_message = "Cannot decrypt content: missing salt or IV.";
                            } else {
                                $salt = base64_decode($paste_data['encryption_salt']);
                                $iv = base64_decode($paste_data['encryption_iv']);
                                $submitted_password = $_POST['password'];

                                // Determine iterations (Compatibility with old pastes)
                                $iterations = isset($paste_data['kdf_iterations']) ? (int) $paste_data['kdf_iterations'] : 10000;

                                $decryption_key = hash_pbkdf2('sha256', $submitted_password, $salt, $iterations, 32, true);

                                $cipher = 'aes-256-cbc';
                                $decrypted_content = openssl_decrypt(base64_decode($paste_data['content']), $cipher, $decryption_key, OPENSSL_RAW_DATA, $iv);

                                if ($decrypted_content === false) {
                                    $password_error = "Incorrect password or unable to decrypt content.";
                                    $show_password_form = true;
                                    $decrypted_successfully = false;
                                } else {
                                    $display_content = $decrypted_content;
                                }
                            }
                        } else {
                            // Non-encrypted but password protected
                            $display_content = $paste_data['content'];
                        }
                    } else {
                        $password_error = "Incorrect password.";
                        $show_password_form = true;
                    }
                } else {
                    $password_error = "Please enter a password.";
                    $show_password_form = true;
                }
            } else {
                $show_password_form = true;
            }
        } else {
            // Not password protected
            $display_content = $paste_data['content'];
            $decrypted_successfully = true;
        }

        // 7. Handle "Burn After Reading"
        // Only burn if we successfully showed the content
        if ($decrypted_successfully && isset($paste_data['burn_after_read']) && $paste_data['burn_after_read'] === true) {
            // We are inside the lock. Safe to delete.
            // We need to signal that we burned it.
            $should_burn = true;
        }
    }

    // Perform Burn Delete if needed (Still inside lock)
    if ($should_burn && $fp) {
        ftruncate($fp, 0); // Clear content
        // Unlock happens on close or explicit
        // We delete file after closing
        $show_burn_message = true;
    }

    if ($fp) {
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    // Unlink if burnt
    if ($should_burn && file_exists($file_path)) {
        unlink($file_path);
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
            <pre
                class="paste-content-wrapper"><?php echo htmlspecialchars($display_content, ENT_QUOTES, 'UTF-8'); // Output Sanitization HERE ?></pre>
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
        <div style="text-align: center;">
            <img src="/images/logopaste.png" alt="Logo">
        </div>
        <div style="text-align: center; margin-top: 15px; font-size: 0.9em;">
            <a href="terms.html" style="color: #666; text-decoration: none;">Terms & Conditions</a>
        </div>
    </footer>
</body>

</html>