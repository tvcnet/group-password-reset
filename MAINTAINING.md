# Maintaining Group Password Reset

This plugin is distributed through GitHub releases rather than WordPress.org. The repository should still stay close to WordPress plugin expectations so that future review or distribution changes do not require a large cleanup pass.

## Baseline

- WordPress compatibility floor: `6.8.3`
- WordPress tested up to: `6.9.4`
- PHP minimum: `8.3`
- Current packaging target: `group-password-reset.zip`
- Zip root directory: `group-password-reset/`

## Repo Standards

- Run WordPress coding standards checks with:
  - `phpcs --standard=phpcs.xml.dist .`
- Auto-fix safe PHP formatting changes with:
  - `phpcbf --standard=phpcs.xml.dist group-password-reset.php includes`
- Re-run syntax checks before release:
  - `php -l group-password-reset.php`
  - `php -l includes/admin-menu.php`
  - `php -l includes/admin-views.php`
  - `php -l includes/password-reset.php`

## Manual QA Checklist

- Confirm the plugin activates cleanly with no PHP notices or warnings.
- Confirm the plugin row on `plugins.php` shows the expected metadata and `View details` modal.
- Confirm the modal opens, scrolls to the footer, and the download/homepage links are correct.
- Confirm a role-based reset works with JavaScript enabled.
- Confirm an all-users reset works with one or more excluded usernames.
- Confirm excluded usernames appear in the results summary and are skipped.
- Confirm a no-match role finishes cleanly without getting stuck.
- Confirm the non-JavaScript fallback path works through `admin-post.php`.
- Confirm summary totals and per-user statuses stay consistent between async and fallback runs.
- Confirm reset emails are attempted and that failure states are reported cleanly when mail is unavailable.

## Release Checklist

- Update plugin header metadata in `group-password-reset.php` if compatibility or version values changed.
- Update `readme.txt` and `README.md` so requirements, behavior, and changelog stay aligned.
- Confirm the modal metadata still matches the canonical constants in `group-password-reset.php`.
- Run WordPressCS and PHP syntax checks.
- Test on the local WordPress installation at `/Users/JW/Studio/my-wordpress-website`.
- Build an installable archive whose filename is `group-password-reset.zip`.
- Verify the archive extracts to a top-level `group-password-reset/` directory.
- Attach the installable zip to the GitHub release and use that asset in documentation links.

## Local Environment Notes

- Primary local site: `http://my-wordpress-website.wp.local/`
- WordPress admin: `http://my-wordpress-website.wp.local/wp-admin`
- Local plugin install path: `/Users/JW/Studio/my-wordpress-website/wp-content/plugins/group-password-reset`
- Forward-compatibility testing on prerelease WordPress builds is useful, but the documented compatibility baseline should remain grounded in the stable target versions above.
