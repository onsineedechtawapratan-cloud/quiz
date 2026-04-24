<?php

namespace SiteLeads\Features\Greetings;

use IlluminateAgnostic\Arr\Support\Arr;
use SiteLeads\Features\Widgets\FCWidgets;
use SiteLeads\Utils;

class GreetingRenderer {

	private static $types = array(
		'simple',
		'narrow',
		'vertical',
		'horizontal',
		'circle',
	);

	private static $greeting_id = null;

	private static $config = array();

	public static function getConfigValue( $key, $default = null ) {
		return Arr::get( self::$config, $key, $default );
	}

	public static function getGreetingId() {
		return self::$greeting_id;
	}

	public static function printIDSelector() {
		$id = self::getGreetingId();

		if ( ! $id ) {
			return '';
		}

		printf( '#%s', esc_attr( $id ) );
	}

	public static function textConfigToStyle( $config ) {

		if ( ! is_array( $config ) ) {
			return '';
		}

		$styles = array();
		if ( isset( $config['color'] ) ) {
			$styles[] = 'color: ' . esc_html( $config['color'] );
		}
		if ( isset( $config['family'] ) ) {
			$styles[] = 'font-family: ' . esc_html( $config['family'] );
		}
		if ( isset( $config['weight'] ) ) {
			$styles[] = 'font-weight: ' . esc_html( $config['weight'] );
		}
		if ( isset( $config['size'] ) ) {
			$styles[] = 'font-size: ' . ( is_numeric( $config['size'] ) ? $config['size'] . 'px' : esc_html( $config['size'] ) );
		}

		if ( isset( $config['align'] ) ) {
			$styles[] = 'text-align: ' . esc_html( $config['align'] );
		}

		if ( isset( $config['family'] ) ) {
			$weight = isset( $config['weight'] ) ? $config['weight'] : '400';
			FCWidgets::require_google_font( $config['family'], $weight );
		}

		return implode( ';', $styles );
	}

	public static function styleArrayToString( $styles ) {
		$result = array();
		foreach ( $styles as $key => $value ) {
			$result[] = $key . ':' . $value;
		}
		return implode( ';', $result );
	}

	/**
	 * @param array $greeting_data
	 * @param FCWidget $widget
	 */
	public static function render( $greeting_data, $widget ) {

		$widget_id           = $widget->settingPath ?? uniqid( 'widget-' ); // fallback to unique ID if widget or settingPath is not available
		static::$config      = $greeting_data;
		static::$greeting_id = 'sl-' . $widget_id . '-greeting';

		$style = static::renderStyleVars( $greeting_data );

		// fab-size-full
		$fabBorderWidth = $widget->getStyle( 'borderWidth', 0, array( 'styledComponent' => 'icon' ) );
		$style         .= sprintf( '--sl-full-fab-size: calc(var(--sl-base-fab-size) + %spx * 2)', $fabBorderWidth );

		$args = array(
			'class'                => 'siteleads-greeting',
			'data-dismiss'         => Arr::get( $greeting_data, 'dismiss', 'on-widget-click' ),
			'data-dismiss-seconds' => Arr::get( $greeting_data, 'dismissDelay', 60 ),
			'style'                => $style,
		);

		$args_string = '';
		foreach ( $args as $key => $value ) {
			$args_string = sprintf( '%s %s="%s"', $args_string, esc_attr( $key ), esc_attr( $value ) );
		}

		$content = static::loadGreetingContent( $greeting_data );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $content is generated from our own templates and is safe to output
		printf( '<div %s>%s</div>', $args_string, $content );
	}

	public static function loadGreetingContent( $greeting_data ) {
		$type = Arr::get( $greeting_data, 'type', 'simple' );

		if ( ! in_array( $type, self::$types, true ) ) {
			return '';
		}

		$template          = Arr::get( $greeting_data, 'template', 'default' );
		$template_override = Arr::get( $greeting_data, '_template', null );

		if ( $template_override ) {
			$template = $template_override;
		}

		$file = __DIR__ . '/templates/' . $type . '/' . $template . '.php';
		$file = apply_filters( 'siteleads_greeting_template_file', $file, $type, $template, $greeting_data );
		$file = wp_normalize_path( $file );

		if ( file_exists( $file ) ) {
			ob_start();
			include $file;
			return ob_get_clean();
		}

		return '';
	}

