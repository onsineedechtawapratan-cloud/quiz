<?php

namespace SiteLeads;

use SiteLeads\Admin\Admin;
use SiteLeads\Core\Activation;
use SiteLeads\Core\AssetsRegistry;
use SiteLeads\Core\NotificationsManager;
use SiteLeads\Core\Singleton;
use SiteLeads\Features\Features;
use SiteLeads\Core\SvgFilter;



class Bootstrap {
	use Singleton;

	protected function __construct() {

		Activation::load();
		Admin::load();
		AssetsRegistry::load();
		SvgFilter::load();
		Features::load();
		NotificationsManager::load();

		// Load Pro version if exists.
		$skip_pro_loaded = defined( 'SITELEADS_SKIP_PRO_LOADED' ) && SITELEADS_SKIP_PRO_LOADED;
		if ( file_exists( SITELEADS_ROOT_DIR . 'pro/bootstrap.php' ) && ! $skip_pro_loaded ) {
			require_once SITELEADS_ROOT_DIR . 'pro/bootstrap.php';
		}
	}


	public static function load() {

		return self::getInstance();
	}
}
