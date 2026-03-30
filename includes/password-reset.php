<?php
/**
 * Reset services and AJAX handlers for Group Password Reset.
 *
 * @package GroupPasswordReset
 */

if (!defined('ABSPATH')) {
    exit;
}

function gpr_sanitize_excluded_usernames($excludedUsernames) {
    if (!is_string($excludedUsernames)) {
        return '';
    }

    $usernames = preg_split('/[\s,]+/', $excludedUsernames);
    $usernames = array_filter(array_map('sanitize_user', $usernames));
    $usernames = array_unique($usernames);

    return implode(', ', $usernames);
}

function gpr_get_excluded_usernames_list($excludedUsernames = null) {
    $rawValue = is_string($excludedUsernames) ? $excludedUsernames : (string) get_option('gpr_excluded_usernames', '');
    $sanitized = gpr_sanitize_excluded_usernames($rawValue);
    $usernames = $sanitized === '' ? array() : array_map('trim', explode(',', $sanitized));
    $usernames[] = 'hackguard';

    return array_values(array_unique(array_filter($usernames)));
}

function gpr_get_available_roles() {
    $editableRoles = get_editable_roles();
    $roleLabels = array();

    foreach ($editableRoles as $roleKey => $roleConfig) {
        $roleLabels[$roleKey] = translate_user_role($roleConfig['name']);
    }

    asort($roleLabels);

    return $roleLabels;
}

function gpr_get_scope_label($role) {
    if ($role === '') {
        return __('All users', 'group-password-reset');
    }

    $roles = gpr_get_available_roles();

    return isset($roles[$role]) ? $roles[$role] : $role;
}

function gpr_get_target_users($role) {
    $args = array(
        'orderby' => 'login',
        'order' => 'ASC',
    );

    if ($role !== '') {
        $args['role'] = $role;
    }

    return get_users($args);
}

function gpr_format_user_role_label($user) {
    $editableRoles = get_editable_roles();
    $userRoles = array();

    if (is_array($user) && isset($user['roles'])) {
        $userRoles = (array) $user['roles'];
    } elseif (is_object($user) && isset($user->roles)) {
        $userRoles = (array) $user->roles;
    }

    $roles = array();

    foreach ($userRoles as $roleKey) {
        $roles[] = isset($editableRoles[$roleKey]) ? translate_user_role($editableRoles[$roleKey]['name']) : $roleKey;
    }

    return empty($roles) ? __('No role assigned', 'group-password-reset') : implode(', ', $roles);
}

function gpr_prepare_reset_run($role, $excludedUsernames) {
    $users = gpr_get_target_users($role);
    $excludedList = gpr_get_excluded_usernames_list($excludedUsernames);
    $queuedUsers = array();
    $skippedResults = array();

    foreach ($users as $user) {
        if (in_array($user->user_login, $excludedList, true)) {
            $skippedResults[] = array(
                'username' => $user->user_login,
                'email' => $user->user_email,
                'role' => gpr_format_user_role_label($user),
                'status' => 'skipped',
                'message' => __('Excluded from this reset run.', 'group-password-reset'),
            );
            continue;
        }

        $queuedUsers[] = array(
            'ID' => (int) $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'display_name' => $user->display_name,
            'roles' => array_values((array) $user->roles),
        );
    }

    return array(
        'total_users' => count($users),
        'queued_users' => array_values($queuedUsers),
        'skipped_results' => $skippedResults,
    );
}

function gpr_reset_single_user($user) {
    $userObject = get_user_by('id', (int) $user['ID']);

    if (!$userObject) {
        return array(
            'username' => $user['user_login'],
            'email' => $user['user_email'],
            'role' => gpr_format_user_role_label((object) $user),
            'status' => 'failed',
            'message' => __('User no longer exists.', 'group-password-reset'),
        );
    }

    wp_set_password(wp_generate_password(32, true, true), $userObject->ID);

    $resetKey = get_password_reset_key($userObject);

    if (is_wp_error($resetKey)) {
        return array(
            'username' => $userObject->user_login,
            'email' => $userObject->user_email,
            'role' => gpr_format_user_role_label($userObject),
            'status' => 'failed',
            'message' => __('Password changed, but the reset link could not be generated.', 'group-password-reset'),
        );
    }

    $resetLink = network_site_url(
        'wp-login.php?action=rp&key=' . rawurlencode($resetKey) . '&login=' . rawurlencode($userObject->user_login),
        'login'
    );

    $mailSent = wp_mail(
        $userObject->user_email,
        __('Password Reset', 'group-password-reset'),
        sprintf(
            /* translators: 1: display name 2: reset link */
            __("Hello %1\$s,\n\nYour password has been reset by a site administrator. Please use the link below to set a new password:\n\n%2\$s\n\nIf you did not expect this change, contact the site administrator immediately.", 'group-password-reset'),
            $userObject->display_name,
            $resetLink
        )
    );

    if (!$mailSent) {
        return array(
            'username' => $userObject->user_login,
            'email' => $userObject->user_email,
            'role' => gpr_format_user_role_label($userObject),
            'status' => 'failed',
            'message' => __('Password reset completed, but the email notification failed to send.', 'group-password-reset'),
        );
    }

    return array(
        'username' => $userObject->user_login,
        'email' => $userObject->user_email,
        'role' => gpr_format_user_role_label($userObject),
        'status' => 'success',
        'message' => __('Password reset email sent.', 'group-password-reset'),
    );
}

