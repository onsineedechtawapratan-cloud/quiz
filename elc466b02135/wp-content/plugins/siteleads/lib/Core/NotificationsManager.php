<?php

namespace SiteLeads\Core;

use DateTime;
use IlluminateAgnostic\Arr\Support\Arr;

class NotificationsManager {

	private static $remote_data_url_base = 'https://siteleads.ai/wp-json/wp/v2/notification';


	const DISSMISSABLE_NOTICE_OPTION_KEY = '_siteleads_dismissible_notices';

	public static function load() {
		add_action( 'admin_init', array( NotificationsManager::class, 'init' ) );
		add_action( 'wp_ajax_siteleads-dismissable-notice--dismiss', array( NotificationsManager::class, '_dismiss_dismissable_notice' ) );
		if ( ! wp_next_scheduled( NotificationsManager::class . '::init' ) ) {
			wp_schedule_event( time(), 'twicedaily', NotificationsManager::class . '::init' );
		}
	}

	/**
	 * Checks if this WordPress instances is declared as a development environment.
	 * Relies on the `SITELEADS_NOTIFICATIONS_DEV_MODE` constant.
	 *
	 * @return bool
	 */
	private static function isDevMode() {
		return ( defined( 'SITELEADS_NOTIFICATIONS_DEV_MODE' ) && SITELEADS_NOTIFICATIONS_DEV_MODE );
	}

	/**
	 * Verifies the data and displays remote notifications accordingly.
	 *
	 * @return void
	 */
	public static function init() {

		// check if we have cached data in transient
		$notifications = get_transient( static::getTransientKey() );

		if ( $notifications === false || self::isDevMode() ) {
			// No notifications, try to get them from remote and cache them.
			static::prepareRetrieveRemoteNotifications();
		}

		static::displayNotifications( $notifications );

		add_action( 'wp_ajax_siteleads-remote-notifications-retrieve', array( NotificationsManager::class, 'updateNotificationsData' ) );
	}

	/**
	 * Adds a JavaScript code which fetches notifications asynchronously.
	 *
	 * @return void
	 */
	public static function prepareRetrieveRemoteNotifications() {

		add_action(
			'admin_footer',
			function () {
				$fetch_url = add_query_arg(
					array(
						'action'   => 'siteleads-remote-notifications-retrieve',
						'_wpnonce' => wp_create_nonce( 'siteleads-remote-notifications-retrieve-nonce' ),

					),
					admin_url( 'admin-ajax.php' )
				); ?>
					<script>
						window.fetch("<?php echo esc_url_raw( $fetch_url ); ?>")
					</script>
					<?php
			}
		);
	}

	/**
	 * Retrieves notifications and saves them in a transient.
	 *
	 * @return void
	 */
	public static function updateNotificationsData() {
		check_ajax_referer( 'siteleads-remote-notifications-retrieve-nonce' );

		$url = add_query_arg(
			array(
				'_fields'                 => 'acf,id,modified',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'                => 'license_type',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'              => apply_filters( 'siteleads/notifications/license_type', 'free' ),
				'siteleads_version'       => SITELEADS_VERSION,
				'siteleads_build'         => SITELEADS_BUILD_NUMBER,
				'siteleads_theme_version' => wp_get_theme()->get( 'Version' ),
				'template'                => get_template(),
				'stylesheet'              => get_stylesheet(),
				'source'                  => Flags::get( 'start_source', 'other' ),
				'activated_on'            => Flags::get( 'activation_time', '' ),
				'pro_activated_on'        => Flags::get( 'pro_activation_time', '' ),
			),
			self::$remote_data_url_base
		);

		$data = wp_remote_get( $url );

		$code = wp_remote_retrieve_response_code( $data );
		$body = wp_remote_retrieve_body( $data );

		$posts = json_decode( $body, true );

		if ( $code !== 200 ) {
			wp_send_json_error( $code );
		}

		$notifications = array();

		foreach ( $posts as $post ) {
			$notifications[ $post['id'] ] = $post;
		}

		$done = set_transient( static::getTransientKey(), $notifications, DAY_IN_SECONDS );

		wp_send_json_success( $done );
	}

