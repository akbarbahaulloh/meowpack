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

	<!-- Chart: 30-day visitors -->
	<div class="meowpack-section">
		<div class="meowpack-section__header">
			<h2><?php esc_html_e( 'Grafik 30 Hari Terakhir', 'meowpack' ); ?></h2>
			<div class="meowpack-chart-legend">
				<span class="meowpack-legend meowpack-legend--uv"><?php esc_html_e( 'Pengunjung Unik', 'meowpack' ); ?></span>
				<span class="meowpack-legend meowpack-legend--pv"><?php esc_html_e( 'Pageviews', 'meowpack' ); ?></span>
			</div>
		</div>
		<div class="meowpack-chart-container">
			<canvas id="meowpack-chart-visitors" height="300"></canvas>
		</div>
		<p class="meowpack-chart-loading"><?php esc_html_e( 'Memuat grafik...', 'meowpack' ); ?></p>
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
					value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>"
					max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
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

	<div class="meowpack-row">

		<!-- Top Posts -->
		<div class="meowpack-section meowpack-section--half">
			<div class="meowpack-section__header">
				<h2><?php esc_html_e( 'Artikel Terpopuler', 'meowpack' ); ?></h2>
				<select id="meowpack-top-period">
					<option value="this_month"><?php esc_html_e( 'Bulan Ini', 'meowpack' ); ?></option>
					<option value="this_week"><?php esc_html_e( 'Minggu Ini', 'meowpack' ); ?></option>
					<option value="today"><?php esc_html_e( 'Hari Ini', 'meowpack' ); ?></option>
					<option value="alltime"><?php esc_html_e( 'Semua Waktu', 'meowpack' ); ?></option>
				</select>
			</div>
			<div id="meowpack-top-posts">
				<?php
				$top = $stats->get_top_posts( 10, 'this_month' );
				if ( empty( $top ) ) :
				?>
				<p class="meowpack-empty"><?php esc_html_e( 'Belum ada data.', 'meowpack' ); ?></p>
				<?php else : ?>
				<ol class="meowpack-top-list">
					<?php foreach ( $top as $i => $post ) : ?>
					<li class="meowpack-top-item">
						<span class="meowpack-top-rank"><?php echo esc_html( $i + 1 ); ?></span>
						<div class="meowpack-top-info">
							<a href="<?php echo esc_url( $post['url'] ); ?>" target="_blank"><?php echo esc_html( $post['title'] ); ?></a>
						</div>
						<span class="meowpack-top-views"><?php echo esc_html( MeowPack_ViewCounter::format_number( $post['views'] ) ); ?></span>
					</li>
					<?php endforeach; ?>
				</ol>
				<?php endif; ?>
			</div>
		</div>

		<!-- Source Breakdown -->
		<div class="meowpack-section meowpack-section--half">
			<div class="meowpack-section__header">
				<h2><?php esc_html_e( 'Sumber Kunjungan', 'meowpack' ); ?></h2>
			</div>
			<div class="meowpack-chart-container meowpack-chart-container--pie">
				<canvas id="meowpack-chart-sources" height="250"></canvas>
			</div>
			<?php
			$sources = $stats->get_source_breakdown( 'this_month' );
			$total_s = array_sum( $sources );
			if ( $total_s > 0 ) :
			?>
			<ul class="meowpack-source-list">
				<?php
				$source_labels = array(
					'direct'   => __( 'Langsung', 'meowpack' ),
					'search'   => __( 'Pencarian', 'meowpack' ),
					'social'   => __( 'Sosial Media', 'meowpack' ),
					'referral' => __( 'Referral', 'meowpack' ),
					'email'    => __( 'Email', 'meowpack' ),
				);
				$source_colors = array(
					'direct'   => '#6366f1',
					'search'   => '#06b6d4',
					'social'   => '#f59e0b',
					'referral' => '#10b981',
					'email'    => '#ec4899',
				);
				foreach ( $sources as $key => $val ) :
					$pct = $total_s > 0 ? round( $val / $total_s * 100, 1 ) : 0;
				?>
				<li class="meowpack-source-item">
					<span class="meowpack-source-dot" style="background:<?php echo esc_attr( $source_colors[ $key ] ?? '#888' ); ?>"></span>
					<span class="meowpack-source-name"><?php echo esc_html( $source_labels[ $key ] ?? $key ); ?></span>
					<span class="meowpack-source-pct"><?php echo esc_html( $pct ); ?>%</span>
					<span class="meowpack-source-val"><?php echo esc_html( MeowPack_ViewCounter::format_number( $val ) ); ?></span>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div>
	</div>
</div>

<script>
// Embed chart data inline to avoid extra REST call on page load.
window.meowpackChartData = <?php echo wp_json_encode( $stats->get_last_n_days( 30 ) ); ?>;
window.meowpackSourceData = <?php echo wp_json_encode( $stats->get_source_breakdown( 'this_month' ) ); ?>;
</script>
