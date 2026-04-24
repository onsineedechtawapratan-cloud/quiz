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
		<?php GreetingRenderer::printIDSelector(); ?> img {
			border-radius: 4px;
			width: 100%;
		}
	</style>
	<?php GreetingRenderer::renderCloseButton(); ?>
	<div class="sl-overflow-hidden sl-greeting-radius">
		<div class=" siteleads-greeting__card  sl-greeting-spacing sl-greeting-width ">
			<div class="siteleads-greeting__card-content sl-greeting-radius " >
				<?php GreetingRenderer::renderImage(); ?>
				<?php GreetingRenderer::renderTitle(); ?>
				<?php GreetingRenderer::renderText(); ?>
			</div>
		</div>
	</div>
</div>
