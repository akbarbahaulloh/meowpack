<?php
/**
 * Stats Widget class — public visitor counter widget.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MeowPack_Stats_Widget
 *
 * Displays a public visitor statistics box (sidebar/footer widget).
 * Also registers the [meowpack_counter] shortcode.
 */
class MeowPack_Stats_Widget extends WP_Widget {

	/**
	 * Constructor — register widget and shortcode.
	 */
	public function __construct() {
		parent::__construct(
			'meowpack_stats_widget',
			__( 'MeowPack — Statistik Pengunjung', 'meowpack' ),
			array(
				'description' => __( 'Tampilkan statistik pengunjung: hari ini, bulan ini, total, dan total halaman dibaca.', 'meowpack' ),
				'classname'   => 'meowpack-stats-widget',
			)
		);

		add_shortcode( 'meowpack_counter', array( $this, 'shortcode' ) );
	}

	/**
	 * Front-end display of widget.
	 *
	 * @param array $args     Widget display arguments.
	 * @param array $instance Widget settings.
	 */
	public function widget( $args, $instance ) {
		$title      = ! empty( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : __( 'Statistik Pengunjung', 'meowpack' );
		$show_types = ! empty( $instance['show_types'] ) ? $instance['show_types'] : array( 'today', 'month', 'total', 'pageviews' );

		echo wp_kses_post( $args['before_widget'] );

		if ( $title ) {
			echo wp_kses_post( $args['before_title'] . $title . $args['after_title'] );
		}

		echo $this->render_counters( $show_types ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$title      = $instance['title'] ?? __( 'Statistik Pengunjung', 'meowpack' );
		$show_types = $instance['show_types'] ?? array( 'today', 'month', 'total', 'pageviews' );

		$type_options = array(
			'today'     => __( 'Pengunjung Hari Ini', 'meowpack' ),
			'month'     => __( 'Pengunjung Bulan Ini', 'meowpack' ),
			'total'     => __( 'Total Pengunjung', 'meowpack' ),
			'pageviews' => __( 'Total Halaman Dibaca', 'meowpack' ),
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Judul:', 'meowpack' ); ?>
			</label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p><?php esc_html_e( 'Tampilkan:', 'meowpack' ); ?></p>
		<?php foreach ( $type_options as $value => $label ) : ?>
		<p>
			<input type="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_types_' . $value ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_types' ) ); ?>[]"
				value="<?php echo esc_attr( $value ); ?>"
				<?php checked( in_array( $value, (array) $show_types, true ) ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_types_' . $value ) ); ?>">
				<?php echo esc_html( $label ); ?>
			</label>
		</p>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance               = array();
		$instance['title']      = sanitize_text_field( $new_instance['title'] ?? '' );
		$allowed_types          = array( 'today', 'month', 'total', 'pageviews' );
		$selected               = array_intersect( (array) ( $new_instance['show_types'] ?? array() ), $allowed_types );
		$instance['show_types'] = $selected;
		return $instance;
	}

	/**
	 * Render counter boxes.
	 *
	 * @param array $types Types to display: today|month|total|pageviews.
	 * @return string HTML.
	 */
	public function render_counters( $types ) {
		$cache_key = 'meowpack_widget_counters_' . implode( '_', (array) $types );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$stats  = MeowPack_Core::get_instance()->stats;
		$today  = $stats->get_sitewide_stats( 'today' );
		$month  = $stats->get_sitewide_stats( 'month' );
		$alltime= $stats->get_sitewide_stats( 'alltime' );

		$data = array(
			'today'     => array(
				'value' => $today['unique_visitors'],
				'label' => __( 'Pengunjung Hari Ini', 'meowpack' ),
				'icon'  => '👁',
			),
			'month'     => array(
				'value' => $month['unique_visitors'],
				'label' => __( 'Pengunjung Bulan Ini', 'meowpack' ),
				'icon'  => '📅',
			),
			'total'     => array(
				'value' => $alltime['unique_visitors'],
				'label' => __( 'Total Pengunjung', 'meowpack' ),
				'icon'  => '👥',
			),
			'pageviews' => array(
				'value' => $alltime['total_views'],
				'label' => __( 'Total Halaman Dibaca', 'meowpack' ),
				'icon'  => '📄',
			),
		);

		$types = (array) $types;
		if ( in_array( 'all', $types, true ) ) {
			$types = array( 'today', 'month', 'total', 'pageviews' );
		}

		$html = '<div class="meowpack-counter-grid">';

		foreach ( $types as $type ) {
			if ( ! isset( $data[ $type ] ) ) {
				continue;
			}

			$item      = $data[ $type ];
			$formatted = MeowPack_ViewCounter::format_number( $item['value'] );

			$html .= sprintf(
				'<div class="meowpack-counter-box">
					<span class="meowpack-counter-box__icon">%s</span>
					<span class="meowpack-counter-box__number">%s</span>
					<span class="meowpack-counter-box__label">%s</span>
				</div>',
				esc_html( $item['icon'] ),
				esc_html( $formatted ),
				esc_html( $item['label'] )
			);
		}

		$html .= '</div>';

		set_transient( $cache_key, $html, 5 * MINUTE_IN_SECONDS );

		return $html;
	}

	/**
	 * Shortcode: [meowpack_counter type="today|month|total|pageviews|all"].
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array( 'type' => 'all' ),
			$atts,
			'meowpack_counter'
		);

		$type  = sanitize_text_field( $atts['type'] );
		$types = ( 'all' === $type ) ? array( 'today', 'month', 'total', 'pageviews' ) : explode( ',', $type );
		$types = array_map( 'trim', $types );

		// Enqueue CSS if not already done.
		wp_enqueue_style(
			'meowpack-share-buttons',
			MEOWPACK_URL . 'public/assets/meowpack-public.css',
			array(),
			MEOWPACK_VERSION
		);

		return $this->render_counters( $types );
	}
}

/**
 * MeowPack Popular Posts Widget.
 */
class MeowPack_Popular_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'meowpack_popular_widget',
			__( '🐱 MeowPack: Trending Posts', 'meowpack' ),
			array( 'description' => __( 'Tampilkan artikel paling banyak dibaca 7 hari terakhir.', 'meowpack' ) )
		);
	}

	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Terpopuler Minggu Ini', 'meowpack' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;

		echo wp_kses_post( $args['before_widget'] );
		if ( $title ) {
			echo wp_kses_post( $args['before_title'] . esc_html( $title ) . $args['after_title'] );
		}

		// Fetch popular posts using MeowPack's tracking data
		global $wpdb;
		$results = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT post_id, SUM(total_views) as total_views 
			 FROM {$wpdb->prefix}meow_daily_stats 
			 WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
			 GROUP BY post_id 
			 ORDER BY total_views DESC 
			 LIMIT %d",
			$count
		) );

