<?php
/**
 * GitHub Updater Class.
 * Handles automatic updates for MeowPack directly from GitHub.
 *
 * @package MeowPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MeowPack_GitHub_Updater {

	/**
	 * GitHub username.
	 * @var string
	 */
	private $username = 'akbarbahaulloh';

	/**
	 * GitHub repository name.
	 * @var string
	 */
	private $repository = 'meowpack';

	/**
	 * Plugin slug (folder name).
	 * @var string
	 */
	private $slug = 'meowpack';

	/**
	 * Plugin basename (folder/file).
	 * @var string
	 */
	private $basename = 'meowpack/meowpack.php';

	/**
	 * Cached release info.
	 * @var object|null
	 */
	private $github_response = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook into the plugin update check.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );

		// Hook into the plugin details popup (View Details link).
		add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 10, 3 );

		// Hook to rename the downloaded folder. GitHub appends commit hashes to the zipball root folder.
		add_filter( 'upgrader_source_selection', array( $this, 'rename_github_folder' ), 10, 4 );

		// Hook to save the newly installed SHA after a successful update.
		add_action( 'upgrader_process_complete', array( $this, 'update_installed_sha' ), 10, 2 );

		// Manual check link in plugins list.
		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'add_manual_check_link' ) );
		add_action( 'admin_init', array( $this, 'handle_manual_check' ) );
		add_action( 'admin_notices', array( $this, 'display_update_notice' ) );
	}

	/**
	 * Get the latest commit from the main branch on GitHub.
	 * Results are transiently cached for 1 hour to avoid hitting the API rate limit constantly.
	 *
	 * @param bool $force Bypass cache.
	 * @return object|false
	 */
	private function get_latest_commit( $force = false ) {
		if ( ! $force && null !== $this->github_response ) {
			return $this->github_response;
		}

		$transient_key = 'meowpack_gh_commit_data';
		if ( ! $force ) {
			$cached = get_site_transient( $transient_key );
			if ( false !== $cached ) {
				$this->github_response = $cached;
				return $cached;
			}
		}

		// Fetch the latest commit on 'main' branch.
		$url = "https://api.github.com/repos/{$this->username}/{$this->repository}/commits/main";
		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			),
		) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( empty( $data ) || ! isset( $data->sha ) ) {
			return false;
		}

		$this->github_response = $data;
		// Cache for 1 hour
		set_site_transient( $transient_key, $data, HOUR_IN_SECONDS );

		return $this->github_response;
	}

	/**
	 * Check for updates and push them to the transient.
	 *
	 * @param object $transient The update plugins transient.
	 * @return object
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$latest_commit = $this->get_latest_commit();
		if ( ! $latest_commit || empty( $latest_commit->sha ) ) {
			return $transient;
		}

		$remote_sha = sanitize_text_field( $latest_commit->sha );
		$local_sha  = get_option( 'meowpack_installed_sha', '' );

		// If SHAs differ, an update is available!
		if ( $remote_sha !== $local_sha ) {
			// We spoof the new_version so WordPress always triggers the UI logic.
			$display_version = ( defined( 'MEOWPACK_VERSION' ) ? MEOWPACK_VERSION : '2.1.0' ) . '.' . substr( $remote_sha, 0, 7 );

			$plugin_data = array(
				'slug'        => $this->slug,
				'plugin'      => $this->basename,
				'new_version' => $display_version,
				'url'         => $latest_commit->html_url, // Link to the commit
				'package'     => "https://api.github.com/repos/{$this->username}/{$this->repository}/zipball/main",
				'icons'       => array(
					'1x' => 'https://avatars.githubusercontent.com/u/35791720?v=4',
				)
			);

			$transient->response[ $this->basename ] = (object) $plugin_data;
		}

		return $transient;
	}

	/**
	 * Populate the "View details" popup for the plugin.
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Install API.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object
	 */
	public function get_plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$latest_commit = $this->get_latest_commit();
		if ( ! $latest_commit ) {
			return $result;
		}

		$remote_sha = sanitize_text_field( $latest_commit->sha );
		$message    = isset( $latest_commit->commit->message ) ? esc_html( $latest_commit->commit->message ) : 'Pembaruan kode terbaru.';
		$date       = isset( $latest_commit->commit->committer->date ) ? gmdate( 'Y-m-d H:i:s', strtotime( $latest_commit->commit->committer->date ) ) : '';

		$plugin_info = new stdClass();
		$plugin_info->name          = 'MeowPack';
		$plugin_info->slug          = $this->slug;
		$plugin_info->version       = ( defined( 'MEOWPACK_VERSION' ) ? MEOWPACK_VERSION : '2.2.0' ) . '.' . substr( $remote_sha, 0, 7 );
		$plugin_info->author        = '<a href="https://github.com/' . esc_attr( $this->username ) . '">Akbar Bahaulloh</a>';
		$plugin_info->homepage      = 'https://github.com/' . $this->username . '/' . $this->repository;
		$plugin_info->requires      = '5.0';
		$plugin_info->tested        = '6.6';
		$plugin_info->downloaded    = 0;
		$plugin_info->last_updated  = $date;
		$plugin_info->sections      = array(
			'description' => 'The ultimate security and optimization powerhouse for WordPress. Real-time local stats, AI-powered protection, malware scanning, and instant social engine — privacy-first, zero cloud dependencies, 100% control.',
			'changelog'   => "<strong>Commit Baru (" . substr( $remote_sha, 0, 7 ) . "):</strong><br><br>" . nl2br( $message ),
		);
		$plugin_info->download_link = "https://api.github.com/repos/{$this->username}/{$this->repository}/zipball/main";

		return $plugin_info;
	}

	/**
	 * Rename the extracted folder.
	 * GitHub appends arbitrary commit hashes to the root folder of the downloaded zip.
	 */
	public function rename_github_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		// Make sure it's updating this specific plugin.
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->basename ) {
			return $source;
		}

		$new_source = trailingslashit( $remote_source ) . $this->slug;
		$wp_filesystem->move( $source, $new_source );

		return trailingslashit( $new_source );
	}

	/**
	 * Logic fired after processing a plugin update.
	 * We use this to save the new SHA to the database to stop the update loop.
	 *
	 * @param WP_Upgrader $upgrader_object WP_Upgrader instance.
	 * @param array       $options         Array of bulk item update data.
	 */
	public function update_installed_sha( $upgrader_object, $options ) {
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] && ! empty( $options['plugins'] ) ) {
			if ( in_array( $this->basename, $options['plugins'], true ) ) {
				
				// Clear the cache so we force a fresh fetch.
				delete_site_transient( 'meowpack_gh_commit_data' );

				// Fetch the absolute newest commit (which we just installed via the main branch zip).
				$latest = $this->get_latest_commit( true );

				if ( $latest && ! empty( $latest->sha ) ) {
					// Save to database so we know what is currently installed!
					update_option( 'meowpack_installed_sha', sanitize_text_field( $latest->sha ) );
				}
			}
		}
	}

	/**
	 * Add "Check for Update" link to plugin action links.
	 */
	public function add_manual_check_link( $links ) {
		$check_link = array(
			'<a href="' . esc_url( admin_url( 'plugins.php?meowpack_check_update=1' ) ) . '">' . __( 'Cek Pembaruan', 'meowpack' ) . '</a>',
		);
		return array_merge( $check_link, $links );
	}

	/**
	 * Handle manual update check from URL.
	 */
	public function handle_manual_check() {
		if ( ! is_admin() || ! isset( $_GET['meowpack_check_update'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Clear the cache.
		delete_site_transient( 'meowpack_gh_commit_data' );
		delete_site_transient( 'update_plugins' ); // Force WP to re-check all plugins.

		// Redirect back with a notice flag.
		wp_safe_redirect( admin_url( 'plugins.php?meowpack_update_checked=1' ) );
		exit;
	}

	/**
	 * Display a notice after checking for updates manually.
	 */
	public function display_update_notice() {
		if ( isset( $_GET['meowpack_update_checked'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'MeowPack berhasil memeriksa pembaruan di GitHub. Jika ada versi baru, tombol "Update Now" akan muncul di bawah deskripsi plugin.', 'meowpack' ) . '</p></div>';
		}
	}
}
