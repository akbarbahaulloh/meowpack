<?php
/**
 * Admin view: Detailed Location Statistics (Country, Region, City).
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;

$stats  = MeowPack_Core::get_instance()->stats;
$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'today';

$countries = $stats->get_country_stats( $period );
$regions   = $stats->get_region_stats( $period );
$cities    = $stats->get_city_stats( $period );

$total_v   = array_sum( array_column( $countries, 'views' ) );

$period_tabs = array(
	'today'      => __( 'Hari Ini', 'meowpack' ),
	'this_week'  => __( 'Minggu Ini', 'meowpack' ),
	'this_month' => __( 'Bulan Ini', 'meowpack' ),
	'this_year'  => __( 'Tahun Ini', 'meowpack' ),
	'alltime'    => __( 'Semua Waktu', 'meowpack' ),
);
?>
<div class="meowpack-section">
	<div class="meowpack-section__header">
		<h2>🌍 <?php esc_html_e( 'Analisis Geografis Pengunjung', 'meowpack' ); ?></h2>
		<div class="meowpack-period-selector">
			<?php foreach ( $period_tabs as $key => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'period', $key ) ); ?>" 
				   class="button <?php echo $period === $key ? 'button-primary' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>

	<div style="display: flex; gap: 30px; margin-top: 30px; align-items: flex-start; flex-wrap: wrap;">
		
		<!-- Left Side: Country Table & Chart -->
		<div style="flex: 1; min-width: 350px;">
			<div class="meowpack-card">
				<h3 style="margin-top:0;">🚩 <?php esc_html_e( 'Negara Asal', 'meowpack' ); ?></h3>
				<?php if ( empty( $countries ) ) : ?>
					<p class="meowpack-empty"><?php esc_html_e( 'Belum ada data negara.', 'meowpack' ); ?></p>
				<?php else : ?>
					<div class="meowpack-chart-container meowpack-chart-container--pie" style="height: 200px; margin-bottom: 20px;">
						<canvas id="meow-country-chart"></canvas>
					</div>
					<table class="widefat striped meowpack-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Negara', 'meowpack' ); ?></th>
								<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
								<th>%</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice($countries, 0, 10) as $row ) :
								$pct = $total_v > 0 ? round( ( $row['views'] / $total_v ) * 100, 1 ) : 0;
								$flag_url = 'https://flagcdn.com/w20/' . strtolower( $row['country_code'] ) . '.png';
							?>
							<tr>
								<td>
									<img src="<?php echo esc_url( $flag_url ); ?>" width="20" style="vertical-align:middle; margin-right:8px;">
									<strong><?php echo esc_html( $row['country_code'] ); ?></strong>
								</td>
								<td><?php echo esc_html( MeowPack_ViewCounter::format_number( $row['views'] ) ); ?></td>
								<td><?php echo esc_html( $pct ); ?>%</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<!-- Middle Side: Regions -->
		<div style="flex: 1; min-width: 300px;">
			<div class="meowpack-card">
				<h3 style="margin-top:0;">📍 <?php esc_html_e( 'Wilayah / Provinsi', 'meowpack' ); ?></h3>
				<?php if ( empty( $regions ) ) : ?>
					<p class="meowpack-empty"><?php esc_html_e( 'Belum ada data wilayah.', 'meowpack' ); ?></p>
				<?php else : ?>
					<table class="widefat striped meowpack-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nama Wilayah', 'meowpack' ); ?></th>
								<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $regions as $name => $count ) : ?>
							<tr>
								<td><?php echo esc_html( $name ?: 'Unknown' ); ?></td>
								<td><strong><?php echo esc_html( MeowPack_ViewCounter::format_number( $count ) ); ?></strong></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<!-- Right Side: Cities -->
		<div style="flex: 1; min-width: 300px;">
			<div class="meowpack-card">
				<h3 style="margin-top:0;">🏙️ <?php esc_html_e( 'Kota', 'meowpack' ); ?></h3>
				<?php if ( empty( $cities ) ) : ?>
					<p class="meowpack-empty"><?php esc_html_e( 'Belum ada data kota.', 'meowpack' ); ?></p>
				<?php else : ?>
					<table class="widefat striped meowpack-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nama Kota', 'meowpack' ); ?></th>
								<th><?php esc_html_e( 'Views', 'meowpack' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $cities as $name => $count ) : ?>
							<tr>
								<td><?php echo esc_html( $name ?: 'Unknown' ); ?></td>
								<td><strong><?php echo esc_html( MeowPack_ViewCounter::format_number( $count ) ); ?></strong></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

	</div>
</div>

<script>
(function($) {
	function initGeoChart() {
		if (typeof Chart === 'undefined') {
			setTimeout(initGeoChart, 100);
			return;
		}

		var countryData = <?php echo wp_json_encode( array_slice( $countries, 0, 8 ) ); ?>;
		var ctx = document.getElementById('meow-country-chart');
		if (!ctx || !countryData.length) return;

		new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: countryData.map(function(c) { return c.country_code; }),
				datasets: [{
					data: countryData.map(function(c) { return parseInt(c.views, 10); }),
					backgroundColor: ['#6366f1','#06b6d4','#f59e0b','#10b981','#ec4899','#8b5cf6','#f43f5e','#3b82f6'],
					borderWidth: 0
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				cutout: '75%'
			}
		});
	}

	$(document).ready(initGeoChart);
})(jQuery);
</script>
