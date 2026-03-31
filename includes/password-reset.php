<?php
/**
 * Reset services and AJAX handlers for Group Password Reset.
 *
 * @package GroupPasswordReset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gpr_get_all_users_scope_key() {
	return 'all_users';
}

function gpr_get_admin_excluding_current_scope_key() {
	return 'administrator_excluding_current_user';
}

function gpr_normalize_scope_role( $role ) {
	if ( gpr_get_all_users_scope_key() === $role ) {
		return '';
	}

	if ( gpr_get_admin_excluding_current_scope_key() === $role ) {
		return 'administrator';
	}

	return $role;
}

function gpr_sanitize_excluded_usernames( $excluded_usernames ) {
	if ( ! is_string( $excluded_usernames ) ) {
		return '';
	}

	$usernames = preg_split( '/[\s,]+/', $excluded_usernames );
	$usernames = array_filter( array_map( 'sanitize_user', $usernames ) );
	$usernames = array_unique( $usernames );

	return implode( ', ', $usernames );
}

function gpr_get_excluded_usernames_list( $excluded_usernames = null ) {
	$raw_value = is_string( $excluded_usernames ) ? $excluded_usernames : (string) get_option( 'gpr_excluded_usernames', '' );
	$sanitized = gpr_sanitize_excluded_usernames( $raw_value );
	$usernames = '' === $sanitized ? array() : array_map( 'trim', explode( ',', $sanitized ) );

	return array_values( array_unique( array_filter( $usernames ) ) );
}

function gpr_get_available_roles() {
	$editable_roles = get_editable_roles();
	$role_labels    = array();
	$scopes         = array();

	foreach ( $editable_roles as $role_key => $role_config ) {
		$role_labels[ $role_key ] = translate_user_role( $role_config['name'] );
	}

	asort( $role_labels );

	$scopes[ gpr_get_all_users_scope_key() ] = __( 'All users', 'group-password-reset' );

	foreach ( $role_labels as $role_key => $role_label ) {
		if ( 'administrator' === $role_key ) {
			$scopes[ gpr_get_admin_excluding_current_scope_key() ] = __( 'Administrator (excluding current user)', 'group-password-reset' );
		}

		$scopes[ $role_key ] = $role_label;
	}

	return $scopes;
}

function gpr_get_scope_label( $role ) {
	$normalized_role = gpr_normalize_scope_role( $role );

	if ( '' === $normalized_role ) {
		return __( 'All users', 'group-password-reset' );
	}

	$roles = gpr_get_available_roles();

	return isset( $roles[ $role ] ) ? $roles[ $role ] : $role;
}

function gpr_get_target_users( $role ) {
	return get_users( gpr_get_target_user_query_args( $role ) );
}

function gpr_get_target_user_query_args( $role, $number = 0, $offset = 0, $count_total = false ) {
	$args = array(
		'orderby' => 'ID',
		'order'   => 'ASC',
	);

	if ( $number > 0 ) {
		$args['number'] = $number;
		$args['offset'] = $offset;
	}

	if ( $count_total ) {
		$args['count_total'] = true;
	}

	$normalized_role = gpr_normalize_scope_role( $role );

	if ( '' !== $normalized_role ) {
		$args['role'] = $normalized_role;
	}

	return $args;
}

function gpr_count_target_users( $role ) {
	$query = new WP_User_Query( gpr_get_target_user_query_args( $role, 1, 0, true ) );

	return (int) $query->get_total();
}

function gpr_format_user_role_label( $user ) {
	$editable_roles = get_editable_roles();
	$user_roles     = array();

	if ( is_array( $user ) && isset( $user['roles'] ) ) {
		$user_roles = (array) $user['roles'];
	} elseif ( is_object( $user ) && isset( $user->roles ) ) {
		$user_roles = (array) $user->roles;
	}

	$roles = array();

	foreach ( $user_roles as $role_key ) {
		$roles[] = isset( $editable_roles[ $role_key ] ) ? translate_user_role( $editable_roles[ $role_key ]['name'] ) : $role_key;
	}

	return empty( $roles ) ? __( 'No role assigned', 'group-password-reset' ) : implode( ', ', $roles );
}

function gpr_prepare_reset_run( $role, $excluded_usernames ) {
	$total_users   = gpr_count_target_users( $role );
	$excluded_data = gpr_get_excluded_run_data( $role, $excluded_usernames );

	return array(
		'total_users'     => $total_users,
		'queued_total'    => max( 0, $total_users - count( $excluded_data['excluded_ids'] ) ),
		'excluded_ids'    => $excluded_data['excluded_ids'],
		'skipped_results' => $excluded_data['skipped_results'],
	);
}

function gpr_get_excluded_run_data( $role, $excluded_usernames ) {
	$excluded_ids    = array();
	$skipped_results = array();
	$normalized_role = gpr_normalize_scope_role( $role );

	foreach ( gpr_get_excluded_usernames_list( $excluded_usernames ) as $username ) {
		$user = get_user_by( 'login', $username );

		if ( ! $user instanceof WP_User ) {
			continue;
		}

		if ( '' !== $normalized_role && ! in_array( $normalized_role, (array) $user->roles, true ) ) {
			continue;
		}

		$excluded_ids[]    = (int) $user->ID;
		$skipped_results[] = array(
			'username' => $user->user_login,
			'email'    => $user->user_email,
			'role'     => gpr_format_user_role_label( $user ),
			'status'   => 'skipped',
			'message'  => __( 'Excluded from this reset run.', 'group-password-reset' ),
		);
	}

	if ( gpr_get_admin_excluding_current_scope_key() === $role ) {
		$current_user = wp_get_current_user();

		if ( $current_user instanceof WP_User && in_array( 'administrator', (array) $current_user->roles, true ) ) {
			$current_user_id = (int) $current_user->ID;

			if ( ! in_array( $current_user_id, $excluded_ids, true ) ) {
				$excluded_ids[]    = $current_user_id;
				$skipped_results[] = array(
					'username' => $current_user->user_login,
					'email'    => $current_user->user_email,
					'role'     => gpr_format_user_role_label( $current_user ),
					'status'   => 'skipped',
					'message'  => __( 'Current administrator account excluded from this reset run.', 'group-password-reset' ),
				);
			}
		}
	}

	return array(
		'excluded_ids'    => array_values( array_unique( $excluded_ids ) ),
		'skipped_results' => $skipped_results,
	);
}

function gpr_normalize_job_user( $user ) {
	return array(
		'ID'           => (int) $user->ID,
		'user_login'   => $user->user_login,
		'user_email'   => $user->user_email,
		'display_name' => $user->display_name,
		'roles'        => array_values( (array) $user->roles ),
	);
}

function gpr_get_job_batch_users( &$job ) {
	$batch     = array();
	$remaining = $job['total_users'] - $job['offset'];

	if ( $remaining <= 0 ) {
		return $batch;
	}

	$query_size = min( GPR_CHUNK_SIZE, $remaining );
	$users      = get_users( gpr_get_target_user_query_args( $job['role'], $query_size, $job['offset'] ) );

	if ( empty( $users ) ) {
		$job['offset'] = $job['total_users'];
		return $batch;
	}

	$job['offset'] += count( $users );

	foreach ( $users as $user ) {
		if ( in_array( (int) $user->ID, $job['excluded_ids'], true ) ) {
			continue;
		}

		$batch[] = gpr_normalize_job_user( $user );
	}

	return $batch;
}

function gpr_reset_single_user( $user ) {
	$user_object = get_user_by( 'id', (int) $user['ID'] );

	if ( ! $user_object ) {
		return array(
			'username' => $user['user_login'],
			'email'    => $user['user_email'],
			'role'     => gpr_format_user_role_label( (object) $user ),
			'status'   => 'failed',
			'message'  => __( 'User no longer exists.', 'group-password-reset' ),
		);
	}

	wp_set_password( wp_generate_password( 32, true, true ), $user_object->ID );

	$reset_key = get_password_reset_key( $user_object );

	if ( is_wp_error( $reset_key ) ) {
		return array(
			'username' => $user_object->user_login,
			'email'    => $user_object->user_email,
			'role'     => gpr_format_user_role_label( $user_object ),
			'status'   => 'failed',
			'message'  => __( 'Password changed, but the reset link could not be generated.', 'group-password-reset' ),
		);
	}

	$reset_link = network_site_url(
		'wp-login.php?action=rp&key=' . rawurlencode( $reset_key ) . '&login=' . rawurlencode( $user_object->user_login ),
		'login'
	);

	$mail_sent = wp_mail(
		$user_object->user_email,
		__( 'Password Reset', 'group-password-reset' ),
		sprintf(
			/* translators: 1: display name 2: reset link */
			__( "Hello %1\$s,\n\nYour password has been reset by a site administrator. Please use the link below to set a new password:\n\n%2\$s\n\nIf you did not expect this change, contact the site administrator immediately.", 'group-password-reset' ),
			$user_object->display_name,
			$reset_link
		)
	);

	if ( ! $mail_sent ) {
		return array(
			'username' => $user_object->user_login,
			'email'    => $user_object->user_email,
			'role'     => gpr_format_user_role_label( $user_object ),
			'status'   => 'failed',
			'message'  => __( 'Password reset completed, but the email notification failed to send.', 'group-password-reset' ),
		);
	}

	return array(
		'username' => $user_object->user_login,
		'email'    => $user_object->user_email,
		'role'     => gpr_format_user_role_label( $user_object ),
		'status'   => 'success',
		'message'  => __( 'Password reset email sent.', 'group-password-reset' ),
	);
}

