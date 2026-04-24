<?php

namespace SiteLeads\Features;

use SiteLeads\Core\Singleton;
use SiteLeads\Features\Widgets\FCWidgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Features {

	use Singleton;

	protected function __construct() {
		if ( is_admin() ) {
			add_filter( 'upload_mimes', array( $this, 'allow_svg_uploads' ) );
		}

		if ( ! is_admin() ) {
			$isAjaxOrRest = defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX );
			if ( ! $isAjaxOrRest ) {
				FCWidgets::load()->addHooks();
			}
		}

		FCAnalytics::load();
		FCPreviewWidget::load();
		Rest::load();
		new FrontendWidgetLoadActions();
	}

	public function allow_svg_uploads( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';

		return $mimes;
	}
}

