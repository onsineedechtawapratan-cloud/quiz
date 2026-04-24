<?php

namespace SiteLeads\Features\Widgets;

use IlluminateAgnostic\Arr\Support\Arr;
use SiteLeads\Core\DataHelper;
use SiteLeads\Core\LodashBasic;
use SiteLeads\Features\FCPreviewWidget;
use SiteLeads\Features\Greetings\GreetingRenderer as GreetingsRenderer;
use SiteLeads\Features\Widgets\Channels\Channel;
use SiteLeads\Utils;
use function _\uniq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PREVIEW_MODE_ENABLED  = 2;
const PREVIEW_MODE_ERROR    = 1;
const PREVIEW_MODE_DISABLED = 0;

class FCWidget extends DataHelper {

	public $settingPath   = 'widgets';
	public $channels      = array();
	public $device        = 'desktop';
	public $isPreview     = false;
	public $isLivePreview = false;

	public function __construct( $settingPath, $device = 'desktop' ) {
		parent::__construct( $settingPath );

		$this->device = $device;

		$this->initWidgetChannels();
	}

	public function getId() {
		return $this->settingPath;
	}

	public function enqueue_google_font() {

		if ( $this->hasActiveAgent() ) {
			$prop_pairs = array(
				array(
					'header.fontSettings.family',
					'header.fontSettings.weight',
				),
				array(
					'container.fontSettings.family',
					'container.fontSettings.weight',
				),
				array(
					'footer.fontSettings.family',
					'footer.fontSettings.weight',
				),
			);

			$chatStyleComponent = array( 'styledComponent' => 'chat' );

			foreach ( $prop_pairs as $pair ) {
				$fontName   = $this->getStyle( $pair[0], null, $chatStyleComponent );
				$fontWeight = $this->getStyle( $pair[1], '400', $chatStyleComponent );

				if ( $fontName ) {
					FCWidgets::require_google_font( $fontName, $fontWeight );
				}
			}
		}

		// Loop through channels and enqueue used custom fonts on Contact Settings
		$this->enqueueFontsUsedByContacts();

		$this->enqueueDesktopChatViewFonts();
	}

	function enqueue_chatbot_script() {
		$previewMode     = $this->isPreviewMode();
		$livePreviewMode = $this->isLivePreviewMode();

		$isEnabled = $this->getProp( 'isEnabled' );
		if ( $previewMode !== PREVIEW_MODE_ENABLED && $livePreviewMode !== PREVIEW_MODE_ENABLED && ! $isEnabled ) {
			return;
		}

		if ( ! $this->hasActiveAgent() ) {
			return;
		}

		$aiAgent = $this->getProp( 'channels.aiAgent' );
		// aiAgent not enabled
		if ( isset( $aiAgent['enabled'] ) && $aiAgent['enabled'] !== true ) {
			return;
		}

		// chatbot not active
		if ( isset( $aiAgent['chatbotData']['status'] ) && $aiAgent['chatbotData']['status'] !== 'active' ) {
			return;
		}

		// no apiKey found
		if ( ! isset( $aiAgent['chatbotData']['chatbot_apiKey'] ) || empty( $aiAgent['chatbotData']['chatbot_apiKey'] ) ) {
			return;
		}

		$src = add_query_arg(
			array(
				'targetSelector' => rawurlencode( "[data-widget-id={$this->settingPath}] .siteleads-chatbot-container__wrapper" ),
				'is_preview'     => $previewMode === PREVIEW_MODE_ENABLED ? 1 : 0,
				'avatarName'     => rawurlencode( $this->getProp( 'channels.aiAgent.avatarName' ) ),
				'widgetName'     => rawurlencode( $this->getProp( 'name' ) ),
				'uid'            => FCWidgets::get_site_key(),
				'token'          => Utils::generateWidgetJWT(),
			),
			SITELEADS_DASHBOARD_ROOT_URL . '/api/chat/' . $aiAgent['chatbotData']['chatbot_apiKey'] . '/wps'
		);

		$script_settings = array();

		if ( $previewMode || $livePreviewMode ) {
			$id              = Arr::get( $aiAgent, 'chatbotData.id' );
			$script_settings = FCPreviewWidget::getAgentPreviewData( $id );
			$script_key      = $aiAgent['chatbotData']['chatbot_apiKey'];

			if ( ! empty( $script_settings ) ) {
				FCWidgets::require_script_config( $this->settingPath, "sl_agent_data_{$script_key}", $script_settings );
			}
		}

		FCWidgets::require_widget_script( $this->settingPath, $src );
	}

	public function getIsEnabled() {

		$previewMode        = $this->isPreviewMode();
		$livePreviewMode    = $this->isLivePreviewMode();
		$letIsWidgetPreview = false;

		if ( $previewMode === PREVIEW_MODE_ENABLED || $livePreviewMode === PREVIEW_MODE_ENABLED ) {
			$letIsWidgetPreview = true;
		}

		if ( ! $letIsWidgetPreview && ! $this->getProp( 'isEnabled' ) ) {
			return false;
		}
		if ( empty( $this->getProp( 'activeChannels', array() ) ) ) {
			return false;
		}

		return true;
	}

	public function isPreviewMode() {
		if ( \is_user_logged_in() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a preview request, we just check a boolean flag in the request to decide whether to use the transient data or not
			$preview = isset( $_GET['siteleads-preview'] ) ? filter_var( sanitize_text_field( wp_unslash( $_GET['siteleads-preview'] ) ), FILTER_VALIDATE_BOOLEAN ) : false;
			if ( $preview ) {
				$widgetId = Utils::getPreviewedWidgetId();

				if ( $widgetId === false ) {
					return PREVIEW_MODE_DISABLED;
				}
				if ( $widgetId === $this->settingPath ) {
					$this->isPreview = true;

					return PREVIEW_MODE_ENABLED;
				} else {
					return PREVIEW_MODE_ERROR;
				}
			}
		}

		return PREVIEW_MODE_DISABLED;
	}

	public function isLivePreviewMode() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a preview request, we just check a boolean flag in the request to decide whether to use the transient data or not
		$preview = isset( $_GET['siteleads-live-preview'] ) ? filter_var( sanitize_text_field( wp_unslash( $_GET['siteleads-live-preview'] ) ), FILTER_VALIDATE_BOOLEAN ) : false;
		if ( $preview ) {
			$widgetId = Utils::getLivePreviewedWidgetId();

			if ( $widgetId === false ) {
				return PREVIEW_MODE_DISABLED;
			}
			if ( $widgetId === $this->settingPath ) {
				$this->isLivePreview = true;

				return PREVIEW_MODE_ENABLED;
			} else {
				return PREVIEW_MODE_ERROR;
			}
		}

