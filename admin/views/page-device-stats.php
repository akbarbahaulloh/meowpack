<?php
/**
 * Admin page: Device, Browser & OS Statistics.
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;

$stats = MeowPack_Core::get_instance()->stats;
$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'today';

// Fetch using new stats methods.
$device_data  = $stats->get_device_stats( $period );
$browser_data = $stats->get_browser_stats( $period );
$os_data      = $stats->get_os_stats( $period );

$total_views = array_sum( $device_data );

$period_tabs = array(
	'today'      => __( 'Hari Ini', 'meowpack' ),
	'this_week'  => __( 'Minggu Ini', 'meowpack' ),
	'this_month' => __( 'Bulan Ini', 'meowpack' ),
	'this_year'  => __( 'Tahun Ini', 'meowpack' ),
	'alltime'    => __( 'Semua Waktu', 'meowpack' ),
);

$device_icons  = array( 'mobile' => '📱', 'tablet' => '📋', 'desktop' => '🖥️' );
$device_colors = array( 'mobile' => '#89b4fa', 'tablet' => '#cba6f7', 'desktop' => '#a6e3a1' );
?>
<div class="meowpack-section">
	<div class="meowpack-section__header">
		<h2>📱 <?php esc_html_e( 'Statistik Perangkat', 'meowpack' ); ?></h2>
		<div class="meowpack-period-selector">
			<?php foreach ( $period_tabs as $key => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'period', $key ) ); ?>" 
				   class="button <?php echo $period === $key ? 'button-primary' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>

	<div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:24px; margin-top: 20px;">

		<!-- Device Type -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Jenis Device', 'meowpack' ); ?></h3>
			<div class="meowpack-chart-container meowpack-chart-container--pie">
				<canvas id="meow-device-chart" height="220"></canvas>
			</div>
			<table class="widefat striped meowpack-table" style="margin-top:16px;">
				<thead><tr>
					<th><?php esc_html_e( 'Device', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
					<th>%</th>
				</tr></thead>
				<tbody>
					<?php foreach ( $device_data as $type => $count ) :
						$pct = $total_views > 0 ? round( ( $count / $total_views ) * 100, 1 ) : 0;
					?>
					<tr>
						<td><?php echo esc_html( ( $device_icons[ $type ] ?? '❓' ) . ' ' . ucfirst( $type ) ); ?></td>
						<td><strong><?php echo esc_html( MeowPack_ViewCounter::format_number( $count ) ); ?></strong></td>
						<td><?php echo esc_html( $pct ); ?>%</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Browser -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Browser', 'meowpack' ); ?></h3>
			<div class="meowpack-chart-container meowpack-chart-container--pie">
				<canvas id="meow-browser-chart" height="220"></canvas>
			</div>
			<table class="widefat striped meowpack-table" style="margin-top:16px;">
				<thead><tr>
					<th><?php esc_html_e( 'Browser', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $browser_data as $browser => $count ) : ?>
					<tr>
						<td><?php echo esc_html( $browser ); ?></td>
						<td><strong><?php echo esc_html( MeowPack_ViewCounter::format_number( $count ) ); ?></strong></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- OS -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Sistem Operasi', 'meowpack' ); ?></h3>
			<div class="meowpack-chart-container meowpack-chart-container--pie">
				<canvas id="meow-os-chart" height="220"></canvas>
			</div>
			<table class="widefat striped meowpack-table" style="margin-top:16px;">
				<thead><tr>
					<th><?php esc_html_e( 'OS', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $os_data as $os => $count ) : ?>
					<tr>
						<td><?php echo esc_html( $os ); ?></td>
						<td><strong><?php echo esc_html( MeowPack_ViewCounter::format_number( $count ) ); ?></strong></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	</div>
</div>

<script>
(function($) {
	function initDeviceCharts() {
		if (typeof Chart === 'undefined') {
			setTimeout(initDeviceCharts, 100);
			return;
		}

		var palette = ['#89b4fa','#cba6f7','#a6e3a1','#f38ba8','#fab387','#f9e2af','#89dceb','#a6adc8'];

		function makeDonut(id, labels, values) {
			var ctx = document.getElementById(id);
			if (!ctx || !values.length) return;
			new Chart(ctx, {
				type: 'doughnut',
				data: {
					labels: labels,
					datasets: [{ 
						data: values, 
						backgroundColor: palette,
						borderWidth: 0
					}]
				},
				options: { 
					responsive: true, 
					maintainAspectRatio: false,
					plugins: { legend: { position: 'bottom' } },
					cutout: '70%'
				}
			});
		}

		makeDonut('meow-device-chart', 
			<?php echo wp_json_encode( array_map( 'ucfirst', array_keys( $device_data ) ) ); ?>, 
			<?php echo wp_json_encode( array_values( $device_data ) ); ?>
		);
		makeDonut('meow-browser-chart', 
			<?php echo wp_json_encode( array_keys( $browser_data ) ); ?>, 
			<?php echo wp_json_encode( array_values( $browser_data ) ); ?>
		);
		makeDonut('meow-os-chart', 
			<?php echo wp_json_encode( array_keys( $os_data ) ); ?>, 
			<?php echo wp_json_encode( array_values( $os_data ) ); ?>
		);
	}

	$(document).ready(initDeviceCharts);
})(jQuery);
</script>
