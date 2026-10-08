<?php
/**
 * Support and Marketplace Information Manager.
 *
 * @package WPCalibrate\CatalogSyncMeta
 */

namespace WPCalibrate\CatalogSyncMeta\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Class SupportManager
 */
class SupportManager {

	public const EMAIL       = 'support@wpcalibrate.com';
	public const WEBSITE     = 'https://wpcalibrate.com';
	public const MARKETPLACE = 'https://marketplace.wpcalibrate.com/';
	public const PHONE       = '+447474795976';

	/**
	 * Get all support details.
	 *
	 * @return array<string, string>
	 */
	public static function get_details(): array {
		return array(
			'email'       => self::EMAIL,
			'website'     => self::WEBSITE,
			'marketplace' => self::MARKETPLACE,
			'phone'       => self::PHONE,
		);
	}
}
