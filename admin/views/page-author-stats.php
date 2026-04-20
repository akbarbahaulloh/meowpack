<?php
/**
 * Admin page: Author Statistics.
 *
 * @package MeowPack
 */
defined( 'ABSPATH' ) || exit;

global $wpdb;
$visits_table = $wpdb->prefix . 'meow_visits';
$period       = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'today';

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

// Author stats from raw visits.
$author_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	"SELECT author_id,
	        COUNT(*) AS total_views,
	        COUNT(DISTINCT ip_hash) AS unique_visitors,
	        COUNT(DISTINCT post_id) AS post_count
	 FROM {$visits_table}
	 WHERE is_bot = 0 AND author_id > 0 {$where}
	 GROUP BY author_id
	 ORDER BY total_views DESC
	 LIMIT 25",
	ARRAY_A
);

$period_tabs = array(
	'today'   => __( 'Hari Ini', 'meowpack' ),
	'week'    => __( 'Minggu Ini', 'meowpack' ),
	'month'   => __( 'Bulan Ini', 'meowpack' ),
	'alltime' => __( 'Semua Waktu', 'meowpack' ),
);

// Top post per author lookup.
$top_post_by_author = array();
if ( ! empty( $author_rows ) ) {
	$author_ids = implode( ',', array_map( 'absint', array_column( $author_rows, 'author_id' ) ) );
	$top_posts  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT author_id, post_id, COUNT(*) AS views
		 FROM {$visits_table}
		 WHERE is_bot = 0 AND author_id IN ({$author_ids}) {$where}
		 GROUP BY author_id, post_id
		 ORDER BY author_id, views DESC",
		ARRAY_A
	);

	$seen_authors = array();
	foreach ( (array) $top_posts as $row ) {
		$aid = (int) $row['author_id'];
		if ( ! isset( $seen_authors[ $aid ] ) ) {
			$top_post_by_author[ $aid ] = $row;
			$seen_authors[ $aid ]       = true;
		}
	}
}
?>
<div>

	<!-- Period tabs -->
	<div style="margin-bottom:20px;">
		<?php foreach ( $period_tabs as $key => $label ) : ?>
			<a href="?page=meowpack&tab=author&period=<?php echo esc_attr( $key ); ?>"
			   class="button <?php echo $key === $period ? 'button-primary' : ''; ?>"
			   style="margin-right:6px;"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</div>

	<?php if ( empty( $author_rows ) ) : ?>
		<div class="meowpack-notice"><?php esc_html_e( 'Belum ada data author untuk periode ini.', 'meowpack' ); ?></div>
	<?php else : ?>
	<table class="widefat striped meowpack-table">
		<thead>
			<tr>
				<th>#</th>
				<th><?php esc_html_e( 'Penulis', 'meowpack' ); ?></th>
				<th><?php esc_html_e( 'Total Views', 'meowpack' ); ?></th>
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

				$top_post_row = $top_post_by_author[ $author_id ] ?? null;
				$top_post_id  = $top_post_row ? absint( $top_post_row['post_id'] ) : 0;
			?>
			<tr>
				<td><?php echo esc_html( $i + 1 ); ?></td>
				<td>
					<?php if ( $author ) : ?>
						<?php echo get_avatar( $author_id, 32, '', $author_name, array( 'class' => 'avatar', 'style' => 'border-radius:50%;vertical-align:middle;margin-right:8px;' ) ); ?>
						<a href="<?php echo esc_url( $author_url ); ?>" target="_blank"><?php echo $author_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
					<?php else : ?>
						<?php echo esc_html( $author_name ); ?>
					<?php endif; ?>
				</td>
				<td><strong><?php echo esc_html( number_format_i18n( (int) $row['total_views'] ) ); ?></strong></td>
				<td><?php echo esc_html( number_format_i18n( (int) $row['unique_visitors'] ) ); ?></td>
				<td><?php echo esc_html( number_format_i18n( (int) $row['post_count'] ) ); ?></td>
				<td>
					<?php if ( $top_post_id ) : ?>
						<a href="<?php echo esc_url( get_permalink( $top_post_id ) ); ?>" target="_blank">
							<?php echo esc_html( get_the_title( $top_post_id ) ?: '#' . $top_post_id ); ?>
						</a>
						<small style="color:#6c7086;">(<?php echo esc_html( number_format_i18n( (int) $top_post_row['views'] ) ); ?> views)</small>
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
