<?php
/**
 * Admin page: Outbound Click Tracker.
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;

$post_filter = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
$top_links   = MeowPack_Click_Tracker::get_top_links( 30, $post_filter );
$by_post     = MeowPack_Click_Tracker::get_clicks_by_post( 10 );
?>
<div class="wrap meowpack-wrap">
	<h1>🔗 <?php esc_html_e( 'Pelacak URL Keluar', 'meowpack' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Link eksternal yang paling banyak diklik oleh pengunjung situs Anda.', 'meowpack' ); ?>
	</p>

	<div style="display:grid; grid-template-columns:2fr 1fr; gap:24px; align-items:start;">

		<!-- Top outbound links -->
		<div class="meowpack-card">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
				<h3 style="margin:0;"><?php esc_html_e( 'Top 30 URL Keluar', 'meowpack' ); ?></h3>
				<?php if ( $post_filter ) : ?>
					<a href="?page=meowpack-click-tracker" class="button button-small">
						<?php esc_html_e( '← Semua Halaman', 'meowpack' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( empty( $top_links ) ) : ?>
				<p><?php esc_html_e( 'Belum ada data klik. Pastikan fitur Click Tracker diaktifkan di Pengaturan.', 'meowpack' ); ?></p>
			<?php else : ?>
			<table class="widefat striped meowpack-table">
				<thead><tr>
					<th>#</th>
					<th><?php esc_html_e( 'URL', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Klik', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Terakhir Diklik', 'meowpack' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $top_links as $i => $row ) : ?>
					<tr>
						<td><?php echo esc_html( $i + 1 ); ?></td>
						<td>
							<a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="noopener"
							   style="word-break:break-all; font-size:0.9em;">
								<?php echo esc_html( strlen( $row['url'] ) > 80 ? substr( $row['url'], 0, 80 ) . '…' : $row['url'] ); ?>
							</a>
						</td>
						<td><strong><?php echo esc_html( number_format_i18n( (int) $row['total_clicks'] ) ); ?></strong></td>
						<td style="white-space:nowrap; color:#6c7086; font-size:0.85em;">
							<?php echo esc_html( $row['last_clicked'] ? date_i18n( get_option( 'date_format' ), strtotime( $row['last_clicked'] ) ) : '—' ); ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>

		<!-- Click by post -->
		<div class="meowpack-card">
			<h3><?php esc_html_e( 'Klik per Artikel', 'meowpack' ); ?></h3>
			<?php if ( empty( $by_post ) ) : ?>
				<p><?php esc_html_e( 'Belum ada data.', 'meowpack' ); ?></p>
			<?php else : ?>
			<table class="widefat striped meowpack-table">
				<thead><tr>
					<th><?php esc_html_e( 'Artikel', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Klik', 'meowpack' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $by_post as $row ) :
						$post_id    = absint( $row['post_id'] );
						$post_title = get_the_title( $post_id ) ?: '#' . $post_id;
					?>
					<tr>
						<td>
							<a href="?page=meowpack-click-tracker&post_id=<?php echo esc_attr( $post_id ); ?>">
								<?php echo esc_html( strlen( $post_title ) > 40 ? substr( $post_title, 0, 40 ) . '…' : $post_title ); ?>
							</a>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['total_clicks'] ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>

	</div>
</div>
