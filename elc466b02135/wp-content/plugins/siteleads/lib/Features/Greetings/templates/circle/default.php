<?php

use SiteLeads\Features\Greetings\GreetingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div <?php GreetingRenderer::renderContainerAttributes(); ?>>
	<?php GreetingRenderer::renderCloseButton(); ?>
	<style>
		<?php GreetingRenderer::printIDSelector(); ?> .siteleads-greeting__card {
			display: flex;
			flex-direction: column;
		}
		<?php GreetingRenderer::printIDSelector(); ?> .siteleads-greeting__card-image {
			max-height: calc(var(--sl-greeting-size) *0.5);
			overflow: hidden;
		}

		<?php GreetingRenderer::printIDSelector(); ?> img{
			width: 100%;
			height: auto;
			display: block;
		}

		<?php GreetingRenderer::printIDSelector(); ?> .siteleads-greeting__card-content {
			flex-grow: 1;
			display: block;
			padding-top: var(--sl-greeting-spacing,5px);
		}

		<?php GreetingRenderer::printIDSelector(); ?> .siteleads-greeting__card-title {
			padding-bottom: var(--sl-greeting-text-size,10px);
		}
	</style>
	<div class="siteleads-greeting__card sl-greeting-background sl-greeting-shadow sl-greeting-border sl-greeting-size">
		<?php GreetingRenderer::renderImage(); ?>
		<div class="siteleads-greeting__card-content">
			<span aria-hidden="true" class="siteleads-circle-shape-outside"></span>
			<?php GreetingRenderer::renderTitle(); ?>
			<?php GreetingRenderer::renderText(); ?>

			<?php if ( ! GreetingRenderer::getConfigValue( 'image' ) ) : ?>
				<span aria-hidden="true" class="siteleads-circle-shape-outside-reversed"></span>
			<?php endif; ?>
		</div>
	</div>
</div>
