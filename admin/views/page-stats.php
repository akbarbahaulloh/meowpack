<?php
/**
 * Admin view: Consolidated Statistics page with tabs.
 *
 * @package MeowPack
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';

$tabs = array(
	'overview' => array(
		'label' => __( 'Ringkasan', 'meowpack' ),
		'icon'  => '📊',
		'view'  => 'page-dashboard.php',
	),
	'device'   => array(
		'label' => __( 'Perangkat', 'meowpack' ),
		'icon'  => '📱',
		'view'  => 'page-device-stats.php',
	),
	'location' => array(
		'label' => __( 'Lokasi', 'meowpack' ),
		'icon'  => '🌏',
		'view'  => 'page-location-stats.php',
	),
	'author'   => array(
		'label' => __( 'Penulis', 'meowpack' ),
		'icon'  => '✍️',
		'view'  => 'page-author-stats.php',
	),
	'clicks'   => array(
		'label' => __( 'URL Keluar', 'meowpack' ),
		'icon'  => '🔗',
		'view'  => 'page-click-tracker.php',
	),
);

// Ensure tab exists, otherwise fallback to overview.
if ( ! isset( $tabs[ $current_tab ] ) ) {
	$current_tab = 'overview';
}
?>
<div class="wrap meowpack-admin">
	<h1 class="meowpack-page-title">
		<span class="meowpack-logo">🐾</span> MeowPack — <?php esc_html_e( 'Statistik', 'meowpack' ); ?>
	</h1>

	<h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
		<?php foreach ( $tabs as $id => $tab ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=meowpack&tab=' . $id ) ); ?>" 
			   class="nav-tab <?php echo $current_tab === $id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab['icon'] . ' ' . $tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<?php
	// Load the tab content.
	$view_file = MEOWPACK_DIR . 'admin/views/' . $tabs[ $current_tab ]['view'];
	if ( file_exists( $view_file ) ) {
		require_once $view_file;
	} else {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'File tampilan tidak ditemukan.', 'meowpack' ) . '</p></div>';
	}
	?>
</div>
