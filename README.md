# Simple PHP Pastebin

A simple pastebin application built with PHP, HTML, CSS, and JavaScript, now featuring a dark theme. Allows users to create text pastes with optional password protection, expiration times, and a "burn after reading" feature.

## Features

*   Create text pastes.
*   Set expiration for pastes (1 Day, 1 Week, 1 Month).
*   Optional password protection for pastes.
*   Optional "burn after reading" feature (paste is deleted after the first time its unique URL is visited).
*   Unique URLs for each paste.
*   Displays the unique paste URL directly on the page after creation for easy copying (no immediate redirect). This ensures "burn after reading" pastes are not accidentally viewed by the creator immediately.
*   Automatic deletion of expired pastes upon access.

## Files

*   `index.html`: The main page with the form to create new pastes.
*   `style.css`: CSS styles for the application.
*   `script.js`: Basic client-side JavaScript for form confirmation.
*   `create.php`: Backend script to handle paste creation and storage.
*   `view.php`: Backend script to handle paste viewing, password checks, expiration, and burn-after-reading.
*   `pastes/`: Directory where paste files are stored.
    *   `pastes/.htaccess`: Security file to prevent direct access to this directory.
*   `README.md`: This file.

## Requirements

*   A web server with PHP support (tested with PHP 7.x, should work with 8.x).
*   PHP `json` extension (usually enabled by default).
*   PHP `random_bytes` function (available in PHP 7+).
*   Web server must have write permissions for the `pastes/` directory.

## Deployment on Plesk (and similar hosting)

1.  **Upload Files:**
    *   Upload all the files (`index.html`, `style.css`, `script.js`, `create.php`, `view.php`) to the desired directory in your web hosting space (e.g., `httpdocs/`, `public_html/`, or a subdirectory).
    *   The `pastes/` directory itself does not need to be uploaded initially, as `create.php` will attempt to create it. However, you can create it manually if you prefer.

2.  **Permissions for `pastes/` directory:**
    *   The web server (PHP script) needs to be able to create and write files in the `pastes/` directory.
    *   When `create.php` runs for the first time, it will attempt to create a directory named `pastes` in the same location as `create.php`.
    *   If the `pastes/` directory is created automatically by `create.php`, it will try to set permissions to `0770`. This usually works if the web server process owns the files/directories or is in the correct group.
    *   **If you encounter issues with saving pastes (e.g., nothing happens or you see errors after submitting a paste), it's likely a permissions issue.**
    *   **Troubleshooting Permissions in Plesk:**
        *   Open File Manager in Plesk.
        *   Navigate to the directory where you uploaded the pastebin files.
        *   If the `pastes/` directory was created, select it and click "Change Permissions."
        *   Ensure that the "Plesk IIS WP User" (or a similar user like `apache`, `www-data`, `nobody`, depending on your server setup - Plesk often shows the specific application pool user) has "Write" permissions. Granting "Full Control" to this user for the `pastes/` directory is often the simplest solution if you're unsure.
        *   If the `pastes/` directory was *not* created, you might need to adjust the permissions of the *parent* directory temporarily to allow `create.php` to make it, or create it manually in Plesk File Manager and then set its permissions.

3.  **Secure `pastes/` directory (if `.htaccess` is not effective):**
    *   The provided `.htaccess` file inside `pastes/` (`Deny from all`) is intended to prevent direct web access to stored pastes. This works on Apache servers.
    *   If your server is Nginx or IIS and does not process `.htaccess` files in the same way, you might need to configure similar restrictions through your web server's configuration panel in Plesk (e.g., "Deny direct access" settings for the directory if available). The primary goal is that users should not be able to navigate to `yourdomain.com/pastes/some_paste_id.json` directly. Access should only be through `view.php`.

4.  **Testing:**
    *   Open your website (e.g., `yourdomain.com/path/to/pastebin/index.html`) in a browser.
    *   Try creating a paste using the form.
    *   After submission, the page should *not* redirect. Instead, a success message and the unique URL for your paste should appear below the form.
    *   Copy this URL and open it in a new tab or window. You should be able to see your paste.
    *   Test password protection: create a password-protected paste, then try to view it. You should be prompted for the password.
    *   Test the "burn after reading" feature: create a "burn" paste, view it once. Try to view it again using the same URL; it should be gone or show an appropriate message.
    *   Test expiration by setting a short expiration (if you were to modify the code for testing, e.g., 1 minute) or by waiting for a day and then trying to access the paste.

## (Optional) Cron Job for Proactive Cleanup

The application currently deletes expired pastes when someone tries to access them via `view.php`.
For a more proactive cleanup of very old, unaccessed expired pastes, you could set up a cron job on your server. This is an advanced step and not strictly necessary for basic operation.

To do this, you would need to:
1.  Create a new PHP script (e.g., `cleanup_expired.php`) that:
    *   Scans all files in the `pastes/` directory.
    *   For each file, reads its JSON content.
    *   Checks the `expiration_timestamp`.
    *   If `time() >= expiration_timestamp`, deletes the file.
2.  Set up a cron job in Plesk (Scheduled Tasks) to run this `cleanup_expired.php` script periodically (e.g., once a day).
    *   Command for the cron job would typically be something like: ` /opt/plesk/php/YOUR_PHP_VERSION/bin/php /var/www/vhosts/YOUR_DOMAIN/httpdocs/PATH_TO_SCRIPT/cleanup_expired.php` (The exact path to PHP and your website files will vary).

This feature is not implemented in the current set of files but is a common way to manage expired data.

## Security Notes

*   **Content Sanitization:** Paste content is sanitized using `htmlspecialchars()` before storage to prevent XSS when viewed.
*   **Password Hashing:** Passwords are securely hashed using `password_hash()` and verified with `password_verify()`.
*   **Directory Protection:** The `pastes/.htaccess` file helps protect direct access to raw paste files on Apache servers. Ensure similar protection if on other web servers.
*   **Server-Side Validation:** Key logic (expiration, password checking) is handled server-side.
*   **Permissions:** Correctly setting file/directory permissions for the `pastes/` directory is crucial. It should be writable by the web server user but not excessively open to others.
