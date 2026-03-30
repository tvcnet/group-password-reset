<?php
/**
 * Admin page and request wiring for Group Password Reset.
 *
 * @package GroupPasswordReset
 */

if (!defined('ABSPATH')) {
    exit;
}

function gpr_register_settings() {
    register_setting(
        'gpr_settings_group',
        'gpr_excluded_usernames',
        array(
            'type' => 'string',
            'sanitize_callback' => 'gpr_sanitize_excluded_usernames',
            'default' => '',
        )
    );
}

function gpr_add_admin_menu() {
    add_menu_page(
        __('Group Password Reset', 'group-password-reset'),
        __('Group Password Reset', 'group-password-reset'),
        'manage_options',
        'group-password-reset',
        'gpr_render_admin_page',
        'dashicons-admin-users',
        60
    );
}

function gpr_enqueue_admin_assets($hookSuffix) {
    $supportedScreens = array(
        'toplevel_page_group-password-reset',
        'plugins.php',
    );

    if (!in_array($hookSuffix, $supportedScreens, true)) {
        return;
    }

    wp_enqueue_style(
        'gpr-admin-styles',
        GPR_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        GPR_VERSION
    );

    wp_enqueue_script(
        'gpr-admin-script',
        GPR_PLUGIN_URL . 'assets/js/admin.js',
        array(),
        GPR_VERSION,
        true
    );

    wp_localize_script(
        'gpr-admin-script',
        'gprAdmin',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gpr_job_nonce'),
            'chunkSize' => GPR_CHUNK_SIZE,
            'detailsModal' => array(
                'pluginName' => __("The Hack Repair Guy's Group Password Reset", 'group-password-reset'),
                'versionLabel' => GPR_VERSION,
                'requiresWp' => '6.8.3',
                'requiresPhp' => '8.3',
                'testedUpTo' => '6.8.3',
                'authorName' => 'Jim Walker',
                'authorUrl' => 'https://hackrepair.com',
                'pluginUrl' => 'https://hackrepair.com/plugins/group-password-reset',
                'releaseUrl' => 'https://github.com/tvcnet/group-password-reset/releases/tag/v3.0.0',
                'downloadUrl' => 'https://github.com/tvcnet/group-password-reset/releases/download/v3.0.0/group-password-reset.zip',
                'repoUrl' => 'https://github.com/tvcnet/group-password-reset',
                'bannerUrl' => 'https://hackrepair.com/wp-content/uploads/2024/07/banner-772x250-1.png?_t=1721766551',
                'activeInstalls' => __('Direct GitHub release', 'group-password-reset'),
                'lastUpdated' => __('Version 3.0.0 release', 'group-password-reset'),
            ),
            'messages' => array(
                'startError' => __('Unable to start the password reset job.', 'group-password-reset'),
                'processError' => __('Unable to continue the password reset job.', 'group-password-reset'),
                'complete' => __('Password reset complete.', 'group-password-reset'),
            ),
        )
    );
}

function gpr_add_plugin_action_links($actions, $pluginFile) {
    if ($pluginFile !== plugin_basename(GPR_PLUGIN_FILE)) {
        return $actions;
    }

    $settingsLink = sprintf(
        '<a href="%s">%s</a>',
        esc_url(gpr_get_admin_page_url()),
        esc_html__('Settings', 'group-password-reset')
    );

    array_unshift($actions, $settingsLink);

    return $actions;
}

function gpr_add_plugin_row_meta($links, $pluginFile) {
    if ($pluginFile !== plugin_basename(GPR_PLUGIN_FILE)) {
        return $links;
    }

    $links[] = sprintf(
        '<a href="#" class="gpr-view-details-link" data-gpr-view-details="1" aria-label="%s">%s</a>',
        esc_attr__('More information about The Hack Repair Guy\'s Group Password Reset', 'group-password-reset'),
        esc_html__('View details', 'group-password-reset')
    );

    return $links;
}

