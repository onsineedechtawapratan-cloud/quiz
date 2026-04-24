<?php

namespace SiteLeads\Admin;

use SiteLeads\Constants;
use SiteLeads\Core\AssetsRegistry;
use SiteLeads\Core\Singleton;
use SiteLeads\Features\Widgets\FCWidgets;
use SiteLeads\Features\Widgets\FCWidgetsManager;
use SiteLeads\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	use Singleton;

	protected function __construct() {
		$this->bootstrap();
	}

	public function bootstrap() {
		add_action( 'init', array( $this, 'registerPostType' ), 9 );
		add_action( 'init', array( $this, 'maybeRegisterWebsite' ), 9 );

		add_action( 'admin_enqueue_scripts', array( $this, 'loadGeneralAdminStyles' ) );

		add_action( 'admin_menu', array( $this, 'addMenuPage' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'loadAdminScripts' ) );

		add_action( 'admin_init', array( $this, 'checkContentExistence' ) );
		add_filter( 'admin_body_class', array( $this, 'addAdminBodyClass' ), 10, 1 );

		add_filter( 'body_class', array( $this, 'addCustomizerPreviewClass' ) );

		add_filter( 'plugin_row_meta', array( $this, 'addBuildNumberToInPluginsList' ), 10, 4 );

		// add exit survey on plugin deactivation
		add_filter( 'network_admin_plugin_action_links', array( $this, 'addExitSurveyLinkOnDeactivation' ), 10, 2 );
		add_filter( 'plugin_action_links', array( $this, 'addExitSurveyLinkOnDeactivation' ), 10, 2 );
		add_action(
			'admin_footer',
			array( $this, 'addExitSurveyModal' )
		);
		add_action( 'admin_head', array( $this, 'enqueueExitSurveyModalAssets' ) );

		add_action( 'wp_ajax_check_wc_status', array( $this, 'check_wc_status' ) );
		add_action( 'wp_ajax_nopriv_check_wc_status', array( $this, 'check_wc_status' ) );

		add_action( 'admin_footer', array( $this, 'loadDialogContainer' ) );
	}

	public function maybeRegisterWebsite() {
		if ( ! is_admin() ) {
			return;
		}

		$apiKey = self::getApiKey();

		if ( ! $apiKey ) {
			return;
		}

		$site_url       = get_site_url();
		$option_key     = sprintf( 'sl_reg_%s', md5( $site_url ) );
		$registered_url = get_option( $option_key, null );
		if ( $site_url === $registered_url ) {
			return;
		}

		$endpoint = SITELEADS_DASHBOARD_ROOT_URL . '/api/register-website';

		$response = wp_remote_post(
			$endpoint,
			array(
				'body'    => json_encode(
					array(
						'apiKey'  => $apiKey,
						'siteUrl' => $site_url,
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code !== 200 ) {
			return false;
		}

		update_option( $option_key, $site_url );
	}

	public function check_wc_status() {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			wp_send_json_error( __( 'Invalid request.', 'siteleads' ) );

			return;
		}

		$is_active = class_exists( 'WooCommerce' );
		wp_send_json_success(
			array(
				'active' => $is_active,
			)
		);

		wp_die();
	}

	public function addCustomizerPreviewClass( $classes ) {
		if ( is_customize_preview() ) {
			$classes[] = 'siteleads-is-customizer-preview';
		}
		return $classes;
	}


	public function checkContentExistence() {
		if ( ! Utils::isSiteLeadsAdminPage() ) {
			return;
		}

		$post_type = Constants::$siteLeadsDataPostType;

		$instance_id = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);
		if ( empty( $instance_id ) && current_user_can( 'edit_posts' ) ) {

			$content = file_get_contents( Utils::getFilePath( 'defaults/default-data.json' ) );
			//create a default instance
			wp_insert_post(
				array(
					'post_type'    => $post_type,
					'post_status'  => 'publish',
					'post_title'   => __( 'SiteLeads Data', 'siteleads' ),
					'post_name'    => $post_type,
					'post_author'  => get_current_user_id(),
					'post_content' => $content,
				)
			);
		}
	}

	public function addAdminBodyClass( $classes ) {

		if ( ! Utils::isSiteLeadsAdminPage() ) {
			return $classes;
		}

		return $classes . ' siteleads-wp-admin-page';
	}

	private static function loadGreetingTemplates() {
		$templates = array();
		$types     = array( 'simple', 'narrow', 'vertical', 'horizontal', 'circle' );
		foreach ( $types as $type ) {
			$path = SITELEADS_ROOT_DIR . '/lib/Features/Greetings/definitions/' . $type . '.php';
			if ( file_exists( $path ) ) {
				$type_templates = require_once $path;
				foreach ( $type_templates as $template ) {
					$templates[] = array_merge(
						array(
							'type' => $type,
						),
						$template
					);
				}
			}
		}

		$templates          = apply_filters( 'siteleads_greeting_templates', $templates );
		$template_overrides = array(
			'entranceDelay' => 1,
			'dismissDelay'  => 15,
			'dismiss'       => 'on-widget-click',
		);

		foreach ( $templates as &$template ) {
			if ( ! isset( $template['preview_image'] ) ) {
				$template['preview_image'] = Utils::getUrl(
					sprintf(
						'assets/greetings/%s/%s.png',
						$template['type'],
						$template['template']
					)
				);
			}

			if ( ! isset( $template['_template'] ) ) {
				$template['_template'] = null;
			}
			$template = array_merge( $template, $template_overrides );
		}

		return $templates;
	}

	public static function getApiKey() {
		$ai_key = get_option( 'siteleads_ai_api_key', null );
		$ai_key = apply_filters( 'siteleads_get_ai_key', $ai_key );
		return $ai_key;
	}

	public static function getApiKeyIdentifier() {
		return get_option( 'siteleads_ai_key_identifier', '' );
	}



	public static function getBackendData() {
		$ai_key = self::getApiKey();

		$channelsIcons = array();

		foreach ( Constants::$channels as $channel ) {
			$channelsIcons[ $channel ] = Utils::getIconAsset( "channels-icons/{$channel}.svg" );
		}

		$settings = array(
			'base_url'                => site_url(),
			'admin_url'               => admin_url(),
			'ajax_url'                => admin_url( 'admin-ajax.php' ),
			'plugin_url'              => SITELEADS_ROOT_URL,
			'admin_plugins_url'       => admin_url( 'plugins.php' ),
			'bpaNonce'                => wp_create_nonce( 'bpa_wp_nonce' ),
			'events_nonce'            => wp_create_nonce( 'events_nonce' ),
			'showDebug'               => defined( '\SITELEADS_DEBUG' ) && \SITELEADS_DEBUG,
			'siteLeadsDataPostType'   => Constants::$siteLeadsDataPostType,
			'siteLeadsChatbotRootUrl' => SITELEADS_DASHBOARD_ROOT_URL,
			'siteLeadsAIKey'          => $ai_key,
			'timezone'                => wp_timezone()->getName(),
			'upgrade_url'             => Utils::getWebsiteURL( 'upgrade' ),
			'greetings_templates'     => static::loadGreetingTemplates(),
			'channelsIcons'           => $channelsIcons,
			'channelTypes'            => Constants::$channels,
			'defaultWidgetTemplate'   => FCWidgetsManager::getDefaultWidgetTemplate(),
		);

		$widgets_data = array();
		foreach ( FCWidgets::getWidgetList() as $widget ) {
			$widgets_data[ $widget['id'] ] = $widget;
		}

		$settings['widgets'] = $widgets_data;

		return apply_filters( 'siteleads_admin_backend_data', $settings );
	}

	public function loadBackendData() {
		$settings = self::getBackendData();

		wp_add_inline_script(
			AssetsRegistry::getAssetHandle( 'admin-pages' ),
			sprintf(
				'window.siteLeadsUtils = %s;',
				wp_json_encode( $settings )
			),
			'before'
		);
	}

	public function loadAdminScripts() {

		if ( ! Utils::isSiteLeadsAdminPage() ) {
			return;
		}

		AssetsRegistry::enqueueAssetGroup( 'admin-pages' );
		wp_add_inline_script(
			AssetsRegistry::getAssetHandle( 'admin-pages' ),
			'window.siteLeadsInit();'
		);

		wp_enqueue_media();

		$this->loadBackendData();

		$post_type   = Constants::$siteLeadsDataPostType;
		$instance_id = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);
		$instance_id = ! empty( $instance_id ) ? $instance_id[0] : 0;

		$preload_data = array(
			'/wp/v2/types?context=view',
			'/wp/v2/types?context=edit',
			"/wp/v2/types/{$post_type}?context=edit",
		);

		if ( $instance_id ) {
			$preload_data[] = "/wp/v2/{$post_type}/{$instance_id}";
		}

		$context = new \WP_Block_Editor_Context();

		//Added in the 'wp-api-fetch' inline script
		if ( function_exists( 'block_editor_rest_api_preload' ) ) {
			\block_editor_rest_api_preload( $preload_data, $context );
		}
	}

	public function loadGeneralAdminStyles() {
		//load style we want on all admin pages
		AssetsRegistry::enqueueAssetGroup( 'admin/pages/all' );
	}

	public function registerPostType() {
		if ( post_type_exists( Constants::$siteLeadsDataPostType ) ) {
			return;
		}

		$args = array(
			'label'               => __( 'SiteLeads settings', 'siteleads' ),
			'public'              => false,
			'show_ui'             => false,
			'show_in_rest'        => true,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'rewrite'             => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'rest_base'           => Constants::$siteLeadsDataPostType,
			'capabilities'        => array(
				'read'                   => 'edit_theme_options',
				'create_posts'           => 'edit_theme_options',
				'edit_posts'             => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
				'edit_others_posts'      => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
			),
			'map_meta_cap'        => true,
		);

		register_post_type( Constants::$siteLeadsDataPostType, $args );
	}

	public function addMenuPage() {
		\add_menu_page(
			__( 'SiteLeads', 'siteleads' ),
			__( 'SiteLeads', 'siteleads' ),
			'manage_options',
			'siteleads',
			array( $this, 'loadAdminPage' ),
			// 'dashicons-cart',
			SITELEADS_ROOT_URL . '/assets/images/admin-menu-icon.svg',
			21
		);

		wp_add_inline_style(
			'admin-menu',
			'#adminmenu a.toplevel_page_siteleads img { max-width: 20px; display: block;margin: 0; padding: 0; }' .
				'#adminmenu  a.toplevel_page_siteleads .dashicons-before { display: flex; align-items: center;justify-content: center;}' .
				'#adminmenu  .toplevel_page_siteleads a[href*="siteleads-upgrade"] { color: #dab6fc !important; }' .
				'#adminmenu  .toplevel_page_siteleads a.siteleads-upgrade-link { color: #dab6fc !important; }' .
				'#adminmenu  .siteleads-upgrade-link-text{display flex;align-items:center;display: flex;gap: 4px;}' .
				'#adminmenu  .siteleads-upgrade-link-text svg {width: 1.25em;height:1.25em;}'
		);

		add_submenu_page(
			'siteleads',
			__( 'Dashboard', 'siteleads' ),
			__( 'Dashboard', 'siteleads' ),
			'manage_options',
			'siteleads',
			array( $this, 'loadAdminPage' )
		);

		add_submenu_page(
			'siteleads',
			__( 'Analytics', 'siteleads' ),
			__( 'Analytics', 'siteleads' ),
			'manage_options',
			'siteleads-analytics',
			array( $this, 'loadAnalyticsPage' )
		);

		add_submenu_page(
			'siteleads',
			__( 'Messages', 'siteleads' ),
			__( 'Messages', 'siteleads' ),
			'manage_options',
			'siteleads-messages',
			array( $this, 'loadMessagesPage' )
		);

		add_submenu_page(
			'siteleads',
			__( 'Leads', 'siteleads' ),
			__( 'Leads', 'siteleads' ),
			'manage_options',
			'siteleads-leads',
			array( $this, 'loadManageLeadsPage' )
		);

		add_submenu_page(
			'siteleads',
			__( 'My Account', 'siteleads' ),
			__( 'My Account', 'siteleads' ),
			'manage_options',
			'siteleads-my-account',
			array( $this, 'loadMyAccountPage' )
		);

		add_submenu_page(
			'siteleads',
			__( 'Upgrade', 'siteleads' ),
			sprintf(
				'<span class="siteleads-upgrade-link-text"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-crown-icon lucide-crown"><path d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.734H5.81a1 1 0 0 1-.957-.734L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294z"/><path d="M5 21h14"/></svg><span>%s</span></span>',
				esc_html__( 'Upgrade to PRO', 'siteleads' )
			),
			'manage_options',
			'siteleads-upgrade',
			''
		);

		do_action( 'siteleads_admin_menu', $this );

		add_action(
			'in_admin_header',
			function () {
				?>
				<script>
					(function(props){
						const upgradeLink = document.querySelector('.toplevel_page_siteleads a[href*="siteleads-upgrade"]');
						if(upgradeLink){
							upgradeLink.setAttribute('href', props.upgradeURL);
							upgradeLink.setAttribute('target', '_blank');
							upgradeLink.classList.add('siteleads-upgrade-link');
						}
					})(
					<?php
						echo wp_json_encode(
							array(
								'upgradeURL' => Utils::getWebsiteURL( 'upgrade' ),
							)
						);
					?>
					);
				</script>
				<?php
			}
		);
	}

	public function loadAdminPage() {

		?>

		<div id="siteleads-page" class="--siteleads-is-loading" data-page="dashboard">
			<div class="siteleads-container">
				<div class="siteleads-loader"></div>
			</div>
		</div>

		<?php
	}

	public function loadDialogContainer() {
		if ( ! Utils::isSiteLeadsAdminPage() ) {
			return;
		}

		?>
		<div class="siteleads-dialog-container"></div>
		<?php
	}

	public function loadAnalyticsPage() {
		?>

		<div id="siteleads-page" class="--siteleads-is-loading" data-page="analytics">
			<div class="siteleads-container">
				<div class="siteleads-loader"></div>
			</div>
		</div>

		<?php
	}

	public function loadMessagesPage() {
		?>

		<div id="siteleads-page" class="--siteleads-is-loading" data-page="messages">
			<div class="siteleads-container">
				<div class="siteleads-loader"></div>
			</div>
		</div>

		<?php
	}

	public function loadManageLeadsPage() {
		?>

		<div id="siteleads-page" class="--siteleads-is-loading" data-page="leads">
			<div class="siteleads-container">
				<div class="siteleads-loader"></div>
			</div>
		</div>

		<?php
	}

	public function loadMyAccountPage() {
		?>

		<div id="siteleads-page" class="--siteleads-is-loading" data-page="my-account">
			<div class="siteleads-container">
				<div class="siteleads-loader"></div>
			</div>
		</div>

		<?php
	}

	public function addBuildNumberToInPluginsList( $plugin_meta, $plugin_file ) {
		$basename = plugin_basename( SITELEADS_ENTRY_FILE );

		if ( $plugin_file === $basename ) {
			$plugin_meta[0] =
				"{$plugin_meta[0]} (build: " . SITELEADS_BUILD_NUMBER . ')';
		}

		return $plugin_meta;
	}

	public function addExitSurveyLinkOnDeactivation( $actions, $plugin_file ) {
		$basename = plugin_basename( SITELEADS_ENTRY_FILE );

		if ( $plugin_file === $basename && isset( $actions['deactivate'] ) ) {
			// add data-siteleads-exit-survey attribute to the deactivate link
			$actions['deactivate'] = str_replace(
				'<a ',
				'<a data-siteleads-exit-survey="true" ',
				$actions['deactivate']
			);
		}

		return $actions;
	}

	public function addExitSurveyModal() {

		$current_screen = get_current_screen();
		if ( ! $current_screen || ( $current_screen->base !== 'plugins' && $current_screen->base !== 'plugins-network' ) ) {
			return;
		}

		$images_base_url = Utils::getUrl( 'static/admin-pages/exit-survey/images' );
		$feature_list    = array(
			array(
				'icon' => 'plugin.svg',
				'text' => __( 'Works with any theme', 'siteleads' ),
			),
			array(
				'icon' => 'messages.svg',
				'text' => __( 'Connects you with visitors through WhatsApp, Email, Phone & more', 'siteleads' ),
			),
			array(
				'icon' => 'people.svg',
				'text' => __( 'Collects leads 24/7 with your own AI Assistant', 'siteleads' ),
			),
		);

		?>
		<dialog id="siteleads-exit-survey-modal" class="siteleads-exit-survey-modal">
			<div class="siteleads-exit-survey-modal-content">
				<div class="siteleads-exit-survey-container">
					<div class="siteleads-exit-survey-layout">
						<section class="siteleads-exit-survey-left"
							style="background-image: url('<?php echo esc_url( "{$images_base_url}/bg.svg" ); ?>')"
						>
							<h2>
								<img src="<?php echo esc_url( "{$images_base_url}/logo.svg" ); ?>" alt="<?php echo esc_attr__( 'Siteleads', 'siteleads' ); ?>" class="siteleads-exit-survey-logo">	
							<?php echo esc_html__( 'Did you know that SiteLeads:', 'siteleads' ); ?></h2>
							<ul>
								<?php foreach ( $feature_list as $feature ) : ?>
									<li>
										<img src="<?php echo esc_url( "{$images_base_url}/{$feature['icon']}" ); ?>" alt="" aria-hidden="true">
										<span><?php echo esc_html( $feature['text'] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
							<button type="button" class="siteleads-exit-survey-retry"><?php echo esc_html__( "I'll give it another shot", 'siteleads' ); ?></button>
						</section>

						<section class="siteleads-exit-survey-right">
						<div class="siteleads-exit-survey-texts">
							<h2><?php echo esc_html__( 'Quick feedback', 'siteleads' ); ?></h2>
							<p><?php echo esc_html__( 'If you still want to uninstall, mind telling us why?', 'siteleads' ); ?></p>
						</div>
						<p class="siteleads-exit-survey-warning" role="alert" aria-live="polite" hidden>
								<?php echo esc_html__( 'Please select an option before submitting.', 'siteleads' ); ?>
						</p>
						<iframe
							id="siteleads-exit-survey-iframe"
							src="<?php echo esc_url( 'https://siteleads.ai/survey/?v=3&theme=' . rawurlencode( get_stylesheet()) ); ?>"
								class="siteleads-exit-survey-iframe"
								frameborder="0"
							></iframe>
							<div class="siteleads-exit-survey-actions">
								<button type="button" class="button-link siteleads-exit-survey-skip"><?php echo esc_html__( 'Skip & Deactivate', 'siteleads' ); ?></button>
								<button type="button" class="button button-primary siteleads-exit-survey-submit"><?php echo esc_html__( 'Submit & Deactivate', 'siteleads' ); ?></button>
							</div>
							
						</section>
					</div>
				</div>
			</div>
		</dialog>
		<?php
	}

	public function enqueueExitSurveyModalAssets() {
		$current_screen = get_current_screen();
		if ( ! $current_screen || ( $current_screen->base !== 'plugins' && $current_screen->base !== 'plugins-network' ) ) {
			return;
		}

		wp_enqueue_style(
			'siteleads-exit-survey-modal',
			Utils::getUrl( 'static/admin-pages/exit-survey/style.css' ),
			array(),
			SITELEADS_BUILD_NUMBER
		);

		wp_enqueue_script(
			'siteleads-exit-survey-modal',
			Utils::getUrl( 'static/admin-pages/exit-survey/script.js' ),
			array(),
			SITELEADS_BUILD_NUMBER,
			true
		);
	}
}
