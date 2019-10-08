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
 * Get the url to get BuddyPress updates.
 *
 * @since 1.0.0
 */
function buddypress_beta_tester_get_updates_url() {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'upgrade-plugin',
				'plugin' => 'buddypress/bp-loader.php',
			),
			self_admin_url( 'update.php' )
		),
		'upgrade-plugin_buddypress/bp-loader.php'
	);
}

/**
 * Get the new site transient to use to get the requested version.
 *
 * @since 1.0.0
 *
 * @param  object|WP_Error $api     The Plugins API information about BuddyPress or WP_Error.
 * @param  string          $version The version to use for this upgrade or downgrade.
 * @return object                   The site transient to use for this upgrade.
 */
function buddypress_beta_tester_get_version( $api = null, $version = '' ) {
	$new_transient = null;

	if ( is_wp_error( $api ) || ! $api || ! $version ) {
		return $new_transient;
	}

	$updates     = get_site_transient( 'update_plugins' );
	$plugin_file = 'buddypress/bp-loader.php';

	if ( ! isset( $updates->response ) ) {
		$updates->response = array();
	}

	if ( ! isset( $updates->response[ $plugin_file ] ) ) {
		$icons = array();
		if ( isset( $api->icons ) )  {
			$icons = $api->icons;
		}

		$banners = array();
		if ( isset( $api->banners ) )  {
			$banners = $api->banners;
		}

		$banners_rtl = array();
		if ( isset( $api->banners_rtl ) )  {
			$banners_rtl = $api->banners_rtl;
		} else {
			$banners_rtl = $banners;
		}

		$updates->response[ $plugin_file ] = (object) array(
			'id'             => 'w.org/plugins/buddypress',
			'slug'           => 'buddypress',
			'plugin'         => $plugin_file,
			'new_version'    => $version,
			'url'            => 'https://wordpress.org/plugins/buddypress/',
			'package'        => $api->versions[ $version ],
			'icons'          => $icons,
			'banners'        => $banners,
			'banners_rtl'    => $banners_rtl,
			'upgrade_notice' => '',
		);

		$new_transient = $updates;
	} elseif ( isset( $updates->response[ $plugin_file ]->new_version ) && $version !== $updates->response[ $plugin_file ]->new_version ) {
		$updates->response[ $plugin_file ]->new_version    = $version;
		$updates->response[ $plugin_file ]->package        = $api->versions[ $version ];
		$updates->response[ $plugin_file ]->upgrade_notice = '';

		$new_transient = $updates;
	}

	return $new_transient;
}

/**
 * Get information about BuddyPress during page load & handle revert if needed.
 *
 * @since 1.0.0
 */
function buddypress_beta_tester_admin_load() {
	include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

	$bpbt = buddypress_beta_tester();
	$bpbt->api = plugins_api(
		'plugin_information',
		array(
			'slug'   => 'buddypress',
			'fields' => array(
				'tags'        => true,
				'icons'       => true,
				'banners_rtl' => true,
				'sections'    => false,
			),
		)
	);

	if ( ! is_wp_error( $bpbt->api ) && isset( $_GET['action'] ) && 'restore-stable' === $_GET['action'] ) {
		check_admin_referer( 'restore_stable_buddypress' );

		$stable = '';
		if ( isset( $_GET['stable'] ) && $_GET['stable'] ) {
			$stable = wp_unslash( $_GET['stable'] );
		}

		if ( isset( $bpbt->api->versions[ $stable ] ) ) {
			$plugin_file   = 'buddypress/bp-loader.php';
			$new_transient = buddypress_beta_tester_get_version( $bpbt->api, $stable );

			if ( ! is_null( $new_transient ) ) {
				set_site_transient( 'update_plugins', $new_transient );

				// We need to do this to make sure the redirect works as expected.
				$redirect_url = str_replace( '&amp;', '&', buddypress_beta_tester_get_updates_url() );

				wp_safe_redirect( $redirect_url );
				exit();
			}
		}
	}
}

/**
 * Display the Tools page.
 *
 * @since 1.0.0
 */
