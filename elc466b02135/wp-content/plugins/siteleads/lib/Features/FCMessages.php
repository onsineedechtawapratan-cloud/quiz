<?php

namespace LeadGreet\Features;

use SiteLeads\Core\DataHelper;
use SiteLeads\Core\Singleton;

class FCMessages extends DataHelper {
	use Singleton;

	protected $settingPath = '';

	protected function __construct() {
		parent::__construct();

	}


}
