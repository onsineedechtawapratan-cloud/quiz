<?php

namespace SiteLeads\Features\Widgets;

use SiteLeads\Core\AssetsRegistry;
use SiteLeads\Core\DataHelper;
use SiteLeads\Core\LodashBasic;
use SiteLeads\Core\Singleton;
use SiteLeads\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FCWidgets extends DataHelper {
	use Singleton;

	// TODO: --siteleads-root-alignment needs to be moved to individual widget, in case of multiple widgets

	protected $settingPath = 'widgets';


	protected static $fonts_to_enqueue   = array(
		'Geist' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900' ),
	);
	protected static $fonts_enqueue_hook = false;


	protected static $widgets_scripts_to_enqueue = array();

	/**
	 * @var FCWidget[]
	 */
	public $widgets = null;

	protected function __construct() {
		parent::__construct();
	}

	public function addHooks() {
		add_filter(
			'template_include',
			function ( $template ) {
				if ( Utils::getPreviewedWidgetId() ) {
					add_filter( 'show_admin_bar', '__return_false' );
					wp_dequeue_script( 'admin-bar' );
					wp_dequeue_style( 'admin-bar' );

					return Utils::getFilePath( 'assets/preview-template.php' );
				}

				return $template;
			},
			99
		);

		add_action( 'wp_enqueue_scripts', array( $this, 'addFrontendAssets' ) );

		add_action(
			'wp_footer',
			function () {
				?>
				<div class="siteleads-root --siteleads-root-alignment-left">
					<div class="siteleads-root-inner">
						<div class="siteleads-fc-widgets">
							<?php $this->printWidgets( 'left' ); ?>
						</div>
					</div>
				</div>
				<div class="siteleads-root --siteleads-root-alignment-right">
					<div class="siteleads-root-inner">
						<div class="siteleads-fc-widgets">
							<?php $this->printWidgets( 'right' ); ?>
						</div>
					</div>
				</div>
				<?php
			},
			0
		);

		add_action(
			'admin_bar_menu',
			function ( $wp_admin_bar ) {

				$live_preview_widget_id = Utils::getLivePreviewedWidgetId();
				if ( ! $live_preview_widget_id ) {
					return;
				}

				$widget = new FCWidget( $live_preview_widget_id );

				$wp_admin_bar->add_node(
					array(
						'id'    => 'siteleads-live-preview-widget',
						'title' => sprintf(
							'<img src="%s"><span>Previewing: %s</span>',
							esc_url( SITELEADS_ROOT_URL . '/assets/images/admin-menu-icon.svg' ),
							$widget->getProp( 'name' )
						),
						'href'  => add_query_arg(
							array(
								'page'       => 'siteleads',
								'inner_page' => 'channel-selection',
								'widgetId'   => $live_preview_widget_id,
								'action'     => 'edit',
							),
							admin_url( 'admin.php' )
						),
						'meta'  => array(
							'class' => 'siteleads-live-preview-widget',
							'title' => sprintf(
								'Live Preview: %s',
								$widget->getProp( 'name' )
							),
						),
					)
				);
			},
			100
		);

		add_action(
			'wp_enqueue_scripts',
			function () {
				if ( ! Utils::getLivePreviewedWidgetId() ) {
					return;
				}

				wp_add_inline_style(
					'admin-bar',
					'' .
					'#wp-admin-bar-siteleads-live-preview-widget {background-color: #2758e5 !important; max-width: 200px;  }' .
					'#wp-admin-bar-siteleads-live-preview-widget * {display:block; box-sizing:border-box}' .
					'#wp-admin-bar-siteleads-live-preview-widget:hover {background-color: #1641c0 !important;}' .
					'#wp-admin-bar-siteleads-live-preview-widget a {display: flex !important;  align-items: center;  flex-wrap: nowrap;  gap: 8px;  color: #fff;}' .
					'#wp-admin-bar-siteleads-live-preview-widget a:focus,' .
					'#wp-admin-bar-siteleads-live-preview-widget a:hover {color: #fff !important;}' .
					'#wp-admin-bar-siteleads-live-preview-widget a img {max-height: 1em;  display: block;}' .
					'#wp-admin-bar-siteleads-live-preview-widget a span {white-space: nowrap; overflow: hidden;  text-overflow: ellipsis; min-width:100%; width: fit-content; padding-right: 14px;}'
				);
			}
		);
	}

	public function buildGoogleFontsURL() {
		$href       = 'https://fonts.googleapis.com/css';
		$query_args = array(
			'display' => 'swap',
		);

		$family_query_parts = array();
		foreach ( self::$fonts_to_enqueue as $family => $variants ) {

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

	private function is_consecutive_range( $weights ) {
		if ( count( $weights ) < 2 ) {
			return false;
		}

		$min            = min( $weights );
		$max            = max( $weights );
		$expected_count = ( ( $max - $min ) / 100 ) + 1;

		return count( $weights ) === $expected_count;
	}

	public function getWidgetInPreviewMode() {
		return Utils::getPreviewedWidgetId();
	}

	public function getWidgetInLivePreviewMode() {
		return Utils::getLivePreviewedWidgetId();
	}

	public function getWidgets( $ids = null, $device = 'desktop' ) {

		$widgetIds           = $this->getProp( 'ids' );
		$previewWidgetId     = $this->getWidgetInPreviewMode();
		$livePreviewWidgetId = $this->getWidgetInLivePreviewMode();

		$previewWidgets = array_filter(
			array( $previewWidgetId, $livePreviewWidgetId )
		);

		if ( empty( $previewWidgets ) && ! is_array( $widgetIds ) ) {
			return array();
		}

		if ( ! empty( $previewWidgets ) ) {
			$widgetIds = $previewWidgets;
		}

		$widgetIds = $ids ? array_intersect( $ids, $widgetIds ) : $widgetIds;

		$widgets = array_map(
			function ( $id ) use ( $device ) {
				return new FCWidget( $id, $device );
			},
			$widgetIds
		);

		$this->widgets = $widgets;

		return $this->widgets;
	}

	public function getWidgetsByPosition( $position ) {
		$widgetIds           = $this->getProp( 'ids' );
		$previewWidgetId     = $this->getWidgetInPreviewMode();
		$livePreviewWidgetId = $this->getWidgetInLivePreviewMode();
		if ( ! $previewWidgetId && ! $livePreviewWidgetId && ! is_array( $widgetIds ) ) {
			return array();
		}

		if ( $previewWidgetId ) {
			$widgetIds = array( $previewWidgetId );
		}
		if ( $livePreviewWidgetId ) {
			$widgetIds = array( $livePreviewWidgetId );
		}

		$widgets = array();
		foreach ( $widgetIds as $id ) {
			$widget = new FCWidget( $id );

			$widget_position = $widget->getStyle( 'position', null, array( 'styledComponent' => 'icon' ) );
			if ( $widget_position === $position ) {
				$widgets[] = $widget;
			}
		}

		$this->widgets = $widgets;

		return $this->widgets;
	}


	public function printWidgets( $position = 'right' ) {

		/** @var FCWidget[] $widgets */
		$widgets = $this->getWidgetsByPosition( $position );
		if ( ! is_array( $widgets ) || count( $widgets ) === 0 ) {
			return;
		}
		foreach ( $widgets as $widget ) {
			if ( $widget->getIsEnabled() ) {
				$widget->printPlaceholder();
			}
		}
	}


	public function addFrontendAssets() {
		$name = 'frontend/widgets';

		AssetsRegistry::enqueueAssetGroup( $name );

		wp_localize_script(
			AssetsRegistry::getAssetHandle( $name ),
			'siteLeadsData',
			array_merge(
				array(
					'ajax_url'    => admin_url( 'admin-ajax.php' ),
					'referrer'    => esc_url_raw( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' ),
					'server_time' => time(),
					'site_url'    => get_site_url(),
				)
			)
		);

		$loader_script = file_get_contents( SITELEADS_ROOT_DIR . 'assets/loader.js' );

		wp_add_inline_script(
			AssetsRegistry::getAssetHandle( $name ),
			$loader_script,
			'after'
		);
	}

	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function load() {
		return self::getInstance();
	}


	public function getWidgetIds() {
		return $this->getProp( 'ids', array() );
	}

	public function getAgentIds() {
		$widgets = $this->getWidgetList();
		$ids     = array();
		foreach ( $widgets as  $widgetData ) {
			$agentId = LodashBasic::get( $widgetData, 'props.channels.aiAgent.chatbotData.id', null );
			if ( $agentId ) {
				$ids[] = $agentId;
			}
		}
		return $ids;
	}

	static function getWidgetList() {
		$pluginData          = DataHelper::getDataFromDatabase();
		$ids                 = LodashBasic::get( $pluginData, 'widgets.props.ids', array() );
		$previewWidgetId     = Utils::getPreviewedWidgetId();
		$livePreviewWidgetId = Utils::getLivePreviewedWidgetId();
		if ( $previewWidgetId ) {
			$ids[] = $previewWidgetId;
		}
		if ( $livePreviewWidgetId ) {
			$ids[] = $livePreviewWidgetId;
		}

		$widgets = array_map(
			function ( $id ) use ( $pluginData ) {
				$data = LodashBasic::get( $pluginData, $id, array() );

				return array_merge(
					$data,
					array(
						'id' => $id,
					)
				);
			},
			$ids
		);

		return $widgets;
	}

	public static function require_google_font( $family, $weight, $style = 'normal' ) {

		if ( ! isset( self::$fonts_to_enqueue[ $family ] ) ) {
			self::$fonts_to_enqueue[ $family ] = array( '400' );
		}

		$font_variant = strval( $weight );
		if ( $style === 'italic' ) {
			$font_variant .= 'i';
		}
		if ( in_array( $font_variant, self::$fonts_to_enqueue[ $family ], true ) ) {
			return;
		}

		self::$fonts_to_enqueue[ $family ][] = $font_variant;
	}

	public static function require_widget_script( $handle, $src ) {
		if ( ! isset( self::$widgets_scripts_to_enqueue[ $handle ] ) ) {
			self::$widgets_scripts_to_enqueue[ $handle ] = array();
		}

		if ( is_string( $src ) && in_array( $src, self::$widgets_scripts_to_enqueue[ $handle ], true ) ) {
			return;
		}

		self::$widgets_scripts_to_enqueue[ $handle ][] = $src;
	}

	public static function require_script_config( $handle, $prop, $settings ) {
		$config = array(
			'prop'     => $prop,
			'settings' => $settings,
		);

		return static::require_widget_script( $handle, $config );
	}


	public static function get_widget_scripts() {
		return self::$widgets_scripts_to_enqueue;
	}

	public static function get_site_key() {
		global $siteleads_site_key;

		if ( isset( $siteleads_site_key ) && $siteleads_site_key ) {
			return $siteleads_site_key;
		}

		$site_key = get_option( 'siteleads_site_key', '' );
		if ( ! $site_key ) {
			$site_key = wp_generate_uuid4();
			update_option( 'siteleads_site_key', $site_key );
		}

		$siteleads_site_key = $site_key;

		return $siteleads_site_key;
	}
}
