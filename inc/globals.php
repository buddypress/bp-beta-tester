<?php
/**
 * Globals.
 *
 * @package   bp-beta-tester
 * @subpackage \inc\globals
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Register plugin globals.
 *
 * @since 1.0.0
 */
function bp_beta_tester_globals() {
	$bpbt = bp_beta_tester();

	$bpbt->version  = '1.0.0-alpha';
	$bpbt->dir      = plugin_dir_path( dirname( __FILE__ ) );
	$bpbt->inc_path = plugin_dir_path( __FILE__ );
}
add_action( 'plugins_loaded', 'bp_beta_tester_globals' );
