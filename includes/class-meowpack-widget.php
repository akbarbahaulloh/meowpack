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
			'today'     => __( 'Hari ini', 'meowpack' ),
			'week'      => __( 'Minggu ini', 'meowpack' ),
			'month'     => __( 'Bulan ini', 'meowpack' ),
			'year'      => __( 'Tahun ini', 'meowpack' ),
			'total'     => __( 'Sepanjang waktu', 'meowpack' ),
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
		$allowed_types          = array( 'today', 'week', 'month', 'year', 'total' );
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
		$year   = $stats->get_sitewide_stats( 'year' );
		$alltime= $stats->get_sitewide_stats( 'alltime' );

		$data = array(
			'today'     => array(
				'value' => $today['unique_visitors'],
				'label' => __( 'Hari ini', 'meowpack' ),
			),
			'week'      => array(
				'value' => MeowPack_Core::get_instance()->stats->get_sitewide_stats( 'week' )['unique_visitors'],
				'label' => __( 'Minggu ini', 'meowpack' ),
			),
			'month'     => array(
				'value' => $month['unique_visitors'],
				'label' => __( 'Bulan ini', 'meowpack' ),
			),
			'year'      => array(
				'value' => $year['unique_visitors'],
				'label' => __( 'Tahun ini', 'meowpack' ),
			),
			'total'     => array(
				'value' => $alltime['unique_visitors'],
				'label' => __( 'Sepanjang waktu', 'meowpack' ),
			),
			'pageviews' => array(
				'value' => $alltime['total_views'],
				'label' => __( 'Total halaman dibaca', 'meowpack' ),
			),
		);

		$types = (array) $types;
		if ( in_array( 'all', $types, true ) ) {
			$types = array( 'today', 'week', 'month', 'year', 'total' );
		}

		$html = '<ul class="meowpack-stats-list" style="list-style:none; padding:0; margin:0;">';

		foreach ( $types as $type ) {
			if ( ! isset( $data[ $type ] ) ) {
				continue;
			}

			$item      = $data[ $type ];
			$formatted = MeowPack_ViewCounter::format_number( $item['value'] );

			$html .= sprintf(
				'<li class="meowpack-stats-list__item" style="padding: 4px 0; border-bottom: 1px dashed #eee; display: flex; justify-content: space-between;">
					<span class="meowpack-stats-list__label">%s</span>
					<span class="meowpack-stats-list__number" style="font-weight: bold;">%s</span>
				</li>',
				esc_html( $item['label'] ),
				esc_html( $formatted )
			);
		}

		$html .= '</ul>';

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
		$types = ( 'all' === $type ) ? array( 'today', 'month', 'year', 'total', 'pageviews' ) : explode( ',', $type );
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
		$stats_table  = $wpdb->prefix . 'meow_daily_stats';
		$visits_table = $wpdb->prefix . 'meow_visits';
		$seven_days_ago = date( 'Y-m-d', strtotime( '-7 days' ) );

		// We merge historical stats and today's raw visits
		$results = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
			$count
		) );

		if ( ! empty( $results ) ) {
			wp_enqueue_style( 'meowpack-public', MEOWPACK_URL . 'public/assets/meowpack-public.css', array(), MEOWPACK_VERSION );
			echo '<ul class="meowpack-post-list">';
			foreach ( $results as $row ) {
				$post = get_post( $row->post_id );
				if ( ! $post || $post->post_status !== 'publish' ) {
					continue;
				}
				echo '<li class="meowpack-post-list__item">';
				echo '<a href="' . esc_url( get_permalink( $post->ID ) ) . '" class="meowpack-post-list__link">';
				echo '<span class="meowpack-post-list__bullet">•</span>';
				echo '<span class="meowpack-post-list__title">' . esc_html( $post->post_title ) . '</span>';
				echo '</a>';
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
			wp_enqueue_style( 'meowpack-public', MEOWPACK_URL . 'public/assets/meowpack-public.css', array(), MEOWPACK_VERSION );
			echo '<ul class="meowpack-post-list">';
			while ( $query->have_posts() ) {
				$query->the_post();
				echo '<li class="meowpack-post-list__item">';
				echo '<a href="' . esc_url( get_permalink() ) . '" class="meowpack-post-list__link">';
				echo '<span class="meowpack-post-list__bullet">•</span>';
				echo '<span class="meowpack-post-list__title">' . get_the_title() . '</span>';
				echo '</a>';
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

/**
 * MeowPack Recent Posts Widget.
 */
class MeowPack_Recent_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'meowpack_recent_widget',
			__( '🐱 MeowPack: Recent Posts', 'meowpack' ),
			array( 'description' => __( 'Tampilkan artikel terbaru dengan gaya premium.', 'meowpack' ) )
		);
	}

	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Artikel Terbaru', 'meowpack' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;

		echo wp_kses_post( $args['before_widget'] );
		if ( $title ) {
			echo wp_kses_post( $args['before_title'] . esc_html( $title ) . $args['after_title'] );
		}

		$q_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'ignore_sticky_posts' => 1,
		);

		$query = new WP_Query( $q_args );

		if ( $query->have_posts() ) {
			wp_enqueue_style( 'meowpack-public', MEOWPACK_URL . 'public/assets/meowpack-public.css', array(), MEOWPACK_VERSION );
			echo '<ul class="meowpack-post-list">';
			while ( $query->have_posts() ) {
				$query->the_post();
				echo '<li class="meowpack-post-list__item">';
				echo '<a href="' . esc_url( get_permalink() ) . '" class="meowpack-post-list__link">';
				echo '<span class="meowpack-post-list__bullet">•</span>';
				echo '<span class="meowpack-post-list__title">' . get_the_title() . '</span>';
				echo '</a>';
				echo '</li>';
			}
			echo '</ul>';
			wp_reset_postdata();
		}

		echo wp_kses_post( $args['after_widget'] );
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Artikel Terbaru', 'meowpack' );
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
 * MeowPack Related Posts Widget.
 */
