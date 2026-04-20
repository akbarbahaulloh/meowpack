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