function gpr_get_job_storage_key() {
    return 'gpr_job_' . get_current_user_id();
}

function gpr_get_results_storage_key() {
    return 'gpr_results_' . get_current_user_id();
}

function gpr_store_flash_results($payload) {
    set_transient(gpr_get_results_storage_key(), $payload, GPR_RESULTS_TRANSIENT_TTL);
}

function gpr_get_flash_results() {
    $payload = get_transient(gpr_get_results_storage_key());

    if ($payload !== false) {
        delete_transient(gpr_get_results_storage_key());
    }

    return $payload ?: null;
}

function gpr_build_summary($job) {
    return array(
        'total' => $job['total_users'],
        'queued' => $job['queued_total'],
        'processed' => $job['summary']['success'] + $job['summary']['failed'] + $job['summary']['skipped'],
        'success' => $job['summary']['success'],
        'failed' => $job['summary']['failed'],
        'skipped' => $job['summary']['skipped'],
    );
}

function gpr_get_job_batch_storage_key($suffix) {
    return 'gpr_job_batch_' . get_current_user_id() . '_' . $suffix;
}

function gpr_store_job_batch_results($batchKey, $results) {
    set_transient($batchKey, $results, GPR_JOB_TRANSIENT_TTL);
}

function gpr_get_all_job_results($job) {
    $results = isset($job['initial_results']) ? $job['initial_results'] : array();

    foreach ($job['batch_keys'] as $batchKey) {
        $batchResults = get_transient($batchKey);
        if (is_array($batchResults)) {
            $results = array_merge($results, $batchResults);
        }
    }

    return $results;
}

function gpr_delete_job_batch_results($job) {
    foreach ($job['batch_keys'] as $batchKey) {
        delete_transient($batchKey);
    }
}

function gpr_ajax_start_job() {
    check_ajax_referer('gpr_job_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized.', 'group-password-reset'), 403);
    }

    $role = isset($_POST['role']) ? sanitize_key(wp_unslash($_POST['role'])) : '';
    $excludedUsernames = isset($_POST['excluded_usernames']) ? gpr_sanitize_excluded_usernames(wp_unslash($_POST['excluded_usernames'])) : '';

    update_option('gpr_excluded_usernames', $excludedUsernames);

    $run = gpr_prepare_reset_run($role, $excludedUsernames);
    $job = array(
        'role' => $role,
        'scope_label' => gpr_get_scope_label($role),
        'excluded_usernames' => $excludedUsernames,
        'total_users' => $run['total_users'],
        'queued_total' => count($run['queued_users']),
        'remaining_users' => $run['queued_users'],
        'initial_results' => $run['skipped_results'],
        'batch_keys' => array(),
        'summary' => array(
            'success' => 0,
            'failed' => 0,
            'skipped' => count($run['skipped_results']),
        ),
    );

    set_transient(gpr_get_job_storage_key(), $job, GPR_JOB_TRANSIENT_TTL);

    wp_send_json_success(
        array(
            'summary' => gpr_build_summary($job),
            'scopeLabel' => $job['scope_label'],
            'hasQueuedUsers' => !empty($job['remaining_users']),
            'results' => $job['initial_results'],
            'excludedUsernames' => $excludedUsernames,
        )
    );
}

function gpr_ajax_process_job() {
    check_ajax_referer('gpr_job_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized.', 'group-password-reset'), 403);
    }

    $job = get_transient(gpr_get_job_storage_key());

    if (!is_array($job)) {
        wp_send_json_error(__('No reset job is currently active.', 'group-password-reset'), 400);
    }

    $batch = array_slice($job['remaining_users'], 0, GPR_CHUNK_SIZE);
    $batchResults = array();

    foreach ($batch as $user) {
        $result = gpr_reset_single_user($user);
        $batchResults[] = $result;

        if ($result['status'] === 'success') {
            $job['summary']['success']++;
        } elseif ($result['status'] === 'failed') {
            $job['summary']['failed']++;
        }
    }

    if (!empty($batchResults)) {
        $batchKey = gpr_get_job_batch_storage_key(count($job['batch_keys']));
        gpr_store_job_batch_results($batchKey, $batchResults);
        $job['batch_keys'][] = $batchKey;
    }

    $job['remaining_users'] = array_slice($job['remaining_users'], count($batch));
    $completed = empty($job['remaining_users']);
    $summary = gpr_build_summary($job);

    if ($completed) {
        gpr_store_flash_results(
            array(
                'summary' => $summary,
                'results' => gpr_get_all_job_results($job),
                'scope_label' => $job['scope_label'],
                'excluded_usernames' => $job['excluded_usernames'],
                'mode' => 'async',
            )
        );
        gpr_delete_job_batch_results($job);
        delete_transient(gpr_get_job_storage_key());
    } else {
        set_transient(gpr_get_job_storage_key(), $job, GPR_JOB_TRANSIENT_TTL);
    }

    wp_send_json_success(
        array(
            'results' => $batchResults,
            'summary' => $summary,
            'scopeLabel' => $job['scope_label'],
            'excludedUsernames' => $job['excluded_usernames'],
            'completed' => $completed,
        )
    );
}

add_action('wp_ajax_gpr_start_job', 'gpr_ajax_start_job');
add_action('wp_ajax_gpr_process_job', 'gpr_ajax_process_job');
