<?php
/**
 * Share Buttons class — frontend share buttons via filter, shortcode, and block.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_ShareButtons
 *
 * Injects social share buttons before/after post content.
 */
class MeowPack_ShareButtons {

	/** @var array Platform definitions { slug, label, color, icon_svg }. */
	private static $platforms = array(
		'facebook'  => array( 'label' => 'Facebook',  'color' => '#1877f2', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.97h-1.514c-1.491 0-1.956.93-1.956 1.886v2.267h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>' ),
		'twitter'   => array( 'label' => 'X (Twitter)', 'color' => '#000000', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.747l7.73-8.835L1.254 2.25h6.988l4.26 5.632 5.742-5.632zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/></svg>' ),
		'whatsapp'  => array( 'label' => 'WhatsApp',   'color' => '#25d366', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>' ),
		'telegram'  => array( 'label' => 'Telegram',   'color' => '#26a5e4', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>' ),
		'linkedin'  => array( 'label' => 'LinkedIn',   'color' => '#0a66c2', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 0 1-2.063-2.065 2.064 2.064 0 1 1 2.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>' ),
		'bluesky'   => array( 'label' => 'Bluesky',    'color' => '#0085ff', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 10.8c-1.087-2.114-4.046-6.053-6.798-7.995C2.566.944 1.561 1.266.902 1.565.139 1.908 0 3.08 0 3.768c0 .69.378 5.65.624 6.479.815 2.736 3.713 3.66 6.383 3.364.136-.02.275-.039.415-.056-.138.022-.276.04-.415.056-3.912.58-7.387 2.005-2.83 7.078 5.013 5.19 6.87-1.113 7.823-4.308.953 3.195 2.05 9.271 7.733 4.308 4.267-4.308 1.172-6.498-2.74-7.078a8.741 8.741 0 0 1-.415-.056c.14.017.279.036.415.056 2.67.297 5.568-.628 6.383-3.364.246-.828.624-5.79.624-6.478 0-.69-.139-1.861-.902-2.204-.659-.299-1.664-.62-4.3 1.24C16.046 4.748 13.087 8.687 12 10.8Z"/></svg>' ),
		'threads'   => array( 'label' => 'Threads',    'color' => '#000000', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.852 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.29 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.022-5.11.936-6.54 2.717C4.307 6.504 3.616 8.914 3.589 12c.027 3.086.718 5.496 2.057 7.164 1.43 1.783 3.631 2.698 6.54 2.717 1.868-.012 3.45-.349 4.604-.994 1.478-.832 2.29-2.132 2.29-3.707 0-1.518-.7-2.697-2.02-3.397-.3 1.678-1.027 3.038-2.165 4.005-1.267 1.07-2.928 1.63-4.903 1.663-1.597-.026-2.936-.516-3.876-1.415-.98-.936-1.47-2.23-1.43-3.747.04-1.513.571-2.778 1.54-3.658.97-.882 2.337-1.367 3.955-1.407 1.17.013 2.217.256 3.091.718l.042.023c.054.035.105.07.157.106l-.026-.017c.055.035.107.073.158.11l-.028-.02-.025-.01c.02.01.038.021.056.032.106.065.207.133.303.204l.016.013.013.01.009.007-.01-.007.003.002c.39.286.745.6 1.057.94.327.356.597.75.807 1.178.218.44.37.92.451 1.43.082.519.098 1.051.047 1.584-.196 2.095-1.254 3.844-3.072 5.063-1.583 1.073-3.668 1.659-5.89 1.672h-.007zm3.32-9.06c.12-.586.14-1.237.05-1.928a3.86 3.86 0 0 0-.446-1.332 3.495 3.495 0 0 0-.878-1.03c-.073-.063-.148-.123-.225-.18a5.034 5.034 0 0 0-.356-.239 6.64 6.64 0 0 0-1.97-.745 7.447 7.447 0 0 0-1.52-.155c-1.157.028-2.089.343-2.723.913-.63.566-.96 1.37-.99 2.393-.03 1.024.27 1.822.899 2.416.596.562 1.461.87 2.508.887 1.59-.027 2.867-.572 3.73-1.494.338-.364.634-.78.92-1.506z"/></svg>' ),
		'pinterest' => array( 'label' => 'Pinterest',  'color' => '#e60023', 'icon' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>' ),
	);

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		if ( '1' !== MeowPack_Database::get_setting( 'enable_share_buttons', '1' ) ) {
			return;
		}

		add_filter( 'the_content', array( $this, 'inject_share_buttons' ) );
		add_shortcode( 'meowpack_share', array( $this, 'shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend CSS.
	 */
	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		wp_enqueue_style(
			'meowpack-share-buttons',
			MEOWPACK_URL . 'public/assets/meowpack-public.css',
			array(),
			MEOWPACK_VERSION
		);
	}

	/**
	 * Inject share buttons via the_content filter.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function inject_share_buttons( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$position = MeowPack_Database::get_setting( 'share_button_position', 'after' );
		$html     = $this->render( get_the_ID() );

		switch ( $position ) {
			case 'before':
				return $html . $content;
			case 'both':
				return $html . $content . $html;
			case 'none':
				return $content;
			default: // after.
				return $content . $html;
		}
	}

	/**
	 * Shortcode handler: [meowpack_share].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => get_the_ID(),
				'style'   => MeowPack_Database::get_setting( 'share_button_style', 'icon-text' ),
			),
			$atts,
			'meowpack_share'
		);

		return $this->render( absint( $atts['post_id'] ), $atts['style'] );
	}

	/**
	 * Register Gutenberg block.
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			'meowpack/share-buttons',
			array(
				'render_callback' => array( $this, 'block_render' ),
				'attributes'      => array(
					'style' => array( 'type' => 'string', 'default' => 'icon-text' ),
				),
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array $atts Block attributes.
	 * @return string
	 */
	public function block_render( $atts ) {
		return $this->render( get_the_ID(), $atts['style'] ?? 'icon-text' );
	}

	/**
	 * Render the share button HTML.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $style   icon-only | icon-text | pill-button
	 * @return string
	 */
	public function render( $post_id, $style = '' ) {
		if ( ! $post_id ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		if ( ! $style ) {
			$style = MeowPack_Database::get_setting( 'share_button_style', 'icon-text' );
		}

		$url           = rawurlencode( get_permalink( $post ) );
		$title         = rawurlencode( get_the_title( $post ) );
		$excerpt       = rawurlencode( get_the_excerpt( $post ) );
		$platforms_raw = MeowPack_Database::get_setting( 'share_platforms', 'facebook,twitter,telegram,whatsapp' );
		$active        = array_map( 'trim', explode( ',', $platforms_raw ) );

		$share_urls = array(
			'facebook'  => "https://www.facebook.com/sharer/sharer.php?u={$url}",
			'twitter'   => "https://twitter.com/intent/tweet?url={$url}&text={$title}",
			'whatsapp'  => "https://wa.me/?text={$title}%20{$url}",
			'telegram'  => "https://t.me/share/url?url={$url}&text={$title}",
			'linkedin'  => "https://www.linkedin.com/sharing/share-offsite/?url={$url}",
			'bluesky'   => "https://bsky.app/intent/compose?text={$title}%20{$url}",
			'threads'   => "https://www.threads.net/intent/post?text={$title}%20{$url}",
			'pinterest' => "https://pinterest.com/pin/create/button/?url={$url}&description={$title}",
			'line'      => "https://social-plugins.line.me/lineit/share?url={$url}",
		);

		$style_class = 'meowpack-share--' . sanitize_html_class( $style );

		$html  = '<div class="meowpack-share ' . esc_attr( $style_class ) . '" data-post-id="' . esc_attr( $post_id ) . '">';
		$html .= '<span class="meowpack-share__label">' . esc_html__( 'Bagikan:', 'meowpack' ) . '</span>';

		foreach ( self::$platforms as $slug => $info ) {
			if ( ! in_array( $slug, $active, true ) ) {
				continue;
			}

			$share_url = $share_urls[ $slug ] ?? '#';
			$color     = esc_attr( $info['color'] );
			$label     = esc_html( $info['label'] );
			$icon      = $info['icon']; // Already sanitized SVG.

			$html .= sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="meowpack-share__btn meowpack-share__btn--%s" style="--btn-color:%s;" data-platform="%s" aria-label="%s" id="meow-share-%s-%d">',
				esc_url( html_entity_decode( urldecode( $share_url ) ) ),
				esc_attr( $slug ),
				$color,
				esc_attr( $slug ),
				$label,
				esc_attr( $slug ),
				esc_attr( $post_id )
			);

			$html .= '<span class="meowpack-share__icon">' . $icon . '</span>';

			if ( 'icon-only' !== $style ) {
				$html .= '<span class="meowpack-share__text">' . $label . '</span>';
			}

			$html .= '</a>';
		}

		$html .= '</div>';

		// Inline click-tracking script (lazy, won't duplicate if rendered multiple times).
		static $script_added = false;
		if ( ! $script_added ) {
			$html .= $this->get_click_tracking_script();
			$script_added = true;
		}

		return $html;
	}

	/**
	 * Returns the inline JS for click tracking.
	 *
	 * @return string
	 */
	private function get_click_tracking_script() {
		$endpoint = rest_url( 'meowpack/v1/share-click' );
		$nonce    = wp_create_nonce( 'meowpack_share_click' );

		return '<script id="meowpack-share-track">(function(){
			document.addEventListener("click",function(e){
				var btn=e.target.closest(".meowpack-share__btn");
				if(!btn)return;
				var pid=btn.closest(".meowpack-share").dataset.postId;
				var platform=btn.dataset.platform;
				if(!pid||!platform)return;
				fetch("' . esc_js( $endpoint ) . '",{
					method:"POST",
					headers:{"Content-Type":"application/json"},
					body:JSON.stringify({post_id:parseInt(pid),platform:platform,nonce:"' . esc_js( $nonce ) . '"})
				});
			});
		})();</script>';
	}

	/**
	 * Handle share click REST request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_share_click( WP_REST_Request $request ) {
		$nonce = $request->get_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'meowpack_share_click' ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}

		$post_id  = absint( $request->get_param( 'post_id' ) );
		$platform = sanitize_key( $request->get_param( 'platform' ) );

		if ( ! $post_id || ! $platform ) {
			return new WP_REST_Response( array( 'ok' => false ), 200 );
		}

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'meow_share_logs',
			array(
				'post_id'    => $post_id,
				'platform'   => $platform,
				'status'     => 'click',
				'shared_at'  => current_time( 'mysql' ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