	/**
	 * Adds the stack of notifications for display using `siteleads_add_dismissable_notice`.
	 *
	 * @param array $notifications
	 * @return void
	 */
	private static function displayNotifications( $notifications ) {

		if ( empty( $notifications ) ) {
			return;
		}

		foreach ( $notifications as $notification ) {
			$params       = $notification['acf'];
			$params['id'] = $notification['id'];
			$modified     = Arr::get( $notification, 'modified', null );

			if ( $params['dev'] === true && ! self::isDevMode() ) {
				continue;
			}

			if ( ! self::isTimeToDisplay( $params ) ) {
				continue;
			}

			$classnames    = 'siteleads-remote-notification';
			$allowed_types = array( 'info', 'warning', 'error', 'success' );

			if ( ! empty( $params['type'] ) && in_array( $params['type'], $allowed_types ) ) {
				$classnames .= ' notice-' . $params['type'] . ' siteleads-remote-notification-' . $params['type'];
			}

			// $notice_key = 'siteleads-remote-notice-' . $params['id'];
			$notice_key = 'sl-remote-notice-' . $params['id'];

			if ( $modified ) {
				$notice_key .= '-' . strtotime( $modified );
			}

			if ( self::isDevMode() ) {
				$notice_key .= '-' . time();
			}

			self::addDismissableNotice(
				$notice_key,
				array( NotificationsManager::class, 'displayNotification' ),
				0,
				$params,
				$classnames
			);
		}
	}

