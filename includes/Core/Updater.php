<?php
/**
 * Remote plugin update checker (self-hosted or GitHub releases).
 *
 * @package GCRM\Core
 */

namespace GCRM\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Class Updater
 */
class Updater {

	private const CACHE_KEY = 'gcrm_remote_update_info';
	private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Plugin basename (e.g. folder/file.php).
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Plugin slug (folder name).
	 *
	 * @var string
	 */
	private string $plugin_slug;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_file = GCRM_PLUGIN_BASENAME;
		$this->plugin_slug = dirname( GCRM_PLUGIN_BASENAME );
	}

	/**
	 * Register update hooks.
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'in_plugin_update_message-' . $this->plugin_file, array( $this, 'update_message' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_action( 'admin_init', array( $this, 'handle_manual_check' ) );
		add_action( 'upgrader_process_complete', array( $this, 'after_upgrade' ), 10, 2 );
	}

	/**
	 * Inject update into WordPress plugins transient.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$remote = $this->get_remote_info();
		if ( ! $remote || empty( $remote->version ) || empty( $remote->download_url ) ) {
			return $transient;
		}

		if ( version_compare( GCRM_VERSION, $remote->version, '<' ) ) {
			$transient->response[ $this->plugin_file ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_file,
				'new_version' => $remote->version,
				'url'         => $remote->homepage ?? '',
				'package'     => $remote->download_url,
				'icons'       => array(),
				'banners'     => array(),
				'banners_rtl' => array(),
				'tested'      => $remote->tested ?? '',
				'requires_php'=> $remote->requires_php ?? '8.1',
			);
		} else {
			$transient->no_update[ $this->plugin_file ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_file,
				'new_version' => GCRM_VERSION,
				'url'         => $remote->homepage ?? '',
				'package'     => '',
			);
		}

		return $transient;
	}

	/**
	 * Plugin details for the "View details" modal on Plugins → Updates.
	 *
	 * @param mixed  $result Result.
	 * @param string $action Action.
	 * @param object $args Args.
	 * @return mixed
	 */
	public function plugin_information( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$remote = $this->get_remote_info( true );
		if ( ! $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => $remote->name ?? 'Guest Customer Recovery & Marketing Suite',
			'slug'          => $this->plugin_slug,
			'version'       => $remote->version,
			'author'        => $remote->author ?? 'GCRM Team',
			'homepage'      => $remote->homepage ?? '',
			'download_link' => $remote->download_url,
			'requires'      => $remote->requires ?? '6.5',
			'requires_php'  => $remote->requires_php ?? '8.1',
			'tested'        => $remote->tested ?? '',
			'last_updated'  => $remote->last_updated ?? '',
			'sections'      => array(
				'description' => $this->get_section_field( $remote, 'description' ),
				'changelog'   => $this->get_changelog( $remote ),
			),
		);
	}

	/**
	 * Changelog line under plugin row on updates screen.
	 *
	 * @param array<string, mixed> $plugin_data Plugin data.
	 * @param object               $response Update response.
	 */
	public function update_message( array $plugin_data, $response ): void {
		$remote = $this->get_remote_info();
		if ( ! $remote ) {
			return;
		}
		$changelog = $this->get_changelog( $remote );
		if ( $changelog ) {
			echo ' <span class="gcrm-update-changelog">' . wp_kses_post( $changelog ) . '</span>';
		}
	}

	/**
	 * Admin notice when a newer version is available.
	 */
	public function admin_notice(): void {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$remote = $this->get_remote_info();
		if ( ! $remote || ! $this->is_update_available( $remote ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'update' === $screen->id ) {
			return;
		}

		$update_url = wp_nonce_url(
			self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $this->plugin_file ) ),
			'upgrade-plugin_' . $this->plugin_file
		);
		$plugins_url = self_admin_url( 'plugins.php' );
		$check_url   = wp_nonce_url(
			admin_url( 'admin.php?page=gcrm-settings&gcrm_check_updates=1' ),
			'gcrm_check_updates'
		);

		echo '<div class="notice notice-warning is-dismissible"><p>';
		printf(
			/* translators: 1: new version, 2: current version */
			esc_html__( 'Guest Customer Recovery & Marketing Suite: version %1$s is available. You are running %2$s.', 'gcrm' ),
			esc_html( $remote->version ),
			esc_html( GCRM_VERSION )
		);
		echo ' <a href="' . esc_url( $update_url ) . '" class="button button-primary" style="margin-left:8px;">' . esc_html__( 'Update now', 'gcrm' ) . '</a>';
		echo ' <a href="' . esc_url( $plugins_url ) . '">' . esc_html__( 'View plugins', 'gcrm' ) . '</a>';
		echo ' <a href="' . esc_url( $check_url ) . '">' . esc_html__( 'Check again', 'gcrm' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Force refresh when admin clicks "Check for updates".
	 */
	public function handle_manual_check(): void {
		if ( empty( $_GET['gcrm_check_updates'] ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'gcrm_check_updates' ) ) {
			return;
		}

		$this->clear_cache();
		delete_site_transient( 'update_plugins' );
		$remote = $this->get_remote_info( true );

		$redirect = admin_url( 'admin.php?page=gcrm-settings' );
		if ( $remote && $this->is_update_available( $remote ) ) {
			$redirect = add_query_arg( 'gcrm_update_found', $remote->version, $redirect );
		} else {
			$redirect = add_query_arg( 'gcrm_update_none', '1', $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Clear update cache after plugin upgrade.
	 *
	 * @param \WP_Upgrader $upgrader Upgrader.
	 * @param array<string, mixed> $options Options.
	 */
	public function after_upgrade( $upgrader, array $options ): void {
		if ( empty( $options['action'] ) || 'update' !== $options['action'] || empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}
		if ( ! empty( $options['plugins'] ) && in_array( $this->plugin_file, (array) $options['plugins'], true ) ) {
			$this->clear_cache();
		}
	}

	/**
	 * Whether remote version is newer than installed.
	 *
	 * @param object $remote Remote info.
	 */
	public function is_update_available( object $remote ): bool {
		return ! empty( $remote->version ) && version_compare( GCRM_VERSION, $remote->version, '<' );
	}

	/**
	 * Get cached or fresh remote update info.
	 *
	 * @param bool $force Force refresh.
	 * @return object|null
	 */
	public function get_remote_info( bool $force = false ): ?object {
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached && is_string( $cached ) ) {
				$decoded = json_decode( $cached );
				return is_object( $decoded ) ? $decoded : null;
			}
		}

		$url = $this->get_check_url();
		if ( ! $url ) {
			set_transient( self::CACHE_KEY, '', self::CACHE_TTL );
			return null;
		}

		$remote = $this->fetch_remote( $url );
		set_transient( self::CACHE_KEY, $remote ? wp_json_encode( $remote ) : '', self::CACHE_TTL );
		update_option( 'gcrm_update_last_check', current_time( 'mysql' ) );

		return $remote;
	}

	/**
	 * Get a section field from remote info.
	 *
	 * @param object $remote Remote info.
	 * @param string $field Field name.
	 */
	private function get_section_field( object $remote, string $field ): string {
		if ( ! isset( $remote->sections ) ) {
			return '';
		}
		$sections = $remote->sections;
		if ( is_object( $sections ) && isset( $sections->{$field} ) ) {
			return (string) $sections->{$field};
		}
		if ( is_array( $sections ) && isset( $sections[ $field ] ) ) {
			return (string) $sections[ $field ];
		}
		return '';
	}

	/**
	 * Get changelog HTML from remote info (safe for cached data).
	 *
	 * @param object $remote Remote info.
	 */
	private function get_changelog( object $remote ): string {
		if ( ! isset( $remote->sections ) ) {
			return '';
		}
		$sections = $remote->sections;
		if ( is_object( $sections ) && isset( $sections->changelog ) ) {
			return (string) $sections->changelog;
		}
		if ( is_array( $sections ) && isset( $sections['changelog'] ) ) {
			return (string) $sections['changelog'];
		}
		return '';
	}

	/**
	 * Resolved update check URL from settings.
	 */
	public function get_check_url(): string {
		$default = 'https://github.com/saadsrabon/woccomerce-guest-recovery';
		$url     = trim( (string) get_option( 'gcrm_update_check_url', $default ) );
		if ( ! $url ) {
			return '';
		}

		// Convert GitHub repo URL to latest release API.
		if ( preg_match( '#github\.com/([^/]+)/([^/]+)#i', $url, $m ) ) {
			$repo = preg_replace( '#\.git$#', '', $m[2] );
			return 'https://api.github.com/repos/' . $m[1] . '/' . $repo . '/releases/latest';
		}

		return $url;
	}

	/**
	 * HTTP fetch and normalize response.
	 *
	 * @param string $url URL.
	 */
	private function fetch_remote( string $url ): ?object {
		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'GCRM-WordPress-Plugin/' . GCRM_VERSION,
			),
		);

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			Logger::log( 'Update check failed: ' . ( is_wp_error( $response ) ? $response->get_error_message() : 'HTTP error' ), 'warning' );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( ! is_object( $body ) ) {
			return null;
		}

		// GitHub Releases API shape.
		if ( isset( $body->tag_name ) ) {
			return $this->normalize_github_release( $body );
		}

		return $this->normalize_json_manifest( $body );
	}

	/**
	 * Normalize custom update.json manifest.
	 *
	 * @param object $body JSON body.
	 */
	private function normalize_json_manifest( object $body ): ?object {
		if ( empty( $body->version ) || empty( $body->download_url ) ) {
			return null;
		}

		$sections = $body->sections ?? (object) array();
		if ( is_array( $sections ) ) {
			$sections = (object) $sections;
		}

		return (object) array(
			'name'          => $body->name ?? 'Guest Customer Recovery & Marketing Suite',
			'version'       => ltrim( (string) $body->version, 'v' ),
			'download_url'  => (string) $body->download_url,
			'homepage'      => $body->homepage ?? $body->url ?? '',
			'author'        => $body->author ?? 'GCRM Team',
			'requires'      => $body->requires ?? '6.5',
			'requires_php'  => $body->requires_php ?? '8.1',
			'tested'        => $body->tested ?? '',
			'last_updated'  => $body->last_updated ?? gmdate( 'Y-m-d' ),
			'sections'      => (object) array(
				'description' => $sections->description ?? '',
				'changelog'   => $sections->changelog ?? '',
			),
		);
	}

	/**
	 * Normalize GitHub latest release response.
	 *
	 * @param object $release Release object.
	 */
	private function normalize_github_release( object $release ): ?object {
		$download_url = '';
		if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( ! empty( $asset->browser_download_url ) && str_ends_with( strtolower( $asset->name ?? '' ), '.zip' ) ) {
					$download_url = $asset->browser_download_url;
					break;
				}
			}
		}
		if ( ! $download_url && ! empty( $release->zipball_url ) ) {
			$download_url = $release->zipball_url;
		}
		if ( ! $download_url ) {
			return null;
		}

		$version = ltrim( (string) $release->tag_name, 'v' );
		$changelog = ! empty( $release->body ) ? wp_kses_post( wpautop( $release->body ) ) : '';

		return (object) array(
			'name'          => 'Guest Customer Recovery & Marketing Suite',
			'version'       => $version,
			'download_url'  => $download_url,
			'homepage'      => $release->html_url ?? '',
			'author'        => 'GCRM Team',
			'requires'      => '6.5',
			'requires_php'  => '8.1',
			'tested'        => '',
			'last_updated'  => ! empty( $release->published_at ) ? gmdate( 'Y-m-d', strtotime( $release->published_at ) ) : gmdate( 'Y-m-d' ),
			'sections'      => (object) array(
				'description' => '',
				'changelog'   => $changelog,
			),
		);
	}

	/**
	 * Clear update cache.
	 */
	public function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Status for settings UI.
	 *
	 * @return array{configured: bool, current: string, latest: string|null, update_available: bool, last_check: string}
	 */
	public function get_status(): array {
		$remote = $this->get_remote_info();
		return array(
			'configured'       => (bool) $this->get_check_url(),
			'current'          => GCRM_VERSION,
			'latest'           => $remote->version ?? null,
			'update_available' => $remote ? $this->is_update_available( $remote ) : false,
			'last_check'       => get_option( 'gcrm_update_last_check', '' ),
		);
	}
}