function gpr_render_plugin_details_modal() {
    global $pagenow;

    if ($pagenow !== 'plugins.php') {
        return;
    }
    ?>
    <div id="gpr-plugin-modal" class="gpr-plugin-modal" hidden>
        <div class="gpr-plugin-modal__backdrop" data-gpr-modal-close="1"></div>
        <div class="gpr-plugin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="gpr-plugin-modal-title">
            <button type="button" class="gpr-plugin-modal__close" aria-label="<?php esc_attr_e('Close plugin details', 'group-password-reset'); ?>" data-gpr-modal-close="1">×</button>

            <div class="gpr-plugin-modal__banner">
                <img src="https://hackrepair.com/wp-content/uploads/2024/07/banner-772x250-1.png?_t=1721766551" alt="<?php esc_attr_e('Group Password Reset banner', 'group-password-reset'); ?>">
                <div class="gpr-plugin-modal__banner-copy">
                    <h2 id="gpr-plugin-modal-title"><?php esc_html_e("The Hack Repair Guy's Group Password Reset", 'group-password-reset'); ?></h2>
                </div>
            </div>

            <div class="gpr-plugin-modal__tabs" role="tablist" aria-label="<?php esc_attr_e('Plugin information tabs', 'group-password-reset'); ?>">
                <button type="button" class="gpr-plugin-modal__tab is-active" data-gpr-tab="description"><?php esc_html_e('Description', 'group-password-reset'); ?></button>
                <button type="button" class="gpr-plugin-modal__tab" data-gpr-tab="installation"><?php esc_html_e('Installation', 'group-password-reset'); ?></button>
                <button type="button" class="gpr-plugin-modal__tab" data-gpr-tab="changelog"><?php esc_html_e('Changelog', 'group-password-reset'); ?></button>
            </div>

            <div class="gpr-plugin-modal__content">
                <div class="gpr-plugin-modal__main">
                    <section class="gpr-plugin-modal__panel is-active" data-gpr-panel="description">
                        <p class="gpr-plugin-modal__eyebrow"><?php esc_html_e('Security Utility', 'group-password-reset'); ?></p>
                        <p><strong><?php esc_html_e('Group Password Reset is built for administrators who need to invalidate passwords quickly across a role or an entire site. It is particularly useful for post-hack cleanup, membership sites, and any workflow that requires mass credential rotation.', 'group-password-reset'); ?></strong></p>
                        <ul>
                            <li><?php esc_html_e('Reset by role or across all users', 'group-password-reset'); ?></li>
                            <li><?php esc_html_e('Exclude specific usernames from a run', 'group-password-reset'); ?></li>
                            <li><?php esc_html_e('Process large user lists in batches', 'group-password-reset'); ?></li>
                            <li><?php esc_html_e('Show success, failed, and skipped results clearly', 'group-password-reset'); ?></li>
                            <li><?php esc_html_e('Provide a JavaScript-enhanced flow with a non-JavaScript fallback', 'group-password-reset'); ?></li>
                        </ul>
                    </section>

                    <section class="gpr-plugin-modal__panel" data-gpr-panel="installation" hidden>
                        <ol>
                            <li><?php esc_html_e('Download the official group-password-reset.zip release package.', 'group-password-reset'); ?></li>
                            <li><?php esc_html_e('Go to Plugins > Add New Plugin in WordPress.', 'group-password-reset'); ?></li>
                            <li><?php esc_html_e('Upload the zip file and activate the plugin.', 'group-password-reset'); ?></li>
                            <li><?php esc_html_e('Open Group Password Reset from the WordPress admin menu.', 'group-password-reset'); ?></li>
                        </ol>
                        <p><?php esc_html_e('Manual installation is also supported by uploading the group-password-reset folder into /wp-content/plugins/.', 'group-password-reset'); ?></p>
                    </section>

                    <section class="gpr-plugin-modal__panel" data-gpr-panel="changelog" hidden>
                        <h3><?php esc_html_e('Version 3.0.0', 'group-password-reset'); ?></h3>
                        <ul>
                            <li><?php esc_html_e('Modernized the admin experience with a native WordPress layout.', 'group-password-reset'); ?></li>
                            <li><?php esc_html_e('Added a non-JavaScript fallback for the reset workflow.', 'group-password-reset'); ?></li>
                            <li><?php esc_html_e('Improved batching, result reporting, and exclusion handling.', 'group-password-reset'); ?></li>
                            <li><?php esc_html_e('Updated compatibility guidance to WordPress 6.8.3 and PHP 8.3.', 'group-password-reset'); ?></li>
                        </ul>
                    </section>
                </div>

                <aside class="gpr-plugin-modal__sidebar">
                    <ul class="gpr-plugin-modal__meta">
                        <li><strong><?php esc_html_e('Version:', 'group-password-reset'); ?></strong> <?php echo esc_html(GPR_VERSION); ?></li>
                        <li><strong><?php esc_html_e('Author:', 'group-password-reset'); ?></strong> <a href="https://hackrepair.com" target="_blank" rel="noopener">Jim Walker</a></li>
                        <li><strong><?php esc_html_e('Last Updated:', 'group-password-reset'); ?></strong> <?php esc_html_e('March 30, 2026', 'group-password-reset'); ?></li>
                        <li><strong><?php esc_html_e('Requires WordPress Version:', 'group-password-reset'); ?></strong> <?php esc_html_e('6.8.3 or higher', 'group-password-reset'); ?></li>
                        <li><strong><?php esc_html_e('Compatible up to:', 'group-password-reset'); ?></strong> <?php esc_html_e('6.9.4', 'group-password-reset'); ?></li>
                        <li><strong><?php esc_html_e('Active Installations:', 'group-password-reset'); ?></strong> <?php esc_html_e('Direct distribution', 'group-password-reset'); ?></li>
                        <li><a href="https://github.com/tvcnet/group-password-reset" target="_blank" rel="noopener"><?php esc_html_e('GitHub Plugin Page »', 'group-password-reset'); ?></a></li>
                        <li><a href="https://hackrepair.com/plugins/group-password-reset" target="_blank" rel="noopener"><?php esc_html_e('Plugin Homepage »', 'group-password-reset'); ?></a></li>
                    </ul>
                </aside>
            </div>

            <div class="gpr-plugin-modal__footer">
                <a class="button button-primary" href="https://github.com/tvcnet/group-password-reset/releases/download/v3.0.0/group-password-reset.zip" target="_blank" rel="noopener"><?php esc_html_e('Download Plugin', 'group-password-reset'); ?></a>
                <a class="button" href="https://hackrepair.com/plugins/group-password-reset" target="_blank" rel="noopener"><?php esc_html_e('Plugin Homepage', 'group-password-reset'); ?></a>
            </div>
        </div>
    </div>
    <?php
}

