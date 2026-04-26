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
		$stats_table  = $wpdb->prefix . 'meow_daily_stats';
		$visits_table = $wpdb->prefix . 'meow_visits';
		$seven_days_ago = date( 'Y-m-d', strtotime( '-7 days' ) );

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, SUM(views) as total_views FROM (
				SELECT post_id, SUM(total_views) as views 
				FROM {$stats_table} 
				WHERE stat_date >= %s AND post_id > 0
				GROUP BY post_id
				UNION ALL
				SELECT post_id, COUNT(*) as views
				FROM {$visits_table}
				WHERE visit_date >= %s AND is_bot = 0 AND post_id > 0
				  AND visit_date > (SELECT COALESCE(MAX(stat_date), '0000-00-00') FROM {$stats_table})
				GROUP BY post_id
			) as combined
			GROUP BY post_id
			ORDER BY total_views DESC
			LIMIT %d",
			$seven_days_ago,
			$seven_days_ago,
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
						<span class="meowpack-post-list__bullet">•</span>
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
