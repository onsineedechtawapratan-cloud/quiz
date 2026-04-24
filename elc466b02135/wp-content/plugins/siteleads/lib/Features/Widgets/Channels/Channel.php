<?php

namespace SiteLeads\Features\Widgets\Channels;

use SiteLeads\Core\DataHelper;
use SiteLeads\Utils;
use IlluminateAgnostic\Arr\Support\Arr;
use SiteLeads\Features\Widgets\FCWidgetsManager;

class Channel extends DataHelper {

	use ChannelHelperTrait;


	/**
	 * Undocumented variable
	 *
	 * @var FCWidget
	 */
	public $parentWidget = null;
	public $widgetId;
	public $name;
	public $device;
	public $isPreview;

	public function __construct( $parentWidget, $widgetId, $name, $settingPath, $device = 'desktop', $isPreview = false ) {
		parent::__construct( $settingPath );
		$this->parentWidget = $parentWidget;
		$this->widgetId     = $widgetId;
		$this->name         = $name;
		$this->device       = $device;
		$this->isPreview    = $isPreview;
	}

	public function getChannelName() {
		return $this->name;
	}

	public function render() {
		if ( ! $this->canRenderChannel() ) {
			return false;
		}

		$children = $this->getChannelProp( 'children' );

		if ( $children && count( $children ) === 1 ) {
			return $this->renderChannel( 0 );
		} else {
			$this->renderChannelWithLauncher();
		}

		return false;
	}

	public function canRenderChannel() {
		// the children behavior for show on should be decided
		$children   = $this->getChannelProp( 'children', array() );
		$showOnPath = $children && count( $children ) > 0 ? 'children.0.showOn' : 'showOn';
		$showOn     = $this->getChannelProp( $showOnPath );

		// If children[0].showOn doesn't exist, fallback to default showOn
		if ( $children && count( $children ) > 0 && ( ! $showOn || ! is_array( $showOn ) ) ) {
			$showOn = $this->getChannelProp( 'showOn' );
		}

		if ( ! $showOn || ! is_array( $showOn ) || ! in_array( $this->device, $showOn ) ) {
			return false;
		}

		// Loop through children to see if at least one can be rendered
		$availableContacts = array();
		foreach ( $children as $key => $child ) {
			$canRenderContact = $this->canRenderContact( "children.{$key}" );
			if ( $canRenderContact ) {
				$availableContacts[] = $child;
			}
		}
		if ( count( $availableContacts ) === 0 ) {
			return false;
		}

		return true;
	}

	/*
	 * Render a channel with only one contact
	 */
	public function renderChannel( $index = 0 ) {

		$index       = 0;
		$contactPath = "children.{$index}";
		$defaultText = $this->getChannelProp( 'textOnHover' );
		$text        = $this->getChannelProp( "{$contactPath}.textOnHover", $defaultText );
		$bgColor     = $this->getChannelProp( 'backgroundColor' );

		$initialAttrs = array();
		if ( count( $this->parentWidget->channels ) === 1 ) {
			$initialAttrs['style'] = $this->getChannelIconStyles( true );
		}

		$attributes = $this->getLinkAttributes( $contactPath, $initialAttrs, true );

		if ( ! $this->canRenderContact( $contactPath ) ) {
			return false;
		}

		$gradientBackground = $this->getChannelBackgroundGradient();

		$styles = array(
			'--sl-icon-color'  => $bgColor,
			'background-image' => $gradientBackground ? $gradientBackground : null,
		);

		$stylesString = Utils::convertStyleArrayToString( $styles );

		?>
		<div class="siteleads-channel__wrapper"
			style="<?php echo esc_attr( $stylesString ); ?>">
			<?php $this->printContactButton( $index, $attributes, $text ); ?>
		</div>
		<?php

		return true;
	}