function buddypress_beta_tester_admin_page() {
	$bpbt            = buddypress_beta_tester();
	$latest           = '';
	$new_transient    = null;
	$is_latest_stable = false;

	if ( isset( $bpbt->api ) && $bpbt->api ) {
		$api = $bpbt->api;
	} else {
		$api = new WP_Error( 'unavailable_plugins_api', __( 'The Plugins API is unavailable.', 'buddypress_beta_tester' ) );
	}

	if ( ! is_wp_error( $api ) ) {
		$versions = $api->versions;

		// Sort versions so that latest are first.
		uksort( $versions, 'buddypress_beta_tester_sort_versions' );

		$releases         = array_keys( $versions );
		$latest           = reset( $releases );
		$is_latest_stable = false === strpos( $latest, '-' );
		$installed        = array();
		$plugin_file      = 'buddypress/bp-loader.php';
		$url              = '';
		$revert           = array();
		$action           = '';

		if ( file_exists( WP_PLUGIN_DIR .'/' . $plugin_file ) ) {
			$installed = get_plugin_data( WP_PLUGIN_DIR .'/buddypress/bp-loader.php', false, false );
		}

		if ( ! $installed ) {
			$action = sprintf(
				/* translators: the %s placeholder is for the BuddyPress release tag. */
				__( 'Install %s', 'buddypress-beta-tester' ),
				$latest
			);

			$url = wp_nonce_url(
				add_query_arg(
					array(
						'action'                 => 'install-plugin',
						'plugin'                 => 'buddypress',
						'buddypress-beta-tester' => $latest,
					),
					self_admin_url( 'update.php' )
				),
				'install-plugin_buddypress'
			);
		} elseif ( isset( $installed['Version'] ) ) {
			$action = sprintf(
				/* translators: the %s placeholder is for the BuddyPress release tag. */
				__( 'Upgrade to %s', 'buddypress-beta-tester' ),
				$latest
			);

			if ( $is_latest_stable ) {
				$url = self_admin_url( 'update-core.php' );
			} elseif ( false !== strpos( $installed['Version'], '-' ) ) {
				// Find the first stable version to be able to switch to it.
				foreach ( $versions as $version => $package ) {
					if ( false === strpos( $version, '-' ) ) {
						$revert = array(
							'url' => wp_nonce_url(
								add_query_arg(
									array(
										'action'  => 'restore-stable',
										'page'    => 'buddypress-beta-tester',
										'stable'  => $version
									),
									self_admin_url( 'tools.php' )
								),
								'restore_stable_buddypress'
							),
							'version' => $version
						);
						break;
					}
				}
			}

			if ( ! $is_latest_stable && version_compare( $installed['Version'], $latest, '<' ) ) {
				$url = buddypress_beta_tester_get_updates_url();

				$new_transient = buddypress_beta_tester_get_version( $api, $latest );

				if ( ! is_null( $new_transient ) ) {
					set_site_transient( 'update_plugins', $new_transient );
				}
			}
		}
	}
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
		<ul>
			<?php if ( $new_transient || $is_latest_stable || ! $installed ) : ?>
				<li>
					<a href="<?php echo esc_url( $url ); ?>" class="button button-primary">
						<?php echo esc_html( $action ); ?>
					</a>
				</li>
			<?php endif; ?>

			<?php if ( $revert['url'] ) : ?>
				<li>
					<a href="<?php echo esc_url( $revert['url'] ); ?>" class="button button-secondary">
						<?php
						printf(
							/* translators: the %s placeholder is for the BuddyPress release tag. */
							esc_html__( 'Revert to %s', 'buddypress-beta-tester' ),
							esc_html( $revert['version'] )
						);
						?>
					</a>
				</li>
			<?php endif; ?>
		</ul>

		<?php if ( ! $new_transient && $installed && ! $is_latest_stable ) : ?>
			<p class="description"><?php esc_html_e( 'You already have the latest release installed.', 'buddypress-beta-tester' ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Filter Plugin API arguments to eventually include the tags field.
 *
 * @since 1.0.0
 *
 * @param object $args   Plugin API arguments.
 * @param string $action The type of information being requested from the Plugin API.
 * @return object        The Plugin API arguments.
 */
function buddypress_beta_tester_plugins_api_args( $args = null, $action = '' ) {
	if ( 'plugin_information' !== $action || ! isset( $args->slug ) ) {
		return $args;
	}

	if ( 'buddypress' === $args->slug ) {
		$bpbt             = buddypress_beta_tester();
		$bpbt->beta_or_rc = '';

		if ( isset( $_GET['buddypress-beta-tester'] ) && $_GET['buddypress-beta-tester'] ) {
			$bpbt->beta_or_rc = wp_unslash( $_GET['buddypress-beta-tester'] );
		}

		if ( $bpbt->beta_or_rc ) {
			$args->fields = array_merge( $args->fields, array( 'tags' => true ) );
		}
	}

	return $args;
}
add_filter( 'plugins_api_args', 'buddypress_beta_tester_plugins_api_args', 10, 2 );

/**
 * Filter the Plugin API response results to eventually override the download link.
 *
 * @since 1.0.0
 *
 * @param object|WP_Error $res    Response object or WP_Error.
 * @param string          $action The type of information being requested from the Plugin API.
 * @param object          $args   Plugin API arguments.
 * @return object|WP_Error        The Plugin API response or WP_error.
 */
function buddypress_beta_tester_plugins_api( $res = null, $action = '', $args = array() ) {
	if ( is_wp_error( $res ) || 'plugin_information' !== $action || 'buddypress' !== $res->slug ) {
		return $res;
	}

	$bpbt       = buddypress_beta_tester();
	$beta_or_rc = '';

	if ( isset( $bpbt->beta_or_rc ) && $bpbt->beta_or_rc ) {
		$beta_or_rc = $bpbt->beta_or_rc;
		unset( $bpbt->beta_or_rc );
	}

	if ( $beta_or_rc && isset( $res->versions ) ) {
		if ( isset( $res->versions[ $beta_or_rc ] ) ) {
			$res->download_link = $res->versions[ $beta_or_rc ];
			$res->version       = $beta_or_rc;

		} else {
			return new WP_Error(
				'invalid_version',
				sprintf(
					/* translators: the %s placeholder is for the BuddyPress release tag. */
					esc_html__( 'The BuddyPress version %s is not available on WordPress.org.', 'buddypress-beta-tester' ),
					esc_html( $beta_or_rc )
				)
			);
		}
	}

	return $res;
}
add_filter( 'plugins_api_result', 'buddypress_beta_tester_plugins_api', 10, 3 );

/**
 * Add a Tools submenu.
 *
 * @since 1.0.0
 */
function buddypress_beta_tester_admin_menu() {
	$screen = add_management_page(
		__( 'BuddyPress Beta Tester', 'buddypress-beta-tester' ),
		__( 'BetaTest BuddyPress', 'buddypress-beta-tester' ),
		'manage_options',
		'buddypress-beta-tester',
		'buddypress_beta_tester_admin_page'
	);

	add_action( 'load-' . $screen, 'buddypress_beta_tester_admin_load' );
}
add_action( 'admin_menu', 'buddypress_beta_tester_admin_menu' );
