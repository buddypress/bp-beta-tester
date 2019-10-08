<?php
/**
 * A plugin to switch between stable, beta or RC versions of BuddyPress.
 *
 * @package   buddypress-beta-tester
 * @author    imath
 * @license   GPL-2.0+
 * @link      https://imathi.eu
 *
 * @wordpress-plugin
 * Plugin Name:       BuddyPress Beta Tester
 * Plugin URI:        https://github.com/imath/buddypress-beta-tester
 * Description:       A plugin to switch between stable, beta or RC versions of BuddyPress.
 * Version:           1.0.0-alpha
 * Author:            imath
 * Author URI:        https://github.com/imath
 * Text Domain:       carte-de-survol
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages/
 * GitHub Plugin URI: https://github.com/imath/buddypress-beta-tester
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Main Class
 *
 * @since 1.0.0
 */
final class BuddyPress_Beta_Tester {
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
 * @return BuddyPress_Beta_Tester The main instance of the plugin.
 */
function buddypress_beta_tester() {
	if ( ! is_admin() ) {
		return;
	}

	return BuddyPress_Beta_Tester::start();
}
add_action( 'plugins_loaded', 'buddypress_beta_tester', 8 );
