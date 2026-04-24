<?php

namespace SiteLeads\Features;

use SiteLeads\Constants;
use SiteLeads\Core\DataHelper;
use SiteLeads\Core\Singleton;
use SiteLeads\Features\Widgets\FCWidgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FCAnalytics extends DataHelper {
	use Singleton;

	protected $settingPath = '';

	protected function __construct() {
		parent::__construct();

		// Track events
		add_action( 'wp_ajax_siteleads_track_event', array( $this, 'wp_ajax_track_event' ) );
		add_action( 'wp_ajax_nopriv_siteleads_track_event', array( $this, 'wp_ajax_track_event' ) );

		// Total widget clicks
		add_action( 'wp_ajax_siteleads_get_widget_clicks', array( $this, 'wp_ajax_get_widget_clicks' ) );

		// Click rate
		add_action( 'wp_ajax_siteleads_get_click_rate', array( $this, 'wp_ajax_get_click_rate' ) );

		// Delete widget
		add_action( 'wp_ajax_siteleads_delete_widget', array( $this, 'wp_ajax_delete_widget' ) );
	}

	private function track_validate_event_type( $event_type ) {
		$valid_event_types = array(
			'widget-impression',
			'widget-click',
			'channel-click',
		);

		if ( ! in_array( $event_type, $valid_event_types, true ) ) {
			throw new \Exception( __( 'Invalid event_type parameter.', 'siteleads' ) );
		}
	}

	private function track_validate_widget_id( $widget_id ) {
		if ( empty( $widget_id ) ) {
			throw new \Exception( __( 'Missing widget_id parameter.', 'siteleads' ) );
		}

		$ids = FCWidgets::getInstance()->getWidgetIds();

		if ( ! in_array( $widget_id, $ids, true ) ) {
			throw new \Exception( __( 'Invalid widget_id parameter.', 'siteleads' ) );
		}
	}

	private function track_validate_agent_id( $agent_id ) {
		if ( empty( $agent_id ) ) {
			return; // agent_id is optional, so if it's not provided, we can skip validation
		}

		$ids = FCWidgets::getInstance()->getAgentIds();

		if ( intval( $agent_id ) && ! in_array( intval( $agent_id ), $ids, true ) ) {
			throw new \Exception( __( 'Invalid agent_id parameter.', 'siteleads' ) );
		}
	}


	private function track_validate_channel( $channel ) {
		if ( empty( $channel ) ) {
			return; // channel is optional, so if it's not provided, we can skip validation
		}

		if ( ! in_array( $channel, Constants::$channels, true ) ) {
			throw new \Exception( __( 'Invalid channel parameter.', 'siteleads' ) );
		}
	}


	private function track_validate_channels_json_list( $channels ) {
		$channels_array = json_decode( $channels, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \Exception( __( 'Invalid channels parameter: not a valid JSON string.', 'siteleads' ) );
		}

		if ( ! is_array( $channels_array ) ) {
			throw new \Exception( __( 'Invalid channels parameter: expected a JSON array.', 'siteleads' ) );
		}

		foreach ( $channels_array as $channel ) {
			if ( ! in_array( $channel, Constants::$channels, true ) ) {
				// translators: %s is the invalid channel name that was passed in the request.
				throw new \Exception( sprintf( __( 'Invalid channel in channels list: %s', 'siteleads' ), esc_html( $channel ) ) );
			}
		}
	}

	public function track_validate_visitor_id( $visitor_id ) {
		// visitor_id is optional, but if provided, it should be a valid UUIDv4
		if ( ! empty( $visitor_id ) && ! wp_is_uuid( $visitor_id, 4 ) ) {
			throw new \Exception( __( 'Invalid visitor_id parameter: must be a valid UUIDv4 string.', 'siteleads' ) );
		}
	}

	public function track_validate_has_parent_widget( $has_parent_widget ) {
		if ( ! in_array( $has_parent_widget, array( '0', '1' ), true ) ) {
			throw new \Exception( __( 'Invalid has_parent_widget parameter: must be "0" or "1".', 'siteleads' ) );
		}
	}

	public function track_validate_page_url( $page_url ) {
		if ( ! empty( $page_url ) && ! filter_var( $page_url, FILTER_VALIDATE_URL ) ) {
			throw new \Exception( __( 'Invalid page_url parameter: must be a valid URL.', 'siteleads' ) );
		}

		// if page is not from this wp instance, reject it
		if ( ! empty( $page_url ) && strpos( $page_url, home_url() ) !== 0 ) {
			throw new \Exception( __( 'Invalid page_url parameter: URL must be from the same domain as the WordPress site.', 'siteleads' ) );
		}
	}

	public function validate_track_event_request( $event_type, $widget_id, $agent_id, $channel, $channels, $visitor_id, $has_parent_widget, $page_url ) {
		$this->track_validate_event_type( $event_type );
		$this->track_validate_widget_id( $widget_id );
		$this->track_validate_agent_id( $agent_id );
		$this->track_validate_channel( $channel );
		$this->track_validate_channels_json_list( $channels );
		$this->track_validate_visitor_id( $visitor_id );
		$this->track_validate_has_parent_widget( $has_parent_widget );
		$this->track_validate_page_url( $page_url );
	}

	public function wp_ajax_track_event() {
		try {
			// Verify nonce for security
			check_ajax_referer( 'siteleads_track_event', 'security' );

			$event_type        = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : null;
			$widget_id         = isset( $_POST['widget_id'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_id'] ) ) : null;
			$agent_id          = isset( $_POST['agent_id'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_id'] ) ) : null;
			$channel           = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : null;
			$channels          = isset( $_POST['channels'] ) ? sanitize_text_field( wp_unslash( $_POST['channels'] ) ) : '[]';
			$visitor_id        = isset( $_POST['visitor_id'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_id'] ) ) : null;
			$has_parent_widget = isset( $_POST['has_parent_widget'] ) ? sanitize_text_field( wp_unslash( $_POST['has_parent_widget'] ) ) : null;
			$page_url          = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';

			if ( empty( $event_type ) ) {
				throw new \Exception( __( 'Missing event_type parameter.', 'siteleads' ) );
			}

			if ( ! $visitor_id ) {
				$visitor_id = wp_generate_uuid4();
			}

			try {
				$this->validate_track_event_request(
					$event_type,
					$widget_id,
					$agent_id,
					$channel,
					$channels,
					$visitor_id,
					$has_parent_widget,
					$page_url
				);
			} catch ( \Exception $e ) {
				// If validation fails, return an error response with details
				wp_send_json_error(
					array(
						'error' => $e->getMessage(),
						'code'  => $e->getCode(),
					)
				);
			}

			$rows       = array();
			$event_type = explode( ',', $event_type );
			if ( is_array( $event_type ) ) {
				foreach ( $event_type as $type ) {
					$row = $this->siteleads_log_event( $type, $widget_id, $agent_id, $channels, $has_parent_widget, $channel, $visitor_id, $page_url );
					if ( $row ) {
						$rows[] = $row;
					}
				}
			} else {
				$rows[] = $this->siteleads_log_event( $event_type, $widget_id, $agent_id, $channels, $has_parent_widget, $channel, $visitor_id, $page_url );
			}

			// Return success response
			wp_send_json_success( $rows );

		} catch ( Exception $e ) {
			// Handle expected exceptions
			wp_send_json_error(
				array(
					'error' => $e->getMessage(),
					'code'  => $e->getCode(),
					'trace' => WP_DEBUG ? $e->getTraceAsString() : null, // only show stack trace if debugging
				)
			);
		} catch ( Throwable $e ) {
			// Catch fatal or unexpected errors
			wp_send_json_error(
				array(
					'error'   => __( 'Unexpected server error.', 'siteleads' ),
					'details' => WP_DEBUG ? $e->getMessage() : null,
				)
			);
		}
	}

	public function siteleads_log_event( $event_type, $widget_id, $agent_id, $channels, $has_parent_widget = true, $channel = null, $visitor_id = null, $page_url = '' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		if ( defined( 'SITE_LEADS_RANDOM_LOG' ) && SITE_LEADS_RANDOM_LOG ) {
			// Generate a random timestamp between "now" and 14 days ago
			$two_weeks_in_seconds = 14 * 24 * 60 * 60;
			$random_timestamp     = time() - wp_rand( 0, $two_weeks_in_seconds );

			$created_at = gmdate( 'Y-m-d', $random_timestamp );
		} else {
			// Use the standard WordPress current time function
			$created_at = current_time( 'Y-m-d' );
		}

		$event_id = wp_generate_uuid4();
		//$created_at  = current_time( 'Y-m-d' );
		$user_id     = get_current_user_id() ?: null;
		$session_id  = null; //wp_generate_uuid4();
		$device_type = wp_is_mobile() ? 'mobile' : 'desktop';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert required for analytics event logging.
		$id = $wpdb->insert(
			$table_name,
			array(
				'event_id'          => $event_id,
				'created_at'        => $created_at,
				'event_type'        => $event_type,
				'channel'           => $channel,
				'channels'          => $channels,
				'visitor_id'        => $visitor_id,
				'user_id'           => $user_id,
				'session_id'        => $session_id,
				'page_url'          => $page_url,
				'device_type'       => $device_type,
				'has_parent_widget' => $has_parent_widget,
				'widget_id'         => $widget_id,
				'agent_id'          => $agent_id,
			)
		);

		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fetches newly inserted row; caching is not applicable here.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE event_id = %s',
					$table_name,
					$event_id
				),
				ARRAY_A
			);

			return $row;
		} else {
			return array(
				'success' => false,
			);
		}
	}

	public function wp_ajax_get_widget_clicks() {
		check_ajax_referer( 'events_nonce', 'security' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';

		if ( ! $start_date || ! $end_date ) {
			wp_send_json_error( array( 'message' => __( 'Missing date range', 'siteleads' ) ) );
		}

		// -------------------------
		// Total channel clicks across all widgets
		// -------------------------
		$query = $wpdb->prepare(
			"
	        SELECT COUNT(*)
	        FROM %i
	        WHERE event_type = 'widget-click'
	          AND created_at >= %s AND created_at <= %s
	    ",
			$table_name,
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare() above.
		$total_clicks = intval( $wpdb->get_var( $query ) );

		// -------------------------
		// Uniques visitors across all widgets
		// -------------------------
		$query = $wpdb->prepare(
			"
	        SELECT widget_id, COUNT(distinct visitor_id ) as uniqueImpressions
	        FROM %i
	        WHERE event_type = 'widget-impression'
	          AND created_at >= %s AND created_at <= %s
	        GROUP BY widget_id
	    ",
			$table_name,
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare() above.
		$results = $wpdb->get_results( $query );

		$widget_ids                = array();
		$unique_visitors_by_widget = array();
		foreach ( $results as $row ) {
			$widget_ids[]                                 = $row->widget_id;
			$unique_visitors_by_widget[ $row->widget_id ] = intval( $row->uniqueImpressions );
		}

		// Widget data
		$channels_by_widget     = $this->get_unique_channels_data_by_widget( $start_date, $end_date );
		$channels_all_widgets   = $this->get_unique_channels_data_all_widgets( $start_date, $end_date );
		$chart_data_by_widget   = $this->get_unique_daily_data_by_widget( $start_date, $end_date );
		$chart_data_all_widgets = $this->get_unique_daily_data_all_widgets( $start_date, $end_date );
		$widget_data_by_device  = $this->get_unique_data_by_device( $start_date, $end_date );

		// Agent data
		$table_agents_data = $this->get_unique_agents_table_data( $start_date, $end_date );
		$chart_agents_data = $this->get_unique_daily_agents_data( $start_date, $end_date );

		// -------------------------
		// Response
		// -------------------------
		wp_send_json_success(
			array(
				'total'   => $total_clicks,
				'widgets' => array(
					'byDevice'  => $widget_data_by_device,
					'byChannel' => array_merge(
						$channels_by_widget,
						array( 'all' => $channels_all_widgets )
					),
					'daily'     => array_merge(
						$chart_data_by_widget,
						array( 'all' => $chart_data_all_widgets )
					),
					'ids'       => $widget_ids,
				),
				'agents'  => array(
					'byAgent' => $table_agents_data,
					'daily'   => $chart_agents_data,
				),
			)
		);
	}

	public function wp_ajax_get_click_rate() {
		check_ajax_referer( 'events_nonce', 'security' );

		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';

		if ( ! $start_date || ! $end_date ) {
			wp_send_json_error( array( 'message' => __( 'Missing date range', 'siteleads' ) ) );
		}

		$total_unique_openers_all        = $this->get_unique_clicks_across_all_widgets( $start_date, $end_date );
		$unique_openers_by_widget        = $this->get_unique_clicks_by_widget( $start_date, $end_date );
		$total_unique_clickers_all       = $this->get_unique_clicks_across_all_channels( $start_date, $end_date );
		$unique_clickers_by_channel      = $this->get_unique_clicks_by_channel( $start_date, $end_date );
		$channel_data_results            = $this->get_unique_channel_clicks_by_widget_id( $start_date, $end_date );
		$total_unique_clickers_by_widget = $this->get_unique_clickers_per_widget( $start_date, $end_date );

		$widget_map = array();

		foreach ( $channel_data_results as $row ) {
			$w_id    = $row->widget_id;
			$channel = $row->channel;
			$clicks  = (int) $row->clicks;

			$w_openers = isset( $unique_openers_by_widget[ $w_id ] ) ? (int) $unique_openers_by_widget[ $w_id ] : 0;

			if ( ! isset( $widget_map[ $w_id ] ) ) {
				$w_total_clicks = isset( $total_unique_clickers_by_widget[ $w_id ] ) ? (int) $total_unique_clickers_by_widget[ $w_id ]->total_clicks : 0;

				$widget_map[ $w_id ] = array(
					'clicks'     => $w_total_clicks,
					'click_rate' => ( $w_openers > 0 ) ? round( ( $w_total_clicks / $w_openers ) * 100, 2 ) : 0,
					'rates'      => array(),
				);
			}

			$widget_map[ $w_id ]['rates'][ $channel ] = array(
				'clicks'     => $clicks,
				'click_rate' => ( $w_openers > 0 ) ? round( ( $clicks / $w_openers ) * 100, 2 ) : 0,
			);
		}

		$sort_desc = function ( $a, $b ) {
			return $b['click_rate'] <=> $a['click_rate'];
		};

		foreach ( $widget_map as &$w_data ) {
			uasort( $w_data['rates'], $sort_desc );
		}

		$all_rates_formatted = array();
		foreach ( $unique_clickers_by_channel as $channel => $clicks ) {
			$all_rates_formatted[ $channel ] = array(
				'clicks'     => (int) $clicks,
				'click_rate' => ( $total_unique_openers_all > 0 ) ? round( ( $clicks / $total_unique_openers_all ) * 100, 2 ) : 0,
			);
		}
		uasort( $all_rates_formatted, $sort_desc );

		$widget_map['all'] = array(
			'clicks'     => $total_unique_clickers_all,
			'click_rate' => ( $total_unique_openers_all > 0 ) ? round( ( $total_unique_clickers_all / $total_unique_openers_all ) * 100, 2 ) : 0,
			'rates'      => $all_rates_formatted,
		);

		wp_send_json_success( $widget_map );
	}

	public function wp_ajax_delete_widget() {
		check_ajax_referer( 'events_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'siteleads' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$widget_id = isset( $_POST['widget_id'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_id'] ) ) : '';

		if ( empty( $widget_id ) ) {
			wp_send_json_error( __( 'Widget ID is required', 'siteleads' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct delete required to remove widget analytics data.
		$deleted = $wpdb->delete(
			$table_name,
			array( 'widget_id' => $widget_id ),
			array( '%s' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( __( 'Failed to delete events from database', 'siteleads' ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Widget events deleted successfully', 'siteleads' ),
				'count'   => $deleted,
			)
		);
	}

	/**
	 * Gets unique visitors per channel, grouped by widget.
	 * Used for the granular breakdown.
	 */
	public function get_unique_channel_clicks_by_widget_id( $start_date, $end_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query; caching not appropriate for real-time data.
		return $wpdb->get_results(
			$wpdb->prepare(
				"
        SELECT widget_id, channel, COUNT(DISTINCT visitor_id) AS clicks
        FROM %i
        WHERE event_type = 'channel-click'
          AND channel IS NOT NULL AND channel <> ''
          AND created_at BETWEEN %s AND %s
        GROUP BY widget_id, channel
    ",
				$table_name,
				$start_date,
				$end_date
			)
		);
	}

	/**
	 * Gets total unique people who clicked ANY channel for each widget.
	 * Crucial for the 100% cap calculation.
	 */
	public function get_unique_clickers_per_widget( $start_date, $end_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query; caching not appropriate for real-time data.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
        SELECT widget_id, COUNT(DISTINCT visitor_id) AS total_clicks
        FROM %i
        WHERE event_type = 'channel-click'
          AND created_at BETWEEN %s AND %s
        GROUP BY widget_id
    ",
				$table_name,
				$start_date,
				$end_date
			),
			OBJECT_K
		);

		return $results;
	}

	public function get_clicks_across_all_widgets( $start_date, $end_date ) {
		if ( ! $start_date || ! $end_date ) {
			return 0;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$widget_clicks_query = $wpdb->prepare(
			"
		    SELECT COUNT(*)
		    FROM %i
		    WHERE event_type = 'widget-click'
		      AND created_at >= %s AND created_at <= %s
		",
			$table_name,
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare() above.
		return (int) $wpdb->get_var( $widget_clicks_query );
	}

	public function get_clicks_per_widget( $start_date, $end_date ) {
		if ( ! $start_date || ! $end_date ) {
			return array();
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$widget_clicks_query = $wpdb->prepare(
			"
		    SELECT
		        widget_id,
		        COUNT(*) AS total_clicks
		    FROM %i
		    WHERE event_type = 'channel-click'
		      AND widget_id IS NOT NULL
		      AND channel IS NOT NULL
		      AND channel <> ''
		      AND created_at BETWEEN %s AND %s
		    GROUP BY widget_id, channel
		    ORDER BY widget_id ASC, total_clicks DESC
		",
			$table_name,
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare() above.
		$results       = $wpdb->get_results( $widget_clicks_query, ARRAY_A );
		$click_results = array_column( $results, 'total_clicks', 'widget_id' );

		return $click_results;
	}

	public function get_unique_clicks_across_all_widgets( $start_date, $end_date ) {
		if ( ! $start_date || ! $end_date ) {
			return 0;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$widget_clicks_query = $wpdb->prepare(
			"
		    SELECT COUNT(DISTINCT visitor_id) AS clicks
		    FROM %i
		    WHERE event_type = 'widget-click'
		      AND created_at >= %s AND created_at <= %s
		",
			$table_name,
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare() above.
		return (int) $wpdb->get_var( $widget_clicks_query );
	}

	public function get_unique_clicks_by_widget( $start_date, $end_date ) {
		if ( ! $start_date || ! $end_date ) {
			return array();
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$widget_clicks_query = $wpdb->prepare(
			"
		    SELECT widget_id, COUNT(DISTINCT visitor_id) AS clicks
		    FROM %i
		    WHERE event_type = 'widget-click'
		      AND created_at >= %s AND created_at <= %s
		    GROUP BY widget_id
		",
			$table_name,
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare() above.
		$click_results = $wpdb->get_results( $widget_clicks_query );
		$click_results = array_column( $click_results, 'clicks', 'widget_id' );

		return $click_results;
	}

	public function get_unique_clicks_across_all_channels( $start_date, $end_date ) {
		if ( ! $start_date || ! $end_date ) {
			return 0;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$channel_clicks_query = $wpdb->prepare(
			"
		    SELECT COUNT(DISTINCT visitor_id) AS clicks
		    FROM %i
		    WHERE event_type = 'channel-click'
		      AND created_at >= %s AND created_at <= %s
		",
			$table_name,
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare() above.
		return (int) $wpdb->get_var( $channel_clicks_query );
	}

	public function get_unique_clicks_by_channel( $start_date, $end_date ) {
		if ( ! $start_date || ! $end_date ) {
			return array();
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$channel_clicks_query = $wpdb->prepare(
			"
	        SELECT channel, COUNT(DISTINCT visitor_id) AS clicks
	        FROM %i
	        WHERE event_type = 'channel-click'
	          AND channel IS NOT NULL
	          AND channel <> ''
	          AND created_at BETWEEN %s AND %s
	        GROUP BY channel
	    ",
			$table_name,
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare() above.
		$click_results = $wpdb->get_results( $channel_clicks_query );
		$click_results = array_column( $click_results, 'clicks', 'channel' );

		return $click_results;
	}

	public function get_unique_daily_data_by_widget( $start_date, $end_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		// 1. Execute the Query
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query; caching not appropriate for real-time data.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
	        SELECT
	            DATE(created_at) AS event_date,
	            widget_id,
	            COUNT(CASE WHEN event_type = 'widget-impression' THEN visitor_id END) AS impressions,
	            COUNT(DISTINCT CASE WHEN event_type = 'widget-impression' THEN visitor_id END) AS uniqueImpressions,
	            COUNT(DISTINCT CASE WHEN event_type = 'widget-click' THEN visitor_id END) AS uniqueVisitors,
	            COUNT(DISTINCT CASE WHEN (event_type = 'widget-click' AND has_parent_widget = '1') OR (event_type = 'channel-click' AND has_parent_widget = '0') THEN visitor_id END) AS uniqueClicks
	        FROM %i
	        WHERE event_type IN ('widget-impression', 'widget-click', 'channel-click')
	         AND created_at BETWEEN %s AND %s
	        GROUP BY event_date, widget_id
	        ORDER BY event_date ASC, widget_id ASC
	    ",
				$table_name,
				$start_date,
				$end_date
			)
		);

		if ( empty( $results ) ) {
			return array();
		}

		// 2. Identify all unique dates in the range
		$all_dates = array_unique( array_column( $results, 'event_date' ) );

		// 3. Structure the data by widget_id
		$structured_data = array();
		foreach ( $results as $row ) {
			$widget_id = $row->widget_id;

			if ( ! isset( $structured_data[ $widget_id ] ) ) {
				$structured_data[ $widget_id ] = array();
			}

			// Indexing by the date alias 'event_date'
			$structured_data[ $widget_id ][ $row->event_date ] = array(
				'date'              => $row->event_date,
				'impressions'       => (int) $row->impressions,
				'uniqueImpressions' => (int) $row->uniqueImpressions,
				'uniqueVisitors'    => (int) $row->uniqueVisitors,
				'uniqueClicks'      => (int) $row->uniqueClicks,
			);
		}

		// 4. Fill missing dates with zero values
		$final_output = array();
		foreach ( $structured_data as $id => $day_data ) {
			$final_output[ $id ] = array();
			foreach ( $all_dates as $date ) {
				if ( isset( $day_data[ $date ] ) ) {
					$final_output[ $id ][] = $day_data[ $date ];
				}
			}
		}

		return $final_output;
	}

	public function get_unique_daily_data_all_widgets( $start_date, $end_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		// 1. Execute the Query
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query; caching not appropriate for real-time data.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"
	        SELECT
	            DATE(created_at) AS event_date,
	            COUNT(CASE WHEN event_type = 'widget-impression' THEN visitor_id END) AS impressions,
	            COUNT(DISTINCT CASE WHEN event_type = 'widget-impression' THEN visitor_id END) AS uniqueImpressions,
	            COUNT(DISTINCT CASE WHEN event_type = 'widget-click' THEN visitor_id END) AS uniqueVisitors,
	            COUNT(DISTINCT CASE WHEN (event_type = 'widget-click' AND has_parent_widget = '1') OR (event_type = 'channel-click' AND has_parent_widget = '0') THEN visitor_id END) AS uniqueClicks
	        FROM %i
	        WHERE event_type IN ('widget-impression', 'widget-click', 'channel-click')
	          AND created_at BETWEEN %s AND %s
	        GROUP BY event_date
	        ORDER BY event_date ASC
	    ",
				$table_name,
				$start_date,
				$end_date
			)
		);

		if ( empty( $results ) ) {
			return array();
		}

		// 2. Index by date for gap-filling
		$data_by_date = array();
		foreach ( $results as $row ) {
			$data_by_date[ $row->event_date ] = array(
				'date'              => $row->event_date,
				'impressions'       => (int) $row->impressions,
				'uniqueImpressions' => (int) $row->uniqueImpressions,
				'uniqueVisitors'    => (int) $row->uniqueClicks > (int) $row->uniqueVisitors ? (int) $row->uniqueClicks : (int) $row->uniqueVisitors,
				'uniqueClicks'      => (int) $row->uniqueClicks,
			);
		}

		// 3. Fill missing dates with zero values
		$final_output = array();
		$current      = new \DateTime( $start_date );
		$last         = new \DateTime( $end_date );

		while ( $current <= $last ) {
			$date_string = $current->format( 'Y-m-d' );
			if ( isset( $data_by_date[ $date_string ] ) ) {
				$final_output[] = $data_by_date[ $date_string ];
			}
			$current->modify( '+1 day' );
		}

		return $final_output;
	}

	public function get_unique_channels_data_by_widget( $start_date, $end_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		// 1. Get all unique channels available in widgets
		$channels   = $this->get_unique_channel_names();
		$channels[] = 'all';

		// 2. Get all widgets that have at least one impression
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query; caching not appropriate for real-time data.
		$widgets = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT widget_id FROM %i WHERE event_type = 'widget-impression'",
				$table_name
			)
		);

		if ( empty( $channels ) || empty( $widgets ) ) {
			return array();
		}

		$queries = array();

		foreach ( $channels as $chan ) {
			if ( $chan === 'all' ) {
				$queries[] = $wpdb->prepare(
					"SELECT
		             widget_id,
		             'all' AS channel_name,
		             COUNT(CASE WHEN event_type = 'widget-impression' THEN 1 END) AS impressions,
		             COUNT(DISTINCT CASE WHEN event_type = 'widget-impression' THEN visitor_id END) AS uniqueImpressions,
		             COUNT(DISTINCT CASE WHEN (event_type = 'widget-click') OR (event_type = 'channel-click' AND has_parent_widget = '0') THEN visitor_id END) AS uniqueVisitors,
		             COUNT(DISTINCT CASE WHEN event_type = 'channel-click' THEN visitor_id END) AS uniqueClicks,
		             COUNT(CASE WHEN event_type = 'channel-click' THEN 1 END) AS totalcalc
		             FROM %i
		             WHERE event_type IN ('widget-impression', 'widget-click', 'channel-click')
		               AND created_at BETWEEN %s AND %s
		             GROUP BY widget_id",
					$table_name,
					$start_date,
					$end_date
				);
			} else {
				$like_val = '%' . $wpdb->esc_like( '"' . $chan . '"' ) . '%';

				$queries[] = $wpdb->prepare(
					"SELECT
		           widget_id,
		           %s AS channel_name,
		           COUNT(CASE WHEN event_type = 'widget-impression' THEN 1 END) AS impressions,
		           COUNT(DISTINCT CASE WHEN event_type = 'widget-impression' THEN visitor_id END) AS uniqueImpressions,
		           COUNT(DISTINCT CASE WHEN (event_type = 'widget-click' AND has_parent_widget = '1') OR ((event_type = 'channel-click' or event_type = 'widget-impression') AND has_parent_widget = '0') AND channels LIKE %s THEN visitor_id END) AS uniqueVisitors,
		           COUNT(DISTINCT CASE WHEN event_type = 'channel-click' AND channel = %s THEN visitor_id END) AS uniqueClicks,
		           COUNT(CASE WHEN event_type = 'channel-click' AND channel = %s THEN 1 END) AS total
		           FROM %i
		           WHERE (
		                 (event_type = 'widget-click' AND channels LIKE %s)
		              OR (event_type = 'channel-click' AND channel = %s)
		              OR (event_type = 'widget-impression' AND channels LIKE %s)
		           )
		           AND created_at BETWEEN %s AND %s
		           GROUP BY widget_id",
					$chan,
					$like_val,
					$chan,
					$chan,
					$table_name,
					$like_val,
					$chan,
					$like_val,
					$start_date,
					$end_date
				);
			}
		}

		$full_query = implode( ' UNION ALL ', $queries );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Each UNION fragment is individually prepared before concatenation.
		$results = $wpdb->get_results( $full_query );

		$output = array();

		// 3. Process Results
		foreach ( $results as $row ) {
			$w_id = $row->widget_id;
			$chan = $row->channel_name;

			$uVisitors = (int) $row->uniqueVisitors;
			$uClicks   = (int) $row->uniqueClicks;

			if ( $uClicks > $uVisitors ) {
				$uVisitors = $uClicks;
			}

			if ( ! isset( $output[ $w_id ] ) ) {
				$output[ $w_id ] = array();
			}

			$output[ $w_id ][ $chan ] = array(
				'impressions'       => (int) $row->impressions,
				'uniqueImpressions' => (int) $row->uniqueImpressions,
				'uniqueVisitors'    => $uVisitors,
				'uniqueClicks'      => $uClicks,
				'total'             => (int) $row->total,
				'clickRate'         => $uVisitors > 0 ? round( ( $uClicks / $uVisitors ) * 100, 2 ) : 0,
			);
		}

		return $output;
	}


	public function get_unique_data_by_device( $start_date, $end_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$query = $wpdb->prepare(
			"SELECT
			 widget_id,
             device_type AS device,
             COUNT(DISTINCT CASE WHEN event_type = 'widget-impression' THEN visitor_id END) AS uniqueVisitors,
             COUNT(DISTINCT CASE WHEN event_type = 'channel-click' THEN visitor_id END) AS uniqueClicks
						 FROM %i
             WHERE event_type IN ('widget-impression', 'widget-click', 'channel-click')
               AND created_at BETWEEN %s AND %s
             GROUP BY device_type, widget_id",
			$table_name,
			$start_date,
			$end_date
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with $wpdb->prepare() above.
		$results = $wpdb->get_results( $query );

		$output = array();

		// 3. Process Results
		foreach ( $results as $row ) {
			$device   = $row->device;
			$widgetId = $row->widget_id;

			$uVisitors = (int) $row->uniqueVisitors;
			$uClicks   = (int) $row->uniqueClicks;

			$output[ $widgetId ][ $device ] = array(
				'uniqueVisitors' => $uVisitors,
				'uniqueClicks'   => $uClicks,
				'clickRate'      => $uVisitors > 0 ? round( ( $uClicks / $uVisitors ) * 100, 2 ) : 0,
			);
		}

		return $output;
	}

	public function get_unique_channels_data_all_widgets( $start_date, $end_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$channels = $this->get_unique_channel_names();

		if ( empty( $channels ) ) {
			return array();
		}

		$queries = array();

		foreach ( $channels as $chan ) {
			$like_val = '%' . $wpdb->esc_like( '"' . $chan . '"' ) . '%';

			$queries[] = $wpdb->prepare(
				"SELECT
               %s AS channel_name,
               COUNT(CASE WHEN event_type = 'widget-impression' THEN visitor_id END) AS impressions,
               COUNT(DISTINCT CASE WHEN event_type = 'widget-impression' THEN visitor_id END) AS uniqueImpressions,
               COUNT(DISTINCT CASE WHEN event_type = 'widget-click' AND channels LIKE %s THEN visitor_id END) AS uniqueVisitors,
               COUNT(DISTINCT CASE WHEN event_type = 'channel-click' AND channel = %s THEN visitor_id END) AS uniqueClicks,
               COUNT(CASE WHEN event_type = 'channel-click' AND channel = %s THEN 1 END) AS total
           FROM %i
           WHERE ((event_type = 'widget-click' AND channels LIKE %s)
              OR (event_type = 'channel-click' AND channel = %s)
              OR (event_type = 'widget-impression' AND channels LIKE %s))
              AND created_at BETWEEN %s AND %s",
				$chan,
				$like_val,
				$chan,
				$chan,
				$table_name,
				$like_val,
				$chan,
				$like_val,
				$start_date,
				$end_date
			);
		}

		// Combine all channel queries
		$full_query = implode( ' UNION ALL ', $queries );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Each UNION fragment is individually prepared before concatenation.
		$results = $wpdb->get_results( $full_query );

		if ( empty( $results ) ) {
			return array();
		}

		// 2. Structure the output: Channel -> Totals
		$output = array();
		foreach ( $results as $row ) {
			$chan = $row->channel_name;
			if ( $row->uniqueClicks > $row->uniqueVisitors ) {
				$row->uniqueVisitors = $row->uniqueClicks;
			}

			$output[ $chan ] = array(
				'impressions'       => (int) $row->impressions,
				'uniqueImpressions' => (int) $row->uniqueImpressions,
				'uniqueVisitors'    => (int) $row->uniqueVisitors,
				'uniqueClicks'      => (int) $row->uniqueClicks,
				'clickRate'         => $row->uniqueVisitors > 0
					? round( ( $row->uniqueClicks / $row->uniqueVisitors ) * 100, 2 )
					: 0,
				'total'             => (int) $row->total,
			);
		}

		return $output;
	}

	public function get_unique_agents_table_data( $start_date, $end_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		/*      $agent_ids = $this->get_unique_agents_ids();

				if ( empty( $agent_ids ) ) {
					return [];
				}*/

		$queries  = array();
		$like_val = '%aiAgent%';

		$queries[] = $wpdb->prepare(
			"SELECT
			   agent_id as agentId,
			   COUNT(DISTINCT visitor_id) as uniqueVisitors
		   FROM %i
		   WHERE ((event_type = 'widget-click' AND has_parent_widget = '1')
			  OR ((event_type = 'channel-click' or event_type = 'widget-impression') and has_parent_widget = '0'))
			  AND channels like %s
			  AND created_at BETWEEN %s AND %s
			GROUP BY agent_id",
			$table_name,
			$like_val,
			$start_date,
			$end_date
		);

		$queries[] = $wpdb->prepare(
			"SELECT
			   'all' as agentId,
			   COUNT(DISTINCT visitor_id) as uniqueVisitors
		   FROM %i
		   WHERE ((event_type = 'widget-click' AND has_parent_widget = '1')
			  OR ((event_type = 'channel-click' or event_type = 'widget-impression') and has_parent_widget = '0'))
			  AND channels like %s
			  AND created_at BETWEEN %s AND %s",
			$table_name,
			$like_val,
			$start_date,
			$end_date
		);

		$full_query = implode( ' UNION ', $queries );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Each UNION fragment is individually prepared before concatenation.
		$results = $wpdb->get_results( $full_query );

		// data by agentId
		$data_by_agentId = array();
		foreach ( $results as $row ) {
			$data_by_agentId[ $row->agentId ] = array(
				'uniqueVisitors' => (int) $row->uniqueVisitors,
			);
		}

		return $data_by_agentId;
	}

	public function get_unique_daily_agents_data( $start_date, $end_date ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		$agent_ids = $this->get_unique_agents_ids();

		if ( empty( $agent_ids ) ) {
			return array();
		}

		$queries  = array();
		$like_val = '%aiAgent%';

		foreach ( $agent_ids as $agent_id ) {
			$queries[] = $wpdb->prepare(
				"(SELECT
					created_at as 'date',
					%s as agentId,
					COUNT(DISTINCT visitor_id) as uniqueVisitors
				FROM %i
				WHERE ((event_type = 'widget-click' AND has_parent_widget = '1')
					OR ((event_type = 'channel-click' or event_type = 'widget-impression') and has_parent_widget = '0'))
					AND channels like %s
					AND agent_id = %s
					AND created_at BETWEEN %s AND %s
				GROUP BY created_at
				ORDER BY created_at ASC)",
				$agent_id,
				$table_name,
				$like_val,
				$agent_id,
				$start_date,
				$end_date
			);
		}

		// All agents (True Unique Visitors across all agents)
		$queries[] = $wpdb->prepare(
			"(SELECT
		       created_at as 'date',
		       'all' as agentId,
		       COUNT(DISTINCT visitor_id) as uniqueVisitors
		    FROM %i WHERE ((event_type = 'widget-click' AND has_parent_widget = '1')
		       OR ((event_type = 'channel-click' or event_type = 'widget-impression') and has_parent_widget = '0'))
		       AND channels like %s
		       AND created_at BETWEEN %s AND %s
		    GROUP BY created_at)",
			$table_name,
			$like_val,
			$start_date,
			$end_date
		);

		$full_query = implode( ' UNION ', $queries );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Each UNION fragment is individually prepared before concatenation.
		$results = $wpdb->get_results( $full_query );

		$data = array();

		foreach ( $results as $row ) {
			$agent_id       = $row->agentId;
			$date           = $row->date;
			$uniqueVisitors = (int) $row->uniqueVisitors;

			if ( ! isset( $data[ $agent_id ] ) ) {
				$data[ $agent_id ] = array();
			}

			$data[ $agent_id ][] = array(
				'date'           => $date,
				'uniqueVisitors' => $uniqueVisitors,
			);
		}

		return $data;
	}

	public function get_unique_channel_names() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query; caching not appropriate for real-time data.
		$channels_results = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT channels FROM %i',
				$table_name
			)
		);

		$channels = array();
		foreach ( $channels_results as $chan_string ) {
			$temp = json_decode( $chan_string, true );
			if ( is_array( $temp ) ) {
				$channels = array_merge( $channels, $temp );
			}
		}
		$channels = array_unique( $channels );

		return $channels;
	}

	public function get_unique_agents_ids() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'siteleads_events';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics query; caching not appropriate for real-time data.
		$results = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT agent_id FROM %i WHERE agent_id IS NOT NULL',
				$table_name
			)
		);

		$agent_ids = array_unique( $results );

		return $agent_ids;
	}
}