function gpr_handle_reset_request() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to perform this action.', 'group-password-reset'));
    }

    check_admin_referer('gpr_reset_passwords');

    $role = isset($_POST['gpr_user_role']) ? sanitize_key(wp_unslash($_POST['gpr_user_role'])) : '';
    $excludedUsernames = isset($_POST['gpr_excluded_usernames']) ? gpr_sanitize_excluded_usernames(wp_unslash($_POST['gpr_excluded_usernames'])) : '';

    update_option('gpr_excluded_usernames', $excludedUsernames);

    $run = gpr_prepare_reset_run($role, $excludedUsernames);
    $results = $run['skipped_results'];
    $summary = array(
        'total' => $run['total_users'],
        'queued' => $run['queued_total'],
        'processed' => count($run['skipped_results']),
        'success' => 0,
        'failed' => 0,
        'skipped' => count($run['skipped_results']),
    );
    $job = array(
        'role' => $role,
        'total_users' => $run['total_users'],
        'offset' => 0,
        'excluded_ids' => $run['excluded_ids'],
    );

    while ($job['offset'] < $job['total_users']) {
        $batch = gpr_get_job_batch_users($job);

        if (empty($batch)) {
            continue;
        }

        foreach ($batch as $user) {
            $result = gpr_reset_single_user($user);
            $results[] = $result;
            $summary['processed']++;

            if ($result['status'] === 'success') {
                $summary['success']++;
            } else {
                $summary['failed']++;
            }
        }
    }

    gpr_store_flash_results(
        array(
            'summary' => $summary,
            'results' => $results,
            'scope_label' => gpr_get_scope_label($role),
            'excluded_usernames' => $excludedUsernames,
            'mode' => 'fallback',
        )
    );

    wp_safe_redirect(gpr_get_admin_page_url());
    exit;
}

function gpr_get_admin_page_url() {
    return admin_url('admin.php?page=group-password-reset');
}

