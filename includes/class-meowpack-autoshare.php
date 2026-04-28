<?php
/**
 * AutoShare class — auto-publish posts to social media platforms.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_AutoShare
 *
 * Handles auto-sharing posts to 9 social media platforms.
 * Supports templates, retry logic, and per-post platform selection.
 */
class MeowPack_AutoShare {

	/**
	 * Supported platforms.
	 *
	 * @var string[]
	 */
	public static $platforms = array(
		'telegram', 'facebook', 'instagram', 'twitter', 'linkedin',
		'bluesky', 'threads', 'pinterest', 'line', 'whatsapp',
	);

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		if ( '1' !== MeowPack_Database::get_setting( 'enable_autoshare', '0' ) ) {
			return;
		}

		// Trigger share when post is published.
		add_action( 'publish_post', array( $this, 'on_publish_post' ), 10, 2 );

		// Meta box for per-post platform selection.
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ), 10, 2 );

		// Admin post row actions for manual share.
		add_filter( 'post_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_action( 'admin_post_meowpack_manual_autoshare', array( $this, 'handle_manual_share' ) );
		add_action( 'admin_notices', array( $this, 'show_share_notice' ) );
	}

	// -------------------------------------------------------------------------
	// WordPress Hooks
	// -------------------------------------------------------------------------

	/**
	 * Hook called when a post transitions to published state.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_publish_post( $post_id, WP_Post $post ) {
		// Avoid autosaves and revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Get platforms selected for this post (from meta box), fallback to global setting.
		$selected_platforms = get_post_meta( $post_id, '_meow_autoshare_platforms', true );
		if ( empty( $selected_platforms ) || ! is_array( $selected_platforms ) ) {
			$setting_platforms = MeowPack_Database::get_setting( 'autoshare_platforms', 'telegram' );
			$selected_platforms = array_map( 'trim', explode( ',', $setting_platforms ) );
		}

		$delay_hours = (int) MeowPack_Database::get_setting( 'autoshare_delay_hours', 0 );

		if ( $delay_hours > 0 ) {
			wp_schedule_single_event(
				time() + ( $delay_hours * HOUR_IN_SECONDS ),
				'meowpack_delayed_share',
				array( $post_id, $selected_platforms )
			);
			add_action( 'meowpack_delayed_share', array( $this, 'share_post' ), 10, 2 );
		} else {
			$this->share_post( $post_id, $selected_platforms );
		}
	}

	/**
	 * Register meta box on post edit screens.
	 */
	public function register_meta_box() {
		add_meta_box(
			'meowpack_autoshare',
			__( 'MeowPack — Auto Share', 'meowpack' ),
			array( $this, 'render_meta_box' ),
			array( 'post', 'page' ),
			'side',
			'default'
		);
	}

	/**
	 * Render the auto-share meta box.
	 *
	 * @param WP_Post $post Post object.
	 */
	public function render_meta_box( WP_Post $post ) {
		wp_nonce_field( 'meowpack_autoshare_meta', 'meowpack_autoshare_nonce' );

		$selected = get_post_meta( $post->ID, '_meow_autoshare_platforms', true );
		if ( ! is_array( $selected ) ) {
			$global_setting = MeowPack_Database::get_setting( 'autoshare_platforms', 'telegram' );
			$selected       = array_map( 'trim', explode( ',', $global_setting ) );
		}

		$labels = array(
			'telegram'  => 'Telegram',
			'facebook'  => 'Facebook',
			'twitter'   => 'X (Twitter)',
			'linkedin'  => 'LinkedIn',
			'bluesky'   => 'Bluesky',
			'threads'   => 'Threads',
			'pinterest' => 'Pinterest',
			'line'      => 'Line Notify',
			'whatsapp'  => 'WhatsApp',
		);

		echo '<p style="margin:0 0 8px;font-size:12px;color:#666;">' . esc_html__( 'Bagikan ke:', 'meowpack' ) . '</p>';
		foreach ( self::$platforms as $platform ) {
			$checked = in_array( $platform, $selected, true ) ? 'checked' : '';
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="meow_autoshare_platforms[]" value="%s" %s /> %s</label>',
				esc_attr( $platform ),
				esc_attr( $checked ),
				esc_html( $labels[ $platform ] ?? $platform )
			);
		}

		// Show share history for this post.
		$logs = $this->get_share_logs( $post->ID );
		if ( ! empty( $logs ) ) {
			echo '<hr style="margin:10px 0;"><p style="font-size:11px;font-weight:600;">' . esc_html__( 'Riwayat Share:', 'meowpack' ) . '</p>';
			foreach ( $logs as $log ) {
				$icon = 'success' === $log->status ? '✅' : ( 'failed' === $log->status ? '❌' : '⏳' );
				printf(
					'<span style="display:block;font-size:11px;">%s %s — %s</span>',
					esc_html( $icon ),
					esc_html( ucfirst( $log->platform ) ),
					esc_html( $log->shared_at ?? $log->created_at )
				);
			}
		}
	}

	/**
	 * Save meta box data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_box( $post_id, WP_Post $post ) {
		if ( ! isset( $_POST['meowpack_autoshare_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['meowpack_autoshare_nonce'] ) ), 'meowpack_autoshare_meta' ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$platforms = isset( $_POST['meow_autoshare_platforms'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['meow_autoshare_platforms'] ) )
			: array();

		$platforms = array_intersect( $platforms, self::$platforms );
		update_post_meta( $post_id, '_meow_autoshare_platforms', $platforms );
	}

	// -------------------------------------------------------------------------
	// Manual Share via Row Actions
	// -------------------------------------------------------------------------

	/**
	 * Add "Bagikan ke Sosmed" link to post row actions.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post object.
	 * @return array
	 */
	public function add_row_action( $actions, $post ) {
		if ( 'publish' !== $post->post_status ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=meowpack_manual_autoshare&post_id=' . $post->ID ),
			'meowpack_manual_share_' . $post->ID
		);

		$actions['meowpack_share'] = sprintf(
			'<a href="%s" style="color:#0073aa; font-weight:600;">%s</a>',
			esc_url( $url ),
			esc_html__( '🚀 Bagikan ke Sosmed', 'meowpack' )
		);

		return $actions;
	}

	/**
	 * Handle manual share execution from row action.
	 */
	public function handle_manual_share() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! $post_id || ! isset( $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Permintaan tidak valid.', 'meowpack' ) );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'meowpack_manual_share_' . $post_id ) ) {
			wp_die( esc_html__( 'Keamanan (Nonce) gagal diverifikasi.', 'meowpack' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Akses ditolak.', 'meowpack' ) );
		}

		$selected_platforms = get_post_meta( $post_id, '_meow_autoshare_platforms', true );
		if ( empty( $selected_platforms ) || ! is_array( $selected_platforms ) ) {
			$setting_platforms = MeowPack_Database::get_setting( 'autoshare_platforms', 'telegram' );
			$selected_platforms = array_map( 'trim', explode( ',', $setting_platforms ) );
		}

		// Force instant share regardless of delay setting.
		$this->share_post( $post_id, $selected_platforms );

		$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php' );
		wp_safe_redirect( add_query_arg( 'meowpack_shared', $post_id, $redirect ) );
		exit;
	}

	/**
	 * Show success notice after manual share.
	 */
	public function show_share_notice() {
		if ( isset( $_GET['meowpack_shared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( $_GET['meowpack_shared'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>✅ <strong>Berhasil!</strong> ' . sprintf( esc_html__( 'Artikel ID %d sedang didistribusikan ke sosial media aktif.', 'meowpack' ), $post_id ) . '</p></div>';
		}
	}

	// -------------------------------------------------------------------------
	// Share Dispatcher
	// -------------------------------------------------------------------------

	/**
	 * Public wrapper to test a platform connection.
	 *
	 * @param string $platform Platform slug.
	 * @param array  $vars     Template variables.
	 * @return array
	 */
	public function test_connection( $platform, $vars ) {
		$method = 'share_' . $platform;
		if ( method_exists( $this, $method ) ) {
			return $this->$method( $vars );
		}
		return array( 'success' => false, 'code' => 404 );
	}

	/**
	 * Share a post to the given platforms.
	 *
	 * @param int      $post_id   Post ID.
	 * @param string[] $platforms Array of platform slugs.
	 */
	public function share_post( $post_id, array $platforms ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		$vars = $this->build_template_vars( $post );

		foreach ( $platforms as $platform ) {
			$platform = sanitize_key( $platform );
			if ( ! in_array( $platform, self::$platforms, true ) ) {
				continue;
			}

			// Skip if already shared successfully.
			if ( $this->is_already_shared( $post_id, $platform ) ) {
				continue;
			}

			$this->log_share( $post_id, $platform, 'pending' );

			$method = 'share_' . $platform;
			if ( method_exists( $this, $method ) ) {
				$result = $this->$method( $vars );
				$status = $result['success'] ? 'success' : 'failed';
				$code   = $result['code'] ?? null;
				$this->update_share_log( $post_id, $platform, $status, $code );
			}
		}
	}

	/**
	 * Build template variable map for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private function build_template_vars( WP_Post $post ) {
		$excerpt = has_excerpt( $post )
			? get_the_excerpt( $post )
			: wp_trim_words( strip_shortcodes( $post->post_content ), 30 );

		$tags = implode( ' #', array_column( wp_get_post_tags( $post->ID ), 'name' ) );
		if ( $tags ) {
			$tags = '#' . $tags;
		}

		return array(
			'{id}'             => $post->ID,
			'{title}'          => get_the_title( $post ),
			'{url}'            => get_permalink( $post ),
			'{excerpt}'        => $excerpt,
			'{tags}'           => $tags,
			'{sitename}'       => get_bloginfo( 'name' ),
			'{featured_image}' => get_the_post_thumbnail_url( $post->ID, 'full' ),
		);
	}

	/**
	 * Fill template placeholders.
	 *
	 * @param string $template Template string.
	 * @param array  $vars     Variable map.
	 * @return string
	 */
	private function fill_template( $template, array $vars ) {
		return str_replace( array_keys( $vars ), array_values( $vars ), $template );
	}

	/**
	 * Get token data for a platform.
	 *
	 * @param string $platform Platform slug.
	 * @return array|null
	 */
	private function get_token( $platform ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_social_tokens';
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$table} WHERE platform = %s", $platform ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		// Decrypt access_token.
		if ( ! empty( $row['access_token'] ) ) {
			$decrypted = self::decrypt_token( $row['access_token'] );
			$row['access_token'] = $decrypted ?: $row['access_token'];
		}

		if ( ! empty( $row['token_data'] ) ) {
			$row['token_data'] = json_decode( $row['token_data'], true );
		}

		return $row;
	}

	// -------------------------------------------------------------------------
	// Platform Methods
	// -------------------------------------------------------------------------

	/**
	 * Share to Telegram via Bot API.
	 *
	 * @param array $vars Template vars.
	 * @return array{ success: bool, code: int|null }
	 */
	private function share_telegram( array $vars ) {
		$token = $this->get_token( 'telegram' );
		if ( ! $token || empty( $token['access_token'] ) || empty( $token['token_data']['chat_id'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$bot_token = $token['access_token'];
		$chat_id   = $token['token_data']['chat_id'];
		$template  = $token['token_data']['message_template'] ?? "{title}\n\n{excerpt}\n\n{url}";
		$text      = $this->fill_template( $template, $vars );

		$response = wp_remote_post(
			"https://api.telegram.org/bot{$bot_token}/sendMessage",
			array(
				'timeout' => 15,
				'body'    => array(
					'chat_id'    => $chat_id,
					'text'       => $text,
					'parse_mode' => 'HTML',
				),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		return array( 'success' => ! is_wp_error( $response ) && 200 === (int) $code, 'code' => (int) $code );
	}

	/**
	 * Share to Facebook Page via Graph API.
	 *
	 * @param array $vars Template vars.
	 * @return array{ success: bool, code: int|null }
	 */
	private function share_facebook( array $vars ) {
		$token = $this->get_token( 'facebook' );
		if ( ! $token || empty( $token['access_token'] ) || empty( $token['token_data']['page_id'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$page_id      = $token['token_data']['page_id'];
		$access_token = $token['access_token'];
		$template     = $token['token_data']['message_template'] ?? '{title} — {sitename}';
		$message      = $this->fill_template( $template, $vars );

		$response = wp_remote_post(
			"https://graph.facebook.com/{$page_id}/feed",
			array(
				'timeout' => 15,
				'body'    => array(
					'message'      => $message,
					'link'         => $vars['{url}'],
					'access_token' => $access_token,
				),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		return array( 'success' => ! is_wp_error( $response ) && 200 === (int) $code, 'code' => (int) $code );
	}

	/**
	 * Share to X (Twitter) via API v2 with OAuth 1.0a.
	 *
	 * @param array $vars Template vars.
	 * @return array{ success: bool, code: int|null }
	 */
	private function share_twitter( array $vars ) {
		$token = $this->get_token( 'twitter' );
		if ( ! $token || empty( $token['token_data'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$td = $token['token_data'];
		// Twitter OAuth 1.0a requires 4 keys. access_token is the primary encrypted token.
		if ( empty( $td['api_key'] ) || empty( $td['api_secret'] ) || empty( $token['access_token'] ) || empty( $td['access_secret'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$template = $td['message_template'] ?? '{title} {url}';
		$text     = $this->fill_template( $template, $vars );
		// Truncate to 280 chars.
		if ( mb_strlen( $text ) > 280 ) {
			$text = mb_substr( $text, 0, 277 ) . '...';
		}

		$url    = 'https://api.twitter.com/2/tweets';
		$body   = wp_json_encode( array( 'text' => $text ) );
		$oauth  = $this->build_oauth1_header(
			$td['api_key'], $td['api_secret'],
			$token['access_token'], $td['access_secret'],
			'POST', $url, array()
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => $oauth,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		return array( 'success' => ! is_wp_error( $response ) && 201 === (int) $code, 'code' => (int) $code );
	}

	/**
	 * Share to LinkedIn via UGC Posts API.
	 *
	 * @param array $vars Template vars.
	 * @return array{ success: bool, code: int|null }
	 */
	private function share_linkedin( array $vars ) {
		$token = $this->get_token( 'linkedin' );
		if ( ! $token || empty( $token['access_token'] ) || empty( $token['token_data']['author'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$author   = $token['token_data']['author']; // e.g. urn:li:person:xxxxx
		$template = $token['token_data']['message_template'] ?? '{title}\n\n{excerpt}\n\n{url}';
		$text     = $this->fill_template( $template, $vars );

		$body = array(
			'author'          => $author,
			'lifecycleState'  => 'PUBLISHED',
			'specificContent' => array(
				'com.linkedin.ugc.ShareContent' => array(
					'shareCommentary'   => array( 'text' => $text ),
					'shareMediaCategory'=> 'ARTICLE',
					'media'             => array(
						array(
							'status'         => 'READY',
							'originalUrl'    => $vars['{url}'],
							'title'          => array( 'text' => $vars['{title}'] ),
							'description'    => array( 'text' => $vars['{excerpt}'] ),
						),
					),
				),
			),
			'visibility' => array(
				'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
			),
		);

		$response = wp_remote_post(
			'https://api.linkedin.com/v2/ugcPosts',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token['access_token'],
					'Content-Type'  => 'application/json',
					'X-Restli-Protocol-Version' => '2.0.0',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		return array( 'success' => ! is_wp_error( $response ) && 201 === (int) $code, 'code' => (int) $code );
	}

	/**
	 * Share to Bluesky via AT Protocol.
	 *
	 * @param array $vars Template vars.
	 * @return array{ success: bool, code: int|null }
	 */
	private function share_bluesky( array $vars ) {
		$token = $this->get_token( 'bluesky' );
		if ( ! $token || empty( $token['token_data']['handle'] ) || empty( $token['token_data']['password'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		// Step 1: Authenticate.
		$auth = wp_remote_post(
			'https://bsky.social/xrpc/com.atproto.server.createSession',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'identifier' => $token['token_data']['handle'],
					'password'   => $token['token_data']['password'],
				) ),
			)
		);

		if ( is_wp_error( $auth ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$auth_data = json_decode( wp_remote_retrieve_body( $auth ), true );
		$jwt       = $auth_data['accessJwt'] ?? '';
		$did       = $auth_data['did'] ?? '';

		if ( ! $jwt || ! $did ) {
			return array( 'success' => false, 'code' => 401 );
		}

		// Step 2: Create post record.
		$template = $token['token_data']['message_template'] ?? '{title}\n\n{excerpt}\n\n{url}';
		$text     = $this->fill_template( $template, $vars );

		$response = wp_remote_post(
			'https://bsky.social/xrpc/com.atproto.repo.createRecord',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => "Bearer {$jwt}",
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'repo'       => $did,
					'collection' => 'app.bsky.feed.post',
					'record'     => array(
						'$type'     => 'app.bsky.feed.post',
						'text'      => mb_substr( $text, 0, 300 ),
						'createdAt' => gmdate( 'c' ),
					),
				) ),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		return array( 'success' => ! is_wp_error( $response ) && 200 === (int) $code, 'code' => (int) $code );
	}

	/**
	 * Share to Threads via Meta Threads API.
	 *
	 * @param array $vars Template vars.
	 * @return array{ success: bool, code: int|null }
	 */
	private function share_threads( array $vars ) {
		$token = $this->get_token( 'threads' );
		if ( ! $token || empty( $token['access_token'] ) || empty( $token['token_data']['user_id'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$user_id      = $token['token_data']['user_id'];
		$access_token = $token['access_token'];
		$template     = $token['token_data']['message_template'] ?? '{title}\n\n{url}';
		$text         = $this->fill_template( $template, $vars );

		// Step 1: Create container.
		$container_resp = wp_remote_post(
			"https://graph.threads.net/v1.0/{$user_id}/threads",
			array(
				'timeout' => 15,
				'body'    => array(
					'media_type'   => 'TEXT',
					'text'         => $text,
					'access_token' => $access_token,
				),
			)
		);

		if ( is_wp_error( $container_resp ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$container = json_decode( wp_remote_retrieve_body( $container_resp ), true );
		$creation_id = $container['id'] ?? '';

		if ( ! $creation_id ) {
			return array( 'success' => false, 'code' => (int) wp_remote_retrieve_response_code( $container_resp ) );
		}

		// Step 2: Publish container.
		$publish_resp = wp_remote_post(
			"https://graph.threads.net/v1.0/{$user_id}/threads_publish",
			array(
				'timeout' => 15,
				'body'    => array(
					'creation_id'  => $creation_id,
					'access_token' => $access_token,
				),
			)
		);

		$code = wp_remote_retrieve_response_code( $publish_resp );
		return array( 'success' => ! is_wp_error( $publish_resp ) && 200 === (int) $code, 'code' => (int) $code );
	}

	/**
	 * Share to Pinterest via API v5.
	 *
	 * @param array $vars Template vars.
	 * @return array{ success: bool, code: int|null }
	 */
	private function share_pinterest( array $vars ) {
		$token = $this->get_token( 'pinterest' );
		if ( ! $token || empty( $token['access_token'] ) || empty( $token['token_data']['board_id'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$board_id     = $token['token_data']['board_id'];
		$access_token = $token['access_token'];
		$thumbnail    = get_the_post_thumbnail_url( 0, 'large' );

		$body = array(
			'board_id'    => $board_id,
			'link'        => $vars['{url}'],
			'title'       => $vars['{title}'],
			'description' => $vars['{excerpt}'],
			'media_source'=> array(
				'source_type' => 'image_url',
				'url'         => $thumbnail ?: $vars['{url}'],
			),
		);

		$response = wp_remote_post(
			'https://api.pinterest.com/v5/pins',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		return array( 'success' => ! is_wp_error( $response ) && 201 === (int) $code, 'code' => (int) $code );
	}

	/**
	 * Share to Line Notify.
	 *
	 * @param array $vars Template vars.
	 * @return array{ success: bool, code: int|null }
	 */
	private function share_line( array $vars ) {
		$token = $this->get_token( 'line' );
		if ( ! $token || empty( $token['access_token'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$template = $token['token_data']['message_template'] ?? "\n{title}\n{excerpt}\n{url}";
		$message  = $this->fill_template( $template, $vars );

		$response = wp_remote_post(
			'https://notify-api.line.me/api/notify',
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $token['access_token'] ),
				'body'    => array( 'message' => $message ),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		return array( 'success' => ! is_wp_error( $response ) && 200 === (int) $code, 'code' => (int) $code );
	}

	/**
	 * Share to WhatsApp via Business Cloud API.
	 *
	 * @param array $vars Template vars.
	 * @return array{ success: bool, code: int|null }
	 */
	private function share_whatsapp( array $vars ) {
		$token = $this->get_token( 'whatsapp' );
		if ( ! $token || empty( $token['access_token'] ) || empty( $token['token_data']['phone_number_id'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$phone_id  = $token['token_data']['phone_number_id'];
		$recipient = $token['token_data']['recipient_number'] ?? '';
		$template_name = $token['token_data']['template_name'] ?? 'meowpack_post_share';

		if ( empty( $recipient ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$body = array(
			'messaging_product' => 'whatsapp',
			'to'                => $recipient,
			'type'              => 'text',
			'text'              => array(
				'body' => $this->fill_template( "{title}\n\n{excerpt}\n\n{url}", $vars ),
			),
		);

		$response = wp_remote_post(
			"https://graph.facebook.com/v18.0/{$phone_id}/messages",
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token['access_token'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		$code = wp_remote_retrieve_response_code( $response );
		return array( 'success' => ! is_wp_error( $response ) && 200 === (int) $code, 'code' => (int) $code );
	}

	/**
	 * Check if a post has already been shared successfully to a platform.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $platform Platform slug.
	 * @return bool
	 */
	private function is_already_shared( $post_id, $platform ) {
		global $wpdb;
		$status = $wpdb->get_var( $wpdb->prepare(
			"SELECT status FROM {$wpdb->prefix}meow_share_logs WHERE post_id = %d AND platform = %s AND status = 'success' LIMIT 1",
			absint( $post_id ), sanitize_key( $platform )
		) );
		return 'success' === $status;
	}

	/**
	 * Share to Instagram via Graph API (Business/Creator accounts).
	 * Requires a featured image.
	 *
	 * @param array $vars Template vars.
	 * @return array{ success: bool, code: int|null }
	 */
	private function share_instagram( array $vars ) {
		$token = $this->get_token( 'instagram' );
		if ( ! $token || empty( $token['access_token'] ) || empty( $token['token_data']['ig_user_id'] ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$image_url = $vars['{featured_image}'] ?? '';
		if ( ! $image_url ) {
			return array( 'success' => false, 'code' => 400 ); // No image.
		}

		$ig_user_id   = $token['token_data']['ig_user_id'];
		$access_token = $token['access_token'];
		$template     = $token['token_data']['message_template'] ?? "{title}\n\n{url}";
		$caption      = $this->fill_template( $template, $vars );

		// Step 1: Create media container.
		$container_resp = wp_remote_post(
			"https://graph.facebook.com/v19.0/{$ig_user_id}/media",
			array(
				'timeout' => 20,
				'body'    => array(
					'image_url'    => $image_url,
					'caption'      => $caption,
					'access_token' => $access_token,
				),
			)
		);

		if ( is_wp_error( $container_resp ) ) {
			return array( 'success' => false, 'code' => null );
		}

		$container = json_decode( wp_remote_retrieve_body( $container_resp ), true );
		$creation_id = $container['id'] ?? '';

		if ( ! $creation_id ) {
			return array( 'success' => false, 'code' => (int) wp_remote_retrieve_response_code( $container_resp ) );
		}

		// Step 2: Publish media.
		$publish_resp = wp_remote_post(
			"https://graph.facebook.com/v19.0/{$ig_user_id}/media_publish",
			array(
				'timeout' => 20,
				'body'    => array(
					'creation_id'  => $creation_id,
					'access_token' => $access_token,
				),
			)
		);

		$code = wp_remote_retrieve_response_code( $publish_resp );
		return array( 'success' => ! is_wp_error( $publish_resp ) && 200 === (int) $code, 'code' => (int) $code );
	}

	// -------------------------------------------------------------------------
	// Share Log Management
	// -------------------------------------------------------------------------

	/**
	 * Insert a pending share log.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $platform Platform slug.
	 * @param string $status   Status string.
	 */
	private function log_share( $post_id, $platform, $status = 'pending' ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'meow_share_logs',
			array(
				'post_id'    => absint( $post_id ),
				'platform'   => sanitize_key( $platform ),
				'status'     => $status,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Update the most recent share log for a post+platform.
	 *
	 * @param int      $post_id  Post ID.
	 * @param string   $platform Platform slug.
	 * @param string   $status   Status.
	 * @param int|null $code     HTTP response code.
	 */
	private function update_share_log( $post_id, $platform, $status, $code = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_share_logs';

		$log_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE post_id = %d AND platform = %s ORDER BY created_at DESC LIMIT 1",
				$post_id, $platform
			)
		);

		if ( $log_id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'status'        => $status,
					'response_code' => $code,
					'shared_at'     => 'success' === $status ? current_time( 'mysql' ) : null,
				),
				array( 'id' => (int) $log_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Get share logs for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_share_logs( $post_id ) {
		global $wpdb;
		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}meow_share_logs WHERE post_id = %d ORDER BY created_at DESC LIMIT 20",
				absint( $post_id )
			)
		);
	}

	/**
	 * Retry failed shares (called by daily cron).
	 * Retries up to 3 times.
	 */
	public function retry_failed_shares() {
		global $wpdb;
		$table = $wpdb->prefix . 'meow_share_logs';

		$failed = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$table} WHERE status = 'failed' AND retry_count < 3 ORDER BY created_at ASC LIMIT 20",
			ARRAY_A
		);

		foreach ( $failed as $log ) {
			$post = get_post( (int) $log['post_id'] );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}

			$vars   = $this->build_template_vars( $post );
			$method = 'share_' . $log['platform'];
			if ( ! method_exists( $this, $method ) ) {
				continue;
			}

			$result = $this->$method( $vars );
			$status = $result['success'] ? 'success' : 'failed';

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'status'        => $status,
					'response_code' => $result['code'],
					'retry_count'   => (int) $log['retry_count'] + 1,
					'shared_at'     => 'success' === $status ? current_time( 'mysql' ) : null,
				),
				array( 'id' => (int) $log['id'] ),
				array( '%s', '%d', '%d', '%s' ),
				array( '%d' )
			);
		}
	}

	// -------------------------------------------------------------------------
	// Token Encryption
	// -------------------------------------------------------------------------

	/**
	 * Encrypt a token using AUTH_KEY + OpenSSL.
	 *
	 * @param string $token Plain text token.
	 * @return string Base64-encoded encrypted token.
	 */
	public static function encrypt_token( $token ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $token ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$key    = substr( hash( 'sha256', AUTH_KEY . wp_salt() ), 0, 32 );
		$iv     = openssl_random_pseudo_bytes( 16 );
		$enc    = openssl_encrypt( $token, 'AES-256-CBC', $key, 0, $iv );
		return base64_encode( $iv . '::' . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a token.
	 *
	 * @param string $encrypted Encrypted token.
	 * @return string|false Decrypted string or false on failure.
	 */
	public static function decrypt_token( $encrypted ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}

		$data = base64_decode( $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === strpos( $data, '::' ) ) {
			return $data;
		}

		list( $iv, $enc ) = explode( '::', $data, 2 );
		$key = substr( hash( 'sha256', AUTH_KEY . wp_salt() ), 0, 32 );
		return openssl_decrypt( $enc, 'AES-256-CBC', $key, 0, $iv );
	}

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	/**
	 * Build OAuth 1.0a Authorization header for Twitter.
	 *
	 * @param string $consumer_key    API Key.
	 * @param string $consumer_secret API Secret.
	 * @param string $access_token    Access Token.
	 * @param string $access_secret   Access Secret.
	 * @param string $method          HTTP method.
	 * @param string $url             Request URL.
	 * @param array  $params          Query params (if any).
	 * @return string Authorization header value.
	 */
	private function build_oauth1_header( $consumer_key, $consumer_secret, $access_token, $access_secret, $method, $url, $params ) {
		$oauth = array(
			'oauth_consumer_key'     => $consumer_key,
			'oauth_nonce'            => wp_generate_uuid4(),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => time(),
			'oauth_token'            => $access_token,
			'oauth_version'          => '1.0',
		);

		$base_params = array_merge( $oauth, $params );
		ksort( $base_params );

		$param_string = http_build_query( $base_params, '', '&', PHP_QUERY_RFC3986 );
		$base_string  = strtoupper( $method ) . '&' . rawurlencode( $url ) . '&' . rawurlencode( $param_string );
		$signing_key  = rawurlencode( $consumer_secret ) . '&' . rawurlencode( $access_secret );
		$signature    = base64_encode( hash_hmac( 'sha1', $base_string, $signing_key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$oauth['oauth_signature'] = $signature;
		ksort( $oauth );

		$parts = array();
		foreach ( $oauth as $key => $value ) {
			$parts[] = rawurlencode( $key ) . '="' . rawurlencode( $value ) . '"';
		}

		return 'OAuth ' . implode( ', ', $parts );
	}
}
