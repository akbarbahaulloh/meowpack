<?php
/**
 * Admin page: Location Statistics (Country / Region / City).
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;

global $wpdb;
$table  = $wpdb->prefix . 'meow_visits';
$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'today';
$drill  = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : '';

switch ( $period ) {
	case 'today':
		$where_date = $wpdb->prepare( 'AND visit_date = %s', current_time( 'Y-m-d' ) );
		break;
	case 'week':
		$monday     = date( 'Y-m-d', strtotime( 'monday this week', current_time( 'timestamp' ) ) );
		$where_date = $wpdb->prepare( 'AND visit_date >= %s', $monday );
		break;
	case 'month':
		$start      = current_time( 'Y-m' ) . '-01';
		$where_date = $wpdb->prepare( 'AND visit_date >= %s', $start );
		break;
	default:
		$where_date = '';
}

$base_where = "WHERE is_bot = 0 {$where_date}";

// Top countries.
$countries = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	"SELECT country_code, COUNT(*) AS views, COUNT(DISTINCT ip_hash) AS unique_visitors
	 FROM {$table} {$base_where} AND country_code IS NOT NULL AND country_code != ''
	 GROUP BY country_code
	 ORDER BY views DESC
	 LIMIT 30",
	ARRAY_A
);

// If drilling into a country, show regions + cities.
$regions = array();
$cities  = array();
if ( $drill ) {
	$regions = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT region, COUNT(*) AS views FROM {$table}
			 WHERE is_bot = 0 AND country_code = %s {$where_date}
			   AND region IS NOT NULL AND region != ''
			 GROUP BY region
			 ORDER BY views DESC
			 LIMIT 20",
			$drill
		),
		ARRAY_A
	);

	$cities = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT city, COUNT(*) AS views FROM {$table}
			 WHERE is_bot = 0 AND country_code = %s {$where_date}
			   AND city IS NOT NULL AND city != ''
			 GROUP BY city
			 ORDER BY views DESC
			 LIMIT 20",
			$drill
		),
		ARRAY_A
	);
}

$period_tabs = array(
	'today'   => __( 'Hari Ini', 'meowpack' ),
	'week'    => __( 'Minggu Ini', 'meowpack' ),
	'month'   => __( 'Bulan Ini', 'meowpack' ),
	'alltime' => __( 'Semua Waktu', 'meowpack' ),
);

$total_views = array_sum( array_column( $countries ?: array(), 'views' ) );
?>
<div>

	<!-- Period tabs -->
	<div style="margin-bottom:20px;">
		<?php foreach ( $period_tabs as $key => $label ) : ?>
			<a href="?page=meowpack&tab=location&period=<?php echo esc_attr( $key ); ?>"
			   class="button <?php echo $key === $period ? 'button-primary' : ''; ?>"
			   style="margin-right:6px;"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</div>

	<div style="display:grid; grid-template-columns:2fr 1fr; gap:24px; align-items:start;">

		<!-- Countries table -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Top Negara', 'meowpack' ); ?></h3>
			<?php if ( empty( $countries ) ) : ?>
				<p><?php esc_html_e( 'Belum ada data lokasi. Pastikan fitur geolokasi aktif.', 'meowpack' ); ?></p>
			<?php else : ?>
			<table class="widefat striped meowpack-table">
				<thead><tr>
					<th>#</th>
					<th><?php esc_html_e( 'Negara', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Unik', 'meowpack' ); ?></th>
					<th>%</th>
					<th></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $countries as $i => $row ) :
						$pct = $total_views > 0 ? round( ( $row['views'] / $total_views ) * 100, 1 ) : 0;
					?>
					<tr>
						<td><?php echo esc_html( $i + 1 ); ?></td>
						<td>
							<span style="font-size:1.4em; vertical-align:middle;"
							      title="<?php echo esc_attr( $row['country_code'] ); ?>">
								<?php
								// Flag emoji from country code (unicode trick).
								$cc = strtoupper( $row['country_code'] ?? '' );
								if ( strlen( $cc ) === 2 ) {
									$flag = mb_convert_encoding(
										'&#' . ( 0x1F1E0 + ord( $cc[0] ) - ord( 'A' ) ) . ';&#' . ( 0x1F1E0 + ord( $cc[1] ) - ord( 'A' ) ) . ';',
										'UTF-8',
										'HTML-ENTITIES'
									);
									echo $flag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								}
								?>
							</span>
							<?php echo esc_html( $row['country_code'] ); ?>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['unique_visitors'] ) ); ?></td>
						<td>
							<div style="background:#313244;border-radius:4px;height:8px;width:100px;">
								<div style="background:#89b4fa;height:8px;border-radius:4px;width:<?php echo esc_attr( $pct ); ?>%;"></div>
							</div>
							<?php echo esc_html( $pct ); ?>%
						</td>
						<td>
							<a href="?page=meowpack&tab=location&period=<?php echo esc_attr( $period ); ?>&country=<?php echo esc_attr( $row['country_code'] ); ?>"
							   class="button button-small"><?php esc_html_e( 'Detail', 'meowpack' ); ?></a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>

		<!-- Drill-down panel -->
		<div>
			<?php if ( $drill ) : ?>
			<div class="meowpack-card" style="margin-bottom:20px;">
				<h3><?php printf( esc_html__( 'Wilayah — %s', 'meowpack' ), esc_html( $drill ) ); ?></h3>
				<?php if ( empty( $regions ) ) : ?>
					<p><?php esc_html_e( 'Tidak ada data wilayah.', 'meowpack' ); ?></p>
				<?php else : ?>
				<table class="widefat striped meowpack-table">
					<thead><tr>
						<th><?php esc_html_e( 'Wilayah', 'meowpack' ); ?></th>
						<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $regions as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['region'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>

			<div class="meowpack-card">
				<h3><?php printf( esc_html__( 'Kota — %s', 'meowpack' ), esc_html( $drill ) ); ?></h3>
				<?php if ( empty( $cities ) ) : ?>
					<p><?php esc_html_e( 'Tidak ada data kota.', 'meowpack' ); ?></p>
				<?php else : ?>
				<table class="widefat striped meowpack-table">
					<thead><tr>
						<th><?php esc_html_e( 'Kota', 'meowpack' ); ?></th>
						<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $cities as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['city'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>
			<?php else : ?>
			<div class="meowpack-card">
				<p style="color:#6c7086; font-style:italic;">
					<?php esc_html_e( 'Klik "Detail" pada baris negara untuk melihat rincian wilayah dan kota.', 'meowpack' ); ?>
				</p>
			</div>
			<?php endif; ?>
		</div>

	</div>
</div>