function gpr_render_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $resultsPayload = gpr_get_flash_results();
    $roles = gpr_get_available_roles();
    $excludedUsernames = get_option('gpr_excluded_usernames', '');
    $screenNotice = gpr_get_screen_notice($resultsPayload);
    ?>
    <div class="wrap gpr-admin-page">
        <h1><?php echo esc_html(sprintf(__('Group Password Reset %s', 'group-password-reset'), GPR_VERSION)); ?></h1>
        <p class="gpr-lead">
            <?php esc_html_e('Reset passwords for a selected role or all users, then notify each affected account with a secure password reset link.', 'group-password-reset'); ?>
        </p>

        <?php if ($screenNotice) : ?>
            <div class="notice <?php echo esc_attr($screenNotice['class']); ?> gpr-notice">
                <p><?php echo esc_html($screenNotice['message']); ?></p>
            </div>
        <?php endif; ?>

        <div class="gpr-card">
            <h2><?php esc_html_e('Reset Settings', 'group-password-reset'); ?></h2>
            <form id="gpr-reset-form" class="gpr-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('gpr_reset_passwords'); ?>
                <input type="hidden" name="action" value="gpr_reset_passwords">

                <div class="gpr-field">
                    <label for="gpr-user-role"><?php esc_html_e('Target user role', 'group-password-reset'); ?></label>
                    <select id="gpr-user-role" name="gpr_user_role">
                        <option value=""><?php esc_html_e('All users', 'group-password-reset'); ?></option>
                        <?php foreach ($roles as $roleKey => $roleLabel) : ?>
                            <option value="<?php echo esc_attr($roleKey); ?>"><?php echo esc_html($roleLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Choose a single role, or leave this on All users to process every account except exclusions.', 'group-password-reset'); ?></p>
                </div>

                <div class="gpr-field">
                    <label for="gpr-excluded-usernames"><?php esc_html_e('Excluded usernames', 'group-password-reset'); ?></label>
                    <textarea id="gpr-excluded-usernames" name="gpr_excluded_usernames" rows="4"><?php echo esc_textarea($excludedUsernames); ?></textarea>
                    <p class="description"><?php esc_html_e('Enter usernames separated by commas. These users will be skipped and listed in the results.', 'group-password-reset'); ?></p>
                </div>

                <div class="gpr-field gpr-confirmation">
                    <label>
                        <input type="checkbox" name="gpr_confirm_reset" value="1" required>
                        <?php esc_html_e('I understand this will invalidate current passwords for the selected users.', 'group-password-reset'); ?>
                    </label>
                </div>

                <div id="gpr-progress-panel" class="gpr-progress" hidden aria-live="polite">
                    <p class="gpr-progress__status"><?php esc_html_e('Preparing password reset job…', 'group-password-reset'); ?></p>
                    <progress class="gpr-progress__bar" value="0" max="100"></progress>
                    <p class="gpr-progress__meta"></p>
                </div>

                <div class="gpr-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Reset Passwords', 'group-password-reset'); ?></button>
                    <p class="description"><?php echo esc_html(sprintf(__('JavaScript-enabled browsers process accounts in batches of %d. Without JavaScript, the reset runs on form submission and returns to this page with the results.', 'group-password-reset'), GPR_CHUNK_SIZE)); ?></p>
                </div>
            </form>
        </div>

        <div class="gpr-card">
            <h2><?php esc_html_e('What happens', 'group-password-reset'); ?></h2>
            <ol class="gpr-steps">
                <li><?php esc_html_e('Select a role or leave the target on All users.', 'group-password-reset'); ?></li>
                <li><?php esc_html_e('List any usernames that must be excluded from the reset.', 'group-password-reset'); ?></li>
                <li><?php esc_html_e('Start the reset. Each affected user gets a secure password reset link by email.', 'group-password-reset'); ?></li>
                <li><?php esc_html_e('Review the summary and per-user results on this screen.', 'group-password-reset'); ?></li>
            </ol>
        </div>

        <div id="gpr-results-panel" class="gpr-card"<?php echo $resultsPayload ? '' : ' hidden'; ?>>
            <h2><?php esc_html_e('Latest results', 'group-password-reset'); ?></h2>
            <div id="gpr-results-summary">
                <?php if ($resultsPayload) : ?>
                    <?php gpr_render_summary($resultsPayload); ?>
                <?php endif; ?>
            </div>
            <table class="widefat striped gpr-results-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Username', 'group-password-reset'); ?></th>
                        <th><?php esc_html_e('Email', 'group-password-reset'); ?></th>
                        <th><?php esc_html_e('Role', 'group-password-reset'); ?></th>
                        <th><?php esc_html_e('Status', 'group-password-reset'); ?></th>
                        <th><?php esc_html_e('Details', 'group-password-reset'); ?></th>
                    </tr>
                </thead>
                <tbody id="gpr-results-body">
                    <?php if ($resultsPayload) : ?>
                        <?php foreach ($resultsPayload['results'] as $result) : ?>
                            <?php gpr_render_result_row($result); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

function gpr_get_screen_notice($resultsPayload) {
    if (!$resultsPayload) {
        return null;
    }

    $summary = $resultsPayload['summary'];

    if ($summary['failed'] > 0) {
        return array(
            'class' => 'notice-warning',
            'message' => sprintf(
                /* translators: 1: successful resets 2: failed resets 3: skipped users */
                __('Password reset finished with %1$d success, %2$d failed, and %3$d skipped.', 'group-password-reset'),
                $summary['success'],
                $summary['failed'],
                $summary['skipped']
            ),
        );
    }

    return array(
        'class' => 'notice-success',
        'message' => sprintf(
            /* translators: 1: successful resets 2: skipped users */
            __('Password reset finished with %1$d success and %2$d skipped.', 'group-password-reset'),
            $summary['success'],
            $summary['skipped']
        ),
    );
}

function gpr_render_summary($resultsPayload) {
    $summary = $resultsPayload['summary'];
    ?>
    <div class="gpr-summary-grid">
        <div><strong><?php esc_html_e('Scope', 'group-password-reset'); ?>:</strong> <?php echo esc_html($resultsPayload['scope_label']); ?></div>
        <div><strong><?php esc_html_e('Matched users', 'group-password-reset'); ?>:</strong> <?php echo esc_html((string) $summary['total']); ?></div>
        <div><strong><?php esc_html_e('Processed', 'group-password-reset'); ?>:</strong> <?php echo esc_html((string) $summary['processed']); ?></div>
        <div><strong><?php esc_html_e('Success', 'group-password-reset'); ?>:</strong> <?php echo esc_html((string) $summary['success']); ?></div>
        <div><strong><?php esc_html_e('Failed', 'group-password-reset'); ?>:</strong> <?php echo esc_html((string) $summary['failed']); ?></div>
        <div><strong><?php esc_html_e('Skipped', 'group-password-reset'); ?>:</strong> <?php echo esc_html((string) $summary['skipped']); ?></div>
    </div>
    <?php if (!empty($resultsPayload['excluded_usernames'])) : ?>
        <p><strong><?php esc_html_e('Excluded usernames', 'group-password-reset'); ?>:</strong> <?php echo esc_html($resultsPayload['excluded_usernames']); ?></p>
    <?php endif; ?>
    <?php
}

function gpr_render_result_row($result) {
    $statusClasses = array(
        'success' => 'gpr-status gpr-status--success',
        'failed' => 'gpr-status gpr-status--failed',
        'skipped' => 'gpr-status gpr-status--skipped',
    );

    $statusClass = isset($statusClasses[$result['status']]) ? $statusClasses[$result['status']] : 'gpr-status';
    ?>
    <tr>
        <td><?php echo esc_html($result['username']); ?></td>
        <td><?php echo esc_html($result['email']); ?></td>
        <td><?php echo esc_html($result['role']); ?></td>
        <td><span class="<?php echo esc_attr($statusClass); ?>"><?php echo esc_html(ucfirst($result['status'])); ?></span></td>
        <td><?php echo esc_html($result['message']); ?></td>
    </tr>
    <?php
}

add_action('admin_init', 'gpr_register_settings');
add_action('admin_menu', 'gpr_add_admin_menu');
add_action('admin_enqueue_scripts', 'gpr_enqueue_admin_assets');
add_action('admin_post_gpr_reset_passwords', 'gpr_handle_reset_request');
add_filter('plugin_action_links', 'gpr_add_plugin_action_links', 10, 2);
add_filter('plugin_row_meta', 'gpr_add_plugin_row_meta', 10, 2);
add_action('admin_footer', 'gpr_render_plugin_details_modal');
