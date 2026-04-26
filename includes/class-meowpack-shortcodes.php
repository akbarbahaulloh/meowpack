<?php
/**
 * MeowPack Shortcodes class.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Shortcodes
 *
 * Handles public shortcodes for displaying posts.
 */
class MeowPack_Shortcodes {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'meowpack_recent', array( $this, 'recent_posts' ) );
		add_shortcode( 'meowpack_random', array( $this, 'random_posts' ) );
		add_shortcode( 'meowpack_popular', array( $this, 'popular_posts' ) );
	}

	/**
	 * [meowpack_recent count="5"]
	 */
	public function recent_posts( $atts ) {
		$atts = shortcode_atts(
			array(
				'count' => 5,
			),
			$atts,
			'meowpack_recent'
		);

		$query = new WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => absint( $atts['count'] ),
			'ignore_sticky_posts' => 1,
		) );

		return $this->render_list( $query );
	}

	/**
	 * [meowpack_random count="5"]
	 */
	public function random_posts( $atts ) {
		$atts = shortcode_atts(
			array(
				'count' => 5,
			),
			$atts,
			'meowpack_random'
		);

		$query = new WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => absint( $atts['count'] ),
			'orderby'        => 'rand',
			'ignore_sticky_posts' => 1,
		) );

		return $this->render_list( $query );
	}

	/**
	 * [meowpack_popular count="5"]
	 */
	public function popular_posts( $atts ) {
		$atts = shortcode_atts(
			array(
				'count' => 5,
			),
			$atts,
			'meowpack_popular'
		);

		global $wpdb;
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, SUM(total_views) as total_views 
			 FROM {$wpdb->prefix}meow_daily_stats 
			 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
			 GROUP BY post_id 
			 ORDER BY total_views DESC 
			 LIMIT %d",
			absint( $atts['count'] )
		) );

		if ( empty( $results ) ) {
			return '<p>' . esc_html__( 'No popular posts yet.', 'meowpack' ) . '</p>';
		}

		$post_ids = wp_list_pluck( $results, 'post_id' );
		$query = new WP_Query( array(
			'post__in'            => $post_ids,
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'orderby'             => 'post__in',
			'ignore_sticky_posts' => 1,
		) );

		return $this->render_list( $query );
	}

	/**
	 * Render the list of posts.
	 *
	 * @param WP_Query $query
	 * @return string
	 */
	private function render_list( $query ) {
		if ( ! $query->have_posts() ) {
			return '';
		}

		// Enqueue public styles.
		wp_enqueue_style( 'meowpack-public' );

		$html = '<ul class="meowpack-post-list">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$html .= sprintf(
				'<li class="meowpack-post-list__item">
					<a href="%s" class="meowpack-post-list__link">
						<span class="meowpack-post-list__bullet">👉</span>
						<span class="meowpack-post-list__title">%s</span>
					</a>
				</li>',
				esc_url( get_permalink() ),
				get_the_title()
			);
		}
		$html .= '</ul>';

		wp_reset_postdata();

		return $html;
	}
}

// Initialize.
new MeowPack_Shortcodes();