function gpr_generate_job_id() {
	return wp_generate_uuid4();
}

function gpr_is_valid_job_id( $job_id ) {
	return is_string( $job_id ) && 1 === preg_match( '/^[a-f0-9-]{36}$/i', $job_id );
}

function gpr_get_job_storage_key( $job_id ) {
	return 'gpr_job_' . $job_id;
}

function gpr_create_reset_job( $role, $excluded_usernames, $owner_user_id, $job_id = null ) {
	$job_id = $job_id ? $job_id : gpr_generate_job_id();
	$run    = gpr_prepare_reset_run( $role, $excluded_usernames );

	return array(
		'job_id'             => $job_id,
		'owner_user_id'      => (int) $owner_user_id,
		'role'               => $role,
		'scope_label'        => gpr_get_scope_label( $role ),
		'excluded_usernames' => $excluded_usernames,
		'total_users'        => $run['total_users'],
		'queued_total'       => $run['queued_total'],
		'offset'             => 0,
		'excluded_ids'       => $run['excluded_ids'],
		'initial_results'    => $run['skipped_results'],
		'batch_keys'         => array(),
		'summary'            => array(
			'success' => 0,
			'failed'  => 0,
			'skipped' => count( $run['skipped_results'] ),
		),
	);
}

function gpr_store_job( $job ) {
	set_transient( gpr_get_job_storage_key( $job['job_id'] ), $job, GPR_JOB_TRANSIENT_TTL );
}

