<?php

namespace SiteLeads\Features\Widgets;

use IlluminateAgnostic\Arr\Support\Arr;
use SiteLeads\Core\DataHelper;
use SiteLeads\Core\Flags;
use SiteLeads\Core\LodashBasic;
use SiteLeads\Utils;

class FCWidgetsManager extends DataHelper {
	const DEFAULT_WIDGET_CREATED_OPTION_NAME = 'siteleads_created_default_widget';


	private static $default_multi_contact_settings = array(
		'headline'    => array(
			'value'        => '',
			'fontSettings' => array(
				'color'  => '#FFFFFF',
				'family' => 'Geist',
				'weight' => '600',
				'size'   => 20,
				'align'  => 'left',
			),
		),
		'welcomeText' => array(
			'value'        => '',
			'fontSettings' => array(
				'color'  => '#404040',
				'family' => 'Geist',
				'weight' => '400',
				'size'   => 14,
				'align'  => 'left',
			),
		),
	);


	private static function updateWithMultiContactDefaults( &$template ) {

		$channels = Arr::get( $template, 'props.channels', array() );
		foreach ( $channels as $slug => $channel ) {
			$contactSettings = array_replace_recursive(
				static::$default_multi_contact_settings,
				Arr::get( $channel, 'contactSettings', array() )
			);

			Arr::set( $template, "props.channels.{$slug}.contactSettings", $contactSettings );
		}
	}

	public static function getDefaultWidgetTemplate() {
		static $template;

		if ( $template ) {
			return $template;
		}

		$template = require_once Utils::getFilePath( 'defaults/widget-template.php' );

		static::updateWithMultiContactDefaults( $template );

		return $template;
	}

	public static function __createDefaultWidget($options = array()) {
		if ( static::getDefaultWidgetAlreadyCreated() ) {
			return null;
		}

		$created_widgets_list = static::getWidgetListOptions();
		if ( count( $created_widgets_list ) > 0 ) {
			$first_widget_id = LodashBasic::get( $created_widgets_list, '0.value' );
			if ( $first_widget_id ) {
				update_option( static::DEFAULT_WIDGET_CREATED_OPTION_NAME, true );
				return null;
			}
		}
		$start_source = LodashBasic::get($options, 'start_source');
		if(!empty($start_source) && is_string($start_source)) {
			Flags::set( 'start_source', $start_source );
		}

		$name = __( 'Sample Widget', 'siteleads' );

		$template = static::getDefaultWidgetTemplate();

		$template['props']['name'] = $name;

		$newId = 'widget-' . (int) ( microtime( true ) * 1000 );

		$currentData                              = FCWidget::getDataFromDatabase();
		$currentData['widgets']['props']['ids'][] = $newId;
		$currentData[ $newId ]                    = $template;
		FCWidget::updatePluginDataContent( $currentData );
		update_option( static::DEFAULT_WIDGET_CREATED_OPTION_NAME, true );

		return $newId;
	}

	public static function getDefaultWidgetAlreadyCreated() {
		return get_option( static::DEFAULT_WIDGET_CREATED_OPTION_NAME, false );
	}

	public static function getFirstWidgetId() {
		$widgets       = static::getWidgetList();
		$firstWidgetId = LodashBasic::get( $widgets, '0.id' );
		return $firstWidgetId;
	}

	public static function getWidgetById( $widgetId ) {
		$currentData = FCWidget::getPluginData();
		if ( ! array_key_exists( $widgetId, $currentData ) ) {
			return null;
		}

		return new FCWidget( $widgetId );
	}
	public static function createDefaultWidgetWithPhoneWhatsappAndEmail($options = array()) {
		$widget_id = static::__createDefaultWidget($options);
		$widget    = static::getWidgetById( $widget_id );
		if ( empty( $widget ) || ! ( $widget instanceof FCWidget ) ) {
			return null;
		}

		$is_enabled = !!LodashBasic::get($options, 'isEnabled', true);

		$widget->setProp( 'publishedAt', date(DATE_ATOM) );
		$widget->setProp( 'isEnabled', $is_enabled );
		//$widget->setProp( 'greeting.enabled', false );

		$widget->saveChanges();
		return $widget_id;
	}
	public static function createDefaultWidgetWithOnlyPhoneChannel( $phone_nr = '', $options = array()) {
		$widget_id = static::__createDefaultWidget($options);
		$widget    = static::getWidgetById( $widget_id );
		if ( empty( $widget ) || ! ( $widget instanceof FCWidget ) ) {
			return null;
		}
		$widget->setProp( 'channels.phone.enabled', true );
		if ( empty( $phone_nr ) ) {
			$phone_nr = '';
		}
		$phone_data = array(
			'id'     => 1,
			'name'   => '',
			'number' => $phone_nr,
		);



		$widget->setProp( 'channels.phone.children', array( $phone_data ) );
		$widget->setProp( 'activeChannels', array( 'phone' ) );
		$widget->setProp( 'channelsOrder', array( 'phone' ) );
		$widget->setProp( 'channels.whatsapp.enabled', false );
		$widget->setProp( 'channels.email.enabled', false );


		$is_enabled = !!LodashBasic::get($options, 'isEnabled', true);
		$widget->setProp( 'publishedAt', date(DATE_ATOM) );
		$widget->setProp( 'isEnabled', $is_enabled );
		//$widget->setProp( 'greeting.enabled', false );



		$widget->saveChanges();
		return $widget_id;
	}

