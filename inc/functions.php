<?php
/**
 * Functions.
 *
 * @package   buddypress-beta-tester
 * @subpackage \inc\functions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Sort Callback for BuddyPress versions.
 *
 * @since 1.0.0
 *
 * @param string $a The BuddyPress version to compare.
 * @param string $b The BuddyPress version to compare with.
 * @return boolean  True if $a < $b.
 */
function buddypress_beta_tester_sort_versions( $a, $b ) {
	return version_compare( $a, $b, '<' );
}

/**
 * Display the Tools page.
 *
 * @since 1.0.0
 */
function buddypress_beta_tester_admin_page() {
	include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

	$api = plugins_api(
		'plugin_information',
		array(
			'slug'   => 'buddypress',
			'fields' => array(
				'tags' => true,
			),
		)
	);

	$versions = $api->versions;
	uksort( $versions, 'buddypress_beta_tester_sort_versions' );
	$releases = array_keys( $versions );
	$latest   = reset( $releases );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'BuddyPress Beta Tester', 'buddypress-beta-tester' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: the %s placeholder is for the BuddyPress release tag. */
				esc_html__( 'The latest BuddyPress release is: %s', 'buddypress-beta-tester' ),
				esc_html( $latest )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Add a Tools submenu.
 *
 * @since 1.0.0
 */
function buddypress_beta_tester_admin_menu() {
	add_management_page(
		__( 'BuddyPress Beta Tester', 'buddypress-beta-tester' ),
		__( 'BetaTest BuddyPress', 'buddypress-beta-tester' ),
		'manage_options',
		'buddypress-beta-tester',
		'buddypress_beta_tester_admin_page'
	);
}
add_action( 'admin_menu', 'buddypress_beta_tester_admin_menu' );
