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
                'requiresWp' => GPR_REQUIRES_AT_LEAST,
                'requiresPhp' => GPR_REQUIRES_PHP,
                'testedUpTo' => GPR_TESTED_UP_TO,
                'authorName' => GPR_AUTHOR_NAME,
                'authorUrl' => GPR_AUTHOR_URI,
                'pluginUrl' => GPR_PLUGIN_URI,
                'releaseUrl' => gpr_get_release_url(),
                'downloadUrl' => gpr_get_download_url(),
                'repoUrl' => GPR_REPO_URL,
                'bannerUrl' => GPR_PLUGIN_URL . 'assets/images/plugin-banner.png',
                'activeInstalls' => __('Direct GitHub release', 'group-password-reset'),
                'lastUpdated' => sprintf(
                    /* translators: %s: plugin version */
                    __('Version %s release', 'group-password-reset'),
                    GPR_VERSION
                ),
            ),
            'messages' => array(
                'startError' => __('Unable to start the password reset job.', 'group-password-reset'),
                'processError' => __('Unable to continue the password reset job.', 'group-password-reset'),
                'complete' => __('Password reset complete.', 'group-password-reset'),
                'preparingJob' => __('Preparing password reset job…', 'group-password-reset'),
                'processingJob' => __('Processing password resets…', 'group-password-reset'),
                'noQueuedUsers' => __('No queued users remained after exclusions.', 'group-password-reset'),
                'requestFailed' => __('Request failed.', 'group-password-reset'),
                'allUsers' => __('All users', 'group-password-reset'),
                'scope' => __('Scope', 'group-password-reset'),
                'matchedUsers' => __('Matched users', 'group-password-reset'),
                'processed' => __('Processed', 'group-password-reset'),
                'success' => __('Success', 'group-password-reset'),
                'failed' => __('Failed', 'group-password-reset'),
                'skipped' => __('Skipped', 'group-password-reset'),
                'excludedUsernames' => __('Excluded usernames', 'group-password-reset'),
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

add_action('admin_init', 'gpr_register_settings');
add_action('admin_menu', 'gpr_add_admin_menu');
add_action('admin_enqueue_scripts', 'gpr_enqueue_admin_assets');
add_action('admin_post_gpr_reset_passwords', 'gpr_handle_reset_request');
add_filter('plugin_action_links', 'gpr_add_plugin_action_links', 10, 2);
add_filter('plugin_row_meta', 'gpr_add_plugin_row_meta', 10, 2);
add_action('admin_footer', 'gpr_render_plugin_details_modal');
