<?php
/**
 * Globals.
 *
 * @package   buddypress-beta-tester
 * @subpackage \inc\globals
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Register plugin globals.
 *
 * @since 1.0.0
 */
function buddypress_beta_tester_globals() {
	$bpbt = buddypress_beta_tester();

	$bpbt->version  = '1.0.0-alpha';
	$bpbt->dir      = plugin_dir_path( dirname( __FILE__ ) );
	$bpbt->inc_path = plugin_dir_path( __FILE__ );
}
add_action( 'plugins_loaded', 'buddypress_beta_tester_globals' );
