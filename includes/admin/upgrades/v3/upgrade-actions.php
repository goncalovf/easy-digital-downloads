<?php
/**
 * v3 Upgrade Actions
 *
 * @package     EDD
 * @subpackage  Admin/Upgrades/v3
 * @copyright   Copyright (c) 2020, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.0
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Handles the 3.0 upgrade process.
 *
 * This loops through all upgrades that have not yet been completed, and steps through each process.
 *
 * @since 3.0
 * @return void
 */
function edd_process_v3_upgrade() {
	check_ajax_referer( 'edd_process_v3_upgrade' );

	$all_upgrades = edd_get_v30_upgrades();
	$upgrade_keys = array_keys( $all_upgrades );

	$upgrade_key = ! empty( $_POST['upgrade_key'] ) && array_key_exists( $_POST['upgrade_key'], $all_upgrades )
		? $_POST['upgrade_key']
		: reset( $upgrade_keys ); // First item in list.

	if ( ! array_key_exists( $upgrade_key, $all_upgrades ) ) {
		wp_send_json_error( __( 'This is not a valid 3.0 upgrade.', 'easy-digital-downloads' ) );
	}

	$step = ! empty( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;

	// If we have a step already saved, use that instead.
	// This is commented out for now because some changes are required in the migration processes first.
	/*$saved_step = get_option( sprintf( 'edd_v3_migration_%s_step', sanitize_key( $upgrade_key ) ) );
	if ( ! empty( $saved_step ) ) {
		$step = absint( $saved_step );
	}*/

	$class_name = $all_upgrades[ $upgrade_key ]['class'];

	// Load the required classes.
	require_once EDD_PLUGIN_DIR . 'includes/admin/reporting/export/class-batch-export.php';
	do_action( 'edd_batch_export_class_include', $class_name );

	if ( ! class_exists( $class_name ) ) {
		wp_send_json_error( __( 'Error loading migration class.', 'easy-digital-downloads' ) );
	}

	error_log( $class_name );                                                                                                                                                                                                                                                                                                                                                                                                                   // @todo remove                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     // @t remove

	/** @var \EDD_Batch_Export $export */
	$export = new $class_name( $step );

	if ( ! $export->can_export() ) {
		wp_die( -1, 403, array( 'response' => 403 ) );
	}

	//$was_processed       = $export->process_step();                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    // @todo remove
	$was_processed       = false; // @todo remove                                                                                                                                                                                                                                                                                                                                                                                                       // @todo remove
	$percentage_complete = $export->get_percentage_complete();
	$percentage_complete = 100; // @todo remove

	// Build some shared args.
	$response_args = array(
		'upgrade_processed' => $upgrade_key,
		'nonce'             => wp_create_nonce( 'edd_process_v3_upgrade' )
	);

	if ( $was_processed ) {
		// Data was processed, which means we'll want to repeat this upgrade again next time.
		wp_send_json_success( wp_parse_args( array(
			'upgrade_completed' => false,
			'next_step'         => $step + 1,
			'next_upgrade'      => $upgrade_key,
			'percentage'        => $percentage_complete,
		), $response_args ) );
	} else {
		// No data was processed, which means it's time to move on to the next upgrade.

		// Figure out which upgrade is next.
		$remaining_upgrades = array_slice( $upgrade_keys, array_search( $upgrade_key, $upgrade_keys ) + 1 );
		$next_upgrade       = ! empty( $remaining_upgrades ) ? reset( $remaining_upgrades ) : false;

		wp_send_json_success( wp_parse_args( array(
			'upgrade_completed' => true,
			'next_step'         => 1,
			'next_upgrade'      => $next_upgrade,
			'percentage'        => 0
		), $response_args ) );
	}

}

add_action( 'wp_ajax_edd_process_v3_upgrade', 'edd_process_v3_upgrade' );
