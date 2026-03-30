# The Hack Repair Guy's Group Password Reset

Bulk reset WordPress user passwords by role or across the full site, skip excluded usernames, and notify affected users with secure password reset links.

## Requirements

- WordPress 6.8.3 or newer
- PHP 8.3 or newer
- Administrator access to the target site

## Features

- Reset passwords for a selected role or all users
- Persist excluded usernames between runs
- Show skipped users in the results table
- Send secure password reset links by email instead of exposing raw passwords
- Support both JavaScript-enhanced batch processing and a non-JavaScript fallback
- Store async progress per administrator so one reset run does not overwrite another user's session

## How It Works

1. Open the **Group Password Reset** admin screen.
2. Choose a role or leave the target on **All users**.
3. Enter comma-separated usernames to exclude.
4. Confirm that you want to invalidate current passwords.
5. Start the reset.

With JavaScript enabled, the plugin processes users in batches of 20 and updates the results table in place. Without JavaScript, the form submits through WordPress `admin-post.php`, processes the full run on the server, and redirects back with the summary and per-user results.

## Architecture Notes

- `group-password-reset.php` is the bootstrap file and loads the admin and reset subsystems.
- `includes/admin-menu.php` owns the admin screen, asset registration, and the non-JavaScript form handler.
- `includes/password-reset.php` owns shared reset logic, result formatting, transient-backed async jobs, and AJAX endpoints.
- `assets/js/admin.js` progressively enhances the admin screen with async batching.
- `assets/css/admin.css` keeps the UI aligned with a native WordPress admin layout.

## Testing Notes

Baseline compatibility target:

- WordPress 6.8.3
- PHP 8.3

Recommended manual scenarios:

- Reset a single role with multiple users.
- Reset all users while excluding one or more usernames.
- Run the tool with a role that has no users.
- Trigger a run with JavaScript disabled to verify the fallback path.
- Verify results distinguish `success`, `failed`, and `skipped`.

## Release Checklist

- Update the plugin version and changelog.
- Verify `readme.txt` matches the plugin header and current behavior.
- Confirm the admin screen loads without PHP notices.
- Confirm the reset flow works on WordPress 6.8.3 with PHP 8.3, plus any newer local prerelease builds used for forward-compatibility checks.
