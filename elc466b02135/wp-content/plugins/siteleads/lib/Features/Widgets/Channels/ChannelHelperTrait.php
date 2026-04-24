<?php

namespace SiteLeads\Features\Widgets\Channels;

trait ChannelHelperTrait {

	public function getChannelProp( $path, $defaultValue = null ) {
		$name = $this->getChannelName();

		return $this->getProp( "channels.{$name}.{$path}", $defaultValue );

	}

	abstract protected function getProp( $path, $defaultValue = null);
	abstract protected function getChannelName();
}