	public function getChannelIconStyles( $as_string = false ) {
		$bgColor       = $this->getChannelProp( 'backgroundColor' );
		$iconComponent = array( 'styledComponent' => 'icon' );
		$boxShadow     = $this->getStyle( 'boxShadow', null, $iconComponent );
		$borderColor   = $this->getStyle( 'borderColor', null, $iconComponent );
		$borderWidth   = $this->getStyle( 'borderWidth', null, $iconComponent );
		$isDesktop     = $this->device === 'desktop';

		$styles = array(
			'color'            => esc_attr( $bgColor ),
			'border-radius'    => '100%',
			'background-color' => esc_attr( $bgColor ),
		);
		if ( count( $this->parentWidget->channels ) === 1 ) {
			$styles['box-shadow']            = $isDesktop ? sprintf( 'var(--sl-shadow-%s)', esc_attr( $boxShadow ) ) : 'none';
			$styles['border-width']          = esc_attr( $borderWidth ) . 'px';
			$styles['--sl-fab-border-width'] = esc_attr( $borderWidth ) . 'px';
			$styles['border-color']          = esc_attr( $borderColor );
		}

		if ( $as_string ) {
			return Utils::convertStyleArrayToString( $styles );
		}

		return $styles;
	}

	/*
	 * Print only the contact icon
	 * $index - contact index in the children array
	 * $hasAvatar - whether to check for avatar URL
	 */
	public function printContactIcon( $index, $hasAvatar = false ) {
		$icon          = Utils::getIconAsset( 'avatar-placeholder.svg' );
		$customIconUrl = $this->getChannelProp( 'customIcon.url' );
		$hasSiblings   = $this->channelHasMultipleContacts();
		$avatarUrl     = $this->getChannelProp( "children.{$index}.avatar.url" );
		$bgColor       = $this->getChannelProp( "children.{$index}.backgroundColor" );

		if ( ! $bgColor ) {
			$bgColor = $this->getChannelProp( 'backgroundColor', '#1a1a1a' );
		}

		$src = null;
		if ( $hasSiblings && $avatarUrl ) {
			// Inside contact Launcher - has avatar set
			$src = $avatarUrl;
		} elseif ( ! $hasAvatar && $customIconUrl ) {
			// Custom channel icon
			$src = $customIconUrl;
		} elseif ( ! $hasAvatar && ! $customIconUrl ) {
			// Default channel icon
			$icon = Utils::getIconAsset( "channels-icons/{$this->name}.svg" );
		}

		if ( $src ) {
			$icon = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $this->name ) . '"/>';
		}