	public static function createDefaultWidgetWithOnlyWhatsappChanel( $phone_nr = '', $options = array() ) {
		$widget_id = static::__createDefaultWidget($options);
		$widget    = static::getWidgetById( $widget_id );
		if ( empty( $widget ) || ! ( $widget instanceof FCWidget ) ) {
			return null;
		}

		$widget->setProp( 'channels.whatsapp.enabled', true );
		if ( empty( $phone_nr ) ) {
			$phone_nr = '';
		}
		$phone_data = array(
			'id'     => 1,
			'name'   => '',
			'number' => $phone_nr,
		);
		$widget->setProp( 'channels.phone.children', array( $phone_data ) );
		$widget->setProp( 'activeChannels', array( 'whatsapp' ) );
		$widget->setProp( 'channelsOrder', array( 'whatsapp' ) );
		$widget->setProp( 'channels.phone.enabled', false );
		$widget->setProp( 'channels.email.enabled', false );


		$is_enabled = !!LodashBasic::get($options, 'isEnabled', true);
		$widget->setProp( 'publishedAt', date(DATE_ATOM) );
		$widget->setProp( 'isEnabled', $is_enabled );
	//	$widget->setProp( 'greeting.enabled', false );

		$widget->saveChanges();
		return $widget_id;
	}

	public static function toggleWidgetEnabled( $widget, $enabled = true ) {
		if ( is_string( $widget ) ) {
			$widget = static::getWidgetById( $widget );
		}
		if ( empty( $widget ) || ! ( $widget instanceof FCWidget ) ) {
			return null;
		}
		$widget->setProp( 'isEnabled', $enabled );
		$widget->saveChanges();
	}

	public static function updateWidgetPhoneNumberAfterCreation( $widget, $phone_nr = '' ) {
		if ( is_string( $widget ) ) {
			$widget = static::getWidgetById( $widget );
		}
		if ( empty( $widget ) || ! ( $widget instanceof FCWidget ) ) {
			return null;
		}

		$widget->setProp( 'channels.phone.enabled', true );

		$phone_data = array(
			'id'     => 1,
			'name'   => '',
			'number' => $phone_nr,
		);
		$widget->setProp( 'channels.phone.children', array( $phone_data ) );

		$widget->setProp( 'isEnabled', true );
		$active_channels = $widget->getProp( 'activeChannels', array() );
		if ( ! in_array( 'phone', $active_channels ) ) {
			$active_channels[] = 'phone';
			$widget->setProp( 'activeChannels', $active_channels );
			$widget->setProp( 'channelsOrder', $active_channels );
		}

		$widget->saveChanges();
	}

	public static function updateWidgetWhatsappNumberAfterCreation( $widget, $phone_nr = '' ) {
		if ( is_string( $widget ) ) {
			$widget = static::getWidgetById( $widget );
		}
		if ( empty( $widget ) || ! ( $widget instanceof FCWidget ) ) {
			return null;
		}
		$widget->setProp( 'channels.whatsapp.enabled', true );

		$phone_data = array(
			'id'     => 1,
			'name'   => '',
			'number' => $phone_nr,
		);
		$widget->setProp( 'channels.whatsapp.children', array( $phone_data ) );

		$widget->setProp( 'isEnabled', true );
		$active_channels = $widget->getProp( 'activeChannels', array() );
		if ( ! in_array( 'whatsapp', $active_channels ) ) {
			$active_channels[] = 'whatsapp';
			$widget->setProp( 'activeChannels', $active_channels );
			$widget->setProp( 'channelsOrder', $active_channels );
		}

		$widget->saveChanges();
	}
	static function getWidgetList() {
		$pluginData = static::getPluginData();
		$ids        = LodashBasic::get( $pluginData, 'widgets.props.ids', array() );

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
	public static function getWidgetListOptions() {

		$widgets       = static::getWidgetList();
		$siteLeadsData = static::getPluginData();

		$items = array_map(
			function ( $item ) use ( $siteLeadsData ) {
				$id = LodashBasic::get( $item, 'id', null );
				return array(
					'value'     => $id,
					'label'     => LodashBasic::get( $item, 'props.name', null ),
					'isEnabled' => LodashBasic::get( $siteLeadsData, array( $id, 'props', 'isEnabled' ), true ),
				);
			},
			$widgets
		);

		//We make sure we have the data we require
		$items = array_filter(
			$items,
			function ( $item ) {
				return LodashBasic::get( $item, 'value' ) !== null && LodashBasic::get( $item, 'label' ) !== null;
			}
		);

		return $items;
	}
}
