<?php
/**
 * Admin page: Author Statistics.
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;

$stats  = MeowPack_Core::get_instance()->stats;
$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'today';

$author_rows = $stats->get_author_stats( $period );

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
		<h2>✍️ <?php esc_html_e( 'Statistik Penulis', 'meowpack' ); ?></h2>
		<div class="meowpack-period-selector">
			<?php foreach ( $period_tabs as $key => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'period', $key ) ); ?>" 
				   class="button <?php echo $period === $key ? 'button-primary' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( empty( $author_rows ) ) : ?>
		<p class="meowpack-empty"><?php esc_html_e( 'Belum ada data penulis untuk periode ini.', 'meowpack' ); ?></p>
	<?php else : ?>
		<table class="widefat striped meowpack-table" style="margin-top:20px;">
			<thead>
				<tr>
					<th>#</th>
					<th><?php esc_html_e( 'Penulis', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Total Tampilan', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Pengunjung Unik', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Jumlah Artikel', 'meowpack' ); ?></th>
					<th><?php esc_html_e( 'Artikel Terpopuler', 'meowpack' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $author_rows as $i => $row ) :
					$author_id   = absint( $row['author_id'] );
					$author      = get_userdata( $author_id );
					$author_name = $author ? esc_html( $author->display_name ) : '#' . $author_id;
					$author_url  = $author ? get_author_posts_url( $author_id ) : '';
					
					// Fetch top post live (not cached for high precision here).
					$top_post = $stats->get_top_post_for_author( $author_id, $period );
				?>
				<tr>
					<td><?php echo esc_html( $i + 1 ); ?></td>
					<td>
						<?php if ( $author ) : ?>
							<div style="display:flex; align-items:center; gap:10px;">
								<?php echo get_avatar( $author_id, 32, '', $author_name, array( 'style' => 'border-radius:50%;' ) ); ?>
								<a href="<?php echo esc_url( $author_url ); ?>" target="_blank" style="font-weight:600;">
									<?php echo $author_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</a>
							</div>
						<?php else : ?>
							<?php echo esc_html( $author_name ); ?>
						<?php endif; ?>
					</td>
					<td><strong><?php echo esc_html( MeowPack_ViewCounter::format_number( $row['views'] ) ); ?></strong></td>
					<td><?php echo esc_html( MeowPack_ViewCounter::format_number( $row['unique_visitors'] ) ); ?></td>
					<td><?php echo esc_html( MeowPack_ViewCounter::format_number( $row['post_count'] ) ); ?></td>
					<td>
						<?php if ( ! empty( $top_post ) ) : ?>
							<a href="<?php echo esc_url( get_permalink( $top_post['post_id'] ) ); ?>" target="_blank">
								<?php echo esc_html( get_the_title( $top_post['post_id'] ) ?: '#' . $top_post['post_id'] ); ?>
							</a>
							<span style="color:#64748b; font-size:12px;">(<?php echo esc_html( MeowPack_ViewCounter::format_number( $top_post['views'] ) ); ?> views)</span>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
