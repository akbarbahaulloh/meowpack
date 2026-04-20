<?php
/**
 * Reactions class — Handles post reactions (emojis).
 *
 * @package MeowPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MeowPack_Reactions {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Output reaction bar at the bottom of content.
		add_filter( 'the_content', array( $this, 'append_reaction_bar' ), 20 );

		// Enqueue scripts (shared with tracker/frontend).
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register REST endpoint for reactions.
	 * (Called from MeowPack_Core)
	 */
	public static function register_routes( $core ) {
		register_rest_route(
			'meowpack/v1',
			'/reaction',
			array(
				'methods'             => 'POST',
				'callback'            => array( $core->reactions, 'handle_reaction_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id'  => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
					'reaction' => array( 'required' => true, 'type' => 'string',  'sanitize_callback' => 'sanitize_key' ),
					'nonce'    => array( 'required' => true, 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	/**
	 * Append the reaction bar to posts.
	 */
	public function append_reaction_bar( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		// Check if enabled in settings? Default on if not specified.
		if ( '0' === MeowPack_Database::get_setting( 'enable_reactions', '1' ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		$stats   = $this->get_post_reactions( $post_id );
		
		$reactions = array(
			'like'    => array( 'emoji' => '👍', 'label' => __( 'Suka', 'meowpack' ) ),
			'love'    => array( 'emoji' => '❤️', 'label' => __( 'Sayang', 'meowpack' ) ),
			'laugh'   => array( 'emoji' => '😂', 'label' => __( 'Lucu', 'meowpack' ) ),
			'wow'     => array( 'emoji' => '😮', 'label' => __( 'Kagum', 'meowpack' ) ),
			'sad'     => array( 'emoji' => '😢', 'label' => __( 'Sedih', 'meowpack' ) ),
			'angry'   => array( 'emoji' => '😡', 'label' => __( 'Marah', 'meowpack' ) ),
		);

		ob_start();
		?>
		<div class="meowpack-reactions" data-post-id="<?php echo esc_attr( $post_id ); ?>">
			<div class="meowpack-reactions__title"><?php esc_html_e( 'Bagaimana perasaan Anda?', 'meowpack' ); ?></div>
			<div class="meowpack-reactions__list">
				<?php foreach ( $reactions as $key => $data ) : 
					$count = $stats[ $key ] ?? 0;
				?>
					<button class="meowpack-reaction-btn" data-type="<?php echo esc_attr( $key ); ?>" title="<?php echo esc_attr( $data['label'] ); ?>">
						<span class="meowpack-reaction-emoji"><?php echo $data['emoji']; ?></span>
						<span class="meowpack-reaction-count"><?php echo $count > 0 ? esc_html( $count ) : ''; ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="meowpack-reactions__msg"></div>
		</div>
		<style>
			.meowpack-reactions { margin: 30px 0; padding: 20px; border-top: 1px solid #eee; border-bottom: 1px solid #eee; text-align: center; }
			.meowpack-reactions__title { font-weight: 600; margin-bottom: 15px; color: #444; }
			.meowpack-reactions__list { display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
			.meowpack-reaction-btn { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 30px; padding: 8px 15px; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s; }
			.meowpack-reaction-btn:hover { transform: translateY(-2px); background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
			.meowpack-reaction-btn.is-active { background: #e7f1ff; border-color: #3b82f6; }
			.meowpack-reaction-emoji { font-size: 20px; }
			.meowpack-reaction-count { font-size: 14px; color: #666; font-weight: 600; min-width: 10px; }
			.meowpack-reactions__msg { margin-top: 10px; font-size: 12px; color: #888; height: 18px; }
		</style>
		<?php
		return $content . ob_get_clean();
	}

	/**
	 * Get reaction counts for a post.
	 */
	public function get_post_reactions( $post_id ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'meow_reactions';
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT reaction_type, COUNT(*) as count FROM {$table} WHERE post_id = %d GROUP BY reaction_type",
			$post_id
		), ARRAY_A );

		$stats = array();
		foreach ( $results as $row ) {
			$stats[ $row['reaction_type'] ] = (int) $row['count'];
		}
		return $stats;
	}

	/**
	 * Handle reaction request from REST API.
	 */
	public function handle_reaction_request( WP_REST_Request $request ) {
		$post_id  = $request->get_param( 'post_id' );
		$reaction = $request->get_param( 'reaction' );
		$nonce    = $request->get_param( 'nonce' );

		if ( ! wp_verify_nonce( $nonce, 'meowpack_tracker' ) ) {
			return new WP_Error( 'invalid_nonce', 'Sesi kedaluwarsa. Silakan refresh halaman.', array( 'status' => 403 ) );
		}

		global $wpdb;
		$table   = $wpdb->prefix . 'meow_reactions';
		$ip_hash = hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' );

		// Check if already reacted.
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE post_id = %d AND ip_hash = %s",
			$post_id,
			$ip_hash
		) );

		if ( $exists ) {
			// Update to new reaction type? Or just block? User said "pilih salah satu". Let's allow updating.
			$wpdb->update(
				$table,
				array( 'reaction_type' => $reaction ),
				array( 'id' => $exists ),
				array( '%s' ),
				array( '%d' )
			);
			$message = __( 'Reaksi diperbarui!', 'meowpack' );
		} else {
			$wpdb->insert(
				$table,
				array(
					'post_id'       => $post_id,
					'reaction_type' => $reaction,
					'ip_hash'       => $ip_hash,
				),
				array( '%d', '%s', '%s' )
			);
			$message = __( 'Terima kasih atas reaksinya!', 'meowpack' );
		}

		return new WP_REST_Response( array(
			'success' => true,
			'message' => $message,
			'stats'   => $this->get_post_reactions( $post_id ),
		), 200 );
	}

	/**
	 * Enqueue frontend scripts (logic added to tracker.js or similar).
	 * For simplicity, we'll add the JS to meowpack-tracker-script if it exists or footer.
	 */
	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		// Inject JS for reactions.
		add_action( 'wp_footer', function() {
			?>
			<script>
			document.addEventListener('DOMContentLoaded', function() {
				const container = document.querySelector('.meowpack-reactions');
				if (!container) return;

				const post_id = container.dataset.postId;
				const btns = container.querySelectorAll('.meowpack-reaction-btn');
				const msg = container.querySelector('.meowpack-reactions__msg');

				btns.forEach(btn => {
					btn.addEventListener('click', function() {
						const type = this.dataset.type;
						
						// Highlight feedback.
						btns.forEach(b => b.classList.remove('is-active'));
						this.classList.add('is-active');
						msg.textContent = 'Menyimpan...';

						fetch('<?php echo esc_url_raw( rest_url( 'meowpack/v1/reaction' ) ); ?>', {
							method: 'POST',
							headers: { 'Content-Type': 'application/json' },
							body: JSON.stringify({
								post_id: post_id,
								reaction: type,
								nonce: meowpack_tracker_data.nonce
							})
						})
						.then(r => r.json())
						.then(data => {
							if (data.success) {
								msg.textContent = data.message;
								// Update counts.
								btns.forEach(b => {
									const t = b.dataset.type;
									const count = data.stats[t] || '';
									b.querySelector('.meowpack-reaction-count').textContent = count;
								});
							} else {
								msg.textContent = data.message || 'Gagal menyimpan reaksi.';
							}
						})
						.catch(() => {
							msg.textContent = 'Terjadi kesalahan koneksi.';
						});
					});
				});
			});
			</script>
			<?php
		}, 100 );
	}
}
