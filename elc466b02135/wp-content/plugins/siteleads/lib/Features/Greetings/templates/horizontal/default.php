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
			display: flex;
			flex-direction: row;
		}

		<?php GreetingRenderer::printIDSelector(); ?> img {
			max-width: min(160px, var(--sl-greeting-size) / 4);
			border-radius: 4px;
		}

		.--siteleads-root-alignment-left <?php GreetingRenderer::printIDSelector(); ?> .siteleads-greeting__card-content {
			flex-direction: row-reverse;
			gap: 10px;
		}
	</style>
	<?php GreetingRenderer::renderCloseButton(); ?>
	<div class="siteleads-greeting__card  sl-greeting-spacing sl-greeting-width">

		<div class="siteleads-greeting__card-content" >
			<?php GreetingRenderer::renderImage(); ?>
			<div class="sl-content-column">
				<?php GreetingRenderer::renderTitle(); ?>
				<?php GreetingRenderer::renderText(); ?>
			</div>
		</div>
	</div>
</div>
