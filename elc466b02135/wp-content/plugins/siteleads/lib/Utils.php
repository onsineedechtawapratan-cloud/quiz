<?php

namespace SiteLeads;

use SiteLeads\Admin\Admin;
use SiteLeads\Core\AssetsRegistry;
use SiteLeads\Core\Flags;

class Utils {


	public static function getFilePath( $path ) {
		return SITELEADS_ROOT_DIR . "$path";
	}

	public static function getUrl( $path ) {
		return SITELEADS_ROOT_URL . "/$path";
	}


	public static function getAssetName( $name ) {
		return AssetsRegistry::getAssetHandle( $name );
	}

	public static function isSiteLeadsAdminPage() {
		global $pagenow;

		if ( substr( $pagenow, 0, - 4 ) === 'admin' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : false;

			$available_pages = apply_filters(
				'siteleads_admin_pages',
				array(
					'siteleads',
					'siteleads-analytics',
					'siteleads-messages',
					'siteleads-leads',
					'siteleads-my-account',
					'siteleads-upgrade',
				)
			);

			return in_array( $page, $available_pages, true );
		}

		return false;
	}

	public static function getIconAsset( $filename ) {

		$svg_path = SITELEADS_ROOT_DIR . 'assets/icons/' . $filename;
		$ret      = '<!-- SVG not found -->';
		if ( file_exists( $svg_path ) ) {
			$ret = file_get_contents( $svg_path );
		}

		return $ret;
	}

	public static function printIconAsset( $filename ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- We trust our own assets
		echo self::getIconAsset( $filename );
	}

	public static function getAssetFile( $filename ) {

		$file_path = SITELEADS_ROOT_DIR . 'assets/' . $filename;

		if ( file_exists( $file_path ) ) {
			return file_get_contents( $file_path );
		} else {
			return '<!-- FILE not found -->';
		}
	}

	/**
	 * Returns the widget ID if in preview mode, null if preview mode but no widget ID, false if not in preview mode
	 *
	 * @return string|null|false
	 */
	public static function getPreviewedWidgetId() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- we don't verify for nonce, as this is only used to detect if we're in preview mode, and the presence of a valid widget ID is not a security concern
		$preview = isset( $_GET['siteleads-preview'] ) ? filter_var( sanitize_text_field( wp_unslash( $_GET['siteleads-preview'] ) ), FILTER_VALIDATE_BOOLEAN ) : false;

		if ( $preview ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- we don't verify for nonce, as this is only used to detect if we're in preview mode, and the presence of a valid widget ID is not a security concern
			$widgetId = isset( $_GET['widgetId'] ) ? sanitize_text_field( wp_unslash( $_GET['widgetId'] ) ) : null;

			if ( $widgetId ) {
				return $widgetId;
			}

			return null;
		}

