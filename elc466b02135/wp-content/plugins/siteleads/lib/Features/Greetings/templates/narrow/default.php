<?php

use SiteLeads\Features\Greetings\GreetingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div
<?php
GreetingRenderer::renderContainerAttributes(
	array(
		'sl-greeting-background',
		'sl-greeting-radius',
		'sl-greeting-shadow',
		'sl-greeting-border',
	)
);
?>
>

<style>
		<?php GreetingRenderer::printIDSelector(); ?> .siteleads-greeting__card-content {
			padding: 0px 16px;
		}

		<?php GreetingRenderer::printIDSelector(); ?> .siteleads-greeting__card-image img{
			max-width: calc(var(--sl-base-fab-size)* 0.9);
			max-height: calc(var(--sl-base-fab-size)* 0.9);
		}
	</style>
	<div class="spaced siteleads-greeting__card">
		<div class="siteleads-greeting__card-content " >
			<?php GreetingRenderer::renderImage(); ?>
			<?php GreetingRenderer::renderText(); ?>
		</div>
	</div>
</div>