		if ( ! empty( $results ) ) {
			echo '<ul class="meowpack-widget-list" style="list-style:none; padding:0; margin:0;">';
			foreach ( $results as $row ) {
				$post = get_post( $row->post_id );
				if ( ! $post || $post->post_status !== 'publish' ) {
					continue;
				}
				echo '<li style="margin-bottom:12px; display:flex; align-items:center;">';
				
				$thumb = get_the_post_thumbnail_url( $post->ID, 'thumbnail' );
				if ( $thumb ) {
					echo '<div style="width:50px; height:50px; border-radius:4px; margin-right:12px; background:url(\'' . esc_url( $thumb ) . '\') center/cover;"></div>';
				} else {
					echo '<div style="width:50px; height:50px; border-radius:4px; margin-right:12px; background:#e9ecef;"></div>';
				}

				echo '<div style="flex:1;">';
				echo '<a href="' . esc_url( get_permalink( $post->ID ) ) . '" style="font-weight:600; text-decoration:none; display:block; line-height:1.3; margin-bottom:4px; color:#cdd6f4;">' . esc_html( $post->post_title ) . '</a>';
				echo '<div style="font-size:0.8em; color:#a6adc8;">👀 ' . number_format_i18n( $row->total_views ) . ' ' . esc_html__( 'Views', 'meowpack' ) . '</div>';
				echo '</div>';
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'Belum ada data tampilan yang cukup.', 'meowpack' ) . '</p>';
		}

		echo wp_kses_post( $args['after_widget'] );
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Terpopuler Minggu Ini', 'meowpack' );
		$count = ! empty( $instance['count'] ) ? $instance['count'] : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Judul:', 'meowpack' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Jumlah ditampilkan:', 'meowpack' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" step="1" min="1" max="20" value="<?php echo esc_attr( $count ); ?>" size="3">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['count'] = ( ! empty( $new_instance['count'] ) ) ? absint( $new_instance['count'] ) : 5;
		return $instance;
	}
}

/**
 * MeowPack Random Posts Widget.
 */
class MeowPack_Random_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'meowpack_random_widget',
			__( '🐱 MeowPack: Random Posts', 'meowpack' ),
			array( 'description' => __( 'Tampilkan artikel secara acak untuk meningkatkan dwell time.', 'meowpack' ) )
		);
	}

	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Mungkin Anda Suka', 'meowpack' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;

		echo wp_kses_post( $args['before_widget'] );
		if ( $title ) {
			echo wp_kses_post( $args['before_title'] . esc_html( $title ) . $args['after_title'] );
		}

		$q_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'orderby'        => 'rand',
			'posts_per_page' => $count,
			'ignore_sticky_posts' => 1,
		);

		$query = new WP_Query( $q_args );

		if ( $query->have_posts() ) {
			echo '<ul class="meowpack-widget-list" style="list-style:none; padding:0; margin:0;">';
			while ( $query->have_posts() ) {
				$query->the_post();
				echo '<li style="margin-bottom:10px; padding-bottom:10px; border-bottom:1px dashed rgba(255,255,255,0.1);">';
				echo '👉 <a href="' . esc_url( get_permalink() ) . '" style="text-decoration:none; color:#cdd6f4;">' . get_the_title() . '</a>';
				echo '</li>';
			}
			echo '</ul>';
			wp_reset_postdata();
		}

		echo wp_kses_post( $args['after_widget'] );
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Mungkin Anda Suka', 'meowpack' );
		$count = ! empty( $instance['count'] ) ? $instance['count'] : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Judul:', 'meowpack' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"><?php esc_html_e( 'Jumlah ditampilkan:', 'meowpack' ); ?></label>
			<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>" type="number" step="1" min="1" max="20" value="<?php echo esc_attr( $count ); ?>" size="3">
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['count'] = ( ! empty( $new_instance['count'] ) ) ? absint( $new_instance['count'] ) : 5;
		return $instance;
	}
}