		return PREVIEW_MODE_DISABLED;
	}

	public function chatOpenedByDefault() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a preview request, we just check a boolean flag in the request to decide whether to use the transient data or not
		$chatOpened = isset( $_GET['chat-opened-by-default'] ) ? filter_var( sanitize_text_field( wp_unslash( $_GET['chat-opened-by-default'] ) ), FILTER_VALIDATE_BOOLEAN ) : true;

		return $chatOpened;
	}

	public function canRenderWidget( $payload = null, $previewing = false ) {
		$enabled = $previewing || $this->getProp( 'isEnabled', false );
		if ( ! $enabled ) {
			return false;
		}

		//always show widget in preview mode
		if ( $this->isPreview || $this->isLivePreview ) {
			return true;
		}

		$showOnDevices = $this->getProp( 'showOn', array() );
		$showOnDevices = in_array( $this->device, $showOnDevices ? $showOnDevices : array(), true );

		$showWidget = apply_filters( 'siteleads_can_render_widget', $showOnDevices, $this, $payload );

		return $showWidget;
	}


	public function printPlaceholder() {

		$shouldPrintPlaceholder = apply_filters( 'siteleads_can_print_placeholder', true, $this );

		if ( ! $this->isPreview && ! $this->isLivePreview && ! $shouldPrintPlaceholder ) {
			return;
		}

		printf( '<div style="display:none!important" data-siteleads-widget-placeholders="%s"></div>', esc_attr( $this->settingPath ) );
	}

	public function printWidget() {

		?>
		<div class="<?php echo esc_attr( $this->printWidgetClasses() ); ?>" <?php $this->printDataAttrs(); ?>>

			<?php $this->printGreeting(); ?>

			<?php $this->renderChannelsByView(); ?>

			<?php $this->printSiteLeadsButton(); ?>

			<?php $this->printChatbotContainer(); ?>

		</div>
		<?php

		$this->enqueue_google_font();
		$this->enqueue_chatbot_script();
	}

	public function printGreeting() {
		$greeting_data = $this->getProp(
			'greeting',
			array(
				'enabled' => false,
			)
		);

		if ( ! isset( $greeting_data ) || ! $greeting_data['enabled'] ) {
			return;
		}
		GreetingsRenderer::render( $greeting_data, $this );
	}



	public function printChatbotContainer() {

		if ( ! $this->hasActiveAgent() ) {
			return;
		}

		$aiAgent           = $this->getProp( 'channels.aiAgent' );
		$previewMode       = $this->isPreviewMode();
		$livePreviewMode   = $this->isLivePreviewMode();
		$hasAiAgent        = $this->hasActiveAgent();
		$chatOpenByDefault = $this->chatOpenedByDefault();
		if ( ! $hasAiAgent ) {
			return;
		}

		// aiAgent not enabled
		if ( $previewMode !== PREVIEW_MODE_ENABLED && $livePreviewMode !== PREVIEW_MODE_ENABLED && isset( $aiAgent['enabled'] ) && $aiAgent['enabled'] !== true ) {
			return;
		}

		$wrapperStyles = array_merge(
			$this->printChatThemePreset( true )
		);

		$wrapperStyles = Utils::convertStyleArrayToString( $wrapperStyles );

		$shadowLevel = max( 1, (int) $this->getStyle( 'boxShadow', null, array( 'styledComponent' => 'icon' ) ) );

		$chatbotClasses = array(
			'siteleads-chatbot-container',
			$previewMode === PREVIEW_MODE_ENABLED && $hasAiAgent && $chatOpenByDefault ? 'siteleads-chatbot-opened' : '',
			'sl-shadow-' . $shadowLevel,
		);

		?>
		<div style="<?php echo esc_attr( $this->printChatBotStyles() ); ?>"
			class='<?php echo esc_attr( implode( ' ', $chatbotClasses ) ); ?>'>
			<div class='siteleads-chatbot-container__wrapper' style="<?php echo esc_attr( $wrapperStyles ); ?>"></div>


			<?php $this->printChatChannels(); ?>

		</div>

		<?php
	}

	public function printTermsOfUseNotice() {
		//return; // Temporarily disabled

		$hasAiAgent = $this->hasActiveAgent();
		if ( ! $hasAiAgent ) {
			return;
		}

		$termsOfUseText = esc_html__( 'By chatting, you agree with ', 'siteleads' );
		$linkText       = esc_html__( 'AI terms of Use', 'siteleads' );
		$termsOfUseLink = esc_url( 'https://openai.com/policies/row-terms-of-use/' );
		?>
		<div class='siteleads-chatbot-container__terms-of-use'
			style="<?php echo esc_attr( $this->printChatThemePreset() ); ?>">
			<div class="siteleads-chatbot-container__terms-of-use__text">
				<?php echo wp_kses_post( $termsOfUseText ); ?>
				<a href='<?php echo esc_url( $termsOfUseLink ); ?>' target='_blank' rel='noopener noreferrer'>
					<?php echo esc_html( $linkText ); ?>
				</a>
			</div>
			<button class="siteleads-chatbot-container__terms-of-use__hide-notice">
				<span class="siteleads-chatbot-container__terms-of-use__hide-notice-icon"></span>
			</button>
		</div>
		<?php
	}

	public function printChatChannels() {
		$isTryingAgent             = $this->getProp( 'isTryingAgent' );
		$isTryingAgentWithChannels = $this->getProp( 'isTryingAgentWithChannels' );
		if ( $isTryingAgent && ! $isTryingAgentWithChannels ) {
			return;
		}

		$popupView = $this->getProp( 'popupView' );

		$isMobileSameAsDesktop = $this->device === 'mobile' && $popupView['mobile'] === 'same-as-desktop';
		$isDesktopChatView     = $popupView['desktop'] === 'chat';
		$isChatview            = $this->device === 'mobile' ? $isMobileSameAsDesktop && $isDesktopChatView : $isDesktopChatView;

		if ( ! $isChatview ) {
			return;
		}

		$channels = array_filter(
			$this->channels,
			function ( $channel ) {
				$showOn = $this->getProp( "channels.{$channel->name}.showOn", array() );

				if ( ! in_array( $this->device, $showOn ) ) {
					return false;
				}

				return $channel->name !== 'aiAgent';
			}
		);

		if ( count( $channels ) === 0 ) {
			return;
		}

		?>
		<div class='siteleads-chatbot-container__channels-wrapper'
			style="<?php echo esc_attr( $this->printChatFooterStyles() ); ?>; --sl-carousel-items: <?php echo esc_attr( min( count( $channels ), 3 ) ); ?>; ">
			<div class='siteleads-chatbot-container__channels'>
				<div class='siteleads-chatbot-container__channel --siteleads-agent-channel' data-channel='aiAgent'>
					<div class='siteleads-chatbot-container__channel-icon'>
						<?php Utils::printIconAsset( 'channels-icons/aiAgent.svg' ); ?>
					</div>
					<div class='siteleads-chatbot-container__channel-name'
						title="
						<?php
						echo esc_attr(
							$this->getProp( 'channels.aiAgent.textOnHover', __( 'Chat', 'siteleads' ) )
						);
						?>
						"
					>
						<?php
						echo esc_html( $this->getProp( 'channels.aiAgent.textOnHover', __( 'Chat', 'siteleads' ) ) );
						?>
					</div>
				</div>
				<div class='siteleads-chatbot-container__channels-carousel-wrapper'>
					<div class='siteleads-chatbot-container__channels-carousel'
						data-items="<?php echo count( $channels ); ?>"
						data-items-per-page="3" data-current="0">

						<?php
						foreach ( array_reverse( $channels ) as $channel ) {
							$this->printSingleChatChannel( $channel->name );
						}
						?>

					</div>
				</div>
			</div>
			<div class='siteleads-chatbot-container__nav-buttons'>
				<button data-direction="prev" disabled>
					<?php Utils::printIconAsset( 'chevron-left.svg' ); ?>
				</button>
				<button data-direction="next" <?php echo count( $channels ) > 3 ? '' : 'disabled'; ?>>
					<?php Utils::printIconAsset( 'chevron-left.svg' ); ?>
				</button>
			</div>
		</div>


		<?php
	}

	function printChatFooterStyles() {
		$chatStyleComponent = array( 'styledComponent' => 'chat' );

		$headerAvatarName = $this->getStyle( 'header.avatarName', null, $chatStyleComponent );
		$footerIconsColor = $this->getStyle( 'footer.iconsColor', null, $chatStyleComponent );

		$theme = array(
			'--sl-channels-icon-color'                 => $footerIconsColor, // . 'CC', // 80% opacity
			'--sl-footer-channels-text-and-icon-hover' => $headerAvatarName,
			'--sl-footer-channels-background-hover'    => $headerAvatarName,
			'--sl-nav-icon-color'                      => $footerIconsColor, // same as agent message text
		);

		return $this->convertStyleArrayToString( $theme );
	}

	public function printChatThemePreset( $returnAsArray = false ) {
		$chatStyleComponent = array( 'styledComponent' => 'chat' );

		// Avatar icon
		$avatar_url = $this->getProp( 'channels.aiAgent.avatar.url' );

		// Header
		$headerBackground     = $this->getStyle( 'header.background', null, $chatStyleComponent );
		$headerAvatarName     = $this->getStyle( 'header.avatarName', null, $chatStyleComponent );
		$headerIconBackground = $this->getStyle( 'header.iconBackground', null, $chatStyleComponent );
		$headerMinimizeIcon   = $this->getStyle( 'header.minimizeIcon', null, $chatStyleComponent );
		$headerFontSettings   = $this->getStyle( 'header.fontSettings', null, $chatStyleComponent );

		// Container
		$containerBackground             = $this->getStyle( 'container.background', null, $chatStyleComponent );
		$containerAgentMessageBackground = $this->getStyle( 'container.agentMessageBackground', null, $chatStyleComponent );
		$containerAgentMessageText       = $this->getStyle( 'container.agentMessageText', null, $chatStyleComponent );
		$containerUserMessageBackground  = $this->getStyle( 'container.userMessageBackground', null, $chatStyleComponent );
		$containerUserMessageText        = $this->getStyle( 'container.userMessageText', null, $chatStyleComponent );
		$containerFontSettings           = $this->getStyle( 'container.fontSettings', null, $chatStyleComponent );

		// Footer
		$footerFooterBackground = $this->getStyle( 'footer.background', null, $chatStyleComponent );
		// $footerInputBorder      = $this->getStyle( 'footer.inputBorder', null, $chatStyleComponent );
		$footerIconsColor   = $this->getStyle( 'footer.iconsColor', null, $chatStyleComponent );
		$footerFontSettings = $this->getStyle( 'footer.fontSettings', null, $chatStyleComponent );

		if ( empty( $avatar_url ) ) {
			$icon = Utils::getIconAsset( 'channels-icons/aiAgent.svg' );

			$icon = preg_replace( '/fill="[^"]*"/', 'fill="' . $headerBackground . '"', $icon );

			// add more space around the icon by manipulating the viewBox attribute of the svg
			$icon = preg_replace_callback(
				'/viewBox="([^"]*)"/',
				function ( $matches ) {

					if ( ! is_array( $matches ) || count( $matches ) < 2 ) {
						return $matches;
					}

					$viewBoxValues = explode( ' ', $matches[1] );
					if ( count( $viewBoxValues ) === 4 ) {
							$offset = 6;
							$x      = floatval( $viewBoxValues[0] ) - $offset;
							$y      = floatval( $viewBoxValues[1] ) - $offset;
							$width  = floatval( $viewBoxValues[2] ) + 2 * $offset;
							$height = floatval( $viewBoxValues[3] ) + 2 * $offset;

							return 'viewBox="' . implode( ' ', array( $x, $y, $width, $height ) ) . '"';
					}

					return $matches[0];
				},
				$icon
			);

			$icon_base64 = base64_encode( $icon );
			$avatar_url  = 'data:image/svg+xml;base64,' . $icon_base64;
		}

		$theme = array(
			// Avatar icon
			'--sl-header-avatar-image'                 => "url({$avatar_url})",

			// Header Variables
			'--sl-header-background'                   => $headerBackground,
			'--sl-header-avatar-name'                  => $headerAvatarName,
			'--sl-header-icon-background'              => $headerIconBackground,
			'--sl-header-minimize-icon'                => $headerMinimizeIcon,
			'--sl-header-font-family'                  => isset( $headerFontSettings['family'] ) ? $headerFontSettings['family'] : 'inherit',
			'--sl-header-font-size'                    => isset( $headerFontSettings['size'] ) ? $headerFontSettings['size'] . 'px' : 'inherit',
			'--sl-header-font-weight'                  => isset( $headerFontSettings['weight'] ) ? $headerFontSettings['weight'] : 'inherit',
			'--sl-header-font-color'                   => isset( $headerFontSettings['color'] ) ? $headerFontSettings['color'] : 'inherit',

			// Container Variables
			'--sl-container-background'                => $containerBackground,
			'--sl-container-agent-message-background'  => $containerAgentMessageBackground,
			'--sl-container-agent-message-text'        => $containerAgentMessageText,
			'--sl-container-user-message-background'   => $containerUserMessageBackground,
			'--sl-container-user-message-text'         => $containerUserMessageText,
			'--sl-container-font-family'               => isset( $containerFontSettings['family'] ) ? $containerFontSettings['family'] : 'inherit',
			'--sl-container-font-size'                 => isset( $containerFontSettings['size'] ) ? $containerFontSettings['size'] . 'px' : 'inherit',
			'--sl-container-font-weight'               => isset( $containerFontSettings['weight'] ) ? $containerFontSettings['weight'] : 'inherit',
			'--sl-container-font-color'                => isset( $containerFontSettings['color'] ) ? $containerFontSettings['color'] : 'inherit',

			// Footer Variables
			'--sl-footer-background'                   => $footerFooterBackground,
			'--sl-footer-icons-color'                  => $footerIconsColor,
			// '--sl-footer-input-border'                 => $footerInputBorder,
			'--sl-footer-font-family'                  => isset( $footerFontSettings['family'] ) ? $footerFontSettings['family'] : 'inherit',
			'--sl-footer-font-size'                    => isset( $footerFontSettings['size'] ) ? $footerFontSettings['size'] . 'px' : 'inherit',
			'--sl-footer-font-weight'                  => isset( $footerFontSettings['weight'] ) ? $footerFontSettings['weight'] : 'inherit',
			'--sl-footer-font-color'                   => isset( $footerFontSettings['color'] ) ? $footerFontSettings['color'] : '#737373',

			// Extra
			'--sl-footer-channels-text-and-icon'       => $containerAgentMessageText . 'CC', // 80% opacity
			'--sl-footer-channels-text-and-icon-hover' => $headerAvatarName, // 80% opacity
			'--sl-footer-channels-background-hover'    => $headerAvatarName, // 10% opacity
			'--sl-nav-icon-color'                      => $containerAgentMessageText, // same as agent message text
		);

		if ( $returnAsArray ) {
			return $theme;
		}

		return $this->convertStyleArrayToString( $theme );
	}

	public function printChatBotStyles() {
		$iconSize = $this->getProp( 'channelsBar.iconSize', 'medium' );

		$iconSizeMap = array(
			'small'  => '34px',
			'medium' => '44px',
			'large'  => '54px',
		);
		$fabSize     = isset( $iconSizeMap[ $iconSize ] ) ? $iconSizeMap[ $iconSize ] : '44px';

		$styles = array(
			'--sl-view-bar-base-height' => 'calc( ' . $fabSize . ' + 16px )',
		);

		$agentFontName = $this->getProp( 'channels.aiAgent.fontFamily' );
		if ( $agentFontName ) {
			$styles['font-family'] = $agentFontName;
		}

		$theme_presets = $this->printChatThemePreset( true );

		$styles = array_merge( $styles, $theme_presets );

		return $this->convertStyleArrayToString( $styles );
	}

	public function convertStyleArrayToString( $arr ) {
		$vars = array();

		foreach ( $arr as $key => $value ) {
			$vars[] = "$key: $value";
		}

		return implode( ';', $vars );
	}


	public function printSingleChatChannel( $slug ) {
		$basePath   = "channels.{$slug}.children.0";
		$svg        = Utils::getIconAsset( "channels-icons/{$slug}.svg" );
		$attributes = $this->getLinkAttributes( $slug, $basePath, array(), true );
		$showOn     = $this->getProp( "channels.{$slug}.showOn", array() );
		$label      = $this->getProp( "channels.{$slug}.textOnHover", '' );

		if ( ! in_array( $this->device, $showOn ) ) {
			return false;
		}

		?>
		<a class='siteleads-chatbot-container__channel' 
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The attributes are generated internally and do not contain user input, so it is safe to output without escaping
		echo $attributes ? $attributes : '';
		?>
		>
			<div class='siteleads-chatbot-container__channel-icon'>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The SVG content is generated internally and does not contain user input, so it is safe to output without escaping
				echo $svg;
				?>
			</div>
			<div class='siteleads-chatbot-container__channel-name' title="<?php echo esc_attr( $label ); ?>">
				<?php echo esc_html( $label ); ?>
			</div>
		</a>
		<?php
	}

	public function printWidgetClasses() {
		$classes = array( 'siteleads-widget' );

		$position = $this->getStyle( 'position', null, array( 'styledComponent' => 'icon' ) );
		if ( $position ) {
			$classes[] = "--siteleads-root-alignment-{$position}";
		}

		$previewMode = $this->isPreviewMode();
		if ( $previewMode === PREVIEW_MODE_ENABLED ) {
			$classes[] = '--preview-mode';
		}

		$defaultState = $this->getProp( 'defaultState' );
		if ( $defaultState === 'expanded' ) {
			$classes[] = '--expanded-list';
		}

		$popupView = $this->getProp( 'popupView' );
		switch ( $popupView[ $this->device ] ) {
			case 'chat':
				$classes[] = '--horizontal --sl-chat-view';
				break;
			case 'simple':
				$classes[] = '--sl-simple-view';
				break;
			case 'channels-bar':
				$classes[] = '--sl-channels-bar-view';
				break;
			case 'cta-bar':
				$classes[] = '--sl-cta-bar-view';
				break;
			case 'same-as-desktop':
				switch ( $popupView['desktop'] ) {
					case 'chat':
						$classes[] = '--horizontal --sl-chat-view';
						break;
					case 'simple':
						$classes[] = '--sl-simple-view';
						break;
				}
				break;
		}

		$size = $this->getProp( 'size', 'medium' );
		if ( $size ) {
			$classes[] = "--{$size}-size";
		}

		// Is trying Agent
		$isTryingAgent             = $this->getProp( 'isTryingAgent', false );
		$isTryingAgentWithChannels = $this->getProp( 'isTryingAgentWithChannels', false );
		if ( $isTryingAgent && ! $isTryingAgentWithChannels ) {
			$classes[] = '--is-trying-agent --agent-only';
		}

		// Animation classes
		$attentionEffect = $this->getProp( 'attentionEffect' );
		// Attention effect only on desktop, mobile device animations are not wanted
		if ( $attentionEffect && $attentionEffect !== 'none' && $this->device === 'desktop' ) {
			$classes[] = "siteleads-animation__{$attentionEffect}";
		}

		// Pending messages
		$pendingMessages = $this->getProp( 'pendingMessages', false );
		if ( $pendingMessages ) {
			$classes[] = '--has-pending-messages';
		}

		return join( ' ', $classes );
	}

	public function getWidgetId() {
		return $this->getProp( 'channels.aiAgent.chatbotData.id', null );
	}

	public function hasActiveAgent() {

		if ( ! $this->getWidgetId() ) {
			return;
		}

		return isset( $this->channels['aiAgent'] );
	}

	public function printDataAttrs() {
		$dataAttrs = array(
			sprintf( 'data-widget-id="%s"', esc_attr( $this->settingPath ) ),
			'data-init-state="true"',
		);

		$hideAfterFirstClick = esc_attr( $this->getProp( 'cta.showOptions' ) ) === 'hide-after-first-click';
		$initialDelay        = $this->getProp( 'trigger.initialDelay' );
		$onScroll            = $this->getProp( 'trigger.onScroll', array() );
		$popupView           = $this->getProp( 'popupView' );
		$hasAgent            = $this->hasActiveAgent() ? 'true' : 'false';
		$customTitle         = $this->getProp( 'customPageTitle.enabled', false ) ?
			$this->getProp( 'customPageTitle.title', '' ) : '';

		$viewValue = $popupView[ $this->device ];
		if ( $this->device === 'mobile' && $viewValue === 'same-as-desktop' ) {
			$viewValue = $popupView['desktop'];
		}
		$dataAttrs[] = sprintf( 'data-view="%s"', esc_attr( $viewValue ) );
		$dataAttrs[] = sprintf( 'data-device="%s"', esc_attr( $this->device ) );
		$dataAttrs[] = sprintf( 'data-has-agent="%s"', esc_attr( $hasAgent ) );

		if ( $customTitle && $this->device === 'desktop' ) {
			$dataAttrs[] = sprintf( 'data-custom-title="%s"', esc_attr( $customTitle ) );
		}

		if ( ! $this->isPreview ) {
			if ( $hideAfterFirstClick ) {
				$dataAttrs[] = sprintf( 'data-hide-after-first-click="%s"', esc_attr( $hideAfterFirstClick ) );
			}
			if ( isset( $initialDelay['enabled'] ) && $initialDelay['enabled'] && isset( $initialDelay['seconds'] ) ) {
				$dataAttrs[] = sprintf( 'data-initial-delay="%d"', esc_attr( $initialDelay['seconds'] ) );
			}

			if ( isset( $onScroll['enabled'] ) && $onScroll['enabled'] && isset( $onScroll['percent'] ) && intVal( $onScroll['percent'] ) > 0 ) {
				// $dataAttrs[] = "data-display-on-scroll={$onScroll['percent']}";
				$dataAttrs[] = sprintf( 'data-display-on-scroll="%d"', esc_attr( $onScroll['percent'] ) );
			}
		}

		$styles = array(
			'--sl-full-fab-size'    => sprintf(
				'calc(var(--sl-base-fab-size) + %dpx * 2 )',
				$this->getStyle( 'borderWidth', 0, array( 'styledComponent' => 'icon' ) )
			),
			'--sl-fab-border-width' => $this->getStyle( 'borderWidth', 0, array( 'styledComponent' => 'icon' ) ) . 'px',
		);

		$dataAttrs[] = sprintf( 'style="%s"', esc_attr( $this->convertStyleArrayToString( $styles ) ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- dataAttrs is computed already with escaped values
		echo implode( ' ', $dataAttrs );
	}

	public function getInitialDelay() {
		$initialDelay = $this->getProp( 'trigger.initialDelay' );
		if ( isset( $initialDelay['enabled'] ) && $initialDelay['enabled'] && isset( $initialDelay['seconds'] ) ) {
			return intval( $initialDelay['seconds'] );
		}

		return 0;
	}

	public function initWidgetChannels() {
		$activeChannels = $this->getProp( 'activeChannels', array() );
		$channelsOrder  = array_reverse( $this->getProp( 'channelsOrder', array() ) );
		$channels       = array(); // array of Channel class

		foreach ( $channelsOrder as $channelSlug ) {
			if ( ! in_array( $channelSlug, $activeChannels ) ) {
				continue;
			}

			$channel = new Channel(
				$this,
				$this->settingPath,
				$channelSlug,
				$this->settingPath,
				$this->device,
				$this->isPreview
			);

			$canRenderChannel = $channel->canRenderChannel();

			if ( $canRenderChannel ) {
				$channels[ $channelSlug ] = $channel;
			}
		}

		$this->channels = $channels;
	}

	public function renderChannelsByView() {

		$popupView = $this->getProp( 'popupView' );

		switch ( $popupView[ $this->device ] ) {
			//desktop cases
			case 'simple':
				$this->renderSimpleView();
				break;
			case 'chat':
				$isTryingAgent             = $this->getProp( 'isTryingAgent' );
				$isTryingAgentWithChannels = $this->getProp( 'isTryingAgentWithChannels' );
				//if only one channel exists no matter what channel is we should show it as simple view
				if (
					( $isTryingAgent && ! $isTryingAgentWithChannels )
					|| ( count( $this->channels ) === 1 )
				) {
					// Show simple view if only one channel exists or only AI assistant is enabled
					$this->renderSimpleView();
				} else {
					$this->renderChatView();
				}

				break;
			//mobile cases
			case 'same-as-desktop':
				$this->renderSameAsDesktopView();
				break;
			case 'channels-bar':
				// $this->renderChannelsBarView();
				do_action( 'siteleads_render_channels_bar_view', $this );
				break;
			case 'cta-bar':
				// $this->renderCtaBarView();
				do_action( 'siteleads_render_cta_bar_view', $this );
				break;
		}
	}

	public function renderSimpleView() {

		$style[] = 'grid-template-columns: 1fr';
		$classes = 'siteleads-widget__channels';

		if ( count( $this->channels ) === 1 ) {
			$classes .= ' siteleads-init-animation';

			$first_channel = array_values( $this->channels )[0];

			$boxShadow = $this->getStyle( 'boxShadow', '0', array( 'styledComponent' => 'icon' ) );
			$style[]   = sprintf( '--desktop-widget-color: %s', $first_channel->getChannelProp( 'backgroundColor' ) );
			$style[]   = sprintf( 'box-shadow: var(--sl-shadow-%s);border-radius:100%%', esc_attr( $boxShadow ) );

		}

		$style = implode( ';', $style );

		?>
		<div class='<?php echo esc_attr( $classes ); ?>' style="<?php echo esc_attr( $style ); ?>">
			<?php

			foreach ( $this->channels as $channel ) {
				$channel->render();
			}
			?>
		</div>

		<?php
		foreach ( $this->channels as $channel ) {
			$channel->renderContactLauncher();
		}
		?>

		<?php
	}

	public function renderChatView() {

		$channelsOrder = $this->getProp( 'channelsOrder', array() ); // no need for array_reverse
		$bgColor       = $this->getStyle( 'background', null, array( 'styledComponent' => 'icon' ) );
		$popupView     = $this->getProp( 'popupView' );

		$isMobile              = $this->device === 'mobile';
		$isMobileSameAsDesktop = $isMobile && $popupView['mobile'] === 'same-as-desktop';
		$isDesktopChatView     = $popupView['desktop'] === 'chat';

		$isChatview          = $isMobile ? $isMobileSameAsDesktop && $isDesktopChatView : $isDesktopChatView;
		$isChatViewWithAgent = $isChatview && $this->hasActiveAgent();

		$chatHeaderMessage  = $this->getProp( 'chatView.headerMessage' );
		$chatWelcomeMessage = $this->getProp( 'chatView.welcomeMessage' );

		$headerStyle  = Utils::fontSettingsToStyle( isset( $chatHeaderMessage['fontSettings'] ) ? $chatHeaderMessage['fontSettings'] : array() );
		$welcomeStyle = Utils::fontSettingsToStyle( isset( $chatWelcomeMessage['fontSettings'] ) ? $chatWelcomeMessage['fontSettings'] : array() );
		$headerColor  = Arr::get( $chatHeaderMessage, 'fontSettings.color', '#000' );

		if ( $isMobile ) {
			// on mobile render only max 5 channels in chat view
			$channelsOrder = array_slice( $channelsOrder, 0, 5 );
		}

		$boxShadow   = $this->getStyle( 'boxShadow', '0', array( 'styledComponent' => 'icon' ) );
		$shadowLevel = max( 1, (int) $boxShadow );

		// Do not render channel launcher if AI assistant is enabled and chat view is selected
		if ( ! $isChatViewWithAgent ) {
			?>
			<div class="siteleads-channel-launcher sl-shadow-<?php echo esc_attr( $shadowLevel ); ?>">
				<div class="siteleads-channel-launcher__content">
					<div class="siteleads-channel-launcher__content__header"
						style="--sl-header-bg: <?php echo esc_attr( $bgColor ); ?>">
						<span style="<?php echo esc_attr( $headerStyle ); ?>">
							<?php echo esc_html( isset( $chatHeaderMessage['value'] ) ? $chatHeaderMessage['value'] : '' ); ?>
						</span>
						<div class="siteleads-channel-launcher__content__header__close"
							role="button"
							aria-label="<?php echo esc_attr__( 'Close', 'siteleads' ); ?>"
							tabindex="0"
							style="--sl-icon-color: <?php echo esc_attr( $headerColor ); ?>"
						>
							<?php Utils::printIconAsset( 'close.svg' ); ?>
						</div>
					</div>
					<div class="siteleads-channel-launcher__content__body">
						<div class="siteleads-channel-launcher__channels-carousel" data-current="0"
							data-items-per-page="5">
							<div class="siteleads-channel-launcher__description">
								<span style="<?php echo esc_attr( $welcomeStyle ); ?>">
									<?php echo esc_html( isset( $chatWelcomeMessage['value'] ) ? $chatWelcomeMessage['value'] : '' ); ?>
								</span>
							</div>

							<div class="siteleads-channel-launcher__channels-wrapper">
								<div class="siteleads-channel-launcher__channels">
									<?php
									foreach ( array_reverse( $this->channels ) as $channel ) {
										$channel->render();
									}
									?>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<?php
		}

		foreach ( $this->channels as $channel ) {
			$channel->renderContactLauncher();
		}
	}

	public function renderSameAsDesktopView() {
		$popupView   = $this->getProp( 'popupView' );
		$desktopView = $popupView['desktop'];

		switch ( $desktopView ) {
			case 'simple':
				$this->renderSimpleView();
				break;
			case 'chat':
				$isTryingAgent             = $this->getProp( 'isTryingAgent' );
				$isTryingAgentWithChannels = $this->getProp( 'isTryingAgentWithChannels' );

				//if only one channel exists no matter what channel is we should show it as simple view
				if (
					( $isTryingAgent && ! $isTryingAgentWithChannels )
					|| ( count( $this->channels ) === 1 )
				) {
					// Show simple view if only one channel exists or only AI assistant is enabled
					$this->renderSimpleView();
				} else {
					$this->renderChatView();
				}

				break;
		}
	}

	public function renderChannelsBarView() {
		// Deprecated, use action hook 'siteleads_render_channels_bar_view' instead.
	}


	public function renderCtaBarView() {
		// Deprecated, use action hook 'siteleads_render_cta_bar_view' instead.
	}

	public function shouldShowChannel( $channelName ) {
		if ( $this->isPreview ) {
			return true;
		}

		if ( ! $channelName ) {
			return false;
		}

		$showOn = $this->getProp( "channels.{$channelName}.showOn", array( 'desktop', 'mobile' ) );
		if ( ! count( $showOn ) || ! in_array( $this->device, $showOn ) ) {
			return false;
		}

		return true;
	}




	// public function shouldDontShowOnPage() {
	//  $rules = $this->getProp( 'showOnPages.rules.dontShowOn', array() );

	//  if ( count( $rules ) === 0 ) {
	//      return false;
	//  }

	//  global $post;
	//  $page_link = rtrim( get_permalink( $post->ID ), '/' );

	//  $ruleName  = array_keys( $rules )[0];
	//  $ruleValue = $rules[ $ruleName ];

	//  switch ( $ruleName ) {
	//      case 'homepage':
	//          if ( is_front_page() ) {
	//              return true;
	//          }
	//          break;
	//      case 'linkContain':
	//          if ( strpos( $page_link, $ruleValue ) !== false ) {
	//              return true;
	//          }
	//          break;
	//      case 'specificLink':
	//          $full_link = home_url( $ruleValue );
	//          if ( $page_link === $ruleValue || $page_link === $full_link ) {
	//              return true;
	//          }
	//          break;
	//      case 'linksStartWith':
	//          if ( strpos( $page_link, $ruleValue ) === 0 ) {
	//              return true;
	//          }
	//          break;
	//      case 'linksEndWith':
	//          if ( strpos( $page_link, $ruleValue ) === strlen( $page_link ) - strlen( $ruleValue ) ) {
	//              return true;

	//          }
	//          break;
	//      case 'wp_pages':
	//          if ( is_page() ) {
	//              return true;
	//          }
	//          break;
	//      case 'wp_posts':
	//          if ( is_single() ) {
	//              return true;
	//          }
	//          break;
	//      case 'wp_categories':
	//          if ( is_category() ) {
	//              return true;
	//          }
	//          break;
	//      case 'wp_tags':
	//          if ( is_tag() ) {
	//              return true;
	//          }
	//          break;
	//  }

	//  return false;
	// }

	// TODO: implement this function
	public function canRenderChannel( $slug ) {

		switch ( $slug ) {
			case 'whatsapp':
				return true;
		}

		return true;
	}

	public function getLinkAttributes( $name, $contactPath, $data = array(), $as_string = false ) {
		$default_attributes = array_merge(
			array(
				'rel'          => 'nofollow noopener',
				'data-channel' => $name,
			),
			$data
		);
		$attributes         = array();
		$children           = $this->getProp( "channels.{$name}.children", array() );

		if ( count( $children ) < 2 ) {
			$value = '';
			switch ( $name ) {
				case 'whatsapp':
					$value = $this->getProp( "{$contactPath}.number" );
					break;
				case 'phone':
					$value = $this->getProp( "{$contactPath}.number" );
					break;
				case 'email':
					$value = $this->getProp( "{$contactPath}.to" );
					break;
				case 'messenger':
					$value = $this->getProp( "{$contactPath}.username" );
					break;

				case 'instagramDm':
					$value = $this->getProp( "{$contactPath}.username" );
					break;
				case 'instagramPage':
					$value = $this->getProp( "{$contactPath}.page" );
					break;
				case 'wechat':
					$value = $this->getProp( "{$contactPath}.qr.url" );
					break;
				case 'snapchat':
					$value = $this->getProp( "{$contactPath}.userid" );
					break;
				case 'discord':
					$value = $this->getProp( "{$contactPath}.invitationCode" );
					break;
				case 'telegram':
					$value = $this->getProp( "{$contactPath}.username" );
					break;
				case 'sms':
					$value = $this->getProp( "{$contactPath}.number" );
					break;
				case 'tiktok':
					$value = $this->getProp( "{$contactPath}.username" );
					break;
				case 'x':
					$value = $this->getProp( "{$contactPath}.username" );
					break;
				case 'maps':
					$value = $this->getProp( "{$contactPath}.location" );
					break;
			}

			$attributes = Channel::buildChannelLinkAttributes( $name, $value );
		}

		$attributes = array_merge( $default_attributes, $attributes );

		if ( $as_string ) {
			$attrs = array();

			foreach ( $attributes as $key => $value ) {

				if ( $value === 'javascript:void(0);' ) {
					$attrs[] = $key . '="javascript:void(0);"';
					continue;
				}

				$attrs[] = $key . '="' . esc_attr( $value ) . '"';
			}

			return implode( ' ', $attrs );
		}

		return $attributes;
	}


	public function printSiteLeadsButton() {
		if ( count( $this->channels ) < 2 ) {
			return;
		}
		$typography   = $this->getProp( 'typography' );
		$defaultState = $this->getProp( 'defaultState' );
		if ( $defaultState === 'expanded' ) {
			return;
		}

		$bgColor      = $this->getStyle(
			'background',
			'blue',
			array(
				'styledComponent' => 'icon',
			)
		);
		$color        = $this->getStyle(
			'color',
			'blue',
			array(
				'styledComponent' => 'icon',
			)
		);
		$borderRadius = '100%';

		$ctaTextColor = $this->getProp( 'cta.textColor' );
		$ctaBgColor   = $this->getProp( 'cta.backgroundColor' );

		$labelClasses = array( 'fc-button-label' );
		$ctaEnabled   = $this->getProp( 'cta.enabled' );
		$textFormat   = $this->getProp( 'cta.text.format' );
		if ( $ctaEnabled ) {
			if ( $textFormat ) {
				foreach ( $textFormat as $key => $value ) {
					$labelClasses[] = "siteleads-{$value}-text";
				}
			}

			$shape = $this->getProp( 'cta.shape', null );
			if ( $shape === 'rounded' ) {
				$labelClasses[] = 'siteleads-rounded-shape';
			}
		}

		// Box Shadow
		$boxShadow = $this->getStyle( 'boxShadow', '0', array( 'styledComponent' => 'icon' ) );
		// Border width
		$borderWidth = $this->getStyle( 'borderWidth', 0, array( 'styledComponent' => 'icon' ) );
		// Border Color
		$borderColor = $this->getStyle( 'borderColor', 0, array( 'styledComponent' => 'icon' ) );

		$desktopWidgetColor = $bgColor;

		if ( count( $this->channels ) > 1 ) {
			?>
			<button class="siteleads-button siteleads-init-animation" href="javascript:void(0)"
					style="
						--desktop-widget-color: <?php echo esc_attr( $desktopWidgetColor ); ?>;
						box-shadow: <?php printf( 'var(--sl-shadow-%s)', esc_attr( $boxShadow ) ); ?>;
						">
				<span class="fc-button-icon fc-close-icon" style="
					color: <?php echo esc_attr( $bgColor ); ?>;
					background-color: <?php echo esc_attr( $bgColor ); ?>;
					border-radius: <?php echo esc_attr( $borderRadius ); ?>;
					border-width: <?php echo esc_attr( $borderWidth ); ?>px;
					border-color: <?php echo esc_attr( $borderColor ); ?>;
					--sl-icon-color: <?php echo esc_attr( $color ); ?>;
					">
					<?php Utils::printIconAsset( 'close.svg' ); ?>
				</span>
				<span class="fc-button-icon fc-open-icon" style="<?php echo esc_attr( $this->printSiteleadsButtonStyles() ); ?>">
					<?php $this->printWidgetSvgIcon(); ?>
				</span>
			</button>
			<?php
		}
		?>
		<?php
		if ( $ctaEnabled ) {
			?>
			<span class="<?php echo esc_attr( implode( ' ', $labelClasses ) ); ?>" style="
				color: <?php echo esc_attr( $ctaTextColor ); ?>;
				background-color: <?php echo esc_attr( $ctaBgColor ); ?>;
				border-color: <?php echo esc_attr( $ctaTextColor ); ?>;
				font-family: <?php echo esc_attr( $typography ? $typography : 'inherit' ); ?>
				">
				<?php echo esc_html( $this->getProp( 'cta.text.value' ) ); ?>
			</span>
			<?php
		}
		?>
		<?php
	}

	public function printSiteleadsButtonStyles( $isAgentButton = false, $returnAsArray = false ) {
		$popupView                 = $this->getProp( 'popupView' );
		$isTryingAgent             = $this->getProp( 'isTryingAgent', false );
		$isTryingAgentWithChannels = $this->getProp( 'isTryingAgentWithChannels', false );

		$bgColor      = $this->getStyle(
			'background',
			'red',
			array(
				'styledComponent' => 'icon',
			)
		);
		$color        = $this->getStyle(
			'color',
			'red',
			array(
				'styledComponent' => 'icon',
			)
		);
		$type         = $this->getStyle( 'type', null, array( 'styledComponent' => 'icon' ) );
		$borderRadius = '100%';
		// Border width
		$borderWidth = $this->getStyle( 'borderWidth', 0, array( 'styledComponent' => 'icon' ) );
		// Border Color
		$borderColor = $this->getStyle( 'borderColor', 0, array( 'styledComponent' => 'icon' ) );

		$styles = array(
			'border-radius'    => $borderRadius,
			'border-width'     => $borderWidth . 'px',
			'border-color'     => $borderColor,
			'background-color' => $color,
			'aspect-ratio'     => '1 / 1',
		);

		if ( ( ! $isAgentButton && $popupView['desktop'] === 'simple' ) || $popupView['desktop'] === 'chat' || ( $isTryingAgent && ! $isTryingAgentWithChannels ) ) {
			$styles['--sl-icon-color']  = $color;
			$styles['background-color'] = $bgColor;
		} elseif ( $isAgentButton ) {
			$styles['--sl-icon-color']  = $this->getProp( 'channels.aiAgent.backgroundColor' );
			$styles['background-color'] = $this->getProp( 'channels.aiAgent.backgroundColor' );
		}

		if ( $returnAsArray ) {
			return $styles;
		}

		return $this->convertStyleArrayToString( $styles );
	}

	public function printWidgetSvgIcon() {
		$type = $this->getStyle( 'type', null, array( 'styledComponent' => 'icon' ) );

		if ( $type === 'custom' ) {
			$customUrl = $this->getProp( 'customIconUrl', false );
			if ( $customUrl ) {
				echo '<img style="object-fit:cover;"  src="' . esc_url( $customUrl ) . '" alt="' . esc_attr( __( 'Widget Icon', 'siteleads' ) ) . '" width="100%" height="100%" />';
			}

			return;
		}

		$svg = Utils::getIconAsset( 'widget-icons/' . $type . '.svg' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $svg is a trusted value coming from our assets
		printf( '<span class="siteleads-widget-svg-wrapper">%s</span>', $svg );
	}

	public static function createFromTemplate( $params = array() ) {
		$content  = file_get_contents( Utils::getFilePath( 'defaults/default-data.json' ) );
		$content  = json_decode( $content, true );
		$name     = LodashBasic::get( $params, 'name', __( 'Sample Widget', 'siteleads' ) );
		$template = LodashBasic::get( $content, 'widgetTemplate' );
		if ( empty( $template ) ) {
			return;
		}
		LodashBasic::set( $template, 'props.name', $name );
		$newId   = 'widget-kubio';
		$changes = array(
			$newId => $template,
		);

		$currentData = static::getDataFromDatabase();
		$ids         = LodashBasic::get( $currentData, 'widgets.props.ids', array() );
		$ids[]       = $newId;
		$ids         = uniq( $ids );
		$newData     = LodashBasic::mergeSkipSeqArray( $currentData, $changes );

		LodashBasic::set( $newData, 'widgets.props.ids', $ids );
		static::updatePluginDataContent( $newData );

		return array_merge(
			$template,
			array(
				'id' => $newId,
			)
		);
	}

	public static function findById( $id ) {
		$currentData = static::getPLuginData();
		if ( ! array_key_exists( $id, $currentData ) ) {
			return null;
		}

		return new FCWidget( $id );
	}

	public function updateParams( $params ) {
		$phoneNr    = LodashBasic::get( $params, 'phone.phoneNr' );
		$whatsappNr = LodashBasic::get( $params, 'whatsapp.phoneNr' );
		$hasChanges = false;
		if ( ! empty( $phoneNr ) ) {
			$this->setProp( 'channels.phone.number', $phoneNr );
			$hasChanges = true;
		}
		if ( ! empty( $whatsappNr ) ) {
			$this->setProp( 'channels.whatsapp.number', $whatsappNr );
			$hasChanges = true;
		}
		if ( $hasChanges ) {
			$this->saveChanges();
		}
	}

	public function enqueueFontsUsedByContacts() {
		foreach ( $this->channels as $channel ) {
			$children = $channel->getChannelProp( 'children', array() );
			if ( count( $children ) < 2 || $channel->name === 'aiAgent' ) {
				continue;
			}

			$setting = $channel->getChannelProp( 'contactSettings.headline.fontSettings' );

			// Headline message font settings
			$family = isset( $setting['family'] ) ? $setting['family'] : null;
			$weight = isset( $setting['weight'] ) ? $setting['weight'] : null;
			if ( $family && $weight ) {
				FCWidgets::require_google_font( $family, $weight );
			}

			// Welcome Text message font settings
			$setting = $channel->getChannelProp( 'contactSettings.welcomeText.fontSettings' );
			$family  = isset( $setting['family'] ) ? $setting['family'] : null;
			$weight  = isset( $setting['weight'] ) ? $setting['weight'] : null;
			if ( $family && $weight ) {
				FCWidgets::require_google_font( $family, $weight );
			}
		}
	}

	public function enqueueDesktopChatViewFonts() {
		$popupView = $this->getProp( 'popupView.desktop' );

		if ( $this->device !== 'desktop' || $popupView !== 'chat' ) {
			return;
		}

		// Header message font settings
		$family = $this->getProp( 'chatView.headerMessage.fontSettings.family' );
		$weight = $this->getProp( 'chatView.headerMessage.fontSettings.weight' );
		if ( $family ) {
			FCWidgets::require_google_font( $family, $weight );
		}

		// Welcome Text message font settings
		$family = $this->getProp( 'chatView.welcomeMessage.fontSettings.family' );
		$weight = $this->getProp( 'chatView.welcomeMessage.fontSettings.weight' );
		if ( $family ) {
			FCWidgets::require_google_font( $family, $weight );
		}
	}
}
