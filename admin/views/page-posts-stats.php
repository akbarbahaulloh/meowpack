<?php
/**
 * Admin view: Detailed Article Statistics.
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
	'this_year'  => __( 'Tahun Ini', 'meowpack' ),
	'alltime'    => __( 'Semua Waktu', 'meowpack' ),
);

$top_posts = $stats->get_top_posts( 50, $period );
?>
<div class="meowpack-section">
	<div class="meowpack-section__header">
		<h2>📈 <?php esc_html_e( 'Statistik Artikel Terpopuler', 'meowpack' ); ?></h2>
		<div class="meowpack-period-selector">
			<?php foreach ( $period_options as $key => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'period', $key ) ); ?>" 
				   class="button <?php echo $period === $key ? 'button-primary' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( empty( $top_posts ) ) : ?>
		<p class="meowpack-empty"><?php esc_html_e( 'Belum ada data artikel untuk periode ini.', 'meowpack' ); ?></p>
	<?php else : ?>
		<table class="widefat striped meowpack-table" style="margin-top: 20px;">
			<thead>
				<tr>
					<th style="width: 50px;">#</th>
					<th><?php esc_html_e( 'Judul Artikel', 'meowpack' ); ?></th>
					<th style="width: 150px;"><?php esc_html_e( 'Pageviews', 'meowpack' ); ?></th>
					<th style="width: 150px;"><?php esc_html_e( 'Pengunjung Unik', 'meowpack' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Grafik', 'meowpack' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $top_posts as $i => $post ) : 
					$max_views = $top_posts[0]['views'];
					$pct = $max_views > 0 ? ( $post['views'] / $max_views ) * 100 : 0;
				?>
				<tr>
					<td><?php echo esc_html( $i + 1 ); ?></td>
					<td>
						<div style="font-weight: 600;">
							<a href="<?php echo esc_url( $post['url'] ); ?>" target="_blank"><?php echo esc_html( $post['title'] ); ?></a>
						</div>
						<div style="font-size: 10px; color: #64748b;"><?php echo esc_html( $post['url'] ); ?></div>
					</td>
					<td><strong><?php echo esc_html( MeowPack_ViewCounter::format_number( $post['views'] ) ); ?></strong></td>
					<td><?php echo esc_html( MeowPack_ViewCounter::format_number( $post['unique_visitors'] ) ); ?></td>
					<td>
						<div style="width: 100%; height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden; margin-top: 5px;">
							<div style="width: <?php echo esc_attr( $pct ); ?>%; height: 100%; background: #3b82f6;"></div>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
