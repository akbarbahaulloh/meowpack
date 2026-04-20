<?php
/**
 * Admin view: Dashboard page.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats = MeowPack_Core::get_instance()->stats;
?>
<div id="meowpack-dashboard">

	<!-- Overview Cards -->
	<div class="meowpack-cards">
		<?php
		$periods = array(
			'today'   => __( 'Hari Ini', 'meowpack' ),
			'week'    => __( 'Minggu Ini', 'meowpack' ),
			'month'   => __( 'Bulan Ini', 'meowpack' ),
			'year'    => __( 'Tahun Ini', 'meowpack' ),
			'alltime' => __( 'Sepanjang Waktu', 'meowpack' ),
		);
		foreach ( $periods as $period => $label ) :
			$data = $stats->get_sitewide_stats( $period );
		?>
		<div class="meowpack-card">
			<span class="meowpack-card__period"><?php echo esc_html( $label ); ?></span>
			<strong class="meowpack-card__number"><?php echo esc_html( MeowPack_ViewCounter::format_number( $data['unique_visitors'] ) ); ?></strong>
			<span class="meowpack-card__label"><?php esc_html_e( 'Pengunjung Unik', 'meowpack' ); ?></span>
			<span class="meowpack-card__sub"><?php echo esc_html( MeowPack_ViewCounter::format_number( $data['total_views'] ) . ' ' . __( 'pageviews', 'meowpack' ) ); ?></span>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Pro Chart Section -->
	<div class="meowpack-section">
		<div class="meowpack-section__header">
			<div style="display: flex; align-items: center; gap: 20px;">
				<h2 style="font-size: 20px; font-weight: 700;"><?php esc_html_e( 'Tampilan', 'meowpack' ); ?></h2>
				<div class="meowpack-chart-toggles" style="display: flex; gap: 15px; font-size: 13px;">
					<label><input type="checkbox" id="toggle-pv" checked> <?php esc_html_e( 'Pageviews', 'meowpack' ); ?></label>
					<label><input type="checkbox" id="toggle-uv" checked> <?php esc_html_e( 'Pengunjung', 'meowpack' ); ?></label>
				</div>
			</div>
			<div class="meowpack-chart-period">
				<select id="meowpack-chart-days" style="padding: 4px 8px; border-radius: 6px; border: 1px solid #ddd;">
					<option value="7"><?php esc_html_e( '7 Hari Terakhir', 'meowpack' ); ?></option>
					<option value="30" selected><?php esc_html_e( '30 Hari Terakhir', 'meowpack' ); ?></option>
					<option value="90"><?php esc_html_e( '90 Hari Terakhir', 'meowpack' ); ?></option>
				</select>
			</div>
		</div>

		<div class="meowpack-chart-container">
			<canvas id="meowpack-chart-visitors" height="300"></canvas>
		</div>
		
		<!-- Metrics Grid (Summary for Chart Period) -->
		<?php
		// Default 30 days totals.
		$chart_data      = $stats->get_last_n_days( 30 );
		$total_pv_chart  = array_sum( array_column( $chart_data, 'total_views' ) );
		$total_uv_chart  = array_sum( array_column( $chart_data, 'unique_visitors' ) );
		$total_comments  = $stats->get_total_comments( 'alltime' );
		$total_reactions = $stats->get_total_reactions( 'alltime' );
		?>
		<div class="meowpack-metrics-grid">
			<div class="meowpack-metric-box is-active" data-metric="pv">
				<div class="meowpack-metric-box__label">👁️ <?php esc_html_e( 'Tampilan', 'meowpack' ); ?></div>
				<div class="meowpack-metric-box__value" id="metric-pv-val"><?php echo esc_html( MeowPack_ViewCounter::format_number( $total_pv_chart ) ); ?></div>
			</div>
			<div class="meowpack-metric-box" data-metric="uv">
				<div class="meowpack-metric-box__label">👥 <?php esc_html_e( 'Pengunjung', 'meowpack' ); ?></div>
				<div class="meowpack-metric-box__value" id="metric-uv-val"><?php echo esc_html( MeowPack_ViewCounter::format_number( $total_uv_chart ) ); ?></div>
			</div>
			<div class="meowpack-metric-box">
				<div class="meowpack-metric-box__label">💬 <?php esc_html_e( 'Komentar', 'meowpack' ); ?></div>
				<div class="meowpack-metric-box__value"><?php echo esc_html( MeowPack_ViewCounter::format_number( $total_comments ) ); ?></div>
			</div>
			<div class="meowpack-metric-box">
				<div class="meowpack-metric-box__label">❤️ <?php esc_html_e( 'Reaksi', 'meowpack' ); ?></div>
				<div class="meowpack-metric-box__value"><?php echo esc_html( MeowPack_ViewCounter::format_number( $total_reactions ) ); ?></div>
			</div>
		</div>
	</div>

	<!-- Export CSV Section -->
	<div class="meowpack-section meowpack-export-section">
		<div class="meowpack-section__header">
			<h2>📥 <?php esc_html_e( 'Export Data CSV', 'meowpack' ); ?></h2>
		</div>
		<div class="meowpack-export-controls">
			<div class="meowpack-export-group">
				<label for="meowpack-export-type"><?php esc_html_e( 'Tipe Data:', 'meowpack' ); ?></label>
				<select id="meowpack-export-type">
					<option value="daily"><?php esc_html_e( 'Statistik Harian (Sitewide)', 'meowpack' ); ?></option>
					<option value="posts"><?php esc_html_e( 'Per Artikel', 'meowpack' ); ?></option>
					<option value="raw"><?php esc_html_e( 'Data Mentah Kunjungan', 'meowpack' ); ?></option>
				</select>
			</div>
			<div class="meowpack-export-group">
				<label for="meowpack-export-from"><?php esc_html_e( 'Dari:', 'meowpack' ); ?></label>
				<input type="date" id="meowpack-export-from"
					value="<?php echo esc_attr( date( 'Y-m-d', strtotime( '-30 days', current_time( 'timestamp' ) ) ) ); ?>"
					max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
			</div>
			<div class="meowpack-export-group">
				<label for="meowpack-export-to"><?php esc_html_e( 'Sampai:', 'meowpack' ); ?></label>
				<input type="date" id="meowpack-export-to"
					value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"
					max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
			</div>
			<div class="meowpack-export-group meowpack-export-group--preset">
				<label><?php esc_html_e( 'Cepat:', 'meowpack' ); ?></label>
				<div class="meowpack-preset-btns">
					<button class="meowpack-preset" data-days="7"><?php esc_html_e( '7 Hari', 'meowpack' ); ?></button>
					<button class="meowpack-preset" data-days="30"><?php esc_html_e( '30 Hari', 'meowpack' ); ?></button>
					<button class="meowpack-preset" data-days="90"><?php esc_html_e( '90 Hari', 'meowpack' ); ?></button>
					<button class="meowpack-preset" data-days="365"><?php esc_html_e( '1 Tahun', 'meowpack' ); ?></button>
				</div>
			</div>
			<div class="meowpack-export-group meowpack-export-group--action">
				<button id="meowpack-btn-export" class="button button-primary meowpack-export-btn">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
						<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
						<polyline points="7 10 12 15 17 10"/>
						<line x1="12" y1="15" x2="12" y2="3"/>
					</svg>
					<?php esc_html_e( 'Download CSV', 'meowpack' ); ?>
				</button>
			</div>
		</div>
		<p class="meowpack-export-info description">
			<?php esc_html_e( 'File CSV kompatibel dengan Excel (UTF-8 BOM). Data mentah dibatasi 50.000 baris.', 'meowpack' ); ?>
		</p>
	</div>
</div>
</div>

<script>
// Embed chart data inline to avoid extra REST call on page load.
window.meowpackChartData = <?php echo wp_json_encode( $stats->get_last_n_days( 30 ) ); ?>;
window.meowpackSourceData = <?php echo wp_json_encode( $stats->get_source_breakdown( 'alltime' ) ); ?>;
</script>
