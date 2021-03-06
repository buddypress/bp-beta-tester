<?php
/**
 * A plugin to switch between stable, beta or RC versions of BuddyPress.
 *
 * @package   bp-beta-tester
 * @author    The BuddyPress Community
 * @license   GPL-2.0+
 * @link      https://buddypress.org
 *
 * @wordpress-plugin
 * Plugin Name:       BP Beta Tester
 * Plugin URI:        https://github.com/buddypress/bp-beta-tester
 * Description:       A plugin to switch between stable, beta or RC versions of BuddyPress.
 * Version:           1.2.0
 * Author:            The BuddyPress Community
 * Author URI:        https://buddypress.org
 * Text Domain:       bp-beta-tester
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages/
 * Network:           True
 * GitHub Plugin URI: https://github.com/buddypress/bp-beta-tester
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Main Class
 *
 * @since 1.0.0
 */
final class BP_Beta_Tester {
	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin
	 */
	private function __construct() {
		$this->inc();
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since 1.0.0
	 */
	public static function start() {

		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Load needed files.
	 *
	 * @since 1.0.0
	 */
	private function inc() {
		$inc_path = plugin_dir_path( __FILE__ ) . 'inc/';

		require $inc_path . 'globals.php';
		require $inc_path . 'functions.php';
	}
}

/**
 * Start plugin.
 *
 * @since 1.0.0
 *
 * @return BP_Beta_Tester The main instance of the plugin.
 */
function bp_beta_tester() {
	if ( ! is_admin() ) {
		return;
	}

	return BP_Beta_Tester::start();
}
add_action( 'plugins_loaded', 'bp_beta_tester', 8 );
