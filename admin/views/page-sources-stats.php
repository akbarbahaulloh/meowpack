<?php
/**
 * Admin view: Traffic Source Statistics.
 *
 * @package MeowPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats  = MeowPack_Core::get_instance()->stats;
$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'today';

$period_options = array(
	'today'      => __( 'Hari Ini', 'meowpack' ),
	'this_week'  => __( 'Minggu Ini', 'meowpack' ),
	'this_month' => __( 'Bulan Ini', 'meowpack' ),
	'alltime'    => __( 'Semua Waktu', 'meowpack' ),
);

$sources = $stats->get_source_breakdown( $period );
$total_s = array_sum( $sources );
?>

<div class="meowpack-section">
	<div class="meowpack-section__header">
		<h2>🔍 <?php esc_html_e( 'Sumber Kunjungan', 'meowpack' ); ?></h2>
		<div class="meowpack-period-selector">
			<?php foreach ( $period_options as $key => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'period', $key ) ); ?>" 
				   class="button <?php echo $period === $key ? 'button-primary' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>

	<div style="display: flex; gap: 30px; margin-top: 30px; align-items: center; flex-wrap: wrap;">
		<div style="flex: 1; min-width: 300px;">
			<div class="meowpack-chart-container meowpack-chart-container--pie">
				<canvas id="meowpack-chart-sources-dedicated" height="300"></canvas>
			</div>
		</div>

		<div style="flex: 1; min-width: 300px;">
			<?php if ( $total_s <= 0 ) : ?>
				<p class="meowpack-empty"><?php esc_html_e( 'Belum ada data sumber kunjungan untuk periode ini.', 'meowpack' ); ?></p>
			<?php else : ?>
				<table class="widefat striped meowpack-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Sumber', 'meowpack' ); ?></th>
							<th><?php esc_html_e( 'Kunjungan', 'meowpack' ); ?></th>
							<th>%</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$source_labels = array(
							'direct'   => __( 'Langsung / Direct', 'meowpack' ),
							'search'   => __( 'Pencarian (Google/Bing)', 'meowpack' ),
							'social'   => __( 'Sosial Media', 'meowpack' ),
							'referral' => __( 'Situs Lain (Referral)', 'meowpack' ),
							'email'    => __( 'Layanan Email', 'meowpack' ),
						);
						$source_colors = array(
							'direct'   => '#6366f1',
							'search'   => '#06b6d4',
							'social'   => '#f59e0b',
							'referral' => '#10b981',
							'email'    => '#ec4899',
						);

						foreach ( $sources as $key => $val ) :
							if ( $val <= 0 ) continue;
							$pct = round( ( $val / $total_s ) * 100, 1 );
						?>
						<tr>
							<td>
								<span class="meowpack-source-dot" style="background:<?php echo esc_attr( $source_colors[ $key ] ?? '#888' ); ?>"></span>
								<?php echo esc_html( $source_labels[ $key ] ?? $key ); ?>
							</td>
							<td><strong><?php echo esc_html( MeowPack_ViewCounter::format_number( $val ) ); ?></strong></td>
							<td><?php echo esc_html( $pct ); ?>%</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
(function($) {
	$(document).ready(function() {
		if (typeof Chart === 'undefined') return;
		
		const sourceData = <?php echo wp_json_encode( $sources ); ?>;
		const ctx = document.getElementById('meowpack-chart-sources-dedicated');
		if (!ctx) return;

		new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: ['Langsung', 'Pencarian', 'Sosial Media', 'Referral', 'Email'],
				datasets: [{
					data: [
						sourceData.direct,
						sourceData.search,
						sourceData.social,
						sourceData.referral,
						sourceData.email
					],
					backgroundColor: ['#6366f1', '#06b6d4', '#f59e0b', '#10b981', '#ec4899'],
					borderWidth: 0
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { position: 'bottom' }
				},
				cutout: '70%'
			}
		});
	});
})(jQuery);
</script>