class MeowPack_Related_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'meowpack_related_widget',
			__( '🐱 MeowPack: Related Posts', 'meowpack' ),
			array( 'description' => __( 'Tampilkan artikel terkait berdasarkan kategori (hanya tampil di halaman artikel tunggal).', 'meowpack' ) )
		);
	}

	public function widget( $args, $instance ) {
		if ( ! is_single() ) {
			return; // Only show on single post pages
		}

		global $post;

		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Artikel Terkait', 'meowpack' );
		$count = ! empty( $instance['count'] ) ? absint( $instance['count'] ) : 5;

		echo wp_kses_post( $args['before_widget'] );
		if ( $title ) {
			echo wp_kses_post( $args['before_title'] . esc_html( $title ) . $args['after_title'] );
		}

		$categories = get_the_category( $post->ID );
		$category_ids = array();
		if ( $categories ) {
			foreach ( $categories as $category ) {
				$category_ids[] = $category->term_id;
			}
		}

		$q_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'ignore_sticky_posts' => 1,
			'post__not_in'   => array( $post->ID ),
		);

		if ( ! empty( $category_ids ) ) {
			$q_args['category__in'] = $category_ids;
		}

		$query = new WP_Query( $q_args );

		if ( $query->have_posts() ) {
			wp_enqueue_style( 'meowpack-public', MEOWPACK_URL . 'public/assets/meowpack-public.css', array(), MEOWPACK_VERSION );
			echo '<ul class="meowpack-post-list">';
			while ( $query->have_posts() ) {
				$query->the_post();
				echo '<li class="meowpack-post-list__item">';
				echo '<a href="' . esc_url( get_permalink() ) . '" class="meowpack-post-list__link">';
				echo '<span class="meowpack-post-list__bullet">•</span>';
				echo '<span class="meowpack-post-list__title">' . get_the_title() . '</span>';
				echo '</a>';
				echo '</li>';
			}
			echo '</ul>';
			wp_reset_postdata();
		} else {
			echo '<p>' . esc_html__( 'Belum ada artikel terkait.', 'meowpack' ) . '</p>';
		}

		echo wp_kses_post( $args['after_widget'] );
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Artikel Terkait', 'meowpack' );
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
		<p><em><?php esc_html_e( 'Catatan: Widget ini hanya akan muncul di halaman artikel tunggal.', 'meowpack' ); ?></em></p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['count'] = ( ! empty( $new_instance['count'] ) ) ? absint( $new_instance['count'] ) : 5;
		return $instance;
	}
}
