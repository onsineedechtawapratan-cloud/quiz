<?php

namespace SiteLeads\Features;

use SiteLeads\Constants;
use SiteLeads\Core\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FCPreviewWidget {
	use Singleton;

	protected function __construct() {
		// Add ajax endpoints
		add_action( 'wp_ajax_siteleads_set_preview_data', array( $this, 'setPreviewData' ) );
		add_action( 'wp_ajax_siteleads_set_preview_agent_data', array( $this, 'setAgentPreviewData' ) );
		// add action to clean up preview data when post with type Constants::$siteLeadsDataPostType updates
		add_action( 'save_post_' . Constants::$siteLeadsDataPostType, array( __CLASS__, 'cleanupPreviewData' ) );
	}

	public function setPreviewData() {
		check_ajax_referer( 'events_nonce', 'security' );

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- we will validate the JSON in the next step, so no need to sanitize here
		$settings = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';

		$parsed_settings = json_decode( $settings, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $parsed_settings ) ) {
			delete_transient( 'siteleads_preview_data' );
			wp_send_json_error( 'Invalid JSON data' );
		}

		set_transient( 'siteleads_preview_data', $settings, 60 * 5 );
		wp_send_json_success( $settings );
	}

	public function setAgentPreviewData() {
		check_ajax_referer( 'events_nonce', 'security' );

		//phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- we will validate the JSON in the next step, so no need to sanitize here
		$data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';

		$transient_key = 'siteleads_agent_preview_data';

		if ( empty( $data ) ) {
			delete_transient( $transient_key );
			wp_send_json_success( null );
		}

		$parsed_settings = json_decode( $data, true );

		$agent_id       = isset( $parsed_settings['id'] ) ? $parsed_settings['id'] : null;
		$agent_settings = isset( $parsed_settings['settings'] ) ? $parsed_settings['settings'] : null;

		if ( ! $agent_id || ! $agent_settings ) {
			delete_transient( $transient_key );
			wp_send_json_success( null );
		}

		$transient_data = get_transient( $transient_key );

		if ( empty( $transient_data ) ) {
			$transient_data = array();
		}

		$transient_data[ $agent_id ] = $agent_settings;

		set_transient( 'siteleads_agent_preview_data', $transient_data, 60 * 5 );
		wp_send_json_success( $parsed_settings );
	}


	public static function cleanupPreviewData() {
		delete_transient( 'siteleads_preview_data' );
		delete_transient( 'siteleads_agent_preview_data' );
	}


	public static function getAgentPreviewData( $agent_id ) {
		$transient_data = get_transient( 'siteleads_agent_preview_data' );
		if ( empty( $transient_data ) || ! is_array( $transient_data ) || ! isset( $transient_data[ $agent_id ] ) ) {
			return null;
		}

		return $transient_data[ $agent_id ];
	}
}