	public static function renderStyleVars( $greeting_data ) {
		$styles = array();

		$varsKeys = array(
			'size',
			'bgColor',
			'borderColor',
			'borderWidth',
			'radius',
			'shadow',
			'spacing',
			'entranceDelay',
		);

		$rgb_keys = array(
			'bgColor'     => 'bg-color-rgb',
			'borderColor' => 'border-color-rgb',
		);

		foreach ( $varsKeys as $key ) {
			if ( isset( $greeting_data[ $key ] ) ) {

				if ( is_null( $greeting_data[ $key ] ) ) {
					continue;
				}

				$style_key = _wp_to_kebab_case( $key );

				$numeric_unit = 'px';
				$prop_value   = $greeting_data[ $key ];
				switch ( $key ) {
					case 'bgColor':
					case 'borderColor':
						$numeric_unit = '';
						break;
					case 'entranceDelay':
						$numeric_unit = 's';
						// if ( Utils::getPreviewedWidgetId() && ! Utils::getLivePreviewedWidgetId() ) {
						//  $prop_value = 0; // disable entrance delay in preview mode for better experience
						// }
						break;
					case 'shadow':
						$numeric_unit = '';
						$prop_value   = "var(--sl-shadow-{$prop_value})";
						break;
				}

				if ( isset( $rgb_keys[ $key ] ) ) {
					list( $r, $g, $b )           = Utils::hex2rgb( $greeting_data[ $key ] );
					$styles[ $rgb_keys[ $key ] ] = "{$r}, {$g}, {$b}";
				}

				if ( $key === 'bgColor' ) {
					// determine close button color based on bg color brightness
					$brightness                   = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
					$styles['close-button-color'] = ( $brightness > 150 ) ? '#000000' : '#FFFFFF';
				}

				$styles[ $style_key ] = is_numeric( $prop_value ) ? $prop_value . $numeric_unit : $prop_value;
			}
		}

		// get text color
		$styles['text-color'] = Arr::get( $greeting_data, 'textStyle.color', '#000000' );
		// bg image
		$image = Arr::get( $greeting_data, 'image', null );

		if ( $image || isset( $image['url'] ) ) {
			$styles['bg-image'] = 'url(' . esc_url( $image['url'] ) . ')';
		}

		$styles_string = '';

		foreach ( $styles as $key => $value ) {
			$styles_string .= '--sl-greeting-' . esc_html( $key ) . ':' . esc_html( $value ) . ';';
		}

		return $styles_string;
	}

	public static function renderContainerAttributes( $extra_classes = array() ) {

		$position = static::getConfigValue( 'position', 'near-widget' );
		$classes  = array_merge(
			array(
				'siteleads-greeting__card-container',
				'siteleads-greeting--' . static::getConfigValue( 'type', '' ),

			),
			$extra_classes
		);

		$attributes = array(
			'id'            => static::$greeting_id,
			'class'         => implode( ' ', $classes ),
			'data-entrance' => static::getConfigValue( 'entrance', 'fade' ),
			'data-position' => $position,
		);

		if ( self::getConfigValue( 'type' ) === 'simple' ) {
			$attributes['data-position'] = 'hand-sided';
		}

		if ( self::getConfigValue( 'type' ) === 'circle' ) {
			$attributes['data-position'] = 'near-widget';
		}

		foreach ( $attributes as $key => $value ) {
			printf( ' %s="%s" ', esc_attr( $key ), esc_attr( $value ) );
		}
	}


	public static function renderTitle() {
		$value = static::getConfigValue( 'title' );

		if ( empty( $value ) ) {
			return;
		}

		$titleStyle = static::textConfigToStyle( static::getConfigValue( 'titleStyle' ) );

		$value = nl2br( $value );

		?>
			<div class="siteleads-greeting__card-title" style="<?php echo esc_attr( $titleStyle ); ?>;">
				<?php echo wp_kses_post( $value ); ?>
			</div>
		<?php
	}

	public static function renderText() {
		$value = static::getConfigValue( 'text' );

		if ( empty( $value ) ) {
			return;
		}

		$value = nl2br( $value );

		$subtitleStyle = static::textConfigToStyle( static::getConfigValue( 'textStyle' ) );
		?>
			<div class="siteleads-greeting__card-subtitle" style="<?php echo esc_attr( $subtitleStyle ); ?>;">
				<?php echo wp_kses_post( $value ); ?>
			</div>
		<?php
	}

	public static function getImageURL() {
		$image = static::getConfigValue( 'image', null );

		if ( empty( $image ) || ! isset( $image['url'] ) || empty( $image['url'] ) ) {
			return '';
		}

		return $image['url'];
	}

	public static function renderImage() {

		$url = self::getImageURL();

		if ( empty( $url ) ) {
			return '';
		}

		?>
			<div class="siteleads-greeting__card-image">
				<img src="<?php echo esc_url( $url ); ?>" />
			</div>
		<?php
	}

	public static function renderBGImage( $extra_classes = array() ) {
		$url = self::getImageURL();

		if ( empty( $url ) ) {
			return '';
		}

		$classes = array_merge(
			array(
				'siteleads-greeting__card-bg-image',
				'siteleads-bg-image',
			),
			$extra_classes
		);

		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
		</div>
		<?php
	}

	public static function renderCloseButton() {
		?>
		<button class="siteleads-greeting__close-button" aria-label="<?php echo esc_attr__( 'Close greeting', 'siteleads' ); ?>">
				<?php Utils::printIconAsset( 'close.svg' ); ?>
		</button>
		<?php
	}
}
