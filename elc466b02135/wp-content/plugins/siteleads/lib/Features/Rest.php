<?php

namespace SiteLeads\Features;

use SiteLeads\Admin\Admin;
use SiteLeads\Core\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rest {
	use Singleton;

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}


	private function register( $path, $callback, $methods = 'GET' ) {
		register_rest_route(
			'siteleads/v1',
			$path,
			array(
				'methods'             => $methods,
				'callback'            => $callback,
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public function registerRoutes() {
		$this->register( '/set-ai-api-key', array( $this, 'setAIApiKey' ), 'POST' );
	}

	public function setAIApiKey( \WP_REST_Request $request ) {
		$key        = sanitize_text_field( $request->get_param( 'key' ) );
		$identifier = sanitize_text_field( $request->get_param( 'identifier' ) );

		if ( empty( $key ) ) {
			return new \WP_Error( 'no_key', __( 'No key provided', 'siteleads' ), array( 'status' => 400 ) );
		}

		if ( empty( $identifier ) ) {
			return new \WP_Error( 'no_identifier', __( 'No identifier provided', 'siteleads' ), array( 'status' => 400 ) );
		}

		update_option( 'siteleads_ai_api_key', $key );
		update_option( 'siteleads_ai_key_identifier', $identifier );

		return rest_ensure_response(
			array(
				'success'      => true,
				'nextSettings' => Admin::getBackendData(),
			)
		);
	}
}