		?>
		<span class="sl-channel-contact-icon" style="--sl-icon-color: <?php echo esc_attr( $bgColor ); ?>;">
			<?php echo $icon;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon is already escaped when setting $src or is a static asset ?>
		</span>
		<?php
	}


	/*
	 * Print contact button.
	 * If a channel has only one contact, it will be used as the main button (channel button)
	 * If a channel has multiple contacts, it will be used in the launcher
	 * $index - contact index in the children array (-1 for main channel button)
	 */
	public function printContactButton( $index, $attributes, $label = '', $hasAvatar = false ) {
		if ( ! $this->canRenderContact( "children.{$index}" ) ) {
			//return;
		}

		$classes = array( 'siteleads-channel__icon' );
		if ( $hasAvatar ) {
			$classes[] = '--sl-has-avatar';
		}

		$icon           = Utils::getIconAsset( 'avatar-placeholder.svg' );
		$customIconUrl  = $this->getChannelProp( 'customIcon.url' );
		$hasSiblings    = $this->channelHasMultipleContacts();
		$avatarUrl      = $this->getChannelProp( "children.{$index}.avatar.url" );
		$channelBgColor = $this->getChannelProp( 'backgroundColor' );
		$shadowLevel    = $this->getStyle( 'boxShadow', null, array( 'styledComponent' => 'icon' ) );

		$src = null;
		if ( $hasSiblings && $avatarUrl ) {
			// Inside contact Launcher - has avatar set
			$src = $avatarUrl;
		} elseif ( ! $hasAvatar && $customIconUrl ) {
			// Custom channel icon
			$src = $customIconUrl;
		} elseif ( ! $hasAvatar && ! $customIconUrl ) {
			// Default channel icon
			$icon = Utils::getIconAsset( "channels-icons/{$this->name}.svg" );
		}

		if ( $src ) {
			$icon = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $this->name ) . '"/>';
		}

		$gradientBackground = $this->getChannelBackgroundGradient();

		$styles = array(
			'color'         => $channelBgColor,
			'background'    => $gradientBackground ? $gradientBackground : 'var(--sl-icon-color, transparent)',
			'border-radius' => '100%',
			'box-shadow'    => $shadowLevel ? sprintf( 'var(--sl-shadow-%s)', esc_attr( $shadowLevel ) ) : 'none',
		);
		if ( $gradientBackground ) {
			$styles['color'] = 'transparent';
		}

		?>
		<a class="siteleads-channel siteleads-channel__<?php echo esc_attr( $this->name ); ?>" 
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attributes is properly escaped in getLinkAttributes method
		echo $attributes;
		?>
		>
			<span
				class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
				style="<?php echo esc_attr( Utils::convertStyleArrayToString( $styles ) ); ?>"
			>
					<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $icon is already escaped when setting $src or is a static asset
						echo $icon;
					?>
				</span>
			<span class="siteleads-channel__label"><?php echo esc_html( $label ); ?></span>
		</a>
		<?php
	}

	public function channelHasMultipleContacts() {
		$children = $this->getChannelProp( 'children' );

		return $children && count( is_array( $children ) ? $children : array() ) > 1;
	}

	/*
	 * Render a channel with multiple contacts and corresponded launcher
	 */
	public function renderChannelWithLauncher() {

		$contactPath = 'children';
		$children    = $this->getChannelProp( 'children' );
		$defaultText = $this->getChannelProp( 'textOnHover' );
		$text        = $this->getChannelProp( "{$contactPath}.textOnHover", $defaultText );
		$shadowLevel = $this->getStyle( 'boxShadow', null, array( 'styledComponent' => 'icon' ) );

		$customIconUrl = $this->getChannelProp( 'customIcon.url' );
		$icon          = Utils::getIconAsset( "channels-icons/{$this->name}.svg" );
		if ( $customIconUrl ) {
			$icon = '<img src="' . esc_url( $customIconUrl ) . '" alt="' . esc_attr( $this->name ) . '"/>';
		}
		$numberOfContacts = count( is_array( $children ) ? $children : array() );
		if ( $numberOfContacts > 1 ) {
		}

		$gradientBackground   = $this->getChannelBackgroundGradient();
		$styles               = $this->getChannelIconStyles();
		$styles['box-shadow'] = $shadowLevel ? sprintf( 'var(--sl-shadow-%s)', esc_attr( $shadowLevel ) ) : 'none';

		if ( $gradientBackground ) {
			$styles = array_merge(
				$styles,
				array(
					'background-image' => $gradientBackground,
					'color'            => 'transparent',
					'border-radius'    => '100%',
					'--sl-icon-color'  => 'transparent',
				)
			);
		}

		$stylesString = Utils::convertStyleArrayToString( $styles );

		?>
		<div class="siteleads-channel__wrapper siteleads-channel__wrapper-with-launcher" style="<?php echo esc_attr( $stylesString ); ?>">
			<a class="siteleads-channel siteleads-channel__<?php echo esc_attr( $this->name ); ?>"
				data-has-launcher="true"
				data-channel="<?php echo esc_attr( $this->name ); ?>"

			>
				<span class="siteleads-channel__icon"
						style="<?php echo esc_attr( Utils::convertStyleArrayToString( $styles ) ); ?>"
				>
					<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $icon is already escaped when setting $src or is a static asset
					echo $icon;
					?>
				</span>
				<span class="siteleads-channel__label"><?php echo esc_html( $text ); ?></span>
			</a>
		</div>
		<?php

		return true;
	}

	public function renderContactLauncher( $withLauncher = false ) {
		$popupView = $this->getProp( 'popupView' );
		$showOn    = $this->getChannelProp( 'showOn', array() );
		$label     = $this->getChannelProp( 'label' );

		if ( //will this ever be true?
			! in_array(
				$popupView[ $this->device ],
				array(
					'simple',
					'cta-bar',
					'channels-bar',
					'same-as-desktop',
				)
			) &&
			( $popupView['desktop'] !== 'chat' || $withLauncher )
		) {
			return false;
		}

		if ( ! in_array( $this->device, $showOn ) ) {
			return false;
		}

		$children = $this->getChannelProp( 'children' );
		if ( count( is_array( $children ) ? $children : array() ) < 2 ) {
			return false;
		}

		$icon          = sprintf( '<div class="siteleads-channel__icon--launcher">%s</div>', Utils::getIconAsset( "channels-icons/{$this->name}.svg" ) );
		$customIconUrl = $this->getChannelProp( 'customIcon.url' );
		if ( $customIconUrl ) {
			$icon = '<img src="' . esc_url( $customIconUrl ) . '" alt="' . esc_attr( $this->name ) . '"/>';
		}

		$bgColor = $this->getChannelProp( 'backgroundColor' );

		$headline         = $this->getChannelProp( 'contactSettings.headline', array() );
		$welcomeText      = $this->getChannelProp( 'contactSettings.welcomeText', array() );
		$headlineValue    = isset( $headline['value'] ) ? $headline['value'] : '';
		$welcomeTextValue = isset( $welcomeText['value'] ) ? $welcomeText['value'] : '';

		$headlineStyle    = Utils::fontSettingsToStyle( isset( $headline['fontSettings'] ) ? $headline['fontSettings'] : array( 'family' => 'Geist' ) );
		$welcomeTextStyle = Utils::fontSettingsToStyle( isset( $welcomeText['fontSettings'] ) ? $welcomeText['fontSettings'] : array( 'family' => 'Geist' ) );
		$headlineColor    = Arr::get( $headline, 'fontSettings.color', '#ffffff' );

		$shadowLevel     = max( 1, (int) $this->getStyle( 'boxShadow', null, array( 'styledComponent' => 'icon' ) ) );
		$launcherClasses = array(
			'siteleads-contact-launcher',
			sprintf( 'sl-shadow-%s', $shadowLevel ),
		);

		?>
		<div class="<?php echo esc_attr( implode( ' ', $launcherClasses ) ); ?>" data-is-open="false"
			data-widget="<?php echo esc_attr( $this->widgetId ); ?>"
			data-channel="<?php echo esc_attr( $this->name ); ?>"
			style="--sl-icon-color: <?php echo esc_attr( $bgColor ); ?>; --sl-contact-header-color: <?php echo esc_attr( $headlineColor ); ?>;"
		>
			<div class="siteleads-contact-launcher__content">
				<div class="siteleads-contact-launcher__content__header"
					style="--sl-channels-icon-color: white;"
				>
					<div>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $icon is already escaped when setting $src or is a static asset
						echo $icon;
						?>
						<span class="siteleads-contact-launcher__content__header-title"
								style="<?php echo esc_attr( $headlineStyle ); ?>">
							<?php echo esc_html( $headlineValue !== '' ? $headline['value'] : $label ); ?>
						</span>
					</div>
					<div class="siteleads-contact-launcher__content__header__close"
						role="button"
						aria-label="<?php echo esc_attr__( 'Close', 'siteleads' ); ?>"
						tabindex="0"
					>
						<?php Utils::printIconAsset( 'close.svg' ); ?>
					</div>
				</div>

				<div class="siteleads-contact-launcher__content__body">
					<?php if ( $welcomeTextValue && $welcomeTextValue !== '' ) : ?>
						<div class="siteleads-contact-launcher__content__description">
						<span style="<?php echo esc_attr( $welcomeTextStyle ); ?>">
							<?php echo esc_html( isset( $welcomeText['value'] ) ? $welcomeText['value'] : '' ); ?>
						</span>
						</div>
					<?php endif; ?>
					<?php $this->printContactsList( true ); ?>
				</div>
			</div>
		</div>
		<?php

		return true;
	}


	public function printContactsList( $hasAvatar = false ) {
		$children = $this->getChannelProp( 'children' );

		foreach ( $children as $index => $contact ) {
			$contactPath = "children.{$index}";

			$isOnline = true;
			if ( ! $this->canRenderContact( $contactPath ) ) {
				$isOnline = false;
			}

			$data = array();
			if ( $this->name !== 'aiAgent' ) {
				$data['data-is-contact'] = true;
				$data['data-contact-id'] = $contact['id'];
				$data['data-is-online']  = $isOnline ? 'true' : 'false';
			}
			$attributes = $this->getLinkAttributes( $contactPath, $data, true );

			$default_channel_label = $this->getChannelProp( 'label' );

			$contact_label = isset( $contact['name'] ) && $contact['name'] ? $contact['name'] : sprintf(
				'%s %d',
				$default_channel_label ? $default_channel_label : ucfirst( $this->name ),
				$index + 1
			)

			?>
			<div class="siteleads-contact-launcher__contact">
				<?php $this->printContactButton( $index, $attributes, '', $hasAvatar ); ?>

				<div class="siteleads-contact-launcher__contact-details" style="cursor: pointer;">
					<div>
						<span><?php echo esc_html( $contact_label ); ?> </span>
						<span><?php echo esc_html( $contact['role'] ?? '' ); ?> </span>
					</div>
					<div class="siteleads-contact-launcher__contact-status"></div>
				</div>
			</div>
			<?php
		}
	}

	public function canRenderContact( $contactPath ) {
		if ( $this->isPreview ) {
			//return true;
		}

		$contact = $this->getChannelProp( $contactPath );
		if ( ! $contact ) {
			return false;
		}

		$canRenderContact = apply_filters( 'siteleads_can_render_contact', true, $this, $contactPath );

		return $canRenderContact;
	}

	public function getLinkAttributes( $contactPath, $data = array(), $as_string = false ) {

		$default_attributes = array_merge(
			array(
				'rel'          => 'nofollow noopener',
				'data-channel' => $this->name,
				'target'       => '_blank',
			),
			$data
		);

		$value = null;
		switch ( $this->name ) {
			case 'whatsapp':
				$value = $this->getChannelProp( "{$contactPath}.number" );
				break;
			case 'phone':
				$value = $this->getChannelProp( "{$contactPath}.number" );
				break;
			case 'email':
				$value = $this->getChannelProp( "{$contactPath}.to" );
				break;
			case 'messenger':
				$value = $this->getChannelProp( "{$contactPath}.username" );
				break;
			case 'instagramDm':
				$value = $this->getChannelProp( "{$contactPath}.username" );
				break;
			case 'instagramPage':
				$value = $this->getChannelProp( "{$contactPath}.page" );
				break;
			case 'wechat':
				$value = $this->getChannelProp( "{$contactPath}.qr.url" );
				break;
			case 'snapchat':
				$value = $this->getChannelProp( "{$contactPath}.userid" );
				break;
			case 'discord':
				$value = $this->getChannelProp( "{$contactPath}.invitationCode" );
				break;
			case 'telegram':
				$value = $this->getChannelProp( "{$contactPath}.username" );
				break;
			case 'sms':
				$value = $this->getChannelProp( "{$contactPath}.number" );
				break;
			case 'tiktok':
				$value = $this->getChannelProp( "{$contactPath}.username" );
				break;
			case 'x':
				$value = $this->getChannelProp( "{$contactPath}.username" );
				break;
			case 'maps':
				$value = $this->getChannelProp( "{$contactPath}.location" );
				break;

		}

		$attributes = static::buildChannelLinkAttributes( $this->name, $value );

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


	private static function maybeTransformToLink( $value, $link_template, $formats = array() ) {

		if ( ! trim( $value ) ) {
			return null;
		}

		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return $value;
		}

		foreach ( $formats as $format ) {
			switch ( $format ) {
				case 'phone':
					$value = preg_replace( '/\D+/', '', $value );
					if ( strpos( $value, '00' ) === 0 ) {
						$value = substr( $value, 2 );
						$value = '+' . $value;
					}
					break;
			}
		}

		return sprintf( $link_template, urlencode( $value ) );
	}

	public static function buildChannelLinkAttributes( $type, $value ) {

		if ( ! empty( $value ) ) {
			$value = trim( $value );
		}

		$attrs = array_merge(
			array(
				'rel'    => 'nofollow noopener',
				'target' => '_blank',
			)
		);

		if ( $type === 'aiAgent' ) {
			unset( $attrs['target'] );

			return $attrs;
		}

		if ( ! $value ) {
			return array(
				'target' => '_self',
				'href'   => 'javascript:void(0);',
				// 'inert'  => 'inert',
			);
		}

		switch ( $type ) {
			case 'whatsapp':
				$attrs['href'] = self::maybeTransformToLink( $value, 'https://wa.me/%s', array( 'phone' ) );
				break;
			case 'phone':
				$attrs['href'] = self::maybeTransformToLink( $value, 'tel:%s', array( 'phone' ) );
				break;
			case 'email':
				$attrs['href'] = self::maybeTransformToLink( $value, 'mailto:%s' );
				break;
			case 'messenger':
				$attrs['href'] = self::maybeTransformToLink( $value, 'https://m.me/%s' );
				break;
			case 'instagramDm':
				if ( strpos( $value, '@' ) === 0 ) {
					$value = substr( $value, 1 );
				}
				$attrs['href'] = self::maybeTransformToLink( $value, 'https://ig.me/m/%s' );
				break;
			case 'instagramPage':
				if ( strpos( $value, '@' ) === 0 ) {
					$value = substr( $value, 1 );
				}
				$attrs['href'] = self::maybeTransformToLink( $value, 'https://instagram.com/%s' );
				break;
			case 'wechat':
				$attrs['href'] = $value;
				break;
			case 'telegram':
				if ( strpos( $value, '@' ) === 0 ) {
					$value = substr( $value, 1 );
				}
				$attrs['href'] = self::maybeTransformToLink( $value, 'https://t.me/%s' );
				break;
			case 'discord':
				$attrs['href'] = self::maybeTransformToLink( $value, 'https://discord.gg/%s' );
				break;
			case 'sms':
				$attrs['href'] = self::maybeTransformToLink( $value, 'sms:%s', array( 'phone' ) );
				break;
			case 'tiktok':
				if ( strpos( $value, '@' ) === 0 ) {
					$value = substr( $value, 1 );
				}
				$attrs['href'] = self::maybeTransformToLink( $value, 'https://tiktok.com/@%s' );
				break;
			case 'x':
				if ( strpos( $value, '@' ) === 0 ) {
					$value = substr( $value, 1 );
				}
				$attrs['href'] = self::maybeTransformToLink( $value, 'https://x.com/%s' );
				break;
			case 'maps':
				$attrs['href'] = self::maybeTransformToLink( $value, 'https://www.google.com/maps?q=%s' );
				break;
			case 'snapchat':
				$attrs['href'] = self::maybeTransformToLink( $value, 'https://www.snapchat.com/add/%s' );
				break;

		}

		return $attrs;
	}

	public function getChannelBackgroundGradient() {
		$bgGradient = null;

		$widgetTemplate = FCWidgetsManager::getDefaultWidgetTemplate();
		$defaultBgColor = strtolower( Arr::get( $widgetTemplate, "props.channels.{$this->name}.backgroundColor", '#000000' ) );
		$currentBgColor = strtolower( $this->getChannelProp( 'backgroundColor', '#000000' ) );

		if ( $currentBgColor === $defaultBgColor ) {
			$bgGradient = $this->getChannelProp( 'backgroundGradient' );
		}

		return $bgGradient;
	}
}
