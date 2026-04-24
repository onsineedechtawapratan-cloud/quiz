<?php

namespace SiteLeads\Core;

use SiteLeads\Constants;
use SiteLeads\Core\LodashBasic;
use SiteLeads\Utils;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class DataHelper {

	protected $settingPath = null;

	/**
	 * @var array|null All the settings from the admin page of the plugin plugin
	 */
	protected static $pluginData     = null;
	protected static $pluginDataPost = null;
	protected $data                  = null;

	public function __construct( $settingPath = null ) {
		if ( $settingPath ) {
			$this->settingPath = $settingPath;
		}
	}

	public function setStyle() {
		/**
		 * Implement if needed
		 */
	}

	public function setProp( $path, $value, $options = array() ) {
		$mergedOptions = $this->getMergedOptions( $options );
		$absolutePath  = $this->getAbsolutePropPath( $path, $mergedOptions );
		$this->setData( $absolutePath, $value, $mergedOptions );
	}

	public function getStyle( $path, $defaultValue = null, $options = array() ) {
		$mergedOptions = $this->getMergedOptions( $options );
		$absolutePath  = $this->getAbsoluteStylePath( $path, $mergedOptions );

		return $this->getData( $absolutePath, $defaultValue, $mergedOptions );
	}

	public function getProp( $path, $defaultValue = null, $options = array() ) {
		$mergedOptions = $this->getMergedOptions( $options );
		$absolutePath  = $this->getAbsolutePropPath( $path, $mergedOptions );

		return $this->getData( $absolutePath, $defaultValue, $mergedOptions );
	}

	public function getSubProp( $path, $defaultValue = null, $options = array() ) {
		$mergedOptions = $this->getMergedOptions( $options );
		$absolutePath  = $this->getAbsoluteSubPropPath( $path, $mergedOptions );

		return $this->getData( $absolutePath, $defaultValue, $mergedOptions );
	}

	public function getMergedOptions( $options ) {
		$defaultOptions = array(
			'styledComponent' => null,
			'media'           => 'desktop',
			'state'           => 'normal',
			'fromRoot'        => false,
			'unset'           => false,
		);

		return LodashBasic::merge( $defaultOptions, $options );
	}

	public function getAbsoluteStylePath( $path, $options = array() ) {
		return $this->getAbsolutePath( $path, array_merge( $options, array( 'prefix' => 'style' ) ) );
	}

	public function getAbsolutePropPath( $path, $options = array() ) {
		return $this->getAbsolutePath( $path, array_merge( $options, array( 'prefix' => 'props' ) ) );
	}

	public function getAbsoluteSubPropPath( $path, $options = array() ) {
		return $this->getAbsolutePath( $path, array_merge( $options, array( 'prefix' => '' ) ) );
	}

	public function getAbsolutePath( $relativePath, $options = array() ) {
		list ( 'prefix' => $prefix, 'media' => $media, 'state' => $state, 'styledComponent' => $styledComponent ) = $options;

		$paths = $prefix ? array( $prefix ) : array();
		if ( $styledComponent ) {
			$paths[] = "descendants.$styledComponent";
		}
		if ( $media !== 'desktop' ) {
			$paths[] = "media.$media";
		}
		if ( $state !== 'normal' ) {
			$paths[] = "state.$state";
		}

		if ( $relativePath ) {
			$paths[] = $relativePath;
		}
		$joinedPaths = implode( '.', $paths );

		return $joinedPaths;
	}

	public function getData( $path, $defaultValue ) {
		$source = $this->getSourceData();

		return LodashBasic::get( $source, $path, $defaultValue );
	}

	public function setData( $path, $newValue ) {
		$this->getSourceData();

		LodashBasic::set( $this->data, $path, $newValue );
	}

	public function getSourceData() {

		if ( ! $this->settingPath ) {
			// translators: %s is the class name
			throw new \Exception( sprintf( esc_html__( 'settingPath property is not set in %s', 'siteleads' ), esc_html( get_class( $this ) ) ) );
		}

		if ( ! $this->data ) {
			$pluginData = static::getPluginData();
			$this->data = LodashBasic::get( $pluginData, $this->settingPath, array() );
		}

		return $this->data;
	}

	public static function getPluginData() {
		if ( ! static::$pluginData ) {
			static::$pluginData = static::getDataFromDatabase();
		}

		return static::$pluginData;
	}

	public static function getPluginDataPost() {
		if ( empty( static::$pluginDataPost ) ) {
			static::getPluginData();
		}

		return static::$pluginDataPost;
	}

	public static function getPreviewedWidgetId() {
		return Utils::getPreviewedWidgetId();
	}

	public static function getLivePreviewedWidgetId() {
		return Utils::getLivePreviewedWidgetId();
	}

	public static function getDataFromDatabase() {
		$query      = new \WP_Query(
			array(
				'post_type'     => Constants::$siteLeadsDataPostType,
				'post_status'   => array( 'draft', 'publish' ),
				'no_found_rows' => true,
				'post_per_page' => 1,
			)
		);
		$pluginData = array();
		if ( $query->have_posts() ) {
			$post = $query->next_post();

			$widgetId = static::getPreviewedWidgetId();
			if ( ! $widgetId ) {
				$widgetId = static::getLivePreviewedWidgetId();
			}

			if ( $widgetId ) {
				$data = get_transient( 'siteleads_preview_data' );

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a preview request, we just check a boolean flag in the request to decide whether to use the transient data or not
				$skip_widget_preview_transient_data = LodashBasic::has( $_REQUEST, 'skip-widget-preview-transient-data' ) && rest_sanitize_boolean( LodashBasic::get( $_REQUEST, 'skip-widget-preview-transient-data' ) );
				if ( $data && ! $skip_widget_preview_transient_data ) {
					$post->post_content = $data;
				}
			}

			static::$pluginDataPost = $post;

			$pluginData = json_decode( static::$pluginDataPost->post_content, true );
		}

		return $pluginData;
	}

	public function saveChanges() {
		$pluginData = static::getPluginData();

		$currentWidgetData = $this->getSourceData();
		if ( empty( $currentWidgetData ) || empty( $pluginData ) ) {
			return;
		}
		LodashBasic::set( $pluginData, $this->settingPath, $currentWidgetData );

		static::updatePluginDataContent( $pluginData );

		//force this data to be recompiled
		$this->data = null;
	}

	/**
	 * @param $pluginData array
	 */
	public static function updatePluginDataContent( $plugin_data ) {
		$plugin_data_post = static::getPluginDataPost();
		if ( empty( $plugin_data_post ) || ( ! empty( $plugin_data_post ) && ! isset( $plugin_data_post->ID ) ) ) {
			return;
		}

		$plugin_data_content_json = json_encode( $plugin_data, JSON_UNESCAPED_UNICODE );

		$new_plugin_data_post = array(
			'ID'           => $plugin_data_post->ID,
			'post_content' => $plugin_data_content_json,
		);

		wp_update_post( $new_plugin_data_post );

		//update plugin data
		static::$pluginData = static::getDataFromDatabase();
	}

}
