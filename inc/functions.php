<?php
/**
 * Functions.
 *
 * @package   bp-beta-tester
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
function bp_beta_tester_sort_versions( $a, $b ) {
	return version_compare( $a, $b, '<' );
}

/**
 * Get the url to get BuddyPress updates.
 *
 * @since 1.0.0
 */
function bp_beta_tester_get_updates_url() {
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
function bp_beta_tester_get_version( $api = null, $version = '' ) {
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
		if ( isset( $api->icons ) ) {
			$icons = $api->icons;
		}

		$banners = array();
		if ( isset( $api->banners ) ) {
			$banners = $api->banners;
		}

		$banners_rtl = array();
		if ( isset( $api->banners_rtl ) ) {
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
function bp_beta_tester_admin_load() {
	include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

	$bpbt      = bp_beta_tester();
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
		if ( isset( $_GET['stable'] ) ) {
			$stable = wp_unslash( $_GET['stable'] ); // phpcs:ignore
		}

		if ( isset( $bpbt->api->versions[ $stable ] ) ) {
			$plugin_file   = 'buddypress/bp-loader.php';
			$new_transient = bp_beta_tester_get_version( $bpbt->api, $stable );

			if ( ! is_null( $new_transient ) ) {
				set_site_transient( 'update_plugins', $new_transient );

				// We need to do this to make sure the redirect works as expected.
				$redirect_url = str_replace( '&amp;', '&', bp_beta_tester_get_updates_url() );

				wp_safe_redirect( $redirect_url );
				exit();
			}
		}
	}
}

/**
 * Register and enqueue admin style.
 *
 * @since 1.0.0
 */
function bp_beta_tester_enqueue_style() {
	$bpbt = bp_beta_tester();

	wp_register_style(
		'bp-beta-tester',
		$bpbt->css_url . 'style.css',
		array(),
		$bpbt->version
	);

	wp_enqueue_style( 'bp-beta-tester' );
}
add_action( 'admin_enqueue_scripts', 'bp_beta_tester_enqueue_style' );

/**
 * Display the Dashboard submenu page.
 *
 * @since 1.0.0
 */
function bp_beta_tester_admin_page() {
	$bpbt             = bp_beta_tester();
	$latest           = '';
	$new_transient    = null;
	$is_latest_stable = false;

	if ( isset( $bpbt->api ) && $bpbt->api ) {
		$api = $bpbt->api;
	} else {
		$api = new WP_Error( 'unavailable_plugins_api', __( 'The Plugins API is unavailable.', 'bp_beta_tester' ) );
	}

	if ( ! is_wp_error( $api ) ) {
		$versions = $api->versions;

		// Sort versions so that latest are first.
		uksort( $versions, 'bp_beta_tester_sort_versions' );

		$releases         = array_keys( $versions );
		$latest           = reset( $releases );
		$is_latest_stable = false === strpos( $latest, '-' );
		$installed        = array();
		$plugin_file      = 'buddypress/bp-loader.php';
		$url              = '';
		$revert           = array();
		$action           = '';

		if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			$installed = get_plugin_data( WP_PLUGIN_DIR . '/buddypress/bp-loader.php', false, false );
			$installed['is_stable'] = false === strpos( $installed['Version'], '-' );
		}

		if ( ! $installed ) {
			$action = sprintf(
				/* translators: the %s placeholder is for the BuddyPress release tag. */
				__( 'Install %s', 'bp-beta-tester' ),
				$latest
			);

			if ( current_user_can( 'install_plugins' ) ) {
				$url = wp_nonce_url(
					add_query_arg(
						array(
							'action'         => 'install-plugin',
							'plugin'         => 'buddypress',
							'bp-beta-tester' => $latest,
						),
						self_admin_url( 'update.php' )
					),
					'install-plugin_buddypress'
				);
			}
		} elseif ( isset( $installed['Version'] ) ) {
			$action = sprintf(
				/* translators: the %s placeholder is for the BuddyPress release tag. */
				__( 'Upgrade to %s', 'bp-beta-tester' ),
				$latest
			);

			if ( $is_latest_stable ) {
				$url = self_admin_url( 'update-core.php' );
			} elseif ( ! $installed['is_stable'] ) {
				// Find the first stable version to be able to switch to it.
				foreach ( $versions as $version => $package ) {
					if ( false === strpos( $version, '-' ) ) {
						$revert = array(
							'url'     => wp_nonce_url(
								add_query_arg(
									array(
										'action' => 'restore-stable',
										'page'   => 'bp-beta-tester',
										'stable' => $version,
									),
									self_admin_url( 'index.php' )
								),
								'restore_stable_buddypress'
							),
							'version' => $version,
						);
						break;
					}
				}
			}

			if ( ! $is_latest_stable && version_compare( $installed['Version'], $latest, '<' ) ) {
				$url = bp_beta_tester_get_updates_url();

				$new_transient = bp_beta_tester_get_version( $api, $latest );

				if ( ! is_null( $new_transient ) ) {
					set_site_transient( 'update_plugins', $new_transient );
				}
			}

			if ( ! current_user_can( 'update_plugins' ) ) {
				$url    = '';
				$revert = array();
			}
		}
	}
	?>
	<div class="bp-beta-tester-header">
		<div class="bp-beta-tester-title-section">
			<h1><?php esc_html_e( 'Beta Test BuddyPress', 'bp-beta-tester' ); ?></h1>
			<div class="bp-beta-tester-logo">
				<img aria-hidden="true" focusable="false" width="100%" height="100%" src="<?php echo esc_url( $api->icons['svg'] ); ?>">
			</div>
		</div>
		<nav class="bp-beta-tester-tabs-wrapper <?php echo ! $revert['url'] || ! ( $new_transient || $is_latest_stable || ! $installed ) ? 'one-col' : 'two-cols' ?>" aria-label="<?php esc_html_e( 'Main actions', 'bp-beta-tester' ); ?>">
			<?php if ( $new_transient || ( $is_latest_stable && $installed && $latest !== $installed['Version'] ) || ! $installed ) : ?>
				<a href="<?php echo esc_url( $url ); ?>" class="bp-beta-tester-tab active">
					<?php echo esc_html( $action ); ?>
				</a>
			<?php endif; ?>

			<?php if ( $revert['url'] ) : ?>
				<a href="<?php echo esc_url( $revert['url'] ); ?>" class="bp-beta-tester-tab">
					<?php
					printf(
						/* translators: the %s placeholder is for the BuddyPress release tag. */
						esc_html__( 'Downgrade to %s', 'bp-beta-tester' ),
						esc_html( $revert['version'] )
					);
					?>
				</a>
			<?php endif; ?>
		</nav>
	</div>
	<hr class="wp-header-end">
	<div class="bp-beta-tester-body">
		<h2 class="thanks">
			<?php
			/* translators: %1$s is the current user display name and %2$s is a heart dashicon. */
			printf(
				esc_html__( 'Thank you so much %1$s %2$s', 'bp-beta-tester' ),
				esc_html( wp_get_current_user()->display_name ),
				'<span class="dashicons dashicons-heart"></span>'
			);
			?>
		</h2>

		<p><?php esc_html_e( 'Thanks for contributing to BuddyPress: beta testing the plugin is very important to make sure it behaves the right way for you and for the community.', 'bp-beta-tester' ); ?></p>
		<p><?php esc_html_e( 'Although the BuddyPress Core Development Team is regularly testing it, it\'s very challenging to test every possible configuration of WordPress and BuddyPress.', 'bp-beta-tester' ); ?></p>
		<p>
			<?php
			/* translators: %s is the link to the WP Core Contributor handbook page about installing WordPress locally. */
			printf( esc_html__( 'Please make sure to avoid using this plugin on a production site: beta testing is always safer when it\'s done on a %s of your site or on a testing site.', 'bp-beta-tester' ),
				'<a href="' . esc_url( 'https://make.wordpress.org/core/handbook/tutorials/installing-wordpress-locally/' ) . '">' . esc_html__( 'local copy', 'bp-beta-tester' ) . '</a>'
			);
			?>
		</p>

		<?php if ( $is_latest_stable ) : ?>
			<p>
				<?php
				/* translators: %1$s is the link to the BuddyPress account on Twitter and %2$s is the link to the BuddyPress blog. */
				printf(
					esc_html__( 'There is no pre-releases to test currently. Please consider following BuddyPress %1$s or checking %2$s regularly to be informed of the next pre-releases.', 'bp-beta-tester' ),
					'<a href="' . esc_url( 'https://twitter.com/BuddyPress' ) . '">' . esc_html__( 'on Twitter', 'bp-beta-tester' ) . '</a>',
					'<a href="' . esc_url( 'https://buddypress.org/blog/' ) . '">' . esc_html__( 'our blog', 'bp-beta-tester' ) . '</a>'
				);
				?>
			</p>
		<?php elseif ( isset( $installed['is_stable'] ) && ! $installed['is_stable'] ) : ?>
			<h2><?php esc_html_e( 'Have you Found a bug or a possible improvement?', 'bp-beta-tester' ); ?></h2>
			<p>
				<?php
				/* translators: %1$s is the link to the BuddyPress Trac and %2$s is the link to the BuddyPress Support forums. */
				printf(
					esc_html__( 'Please let us know about it opening a new ticket on our %1$s or posting a new topic in our %2$s.', 'bp-beta-tester' ),
					'<a href="' . esc_url( 'https://buddypress.trac.wordpress.org/newticket' ) . '">' . esc_html__( 'Development Tracker', 'bp-beta-tester' ) . '</a>',
					'<a href="' . esc_url( 'https://buddypress.org/support/' ) . '">' . esc_html__( 'support forums', 'bp-beta-tester' ) . '</a>'
				);
				?>
			</p>
			<p><?php esc_html_e( 'One of the Core Developers/Support forum moderators will review your feedback and we\'ll do our best to fix it before the stable version is made available to the public.', 'bp-beta-tester' ); ?></p>
		<?php endif ; ?>
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
function bp_beta_tester_plugins_api_args( $args = null, $action = '' ) {
	if ( 'plugin_information' !== $action || ! isset( $args->slug ) ) {
		return $args;
	}

	if ( 'buddypress' === $args->slug ) {
		$bpbt             = bp_beta_tester();
		$bpbt->beta_or_rc = '';

		if ( isset( $_GET['bp-beta-tester'] ) && $_GET['bp-beta-tester'] ) { // phpcs:ignore
			$bpbt->beta_or_rc = wp_unslash( $_GET['bp-beta-tester'] ); // phpcs:ignore
		}

		if ( $bpbt->beta_or_rc ) {
			$args->fields = array_merge( $args->fields, array( 'tags' => true ) );
		}
	}

	return $args;
}
add_filter( 'plugins_api_args', 'bp_beta_tester_plugins_api_args', 10, 2 );

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
function bp_beta_tester_plugins_api( $res = null, $action = '', $args = array() ) {
	if ( is_wp_error( $res ) || 'plugin_information' !== $action || 'buddypress' !== $res->slug ) {
		return $res;
	}

	$bpbt       = bp_beta_tester();
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
					esc_html__( 'The BuddyPress version %s is not available on WordPress.org.', 'bp-beta-tester' ),
					esc_html( $beta_or_rc )
				)
			);
		}
	}

	return $res;
}
add_filter( 'plugins_api_result', 'bp_beta_tester_plugins_api', 10, 3 );

/**
 * Add a Dashboard submenu.
 *
 * @since 1.0.0
 */
function bp_beta_tester_admin_menu() {
	$page = add_dashboard_page(
		__( 'BuddyPress Beta Tester', 'bp-beta-tester' ),
		__( 'Beta Test BuddyPress', 'bp-beta-tester' ),
		'manage_options',
		'bp-beta-tester',
		'bp_beta_tester_admin_page'
	);

	add_action( 'load-' . $page, 'bp_beta_tester_admin_load' );
}

if ( is_multisite() ) {
	add_action( 'network_admin_menu', 'bp_beta_tester_admin_menu' );
} else {
	add_action( 'admin_menu', 'bp_beta_tester_admin_menu' );
}
