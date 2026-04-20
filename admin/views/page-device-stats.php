<?php
/**
 * Admin page: Device, Browser & OS Statistics.
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;

global $wpdb;
$table  = $wpdb->prefix . 'meow_visits';
$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'month';

switch ( $period ) {
	case 'today':
		$where = $wpdb->prepare( 'AND visit_date = %s', gmdate( 'Y-m-d' ) );
		break;
	case 'week':
		$start = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
		$where = $wpdb->prepare( 'AND visit_date >= %s', $start );
		break;
	case 'month':
		$start = gmdate( 'Y-m' ) . '-01';
		$where = $wpdb->prepare( 'AND visit_date >= %s', $start );
		break;
	default:
		$where = '';
}

$base_where = "WHERE is_bot = 0 {$where}";

// Device type breakdown.
$device_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	"SELECT device_type, COUNT(*) AS views FROM {$table} {$base_where} AND device_type IS NOT NULL GROUP BY device_type ORDER BY views DESC",
	ARRAY_A
);

// Browser breakdown.
$browser_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	"SELECT browser, COUNT(*) AS views FROM {$table} {$base_where} AND browser IS NOT NULL GROUP BY browser ORDER BY views DESC LIMIT 10",
	ARRAY_A
);

// OS breakdown.
$os_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	"SELECT os, COUNT(*) AS views FROM {$table} {$base_where} AND os IS NOT NULL GROUP BY os ORDER BY views DESC LIMIT 10",
	ARRAY_A
);

$total_views = array_sum( array_column( $device_rows ?: array(), 'views' ) );

$period_tabs = array(
	'today'   => __( 'Hari Ini', 'meowpack' ),
	'week'    => __( 'Minggu Ini', 'meowpack' ),
	'month'   => __( 'Bulan Ini', 'meowpack' ),
	'alltime' => __( 'Semua Waktu', 'meowpack' ),
);

$device_icons = array( 'mobile' => '📱', 'tablet' => '📋', 'desktop' => '🖥️' );
$device_colors = array( 'mobile' => '#89b4fa', 'tablet' => '#cba6f7', 'desktop' => '#a6e3a1' );
?>
<div>

	<!-- Period tabs -->
	<div style="margin-bottom:20px;">
		<?php foreach ( $period_tabs as $key => $label ) : ?>
			<a href="?page=meowpack&tab=device&period=<?php echo esc_attr( $key ); ?>"
			   class="button <?php echo $key === $period ? 'button-primary' : ''; ?>"
			   style="margin-right:6px;"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</div>

	<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:24px;">

		<!-- Device Type -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Jenis Device', 'meowpack' ); ?></h3>
			<canvas id="meow-device-chart" height="220"></canvas>
			<table class="widefat striped meowpack-table" style="margin-top:16px;">
				<thead><tr>
					<th><?php esc_html_e( 'Device', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
					<th>%</th>
				</tr></thead>
				<tbody>
					<?php foreach ( (array) $device_rows as $row ) :
						$pct = $total_views > 0 ? round( ( $row['views'] / $total_views ) * 100, 1 ) : 0;
					?>
					<tr>
						<td><?php echo esc_html( ( $device_icons[ $row['device_type'] ] ?? '❓' ) . ' ' . ucfirst( $row['device_type'] ?? 'unknown' ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td>
						<td><?php echo esc_html( $pct ); ?>%</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Browser -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Browser', 'meowpack' ); ?></h3>
			<canvas id="meow-browser-chart" height="220"></canvas>
			<table class="widefat striped meowpack-table" style="margin-top:16px;">
				<thead><tr>
					<th><?php esc_html_e( 'Browser', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( (array) $browser_rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['browser'] ?? '-' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- OS -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Sistem Operasi', 'meowpack' ); ?></h3>
			<canvas id="meow-os-chart" height="220"></canvas>
			<table class="widefat striped meowpack-table" style="margin-top:16px;">
				<thead><tr>
					<th><?php esc_html_e( 'OS', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( (array) $os_rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['os'] ?? '-' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['views'] ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	</div>
</div>

<script>
(function() {
	// Chart.js must be loaded by admin.js.
	if (typeof Chart === 'undefined') return;

	var deviceData   = <?php echo wp_json_encode( array_values( $device_rows ?: array() ) ); ?>;
	var browserData  = <?php echo wp_json_encode( array_values( $browser_rows ?: array() ) ); ?>;
	var osData       = <?php echo wp_json_encode( array_values( $os_rows ?: array() ) ); ?>;

	var palette = ['#89b4fa','#cba6f7','#a6e3a1','#f38ba8','#fab387','#f9e2af','#89dceb','#a6adc8'];

	function makeDonut(id, rows, labelKey, valueKey) {
		var ctx = document.getElementById(id);
		if (!ctx || !rows.length) return;
		new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels:   rows.map(function(r) { return r[labelKey] || 'Unknown'; }),
				datasets: [{ data: rows.map(function(r) { return parseInt(r[valueKey],10); }), backgroundColor: palette }]
			},
			options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
		});
	}

	makeDonut('meow-device-chart',  deviceData,  'device_type', 'views');
	makeDonut('meow-browser-chart', browserData, 'browser',     'views');
	makeDonut('meow-os-chart',      osData,      'os',          'views');
})();
</script>
