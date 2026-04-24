<?php

namespace SiteLeads\Core;

use SiteLeads\Constants;
use SiteLeads\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activation {
	use Singleton;

	protected function __construct() {
		register_activation_hook( SITELEADS_ENTRY_FILE, array( $this, 'onActivate' ) );
		$this->maybeRedirectAfterActivation();
	}

	protected function maybeRedirectAfterActivation() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		add_action(
			'admin_init',
			function () {
				if ( get_option( 'siteleads_activation_redirect', false ) ) {
					delete_option( 'siteleads_activation_redirect' );
					wp_safe_redirect( admin_url( 'admin.php?page=siteleads' ) );
					exit;
				}
			}
		);
	}


	public function onActivate() {
		$this->setFlags();
		$this->loadDefaultData();
		$this->createAnalyticsTables();
		$this->registerActivationRedirect();
	}


	public function setFlags() {

		$activation_time_key = 'activation_time';

		if ( defined( 'SITELEADS_IS_PRO' ) && SITELEADS_IS_PRO ) {
			$activation_time_key = 'pro_' . $activation_time_key;
		}

		if ( ! Flags::get( $activation_time_key ) ) {
			Flags::set( $activation_time_key, time() );
		}
	}

	public function registerActivationRedirect() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		add_option( 'siteleads_activation_redirect', true );
	}

	public function loadDefaultData() {
		$query = new \WP_Query(
			array(
				'post_type'     => Constants::$siteLeadsDataPostType,
				'post_status'   => array( 'draft', 'publish' ),
				'no_found_rows' => true,
				'post_per_page' => 1,

			)
		);

		if ( ! $query->have_posts() ) {
			$this->insertDefaultData();
		}
	}

	public function insertDefaultData() {
		$content = file_get_contents( Utils::getFilePath( 'defaults/default-data.json' ) );
		wp_insert_post(
			array(
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => Constants::$siteLeadsDataPostType,
				'post_name'    => Constants::$siteLeadsDataPostType,
				'post_title'   => __( 'SiteLeads data', 'siteleads' ),
			),
			true
		);
	}

	public function createAnalyticsTables() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'siteleads_events';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
	       	event_id CHAR(36) NOT NULL,
	        event_type VARCHAR(50) NOT NULL,
	        channel VARCHAR(50) NULL,
			channels TEXT NOT NULL,
	        visitor_id VARCHAR(64) NULL,
	        user_id BIGINT(20) NULL,
	        session_id CHAR(36) NULL,
	        widget_id VARCHAR(50) NULL,
	        agent_id VARCHAR(50) NULL,
	        page_url TEXT,
	        device_type VARCHAR(20) DEFAULT 'unknown',
	        has_parent_widget BOOL DEFAULT TRUE,
	        created_at DATE NOT NULL,
	        PRIMARY KEY (event_id)
	    ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