	/**
	 * Prints the HTML of a notification for the given params.
	 *
	 * @param $params
	 * @return void
	 */
	public static function displayNotification( $params ) {
		$link  = $params['primary_link'];
		$slink = $params['secondary_link'];

		$args = array(
			'utm_theme'            => get_template(),
			'utm_childtheme'       => get_stylesheet(),
			'utm_campaign'         => 'wp-notice',
			'utm_medium'           => 'wp',
			'utm_install_source'   => Flags::get( 'start_source', 'other' ),
			'utm_activated_on'     => Flags::get( 'activation_time', '' ),
			'utm_pro_activated_on' => Flags::get( 'pro_activation_time', '' ),
		);

		if ( ! empty( $link ) ) {
			$link['url'] = add_query_arg( $args, $link['url'] );
		}

		if ( ! empty( $slink ) ) {
			$slink['url'] = add_query_arg( $args, $slink['url'] );
		}

		wp_enqueue_script( 'wp-util' ); // make sure to enqueue the admin ajax functions
		wp_enqueue_style( 'siteleads-notifications', siteleads_url( 'static/admin-pages/notifications.css' ), array(), SITELEADS_VERSION );
		?>
		<div class="siteleads-remote-notification-wrapper" id="siteleads-remote-notification-<?php echo esc_attr( $params['id'] ); ?>">
			<div class="siteleads-remote-notification-icon">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wp_kses_post( SITELEADS_LOGO_SVG );
				?>
			</div>
			<?php if ( ! empty( $params['message'] ) ) { ?>
				<div class="siteleads-remote-notification-message">
				<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo wpautop( $params['message'] );
				?>
					</div>
			<?php } ?>
			<div class="siteleads-remote-notification-buttons">
				<?php if ( ! empty( $link ) ) { ?>
					<a target="_blank" href="<?php echo esc_url( $link['url'] ); ?>" class="siteleads-remote-notification-primary"><?php echo esc_html( $link['title'] ); ?></a>
					<?php
				}

				if ( ! empty( $slink ) ) {
					?>
					<a target="_blank" href="<?php echo esc_url( $slink['url'] ); ?>" class="siteleads-remote-notification-secondary"><?php echo esc_html( $slink['title'] ); ?></a>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Verify if the notification checks the time requirements.
	 *
	 * @param array $params Notification parameters.
	 * @return bool
	 */
	private static function isTimeToDisplay( array $params ) {

		if ( $params['has_time_boundary'] === true ) {
			return self::inTimeBoundaries( $params['start_date'], $params['date_end'] );
		}

		$install_time = Flags::get( 'activation_time', time() );

		$install_time = apply_filters( 'siteleads/notifications/install_time', $install_time );

		$show_after = strtotime( '+' . $params['after'] . ' days', $install_time );
		$time       = new DateTime( 'NOW' );

		if ( $show_after <= $time->getTimeStamp() ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if the current time is between a given $start and $end date.
	 * If $start or $end are null that generally means there is no restrain for that edge.
	 *
	 * @param $start
	 * @param $end
	 * @return bool
	 */
	private static function inTimeBoundaries( $start, $end ) {
		$time       = new DateTime( 'today' );
		$start_date = \DateTime::createFromFormat( 'Ymd', $start );

		if ( $start === null || $start_date && $start_date <= $time ) {
			$end_date = \DateTime::createFromFormat( 'Ymd', $end );

			if ( $end === null || $end_date && $time <= $end_date ) {
				return true;
			}
		}

		return false;
	}

	private static function getTransientKey() {
		$transient = apply_filters( 'siteleads/notifications/transient_key', 'siteleads_remote_notifications' );
		return $transient;
	}


	/**
	 * This function adds an action which hooks into admin_notices and creates a dismissible notices via AJAX.
	 *
	 * @param string $name
	 * @param callable $callback
	 * @param integer $repeat_after - use 0 to disable the reappearance time limit
	 * @param array $params
	 * @param string $classes
	 * @return void
	 */
	public static function addDismissableNotice( $name, $callback, $repeat_after = 0, $params = array(), $classes = '' ) {

		if ( self::dismissable_notice_is_dismissed( $name, $repeat_after ) ) {
			return;
		}

		add_action(
			'admin_notices',
			function () use ( $name, $params, $callback, $classes ) {
				$id   = 'siteleads-notice-' . uniqid();
				$data = array(
					'id'   => $id,
					'name' => $name,
				);
				?>
					<div data-siteleads-notice-id="<?php echo esc_attr( $id ); ?>" class="notice is-dismissible <?php echo esc_attr( $classes ); ?>">
						<?php call_user_func( $callback, $params ); ?>
					<script>
						jQuery(function($){
								var data =<?php	echo wp_json_encode( $data ); ?>;
							$(document).on('click','[data-siteleads-notice-id=' + data.id + '] .notice-dismiss',function(){
								wp.ajax.post('siteleads-dismissable-notice--dismiss',{
									siteleads_notice_name:data.name,
										_wpnonce: '<?php echo esc_html( wp_create_nonce( 'siteleads-dismissable-notice--dismiss-nonce' ) ); ?>'
								});
							});
						});
					</script>
				</div>

					<?php

					$notices          = get_option( NotificationsManager::DISSMISSABLE_NOTICE_OPTION_KEY, array() );
					$notices[ $name ] = array( 'dismiss_time' => 0 );
					update_option( NotificationsManager::DISSMISSABLE_NOTICE_OPTION_KEY, $notices );
			}
		);
	}

	/**
	 * This is an ajax callback which marks notices as dismissed.
	 *
	 * @return void
	 */
	public static function _dismiss_dismissable_notice() {
		check_ajax_referer( 'siteleads-dismissable-notice--dismiss-nonce' );
		$notice  = Arr::get( $_REQUEST, 'siteleads_notice_name', false );
		$notices = get_option( NotificationsManager::DISSMISSABLE_NOTICE_OPTION_KEY, array() );

		if ( $notice && Arr::exists( $notices, $notice ) ) {
			$notices[ $notice ] = array( 'dismiss_time' => time() );
			update_option( NotificationsManager::DISSMISSABLE_NOTICE_OPTION_KEY, $notices );
		}
	}

	/**
	 * Checks if a named SiteLeads notice is dismissed at this moment.
	 * @param $name
	 * @param $repeat_after
	 * @return bool
	 */
	public static function dismissable_notice_is_dismissed( $name, $repeat_after = 0 ) {
		$notices = get_option( NotificationsManager::DISSMISSABLE_NOTICE_OPTION_KEY, array() );

		if ( Arr::has( $notices, $name ) ) {

			$dismissed_time = Arr::get( $notices, "{$name}.dismiss_time", 0 );

			if ( $repeat_after === 0 && $dismissed_time !== 0 ) {
				return true;
			}

			if ( $dismissed_time && time() < $dismissed_time + $repeat_after ) {
				return true;
			}
		}
		return false;
	}
}
