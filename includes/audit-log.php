<?php
/**
 * Audit log storage and schema management for Group Password Reset.
 *
 * @package GroupPasswordReset
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gpr_get_audit_log_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'gpr_audit_log';
}

function gpr_get_audit_schema_version() {
	return '1.0.0';
}

function gpr_get_audit_schema_option_name() {
	return 'gpr_audit_schema_version';
}

function gpr_maybe_upgrade_audit_schema() {
	global $wpdb;

	if ( ! $wpdb instanceof wpdb ) {
		return;
	}

	$installed_version = (string) get_option( gpr_get_audit_schema_option_name(), '' );
	$target_version    = gpr_get_audit_schema_version();
	$table_name        = gpr_get_audit_log_table_name();
	$table_exists      = $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

	if ( $installed_version === $target_version ) {
		return;
	}

	if ( $table_exists && '' === $installed_version ) {
		update_option( gpr_get_audit_schema_option_name(), $target_version, false );
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		job_id char(36) NOT NULL,
		event_type varchar(50) NOT NULL,
		actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		actor_login_snapshot varchar(60) NOT NULL DEFAULT '',
		scope_key varchar(100) NOT NULL DEFAULT '',
		scope_label varchar(191) NOT NULL DEFAULT '',
		notify_users tinyint(1) unsigned NOT NULL DEFAULT 0,
		total_users int(11) unsigned NOT NULL DEFAULT 0,
		queued_total int(11) unsigned NOT NULL DEFAULT 0,
		success_total int(11) unsigned NOT NULL DEFAULT 0,
		failed_total int(11) unsigned NOT NULL DEFAULT 0,
		skipped_total int(11) unsigned NOT NULL DEFAULT 0,
		message text NULL,
		created_at_gmt datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY job_id (job_id),
		KEY event_type (event_type),
		KEY actor_user_id (actor_user_id),
		KEY created_at_gmt (created_at_gmt)
	) {$charset_collate};";

	dbDelta( $sql );
	update_option( gpr_get_audit_schema_option_name(), $target_version, false );
}

function gpr_log_job_event( $job, $event_type, $message = '' ) {
	global $wpdb;

	if ( ! $wpdb instanceof wpdb || ! is_array( $job ) ) {
		return false;
	}

	$summary = isset( $job['summary'] ) && is_array( $job['summary'] ) ? $job['summary'] : array();

	return false !== $wpdb->insert(
		gpr_get_audit_log_table_name(),
		array(
			'job_id'               => isset( $job['job_id'] ) ? $job['job_id'] : '',
			'event_type'           => $event_type,
			'actor_user_id'        => isset( $job['owner_user_id'] ) ? (int) $job['owner_user_id'] : 0,
			'actor_login_snapshot' => isset( $job['actor_login_snapshot'] ) ? $job['actor_login_snapshot'] : '',
			'scope_key'            => isset( $job['role'] ) ? $job['role'] : '',
			'scope_label'          => isset( $job['scope_label'] ) ? $job['scope_label'] : '',
			'notify_users'         => empty( $job['skip_email'] ) ? 1 : 0,
			'total_users'          => isset( $job['total_users'] ) ? (int) $job['total_users'] : 0,
			'queued_total'         => isset( $job['queued_total'] ) ? (int) $job['queued_total'] : 0,
			'success_total'        => isset( $summary['success'] ) ? (int) $summary['success'] : 0,
			'failed_total'         => isset( $summary['failed'] ) ? (int) $summary['failed'] : 0,
			'skipped_total'        => isset( $summary['skipped'] ) ? (int) $summary['skipped'] : 0,
			'message'              => sanitize_textarea_field( $message ),
			'created_at_gmt'       => current_time( 'mysql', true ),
		),
		array(
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
			'%d',
			'%d',
			'%d',
			'%d',
			'%d',
			'%d',
			'%s',
			'%s',
		)
	);
}
