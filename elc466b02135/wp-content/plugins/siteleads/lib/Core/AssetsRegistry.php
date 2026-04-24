<?php

namespace SiteLeads\Core;

use SiteLeads\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AssetsRegistry {
	use Singleton;

	private static $possible_css_entries = array( 'index.css', 'style-index.css', 'style.css' );


	protected function __construct() {
		add_action( 'init', array( $this, 'registerAssets' ) );

	}


	public function registerAssets() {
		$assets_manifest = require_once SITELEADS_ROOT_DIR . '/build/assets-manifest.php';

		$assets = array_map( array( $this, 'getAssetData' ), $assets_manifest );

		$this->registerScripts( $assets );
		$this->registerStytles( $assets );
	}


	public static function getAssetHandle( $name ) {
		return 'siteleads-' . str_replace( '/', '_', $name );
	}

	/**
	 * Enqueue the script and style for a specified entry.
	 *
	 * @param  string $name The name of the assets group (webpack entry).
	 * @return void
	 */
	public static function enqueueAssetGroup( $name ) {
		$name = static::getAssetHandle( $name );
		wp_enqueue_style( $name );
		wp_enqueue_script( $name, '', array( 'jquery' ), SITELEADS_VERSION, true );
	}

	/**
	 * Enqueue only the style for a specified entry.
	 *
	 * @param  string $name The name of the assets group (webpack entry).
	 * @return void
	 */
	public static function enqueueStyle( $name ) {
		$name = static::getAssetHandle( $name );
		wp_enqueue_style( $name );
	}

	/**
	 * Wrap within enqueue hook, and enqueue the script and style for a specified entry.
	 * This function detects where the enqueue is called ( admin or frontend ) and uses the correct hook
	 *
	 * @param  string $name The name of the assets group (webpack entry).
	 * @return void
	 */
	public static function enqueueAssetHooked( $name ) {
		$name = static::getAssetHandle( $name );
		$hook = is_admin() ? 'admin_enqueue_scripts' : 'wp_enqueue_scripts';

		add_action(
			$hook,
			function () use ( $name ) {
				wp_enqueue_style( $name );
				wp_enqueue_script( $name );
			}
		);
	}

	private function getAssetData( $asset ) {
		$rel = "build/{$asset}";

		$data = array(
			'name'   => $asset,
			'handle' => static::getAssetHandle( $asset ),
			'css'    => null,
			'js'     => null,
		);

		if ( file_exists( SITELEADS_ROOT_DIR . '/' . $rel . '/index.js' ) ) {
			$path    = SITELEADS_ROOT_DIR . '/' . $rel . '/index.js';
			$url     = SITELEADS_ROOT_URL . '/' . $rel . '/index.js';
			$version = '';
			$deps    = array();

			if ( file_exists( SITELEADS_ROOT_DIR . '/' . $rel . '/index.asset.php' ) ) {
				$asset_data = require_once SITELEADS_ROOT_DIR . '/' . $rel . '/index.asset.php';
				$version    = $asset_data['version'];
				$deps       = $asset_data['dependencies'];
			}

			$data['js'] = array(
				'path'         => $path,
				'url'          => $url,
				'version'      => $version,
				'dependencies' => $deps,
			);
		}

		foreach ( AssetsRegistry::$possible_css_entries as $possible_entry ) {
			if ( file_exists( SITELEADS_ROOT_DIR . '/' . $rel . '/' . $possible_entry ) ) {
				$path = SITELEADS_ROOT_DIR . '/' . $rel . '/' . $possible_entry;
				$url  = SITELEADS_ROOT_URL . '/' . $rel . '/' . $possible_entry;

				$data['css'] = array(
					'path'    => $path,
					'url'     => $url,
					'version' => filemtime( $path ),
				);

				break;
			}
		}

		return $data;
	}

	private function registerScripts( $items ) {
		$key = 'js';
		foreach ( $items as $item ) {
			if ( empty( $item[ $key ] ) ) {
				continue;
			}

			$dependencies = $item[ $key ]['dependencies'];

			switch ( $item['name'] ) {

				case 'admin-pages':
					$dependencies = array_merge(
						$dependencies,
						array( 'wp-core-data', 'wp-components' )
					);
					break;

				case 'frontend/widgets':
					$extra_dependencies = array();

					if ( Utils::getLivePreviewedWidgetId() ) {
						$extra_dependencies[] = 'wp-url';
					}

					$dependencies = array_merge(
						$dependencies,
						$extra_dependencies
					);
					break;

			}

			\wp_register_script( $item['handle'], $item[ $key ]['url'], $dependencies, $item[ $key ]['version'], true );

		}

	}

	private function registerStytles( $items ) {

		$key = 'css';
		foreach ( $items as $item ) {
			$dependencies = array();

			if ( empty( $item[ $key ] ) ) {
				continue;
			}

			switch ( $item['name'] ) {
				case 'admin-pages':
					$dependencies = array_merge(
						$dependencies,
						array( 'wp-components' )
					);
					break;
			}

			\wp_register_style( $item['handle'], $item[ $key ]['url'], $dependencies, $item[ $key ]['version'] );
		}
	}


}
