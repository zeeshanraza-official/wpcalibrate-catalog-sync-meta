<?php
/**
 * Admin Menu and Submenu Management.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Admin;

use WPCalibrate\CatalogSyncMeta\Security\SecurityHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Class AdminMenu
 */
class AdminMenu {

	/**
	 * Shared WPCalibrate top-level menu slug.
	 */
	public const PARENT_MENU_SLUG = 'wpcalibrate';

	/**
	 * Submenu page slug for this plugin.
	 */
	public const SUBMENU_SLUG = 'wpcalibrate-catalog-sync-meta';

	/**
	 * Register menu hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		// Priority 9 so shared parent menu is registered safely before or alongside other WPCalibrate plugins.
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 9 );

		// Constrain admin menu icon globally across all WordPress admin screens.
		add_action( 'admin_head', array( __CLASS__, 'render_admin_menu_styles' ) );
	}

	/**
	 * Output inline CSS in admin_head to strictly constrain the WPCalibrate admin menu icon.
	 *
	 * Constrains icon to 20x20px with proper padding on all WordPress admin pages,
	 * preventing oversized 400x400 icon overflow on screens where plugin admin.css is not loaded,
	 * and swaps to dark icon on light admin color schemes.
	 *
	 * @return void
	 */
	public static function render_admin_menu_styles(): void {
		$icon_dark_url = esc_url( WPCALIBRATE_CSM_URL . 'branding/icon-dark.png' );
		?>
		<style id="wpcalibrate-csm-menu-icon-style">
			#adminmenu .toplevel_page_wpcalibrate .wp-menu-image img,
			#adminmenu #toplevel_page_wpcalibrate .wp-menu-image img,
			#adminmenu a.toplevel_page_wpcalibrate .wp-menu-image img,
			#adminmenu a[href*="page=wpcalibrate"] .wp-menu-image img {
				width: 20px !important;
				height: 20px !important;
				max-width: 20px !important;
				max-height: 20px !important;
				padding: 7px 0 0 0 !important;
				margin: 0 auto !important;
				display: block !important;
				box-sizing: content-box !important;
				object-fit: contain !important;
				opacity: 0.85 !important;
			}
			#adminmenu .toplevel_page_wpcalibrate:hover .wp-menu-image img,
			#adminmenu #toplevel_page_wpcalibrate:hover .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate.wp-has-current-submenu .wp-menu-image img,
			#adminmenu #toplevel_page_wpcalibrate.wp-has-current-submenu .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate.current .wp-menu-image img,
			#adminmenu #toplevel_page_wpcalibrate.current .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate.wp-menu-open .wp-menu-image img,
			#adminmenu #toplevel_page_wpcalibrate.wp-menu-open .wp-menu-image img {
				opacity: 1 !important;
			}
			#adminmenu .toplevel_page_wpcalibrate .wp-menu-image,
			#adminmenu #toplevel_page_wpcalibrate .wp-menu-image {
				width: 36px !important;
				height: 34px !important;
				overflow: hidden !important;
			}
			/* Light admin color scheme support: switch to icon-dark on light background */
			body.admin-color-light #adminmenu #toplevel_page_wpcalibrate .wp-menu-image img,
			body.admin-color-light #adminmenu .toplevel_page_wpcalibrate .wp-menu-image img,
			body.admin-color-light #adminmenu a.toplevel_page_wpcalibrate .wp-menu-image img,
			body.admin-color-light #adminmenu a[href*="page=wpcalibrate"] .wp-menu-image img {
				content: url('<?php echo $icon_dark_url; ?>') !important;
			}
		</style>
		<?php
	}

	/**
	 * Register shared parent menu and isolated submenu.
	 *
	 * @return void
	 */
	public static function register_admin_menu(): void {
		$capability = SecurityHelper::get_admin_capability();

		// Check if top-level WPCalibrate menu already exists to avoid duplication.
		global $menu;
		$parent_exists = false;

		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && self::PARENT_MENU_SLUG === $item[2] ) {
					$parent_exists = true;
					break;
				}
			}
		}

		if ( ! $parent_exists ) {
			add_menu_page(
				'WPCalibrate',
				'WPCalibrate',
				$capability,
				self::PARENT_MENU_SLUG,
				array( __CLASS__, 'render_parent_landing_page' ),
				WPCALIBRATE_CSM_URL . 'branding/icon-white.png',
				56
			);
		}

		// Add this plugin's isolated submenu.
		add_submenu_page(
			self::PARENT_MENU_SLUG,
			__( 'Catalog Sync for Meta', 'wpcalibrate-catalog-sync-meta' ),
			__( 'Catalog Sync for Meta', 'wpcalibrate-catalog-sync-meta' ),
			$capability,
			self::SUBMENU_SLUG,
			array( AdminController::class, 'render_page' )
		);
	}

	/**
	 * Render fallback parent landing page if clicked directly.
	 *
	 * @return void
	 */
	public static function render_parent_landing_page(): void {
		if ( ! SecurityHelper::check_admin_permission() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-catalog-sync-meta' ) );
		}

		// Redirect directly to this plugin's dashboard if it is the primary active WPCalibrate module.
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SUBMENU_SLUG ) );
		exit;
	}
}
