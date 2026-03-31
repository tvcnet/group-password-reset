===== The Hack Repair Guy's Group Password Reset =====

Contributors: hackrepairguy
Tags: password reset, bulk actions, user management, security
Requires at least: 6.8.3
Tested up to: 6.9
Stable tag: 3.0.0
Requires PHP: 8.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk reset WordPress passwords by role or sitewide, skip excluded usernames, and send secure reset links to affected users.

== Description ==

The Hack Repair Guy's Group Password Reset is a native WordPress admin tool for administrators who need to invalidate passwords at scale without manually editing each account.

Use it to:

- reset passwords for a single role
- reset passwords for all users
- exclude specific usernames from a run
- notify each affected user with a secure password reset link
- review success, failure, and skipped results after each run

The plugin supports two execution paths:

- JavaScript-enabled browsers process users in batches of 20 and update the results table in place.
- Browsers without JavaScript fall back to a standard WordPress form submission and return to the admin screen with the same summary data.

Compatibility baseline:

- WordPress 6.9
- PHP 8.3

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Open `Group Password Reset` from the WordPress admin menu.
4. Choose a role or leave the target on `All users`.
5. Enter any excluded usernames, confirm the reset, and start the run.

== Frequently Asked Questions ==

= Can I reset passwords for only one role? =

Yes. Choose a specific role from the dropdown before starting the reset.

= Can I reset all users at once? =

Yes. Leave the role selector on `All users`.

= What happens to excluded usernames? =

Excluded usernames are skipped, shown in the results table, and persisted for the next run.

= What email do users receive? =

Each affected user receives a secure WordPress password reset link so they can choose a new password themselves.

= Does the plugin require JavaScript? =

No. JavaScript enables the batched in-page progress UI, but the main reset flow also works through a non-JavaScript fallback.

== Changelog ==

= 3.0.0 =

- Modernized the admin experience with a native WordPress layout.
- Added a non-JavaScript fallback for the reset workflow.
- Reworked batching and job state handling for safer progress tracking.
- Improved result reporting with success, failed, and skipped statuses.
- Updated documentation and compatibility targets.

= 2.2.0 =

- Added feature to exclude specific usernames from group password reset.

= 2.1.0 =

- Updated readme files and name.

= 2.0.0 =

- CSS updates.
