<?php

namespace SiteLeads\Features;

use IlluminateAgnostic\Arr\Support\Arr;
use SiteLeads\Features\Widgets\FCWidgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FrontendWidgetLoadActions {

	public function __construct() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- we just check for the presence of the parameter here, we don't use the value
		if ( isset( $_REQUEST['siteleads_load_widgets'] ) ) {
			add_action( 'wp', array( $this, 'render_widgets' ), 10 );
		}
	}

	public function render_widgets() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- we decode the json, extract and validate relevant parameters in the next steps, so no need to sanitize here
		$payload    = isset( $_POST['payload'] ) ? json_decode( wp_unslash( $_POST['payload'] ), true ) : null;
		$widget_ids = isset( $payload['widgets'] ) ? $payload['widgets'] : array();
		$device     = isset( $payload['device'] ) ? $payload['device'] : 'desktop';
		$device     = in_array( $device, array( 'mobile', 'desktop' ), true ) ? $device : 'desktop';

		$is_preview             = FCWidgets::getInstance()->getWidgetInPreviewMode() ? true : false;
		$is_live_preview        = false;
		$live_preview_widget_id = FCWidgets::getInstance()->getWidgetInLivePreviewMode();

		if ( $live_preview_widget_id ) {
			$is_live_preview = true;
			$widget_ids[]    = $live_preview_widget_id;
		}

		$response = array(
			'is_preview'        => $is_preview,
			'is_live_preview'   => $is_live_preview,
			'track_event_nonce' => wp_create_nonce( 'siteleads_track_event' ),
			'widgets'           => array(),
			'styles'            => array(),
			'scripts'           => array(),
		);

		$widgets = FCWidgets::getInstance()->getWidgets( $widget_ids, $device );

		foreach ( $widgets as $widget ) {
			/** @var \SiteLeads\Features\Widgets\FCWidget $widget */
			$can_render = $widget->canRenderWidget( $payload, $is_preview || $is_live_preview );
			$content    = '';
			if ( $can_render ) {
				ob_start();
					$widget->printWidget();
				$content = ob_get_clean();
			}

			if ( $content ) {

				$props = $widget->getProp( '' );
				if ( ! $widget->hasActiveAgent() ) {
					$activeChannels          = Arr::get( $props, 'activeChannels', array() );
					$props['activeChannels'] = array_diff( $activeChannels, array( 'aiAgent' ) );
					Arr::set( $props, 'channels.aiAgent.enabled', false );
					Arr::set( $props, 'channels.aiAgent.chatbotData', null );
				}

				$widget_front_json                       = array(
					'content' => $content,
					'agentId' => $widget->hasActiveAgent() ? $widget->getWidgetId() : null,
					'name'    => $widget->getProp( 'name' ),
					'props'   => $props,
				);
				$widget_front_json                       = apply_filters( 'siteleads_frontend_widget_json', $widget_front_json, $widget );
				$response['widgets'][ $widget->getId() ] = $widget_front_json;
			}
		}

		$response['styles'] = array(
			array(
				'type'  => 'url',
				'value' => FCWidgets::getInstance()->buildGoogleFontsURL(),
			),
		);

		foreach ( FCWidgets::get_widget_scripts() as $widget => $handle_scripts ) {

			if ( ! isset( $response['widgets'][ $widget ]['content'] ) ||
				empty( $response['widgets'][ $widget ]['content'] )
			) {
				continue;
			}

			foreach ( $handle_scripts as $script_src ) {

				$type = filter_var( $script_src, FILTER_VALIDATE_URL ) ? 'url' : 'config';

				$response['scripts'][] = array(
					'widget' => $widget,
					'type'   => $type,
					'value'  => $script_src,
				);
			}
		}

		wp_send_json_success( $response );
	}
}
