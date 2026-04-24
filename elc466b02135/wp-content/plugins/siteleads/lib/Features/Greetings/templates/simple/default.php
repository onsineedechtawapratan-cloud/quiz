<?php

use SiteLeads\Features\Greetings\GreetingRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div <?php GreetingRenderer::renderContainerAttributes(); ?>>
	<style>
		<?php GreetingRenderer::printIDSelector(); ?> .sl-greeting-spacing {
			padding-top:0.5rem;
			padding-bottom:0.5rem;
		}
	</style>
	<div class="siteleads-greeting__card">
		<div class="siteleads-greeting__card-content sl-greeting-background sl-greeting-spacing sl-greeting-radius sl-greeting-shadow" >
			<?php GreetingRenderer::renderText(); ?>
		</div>
	</div>
</div>
