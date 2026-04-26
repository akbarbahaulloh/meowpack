<?php
/**
 * View Counter class — displays post view counts on frontend.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_ViewCounter
 *
 * Handles display of view counts and trending posts.
 */
class MeowPack_ViewCounter {

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		if ( '1' !== MeowPack_Database::get_setting( 'enable_view_counter', '1' ) ) {
			return;
		}

		// Removed auto-injection via the_content filter to avoid duplicates
		// with the new Frontend Enhancer module.
		add_shortcode( 'meowpack_views', array( $this, 'shortcode_views' ) );
		add_shortcode( 'meowpack_trending', array( $this, 'shortcode_trending' ) );

		add_action( 'wp_ajax_nopriv_meowpack_get_views', array( $this, 'ajax_get_views' ) );
		add_action( 'wp_ajax_meowpack_get_views', array( $this, 'ajax_get_views' ) );
	}

	/**
	 * Inject view count after title via the_content filter.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function inject_view_count( $content ) {
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Allow per-post opt-out via custom field.
		if ( get_post_meta( $post_id, '_meow_hide_views', true ) ) {
			return $content;
		}

		// Check post type.
		$tracked_types = array_map( 'trim', explode( ',', MeowPack_Database::get_setting( 'track_post_types', 'post,page' ) ) );
		if ( ! in_array( get_post_type( $post_id ), $tracked_types, true ) ) {
			return $content;
		}

		$ajax_url = admin_url( 'admin-ajax.php' );
		$html = sprintf(
			'<div class="meowpack-view-count" id="meowpack-view-count-%1$d"><script type="text/javascript" data-cfasync="false" src="%2$s?action=meowpack_get_views&post_id=%1$d"></script></div>',
			$post_id,
			$ajax_url
		);

		return $html . $content;
	}

	/**
	 * Shortcode: [meowpack_views post_id="123" period="alltime"].
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode_views( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id' => get_the_ID(),
				'period'  => 'alltime',
			),
			$atts,
			'meowpack_views'
		);

		$post_id = absint( $atts['post_id'] );
		$period  = sanitize_text_field( $atts['period'] );

		if ( ! $post_id ) {
			return '';
		}

		$ajax_url = admin_url( 'admin-ajax.php' );
		return sprintf(
			'<div class="meowpack-view-count" id="meowpack-view-count-%1$d"><script type="text/javascript" data-cfasync="false" src="%2$s?action=meowpack_get_views&post_id=%1$d"></script></div>',
			$post_id,
			$ajax_url
		);
	}

	/**
	 * Shortcode: [meowpack_trending days="7" count="5" period="this_week"].
	 *
	 * @param array $atts Attributes.
	 * @return string HTML list of trending posts.
	 */
	public function shortcode_trending( $atts ) {
		$atts = shortcode_atts(
			array(
				'days'   => 7,
				'count'  => 5,
				'period' => 'this_week',
				'title'  => __( 'Artikel Populer', 'meowpack' ),
			),
			$atts,
			'meowpack_trending'
		);

		$posts = MeowPack_Core::get_instance()->stats->get_top_posts(
			absint( $atts['count'] ),
			sanitize_text_field( $atts['period'] )
		);

		if ( empty( $posts ) ) {
			return '';
		}

		$html  = '<div class="meowpack-trending">';
		if ( $atts['title'] ) {
			$html .= '<h3 class="meowpack-trending__title">' . esc_html( $atts['title'] ) . '</h3>';
		}
		$html .= '<ol class="meowpack-trending__list">';

		foreach ( $posts as $post ) {
			$html .= sprintf(
				'<li class="meowpack-trending__item"><a href="%s">%s</a><span class="meowpack-trending__views">%s</span></li>',
				esc_url( $post['url'] ),
				esc_html( $post['title'] ),
				esc_html( self::format_number( $post['views'] ) . ' ' . __( 'dibaca', 'meowpack' ) )
			);
		}

		$html .= '</ol></div>';

		return $html;
	}

	/**
	 * Render a view count badge.
	 *
	 * @param int $views View count.
	 * @return string
	 */
	private function render_count_badge( $views ) {
		$formatted = self::format_number( $views );
		$label     = __( 'dibaca', 'meowpack' );

		return sprintf(
			'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;margin-top:-2px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><span>%s %s</span>',
			esc_html( $formatted ),
			esc_html( $label )
		);
	}

	/**
	 * Output dynamic view count via document.write (Top-10 style).
	 */
	public function ajax_get_views() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_die();
		}
		
		$views_data = MeowPack_Core::get_instance()->stats->get_post_views_detailed( $post_id );
		
		$format = MeowPack_Database::get_setting( 'views_format_text', '{icon} {total} Dilihat' );
		
		$total_formatted = self::format_number( $views_data['total'] );
		$daily_formatted = self::format_number( $views_data['daily'] );
		$icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:middle;margin-right:4px;margin-top:-2px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
		
		$text = str_replace( 
			array( '{total}', '{daily}', '{icon}' ), 
			array( $total_formatted, $daily_formatted, $icon ), 
			$format 
		);

		$output = sprintf(
			'<span class="meowpack-view-text">%s</span>',
			$text // Intentionally not escaping here because we need to output the HTML icon. But format text could contain HTML tags so it's fine.
		);

		$output = addslashes( $output );
		$output = str_replace( array( "\r", "\n" ), '', $output );

		header( 'Content-Type: application/javascript' );
		echo 'document.write(\'' . $output . '\');';
		exit;
	}

	/**
	 * Format a number in Indonesian short format.
	 *
	 * @param int $number Raw number.
	 * @return string Formatted number.
	 */
	public static function format_number( $number ) {
		$format = MeowPack_Database::get_setting( 'number_format', 'id' );
		$number = (int) $number;

		if ( 'id' === $format ) {
			if ( $number >= 1000000 ) {
				return number_format( $number / 1000000, 1, ',', '.' ) . 'jt';
			}
			if ( $number >= 1000 ) {
				return number_format( $number / 1000, 1, ',', '.' ) . 'rb';
			}
			return number_format( $number, 0, ',', '.' );
		}

		// English format.
		if ( $number >= 1000000 ) {
			return number_format( $number / 1000000, 1 ) . 'M';
		}
		if ( $number >= 1000 ) {
			return number_format( $number / 1000, 1 ) . 'K';
		}

		return number_format( $number );
	}
}