		return false;
	}

	/**
	 * Returns the widget ID if in live preview mode, null if preview mode but no widget ID, false if not in preview mode
	 *
	 * @return string|null|false
	 */
	public static function getLivePreviewedWidgetId() {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- we don't verify for nonce, as this is only used to detect if we're in live preview mode, and the presence of a valid widget ID is not a security concern
		$livePreview = isset( $_GET['siteleads-live-preview'] ) ? filter_var( sanitize_text_field( wp_unslash( $_GET['siteleads-live-preview'] ) ), FILTER_VALIDATE_BOOLEAN ) : false;

		if ( $livePreview ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- we don't verify for nonce, as this is only used to detect if we're in live preview mode, and the presence of a valid widget ID is not a security concern
			$widgetId = isset( $_GET['widgetId'] ) ? sanitize_text_field( wp_unslash( $_GET['widgetId'] ) ) : null;

			if ( $widgetId ) {
				return $widgetId;
			}

			return null;
		}

		return false;
	}

	public static function isInsideBoundingBox( $bbox, $coordinates ) {

		try {
			if ( $bbox && $coordinates && is_array( $bbox ) && is_array( $coordinates ) && isset( $coordinates['lat'] ) && isset( $coordinates['long'] ) ) {
				list( $min_latitude, $max_latitude, $min_longitude, $max_longitude ) = $bbox;
				$lat  = $coordinates['lat'];
				$long = $coordinates['long'];

				return $lat >= $min_latitude && $lat <= $max_latitude && $long >= $min_longitude && $long <= $max_longitude;
			}
		} catch ( \Exception $e ) {
			return false;
		}

		return false;
	}

	public static function isBlogPage() {
		global $wp_query;
		$blogPageID    = intval( get_option( 'page_for_posts' ) );
		$currentPageID = isset( $wp_query->query_vars['page_id'] ) ? $wp_query->query_vars['page_id'] : false;
		if ( isset( $wp_query->queried_object_id ) && $currentPageID == false ) {
			$currentPageID = $wp_query->queried_object_id;
		}

		if ( $currentPageID == $blogPageID ) {
			return $blogPageID;
		}

		return false;
	}

	public static function getShopPageId() {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return null;
		}
		$shopPageID = wc_get_page_id( 'shop' );

		return $shopPageID;
	}


	public static function normalizeTimezone( $timezone ) {
		if ( $timezone === 'website' ) {
			return wp_timezone();
		}

			return new \DateTimeZone( $timezone );
	}

	private static function convertTimeToSeconds( $time ) {
		$parts = explode( ':', $time );
		if ( count( $parts ) === 2 ) {
			$hours   = intval( $parts[0] );
			$minutes = intval( $parts[1] );

			return $hours * 3600 + $minutes * 60;
		}

		return false;
	}

	/**
	 * Check if a given timestamp is within a date+time range in a specific timezone.
	 *
	 * @param string $timezone "label#offsetHours" OR "website"
	 * @param string|null $startDate "YYYY-MM-DD"
	 * @param string|null $endDate "YYYY-MM-DD"
	 * @param string|null $startTime "HH:MM"
	 * @param string|null $endTime "HH:MM"
	 * @param int|null $timestamp UTC timestamp (defaults to now)
	 *
	 * @return bool
	 */
	public static function isBetweenDatesWithTimezone(
		$timezone,
		$startDate,
		$startTime,
		$endDate,
		$endTime
	) {
		if ( empty( $startDate ) || empty( $startTime ) ) {
			return true;
		}

		$tz      = self::normalizeTimezone( $timezone );
		$current = new \DateTime( 'now', $tz );

		$startDateTime = new \DateTime( $startDate . ' ' . $startTime, $tz );
		$endDateTime   = new \DateTime( ( $endDate ?? $startDate ) . ' ' . ( $endTime ?? $startTime ), $tz );

		return $current >= $startDateTime && $current < $endDateTime;
	}

	/**
	 * Check if the current time (based on timezone) is within a start–end range.
	 *
	 * @param string $timezone "Europe\Bucharest" or "website"
	 * @param string $startTime e.g. "09:00"
	 * @param string $endTime e.g. "17:00"
	 *
	 * @return bool
	 */
	public static function isTimeWithinRange( $timezone, $startTime, $endTime ) {

		$tz = self::normalizeTimezone( $timezone );
		$dt = new \DateTime( 'now', $tz );

		// get number of secconds since midnight in $dt's timezone
		$currentSeconds = (int) $dt->format( 'H' ) * 3600 + (int) $dt->format( 'i' ) * 60 + (int) $dt->format( 's' );

		$startTimeSeconds = self::convertTimeToSeconds( $startTime );
		$endTimeSeconds   = self::convertTimeToSeconds( $endTime );

		if ( $startTimeSeconds === false || $endTimeSeconds === false ) {
			return true; // if time format is invalid, ignore time targeting
		}

		return $currentSeconds >= $startTimeSeconds && $currentSeconds < $endTimeSeconds;
	}

	/**
	 * Get the day of the week for a given UTC timestamp and timezone.
	 *
	 * @param string $timezone "Europe\Bucharest" OR "website"
	 *
	 * @return string  Day of week in lowercase, e.g. "monday"
	 */
	public static function getDayOfWeekFromTimezone( $timezone = 'website' ) {
		$dt = new \DateTime( 'now', self::normalizeTimezone( $timezone ) );

		return strtolower( $dt->format( 'l' ) );
	}



	private static function compose_site_url( $path, $args = array() ) {
		$root_url = untrailingslashit( SITELEADS_WEBSITE_URL );
		$path     = ltrim( $path, '/' );

		$default_args = array(
			'sl_key'               => get_option( 'siteleads_ai_api_key', '' ),
			'utm_theme'            => get_template(),
			'utm_childtheme'       => get_stylesheet(),
			'utm_install_source'   => Flags::get( 'start_source', 'other' ),
			'utm_activated_on'     => Flags::get( 'activation_time', '' ),
			'utm_pro_activated_on' => Flags::get( 'pro_activation_time', '' ),
		);

		$args = array_merge( $default_args, $args );

		$args = array_map( 'urlencode', $args );
		$args = array_filter( $args );

		return add_query_arg( $args, "{$root_url}/{$path}" );
	}

	public static function getWebsiteURL( $target = 'homepage' ) {

		$args = apply_filters(
			'siteleads_website_root_url_args',
			array()
		);

		switch ( $target ) {
			case 'upgrade':
				return self::compose_site_url( '/#pricing', $args );
			case 'documentation':
				return self::compose_site_url( '/docs', $args );
			case 'support':
			case 'contact':
				return self::compose_site_url( '/contact', $args );
				break;
			default:
				return self::compose_site_url( '/', $args );
		}
	}

	public static function convertStyleArrayToString( $arr ) {
		$vars = array();

		foreach ( $arr as $key => $value ) {
			if ( $value === null ) {
				continue;
			}
			$vars[] = "$key: $value";
		}

		return implode( ';', $vars );
	}

	public static function hex2rgb( $hex ) {
		$hex = str_replace( '#', '', $hex );

		if ( strlen( $hex ) == 3 ) {
			$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
			$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
			$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
		} else {
			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );
		}

		return array( $r, $g, $b );
	}

	public static function fontSettingsToStyle( $settings ) {
		if ( ! $settings || ! count( $settings ) ) {
			return '';
		}

		$raw_settings = array(
			'color'       => isset( $settings['color'] ) ? $settings['color'] : null,
			'font-family' => isset( $settings['family'] ) ? $settings['family'] : null,
			'font-weight' => isset( $settings['weight'] ) ? $settings['weight'] : null,
			'font-size'   => isset( $settings['size'] ) ? $settings['size'] . 'px' : null,
			'text-align'  => isset( $settings['align'] ) ? $settings['align'] : null,
		);

		$style = array_filter( $raw_settings );

		return Utils::convertStyleArrayToString( $style );
	}

	public static function buildGoogleFontsURL( $fonts ) {
		$href       = 'https://fonts.googleapis.com/css';
		$query_args = array(
			'display' => 'swap',
		);

		$family_query_parts = array();
		foreach ( $fonts as $family => $variants ) {

			if ( empty( $variants ) ) {
				continue;
			}

			$family_query_part = $family;
			if ( count( $variants ) > 0 ) {
				$family_query_part .= ':' . implode( ',', $variants );
			}
			$family_query_parts[] = $family_query_part;
		}

		$query_args['family'] = urlencode( implode( '|', $family_query_parts ) );

		return add_query_arg( $query_args, $href );
	}

	public static function base64EncodeJWT( $str ) {
		return str_replace( array( '+', '/', '=' ), array( '-', '_', '' ), base64_encode( $str ) );
	}

	public static function generateWidgetJWT() {
		$private_key            = Admin::getApiKey();
		$private_key_identifier = Admin::getApiKeyIdentifier();

		$header = json_encode(
			array(
				'alg' => 'HS256',
				'typ' => 'JWT',
			)
		);

		$time = time();

		$payload = json_encode(
			array(
				'site-url'           => site_url(),
				'api-key-identifier' => $private_key_identifier,
				'exp'                => $time + 300,
				'iat'                => $time,
			)
		);

		$base64UrlHeader  = Utils::base64EncodeJWT( $header );
		$base64UrlPayload = Utils::base64EncodeJWT( $payload );

		$signature          = hash_hmac( 'sha256', $base64UrlHeader . '.' . $base64UrlPayload, $private_key, true );
		$base64UrlSignature = Utils::base64EncodeJWT( $signature );

		return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
	}
}