function gpr_get_job( $job_id ) {
	return get_transient( gpr_get_job_storage_key( $job_id ) );
}

function gpr_current_user_owns_job( $job ) {
	return isset( $job['owner_user_id'] ) && get_current_user_id() === (int) $job['owner_user_id'];
}

function gpr_get_results_storage_key() {
	return 'gpr_results_' . get_current_user_id();
}

function gpr_store_flash_results( $payload ) {
	set_transient( gpr_get_results_storage_key(), $payload, GPR_RESULTS_TRANSIENT_TTL );
}

function gpr_get_flash_results() {
	$payload = get_transient( gpr_get_results_storage_key() );

	if ( false !== $payload ) {
		delete_transient( gpr_get_results_storage_key() );
	}

	return $payload ? $payload : null;
}

function gpr_build_summary( $job ) {
	return array(
		'total'     => $job['total_users'],
		'queued'    => $job['queued_total'],
		'processed' => $job['summary']['success'] + $job['summary']['failed'] + $job['summary']['skipped'],
		'success'   => $job['summary']['success'],
		'failed'    => $job['summary']['failed'],
		'skipped'   => $job['summary']['skipped'],
	);
}

function gpr_get_job_batch_storage_key( $job_id, $suffix ) {
	return 'gpr_job_batch_' . $job_id . '_' . $suffix;
}

function gpr_store_job_batch_results( $batch_key, $results ) {
	set_transient( $batch_key, $results, GPR_JOB_TRANSIENT_TTL );
}

function gpr_get_all_job_results( $job ) {
	$results = isset( $job['initial_results'] ) ? $job['initial_results'] : array();

	foreach ( $job['batch_keys'] as $batch_key ) {
		$batch_results = get_transient( $batch_key );
		if ( is_array( $batch_results ) ) {
			$results = array_merge( $results, $batch_results );
		}
	}

	return $results;
}

function gpr_delete_job_batch_results( $job ) {
	foreach ( $job['batch_keys'] as $batch_key ) {
		delete_transient( $batch_key );
	}
}

function gpr_finalize_job( $job, $mode ) {
	$summary = gpr_build_summary( $job );

	gpr_store_flash_results(
		array(
			'summary'            => $summary,
			'results'            => gpr_get_all_job_results( $job ),
			'scope_label'        => $job['scope_label'],
			'excluded_usernames' => $job['excluded_usernames'],
			'mode'               => $mode,
		)
	);

	gpr_delete_job_batch_results( $job );
	delete_transient( gpr_get_job_storage_key( $job['job_id'] ) );

	return $summary;
}

function gpr_process_job_batch( &$job ) {
	$batch         = gpr_get_job_batch_users( $job );
	$batch_results = array();

	foreach ( $batch as $user ) {
		$result          = gpr_reset_single_user( $user );
		$batch_results[] = $result;

		if ( 'success' === $result['status'] ) {
			++$job['summary']['success'];
		} elseif ( 'failed' === $result['status'] ) {
			++$job['summary']['failed'];
		}
	}

	if ( ! empty( $batch_results ) ) {
		$batch_key = gpr_get_job_batch_storage_key( $job['job_id'], count( $job['batch_keys'] ) );
		gpr_store_job_batch_results( $batch_key, $batch_results );
		$job['batch_keys'][] = $batch_key;
	}

	$completed = $job['offset'] >= $job['total_users'];
	$summary   = gpr_build_summary( $job );

	return array(
		'results'   => $batch_results,
		'summary'   => $summary,
		'completed' => $completed,
	);
}

function gpr_ajax_start_job() {
	check_ajax_referer( 'gpr_job_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized.', 'group-password-reset' ), 403 );
	}

	$role                   = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
	$raw_excluded_usernames = '';

	if ( isset( $_POST['excluded_usernames'] ) ) {
		$raw_excluded_usernames = sanitize_text_field( wp_unslash( $_POST['excluded_usernames'] ) );
	}

	$excluded_usernames = gpr_sanitize_excluded_usernames( $raw_excluded_usernames );

	update_option( 'gpr_excluded_usernames', $excluded_usernames );

	$job = gpr_create_reset_job( $role, $excluded_usernames, get_current_user_id() );

	if ( $job['queued_total'] > 0 ) {
		gpr_store_job( $job );
	} else {
		gpr_store_flash_results(
			array(
				'summary'            => gpr_build_summary( $job ),
				'results'            => $job['initial_results'],
				'scope_label'        => $job['scope_label'],
				'excluded_usernames' => $job['excluded_usernames'],
				'mode'               => 'async',
			)
		);
	}

	wp_send_json_success(
		array(
			'jobId'             => $job['queued_total'] > 0 ? $job['job_id'] : null,
			'summary'           => gpr_build_summary( $job ),
			'scopeLabel'        => $job['scope_label'],
			'hasQueuedUsers'    => $job['queued_total'] > 0,
			'results'           => $job['initial_results'],
			'excludedUsernames' => $excluded_usernames,
		)
	);
}

function gpr_ajax_process_job() {
	check_ajax_referer( 'gpr_job_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized.', 'group-password-reset' ), 403 );
	}

	$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';

	if ( ! gpr_is_valid_job_id( $job_id ) ) {
		wp_send_json_error( __( 'Invalid reset job ID.', 'group-password-reset' ), 400 );
	}

	$job = gpr_get_job( $job_id );

	if ( ! is_array( $job ) ) {
		wp_send_json_error( __( 'No reset job is currently active.', 'group-password-reset' ), 400 );
	}

	if ( ! gpr_current_user_owns_job( $job ) ) {
		wp_send_json_error( __( 'You do not have permission to continue this reset job.', 'group-password-reset' ), 403 );
	}

	$batch_state = gpr_process_job_batch( $job );

	if ( $batch_state['completed'] ) {
		gpr_finalize_job( $job, 'async' );
	} else {
		gpr_store_job( $job );
	}

	wp_send_json_success(
		array(
			'jobId'             => $job_id,
			'results'           => $batch_state['results'],
			'summary'           => $batch_state['summary'],
			'scopeLabel'        => $job['scope_label'],
			'excludedUsernames' => $job['excluded_usernames'],
			'completed'         => $batch_state['completed'],
		)
	);
}

add_action( 'wp_ajax_gpr_start_job', 'gpr_ajax_start_job' );
add_action( 'wp_ajax_gpr_process_job', 'gpr_ajax_process_job' );
